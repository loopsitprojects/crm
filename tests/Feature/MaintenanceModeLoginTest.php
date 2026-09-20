<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MaintenanceModeLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_when_maintenance_mode_is_off_users_can_login(): void
    {
        Setting::set('maintenance_mode', 0);

        $user = User::factory()->create([
            'email' => 'staff@test.com',
            'password' => Hash::make('password123'),
            'role' => 'Staff',
        ]);

        $response = $this->post('/login', [
            'email' => 'staff@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_when_maintenance_mode_is_off_maintenance_page_redirects_to_login(): void
    {
        Setting::set('maintenance_mode', 0);

        $response = $this->get('/maintenance');
        $response->assertRedirect(route('login'));
    }

    public function test_when_mode_1_active_standard_user_login_redirects_to_maintenance(): void
    {
        Setting::set('maintenance_mode', 1);

        $user = User::factory()->create([
            'email' => 'hod@test.com',
            'password' => Hash::make('password123'),
            'role' => 'HOD',
        ]);

        $response = $this->post('/login', [
            'email' => 'hod@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('maintenance'));
        $this->assertGuest();
    }

    public function test_when_mode_1_active_admin_can_login(): void
    {
        Setting::set('maintenance_mode', 1);

        $admin = User::factory()->create([
            'email' => 'finance@test.com',
            'password' => Hash::make('password123'),
            'role' => 'Finance Admin',
        ]);

        $response = $this->post('/login', [
            'email' => 'finance@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_when_mode_2_active_only_it_admin_can_login(): void
    {
        Setting::set('maintenance_mode', 2);

        // Finance Admin should be blocked in mode 2
        $finance = User::factory()->create([
            'email' => 'finance_mode2@test.com',
            'password' => Hash::make('password123'),
            'role' => 'Finance Admin',
        ]);

        $responseFinance = $this->post('/login', [
            'email' => 'finance_mode2@test.com',
            'password' => 'password123',
        ]);

        $responseFinance->assertRedirect(route('maintenance'));
        $this->assertGuest();

        // IT Admin should be allowed in mode 2
        $itAdmin = User::factory()->create([
            'email' => 'itadmin@test.com',
            'password' => Hash::make('password123'),
            'role' => 'IT Admin',
        ]);

        $responseIt = $this->post('/login', [
            'email' => 'itadmin@test.com',
            'password' => 'password123',
        ]);

        $responseIt->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($itAdmin);
    }

    public function test_maintenance_page_shows_503_for_mode_1_and_mode_2(): void
    {
        Setting::set('maintenance_mode', 1);
        $response1 = $this->get('/maintenance');
        $response1->assertStatus(503);
        $response1->assertSee('System Under Maintenance');
        $response1->assertSee('Administrator Sign In');

        Setting::set('maintenance_mode', 2);
        $response2 = $this->get('/maintenance');
        $response2->assertStatus(503);
        $response2->assertSee('System Under Maintenance');
        $response2->assertSee('Administrator Sign In');
    }

    public function test_login_page_shows_maintenance_banner_when_active(): void
    {
        Setting::set('maintenance_mode', 1);
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Maintenance Mode Active');
    }
}
