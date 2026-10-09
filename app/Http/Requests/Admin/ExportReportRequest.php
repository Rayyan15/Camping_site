<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReportPreset;
use App\Services\Reports\InvalidReportPeriodException;
use App\Services\Reports\ReportPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->is_active && $user->can('view_reports');
    }

    public function rules(): array
    {
        return [
            'periode' => ['required', Rule::enum(ReportPreset::class)],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @throws InvalidReportPeriodException
     */
    public function period(): ReportPeriod
    {
        return ReportPeriod::fromPreset(
            ReportPreset::from($this->validated('periode')),
            $this->validated('dari'),
            $this->validated('sampai'),
        );
    }
}
