# CLAUDE.md — ihumbak-woo-bulk-edit

## Project Overview

WordPress/WooCommerce plugin for bulk editing products. Hybrid approach: "preview & commit" UX (like PW Bulk Edit) with SQL-like filtering and formula engine (like WP Sheet Editor). Audit log with rollback is the key differentiator.

**Working name:** `ihumbak-woo-bulk-edit`
**Repo:** `git@github.com:michalstaniecko/ihumbak-woo-bulk-edit.git`
**Spec:** `docs/specyfikacja-bulk-edit-woocommerce.md`
**Version:** 0.1.0 (Backend MVP)

## Implementation Status

Track progress in GitHub Issues: https://github.com/michalstaniecko/ihumbak-woo-bulk-edit/issues

## Target Environment

- PHP 8.1+
- WordPress 6.4+
- WooCommerce 8.0+
- MySQL 5.7+ / MariaDB 10.6+
- HPOS compatible (`FeaturesUtil::declare_compatibility`)

## Tech Stack

### Backend
- PHP 8.1+ with strict typing
- WP REST API under namespace `ihumbak-woo-bulk-edit/v1`
- Custom SQL via `$wpdb->prepare()` (NOT `WP_Query` for main product filtering — performance)
- DI container, PSR-4 autoloading via Composer

### Frontend
- React 18 + TypeScript (strict, no `any`)
- `@wordpress/scripts` v30 (webpack) for build pipeline
- React Query (TanStack Query) ^5.0 for server state
- Zustand ^5.0 for local state (changes, undo/redo)
- TanStack Table ^8.0 + react-window ^2.2 for virtualized grid
- Zod ^3.23 for API response validation
- `@wordpress/i18n` ^5.0 for translations

## Architectural Decisions

- **Custom DI container** — lightweight, no external library; factory-based registration in `Container.php`
- **Native SQL over WP_Query** — `QueryBuilder` builds raw SQL for performance on large product sets
- **Dynamic LEFT JOINs** — meta tables joined only when meta fields appear in filters/sort; reduces query cost
- **Operator registry pattern** — each SQL operator is a class implementing `OperatorInterface`; extensible via `OperatorRegistry`
- **FieldType as PHP enum** — 11 types (Text, Textarea, Number, Price, Integer, Select, Boolean, Date, Taxonomy, Image, Gallery, CustomMeta)
- **Rate limiting via WP transients** — simple, no external storage dependency
- **REST permission model** — relies on `permission_callback` with `wp_rest` nonce (standard WP REST approach)
- **@wordpress/scripts over Vite** — chose wp-scripts for seamless WP integration, automatic dependency extraction via `app.asset.php`
- **Zod for API response validation** — runtime type safety at the client-server boundary, schemas mirror backend response shapes
- **Div-based grid layout** — ProductGrid uses flex `<div>` elements instead of `<table>` for compatibility with react-window v2 (which renders `<div>` containers); column widths enforced via inline styles
- **Shared scroll container for header + body** — both are wrapped in `.iwbe-grid-scroll-container` (`overflow-x: auto`) with an inner `.iwbe-grid-inner` div whose `width = sum(columnWidths)`. Scrolling the container moves header and virtualized rows together; react-window `<List>` handles vertical virtualization only and fills the inner div's full width. This avoids the need for scroll-event synchronization between two separate containers.
- **PHP empty array caveat** — PHP `json_encode([])` returns `[]` not `{}`, so Zod `FieldSchema.options` uses `z.union([z.record(), z.array(z.never())]).transform()` to handle both formats

## Directory Structure

