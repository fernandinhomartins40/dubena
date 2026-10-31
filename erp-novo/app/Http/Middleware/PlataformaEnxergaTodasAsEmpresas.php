<?php

namespace App\Http\Middleware;

use App\Domain\Tenant\ConexaoDeOwner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PIE P2-E-01 — a camada da plataforma (`/api/superadmin`) lê e escreve pela
 * conexão de owner.
 *
 * O SuperAdmin não resolve tenant de propósito: opera sobre TODAS as empresas.
 * Pelo runtime (`erp_app`, RLS forçada, sem `app.grupo_id`) ele não enxergava
 * nenhuma — lista vazia, dashboard zerado e 404 em suspender, trocar plano e
 * override. Ou seja: a plataforma não conseguia suspender uma revenda, e a
 * correção do PIE-04 ficava sem porta. A suíte em sqlite não tem RLS e passava.
 *
 * Fica DEPOIS de `auth:platform`: só um admin da plataforma autenticado chega
 * aqui, e toda ação continua passando pela `AuditoriaPlataforma`.
 */
class PlataformaEnxergaTodasAsEmpresas
{
    public function handle(Request $request, Closure $next): Response
    {
        return ConexaoDeOwner::executar(fn () => $next($request));
    }
}
