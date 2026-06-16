<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BairroResource\Pages;
use App\Bairro;
use App\Cidade;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * FASE 3 — recurso-piloto do painel Filament (cadastro geográfico, baixo risco).
 * Coexiste com a tela legada bairro.* (AdminLTE). Permissões via BairroPolicy
 * (mapeia menuusers do menu 'bairro.index'). Model legado em App\Bairro.
 */
class BairroResource extends Resource
{
    protected static ?string $model = Bairro::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Cadastros';

    protected static ?string $modelLabel = 'Bairro';

    protected static ?string $pluralModelLabel = 'Bairros';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('descricao')
                    ->label('Descrição')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('cidade_id')
                    ->label('Cidade')
                    ->relationship('cidade', 'descricao')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Toggle::make('ativo')
                    ->label('Ativo')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('descricao')
                    ->label('Descrição')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cidade.descricao')
                    ->label('Cidade')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->defaultSort('descricao')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Escopo por grupo do usuário logado (mesma regra do legado). */
    public static function getEloquentQuery(): Builder
    {
        $grupoId = optional(optional(auth()->user())->empresa)->grupo_id;

        return parent::getEloquentQuery()
            ->when($grupoId, fn (Builder $q) => $q->where('grupo_id', $grupoId));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBairros::route('/'),
            'create' => Pages\CreateBairro::route('/create'),
            'edit' => Pages\EditBairro::route('/{record}/edit'),
        ];
    }
}
