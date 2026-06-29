<?php

namespace App\Domain\Saas;

use App\Domain\Monitora\MonitoraService;
use App\Models\Empresa;
use App\Models\Saas\CidadePlataforma;
use Illuminate\Support\Collection;

/**
 * CidadeService (P3) — catálogo de cidades da plataforma + resolução por ponto.
 *
 * "Multi-cidade" geolocalização-first: a cidade de um endereço é DERIVADA da
 * coordenada (cidade ativa cujo centro está mais perto), não um nível de tenancy.
 * Reusa MonitoraService::distanciaMetros (mesma matemática do marketplace/geofence).
 *
 * O isolamento entre empresas NÃO depende disto — segue por Grupo→Empresa + RLS.
 */
class CidadeService
{
    public function __construct(private MonitoraService $monitora) {}

    /**
     * Cidades ativas da plataforma (catálogo público de descoberta).
     *
     * @return Collection<int, CidadePlataforma>
     */
    public function ativas(): Collection
    {
        return CidadePlataforma::query()->where('ativo', true)->orderBy('uf')->orderBy('nome')->get();
    }

    /**
     * Resolve a cidade da plataforma mais próxima de um ponto (entre as ativas com
     * centro definido). Retorna null se nenhuma cidade tem centro cadastrado.
     */
    public function resolverPorPonto(float $lat, float $lng): ?CidadePlataforma
    {
        return $this->ativas()
            ->filter(fn (CidadePlataforma $c) => $c->centro_lat !== null && $c->centro_lng !== null)
            ->sortBy(fn (CidadePlataforma $c) => $this->monitora->distanciaMetros(
                (float) $c->centro_lat, (float) $c->centro_lng, $lat, $lng,
            ))
            ->first();
    }

    /**
     * Cria ou atualiza uma cidade no catálogo (idempotente por nome+uf). Usado pelo
     * SuperAdmin (P4) e pelo seeder.
     *
     * @param  array<string,mixed>  $dados
     */
    public function salvar(array $dados, ?int $id = null): CidadePlataforma
    {
        if ($id !== null) {
            $cidade = CidadePlataforma::query()->findOrFail($id);
            $cidade->update($dados);

            return $cidade->refresh();
        }

        return CidadePlataforma::query()->updateOrCreate(
            ['nome' => $dados['nome'], 'uf' => $dados['uf']],
            $dados,
        );
    }

    /** Define as cidades em que uma empresa atua (vínculo declarativo). */
    public function definirCidadesDaEmpresa(Empresa $empresa, array $cidadeIds): void
    {
        // sync mantém só os ids informados (remove vínculos antigos).
        $empresa->cidadesPlataforma()->sync(array_values(array_unique(array_map('intval', $cidadeIds))));
    }
}
