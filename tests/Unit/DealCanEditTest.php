<?php

namespace Tests\Unit;

use App\Models\Deal;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class DealCanEditTest extends TestCase
{
    public function test_super_admin_can_edit_any_deal()
    {
        $admin = new User(['id' => 1, 'role' => 'Super Admin', 'department' => 'Tech']);
        $manager = new User(['id' => 2, 'role' => 'Manager', 'department' => 'Creative']);
        $deal = new Deal(['user_id' => 2]);
        $deal->setRelation('owner', $manager);

        $this->assertTrue($deal->canEdit($admin));
    }

    public function test_management_can_edit_any_deal()
    {
        $management = new User(['id' => 1, 'role' => 'Management', 'department' => 'Corporate']);
        $manager = new User(['id' => 2, 'role' => 'Manager', 'department' => 'Creative']);
        $deal = new Deal(['user_id' => 2]);
        $deal->setRelation('owner', $manager);

        $this->assertTrue($deal->canEdit($management));
    }

    public function test_owner_can_edit_own_deal()
    {
        $manager = new User(['id' => 2, 'role' => 'Manager', 'department' => 'Creative']);
        $deal = new Deal(['user_id' => 2]);
        $deal->setRelation('owner', $manager);

        $this->assertTrue($deal->canEdit($manager));
    }

    public function test_hod_can_edit_deals_owned_by_managers_in_same_department()
    {
        $hod = new User(['id' => 1, 'role' => 'HOD', 'department' => 'Creative']);
        $manager = new User(['id' => 2, 'role' => 'Manager', 'department' => 'Creative', 'supervisor_id' => 1]);
        $deal = new Deal(['user_id' => 2]);
        $deal->setRelation('owner', $manager);

        $this->assertTrue($deal->canEdit($hod));
    }

    public function test_hod_cannot_edit_deals_owned_by_managers_in_different_department()
    {
        $hod = new User(['id' => 1, 'role' => 'HOD', 'department' => 'Creative']);
        $managerOtherDept = new User(['id' => 3, 'role' => 'Manager', 'department' => 'Digital', 'supervisor_id' => 99]);
        $deal = new Deal(['user_id' => 3]);
        $deal->setRelation('owner', $managerOtherDept);

        $this->assertFalse($deal->canEdit($hod));
    }
}
