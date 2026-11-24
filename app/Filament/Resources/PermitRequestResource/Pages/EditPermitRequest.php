<?php

namespace App\Filament\Resources\PermitRequestResource\Pages;

use App\Filament\Resources\PermitRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPermitRequest extends EditRecord
{
    protected static string $resource = PermitRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
