<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Menu;
use App\Empresa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * FASE 4 Bloco A (D11) — gestão das permissões do usuário (menuusers) na UI.
 *
 * Cada linha = uma permissão (user × empresa × menu) com as flags do legado
 * (visualizar/criar/editar/deletar/baixar/alerta). É a mesma tabela que o
 * AuthorizeCustom consulta em runtime — editar aqui reflete na autorização sem
 * tocar no motor legado (criarPermissoes). Mostra só menus-FOLHA (descricao
 * não nula = nome de rota); os menus-pai (descricao null) são concedidos
 * automaticamente pelo legado e não precisam ser geridos manualmente.
 */
class MenuuserRelationManager extends RelationManager
{
    protected static string $relationship = 'menuuser';

    protected static ?string $title = 'Permissões';

    protected static ?string $modelLabel = 'permissão';

    protected static ?string $pluralModelLabel = 'permissões';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('menu_id')
                    ->label('Tela / menu')
                    ->options(fn () => Menu::whereNotNull('descricao')
                        ->orderBy('titulo')
                        ->pluck('titulo', 'id'))
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('empresa_id')
                    ->label('Empresa')
                    ->options(fn () => Empresa::orderBy('razao_social')->pluck('razao_social', 'id'))
                    ->searchable()
                    ->required(),
                Forms\Components\Fieldset::make('Ações permitidas')
                    ->schema([
                        Forms\Components\Toggle::make('visualizar')->label('Visualizar'),
                        Forms\Components\Toggle::make('criar')->label('Criar'),
                        Forms\Components\Toggle::make('editar')->label('Editar'),
                        Forms\Components\Toggle::make('deletar')->label('Deletar'),
                        Forms\Components\Toggle::make('baixar')->label('Baixar'),
                        Forms\Components\Toggle::make('alerta')->label('Alerta'),
                    ])
                    ->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('menu_id')
            ->columns([
                Tables\Columns\TextColumn::make('menu.titulo')
                    ->label('Tela')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('menu.descricao')
                    ->label('Rota')
                    ->toggleable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('empresa.razao_social')
                    ->label('Empresa')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('visualizar')->label('Ver')->boolean(),
                Tables\Columns\IconColumn::make('criar')->label('Criar')->boolean(),
                Tables\Columns\IconColumn::make('editar')->label('Editar')->boolean(),
                Tables\Columns\IconColumn::make('deletar')->label('Excluir')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('empresa_id')
                    ->label('Empresa')
                    ->options(fn () => Empresa::orderBy('razao_social')->pluck('razao_social', 'id')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Conceder permissão'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
