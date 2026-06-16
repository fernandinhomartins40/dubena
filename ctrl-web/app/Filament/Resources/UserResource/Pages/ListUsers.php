<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        // Criação de usuário segue no fluxo legado (permissões + oauth client);
        // esta tela cobre listagem/edição básica. Sem CreateAction por ora.
        return [];
    }
}
