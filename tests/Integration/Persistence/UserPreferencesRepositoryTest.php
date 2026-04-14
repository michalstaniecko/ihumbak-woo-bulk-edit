<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Persistence;

use IhumbakWooBulkEdit\Persistence\UserPreferencesRepository;
use WP_UnitTestCase;

/**
 * Integration tests for UserPreferencesRepository.
 *
 * @license GPL-2.0-or-later
 */
final class UserPreferencesRepositoryTest extends WP_UnitTestCase
{
    private UserPreferencesRepository $repo;

    public function set_up(): void
    {
        parent::set_up();
        $this->repo = new UserPreferencesRepository();
    }

    private function createUser(): int
    {
        return (int) self::factory()->user->create(['role' => 'editor']);
    }

    // ── Test 1 ─────────────────────────────────────────────────

    public function test_get_returns_default_for_user_without_meta(): void
    {
        $userId = $this->createUser();

        $result = $this->repo->getColumnVisibility($userId);

        self::assertIsArray($result);
        self::assertArrayHasKey('hidden', $result);
        self::assertArrayHasKey('version', $result);
        self::assertSame([], $result['hidden']);
        self::assertSame(UserPreferencesRepository::SCHEMA_VERSION, $result['version']);
    }

    // ── Test 2 ─────────────────────────────────────────────────

    public function test_update_then_get_roundtrip(): void
    {
        $userId = $this->createUser();

        $this->repo->updateColumnVisibility($userId, ['description', 'weight', 'length']);

        $result = $this->repo->getColumnVisibility($userId);

        self::assertSame(['description', 'weight', 'length'], $result['hidden']);
        self::assertSame(UserPreferencesRepository::SCHEMA_VERSION, $result['version']);
    }

    // ── Test 3 ─────────────────────────────────────────────────

    public function test_update_filters_out_pinned_columns(): void
    {
        $userId = $this->createUser();

        $this->repo->updateColumnVisibility($userId, ['select', 'id', 'description']);

        $result = $this->repo->getColumnVisibility($userId);

        self::assertContains('description', $result['hidden']);
        self::assertNotContains('select', $result['hidden']);
        self::assertNotContains('id', $result['hidden']);
    }

    // ── Test 4 ─────────────────────────────────────────────────

    public function test_reset_deletes_meta(): void
    {
        $userId = $this->createUser();

        $this->repo->updateColumnVisibility($userId, ['description', 'weight']);
        $this->repo->resetColumnVisibility($userId);

        $result = $this->repo->getColumnVisibility($userId);

        self::assertSame([], $result['hidden']);
    }
}
