<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Target;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $itAdmin;
    private User $financeAdmin;
    private User $management;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->itAdmin = User::factory()->create([
            'email' => 'itadmin@test.com',
            'role' => 'IT Admin',
        ]);

        $this->financeAdmin = User::factory()->create([
            'email' => 'finance@test.com',
            'role' => 'Finance Admin',
        ]);

        $this->management = User::factory()->create([
            'email' => 'mgmt@test.com',
            'role' => 'Management',
        ]);

        $this->staff = User::factory()->create([
            'email' => 'staff@test.com',
            'role' => 'Staff',
        ]);
    }

    public function test_it_admin_can_view_departments_in_settings(): void
    {
        $response = $this->actingAs($this->itAdmin)->get(route('settings.index', ['section' => 'departments']));

        $response->assertOk();
        $response->assertSee('Departments');
        $response->assertSee('Add Department');
    }

    public function test_it_admin_can_create_a_department(): void
    {
        $response = $this->actingAs($this->itAdmin)->post(route('settings.storeDepartment'), [
            'name' => 'Cyber Security',
            'group' => 'Tech',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('settings.index', ['section' => 'departments']));
        $this->assertDatabaseHas('departments', [
            'name' => 'Cyber Security',
            'group' => 'Tech',
            'status' => 'active',
        ]);
    }

    public function test_department_name_must_be_unique(): void
    {
        Department::create([
            'name' => 'Existing Dept',
            'group' => 'SBU',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->itAdmin)->post(route('settings.storeDepartment'), [
            'name' => 'Existing Dept',
            'group' => 'SBU',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_it_admin_can_update_department_and_cascade_renames(): void
    {
        $department = Department::create([
            'name' => 'Old Department',
            'group' => 'SBU',
            'status' => 'active',
        ]);

        // User assigned to this department
        $user = User::factory()->create([
            'department' => 'Old Department',
            'role' => 'Staff',
        ]);

        // Target assigned to this department
        Target::create([
            'type' => 'department',
            'department' => 'Old Department',
            'target_amount' => 500000,
        ]);

        $response = $this->actingAs($this->itAdmin)->put(route('settings.updateDepartment', $department), [
            'name' => 'New Department Name',
            'group' => 'Operations',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('settings.index', ['section' => 'departments']));
        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'New Department Name',
            'group' => 'Operations',
        ]);

        // Verify cascading to User and Target
        $this->assertEquals('New Department Name', $user->fresh()->department);
        $this->assertDatabaseHas('targets', [
            'type' => 'department',
            'department' => 'New Department Name',
            'target_amount' => 500000,
        ]);
    }

    public function test_cannot_delete_department_with_assigned_users(): void
    {
        $department = Department::create([
            'name' => 'Assigned Dept',
            'group' => 'SBU',
            'status' => 'active',
        ]);

        User::factory()->create([
            'department' => 'Assigned Dept',
            'role' => 'Staff',
        ]);

        $response = $this->actingAs($this->itAdmin)->delete(route('settings.destroyDepartment', $department));

        $response->assertRedirect(route('settings.index', ['section' => 'departments']));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'Assigned Dept',
        ]);
    }

    public function test_can_delete_unassigned_department(): void
    {
        $department = Department::create([
            'name' => 'Empty Dept',
            'group' => 'SBU',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->itAdmin)->delete(route('settings.destroyDepartment', $department));

        $response->assertRedirect(route('settings.index', ['section' => 'departments']));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('departments', [
            'id' => $department->id,
        ]);
    }

    public function test_standard_staff_user_cannot_manage_departments(): void
    {
        $department = Department::create([
            'name' => 'Protected Dept',
            'group' => 'SBU',
            'status' => 'active',
        ]);

        // Staff user gets redirected to dashboard by PreventStaffAccess
        $resStoreStaff = $this->actingAs($this->staff)->post(route('settings.storeDepartment'), [
            'name' => 'Hacker Dept',
            'group' => 'SBU',
            'status' => 'active',
        ]);
        $resStoreStaff->assertRedirect(route('dashboard'));

        // HOD user is blocked with 403 Forbidden by RoleMiddleware
        $hod = User::factory()->create([
            'email' => 'hod@test.com',
            'role' => 'HOD',
        ]);

        $resStoreHod = $this->actingAs($hod)->post(route('settings.storeDepartment'), [
            'name' => 'Hacker Dept',
            'group' => 'SBU',
            'status' => 'active',
        ]);
        $resStoreHod->assertForbidden();

        $resUpdateHod = $this->actingAs($hod)->put(route('settings.updateDepartment', $department), [
            'name' => 'Hacked Dept',
            'group' => 'SBU',
            'status' => 'active',
        ]);
        $resUpdateHod->assertForbidden();

        $resDeleteHod = $this->actingAs($hod)->delete(route('settings.destroyDepartment', $department));
        $resDeleteHod->assertForbidden();
    }
}
