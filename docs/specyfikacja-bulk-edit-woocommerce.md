# Specyfikacja deweloperska — Bulk Edit dla WooCommerce

Dokument zawiera analizę porównawczą dwóch wiodących pluginów (PW WooCommerce Bulk Edit oraz WP Sheet Editor) i na jej bazie kompletną specyfikację techniczną do implementacji własnego pluginu.

---

## 1. Analiza porównawcza istniejących rozwiązań

### 1.1. PW WooCommerce Bulk Edit (Pimwick)

**Filozofia produktu:** „edytor z podglądem zmian” — interfejs przypomina arkusz kalkulacyjny, ale kluczowe jest to, że żadna zmiana nie idzie do bazy, dopóki użytkownik nie kliknie *Save Changes*. Czerwone podświetlenie obniżek cen, undo/redo, limit 1000 wierszy ze względów wydajnościowych przeglądarki.

**Wersja darmowa** — edycja podstawowych pól: Name, Description, SKU, Regular Price, Tax Status/Class, Manage Stock, Stock Quantity, Backorders, Stock Status, Catalog Visibility, Featured, Status. Edycja wariantów. Live preview, undo, inline + bulk, search/replace z wildcardami, nawigacja klawiaturą.

**Wersja Pro — pełny zakres pól edycji:**
- Ceny: Regular Price, Sale Price, Sale Start/End Date, zmiana Sale Price na podstawie Regular Price, procentowe zmiany z zaokrąglaniem i z możliwością cofnięcia
- Atrybuty: wszystkie custom attributes, default values dla variable products, attribute visibility, modyfikacja atrybutów wybranych produktów
- Taksonomie: Categories (add/remove), Tags
- Opisy: Description, Short Description, Variation Description
- Identyfikatory: SKU, GTIN/UPC/EAN/ISBN, Slug
- Wymiary i wysyłka: Weight, Length, Width, Height, Shipping Class
- Stan magazynowy: Manage Stock, Stock Quantity, Backorders, Stock Status, Sold Individually
- Obrazy: produktu, wariantu, galeria — set/clear w bulk
- Produkty zewnętrzne: External URL, Button Text
- Pliki do pobrania: Virtual, Downloadable, Download Limit, Download Expiry, Download URL, Download Name
- Meta: Purchase Note, Enable Reviews, Menu Order, Published On, Last Edited On, Catalog Visibility, Featured, Status
- Linked products: Cross-sells, Up-sells (jako opcja)
- Bulk delete produktów i wariantów
- Tworzenie wariantów (manual i automatyczne dla wszystkich kombinacji atrybutów)
- Custom fields (dodawane przez użytkownika)

**Filtrowanie (Pro):** product name, type, description, short description, variation description, categories, tags, attributes, regular/sale price, status, stock status/quantity, shipping class, SKU, GTIN, slug, image, vendor, brand, tax status/class, manage stock. Operatory dodatkowe: *Is Empty / Is Not Empty*. Save & load filters. Regex w search/replace. Export wyników do CSV.

**Integracje (Pro):** WPML (filtr po języku), Yoast/Rank Math (GTIN), ACF nie wprost, ale za to bardzo wiele wtyczek branżowych — WooCommerce Brands, YITH, Dokan, MultiVendorX, ATUM, Aelia, B2B Market, Flatsome theme, Gravity Forms Product Add-ons, WooCommerce Subscriptions, WooCommerce Product Add-ons, Variation Swatches, Wholesale, Min/Max Quantities, Cost of Goods, GTIN-y od kilku autorów, kilkunastu różnych vendorów multi-currency i feedy produktowe.

**Ograniczenia techniczne, które warto zauważyć:**
- Limit 1000 wierszy na zapytanie (browser-side performance)
- Problemy z PHP memory exhausted przy dużych zbiorach — w docs sugerują albo zawężenie filtra, albo zwiększenie pamięci
- HPOS (High Performance Order Storage) compatible — istotne dla nowych instalacji Woo

