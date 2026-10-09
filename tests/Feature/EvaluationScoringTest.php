<?php

namespace Tests\Feature;

use App\Contracts\AttendanceScoreSource;
use App\Filament\Resources\Evaluations\Pages\CreateEvaluation;
use App\Filament\Resources\Evaluations\Pages\EditEvaluation;
use App\Filament\Resources\Evaluations\Pages\ListEvaluations;
use App\Filament\Resources\Evaluations\Schemas\EvaluationForm;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationCriteria;
use App\Models\User;
use App\Services\EvaluationScoreCalculator;
use App\Services\NullAttendanceScoreSource;
use Carbon\CarbonInterface;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class EvaluationScoringTest extends TestCase
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

    /** @return list<EvaluationCriteria> */
    private function makeCriteria(): array
    {
        return [
            EvaluationCriteria::create(['name' => 'Kedisiplinan', 'weight' => 30]),
            EvaluationCriteria::create(['name' => 'Kerja sama', 'weight' => 30]),
            EvaluationCriteria::create(['name' => 'Pelayanan', 'weight' => 40]),
        ];
    }

    /**
     * @param  list<EvaluationCriteria>  $criteria
     * @param  list<int>  $scores
     */
    private function scoreRows(array $criteria, array $scores): array
    {
        return collect($criteria)->values()->map(fn (EvaluationCriteria $c, int $i) => [
            'criteria_id' => $c->id,
            'score' => $scores[$i],
        ])->all();
    }

    public function test_calculator_applies_weights(): void
    {
        $total = (new EvaluationScoreCalculator)->calculate([
            ['weight' => 30, 'score' => 85],
            ['weight' => 30, 'score' => 90],
            ['weight' => 40, 'score' => 77],
        ]);

        $this->assertSame(83.3, $total);
    }

    public function test_calculator_rounds_to_two_decimals_and_handles_empty_input(): void
    {
        $calculator = new EvaluationScoreCalculator;

        $this->assertSame(50.67, $calculator->calculate([
            ['weight' => 1, 'score' => 50],
            ['weight' => 2, 'score' => 51],
        ]));
        $this->assertSame(0.0, $calculator->calculate([]));
    }

    public function test_calculator_stays_on_a_100_scale_when_weights_do_not_sum_to_100(): void
    {
        $this->assertSame(80.0, (new EvaluationScoreCalculator)->calculate([
            ['weight' => 20, 'score' => 80],
            ['weight' => 20, 'score' => 80],
        ]));
    }

    public function test_owner_creates_evaluation_with_computed_total_and_evaluator(): void
    {
        $owner = $this->actingAsOwner();
        $criteria = $this->makeCriteria();
        $employee = Employee::create(['name' => 'Ayu']);

        Livewire::test(CreateEvaluation::class)
            ->fillForm([
                'employee_id' => $employee->id,
                'period' => now('Asia/Jakarta')->format('Y-m'),
                'scores' => $this->scoreRows($criteria, [85, 90, 77]),
                'notes' => 'Konsisten.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $evaluation = Evaluation::firstWhere('employee_id', $employee->id);
        $this->assertSame(83.3, $evaluation->total_score);
        $this->assertSame($owner->id, $evaluation->evaluated_by);
        $this->assertSame(3, $evaluation->scores()->count());
        $this->assertTrue($evaluation->employee->is($employee));
        $this->assertTrue($evaluation->evaluator->is($owner));
    }

    public function test_editing_scores_recomputes_total(): void
    {
        $this->actingAsOwner();
        $criteria = $this->makeCriteria();
        $employee = Employee::create(['name' => 'Ayu']);

        Livewire::test(CreateEvaluation::class)
            ->fillForm([
                'employee_id' => $employee->id,
                'period' => now('Asia/Jakarta')->format('Y-m'),
                'scores' => $this->scoreRows($criteria, [100, 100, 100]),
            ])
            ->call('create');

        $evaluation = Evaluation::firstWhere('employee_id', $employee->id);
        $this->assertSame(100.0, $evaluation->total_score);

        Livewire::test(EditEvaluation::class, ['record' => $evaluation->getKey()])
            ->fillForm(['scores' => $this->scoreRows($criteria, [50, 50, 50])])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(50.0, $evaluation->fresh()->total_score);
    }

    public function test_score_outside_0_to_100_is_rejected(): void
    {
        $this->actingAsOwner();
        $criteria = $this->makeCriteria();
        $employee = Employee::create(['name' => 'Ayu']);

        Livewire::test(CreateEvaluation::class)
            ->fillForm([
                'employee_id' => $employee->id,
                'period' => now('Asia/Jakarta')->format('Y-m'),
                'scores' => $this->scoreRows($criteria, [101, 90, 77]),
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertSame(0, Evaluation::count());
    }

    public function test_second_evaluation_for_same_employee_and_month_is_rejected(): void
    {
        $this->actingAsOwner();
        $criteria = $this->makeCriteria();
        $employee = Employee::create(['name' => 'Ayu']);
        $period = now('Asia/Jakarta')->format('Y-m');
        Evaluation::create(['employee_id' => $employee->id, 'period' => $period, 'total_score' => 70]);

        Livewire::test(CreateEvaluation::class)
            ->fillForm([
                'employee_id' => $employee->id,
                'period' => $period,
                'scores' => $this->scoreRows($criteria, [80, 80, 80]),
            ])
            ->call('create')
            ->assertHasFormErrors(['period' => 'unique']);

        $other = Employee::create(['name' => 'Bima']);
        Livewire::test(CreateEvaluation::class)
            ->fillForm([
                'employee_id' => $other->id,
                'period' => $period,
                'scores' => $this->scoreRows($criteria, [80, 80, 80]),
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_database_enforces_unique_employee_and_period(): void
    {
        $employee = Employee::create(['name' => 'Ayu']);
        Evaluation::create(['employee_id' => $employee->id, 'period' => '2026-09', 'total_score' => 70]);

        $this->expectException(QueryException::class);
        Evaluation::create(['employee_id' => $employee->id, 'period' => '2026-09', 'total_score' => 60]);
    }

    public function test_attendance_criterion_is_prefilled_from_the_score_source(): void
    {
        $attendance = EvaluationCriteria::create(['name' => 'Kehadiran', 'weight' => 40, 'is_attendance' => true]);
        $manual = EvaluationCriteria::create(['name' => 'Pelayanan', 'weight' => 60]);
        $employee = Employee::create(['name' => 'Ayu']);

        $this->app->bind(AttendanceScoreSource::class, fn () => new class implements AttendanceScoreSource
        {
            public function scoreFor(Employee $employee, CarbonInterface $month): ?int
            {
                return $month->format('Y-m-d') === '2026-09-01' ? 92 : null;
            }
        });

        $rows = collect(EvaluationForm::scoreRows($employee->id, '2026-09'))->keyBy('criteria_id');
        $this->assertSame(92, $rows[$attendance->id]['score']);
        $this->assertNull($rows[$manual->id]['score']);

        $emptyMonth = collect(EvaluationForm::scoreRows($employee->id, '2026-08'))->keyBy('criteria_id');
        $this->assertNull($emptyMonth[$attendance->id]['score']);
    }

    public function test_null_source_leaves_the_score_for_the_owner_to_fill(): void
    {
        $criteria = EvaluationCriteria::create(['name' => 'Kehadiran', 'weight' => 100, 'is_attendance' => true]);
        $employee = Employee::create(['name' => 'Ayu']);

        $this->app->bind(AttendanceScoreSource::class, NullAttendanceScoreSource::class);

        $row = EvaluationForm::scoreRows($employee->id, '2026-09')[0];
        $this->assertNull($row['score']);
        $this->assertSame($criteria->id, $row['criteria_id']);
    }

    public function test_only_owner_can_access_evaluations(): void
    {
        foreach ([User::ROLE_FRONT_OFFICE, User::ROLE_CASHIER] as $role) {
            $user = $this->userWithRole($role);

            $this->assertFalse($user->can('viewAny', Evaluation::class));
            $this->assertFalse($user->can('create', Evaluation::class));

            $this->actingAs($user);
            Livewire::test(ListEvaluations::class)->assertForbidden();
        }

        $this->actingAsOwner();
        Livewire::test(ListEvaluations::class)->assertSuccessful();
    }
}
