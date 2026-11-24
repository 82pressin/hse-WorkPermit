<?php

namespace App\Filament\Resources\PermitRequestResource\Pages;

use App\Filament\Resources\PermitRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePermitRequest extends CreateRecord
{
    protected static string $resource = PermitRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
    $data['requester_id'] = auth()->id();
    $data['department'] = auth()->user()->department ?? '-';

    return $data;
    }
}