### 1.2. WP Sheet Editor (Bulk Edit Products for WooCommerce)

**Filozofia produktu:** „prawdziwy spreadsheet w wp-admin” — bardziej zbliżone do Excela niż do PW. Każde pole produktu to kolumna, automatyczne wykrywanie wszystkich custom fields ze wszystkich pluginów (ACF, dowolne meta), formuły, masowe operacje matematyczne, operatory porównań SQL-owe.

**Wersja darmowa** — bardzo okrojona: tylko simple products, tylko: title, description, sku, stock qty, stock status, manage stock, regular price, sale price, virtual.

**Wersja Pro:**
- Wszystkie typy produktów: Simple, Variable, Variations, External, Subscription, Membership
- Wszystkie pola produktu (ta sama lista co w PW + Custom Fields)
- Edycja w spreadsheet w wp-admin albo eksport do Excel/Google Sheets i import z powrotem
- Tworzenie wariantów masowo
- Kopiowanie wariantów/atrybutów/plików downloadable z jednego produktu na wiele
- **Advanced Search** — wyszukiwanie po dowolnym polu, dowolny operator (`<`, `>`, `=`, `<=`, `>=`, `LIKE`, `NOT IN` itd.), wiele warunków
- **Formula engine** — replace values w dowolnym polu, operacje matematyczne (zwiększ ceny o 20%, zmniejsz stock o 10), kopiowanie wartości między polami (np. regular → sale)
- Auto-detekcja custom fields (ACF i innych)
- Możliwość ustawienia liczby produktów per batch i odstępu między batchami (dla shared hosting)
- Import CSV
- Bulk delete (przez context menu)
- Formuły z placeholderami (np. `$random_date$`)
- Freezing kolumn, sortowanie, ukrywanie kolumn, autosize
- Konwersja produktu z simple → variable, variation → product itd.

### 1.3. Kluczowe różnice ideologiczne

| Aspekt | PW Bulk Edit | WP Sheet Editor |
|---|---|---|
| Model UX | „Preview & commit” — zmiany lokalnie, save świadomy | „Live spreadsheet” — zmiany potencjalnie zapisywane na bieżąco |
| Undo | Tak, w obrębie sesji przed save | Ograniczone, bo zmiany lecą do DB |
| Filtrowanie | UI przyjazny, ale ograniczony zestaw operatorów | SQL-like, dowolny operator, dowolne pole |
| Formuły | Procentowe zmiany cen z zaokrąglaniem, search/replace, regex | Pełny silnik formuł (math, kopiowanie pól, placeholdery) |
| Wydajność | Limit 1000 wierszy | Pagination + batch saving + tweakable batch size |
| Custom fields | Trzeba dodać manualnie | Automatyczna detekcja |
| Import CSV | Tylko export | Pełen import/export CSV |
| Tworzenie produktów | Nie | Tak |
| Bulk delete | Tak (Pro) | Tak |

**Wniosek dla projektu:** najlepsze rozwiązanie łączy oba podejścia — UX „preview & commit” jak u PW (bezpieczeństwo), ale silnik filtrowania i formuł jak u WP Sheet Editor (moc), oraz batched saving z konfigurowalnymi parametrami (skalowalność).

---

## 2. Specyfikacja produktu — Twój plugin

### 2.1. Założenia ogólne

- **Nazwa robocza:** `woo-bulk-manager` (zmień na własną)
- **Target:** WooCommerce 8.0+, WordPress 6.4+, PHP 8.1+
- **Kompatybilność:** HPOS (High Performance Order Storage), WPML, Polylang (opcjonalnie), klasyczne i blokowe edytory
- **Licencjonowanie:** single-site / multi-site, klucz licencyjny w opcjach
- **Język:** pełna lokalizacja PL/EN, gotowość na inne (`load_plugin_textdomain`)
- **Compliance:** GPL v2+ (wymagane przez WP), respektowanie capabilities WP

