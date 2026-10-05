<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialPerformanceAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $itAdmin;
    private User $management;
    private User $financeAdmin;
    private User $hod;
    private User $manager;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->itAdmin = User::factory()->create([
            'name' => 'IT Admin User',
            'email' => 'itadmin@loops.com',
            'role' => 'IT Admin',
        ]);

        $this->management = User::factory()->create([
            'name' => 'Management User',
            'email' => 'mgmt@loops.com',
            'role' => 'Management',
        ]);

        $this->financeAdmin = User::factory()->create([
            'name' => 'Finance Admin User',
            'email' => 'finance@loops.com',
            'role' => 'Finance Admin',
        ]);

        $this->hod = User::factory()->create([
            'name' => 'HOD User',
            'email' => 'hod@loops.com',
            'role' => 'HOD',
            'department' => 'AM',
        ]);

        $this->manager = User::factory()->create([
            'name' => 'Manager User',
            'email' => 'manager@loops.com',
            'role' => 'Manager',
            'department' => 'AM',
        ]);

        $this->staff = User::factory()->create([
            'name' => 'Staff User',
            'email' => 'staff@loops.com',
            'role' => 'Staff',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('financial-performance.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_staff_is_blocked(): void
    {
        $response = $this->actingAs($this->staff)->get(route('financial-performance.index'));
        $response->assertRedirect(route('dashboard'));
    }

    public function test_manager_is_blocked(): void
    {
        $response = $this->actingAs($this->manager)->get(route('financial-performance.index'));
        $response->assertForbidden();
    }

    public function test_hod_is_blocked(): void
    {
        $response = $this->actingAs($this->hod)->get(route('financial-performance.index'));
        $response->assertForbidden();
    }

    public function test_finance_admin_is_strictly_blocked(): void
    {
        // By default, setting is false
        $response = $this->actingAs($this->financeAdmin)->get(route('financial-performance.index'));
        $response->assertForbidden();
    }

    public function test_finance_admin_can_access_when_enabled_by_it_admin(): void
    {
        \App\Models\Setting::set('financial_performance_finance_admin_enabled', '1', 'permissions');

        $response = $this->actingAs($this->financeAdmin)->get(route('financial-performance.index'));
        $response->assertOk();
        $response->assertSee('Financial Performance');

        // Sidebar link becomes visible
        $this->actingAs($this->financeAdmin)
            ->get(route('dashboard'))
            ->assertSee(route('financial-performance.index'));

        // API data endpoint also becomes accessible
        $this->actingAs($this->financeAdmin)
            ->getJson(route('financial-performance.data'))
            ->assertOk();
    }

    public function test_management_can_be_disabled_by_it_admin(): void
    {
        // Allowed by default
        $this->actingAs($this->management)
            ->get(route('financial-performance.index'))
            ->assertOk();

        // IT Admin disables Management access
        \App\Models\Setting::set('financial_performance_management_enabled', '0', 'permissions');

        // Management is now blocked
        $this->actingAs($this->management)
            ->get(route('financial-performance.index'))
            ->assertForbidden();

        // Sidebar link disappears
        $this->actingAs($this->management)
            ->get(route('dashboard'))
            ->assertDontSee(route('financial-performance.index'));

        // API data endpoint is blocked
        $this->actingAs($this->management)
            ->getJson(route('financial-performance.data'))
            ->assertForbidden();
    }

    public function test_it_admin_can_update_financial_performance_settings_via_post(): void
    {
        $response = $this->actingAs($this->itAdmin)->post(route('settings.updateFinancialPerformancePermissions'), [
            'financial_performance_finance_admin_enabled' => '1',
            'financial_performance_management_enabled' => '1',
        ]);

        $response->assertRedirect(route('settings.index', ['section' => 'financial-performance-settings']));
        $response->assertSessionHas('success');

        $this->assertTrue(filter_var(\App\Models\Setting::get('financial_performance_finance_admin_enabled'), FILTER_VALIDATE_BOOLEAN));
        $this->assertTrue(filter_var(\App\Models\Setting::get('financial_performance_management_enabled'), FILTER_VALIDATE_BOOLEAN));
    }

    public function test_finance_admin_cannot_update_financial_performance_settings(): void
    {
        $response = $this->actingAs($this->financeAdmin)->post(route('settings.updateFinancialPerformancePermissions'), [
            'financial_performance_finance_admin_enabled' => '1',
        ]);

        $response->assertForbidden();
    }

    public function test_it_admin_can_access(): void
    {
        $response = $this->actingAs($this->itAdmin)->get(route('financial-performance.index'));
        $response->assertOk();
        $response->assertSee('Financial Performance');
        $response->assertSee('Loops — Team Performance Dashboard');
    }

    public function test_management_can_access(): void
    {
        $response = $this->actingAs($this->management)->get(route('financial-performance.index'));
        $response->assertOk();
        $response->assertSee('Financial Performance');
        $response->assertSee('Loops — Team Performance Dashboard');
    }

    public function test_api_data_endpoint_permissions(): void
    {
        // Blocked for Finance Admin by default
        $this->actingAs($this->financeAdmin)
            ->getJson(route('financial-performance.data'))
            ->assertForbidden();

        // Allowed for IT Admin
        $response = $this->actingAs($this->itAdmin)
            ->getJson(route('financial-performance.data'));
        $response->assertOk();
        $response->assertJsonStructure([
            'as_of',
            'departments',
            'sales_units',
            'pipeline',
            'transactions',
        ]);
    }

    public function test_sidebar_link_only_visible_to_it_admin_and_management(): void
    {
        // IT Admin sees it
        $this->actingAs($this->itAdmin)
            ->get(route('dashboard'))
            ->assertSee(route('financial-performance.index'));

        // Management sees it
        $this->actingAs($this->management)
            ->get(route('dashboard'))
            ->assertSee(route('financial-performance.index'));

        // Finance Admin does NOT see it
        $this->actingAs($this->financeAdmin)
            ->get(route('dashboard'))
            ->assertDontSee(route('financial-performance.index'));

        // Manager does NOT see it
        $this->actingAs($this->manager)
            ->get(route('dashboard'))
            ->assertDontSee(route('financial-performance.index'));

        // Staff does NOT see it
        $this->actingAs($this->staff)
            ->get(route('dashboard'))
            ->assertDontSee(route('financial-performance.index'));
    }

    public function test_dashboard_data_has_group_target_annual(): void
    {
        $response = $this->actingAs($this->itAdmin)
            ->getJson(route('financial-performance.data'));

        $response->assertOk();
        $response->assertJsonStructure([
            'group' => [
                'target_annual',
                'target_quarterly',
                'revenue',
                'contribution',
            ],
            'departments',
            'sales_units',
        ]);

        $this->assertGreaterThan(0, $response->json('group.target_annual'));
    }

    public function test_service_schema_fallback_when_baseline_missing(): void
    {
        $service = app(\App\Services\FinancialPerformanceService::class);
        $reflection = new \ReflectionMethod($service, 'ensureDefaultSchema');
        $fallback = $reflection->invoke($service, []);

        $this->assertArrayHasKey('group', $fallback);
        $this->assertArrayHasKey('target_annual', $fallback['group']);
        $this->assertEquals(190000000.0, $fallback['group']['target_annual']);
        $this->assertArrayHasKey('departments', $fallback);
        $this->assertArrayHasKey('Corporate', $fallback['departments']);
        $this->assertArrayHasKey('DM', $fallback['departments']);
        $this->assertArrayHasKey('Creative', $fallback['departments']);
        $this->assertArrayHasKey('IT', $fallback['departments']);
    }

    public function test_settings_page_shows_financial_performance_section_only_to_it_admin(): void
    {
        // IT Admin sees Financial Performance settings section and button
        $this->actingAs($this->itAdmin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('btn-financial-performance-settings')
            ->assertSee('section-financial-performance-settings')
            ->assertSee('Financial Performance Dashboard Visibility');

        // Finance Admin does NOT see it
        $this->actingAs($this->financeAdmin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertDontSee('btn-financial-performance-settings')
            ->assertDontSee('section-financial-performance-settings');

        // Management does NOT see it
        $this->actingAs($this->management)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertDontSee('btn-financial-performance-settings')
            ->assertDontSee('section-financial-performance-settings');
    }
}
