<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields;

use IhumbakWooBulkEdit\Fields\Core\CustomTaxonomyField;
use IhumbakWooBulkEdit\Fields\Core\BackordersField;
use IhumbakWooBulkEdit\Fields\Core\ButtonTextField;
use IhumbakWooBulkEdit\Fields\Core\CatalogVisibilityField;
use IhumbakWooBulkEdit\Fields\Core\CategoriesField;
use IhumbakWooBulkEdit\Fields\Core\CrossSellsField;
use IhumbakWooBulkEdit\Fields\Core\DateCreatedField;
use IhumbakWooBulkEdit\Fields\Core\DescriptionField;
use IhumbakWooBulkEdit\Fields\Core\DownloadableField;
use IhumbakWooBulkEdit\Fields\Core\DownloadExpiryField;
use IhumbakWooBulkEdit\Fields\Core\DownloadLimitField;
use IhumbakWooBulkEdit\Fields\Core\ExternalUrlField;
use IhumbakWooBulkEdit\Fields\Core\FeaturedField;
use IhumbakWooBulkEdit\Fields\Core\GalleryField;
use IhumbakWooBulkEdit\Fields\Core\HeightField;
use IhumbakWooBulkEdit\Fields\Core\LengthField;
use IhumbakWooBulkEdit\Fields\Core\ManageStockField;
use IhumbakWooBulkEdit\Fields\Core\MenuOrderField;
use IhumbakWooBulkEdit\Fields\Core\NameField;
use IhumbakWooBulkEdit\Fields\Core\PurchaseNoteField;
use IhumbakWooBulkEdit\Fields\Core\RegularPriceField;
use IhumbakWooBulkEdit\Fields\Core\ReviewsAllowedField;
use IhumbakWooBulkEdit\Fields\Core\SalePriceField;
use IhumbakWooBulkEdit\Fields\Core\ShippingClassField;
use IhumbakWooBulkEdit\Fields\Core\ShortDescriptionField;
use IhumbakWooBulkEdit\Fields\Core\SkuField;
use IhumbakWooBulkEdit\Fields\Core\SlugField;
use IhumbakWooBulkEdit\Fields\Core\SoldIndividuallyField;
use IhumbakWooBulkEdit\Fields\Core\StatusField;
use IhumbakWooBulkEdit\Fields\Core\StockQuantityField;
use IhumbakWooBulkEdit\Fields\Core\TagsField;
use IhumbakWooBulkEdit\Fields\Core\ThumbnailField;
use IhumbakWooBulkEdit\Fields\Core\UpsellsField;
use IhumbakWooBulkEdit\Fields\Core\VirtualField;
use IhumbakWooBulkEdit\Fields\Core\WeightField;
use IhumbakWooBulkEdit\Fields\Core\WidthField;

/**
 * Registry of all available product fields.
 */
final class FieldRegistry
{
    /** @var array<string, FieldInterface> */
    private array $fields = [];

    public function __construct()
    {
        $this->registerCoreFields();
    }

    public function register(FieldInterface $field): void
    {
        $this->fields[$field->getKey()] = $field;
    }

    public function get(string $key): ?FieldInterface
    {
        return $this->fields[$key] ?? null;
    }

    /**
     * @return array<string, FieldInterface>
     */
    public function getAll(): array
    {
        return $this->fields;
    }

    /**
     * @return array<string, FieldInterface>
     */
    public function getEditable(): array
    {
        return array_filter($this->fields, static fn (FieldInterface $f): bool => $f->isEditable());
    }

    /**
     * @return array<string, FieldInterface>
     */
    public function getFilterable(): array
    {
        return array_filter($this->fields, static fn (FieldInterface $f): bool => $f->isFilterable());
    }

    /**
     * @return array<string, FieldInterface>
     */
    public function getSortable(): array
    {
        return array_filter($this->fields, static fn (FieldInterface $f): bool => $f->isSortable());
    }

    /**
     * Convert all fields to array for REST API.
     *
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_values(
            array_map(
                static fn (FieldInterface $f): array => $f->toArray(),
                $this->fields
            )
        );
    }

    /**
     * Auto-discover custom taxonomies registered for the 'product' post type
     * and register them as filterable taxonomy fields.
     *
     * Only taxonomies that are public or show_ui are exposed. Taxonomies already
     * mapped in TaxonomyMap (built-in fields) are skipped.
     *
     * This method is a no-op when get_object_taxonomies() is not available
     * (e.g. in unit-test environments without WordPress loaded).
     *
     * Must be called after the 'init' hook fires so that all taxonomies have
     * been registered by WooCommerce and third-party plugins.
     */
    public function discoverCustomTaxonomies(): void
    {
        if (!function_exists('get_object_taxonomies')) {
            return;
        }

        /** @var array<string, \WP_Taxonomy> $taxonomies */
        $taxonomies = get_object_taxonomies('product', 'objects');

        foreach ($taxonomies as $taxonomy) {
            // Skip if already covered by a built-in field mapping.
            if (TaxonomyMap::fieldForTaxonomy($taxonomy->name) !== null) {
                continue;
            }

            // Skip non-public, non-show_ui taxonomies (internal WP taxonomies
            // such as product_type and product_visibility).
            if (!$taxonomy->public && !$taxonomy->show_ui) {
                continue;
            }

            // Use the taxonomy slug as field key.
            $fieldKey = $taxonomy->name;

            // Skip if a field with this key is already registered (prevents
            // clobbering any core field that shares a slug name).
            if (isset($this->fields[$fieldKey])) {
                continue;
            }

            // Derive human-readable label; fall back to the slug.
            $label = $taxonomy->labels->name ?? $taxonomy->label ?? $taxonomy->name;
            if (empty($label)) {
                $label = $taxonomy->name;
            }

            TaxonomyMap::register($fieldKey, $taxonomy->name);
            $this->register(new CustomTaxonomyField($fieldKey, $label, $taxonomy->name));
        }
    }

    private function registerCoreFields(): void
    {
        $coreFields = [
            // Identifiers & basic info.
            new NameField(),
            new SlugField(),
            new SkuField(),
            new StatusField(),
            new CatalogVisibilityField(),
            new FeaturedField(),
            new DateCreatedField(),

            // Descriptions.
            new DescriptionField(),
            new ShortDescriptionField(),

            // Pricing.
            new RegularPriceField(),
            new SalePriceField(),

            // Stock & inventory.
            new ManageStockField(),
            new StockQuantityField(),
            new BackordersField(),
            new SoldIndividuallyField(),

            // Dimensions & shipping.
            new WeightField(),
            new LengthField(),
            new WidthField(),
            new HeightField(),
            new ShippingClassField(),

            // Downloadable & virtual.
            new VirtualField(),
            new DownloadableField(),
            new DownloadLimitField(),
            new DownloadExpiryField(),

            // External product.
            new ExternalUrlField(),
            new ButtonTextField(),

            // Taxonomy.
            new CategoriesField(),
            new TagsField(),

            // Images.
            new ThumbnailField(),
            new GalleryField(),

            // Linked products.
            new CrossSellsField(),
            new UpsellsField(),

            // Meta.
            new PurchaseNoteField(),
            new ReviewsAllowedField(),
            new MenuOrderField(),
        ];

        foreach ($coreFields as $field) {
            $this->register($field);
        }
    }
}