```
ihumbak-woo-bulk-edit/
├── ihumbak-woo-bulk-edit.php     # Bootstrap, plugin header, autoload
├── uninstall.php                 # Cleanup on uninstall
├── composer.json
├── package.json                  # Frontend dependencies (React, TanStack, Zustand, Zod, Vitest)
├── tsconfig.json                 # TypeScript strict config, path aliases
├── webpack.config.js             # Extends wp-scripts, custom entry & alias
├── src/                          # PHP (PSR-4: IhumbakWooBulkEdit\)
│   ├── Plugin.php                # Main singleton, registers services & hooks
│   ├── Container.php             # DI container with factory pattern
│   ├── Admin/
│   │   ├── Menu.php              # WooCommerce submenu registration
│   │   ├── AssetsLoader.php      # Enqueues JS/CSS, passes REST config
│   │   └── ScreenController.php  # Renders React mount point div
│   ├── Api/
│   │   ├── RestController.php    # Abstract base (namespace, helpers)
│   │   ├── FieldsController.php  # GET /fields endpoint
│   │   ├── ProductsController.php # query / batch / delete / duplicate endpoints
│   │   ├── VariationsController.php # GET /products/{id}/variations endpoint
│   │   ├── ChangelogController.php # GET /changelog endpoint
│   │   └── FiltersController.php # GET|POST|PUT|DELETE /filters endpoints (Issue #18)
│   ├── Query/
│   │   ├── QueryBuilder.php      # Native SQL builder with dynamic JOINs + taxonomy subqueries; filters post_type='product', hydrates type + variations_count
│   │   ├── FilterParser.php      # Parses filter JSON, branches post-col / meta / taxonomy
│   │   ├── VariationsRepository.php # Fetches variations for a parent product via WP REST API
│   │   └── Operators/
│   │       ├── OperatorInterface.php
│   │       ├── OperatorRegistry.php
│   │       ├── EqualOperator.php
│   │       ├── NotEqualOperator.php
│   │       ├── LikeOperator.php
│   │       ├── NotLikeOperator.php
│   │       ├── IsEmptyOperator.php
│   │       └── IsNotEmptyOperator.php
│   ├── Fields/
│   │   ├── FieldInterface.php    # Contract: key, label, type, sanitize, validate
│   │   ├── AbstractField.php     # Base implementation with defaults
│   │   ├── FieldType.php         # Enum with 11 field types
│   │   ├── FieldRegistry.php     # Central registry, filter by capability
│   │   ├── Core/                 # 35 core WC product fields (Issue #14)
│   │   │   ├── NameField.php, SkuField.php, SlugField.php, StatusField.php
│   │   │   ├── RegularPriceField.php, SalePriceField.php
│   │   │   ├── StockQuantityField.php, ManageStockField.php, BackordersField.php, SoldIndividuallyField.php
│   │   │   ├── WeightField.php, LengthField.php, WidthField.php, HeightField.php, ShippingClassField.php
│   │   │   ├── CategoriesField.php, TagsField.php
│   │   │   ├── DescriptionField.php, ShortDescriptionField.php, PurchaseNoteField.php
│   │   │   ├── FeaturedField.php, CatalogVisibilityField.php, ReviewsAllowedField.php, MenuOrderField.php
│   │   │   ├── VirtualField.php, DownloadableField.php, DownloadLimitField.php, DownloadExpiryField.php
│   │   │   ├── ExternalUrlField.php, ButtonTextField.php
│   │   │   ├── UpsellsField.php, CrossSellsField.php
│   │   │   ├── ThumbnailField.php, GalleryField.php
│   │   │   └── DateCreatedField.php
│   │   └── Custom/               # (empty — prepared for custom meta fields)
│   ├── Operations/
│   │   ├── BulkDelete.php        # Trash / permanent delete with variation cascade, audit logging
│   │   └── BulkDuplicate.php     # Wraps WC_Admin_Duplicate_Product, copy_meta/copy_images toggles, audit logging
│   ├── Persistence/
│   │   ├── ProductSaver.php      # Per-product save via WC CRUD with validation; guards variation status to publish/private only
│   │   ├── BatchSaver.php        # Batch orchestrator, optimistic locking, transient cleanup
│   │   ├── DatabaseMigrator.php  # Versioned dbDelta, wbm_db_version option
│   │   ├── ChangeLogRepository.php # Audit log insert/logBatch/query/purgeOlderThan
│   │   └── SavedFiltersRepository.php # CRUD for user-saved filter presets (Issue #18)
│   ├── Integrations/             # (empty — planned)
│   ├── Security/
│   │   ├── CapabilityChecker.php # read/write/delete/manage checks
│   │   └── RateLimiter.php       # Transient-based rate limiting
│   └── Support/                  # (empty — planned)
├── assets/
│   ├── js/
│   │   ├── app.tsx               # React root, QueryClientProvider
│   │   ├── components/
│   │   │   ├── App.tsx           # Main app component
│   │   │   ├── ChangeHistoryPanel/  # Audit log drawer (filters, pagination)
│   │   │   └── ProductGrid/      # Grid, toolbar, bulk-edit/delete/duplicate modals, inline editors, variation rows
│   │   ├── api/                  # apiFetch wrapper + per-endpoint functions
│   │   ├── hooks/                # React Query + custom hooks (save, delete, duplicate, variations, filters)
│   │   ├── store/                # Zustand stores (changes, editing, expansion, filters, recent-filters)
│   │   └── types/                # Zod schemas (api.ts), grid types, global.d.ts (iwbeData)
│   ├── css/
│   │   └── product-grid.css      # Grid styles (WC admin aesthetic)
│   └── build/                    # Compiled output (app.js, app.asset.php)
├── tests/
│   ├── Unit/                     # PHPUnit unit tests (Container, Fields, Query, Operators)
│   ├── Integration/              # PHPUnit integration tests (Plugin, Api, Query, Security)
│   └── e2e/                      # (empty — Playwright planned, Issue #29)
├── docs/
│   └── specyfikacja-bulk-edit-woocommerce.md
└── languages/
```

## Custom Database Tables

Schema version: `1.1.0` (via `DatabaseMigrator::SCHEMA_VERSION`)

