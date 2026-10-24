<?php

namespace Tests\Feature;

use App\Models\EvaluationCriteria;
use App\Models\RefundPolicy;
use App\Services\SettingRepository;
use Database\Seeders\EvaluationCriteriaSeeder;
use Database\Seeders\RefundPolicySeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAnswersSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_seeder_has_two_tiers_and_retires_the_old_half_refund(): void
    {
        RefundPolicy::create(['min_days_before' => 3, 'percent' => 50]);

        $this->seed(RefundPolicySeeder::class);

        $this->assertSame(
            [[7, 100], [0, 0]],
            RefundPolicy::orderByDesc('min_days_before')->get()->map(fn ($t) => [$t->min_days_before, $t->percent])->all(),
        );
    }

    public function test_settings_seeder_means_no_down_payment_and_fixed_hours(): void
    {
        $this->seed(SettingsSeeder::class);
        $settings = app(SettingRepository::class);

        $this->assertSame(0, $settings->downPaymentPercent());
        $this->assertSame('14:00', $settings->checkInTime());
        $this->assertSame('12:00', $settings->checkOutTime());
    }

    public function test_criteria_seeder_gives_three_criteria_totalling_one_hundred(): void
    {
        $this->seed(EvaluationCriteriaSeeder::class);

        $this->assertSame(['Kehadiran', 'Kerapihan', 'Kesopanan'], EvaluationCriteria::orderBy('id')->pluck('name')->all());
        $this->assertSame(EvaluationCriteria::TOTAL_WEIGHT, EvaluationCriteria::activeWeightTotal());
    }

    public function test_landing_shows_hours_and_tiktok_link(): void
    {
        config(['site.tiktok_url' => 'https://www.tiktok.com/@halimun.highland7']);
        $this->seed(SettingsSeeder::class);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('pukul 14.00')
            ->assertSee('pukul 12.00')
            ->assertSee('https://www.tiktok.com/@halimun.highland7', false);
    }
}
