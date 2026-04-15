<?php
/**
 * PriceRounderTest — unit tests for the special-ending price rounding helper.
 *
 * @package IhumbakWooBulkEdit\Tests\Unit\Support
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Support;

use IhumbakWooBulkEdit\Support\PriceRounder;
use PHPUnit\Framework\TestCase;

final class PriceRounderTest extends TestCase
{
    // ── endings() ─────────────────────────────────────────────────────────

    public function test_endings_returns_all_constants(): void
    {
        self::assertSame(
            ['none', '00', '90', '99', '9_00'],
            PriceRounder::endings()
        );
    }

    // ── ENDING_NONE passthrough ────────────────────────────────────────────

    public function test_none_returns_price_unchanged(): void
    {
        self::assertSame(12.34, PriceRounder::applyEnding(12.34, PriceRounder::ENDING_NONE));
        self::assertSame(9.99, PriceRounder::applyEnding(9.99, PriceRounder::ENDING_NONE));
        self::assertSame(0.0, PriceRounder::applyEnding(0.0, PriceRounder::ENDING_NONE));
    }

    // ── .00 rounding ──────────────────────────────────────────────────────

    /**
     * @dataProvider provider_ending_00
     */
    public function test_ending_00(float $input, float $expected): void
    {
        self::assertEqualsWithDelta(
            $expected,
            PriceRounder::applyEnding($input, PriceRounder::ENDING_00),
            0.0001
        );
    }

    /**
     * @return array<string, array{float, float}>
     */
    public static function provider_ending_00(): array
    {
        return [
            'fractional rounds UP to next integer'  => [12.34, 13.00],
            'already .00 stays unchanged'           => [10.00,  10.00],
            'fractional .01 rounds to next integer' => [10.01,  11.00],
            'fractional .99 rounds to next integer' => [10.99,  11.00],
        ];
    }

    // ── .90 rounding ──────────────────────────────────────────────────────

    /**
     * @dataProvider provider_ending_90
     */
    public function test_ending_90(float $input, float $expected): void
    {
        self::assertEqualsWithDelta(
            $expected,
            PriceRounder::applyEnding($input, PriceRounder::ENDING_90),
            0.0001
        );
    }

    /**
     * @return array<string, array{float, float}>
     */
    public static function provider_ending_90(): array
    {
        return [
            'frac < 90 → same integer .90'          => [12.34, 12.90],
            'frac 55 → same integer .90'            => [ 9.55,  9.90],
            'frac > 90 → next integer .90'          => [ 9.95, 10.90],
            'already .90 → unchanged'               => [ 9.90,  9.90],
        ];
    }

    // ── .99 rounding ──────────────────────────────────────────────────────

    /**
     * @dataProvider provider_ending_99
     */
    public function test_ending_99(float $input, float $expected): void
    {
        self::assertEqualsWithDelta(
            $expected,
            PriceRounder::applyEnding($input, PriceRounder::ENDING_99),
            0.0001
        );
    }

    /**
     * @return array<string, array{float, float}>
     */
    public static function provider_ending_99(): array
    {
        return [
            'fractional → same integer .99'         => [12.34, 12.99],
            'frac 55 → same integer .99'            => [ 9.55,  9.99],
            'already .99 → unchanged'               => [ 9.99,  9.99],
            'zero frac → same integer .99'          => [10.00, 10.99],
            'zero → 0.99'                           => [ 0.00,  0.99],
        ];
    }

    // ── Floating-point stability ───────────────────────────────────────────

    public function test_fp_drift_is_handled_for_ending_99(): void
    {
        // 0.1 + 0.2 in IEEE-754 produces 0.30000000000000004; must still → 0.99
        $price = 0.1 + 0.2;
        self::assertEqualsWithDelta(
            0.99,
            PriceRounder::applyEnding($price, PriceRounder::ENDING_99),
            0.0001
        );
    }

    // ── x9.00 rounding ────────────────────────────────────────────────────

    /**
     * @dataProvider provider_ending_9_00
     */
    public function test_ending_9_00(float $input, float $expected): void
    {
        self::assertEqualsWithDelta(
            $expected,
            PriceRounder::applyEnding($input, PriceRounder::ENDING_9_00),
            0.0001
        );
    }

    /**
     * @return array<string, array{float, float}>
     */
    public static function provider_ending_9_00(): array
    {
        return [
            '5.00 rounds to 9.00'              => [  5.00,   9.00],
            'already 9.00 → unchanged'         => [  9.00,   9.00],
            '9.01 rounds to 19.00'             => [  9.01,  19.00],
            '9.50 rounds to 19.00 (not 9.00)'  => [  9.50,  19.00],
            '10.00 rounds to 19.00'            => [ 10.00,  19.00],
            '18.99 rounds to 19.00'            => [ 18.99,  19.00],
            'already 19.00 → unchanged'        => [ 19.00,  19.00],
            'already 99.00 → unchanged'        => [ 99.00,  99.00],
            '99.01 rounds to 109.00'           => [ 99.01, 109.00],
            '100.00 rounds to 109.00'          => [100.00, 109.00],
        ];
    }

    // ── Invalid ending ─────────────────────────────────────────────────────

    public function test_unknown_ending_throws_invalid_argument_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown price ending/');
        PriceRounder::applyEnding(12.34, 'invalid');
    }
}
