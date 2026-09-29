<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementViewTest extends TestCase
{
    use RefreshDatabase;

    private User $itAdmin;
    private User $financeAdmin;
    private User $manager;
    private User $hod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->itAdmin = User::factory()->create([
            'name' => 'IT Admin User',
            'email' => 'itadmin@loops.com',
            'role' => 'IT Admin',
        ]);

        $this->financeAdmin = User::factory()->create([
            'name' => 'Finance Admin User',
            'email' => 'finance@loops.com',
            'role' => 'Finance Admin',
        ]);

        $this->hod = User::factory()->create([
            'name' => 'Varuni Jayasekara',
            'email' => 'varuni@loopsintegrated.com',
            'role' => 'HOD',
            'department' => 'AM',
        ]);

        $this->manager = User::factory()->create([
            'name' => 'Shehan Liyanage',
            'email' => 'gayuru@loopsintegrated.com',
            'role' => 'Manager',
            'department' => 'AM',
            'supervisor_id' => $this->hod->id,
        ]);
    }

    public function test_it_admin_can_view_users_index_with_tabs_and_search(): void
    {
        $response = $this->actingAs($this->itAdmin)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('Manage Users');
        $response->assertSee('Search users...');
        $response->assertSee('All Users');
        $response->assertSee('Finance Admin');
        $response->assertSee('HOD');
        $response->assertSee('Manager');
        $response->assertSee('Varuni Jayasekara');
        $response->assertSee('Shehan Liyanage');
    }

    public function test_finance_admin_can_view_users_index(): void
    {
        $response = $this->actingAs($this->financeAdmin)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('Manage Users');
        $response->assertSee('Import Users');
        $response->assertSee('Add New User');
    }
}
