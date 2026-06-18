<?php

namespace App\Filament\Widgets;

use App\Cliente;
use App\Produto;
use App\Pedido;
use App\Financeiro;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * M1 — Widget de visão geral do painel novo (/admin). Mostra números REAIS do
 * negócio (escopados pela empresa do usuário logado) para o painel não nascer
 * vazio. Usa os models legados; consultas simples e baratas.
 */
class ResumoOperacionalWidget extends BaseWidget
{
    protected static ?int $sort = -3;

    protected function getStats(): array
    {
        $empresaId = optional(optional(auth()->user())->empresa)->id
            ?? optional(auth()->user())->empresa_id;

        $escopo = fn ($query) => $empresaId ? $query->where('empresa_id', $empresaId) : $query;

        $clientes   = $escopo(Cliente::query())->count();
        $produtos   = $escopo(Produto::query())->count();
        $pedidos    = $escopo(Pedido::query())->count();
        $financeiro = $escopo(Financeiro::query())->count();

        return [
            Stat::make('Clientes', number_format($clientes, 0, ',', '.'))
                ->description('Cadastrados na empresa')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make('Produtos', number_format($produtos, 0, ',', '.'))
                ->description('No catálogo')
                ->descriptionIcon('heroicon-m-cube')
                ->color('success'),

            Stat::make('Pedidos', number_format($pedidos, 0, ',', '.'))
                ->description('Total de pedidos')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('warning'),

            Stat::make('Lançamentos financeiros', number_format($financeiro, 0, ',', '.'))
                ->description('Títulos a pagar/receber')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),
        ];
    }
}
