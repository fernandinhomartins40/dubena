<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * FASE 3 — Fundação da UI moderna (Filament 3).
 *
 * O painel novo coexiste com o ERP legado (AdminLTE/Blade) atrás do MESMO login.
 * Decisões de coexistência:
 *  - Guard 'web' (mesmo do ERP): reusa App\User; NÃO há segundo sistema de auth.
 *  - SEM ->login() próprio do Filament: usuários não autenticados caem na rota
 *    nomeada 'login' (= /login do AuthController legado). Um único formulário de
 *    login para as duas UIs.
 *  - Acesso ao painel é decidido por App\User::canAccessPanel() + Policies que
 *    mapeiam as permissões legadas (menuusers) — sem o bypass de AJAX.
 *  - path 'admin': o ERP legado segue na raiz; o Filament vive sob /admin.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->authGuard('web')
            ->brandName('Dubena')
            ->brandLogo(asset('img/logo_dubena.svg'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('logo_icon.svg'))
            ->colors([
                // Azul da identidade do ERP legado (skins AdminLTE: #3A5FCD/#0044cc).
                'primary' => Color::Blue,
            ])
            ->darkMode(true)
            ->sidebarCollapsibleOnDesktop()
            // M1.3 — NAVEGAÇÃO DECLARATIVA: a ordem do sidebar é definida aqui (não em
            // tabela `menus`). Cada Resource declara seu navigationGroup; estes grupos
            // são a espinha do menu-alvo (PRD/MODERN_00_VISAO_UX §3). Grupos sem recurso
            // ainda não aparecem — surgem conforme os módulos migram para Filament.
            ->navigationGroups([
                'Cadastros',
                'Vendas',
                'Estoque',
                'Financeiro',
                'Fiscal',
                'RH',
                'Frota',
                'Relatórios',
                'Administração',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
