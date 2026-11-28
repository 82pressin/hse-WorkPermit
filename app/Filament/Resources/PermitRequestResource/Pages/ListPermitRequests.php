<?php

namespace App\Filament\Resources\PermitRequestResource\Pages;

use App\Filament\Resources\PermitRequestResource;
use App\Models\PermitRequest;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;

use Illuminate\Database\Eloquent\Builder;

class ListPermitRequests extends ListRecords
{
    protected static string $resource = PermitRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $role = auth()->user()->role;
        $active = PermitRequest::ACTIVE_STATUSES[$role] ?? null;

        if ($active === null) {
            return [];   // admin dan pemohon: tanpa tab
        }

        $tabs = [
            'aktif' => Tab::make('Perlu tindakan saya')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $active)),
        ];

        $tabs += in_array($role, PermitRequest::RESTRICTED_ROLES)
            ? ['riwayat' => Tab::make('Riwayat')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', $active))]
            : ['semua' => Tab::make('Semua')];

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'aktif';
    }
}
