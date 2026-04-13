<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit;

use IhumbakWooBulkEdit\Admin\Menu;
use IhumbakWooBulkEdit\Admin\AssetsLoader;
use IhumbakWooBulkEdit\Api\ChangelogController;
use IhumbakWooBulkEdit\Api\FieldsController;
use IhumbakWooBulkEdit\Api\ProductsController;
use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Operations\BulkDelete;
use IhumbakWooBulkEdit\Operations\BulkDuplicate;
use IhumbakWooBulkEdit\Persistence\BatchSaver;
use IhumbakWooBulkEdit\Persistence\ChangeLogRepository;
use IhumbakWooBulkEdit\Persistence\DatabaseMigrator;
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
        // Ensure services are registered even if boot() hasn't run yet
        // (register_activation_hook runs before plugins_loaded).
        $this->registerServices();

        /** @var DatabaseMigrator $migrator */
        $migrator = $this->container->get(DatabaseMigrator::class);
        $migrator->migrate();

        // Schedule change-log rotation (daily).
        if (! wp_next_scheduled('wbm_changelog_rotation')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'wbm_changelog_rotation');
        }
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate(): void
    {
        // Clean up scheduled events if any.
        wp_clear_scheduled_hook('wbm_changelog_rotation');
    }

    /**
     * Cron handler: delete change-log entries older than the configured retention.
     */
    public function rotateChangeLog(): void
    {
        $days = (int) get_option('wbm_changelog_retention_days', 90);

        /** @var ChangeLogRepository $repo */
        $repo = $this->container->get(ChangeLogRepository::class);
        $repo->purgeOlderThan($days);
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
            DatabaseMigrator::class,
            static fn (Container $c): DatabaseMigrator => new DatabaseMigrator()
        );

        $this->container->set(
            ChangeLogRepository::class,
            static fn (Container $c): ChangeLogRepository => new ChangeLogRepository(
                $c->get(DatabaseMigrator::class),
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
                $c->get(ChangeLogRepository::class),
            )
        );

        $this->container->set(
            BulkDelete::class,
            static fn (Container $c): BulkDelete => new BulkDelete(
                $c->get(ChangeLogRepository::class),
            )
        );

        $this->container->set(
            BulkDuplicate::class,
            static fn (Container $c): BulkDuplicate => new BulkDuplicate(
                $c->get(ChangeLogRepository::class),
            )
        );

        $this->container->set(
            ProductsController::class,
            static fn (Container $c): ProductsController => new ProductsController(
                $c->get(FieldRegistry::class),
                $c->get(CapabilityChecker::class),
                $c->get(RateLimiter::class),
                $c->get(BatchSaver::class),
                $c->get(BulkDelete::class),
                $c->get(BulkDuplicate::class),
            )
        );

        $this->container->set(
            ChangelogController::class,
            static fn (Container $c): ChangelogController => new ChangelogController(
                $c->get(ChangeLogRepository::class),
                $c->get(FieldRegistry::class),
                $c->get(CapabilityChecker::class),
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

            /** @var ChangelogController $changelog */
            $changelog = $this->container->get(ChangelogController::class);
            $changelog->register_routes();
        });

        // Ensure schema is up-to-date on upgrade (no-op if versions match).
        /** @var DatabaseMigrator $migrator */
        $migrator = $this->container->get(DatabaseMigrator::class);
        $migrator->maybeMigrate();

        // Cron handler for change-log rotation.
        add_action('wbm_changelog_rotation', [$this, 'rotateChangeLog']);

        // Safety net: if the rotation event was never scheduled (e.g. the plugin
        // was upgraded in place without re-running the activation hook), schedule it now.
        if (! wp_next_scheduled('wbm_changelog_rotation')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'wbm_changelog_rotation');
        }
    }
}
