# Implement Feature with Tests (TDD)

Implement a new feature using Test-Driven Development. Write tests FIRST, then implement the code to make them pass.

## Input

Feature description: $ARGUMENTS

## Workflow

Follow these steps strictly in order:

### Step 1: Analyze the feature requirements

- Read the spec in `docs/specyfikacja-bulk-edit-woocommerce.md` if relevant
- Read `CLAUDE.md` for architectural decisions and conventions
- Identify which layer(s) the feature touches: Fields, Operators, Operations, Persistence, API, Security
- Identify existing interfaces/abstractions the feature must implement

### Step 2: Determine test type and plan tests

Decide whether tests should be **Unit** or **Integration** based on these rules:

**Unit tests** (`tests/Unit/`) — when the class:
- Has no WordPress/WooCommerce function dependencies (or they are stubbed in `tests/Unit/bootstrap.php`)
- Is a pure data class, value object, enum, or container
- Implements `FieldInterface`, `OperatorInterface` (for operators that don't use `$wpdb`)
- Examples: Fields, FieldRegistry, Container, EqualOperator, NotEqualOperator, IsEmptyOperator

**Integration tests** (`tests/Integration/`) — when the class:
- Needs WordPress functions (`wp_insert_post`, `update_post_meta`, `get_current_user_id`, etc.)
- Needs `$wpdb` (LIKE/NOT LIKE operators, QueryBuilder with real DB)
- Is a REST API controller
- Tests security (capabilities, rate limiting)
- Examples: LikeOperator, FilterParser, REST controllers, RateLimiter, CapabilityChecker

### Step 3: Write the tests FIRST

Create test file(s) following these exact conventions:

**File location:** Mirror the `src/` structure under `tests/Unit/` or `tests/Integration/`
- `src/Fields/Core/WeightField.php` → `tests/Unit/Fields/Core/WeightFieldTest.php`
- `src/Operations/SetValue.php` → `tests/Unit/Operations/SetValueTest.php`
- `src/Api/FiltersController.php` → `tests/Integration/Api/FiltersControllerTest.php`

**File structure (Unit):**
```php
<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\{SubNamespace};

use PHPUnit\Framework\TestCase;
// ... imports

final class {ClassName}Test extends TestCase
{
    private {ClassName} $subject;

    protected function setUp(): void
    {
        $this->subject = new {ClassName}();
    }

    public function test_{method_or_scenario}(): void
    {
        // Arrange, Act, Assert
        self::assertSame($expected, $actual);
    }
}
```

**File structure (Integration):**
```php
<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\{SubNamespace};

// ... imports

final class {ClassName}Test extends \WP_UnitTestCase
{
    private {ClassName} $subject;

    public function set_up(): void   // NOTE: set_up() not setUp()
    {
        parent::set_up();
        // Create admin user, set current user, init dependencies
        $user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        $this->subject = new {ClassName}();
    }

    public function test_{method_or_scenario}(): void
    {
        self::assertSame($expected, $actual);
    }
}
```

**Naming rules:**
- Test methods: `test_{scenario_in_snake_case}` — be descriptive
- For Fields: always test `test_key()`, `test_type()`, `test_label()`, `test_sanitize_*()`, `test_validate_*()`, `test_toArray()`
- For Operators: always test `test_identifier()`, `test_toSql_*()` for various inputs
- For REST endpoints: test success response, missing params, unauthorized access, invalid input
- For Operations: test apply with valid input, edge cases, error conditions

**Assertion style:**
- Always use `self::assert*()` (not `$this->assert*()`)
- Prefer `assertSame()` over `assertEquals()` for strict type checking
- Use `assertInstanceOf()`, `assertCount()`, `assertArrayHasKey()`, `assertStringContainsString()`
- For REST: `self::assertSame(200, $response->get_status())` and check `$response->get_data()`
- For validation: check `true` for valid, `string` for error message
- For SQL operators: check `['sql' => string, 'values' => array]` return format

**Mocking (Unit tests only):**
```php
$mock = $this->createMock(InterfaceClass::class);
$mock->method('getKey')->willReturn('key');
```

**REST API testing pattern:**
```php
public function set_up(): void
{
    parent::set_up();
    add_action('rest_api_init', static function (): void {
        $controller = new Controller(...dependencies);
        $controller->register_routes();
    });
    do_action('rest_api_init', rest_get_server());
    $user_id = self::factory()->user->create(['role' => 'administrator']);
    wp_set_current_user($user_id);
}

public function test_endpoint(): void
{
    $request = new \WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/endpoint');
    $request->set_body_params([...]);
    $response = rest_get_server()->dispatch($request);
    self::assertSame(200, $response->get_status());
}
```

**Product creation helper (Integration):**
```php
private function createProduct(string $title, array $meta = [], string $status = 'publish'): int
{
    $id = wp_insert_post([
        'post_title'  => $title,
        'post_type'   => 'product',
        'post_status' => $status,
    ]);
    foreach ($meta as $key => $value) {
        update_post_meta($id, $key, $value);
    }
    return $id;
}
```

### Step 4: Run the tests — they should FAIL

Run:
- Unit tests: `vendor/bin/phpunit --testsuite unit --filter {TestClassName}`
- Integration tests: `vendor/bin/phpunit --testsuite integration --bootstrap tests/Integration/bootstrap.php --filter {TestClassName}`

Verify tests fail for the right reason (class not found, method not found — NOT syntax errors).

### Step 5: Implement the feature

Write the implementation in `src/` following these conventions:
- `declare(strict_types=1)` in every file
- PSR-4 namespace: `IhumbakWooBulkEdit\{SubNamespace}`
- Implement the relevant interface (`FieldInterface`, `OperatorInterface`, etc.)
- Follow existing patterns from similar classes in the same directory
- SQL: only `$wpdb->prepare()`, never string concatenation
- Sanitize input, escape output
- Register new services in `Container.php` and wire in `Plugin.php` if needed

### Step 6: Run the tests — they should PASS

Run the same test command from Step 4. All tests must pass.
If tests fail, fix the implementation (not the tests, unless the test had a bug).

### Step 7: Run the full test suite

Run `vendor/bin/phpunit --testsuite unit` to ensure no regressions.

### Step 8: Summary

Report:
- What was implemented (files created/modified)
- Test results (pass count)
- Any decisions made and why
- What should be done next (if applicable)

## Important Rules

- NEVER skip writing tests
- NEVER write tests after implementation — tests come FIRST
- NEVER use `any` type in TypeScript or skip `declare(strict_types=1)` in PHP
- NEVER concatenate SQL strings — always use `$wpdb->prepare()`
- If the unit bootstrap is missing a WordPress/WooCommerce function stub, ADD it to `tests/Unit/bootstrap.php`
- Keep tests focused: one assertion per concept, descriptive method names
- Do NOT modify existing tests unless the feature changes their contract
