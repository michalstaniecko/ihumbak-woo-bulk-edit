# CLAUDE.md — ihumbak-woo-bulk-edit

## Project Overview

WordPress/WooCommerce plugin for bulk editing products. Hybrid approach: "preview & commit" UX (like PW Bulk Edit) with SQL-like filtering and formula engine (like WP Sheet Editor). Audit log with rollback is the key differentiator.

**Working name:** `ihumbak-woo-bulk-edit`
**Repo:** `git@github.com:michalstaniecko/ihumbak-woo-bulk-edit.git`
**Spec:** `docs/specyfikacja-bulk-edit-woocommerce.md`
**Version:** 0.1.0 (Backend MVP)

## Implementation Status

**Overall progress: ~35% — Backend MVP complete, frontend foundation in place (build pipeline, API client, hooks).**

### Done (Backend MVP)
- Plugin bootstrap with HPOS compatibility declaration
- Custom DI Container (PSR-11 style, factory pattern)
- Admin: Menu (WooCommerce submenu), AssetsLoader, ScreenController (React mount point)
- REST API:
  - `GET /fields` — fully working, returns all registered fields with metadata
  - `POST /products/query` — fully working, filters/sorts/paginates via native SQL
  - `PUT /products/batch` — registered, stub (awaits BatchSaver, Issue #12)
  - `DELETE /products/batch` — registered, stub (awaits BulkDelete, Issue #23)
- Field system: FieldInterface, AbstractField, FieldType enum (11 types), FieldRegistry
- 6 core fields: name, sku, regular_price, sale_price, stock_quantity, status
- Query system: QueryBuilder (native SQL with dynamic LEFT JOINs), FilterParser, OperatorRegistry
- 6 operators: `=`, `!=`, `LIKE`, `NOT LIKE`, `IS EMPTY`, `IS NOT EMPTY`
- Security: CapabilityChecker (read/write/delete/manage), RateLimiter (transient-based, 10 req/60s/user)
- uninstall.php (drops tables, deletes options and transients)

### Done (Frontend Foundation)
- Build pipeline: `@wordpress/scripts` (webpack), TypeScript strict mode, `@/*` path aliases
- React 18 app skeleton with `QueryClientProvider` (`assets/js/app.tsx`)
- REST API client: typed `apiFetch` wrapper with `ApiError` class (`assets/js/api/client.ts`)
- Endpoint functions: `fetchProducts`, `fetchFields` (`assets/js/api/`)
- React Query hooks: `useProducts` (with `keepPreviousData`), `useFields` (`assets/js/hooks/`)
- Zod validation schemas for all API response types (`assets/js/types/api.ts`)
- Global type declarations for `iwbeData` window object (`assets/js/types/global.d.ts`)
- Build output: `assets/build/app.js`, `app.asset.php`

### Not Yet Implemented
- Frontend UI components: Zustand store, grid/table (TanStack Table), filter UI, CSS/styling
- Persistence layer: BatchSaver, ProductSaver, ChangeLog
- Database migrations (table creation on activation) — Issue #25
- Operations: SetValue, SearchReplace, MathOperation
- Filters CRUD endpoints (`GET|POST|PUT|DELETE /filters`)
- Export/Import endpoints
- Bulk operation endpoint (`POST /products/bulk-operation`)
- Integration modules (WPML, Yoast, ACF, etc.)
- Tests (directory structure exists, no test files)
- GPL v2+ license headers in source files

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
- Zustand ^5.0 for local state (changes, undo/redo) — not yet implemented
- TanStack Table ^8.0 + react-window for virtualized grid — not yet implemented
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

## Directory Structure

```
ihumbak-woo-bulk-edit/
├── ihumbak-woo-bulk-edit.php     # Bootstrap, plugin header, autoload
├── uninstall.php                 # Cleanup on uninstall
├── composer.json
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
│   │   └── ProductsController.php # query/batch/delete endpoints
│   ├── Query/
│   │   ├── QueryBuilder.php      # Native SQL builder with dynamic JOINs
│   │   ├── FilterParser.php      # Parses filter JSON, validates fields/operators
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
│   │   └── Core/
│   │       ├── NameField.php
│   │       ├── SkuField.php
│   │       ├── RegularPriceField.php
│   │       ├── SalePriceField.php
│   │       ├── StockQuantityField.php
│   │       └── StatusField.php
│   │   └── Custom/               # (empty — prepared for custom meta fields)
│   ├── Operations/               # (empty — planned)
│   ├── Persistence/              # (empty — planned)
│   ├── Integrations/             # (empty — planned)
│   ├── Security/
│   │   ├── CapabilityChecker.php # read/write/delete/manage checks
│   │   └── RateLimiter.php       # Transient-based rate limiting
│   └── Support/                  # (empty — planned)
├── assets/
│   ├── package.json              # Frontend dependencies (React, TanStack, Zustand, Zod)
│   ├── js/
│   │   ├── app.tsx               # React root, QueryClientProvider
│   │   ├── components/
│   │   │   └── App.tsx           # Main app component (placeholder)
│   │   ├── api/
│   │   │   ├── client.ts         # apiFetch wrapper, ApiError class
│   │   │   ├── products.ts       # fetchProducts function
│   │   │   ├── fields.ts         # fetchFields function
│   │   │   └── index.ts          # Barrel export
│   │   ├── hooks/
│   │   │   ├── useProducts.ts    # React Query hook (keepPreviousData)
│   │   │   ├── useFields.ts      # React Query hook
│   │   │   └── index.ts          # Barrel export
│   │   ├── types/
│   │   │   ├── api.ts            # Zod schemas for Field, Product, Filter, etc.
│   │   │   └── global.d.ts       # iwbeData interface (restUrl, nonce, adminUrl)
│   │   └── store/                # (empty — Zustand planned)
│   ├── css/                      # (empty — planned)
│   └── build/                    # Compiled output (app.js, app.asset.php)
├── tests/
│   ├── Unit/                     # (empty — planned)
│   ├── Integration/              # (empty — planned)
│   └── e2e/                      # (empty — Playwright planned)
├── docs/
│   └── specyfikacja-bulk-edit-woocommerce.md
└── languages/
```

## Custom Database Tables

- `{prefix}_wbm_saved_filters` — user saved filters (JSON)
- `{prefix}_wbm_change_log` — audit log (product_id, field, old_value, new_value)

Note: Tables defined in uninstall.php cleanup but migration/creation not yet implemented (Issue #25).

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
| `PUT` | `/products/batch` | Stub (Issue #12) |
| `DELETE` | `/products/batch` | Stub (Issue #23) |
| `POST` | `/products/bulk-operation` | Planned |
| `GET\|POST\|PUT\|DELETE` | `/filters` | Planned |
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

- PHPUnit for unit tests (Operations, FieldRegistry, QueryBuilder) — target 80%+ coverage
- wp-phpunit for integration tests
- Playwright for e2e tests
- Performance benchmarks with 100k product seed

## Build & Dev Commands

```bash
composer install          # PHP dependencies
cd assets && npm install  # JS dependencies (run from assets/)
cd assets && npm run start  # Dev server with HMR
cd assets && npm run build  # Production build
composer test             # PHPUnit
npx playwright test       # E2E tests
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

## Known TODOs (from code)

- **Issue #12** — Implement `BatchSaver` for `PUT /products/batch`
- **Issue #23** — Implement `BulkDelete` for `DELETE /products/batch`
- **Issue #25** — `DatabaseMigrator` for table creation on plugin activation