### 2.2. Persony i przypadki użycia

1. **Manager sklepu** — masowo zmienia ceny przed promocją (np. -20% na całą kategorię)
2. **Magazynier** — aktualizuje stany magazynowe na podstawie inwentaryzacji
3. **Content manager** — poprawia opisy, taguje, dodaje obrazki
4. **Dropshipper** — importuje feedy z dostawców, podmienia tylko zmienione pola
5. **SEO specialist** — masowo edytuje meta title/description (Yoast/Rank Math)

### 2.3. Wymagania funkcjonalne

#### A. Filtrowanie i wyszukiwanie

System filtrów musi pozwalać na łączenie wielu warunków przez AND/OR z możliwością grupowania (nawiasy logiczne). Każdy warunek to trójka: `pole + operator + wartość(i)`. Pola: wszystkie te wymienione w sekcji 1.1 (PW Pro) plus wszystkie custom fields wykryte automatycznie. Operatory: `=`, `!=`, `<`, `<=`, `>`, `>=`, `LIKE` (z wildcardami `*` i `?`), `NOT LIKE`, `IN`, `NOT IN`, `IS EMPTY`, `IS NOT EMPTY`, `BETWEEN`, `REGEXP`. Filtry zapisywalne pod nazwą, z możliwością współdzielenia między użytkownikami (capability `manage_woocommerce`). Ostatnio użyte filtry w dropdownie. Quick search po SKU/nazwie zawsze widoczny w toolbarze.

#### B. Widok grid (spreadsheet)

Tabela z wirtualnym scrollowaniem (handsontable, AG Grid, lub własna implementacja na bazie React + react-window). Kolumny: konfigurowalne — pokazywanie/ukrywanie, kolejność drag&drop, freeze pierwszych N kolumn, autosize, sort po kliknięciu w nagłówek (multi-sort z Shift). Inline editing pojedynczej komórki (klik lub Enter), nawigacja klawiaturą (strzałki, Tab, Enter, Esc, Ctrl+C/V dla copy-paste między komórkami). Bulk edit kolumny przez klik w nagłówek → modal „zastosuj do wszystkich/zaznaczonych”. Rozwijanie wariantów pod produktem variable (toggle inline). Kolorystyka: zmienione komórki podświetlone, obniżki cen na czerwono, podwyżki na zielono, błędy walidacji na żółto.

#### C. Operacje masowe

- **Set value** — ustaw konkretną wartość dla wszystkich/zaznaczonych
- **Clear value** — wyzeruj pole
- **Increment / decrement** — o stałą lub procent
- **Round** — zaokrąglanie do zadanej precyzji (.99, .95, .00, custom)
- **Search & Replace** — z opcją regex, wildcardami, case sensitivity
- **Append / Prepend** — dodaj tekst na początku/końcu
- **Change case** — UPPER, lower, Title, Sentence
- **Copy field to field** — np. regular_price → sale_price
- **Math formula** — prosty parser wyrażeń (np. `[regular_price] * 0.8`, `[stock] - 5`)
- **Bulk delete** — z confirmation, miękki (move to trash) lub twardy
- **Bulk duplicate** — duplikuj wybrane produkty
- **Bulk create variations** — wszystkie kombinacje wybranych atrybutów
- **Bulk add/remove** — kategorii, tagów, atrybutów

#### D. Preview & commit

Wszystkie zmiany trzymane w stanie front-endu (Redux/Zustand). Panel boczny pokazuje liczbę zmienionych komórek z breakdownem per pole. Przycisk *Save Changes* uruchamia batch save. *Discard Changes* czyści stan. *Undo/Redo* z historią min. 50 kroków (Cmd/Ctrl+Z, Cmd/Ctrl+Shift+Z).

#### E. Batch saving

