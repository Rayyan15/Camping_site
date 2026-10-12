<?php

namespace App\Filament\Resources\Payments\Actions;

use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Services\Payment\TransferProofService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Confirm and reject actions for guest transfer proofs, shared by the payment screens and the booking
 * screens. The rules live in TransferProofService; this class only wires the UI to it.
 */
class TransferProofActions
{
    private const REJECTION_MAX_LENGTH = 300;

    /**
     * @param  Closure(mixed): ?Payment  $resolvePayment  turns the action's record into the proof to review
     */
    public static function confirm(Closure $resolvePayment, string $ability, string $name = 'confirm_transfer'): Action
    {
        return Action::make($name)
            ->label('Konfirmasi transfer')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->authorize($ability)
            ->visible(fn ($record): bool => $resolvePayment($record) !== null)
            ->requiresConfirmation()
            ->modalHeading('Konfirmasi transfer')
            ->modalDescription(fn ($record): string => self::confirmationText($resolvePayment($record)))
            ->modalSubmitActionLabel('Ya, sudah masuk')
            ->action(function ($record, Component $livewire) use ($resolvePayment): void {
                $payment = $resolvePayment($record);

                self::run(
                    fn () => app(TransferProofService::class)->confirm($payment, (int) Auth::id()),
                    'Transfer dikonfirmasi',
                    $livewire,
                );
            });
    }

    /**
     * @param  Closure(mixed): ?Payment  $resolvePayment
     */
    public static function reject(Closure $resolvePayment, string $ability, string $name = 'reject_proof'): Action
    {
        return Action::make($name)
            ->label('Tolak bukti')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->authorize($ability)
            ->visible(fn ($record): bool => $resolvePayment($record) !== null)
            ->modalHeading('Tolak bukti transfer')
            ->modalDescription('Tamu menerima alasan ini lewat email dan di halaman status, lalu bisa mengunggah ulang.')
            ->modalSubmitActionLabel('Tolak bukti')
            ->schema([
                Textarea::make('reason')
                    ->label('Alasan penolakan')
                    ->rows(3)
                    ->maxLength(self::REJECTION_MAX_LENGTH)
                    ->required(),
            ])
            ->action(function (array $data, $record, Component $livewire) use ($resolvePayment): void {
                $payment = $resolvePayment($record);

                self::run(
                    fn () => app(TransferProofService::class)->reject($payment, trim($data['reason']), (int) Auth::id()),
                    'Bukti ditolak',
                    $livewire,
                );
            });
    }

    private static function confirmationText(?Payment $payment): string
    {
        $amount = number_format($payment->amount ?? 0, 0, ',', '.');

        return "Pastikan Rp {$amount} sudah masuk di mutasi rekening sebelum mengonfirmasi.";
    }

    private static function run(Closure $work, string $successTitle, Component $livewire): void
    {
        try {
            $work();
        } catch (PaymentException $e) {
            report($e);
            Notification::make()->title('Gagal memproses bukti')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($successTitle)->success()->send();

        if (method_exists($livewire, 'refreshFormData')) {
            $livewire->refreshFormData(['status', 'paid_amount']);
        }
    }
}
