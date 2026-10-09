<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Services\AttendanceSyncService;
use App\Support\AttendanceSyncReport;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->importAction(),
            CreateAction::make()->label('Catat manual'),
        ];
    }

    private function importAction(): Action
    {
        $upload = config('attendance.upload');

        return Action::make('importLog')
            ->label('Impor log fingerprint')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->visible(fn () => auth()->user()?->can('create_attendance') ?? false)
            ->modalHeading('Impor log fingerprint')
            ->modalDescription('Unggah file CSV (kolom fingerprint_id dan datetime) atau attlog.dat dari mesin. Catatan manual tidak ditimpa.')
            ->modalSubmitActionLabel('Impor sekarang')
            ->schema([
                FileUpload::make('file')
                    ->label('File log')
                    ->disk($upload['disk'])
                    ->directory($upload['directory'])
                    ->visibility('private')
                    ->maxSize($upload['max_kilobytes'])
                    ->rules(['extensions:csv,txt,dat'])
                    ->helperText('Format csv, txt, atau dat. Maksimal 5 MB.')
                    ->required(),
                DatePicker::make('from')
                    ->label('Dari tanggal')
                    ->native(false)
                    ->default(now()->startOfMonth())
                    ->required(),
                DatePicker::make('until')
                    ->label('Sampai tanggal')
                    ->native(false)
                    ->default(now())
                    ->afterOrEqual('from')
                    ->required(),
            ])
            ->action(fn (array $data) => $this->runImport($data));
    }

    /**
     * Runs inline (not queued) because the owner waits for the summary. The scheduled path uses the job.
     *
     * @param  array{file: string, from: string, until: string}  $data
     */
    private function runImport(array $data): void
    {
        $timezone = config('app.timezone');
        $from = CarbonImmutable::parse($data['from'], $timezone)->startOfDay();
        $until = CarbonImmutable::parse($data['until'], $timezone)->endOfDay();

        try {
            $report = app(AttendanceSyncService::class)->importFile($data['file'], $from, $until);
        } catch (Throwable $e) {
            Log::error('Manual fingerprint import failed.', ['error' => $e->getMessage()]);
            Notification::make()->title('Impor gagal')->body('File tidak bisa dibaca. Periksa formatnya lalu coba lagi.')->danger()->send();

            return;
        } finally {
            Storage::disk(config('attendance.upload.disk'))->delete($data['file']);
        }

        $this->notifyReport($report);
    }

    private function notifyReport(AttendanceSyncReport $report): void
    {
        $lines = [
            "Baris dibaca: {$report->rowsRead}",
            "Dibuat: {$report->created}",
            "Diperbarui: {$report->updated}",
            "Dilewati: {$report->skipped()} (baris rusak {$report->malformedLines}, catatan manual {$report->protectedDays})",
            "ID fingerprint tidak dikenal: {$report->unknownIdCount()}",
        ];

        if ($report->unknownIdCount() > 0) {
            $lines[] = 'Daftar ID: '.implode(', ', array_keys($report->unknownFingerprintIds));
        }
        if ($report->employeesWithoutShift !== []) {
            $lines[] = 'Karyawan tanpa shift (tanpa penilaian terlambat): '.implode(', ', $report->employeesWithoutShift);
        }

        $needsAttention = $report->unknownIdCount() > 0 || $report->malformedLines > 0;

        Notification::make()
            ->title('Impor selesai')
            ->body(implode("\n", $lines))
            ->color($needsAttention ? 'warning' : 'success')
            ->persistent()
            ->send();
    }
}