Zapis zmian w batchach (domyślnie 50 produktów per batch, konfigurowalne 10–500). Między batchami opcjonalne opóźnienie (0–2000ms) dla shared hostingów. Progress bar z % i licznikiem. Możliwość przerwania (z zapamiętaniem stanu — co już zapisane, co nie). Auto-retry przy błędach sieci (3 próby z backoffem). Log błędów per produkt z możliwością ponowienia tylko nieudanych.

#### F. Import / Export CSV

Export: zaznaczone wiersze lub cały wynik filtra, wybór kolumn, format CSV/TSV/XLSX, kodowanie UTF-8 z BOM. Import: upload pliku, mapowanie kolumn (auto-detect po nazwie + manual override), tryb update-only (dopasowanie po SKU/ID) lub create-or-update, dry-run preview, batch import z progress barem.

#### G. Custom fields

Auto-detekcja kluczy meta z bazy (z blacklistą private/system keys jak `_edit_lock`, `_edit_last`). Limit pól do 2500 (zgodnie z best practice WP Sheet Editor). Wsparcie dla ACF — wykrywanie definicji pól z `acf_get_field_groups()`, renderowanie odpowiednich edytorów (text, textarea, select, checkbox, radio, image, gallery, repeater jako JSON).

#### H. Integracje (priorytetowo)

1. **WPML / Polylang** — filtrowanie po języku, edycja tłumaczeń
2. **Yoast SEO / Rank Math** — meta title, meta description, focus keyword, GTIN
3. **ACF / ACF Pro** — wszystkie standardowe typy pól
4. **WooCommerce Brands** (oficjalny) i **Perfect WooCommerce Brands**
5. **WooCommerce Subscriptions** — pola subskrypcji
6. **Wholesale plugins** (1-2 popularne)

Architektura ma pozwalać na łatwe dodawanie integracji jako oddzielnych modułów (interfejs `Integration_Interface`).

### 2.4. Wymagania niefunkcjonalne

- **Wydajność:** ładowanie 1000 produktów z wariantami < 3s na średnim hostingu (PHP 8.1, MySQL 8, 512MB RAM)
- **Skalowalność:** obsługa katalogów 100k+ produktów (z paginacją serwerową)
- **Bezpieczeństwo:** wszystkie endpointy z nonce + capability check (`edit_products`), sanityzacja inputu (`wc_clean`, `sanitize_*`), escapowanie outputu, prepared statements w każdym customowym SQL
- **HPOS:** deklaracja kompatybilności przez `FeaturesUtil::declare_compatibility`
- **Dostępność:** WCAG 2.1 AA dla UI admin
- **i18n:** wszystkie stringi w `__()` / `_e()`, plik POT
- **Logging:** integracja z `WC_Logger` dla błędów i audytu zmian (opcjonalne, włączane w ustawieniach)

---

## 3. Architektura techniczna

### 3.1. Stack

- **Backend:** PHP 8.1+, WordPress 6.4+, WooCommerce 8.0+
- **Frontend admin:** React 18 + TypeScript, Zustand (state), React Query (server state), Tailwind CSS lub `@wordpress/components`
- **Grid:** TanStack Table + react-window dla wirtualizacji (lub AG Grid Community jeśli budżet pozwala — ma wbudowane wszystko)
- **Build:** `@wordpress/scripts` (webpack) lub Vite z `@kucrut/vite-for-wp`
- **Komunikacja:** WP REST API (custom namespace `woo-bulk-manager/v1`)
- **Walidacja:** Zod po stronie front, własna warstwa po stronie back

### 3.2. Struktura katalogów

