<?php

namespace Tests\Unit;

use App\Models\Estimate;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class EstimateDuplicationTest extends TestCase
{
    public function test_duplicated_estimate_can_be_edited_by_creator()
    {
        $creator = new User(['role' => 'Manager', 'department' => 'Creative']);
        $creator->id = 10;
        
        $estimate = new Estimate(['user_id' => 10, 'is_duplicated' => true]);
        $estimate->setRelation('user', $creator);

        $this->assertTrue($estimate->canEdit($creator));
    }

    public function test_duplicated_estimate_can_be_edited_by_associated_hod()
    {
        $creator = new User(['role' => 'Manager', 'department' => 'Creative', 'supervisor_id' => 5]);
        $creator->id = 10;

        $hodSameDept = new User(['role' => 'HOD', 'department' => 'Creative']);
        $hodSameDept->id = 5;

        $estimate = new Estimate(['user_id' => 10, 'is_duplicated' => true]);
        $estimate->setRelation('user', $creator);

        $this->assertTrue($estimate->canEdit($hodSameDept));
    }

    public function test_duplicated_estimate_can_be_edited_by_super_admin()
    {
        $creator = new User(['role' => 'Manager', 'department' => 'Creative']);
        $creator->id = 10;

        $superAdmin = new User(['role' => 'Super Admin', 'department' => 'Corporate']);
        $superAdmin->id = 1;

        $estimate = new Estimate(['user_id' => 10, 'is_duplicated' => true]);
        $estimate->setRelation('user', $creator);

        $this->assertTrue($estimate->canEdit($superAdmin));
    }

    public function test_duplicated_estimate_can_be_edited_by_it_admin()
    {
        $creator = new User(['role' => 'Manager', 'department' => 'Creative']);
        $creator->id = 10;

        $itAdmin = new User(['role' => 'IT Admin', 'department' => 'Tech']);
        $itAdmin->id = 2;

        $estimate = new Estimate(['user_id' => 10, 'is_duplicated' => true]);
        $estimate->setRelation('user', $creator);

        $this->assertTrue($estimate->canEdit($itAdmin));
    }

    public function test_duplicated_estimate_cannot_be_edited_by_general_management()
    {
        $creator = new User(['role' => 'Manager', 'department' => 'Creative']);
        $creator->id = 10;

        $management = new User(['role' => 'Management', 'department' => 'Corporate']);
        $management->id = 3;

        $estimate = new Estimate(['user_id' => 10, 'is_duplicated' => true]);
        $estimate->setRelation('user', $creator);

        $this->assertFalse($estimate->canEdit($management));
    }

    public function test_duplicated_estimate_cannot_be_edited_by_other_department_hod()
    {
        $creator = new User(['role' => 'Manager', 'department' => 'Creative', 'supervisor_id' => 5]);
        $creator->id = 10;

        $hodOtherDept = new User(['role' => 'HOD', 'department' => 'Digital']);
        $hodOtherDept->id = 8;

        $estimate = new Estimate(['user_id' => 10, 'is_duplicated' => true]);
        $estimate->setRelation('user', $creator);

        $this->assertFalse($estimate->canEdit($hodOtherDept));
    }

    public function test_duplicated_estimate_cannot_be_edited_by_other_manager()
    {
        $creator = new User(['role' => 'Manager', 'department' => 'Creative']);
        $creator->id = 10;

        $otherManager = new User(['role' => 'Manager', 'department' => 'Creative']);
        $otherManager->id = 11;

        $estimate = new Estimate(['user_id' => 10, 'is_duplicated' => true]);
        $estimate->setRelation('user', $creator);

        $this->assertFalse($estimate->canEdit($otherManager));
    }
}
