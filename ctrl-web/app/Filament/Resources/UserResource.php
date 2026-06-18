<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\User;
use App\Empresa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * FASE 4 Bloco A (D11) — gestão de usuários em Filament.
 *
 * Corrige o IDOR do legado (UsersController@index fazia User::all(), listando
 * usuários de TODAS as empresas, sem paginação — PRD/11 §6): aqui a query é
 * escopada pela(s) empresa(s) do usuário logado e paginada pela tabela.
 *
 * Esta etapa cobre dados básicos (nome/email/ativo/empresa/flags). A senha tem
 * fluxo próprio (legado: updatepassword com Hash::check) e o motor de permissões
 * (menuusers) será uma etapa seguinte — não são editados aqui. Permissões da
 * tela via UserPolicy (menu 'user.index'). Model legado em App\User.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    // M1.3: grupo "Administração" do sidebar-alvo (MODERN_00 §3).
    protected static ?string $navigationGroup = 'Administração';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Usuário';

    protected static ?string $pluralModelLabel = 'Usuários';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('E-mail / login')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('empresa_id')
                    ->label('Empresa')
                    ->relationship('empresa', 'razao_social')
                    ->searchable()
                    ->preload(),
                Forms\Components\Toggle::make('ativo')
                    ->label('Ativo'),
                Forms\Components\Toggle::make('support')
                    ->label('Suporte (acesso total)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('empresa.razao_social')
                    ->label('Empresa')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),
                Tables\Columns\IconColumn::make('support')
                    ->label('Suporte')
                    ->boolean()
                    ->toggleable(),
            ])
            ->defaultSort('name')
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
     * Escopo por empresa(s) do usuário logado (corrige o IDOR do legado).
     * Usuários de suporte (support=1) enxergam todos, como no AuthorizeCustom.
     */
    public static function getEloquentQuery(): Builder
    {
        $auth = auth()->user();
        $query = parent::getEloquentQuery();

        if ($auth && (string) $auth->support === '1') {
            return $query;
        }

        // empresa_list = ids das empresas do usuário (App\User::getEmpresaListAttribute);
        // garante ao menos a empresa principal.
        $empresas = $auth ? collect($auth->empresa_list)->push($auth->empresa_id)->filter()->unique() : collect();

        return $query->when(
            $empresas->isNotEmpty(),
            fn (Builder $q) => $q->whereIn('empresa_id', $empresas->all())
        );
    }

    public static function getRelations(): array
    {
        return [
            UserResource\RelationManagers\MenuuserRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
