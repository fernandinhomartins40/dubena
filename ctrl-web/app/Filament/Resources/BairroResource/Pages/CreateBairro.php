<?php

namespace App\Filament\Resources\BairroResource\Pages;

use App\Filament\Resources\BairroResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBairro extends CreateRecord
{
    protected static string $resource = BairroResource::class;

    /** Preenche grupo_id (NOT NULL) com o grupo do usuário logado. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['grupo_id'] = optional(optional(auth()->user())->empresa)->grupo_id;

        return $data;
    }
}
