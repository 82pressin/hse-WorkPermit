<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermitRequestResource\Pages;
use App\Models\PermitRequest;
use App\Services\PermitWorkflow;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return match ($user->role) {
            'admin' => true,
            'pemohon' => $record->status === 'perlu_revisi' && $record->department === $user->department,
            default => false,
        };
    }

    protected static function runWorkflow(\Closure $callback, string $successMessage): void
    {
        try {
            $callback();
            Notification::make()->title($successMessage)->success()->send();
        } catch (\DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    // Siapa boleh melihat data apa
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->role === 'pemohon') {
            $query->where('department', $user->department);
        } elseif (in_array($user->role, PermitRequest::RESTRICTED_ROLES)) {
            $active = PermitRequest::ACTIVE_STATUSES[$user->role];

            $query->where(function ($q) use ($active, $user) {
                $q->whereIn('status', $active)
                ->orWhereHas('histories.user', fn ($u) => $u->where('role', $user->role));
            });
        }
        // admin dan HSE: tanpa filter, melihat semuanya

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
                    Forms\Components\TimePicker::make('start_time')->label('Jam Mulai')->required()->seconds(false),
                    Forms\Components\TimePicker::make('end_time')->label('Jam Selesai')->required()->seconds(false),
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
                                ->storeFileNamesIn('original_name')
                                ->downloadable()
                                ->openable()
                        ])->columns(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $isHse = fn () => in_array(auth()->user()->role, ['foreman_hse', 'spv_hse']);

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('permit_number')->label('No. Izin')->placeholder('-')->searchable(),

                Tables\Columns\TextColumn::make('vendor_name')->label('Vendor')->searchable(),

                Tables\Columns\TextColumn::make('department')->label('Dept'),
                
                Tables\Columns\TextColumn::make('requester.name')->label('Pemohon')->searchable(),

                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => static::isMaskedFor($state)
                        ? 'Sudah diproses'
                        : (PermitRequest::STATUSES[$state] ?? $state))
                    ->color(fn (string $state) => static::isMaskedFor($state) ? 'gray' : match ($state) {
                        'perlu_revisi', 'ditolak' => 'danger',
                        'review_hse' => 'warning',
                        'diterbitkan', 'selesai' => 'success',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('jsea_progress')->label('TTD JSEA')
                    ->visible(fn () => in_array(auth()->user()->role, ['foreman_hse', 'spv_hse', 'admin']))
                    ->getStateUsing(function (PermitRequest $r) {
                        if (in_array($r->status, ['review_hse', 'perlu_revisi'])) {
                            return null;
                        }
                        $labels = ['spv_hse' => 'Spv HSE', 'ka_dept_ga' => 'Ka.Dept GA'];

                        return collect(PermitRequest::JSEA_APPROVER_ROLES)
                            ->map(fn ($role) => $labels[$role] . ' ' . ($r->hasActiveApprovalFrom('jsea', $role) ? '✓' : '–'))
                            ->implode(' · ');
                    })
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('risk_level')->label('Risiko')->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'tinggi' => 'danger',
                        'sedang' => 'warning',
                        'rendah' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('revision_note')->label('Catatan revisi')
                    ->getStateUsing(fn (PermitRequest $r) => $r->status === 'perlu_revisi' ? $r->latestRevisionNote() : null)
                    ->placeholder('-')->wrap(),

                Tables\Columns\TextColumn::make('start_date')->label('Mulai')->date('d M Y'),
                Tables\Columns\TextColumn::make('end_date')->label('Selesai')->date('d M Y'),])
                    ->defaultSort('created_at', 'desc')
                    ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('approve_review')
                    ->label('Lolos Review')->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PermitRequest $r) => $isHse() && $r->status === 'review_hse')
                    ->action(fn (PermitRequest $r) => static::runWorkflow(
                        fn () => app(PermitWorkflow::class)->approveReview($r, auth()->user()),
                        'Review disetujui, lanjut ke JSEA'
                    )),

                Tables\Actions\Action::make('return_revision')
                    ->label('Kembalikan untuk Revisi')->icon('heroicon-o-arrow-uturn-left')->color('danger')
                    ->form([Forms\Components\Textarea::make('note')->label('Catatan revisi')->required()])
                    ->visible(fn (PermitRequest $r) => $isHse() && $r->status === 'review_hse')
                    ->action(fn (PermitRequest $r, array $data) => static::runWorkflow(
                        fn () => app(PermitWorkflow::class)->returnForRevision($r, auth()->user(), $data['note']),
                        'Permohonan dikembalikan ke pemohon'
                    )),
                    
                Tables\Actions\Action::make('approve_jsea')
                    ->label('Setujui JSEA')->icon('heroicon-o-pencil-square')->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PermitRequest $r) => in_array(auth()->user()->role, PermitRequest::JSEA_APPROVER_ROLES)
                        && $r->status === 'menunggu_jsea'
                        && ! $r->hasActiveApprovalFrom('jsea', auth()->user()->role))
                    ->action(fn (PermitRequest $r) => static::runWorkflow(
                        fn () => app(PermitWorkflow::class)->approveJsea($r, auth()->user()),
                        'JSEA disetujui'
                    )),

                Tables\Actions\Action::make('return_jsea')
                    ->label('Kembalikan JSEA')->icon('heroicon-o-arrow-uturn-left')->color('danger')
                    ->form([Forms\Components\Textarea::make('note')->label('Catatan revisi')->required()])
                    ->visible(fn (PermitRequest $r) => in_array(auth()->user()->role, PermitRequest::JSEA_APPROVER_ROLES)
                        && $r->status === 'menunggu_jsea'
                        && ! $r->hasActiveApprovalFrom('jsea', auth()->user()->role))
                    ->action(fn (PermitRequest $r, array $data) => static::runWorkflow(
                        fn () => app(PermitWorkflow::class)->returnFromJsea($r, auth()->user(), $data['note']),
                        'JSEA dikembalikan ke pemohon'
                    )),

                Tables\Actions\Action::make('resubmit')
                    ->label('Ajukan Ulang')->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->visible(fn (PermitRequest $r) => auth()->user()->role === 'pemohon' && $r->status === 'perlu_revisi')
                    ->action(fn (PermitRequest $r) => static::runWorkflow(
                        fn () => app(PermitWorkflow::class)->resubmit($r, auth()->user()),
                        'Permohonan diajukan ulang ke HSE'
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermitRequests::route('/'),
            'create' => Pages\CreatePermitRequest::route('/create'),
            'edit' => Pages\EditPermitRequest::route('/{record}/edit'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->role === 'admin';
    }

    protected static function isMaskedFor(string $state): bool
    {
        $role = auth()->user()->role;

        return in_array($role, PermitRequest::RESTRICTED_ROLES)
            && ! in_array($state, PermitRequest::ACTIVE_STATUSES[$role]);
    }
}