- `{prefix}wbm_saved_filters` — user saved filters (id, user_id, name, definition JSON blob, is_shared boolean, created_at, updated_at). Row belongs to a user (user_id > 0) or is team-shared (user_id = 0, is_shared = 1). Max 100 filters per user.
- `{prefix}wbm_change_log` — audit log (id, user_id, product_id, field, old_value JSON, new_value JSON, changed_at)

Created via `Persistence\DatabaseMigrator` (`dbDelta`) on activation and on every boot when `wbm_db_version` differs from `DatabaseMigrator::SCHEMA_VERSION`. `uninstall.php` drops both tables, deletes `wbm_db_version` / `wbm_changelog_retention_days`, and clears the rotation cron.

## Coding Conventions

### PHP
- Strict types: `declare(strict_types=1)` in every file
- PSR-4 autoloading, namespace `IhumbakWooBulkEdit`
- All REST endpoints must check nonce (`X-WP-Nonce`) + capability (`edit_products` / `manage_woocommerce`)
- SQL: only `$wpdb->prepare()`, never string concatenation
- Sanitize all input: `wc_clean`, `sanitize_text_field`, `wp_kses_post` for HTML, `floatval` for prices, `absint` for IDs
- Escape all output: `esc_html`, `esc_attr`, `wp_json_encode`
- Use `WC_Logger` for logging
- GPL v2+ license header in every file

### TypeScript / React
- Strict TypeScript, no `any`
- Zustand for change tracking, undo/redo (min 50 steps)
- React Query for all server communication
- No `dangerouslySetInnerHTML` without DOMPurify
- All strings via `@wordpress/i18n` (`__()`, `_x()`)

### REST API Namespace
`ihumbak-woo-bulk-edit/v1`

Endpoints and status:

| Method | Endpoint | Status |
|--------|----------|--------|
| `GET` | `/fields` | Implemented |
| `POST` | `/products/query` | Implemented |
| `GET` | `/products/{id}/variations` | Implemented |
| `PUT` | `/products/batch` | Implemented |
| `DELETE` | `/products/batch` | Implemented |
| `POST` | `/products/duplicate` | Implemented |
| `GET` | `/changelog` | Implemented |
| `POST` | `/products/bulk-operation` | Not implemented (bulk ops run client-side, commit via `PUT /products/batch`) |
| `GET` | `/filters` | Implemented |
| `POST` | `/filters` | Implemented |
| `PUT` | `/filters/{id}` | Implemented |
| `DELETE` | `/filters/{id}` | Implemented |
| `POST` | `/export` | Planned |
| `POST` | `/import` | Planned |
| `GET` | `/import/status/{id}` | Planned |

Query endpoint accepts: `filters` (array of `{field, operator, value}`), `sort` (`{field, order}`), `page` (min 1), `per_page` (10-500).

Error format: `{ "code": "wbm_*", "message": "...", "data": { "status": 4xx, ... } }`

## Performance Guidelines

- Batch saving: default 50 products/batch, configurable 10-500
- `wp_defer_term_counting(true)` during batch operations
- `wc_delete_product_transients()` once after batch, not per product
- Cache query results in transients (5 min TTL, hash-based key), invalidate on `woocommerce_update_product`
- Variations loaded separately and joined in UI
- Target: 1000 products with variants < 3s load on average hosting

## Security Checklist

- Nonce on every REST endpoint via `permission_callback`
- Capability: read = `edit_products`, write = `edit_products`, delete = `delete_products`, shared filters = `manage_woocommerce`
- Rate limiting on batch endpoint (max 10 batches/min/user)
- Audit log always on for destructive operations (delete)
- Optimistic locking: check `post_modified` on save, reject if changed since load

## Testing

- `tests/Unit/` — PHPUnit unit tests (Container, Fields, Query, Operators)
- `tests/Integration/` — PHPUnit integration tests via wp-phpunit (controllers, repositories, security)
- `assets/js/**/__tests__/` — Vitest frontend unit tests (stores, API client, bulk operations, modals)
- `tests/e2e/` — Playwright e2e tests (planned, Issue #29)
- Target: 80%+ coverage for Operations, FieldRegistry, QueryBuilder

## Build & Dev Commands

```bash
composer install          # PHP dependencies
npm install               # JS dependencies (run from repo root)
npm run start             # Dev server with HMR (wp-scripts)
npm run build             # Production build
npm test                  # Vitest (frontend)
composer test             # PHPUnit
npx playwright test       # E2E tests (once added)
```

## i18n

- Text domain: `ihumbak-woo-bulk-edit`
- All strings wrapped in `__()` / `_e()` / `_x()`
- POT file in `languages/ihumbak-woo-bulk-edit.pot`
- Primary languages: PL, EN

## Integrations (modular, via IntegrationInterface)

Priority order:
1. WPML / Polylang
2. Yoast SEO / Rank Math
3. ACF / ACF Pro
4. WooCommerce Brands
5. WooCommerce Subscriptions
6. Wholesale plugins

