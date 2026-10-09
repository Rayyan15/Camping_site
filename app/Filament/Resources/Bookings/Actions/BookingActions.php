<?php

namespace App\Filament\Resources\Bookings\Actions;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BookingReviewException;
use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\PaymentException;
use App\Exceptions\RefundException;
use App\Models\Booking;
use App\Services\BookingReviewService;
use App\Services\BookingStatusTransition;
use App\Services\Payment\PaymentService;
use App\Services\RefundService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Row and header actions shared by the booking table and the edit page.
 * Rules live in BookingStatusTransition and PaymentService; this class only wires the UI to them.
 */
class BookingActions
{
    private const PROOF_DISK = 'local';

    private const PROOF_DIRECTORY = 'payment-proofs';

    private const PROOF_MAX_KILOBYTES = 2048;

    private const PROOF_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    private const REVIEW_MOVE = 'move';

    private const REVIEW_REFUND = 'refund';

    private const PAYABLE_STATUSES = [BookingStatus::PendingPayment, BookingStatus::Paid, BookingStatus::CheckedIn];

    public static function remainingBalance(Booking $booking): int
    {
        return max(0, $booking->total - $booking->paid_amount);
    }

    public static function checkIn(): Action
    {
        return self::statusAction('check_in', 'Check-in', 'heroicon-o-arrow-right-end-on-rectangle', BookingStatus::CheckedIn, 'checkIn')
            ->modalHeading('Check-in tamu')
            ->modalDescription(fn (Booking $record): string => "Konfirmasi tamu booking {$record->code} sudah tiba dan menempati unit.");
    }

    public static function checkOut(): Action
    {
        return self::statusAction('check_out', 'Check-out', 'heroicon-o-arrow-left-start-on-rectangle', BookingStatus::CheckedOut, 'checkOut')
            ->modalHeading('Check-out tamu')
            ->modalDescription(fn (Booking $record): string => "Konfirmasi tamu booking {$record->code} sudah meninggalkan unit. Status tidak bisa dikembalikan.");
    }