```
woo-bulk-manager/
├── woo-bulk-manager.php          # Bootstrap, header, autoload
├── uninstall.php                  # Cleanup przy odinstalowaniu
├── composer.json
├── package.json
├── readme.txt
├── languages/
│   └── woo-bulk-manager.pot
├── src/
│   ├── Plugin.php                 # Klasa główna (singleton)
│   ├── Container.php              # DI container
│   ├── Admin/
│   │   ├── Menu.php               # Rejestracja submenu w WooCommerce
│   │   ├── AssetsLoader.php       # Enqueue JS/CSS
│   │   └── ScreenController.php
│   ├── Api/
│   │   ├── RestController.php     # Bazowy kontroler
│   │   ├── ProductsController.php # GET/PUT /products
│   │   ├── FiltersController.php  # CRUD zapisanych filtrów
│   │   ├── ImportController.php
│   │   ├── ExportController.php
│   │   └── BatchController.php    # Endpoint do batch save
│   ├── Query/
│   │   ├── QueryBuilder.php       # Builder warunków → SQL
│   │   ├── FilterParser.php       # Parsuje JSON filtra na obiekt query
│   │   └── Operators/             # Każdy operator jako klasa
│   ├── Fields/
│   │   ├── FieldRegistry.php      # Rejestr wszystkich pól
│   │   ├── FieldInterface.php
│   │   ├── Core/                  # Pola wbudowane (Price, Stock, SKU itd.)
│   │   └── Custom/                # Wykrywane meta i ACF
│   ├── Operations/
│   │   ├── OperationInterface.php
│   │   ├── SetValue.php
│   │   ├── SearchReplace.php
│   │   ├── MathOperation.php
│   │   ├── BulkDelete.php
│   │   └── ...
│   ├── Persistence/
│   │   ├── ProductSaver.php       # Zapis pojedynczego produktu
│   │   ├── BatchSaver.php         # Orkiestracja batchy
│   │   └── ChangeLog.php          # Audit log
│   ├── Integrations/
│   │   ├── IntegrationInterface.php
│   │   ├── WPML/
│   │   ├── Yoast/
│   │   ├── ACF/
│   │   └── ...
│   ├── Security/
│   │   ├── CapabilityChecker.php
│   │   └── NonceVerifier.php
│   └── Support/
│       ├── Logger.php
│       └── Cache.php
├── assets/
│   ├── js/
│   │   ├── app.tsx                # Entry point React
│   │   ├── components/
│   │   ├── hooks/
│   │   ├── store/                 # Zustand stores
│   │   ├── api/                   # Klient REST
│   │   └── types/
│   ├── css/
│   └── build/                     # Output webpack/vite
└── tests/
    ├── Unit/
    ├── Integration/
    └── e2e/                       # Playwright
```

### 3.3. Model danych

Plugin nie potrzebuje własnych tabel poza dwoma:

**`wp_wbm_saved_filters`** — zapisane filtry użytkownika
- `id` BIGINT PK AUTO_INCREMENT
- `user_id` BIGINT (FK do wp_users, NULL = współdzielony)
- `name` VARCHAR(190)
- `filter_json` LONGTEXT (JSON definicji filtra)
- `created_at`, `updated_at` DATETIME
- INDEX (`user_id`)

**`wp_wbm_change_log`** — audit log (opcjonalny, włączany w ustawieniach)
- `id` BIGINT PK
- `user_id` BIGINT
- `product_id` BIGINT
- `field` VARCHAR(190)
- `old_value` LONGTEXT
- `new_value` LONGTEXT
- `changed_at` DATETIME
- INDEX (`product_id`, `changed_at`), INDEX (`user_id`)

Rotacja logu: cron usuwa wpisy starsze niż X dni (konfigurowalne).

### 3.4. REST API — najważniejsze endpointy

Wszystkie pod namespace `woo-bulk-manager/v1`. Każdy endpoint sprawdza nonce (`X-WP-Nonce`) i capability (`edit_products` lub `manage_woocommerce` dla operacji destrukcyjnych).

