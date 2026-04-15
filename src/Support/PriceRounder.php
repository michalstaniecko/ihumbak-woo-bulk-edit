<?php
/**
 * PriceRounder — special-ending price rounding helper.
 *
 * @package IhumbakWooBulkEdit
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Support;

/**
 * Rounds a price UP to a "special ending" (.00, .90, or .99).
 *
 * Integer-cents math is used throughout to avoid floating-point drift.
 *
 * Rounding semantics
 * ------------------
 * .00  — if fractional cents ≠ 0, advance to next integer; idempotent if already .00.
 * .90  — if frac <  90 → same integer .90
 *         if frac == 90 → unchanged
 *         if frac >  90 → next integer .90
 * .99  — if frac == 99 → unchanged; otherwise → same integer .99
 * none — passthrough, no change.
 */
final class PriceRounder
{
    public const ENDING_NONE = 'none';
    public const ENDING_00   = '00';
    public const ENDING_90   = '90';
    public const ENDING_99   = '99';
    public const ENDING_9_00 = '9_00';

    /**
     * Returns all valid ending identifiers.
     *
     * @return string[]
     */
    public static function endings(): array
    {
        return [
            self::ENDING_NONE,
            self::ENDING_00,
            self::ENDING_90,
            self::ENDING_99,
            self::ENDING_9_00,
        ];
    }

    /**
     * Apply a special ending to a price.
     *
     * @param float  $price  The price to round.
     * @param string $ending One of the ENDING_* constants.
     * @return float         The rounded price.
     *
     * @throws \InvalidArgumentException When an unknown ending is provided.
     */
    public static function applyEnding(float $price, string $ending): float
    {
        if ($ending === self::ENDING_NONE) {
            return $price;
        }

        if (!in_array($ending, self::endings(), true)) {
            throw new \InvalidArgumentException(
                sprintf('Unknown price ending "%s".', $ending)
            );
        }

        // Work in integer cents to avoid floating-point drift.
        $cents     = (int) round($price * 100);
        $wholePart = intdiv($cents, 100);   // integer part (floors toward zero)
        $fracPart  = $cents % 100;          // 0–99 (always non-negative for positive prices)

        switch ($ending) {
            case self::ENDING_00:
                if ($fracPart === 0) {
                    return (float) $wholePart;
                }
                return (float) ($wholePart + 1);

            case self::ENDING_90:
                if ($fracPart === 90) {
                    return $wholePart + 0.90;
                }
                if ($fracPart < 90) {
                    return $wholePart + 0.90;
                }
                // fracPart > 90 → advance to next integer, then .90
                return ($wholePart + 1) + 0.90;

            case self::ENDING_99:
                // idempotent when already .99; otherwise same integer .99
                return $wholePart + 0.99;

            case self::ENDING_9_00:
                // Round UP to nearest integer ending in 9 with zero cents (9, 19, 29, ..., 99, 109, ...).
                if ($wholePart % 10 === 9 && $fracPart === 0) {
                    return (float) $wholePart; // already X9.00 — idempotent
                }
                $effectiveWhole = $wholePart + ($fracPart > 0 ? 1 : 0);
                $lastDigit      = $effectiveWhole % 10;
                $diff           = (9 - $lastDigit + 10) % 10;
                return (float) ($effectiveWhole + $diff);
        }

        // Unreachable — the in_array guard above covers all cases.
        return $price; // @codeCoverageIgnore
    }
}
