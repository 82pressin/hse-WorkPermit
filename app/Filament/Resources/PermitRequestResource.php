<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermitRequestResource\Pages;
use App\Models\PermitRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PermitRequestResource extends Resource
{
    protected static ?string $model = PermitRequest::class;
    protected static ?string $modelLabel = 'Permohonan Izin Kerja';
    protected static ?string $pluralModelLabel = 'Permohonan Izin Kerja';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    // Siapa yang boleh melihat tombol "Buat"
    public static function canCreate(): bool
    {
        return in_array(auth()->user()->role, ['pemohon', 'admin']);
    }

    // Siapa boleh melihat data apa
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->role === 'pemohon') {
            $query->where('department', $user->department);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        $jobTypes = [
            'is_hot_work' => 'Panas',
            'is_work_at_height' => 'Di ketinggian (> 1,8 m)',
            'is_confined_space' => 'Ruang terbatas',
            'is_excavation' => 'Penggalian',
            'is_chemical' => 'Pekerjaan kimia',
            'is_electrical' => 'Elektrikal',
            'is_mechanical' => 'Mekanikal',
        ];

        $checkboxes = collect($jobTypes)
            ->map(fn ($label, $field) => Forms\Components\Checkbox::make($field)->label($label)->live())
            ->values()
            ->all();

        return $form->schema([
            Forms\Components\Section::make('Data Pekerjaan')
                ->schema([
                    Forms\Components\TextInput::make('vendor_name')->label('Nama Perusahaan (Vendor)')->required(),
                    Forms\Components\TextInput::make('location')->label('Lokasi')->required(),
                    Forms\Components\Textarea::make('job_description')->label('Uraian Pekerjaan')->required()->columnSpanFull(),
                    Forms\Components\DatePicker::make('start_date')->label('Tanggal Mulai')->required(),
                    Forms\Components\DatePicker::make('end_date')->label('Tanggal Selesai')->required()->afterOrEqual('start_date'),
                    Forms\Components\TimePicker::make('start_time')->label('Jam Mulai')->required(),
                    Forms\Components\TimePicker::make('end_time')->label('Jam Selesai')->required(),
                    Forms\Components\TextInput::make('personnel_count')->label('Jumlah Personil')->numeric()->minValue(1)->required(),
                ])->columns(2),

            Forms\Components\Section::make('Jenis Pekerjaan')
                ->schema([
                    ...$checkboxes,
                    Forms\Components\Placeholder::make('risk_preview')
                        ->label('Perkiraan tingkat risiko')
                        ->columnSpanFull()
                        ->content(function (Get $get) {
                            $score = 0;
                            foreach (PermitRequest::RISK_WEIGHTS as $field => $weight) {
                                if ($get($field)) {
                                    $score += $weight;
                                }
                            }
                            return $score . ' poin: ' . strtoupper(PermitRequest::levelFor($score));
                        }),
                ])->columns(4),

            Forms\Components\Section::make('Dokumen dari Vendor')
                ->schema([
                    Forms\Components\Repeater::make('documents')
                        ->relationship()
                        ->label('Dokumen')
                        ->addActionLabel('Tambah dokumen')
                        ->schema([
                            Forms\Components\Select::make('type')->label('Jenis dokumen')->required()->options([
                                'surat_permohonan' => 'Surat permohonan',
                                'jsea' => 'JSEA (sudah ditandatangani PIC kontraktor)',
                                'daftar_personel' => 'Daftar personel',
                                'daftar_alat' => 'Daftar alat kerja',
                                'dokumen_k3' => 'Dokumen K3 personel',
                                'lainnya' => 'Lainnya',
                            ]),
                            Forms\Components\FileUpload::make('file_path')
                                ->label('File')
                                ->required()
                                ->disk('public')
                                ->directory('permit-documents')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                ->maxSize(10240)
                                ->storeFileNamesIn('original_name'),
                        ])->columns(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('permit_number')->label('No. Izin')->placeholder('-')->searchable(),
                Tables\Columns\TextColumn::make('vendor_name')->label('Vendor')->searchable(),
                Tables\Columns\TextColumn::make('department')->label('Dept'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('risk_level')->label('Risiko')->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'tinggi' => 'danger',
                        'sedang' => 'warning',
                        'rendah' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('start_date')->label('Mulai')->date('d M Y'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermitRequests::route('/'),
            'create' => Pages\CreatePermitRequest::route('/create'),
            'edit' => Pages\EditPermitRequest::route('/{record}/edit'),
        ];
    }
}