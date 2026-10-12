<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\SettingResource;
use App\Models\Setting;
use App\Services\SettingRepository;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

class ManageSettings extends Page
{
    protected static string $resource = SettingResource::class;

    protected static ?string $title = 'Pengaturan Operasional';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(SettingRepository::class)->formValues());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tarif dan jam menginap')
                ->description('Pajak dipakai pada setiap perhitungan harga booking dan pesanan makanan. Jam check-in dan check-out tampil sebagai informasi untuk tamu.')
                ->columns(3)
                ->schema([
                    TextInput::make('tax_rate_percent')
                        ->label('Pajak dan biaya layanan')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.01)
                        ->suffix('%')
                        ->required(),
                    TimePicker::make('check_in_time')
                        ->label('Jam check-in standar')
                        ->seconds(false)
                        ->format('H:i')
                        ->required(),
                    TimePicker::make('check_out_time')
                        ->label('Jam check-out standar')
                        ->seconds(false)
                        ->format('H:i')
                        ->required(),
                ]),
            Section::make('Uang muka (DP)')
                ->description('Isi 1 sampai 99 untuk memberi tamu pilihan bayar DP di halaman pembayaran. Isi 0 untuk mewajibkan bayar penuh. Sisa tagihan dilunasi paling lambat saat check-in.')
                ->schema([
                    TextInput::make('down_payment_percent')
                        ->label('Persentase DP')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%')
                        ->required(),
                ]),
            Section::make('Rekening transfer')
                ->description('Rekening tujuan transfer yang tampil di halaman pembayaran dan email tamu. Selama salah satu kosong, tamu tidak bisa mengunggah bukti transfer.')
                ->columns(3)
                ->schema([
                    TextInput::make('bank_name')
                        ->label('Nama bank')
                        ->placeholder('Contoh: BCA')
                        ->maxLength(50),
                    TextInput::make('bank_account_number')
                        ->label('Nomor rekening')
                        ->regex('/^[0-9 .-]+$/')
                        ->validationMessages(['regex' => 'Nomor rekening hanya boleh berisi angka, spasi, titik, atau tanda hubung.'])
                        ->maxLength(30),
                    TextInput::make('bank_account_holder')
                        ->label('Atas nama')
                        ->maxLength(100),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Simpan pengaturan')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        Gate::authorize('update', Setting::class);

        app(SettingRepository::class)->saveFormValues($this->form->getState());

        Notification::make()->title('Pengaturan disimpan')->success()->send();
    }
}