| Metoda | Endpoint | Opis |
|---|---|---|
| `GET` | `/fields` | Lista wszystkich dostępnych pól z metadanymi (typ, edytowalność, opcje) |
| `POST` | `/products/query` | Body: filter JSON + sort + pagination → zwraca produkty |
| `PUT` | `/products/batch` | Body: tablica zmian `[{id, field, value}, ...]` → zapisuje batch |
| `POST` | `/products/bulk-operation` | Body: operacja + scope (filter lub IDs) → wykonuje operację |
| `DELETE` | `/products/batch` | Body: IDs → bulk delete |
| `GET` | `/filters` / `POST` / `PUT` / `DELETE` | CRUD zapisanych filtrów |
| `POST` | `/export` | Eksport (zwraca URL do pobrania CSV/XLSX) |
| `POST` | `/import` | Import CSV (multipart) |
| `GET` | `/import/status/{id}` | Status batcha importu |

Format błędu (`WP_Error` → JSON):
```json
{ "code": "wbm_validation_failed", "message": "Sale price must be lower than regular price", "data": { "status": 422, "field": "sale_price", "product_id": 123 } }
```

### 3.5. Kluczowe wybory implementacyjne

**Query Builder** — nie używać `WP_Query` dla głównego filtrowania. Zamiast tego własny builder składający natywne SQL z prepared statements bezpośrednio na `wp_posts` + `wp_postmeta` (z JOINami warunkowymi tylko gdy są warunki na meta). Powód: `WP_Query` z wieloma `meta_query` jest dramatycznie wolne na dużych katalogach. Pamiętaj o HPOS (dla zamówień, nie produktów — produkty na razie zostają w `wp_posts`).

**Cache** — cache wyników zapytań w transientach (z kluczem opartym o hash filtra) na 5 min, invalidate po każdej zmianie produktu (`woocommerce_update_product` hook).

**Variations** — variations to osobne posty (`product_variation`), trzeba je pobierać osobno i łączyć w UI. W gridzie wyświetlać jako rozwijalne wiersze pod produktem rodzicem.

**Walidacja** — biznesowa po stronie back (sale < regular, stock_qty integer, dates parsable itd.). Front waliduje optymistycznie dla UX, ale prawda jest w PHP.

**Wydajność zapisu** — `wp_defer_term_counting(true)` i `wp_defer_comment_counting(true)` na początku batcha, `wc_delete_product_transients()` po zakończeniu batcha (nie po każdym produkcie). Wyłączyć `do_action('save_post')` jeśli to bezpieczne, lub odpalać selektywnie potrzebne hooki.

**Concurrency** — brak twardego lockingu, ale przy zapisie sprawdzaj `post_modified` z momentu loadu. Jeśli się zmieniło → konflikt → poproś użytkownika o decyzję (overwrite / discard / merge).

### 3.6. Bezpieczeństwo

- Nonce na każdym REST endpoint (`permission_callback`)
- Capability check: read = `edit_products`, write = `edit_products`, delete = `delete_products`, manage filters dla wszystkich = `manage_woocommerce`
- Sanityzacja: `wc_clean`, `sanitize_text_field`, `wp_kses_post` dla opisów, `floatval` dla cen, `absint` dla ID
- Escaping: w JS — żadne `dangerouslySetInnerHTML` bez DOMPurify; w PHP — `esc_html`, `esc_attr`, `wp_json_encode`
- SQL: tylko `$wpdb->prepare()`, nigdy konkatenacja
- Rate limiting na endpoincie batch (np. max 10 batchy/min/user)
- Audit log dla operacji destrukcyjnych (delete) zawsze włączony
- CSRF: REST używa nonce WP, dodatkowo `Origin` header check

---

## 4. UX / UI

### 4.1. Layout

