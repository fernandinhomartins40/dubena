<?php

namespace App\Filament\Resources\CidadeResource\Pages;

use App\Filament\Resources\CidadeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCidade extends CreateRecord
{
    protected static string $resource = CidadeResource::class;

    /** Preenche grupo_id com o grupo do usuário logado (escopo do legado). */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['grupo_id'] = optional(optional(auth()->user())->empresa)->grupo_id;

        return $data;
    }
}
