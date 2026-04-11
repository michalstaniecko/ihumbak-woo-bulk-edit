<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit;

use IhumbakWooBulkEdit\Admin\Menu;
use IhumbakWooBulkEdit\Admin\AssetsLoader;
use IhumbakWooBulkEdit\Api\FieldsController;
use IhumbakWooBulkEdit\Api\ProductsController;
use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Persistence\BatchSaver;
use IhumbakWooBulkEdit\Persistence\ProductSaver;
use IhumbakWooBulkEdit\Security\CapabilityChecker;
use IhumbakWooBulkEdit\Security\RateLimiter;

/**
 * Main plugin class — singleton entry point.
 */
final class Plugin
{
    private static ?self $instance = null;

    private Container $container;

    private bool $booted = false;

    private function __construct()
    {
        $this->container = new Container();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Boot the plugin — register services, hooks, etc.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        $this->registerServices();
        $this->registerHooks();

        load_plugin_textdomain(
            'ihumbak-woo-bulk-edit',
            false,
            dirname(IWBE_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Plugin activation.
     */
    public function activate(): void
    {
        // DB migrations will be handled by DatabaseMigrator (Issue #25).
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate(): void
    {
        // Clean up scheduled events if any.
        wp_clear_scheduled_hook('wbm_changelog_rotation');
    }

    private function registerServices(): void
    {
        $this->container->set(Menu::class, static fn (Container $c): Menu => new Menu());

        $this->container->set(
            AssetsLoader::class,
            static fn (Container $c): AssetsLoader => new AssetsLoader()
        );

        $this->container->set(
            FieldRegistry::class,
            static fn (Container $c): FieldRegistry => new FieldRegistry()
        );

        $this->container->set(
            CapabilityChecker::class,
            static fn (Container $c): CapabilityChecker => new CapabilityChecker()
        );

        $this->container->set(
            RateLimiter::class,
            static fn (Container $c): RateLimiter => new RateLimiter()
        );

        $this->container->set(
            FieldsController::class,
            static fn (Container $c): FieldsController => new FieldsController(
                $c->get(FieldRegistry::class),
                $c->get(CapabilityChecker::class),
            )
        );

        $this->container->set(
            ProductSaver::class,
            static fn (Container $c): ProductSaver => new ProductSaver(
                $c->get(FieldRegistry::class),
            )
        );

        $this->container->set(
            BatchSaver::class,
            static fn (Container $c): BatchSaver => new BatchSaver(
                $c->get(ProductSaver::class),
            )
        );

        $this->container->set(
            ProductsController::class,
            static fn (Container $c): ProductsController => new ProductsController(
                $c->get(FieldRegistry::class),
                $c->get(CapabilityChecker::class),
                $c->get(RateLimiter::class),
                $c->get(BatchSaver::class),
            )
        );
    }

    private function registerHooks(): void
    {
        /** @var Menu $menu */
        $menu = $this->container->get(Menu::class);
        add_action('admin_menu', [$menu, 'register']);

        /** @var AssetsLoader $assets */
        $assets = $this->container->get(AssetsLoader::class);
        add_action('admin_enqueue_scripts', [$assets, 'enqueue']);

        add_action('rest_api_init', function (): void {
            /** @var FieldsController $fields */
            $fields = $this->container->get(FieldsController::class);
            $fields->register_routes();

            /** @var ProductsController $products */
            $products = $this->container->get(ProductsController::class);
            $products->register_routes();
        });
    }
}
