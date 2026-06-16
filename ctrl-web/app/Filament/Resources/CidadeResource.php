<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CidadeResource\Pages;
use App\Cidade;
use App\Estado;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * FASE 3 — recurso-piloto do painel Filament (cadastro geográfico, baixo risco).
 * Coexiste com a tela legada cidade.* (AdminLTE). Permissões via CidadePolicy
 * (mapeia menuusers do menu 'cidade.index'). Model legado em App\Cidade.
 */
class CidadeResource extends Resource
{
    protected static ?string $model = Cidade::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Cadastros';

    protected static ?string $modelLabel = 'Cidade';

    protected static ?string $pluralModelLabel = 'Cidades';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('descricao')
                    ->label('Descrição')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('uf')
                    ->label('UF')
                    ->options(fn () => Estado::orderBy('uf')->pluck('uf', 'uf'))
                    ->searchable(),
                Forms\Components\TextInput::make('cod_ibge')
                    ->label('Código IBGE')
                    ->numeric(),
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
                Tables\Columns\TextColumn::make('uf')
                    ->label('UF')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cod_ibge')
                    ->label('Cód. IBGE')
                    ->toggleable(),
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

    /**
     * Escopo por grupo do usuário logado (mesma regra do legado:
     * grupo_id = empresa_padrao->grupo_id). Registros globais (grupo_id null)
     * também aparecem, como nas buscas legadas.
     */
    public static function getEloquentQuery(): Builder
    {
        $grupoId = optional(optional(auth()->user())->empresa)->grupo_id;

        return parent::getEloquentQuery()
            ->when($grupoId, fn (Builder $q) => $q->where(function (Builder $sub) use ($grupoId) {
                $sub->where('grupo_id', $grupoId)->orWhereNull('grupo_id');
            }));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCidades::route('/'),
            'create' => Pages\CreateCidade::route('/create'),
            'edit' => Pages\EditCidade::route('/{record}/edit'),
        ];
    }
}
