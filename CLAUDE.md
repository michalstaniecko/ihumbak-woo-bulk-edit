# CLAUDE.md — ihumbak-woo-bulk-edit

## Project Overview

WordPress/WooCommerce plugin for bulk editing products. Hybrid approach: "preview & commit" UX (like PW Bulk Edit) with SQL-like filtering and formula engine (like WP Sheet Editor). Audit log with rollback is the key differentiator.

**Working name:** `ihumbak-woo-bulk-edit`
**Repo:** `git@github.com:michalstaniecko/ihumbak-woo-bulk-edit.git`
**Spec:** `docs/specyfikacja-bulk-edit-woocommerce.md`

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
- React 18 + TypeScript
- Zustand for local state (changes, undo/redo)
- React Query (TanStack Query) for server state
- TanStack Table + react-window for virtualized grid
- Build: `@wordpress/scripts` or Vite with `@kucrut/vite-for-wp`
- Validation: Zod

## Directory Structure

```
ihumbak-woo-bulk-edit/
├── ihumbak-woo-bulk-edit.php     # Bootstrap, plugin header, autoload
├── uninstall.php
├── composer.json
├── package.json
├── languages/
├── src/                          # PHP (PSR-4: IhumbakWooBulkEdit\)
│   ├── Plugin.php
│   ├── Container.php
│   ├── Admin/                    # Menu, AssetsLoader, ScreenController
│   ├── Api/                      # REST controllers
│   ├── Query/                    # QueryBuilder, FilterParser, Operators/
│   ├── Fields/                   # FieldRegistry, FieldInterface, Core/, Custom/
│   ├── Operations/               # SetValue, SearchReplace, MathOperation, etc.
│   ├── Persistence/              # ProductSaver, BatchSaver, ChangeLog
│   ├── Integrations/             # IntegrationInterface, WPML/, Yoast/, ACF/
│   ├── Security/                 # CapabilityChecker, NonceVerifier
│   └── Support/                  # Logger, Cache
├── assets/
│   ├── js/                       # React app (app.tsx entry)
│   │   ├── components/
│   │   ├── hooks/
│   │   ├── store/                # Zustand stores
│   │   ├── api/                  # REST client
│   │   └── types/
│   ├── css/
│   └── build/                    # Compiled output
└── tests/
    ├── Unit/
    ├── Integration/
    └── e2e/                      # Playwright
```

## Custom Database Tables

- `{prefix}_wbm_saved_filters` — user saved filters (JSON)
- `{prefix}_wbm_change_log` — audit log (product_id, field, old_value, new_value)

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

Key endpoints:
- `GET /fields` — available fields with metadata
- `POST /products/query` — filter + sort + pagination
- `PUT /products/batch` — save changes batch
- `POST /products/bulk-operation` — execute bulk operation
- `DELETE /products/batch` — bulk delete
- `GET|POST|PUT|DELETE /filters` — saved filters CRUD
- `POST /export`, `POST /import`, `GET /import/status/{id}`

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
npm install               # JS dependencies
npm run start             # Dev server with HMR
npm run build             # Production build
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