    public static function recordPayment(): Action
    {
        return Action::make('record_payment')
            ->label('Catat pembayaran')
            ->icon('heroicon-o-banknotes')
            ->color('gray')
            ->authorize('recordPayment')
            ->visible(fn (Booking $record): bool => in_array($record->status, self::PAYABLE_STATUSES, true)
                && self::remainingBalance($record) > 0)
            ->modalHeading('Catat pembayaran di luar gateway')
            ->modalSubmitActionLabel('Simpan pembayaran')
            ->fillForm(fn (Booking $record): array => ['amount' => self::remainingBalance($record)])
            ->schema(fn (Booking $record): array => [
                Select::make('method')
                    ->label('Metode')
                    ->options([
                        PaymentMethod::Cash->value => 'Tunai',
                        PaymentMethod::Transfer->value => 'Transfer bank',
                        PaymentMethod::Manual->value => 'Lainnya',
                    ])
                    ->required()
                    ->live(),
                TextInput::make('amount')
                    ->label('Nominal')
                    ->prefix('Rp')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(self::remainingBalance($record))
                    ->helperText('Sisa tagihan Rp '.number_format(self::remainingBalance($record), 0, ',', '.'))
                    ->required(),
                FileUpload::make('proof_path')
                    ->label('Bukti pembayaran')
                    ->helperText('Gambar JPG, PNG, WebP atau PDF, maksimal 2 MB. Wajib untuk transfer bank.')
                    ->disk(self::PROOF_DISK)
                    ->directory(self::PROOF_DIRECTORY)
                    ->visibility('private')
                    ->acceptedFileTypes(self::PROOF_MIME_TYPES)
                    ->maxSize(self::PROOF_MAX_KILOBYTES)
                    ->getUploadedFileNameForStorageUsing(
                        fn ($file): string => Str::random(40).'.'.$file->guessExtension(),
                    )
                    ->required(fn ($get): bool => $get('method') === PaymentMethod::Transfer->value),
                Textarea::make('note')
                    ->label('Catatan')
                    ->rows(2)
                    ->maxLength(500),
            ])
            ->action(function (array $data, Booking $record, Component $livewire): void {
                try {
                    app(PaymentService::class)->recordManual(
                        $record,
                        (int) $data['amount'],
                        $data['method'],
                        $data['proof_path'] ?? null,
                        Auth::id(),
                    );
                } catch (PaymentException|InvalidArgumentException $e) {
                    report($e);
                    Notification::make()->title('Pembayaran tidak tersimpan')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Pembayaran tercatat')->success()->send();
                self::refreshPage($livewire);
            });
    }

    public static function resolveReview(): Action
    {
        return Action::make('resolve_review')
            ->label('Selesaikan review')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('warning')
            ->authorize('resolveReview')
            ->visible(fn (Booking $record): bool => $record->status === BookingStatus::NeedsReview)
            ->modalHeading('Selesaikan review booking')
            ->modalDescription(fn (Booking $record): string => "Booking {$record->code} sudah dibayar Rp ".number_format($record->paid_amount, 0, ',', '.').' tetapi unitnya sudah diambil tamu lain. Pilih satu hasil.')
            ->modalSubmitActionLabel('Selesaikan')
            ->schema([
                Radio::make('outcome')
                    ->label('Hasil')
                    ->options([
                        self::REVIEW_MOVE => 'Pindahkan ke unit lain bertipe sama yang kosong',
                        self::REVIEW_REFUND => 'Batalkan dan kembalikan seluruh pembayaran',
                    ])
                    ->descriptions([
                        self::REVIEW_MOVE => 'Booking menjadi Lunas. Ditolak bila tidak ada unit kosong pada tanggal menginap.',
                        self::REVIEW_REFUND => 'Refund penuh tanpa tabel kebijakan, karena kesalahan ada di pihak kami.',
                    ])
                    ->required()
                    ->live(),
                Textarea::make('reason')
                    ->label('Alasan pembatalan')
                    ->helperText('Tersimpan pada refund dan riwayat aktivitas.')
                    ->rows(3)
                    ->maxLength(500)
                    ->visible(fn ($get): bool => $get('outcome') === self::REVIEW_REFUND)
                    ->required(fn ($get): bool => $get('outcome') === self::REVIEW_REFUND),
            ])
            ->action(function (array $data, Booking $record, Component $livewire): void {
                $actor = Auth::user();

                try {
                    if ($data['outcome'] === self::REVIEW_MOVE) {
                        app(BookingReviewService::class)->moveToFreeUnit($record, $actor);
                        $title = 'Booking dipindahkan ke unit lain dan berstatus Lunas';
                    } else {
                        app(RefundService::class)->cancelForPropertyFault($record, $data['reason'], $actor);
                        $title = 'Booking dibatalkan dan refund penuh disetujui';
                    }
                } catch (BookingReviewException|RefundException $e) {
                    report($e);
                    Notification::make()->title('Review belum selesai')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title($title)->success()->send();
                self::refreshPage($livewire);
            });
    }

    private static function statusAction(string $name, string $label, string $icon, BookingStatus $target, string $ability): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color('success')
            ->authorize($ability)
            ->visible(fn (Booking $record): bool => app(BookingStatusTransition::class)->canMove($record->status, $target))
            ->requiresConfirmation()
            ->action(function (Booking $record, Component $livewire) use ($target): void {
                try {
                    app(BookingStatusTransition::class)->apply($record, $target);
                } catch (InvalidBookingTransitionException $e) {
                    Notification::make()->title('Status tidak berubah')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Status booking diperbarui')->success()->send();
                self::refreshPage($livewire);
            });
    }

    private static function refreshPage(Component $livewire): void
    {
        if (method_exists($livewire, 'refreshFormData')) {
            $livewire->refreshFormData(['status', 'paid_amount']);
        }
    }
}
