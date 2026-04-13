# CLAUDE.md — ihumbak-woo-bulk-edit

## Project Overview

WordPress/WooCommerce plugin for bulk editing products. Hybrid approach: "preview & commit" UX (like PW Bulk Edit) with SQL-like filtering and formula engine (like WP Sheet Editor). Audit log with rollback is the key differentiator.

**Working name:** `ihumbak-woo-bulk-edit`
**Repo:** `git@github.com:michalstaniecko/ihumbak-woo-bulk-edit.git`
**Spec:** `docs/specyfikacja-bulk-edit-woocommerce.md`
**Version:** 0.1.0 (Backend MVP)

## Implementation Status

**Overall progress: ~75% — Backend MVP + persistence + audit log complete; frontend grid with inline editing, batch save, taxonomy filtering, change history drawer, and client-side bulk operations modal per field type all working.**

### Done (Backend MVP)
- Plugin bootstrap with HPOS compatibility declaration
- Custom DI Container (PSR-11 style, factory pattern)
- Admin: Menu (WooCommerce submenu), AssetsLoader, ScreenController (React mount point)
- REST API:
  - `GET /fields` — fully working, returns all registered fields with metadata
  - `POST /products/query` — fully working, filters/sorts/paginates via native SQL
  - `PUT /products/batch` — fully working (BatchSaver + ProductSaver, writes audit log)
  - `DELETE /products/batch` — registered, stub (awaits BulkDelete, Issue #23)
  - `GET /changelog` — fully working (filters: product_id, user_id, field, date_from, date_to; pagination)
- Field system: FieldInterface, AbstractField, FieldType enum (11 types), FieldRegistry
- **35 core fields** covering all WC product attributes (Issue #14): name, sku, slug, status, regular_price, sale_price, stock_quantity, manage_stock, backorders, sold_individually, weight, length, width, height, shipping_class, categories, tags, description, short_description, featured, catalog_visibility, reviews_allowed, menu_order, purchase_note, virtual, downloadable, download_limit, download_expiry, external_url, button_text, upsells, cross_sells, thumbnail, gallery, date_created
- Query system: QueryBuilder (native SQL with dynamic LEFT JOINs + taxonomy subqueries), FilterParser, OperatorRegistry
- 6 operators: `=`, `!=`, `LIKE`, `NOT LIKE`, `IS EMPTY`, `IS NOT EMPTY`
- **Taxonomy filtering (Issue #35)**: `FilterParser::applyCondition` branches post columns / meta keys / taxonomies; `QueryBuilder::addTaxonomyCondition` emits `p.ID IN/NOT IN (subquery on term_relationships)` so products with many terms don't duplicate rows. Categories, tags, and shipping_class filterable with all 6 operators; negation (`!=`, `NOT LIKE`) also matches products with zero terms in the taxonomy
- Security: CapabilityChecker (read/write/delete/manage), RateLimiter (transient-based, 10 req/60s/user)
- Persistence: `ProductSaver` (per-product save via WC CRUD, captures pre-change values via `get_*` getters for diff), `BatchSaver` (orchestrator with optimistic locking, `wp_defer_term_counting`, transient invalidation, forwards diff to audit log)
- **Audit log (Issue #24)**: `DatabaseMigrator` (versioned `dbDelta`, `wbm_db_version` option), `ChangeLogRepository` (insert/logBatch/query/purgeOlderThan — JSON-encoded old/new values), `ChangelogController` (REST read endpoint), daily WP-Cron `wbm_changelog_rotation` purging entries older than `wbm_changelog_retention_days` (default 90). Tables `wbm_saved_filters` and `wbm_change_log` created on activation and via `maybeMigrate()` on every boot.
- uninstall.php (drops tables, deletes options, clears rotation cron)

### Done (Frontend Foundation)
- Build pipeline: `@wordpress/scripts` v30 (webpack), TypeScript strict mode, `@/*` path aliases
- React 18 app skeleton with `QueryClientProvider` (`assets/js/app.tsx`)
- REST API client: typed `apiFetch` wrapper with `ApiError` class (`assets/js/api/client.ts`)
- Endpoint functions: `fetchProducts`, `fetchFields`, `batchSaveProducts` (`assets/js/api/`)
- React Query hooks: `useProducts` (with `keepPreviousData`), `useFields`, `useBatchSave`
- Zod validation schemas for all API response types (`assets/js/types/api.ts`)
- Global type declarations for `iwbeData` window object (`assets/js/types/global.d.ts`)
- Build output: `assets/build/app.js`, `app.css`, `app.asset.php`

### Done (Frontend Grid — Issue #9)
- ProductGrid component with TanStack Table v8 + react-window v2 virtualization
- Dynamic columns generated from `/fields` API (columnFactory.ts)
- Server-side pagination: page controls (First/Prev/Next/Last), per-page select (25–500)
- Column sorting: clickable headers, asc/desc toggle, sort resets page to 1
- Checkbox row selection: single click + Shift+click range selection
- StatusBar: total products count, selected count, background fetch indicator
- LoadingSkeleton: animated placeholder during initial data fetch
- CSS styling matching WooCommerce admin aesthetics (`assets/css/product-grid.css`)
- **Horizontal scroll sync**: header and body wrapped in a single `.iwbe-grid-scroll-container` with an inner fixed-width div (sum of column widths) so header + rows scroll together

### Done (Frontend Editing & Filtering — Issues #10, #11, #12, #13)
- Zustand stores: `useChangesStore` (change tracking, undo/redo with 50-step history), `useEditingStore` (active cell, draft value, validation)
- Inline cell editing with change highlighting, dedicated editors (`editors/TextEditor.tsx`, `NumberEditor.tsx`, `SelectEditor.tsx`) and shared `CellEditorProps`
- Cell-level validation via `validation.ts` (per-field `validate()` + pending-changes context)
- Batch save endpoint with progress bar, retry, and optimistic locking
- Filter toolbar with quick search (name LIKE, debounced 300ms), multi-step "Add Filter" dropdown (field → operator → value)
- Filter chips with remove buttons, "Clear all" to reset search + filters
- All 6 operators in UI: equals, not equals, contains, not contains, is empty, is not empty
- Filters combined by AND, auto-reload grid on change
- Keyboard navigation (`useGridKeyboardNav`), undo/redo shortcuts (`useUndoRedoShortcuts`)

### Done (Frontend Bulk Operations Modal — Issue #16, client-side)
- `bulkOperations.ts` — pure operation engine with type-discriminated `NumericOperation` / `TextOperation` / `BooleanOperation` / `TaxonomyOperation` and applier functions
- `bulkEdit/BulkEditModal.tsx` — modal triggered from column header dropdown in `HeaderRow`, dispatches to per-field-type form via `inferBulkOperationKind`
- Per-type forms: `NumericBulkForm` (set, increase/decrease absolute & pct, round, clear), `TextBulkForm` (set, search-replace with case sensitivity, append, prepend, upper/lower/title/sentence case, clear), `BooleanBulkForm` (set true/false, toggle), `TaxonomyBulkForm` (add, remove, replace terms)
- Scope selector: "Selected rows (N)" or "All filtered results (M)" — the latter paginates through `/products/query` via `fetchAllFilteredProducts.ts` with progress callback
- Preview-and-commit: operations are applied optimistically to `useChangesStore` (no direct save), flowing through the existing `PUT /products/batch` pipeline — so no new REST endpoint was needed
- Vitest coverage in `__tests__/bulkOperations.test.ts` for every operation mode

### Done (Frontend Change History — Issue #24)
- `api/changelog.ts` + `useChangelog` React Query hook, Zod-validated response
- `ChangeHistoryPanel` drawer component (overlay + backdrop) triggered from toolbar button in `App.tsx`
- Filter UI: product_id, user_id, field (select from registry), date_from, date_to, pagination
- Old value in red, new value in green, clickable product links into wp-admin edit screen

### Done (Tests — partial)
- PHPUnit scaffolding with Unit + Integration suites
- Unit tests: `ContainerTest`, `FieldRegistryTest`, `FieldTypeTest`, all 6 core field tests (Name/Sku/RegularPrice/SalePrice/StockQuantity/Status), `QueryBuilderTest`, all 6 operator tests
- Integration tests: `PluginTest`, `FieldsControllerTest`, `ProductsControllerTest`, `FilterParserTest`, `QueryBuilderTest`, `LikeOperatorTest`, `NotLikeOperatorTest`, `CapabilityCheckerTest`, `RateLimiterTest`, `DatabaseMigratorTest`, `ChangeLogRepositoryTest`, `ChangelogControllerTest`
- Frontend: Vitest configured; `useChangesStore.test.ts` (store coverage) and `ProductGrid/__tests__/bulkOperations.test.ts` (all bulk operation appliers)
- E2E: directory exists, no Playwright tests yet

### Not Yet Implemented
- PHP operation classes (`src/Operations/` is empty) — Issues #17, #31. Current bulk ops are computed in the browser; a server-side engine would be needed for massive datasets or for a dedicated `POST /products/bulk-operation` endpoint
- Filters CRUD endpoints (`GET|POST|PUT|DELETE /filters`) — Issue #18
- Export/Import endpoints — Issue #22
- `DELETE /products/batch` BulkDelete — Issue #23 (route registered, controller method returns a stub)
- Extended operators + AND/OR logic — Issue #19
- Variants inline editing — Issue #15
- Integration modules (WPML, Yoast, ACF, etc.) — Issues #21, #26, #27
- E2E / Playwright tests — Issue #29
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
│   │   ├── ProductsController.php # query/batch/delete endpoints
│   │   └── ChangelogController.php # GET /changelog endpoint
│   ├── Query/
│   │   ├── QueryBuilder.php      # Native SQL builder with dynamic JOINs + taxonomy subqueries
│   │   ├── FilterParser.php      # Parses filter JSON, branches post-col / meta / taxonomy
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
│   ├── Operations/               # (empty — planned)
│   ├── Persistence/
│   │   ├── ProductSaver.php      # Per-product save via WC CRUD with validation
│   │   ├── BatchSaver.php        # Batch orchestrator, optimistic locking, transient cleanup
│   │   ├── DatabaseMigrator.php  # Versioned dbDelta, wbm_db_version option
│   │   └── ChangeLogRepository.php # Audit log insert/logBatch/query/purgeOlderThan
│   ├── Integrations/             # (empty — planned)
│   ├── Security/
│   │   ├── CapabilityChecker.php # read/write/delete/manage checks
│   │   └── RateLimiter.php       # Transient-based rate limiting
│   └── Support/                  # (empty — planned)
├── assets/
│   ├── js/
│   │   ├── app.tsx               # React root, QueryClientProvider
│   │   ├── components/
│   │   │   ├── App.tsx           # Main app component, mounts ProductGrid + ChangeHistoryPanel
│   │   │   ├── ChangeHistoryPanel/
│   │   │   │   ├── ChangeHistoryPanel.tsx  # Drawer for audit log viewer (filters, pagination)
│   │   │   │   └── index.ts
│   │   │   └── ProductGrid/
│   │   │       ├── ProductGrid.tsx       # Main grid container, scroll-container wrapper
│   │   │       ├── useProductGrid.ts     # Central hook (table, sort, pagination, selection, filters, bulk modal trigger)
│   │   │       ├── columnFactory.ts      # Field[] → ColumnDef[] mapping
│   │   │       ├── VirtualizedBody.tsx   # react-window List integration
│   │   │       ├── HeaderRow.tsx         # Header with sort indicators + column dropdown (triggers BulkEditModal)
│   │   │       ├── GridRow.tsx           # Single row with cells + editor mounting
│   │   │       ├── Pagination.tsx        # Page controls + per-page select
│   │   │       ├── StatusBar.tsx         # Total/selected count, save progress
│   │   │       ├── FilterToolbar.tsx     # Search, add-filter dropdown, chips
│   │   │       ├── LoadingSkeleton.tsx   # Animated skeleton loader
│   │   │       ├── validation.ts         # Cell-level validation with pending changes
│   │   │       ├── bulkOperations.ts     # Pure operation engine (numeric/text/boolean/taxonomy appliers)
│   │   │       ├── bulkEdit/
│   │   │       │   ├── BulkEditModal.tsx           # Modal shell, scope selector, form dispatch
│   │   │       │   ├── NumericBulkForm.tsx         # Set/increase/decrease/pct/round/clear
│   │   │       │   ├── TextBulkForm.tsx            # Set/search-replace/append/prepend/case/clear
│   │   │       │   ├── BooleanBulkForm.tsx         # Set true/false/toggle
│   │   │       │   ├── TaxonomyBulkForm.tsx        # Add/remove/replace terms
│   │   │       │   ├── fetchAllFilteredProducts.ts # Paginate /products/query for "all filtered" scope
│   │   │       │   └── index.ts
│   │   │       ├── editors/
│   │   │       │   ├── CellEditorProps.ts  # Shared editor prop contract
│   │   │       │   ├── TextEditor.tsx      # Text/textarea editor
│   │   │       │   ├── NumberEditor.tsx    # Number/price editor
│   │   │       │   ├── SelectEditor.tsx    # Select/boolean editor
│   │   │       │   └── index.ts            # getEditorForField factory
│   │   │       ├── __tests__/
│   │   │       │   └── bulkOperations.test.ts  # Vitest coverage for all operation modes
│   │   │       └── index.ts              # Barrel export
│   │   ├── api/
│   │   │   ├── client.ts         # apiFetch wrapper, ApiError class
│   │   │   ├── products.ts       # fetchProducts, batchSaveProducts
│   │   │   ├── fields.ts         # fetchFields function
│   │   │   ├── changelog.ts      # fetchChangelog function
│   │   │   └── index.ts          # Barrel export
│   │   ├── hooks/
│   │   │   ├── useProducts.ts           # React Query hook (keepPreviousData)
│   │   │   ├── useFields.ts             # React Query hook
│   │   │   ├── useBatchSave.ts          # Batch save orchestration with progress
│   │   │   ├── useChangelog.ts          # React Query hook for audit log
│   │   │   ├── useGridKeyboardNav.ts    # Arrow keys + Enter navigation
│   │   │   ├── useUndoRedoShortcuts.ts  # Cmd/Ctrl+Z, Cmd/Ctrl+Shift+Z
│   │   │   └── index.ts          # Barrel export
│   │   ├── types/
│   │   │   ├── api.ts            # Zod schemas for Field, Product, Filter, etc.
│   │   │   ├── grid.ts           # Grid-specific types (GridPaginationState)
│   │   │   └── global.d.ts       # iwbeData interface (restUrl, nonce, adminUrl)
│   │   └── store/
│   │       ├── useChangesStore.ts  # Change tracking with undo/redo
│   │       ├── useEditingStore.ts  # Cell editing UI state
│   │       ├── __tests__/
│   │       │   └── useChangesStore.test.ts  # Vitest store coverage
│   │       └── index.ts           # Barrel export
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

- `{prefix}wbm_saved_filters` — user saved filters (id, user_id, name, definition JSON, is_shared, timestamps)
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
| `PUT` | `/products/batch` | Implemented (BatchSaver + ProductSaver, optimistic locking, writes audit log) |
| `DELETE` | `/products/batch` | Stub (Issue #23) |
| `GET` | `/changelog` | Implemented (filters, pagination) |
| `POST` | `/products/bulk-operation` | Not implemented — bulk ops currently run client-side in `bulkOperations.ts`, commit via `PUT /products/batch`. A server-side endpoint is only needed for datasets too large for browser iteration |
| `GET\|POST\|PUT\|DELETE` | `/filters` | Planned (Issue #18) |
| `POST` | `/export` | Planned (Issue #22) |
| `POST` | `/import` | Planned (Issue #22) |
| `GET` | `/import/status/{id}` | Planned (Issue #22) |

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

- PHPUnit Unit suite — Container, FieldRegistry, FieldType, core fields, QueryBuilder, operators (in place)
- PHPUnit Integration suite (wp-phpunit) — Plugin bootstrap, FieldsController, ProductsController, FilterParser, QueryBuilder, Like/NotLike operators, CapabilityChecker, RateLimiter (in place)
- Vitest for frontend unit tests — currently `useChangesStore.test.ts`; expand to editors, validation, columnFactory
- Playwright for e2e tests — planned (Issue #29)
- Performance benchmarks with 100k product seed — planned
- Target 80%+ coverage for Operations, FieldRegistry, QueryBuilder

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

## Known TODOs (from code)

- **Issue #23** — Implement `BulkDelete` for `DELETE /products/batch`
- **Issue #17** — PHP-side `SearchReplace` operation with regex support (current client-side text bulk op uses literal escaped strings, not regex)
- **Issue #31** — Math operation parity on the backend; would enable a `POST /products/bulk-operation` endpoint for datasets too large for client-side iteration
