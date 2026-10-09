<?php

namespace Tests\Feature;

use App\Exceptions\ReferencedSetupRecordException;
use App\Filament\Resources\EvaluationCriterias\Pages\CreateEvaluationCriteria;
use App\Filament\Resources\EvaluationCriterias\Pages\EditEvaluationCriteria;
use App\Filament\Resources\EvaluationCriterias\Pages\ListEvaluationCriterias;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationScore;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class EvaluationCriteriaResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getDefaultPanel());
    }

    private function userWithRole(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => $role.Str::random(6).'@example.test',
            'password' => Str::random(24),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function actingAsOwner(): User
    {
        $owner = $this->userWithRole(User::ROLE_OWNER);
        $this->actingAs($owner);

        return $owner;
    }

    public function test_owner_creates_criteria_and_active_total_is_tracked(): void
    {
        $this->actingAsOwner();

        Livewire::test(CreateEvaluationCriteria::class)
            ->fillForm(['name' => 'Kedisiplinan', 'weight' => 40, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(40, EvaluationCriteria::activeWeightTotal());
    }

    public function test_weight_must_be_between_1_and_100(): void
    {
        $this->actingAsOwner();

        foreach ([0, 101] as $weight) {
            Livewire::test(CreateEvaluationCriteria::class)
                ->fillForm(['name' => 'Kriteria', 'weight' => $weight, 'is_active' => true])
                ->call('create')
                ->assertHasFormErrors(['weight']);
        }

        $this->assertSame(0, EvaluationCriteria::count());
    }

    public function test_total_active_weight_cannot_exceed_100(): void
    {
        $this->actingAsOwner();
        EvaluationCriteria::create(['name' => 'Kedisiplinan', 'weight' => 70]);
        $cooperation = EvaluationCriteria::create(['name' => 'Kerja sama', 'weight' => 30]);

        Livewire::test(CreateEvaluationCriteria::class)
            ->fillForm(['name' => 'Pelayanan', 'weight' => 1, 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['weight']);

        Livewire::test(EditEvaluationCriteria::class, ['record' => $cooperation->getKey()])
            ->fillForm(['weight' => 30])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(EditEvaluationCriteria::class, ['record' => $cooperation->getKey()])
            ->fillForm(['weight' => 31])
            ->call('save')
            ->assertHasFormErrors(['weight']);
    }

    public function test_inactive_criteria_do_not_count_toward_the_total(): void
    {
        $this->actingAsOwner();
        EvaluationCriteria::create(['name' => 'Lama', 'weight' => 90, 'is_active' => false]);
        EvaluationCriteria::create(['name' => 'Disiplin', 'weight' => 60]);

        $this->assertSame(60, EvaluationCriteria::activeWeightTotal());

        Livewire::test(CreateEvaluationCriteria::class)
            ->fillForm(['name' => 'Pelayanan', 'weight' => 40, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_table_shows_running_total_and_warning_when_not_100(): void
    {
        $this->actingAsOwner();
        $criteria = EvaluationCriteria::create(['name' => 'Disiplin', 'weight' => 60]);

        Livewire::test(ListEvaluationCriterias::class)
            ->assertCanSeeTableRecords([$criteria])
            ->assertSee('Total bobot aktif: 60 dari 100')
            ->assertSee('Total belum 100');

        EvaluationCriteria::create(['name' => 'Kerja sama', 'weight' => 40]);

        Livewire::test(ListEvaluationCriterias::class)
            ->assertSee('Total bobot aktif: 100 dari 100')
            ->assertDontSee('Total belum 100');
    }

    public function test_criteria_with_scores_cannot_be_deleted(): void
    {
        $owner = $this->actingAsOwner();
        $used = EvaluationCriteria::create(['name' => 'Dipakai', 'weight' => 50]);
        $free = EvaluationCriteria::create(['name' => 'Bebas', 'weight' => 50]);
        $employee = Employee::create(['name' => 'Ayu']);
        $evaluation = Evaluation::create(['employee_id' => $employee->id, 'period' => '2026-09', 'total_score' => 80]);
        EvaluationScore::create(['evaluation_id' => $evaluation->id, 'criteria_id' => $used->id, 'score' => 80]);

        $this->assertFalse($owner->can('delete', $used));
        $this->assertTrue($owner->can('delete', $free));

        $this->expectException(ReferencedSetupRecordException::class);
        $used->delete();
    }

    public function test_only_owner_can_access_criteria(): void
    {
        foreach ([User::ROLE_FRONT_OFFICE, User::ROLE_CASHIER] as $role) {
            $this->actingAs($this->userWithRole($role));
            Livewire::test(ListEvaluationCriterias::class)->assertForbidden();
        }

        $this->actingAsOwner();
        Livewire::test(ListEvaluationCriterias::class)->assertSuccessful();
    }
}
