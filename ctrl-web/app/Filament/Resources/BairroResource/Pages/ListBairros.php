<?php

namespace App\Filament\Resources\BairroResource\Pages;

use App\Filament\Resources\BairroResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBairros extends ListRecords
{
    protected static string $resource = BairroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
