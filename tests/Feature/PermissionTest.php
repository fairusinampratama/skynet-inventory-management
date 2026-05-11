<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_user_management(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($admin);

        $this->assertTrue(UserResource::canAccess());
    }

    public function test_warehouse_cannot_access_user_management(): void
    {
        $warehouse = User::create([
            'name' => 'Warehouse',
            'email' => 'warehouse@example.com',
            'password' => 'password',
            'role' => UserRole::Warehouse,
        ]);

        $this->actingAs($warehouse);

        $this->assertFalse(UserResource::canAccess());
    }
}