```
┌─────────────────────────────────────────────────────────────┐
│  Toolbar: [Filtr ▼] [Quick search] [Save filter] [Export]  │
│  ──────────────────────────────────────────────────────────│
│  Filter chips: [Category: Buty ×] [Stock < 10 ×] [+]       │
│  ──────────────────────────────────────────────────────────│
│  Bulk action bar (po zaznaczeniu): [Edit ▼] [Delete] [...] │
│  ──────────────────────────────────────────────────────────│
│  ┌──────────────────────────────────────────────────────┐  │
│  │ ☐ │ ID │ Image │ SKU │ Name │ Price │ Stock │ ...   │  │
│  │───┼────┼───────┼─────┼──────┼───────┼───────┼───────│  │
│  │ ☐ │ 1  │ [img] │ ABC │ ...  │ 99.00 │ 12    │       │  │
│  │ ☐ │ 2  │ [img] │ DEF │ ...  │ 49.00 │ 0     │       │  │
│  │   ▼ wariant 1                                       │  │
│  │   ▼ wariant 2                                       │  │
│  └──────────────────────────────────────────────────────┘  │
│  ──────────────────────────────────────────────────────────│
│  Status bar: 1247 wyników │ 23 zmiany │ [Discard] [Save]   │
└─────────────────────────────────────────────────────────────┘
```

### 4.2. Kluczowe interakcje

- **Klik w komórkę** → tryb edycji inline
- **Dwuklik** lub **Enter** → otwarcie pełnego edytora dla pól złożonych (textarea z TinyMCE dla description, media picker dla obrazków)
- **Klik w nagłówek kolumny** → menu: Sort / Hide / Freeze / Bulk edit this column / Auto-size
- **Right-click w wierszu** → context menu: Edit / Duplicate / Delete / Open in new tab / Copy values
- **Shift+klik** → zaznaczenie zakresu wierszy
- **Cmd/Ctrl+klik** → toggle zaznaczenia pojedynczego wiersza
- **Cmd/Ctrl+A** → zaznacz wszystkie widoczne
- **Cmd/Ctrl+Shift+A** → zaznacz wszystkie pasujące do filtra (nawet niewidoczne)
- **Cmd/Ctrl+Z / Shift+Z** → undo/redo
- **Cmd/Ctrl+S** → save changes
- **Esc** → cancel edycji komórki / zamknij modal

### 4.3. Modal Bulk Edit (per kolumna)

Pojawia się po kliknięciu w nagłówek → "Bulk edit this column". Zawartość zależy od typu pola:

- **Numeric (cena, stock):** Set value / Increase by amount / Increase by % / Decrease by amount / Decrease by % / Round to / Copy from field
- **Text:** Set value / Search & replace (z regex toggle) / Append / Prepend / Change case / Clear
- **Taxonomy (categories, tags):** Add / Remove / Replace all
- **Date:** Set date / Clear / Shift by days
- **Boolean:** Set true / Set false / Toggle
- **Image:** Set / Clear / Replace if matches

Każdy modal kończy się dropdownem **Apply to:** Selected rows / All filtered results, z licznikiem dotkniętych produktów.

---

## 5. Testowanie

- **Unit:** PHPUnit dla wszystkich klas Operations, FieldRegistry, QueryBuilder. Cel: 80%+ coverage core logic.
- **Integration:** wp-phpunit, testy zapisu produktów, scenariusze z wariantami, scenariusze z metami.
- **E2E:** Playwright — happy paths (filtruj → edytuj cenę 100 produktów → save → zweryfikuj w bazie), edge cases (concurrent edit, błąd walidacji, undo/redo, import CSV z błędami).
- **Performance:** seed 100k produktów, mierz: czas ładowania filtra, czas zapisu batcha 100 produktów, memory peak.
- **Compatibility matrix:** WP 6.4–6.9, WC 8.0–10.6, PHP 8.1–8.3, MySQL 5.7/8.0, MariaDB 10.6+.

---

## 6. Roadmap implementacji (sugerowana, MVP → v1.0)

**Faza 1 — MVP (4–6 tyg.)**
- Bootstrap pluginu, menu, REST namespace, security layer
- Field Registry z polami core (name, sku, price, sale_price, stock, status)
- Query Builder z podstawowymi operatorami (=, !=, LIKE, IS EMPTY)
- Grid React z inline edit, paginacja serwerowa
- Batch save z undo (sesyjny, przed save)
- Filtrowanie po 5–6 najczęstszych polach

**Faza 2 — Pełne pola (3–4 tyg.)**
- Wszystkie pola WC core (atrybuty, kategorie, tagi, wymiary, downloadable, etc.)
- Edycja wariantów inline
- Bulk operations modal per typ pola
- Search & Replace z regex
- Save/load filtrów

**Faza 3 — Zaawansowane (3–4 tyg.)**
- Custom fields auto-detection
- ACF integration
- Import/Export CSV
- Bulk delete, duplicate, create variations
- Audit log + ChangeLog viewer

**Faza 4 — Integracje i polish (2–3 tyg.)**
- WPML, Yoast, Rank Math, WooCommerce Brands
- Performance tuning (cache, deferred hooks)
- E2E test suite, dokumentacja, screenshoty
- Licencjonowanie, automatyczne aktualizacje (jeśli komercyjny)

**Faza 5 — Pro features (opcjonalnie)**
- Math formula engine z parserem wyrażeń
- Schedulowane operacje (cron)
- Historia zmian z możliwością rollbacku
- Multi-store sync

---

## 7. Ryzyka i decyzje do podjęcia

**Ryzyka:**
- **Wydajność na dużych katalogach** — mitygacja: paginacja serwerowa, indeksy DB, cache, profilowanie od początku
- **Konflikty z innymi pluginami** — mitygacja: nie modyfikować globalnych stanów WP, używać WC API gdzie się da, testy kompatybilności
- **HPOS na przyszłość dla produktów** — Woo planuje przeniesienie produktów do custom tables; warto trzymać warstwę dostępu do danych za interfejsem repozytorium, żeby później wymienić implementację
- **Concurrent edits** — bez locków będą konflikty; zdecyduj czy idziesz w optimistic locking (jak GitHub) czy pessimistic (lock per produkt na X minut)

**Decyzje do podjęcia przed kodowaniem:**
1. Czy plugin jest tylko dla Twojego sklepu, czy planujesz dystrybucję? (wpływa na licencjonowanie, i18n, wsparcie)
2. AG Grid Community (MIT) vs własny grid na TanStack — AG Grid daje prawie wszystko od razu, ale jest ciężki (~500KB)
3. Czy potrzebujesz scheduled bulk operations (cron) w MVP?
4. Czy chcesz pełen audit log od początku (zwiększa rozmiar bazy), czy opcjonalny?
5. Jak głęboko integrować z ACF — tylko podstawowe typy pól, czy także repeatery i flexible content?

---

## 8. Podsumowanie różnic vs konkurencja

Twój plugin powinien być **hybrydą** najlepszych cech obu analizowanych:

| Cecha | PW | WPSE | Twój |
|---|---|---|---|
| Preview & commit UX | ✓ | ✗ | ✓ |
| Live spreadsheet | ✗ | ✓ | ✓ (jako tryb opcjonalny) |
| SQL-like operatory filtra | ✗ | ✓ | ✓ |
| Save/load filtrów | ✓ | ✓ | ✓ |
| Formula engine | częściowo | ✓ | ✓ |
| Auto-detect custom fields | ✗ | ✓ | ✓ |
| Import CSV | ✗ | ✓ | ✓ |
| Export CSV | ✓ | ✓ | ✓ |
| Undo/Redo z historią | ✓ | ograniczone | ✓ |
| Bulk delete | ✓ | ✓ | ✓ |
| Tworzenie wariantów masowo | ✓ | ✓ | ✓ |
| Audit log | ✗ | ✗ | ✓ (przewaga) |
| HPOS-ready | ✓ | ✓ | ✓ |
| ACF głęboka integracja | ✗ | częściowo | ✓ (przewaga) |

Audit log z możliwością rollbacku to najmocniejsza potencjalna przewaga, której nie ma żaden z analizowanych konkurentów.
