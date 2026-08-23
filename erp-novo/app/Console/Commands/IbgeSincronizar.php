<?php

namespace App\Console\Commands;

use App\Domain\Geografico\CatalogoIbge;
use App\Models\Geografico\MunicipioIbge;
use Illuminate\Console\Command;

/**
 * ibge:sincronizar — baixa o catálogo oficial de municípios e concilia as cidades.
 *
 * Por que existe: `cod_ibge` vira `cMun`/`cMunFG` no XML da NF-e. Medido na base
 * de produção, 5 das 105 cidades tinham código inventado, zerado, de outra
 * cidade ou um CEP no lugar do código — cada um deles é uma rejeição da SEFAZ
 * esperando o cutover fiscal para acontecer.
 *
 * Somente leitura por default: mostra o que mudaria e só grava com --aplicar.
 */
class IbgeSincronizar extends Command
{
    protected $signature = 'ibge:sincronizar
        {--aplicar : Grava o vínculo e corrige o cod_ibge divergente.}
        {--so-catalogo : Baixa o catálogo e não mexe nas cidades.}';

    protected $description = 'Sincroniza o catálogo de municípios do IBGE e concilia as cidades cadastradas.';

    public function handle(CatalogoIbge $catalogo): int
    {
        $aplicar = (bool) $this->option('aplicar');

        if (MunicipioIbge::query()->count() < 5000) {
            $this->info('Baixando o catálogo de municípios do IBGE…');
            try {
                $r = $catalogo->sincronizar();
            } catch (\Throwable $e) {
                $this->error('Falha ao sincronizar: '.$e->getMessage());

                return self::FAILURE;
            }
            $this->info("Catálogo: {$r['total']} municípios ({$r['novos']} novos).");
        } else {
            $this->line('Catálogo já populado ('.MunicipioIbge::query()->count().' municípios). Use ibge:sincronizar --so-catalogo para forçar.');
            if ($this->option('so-catalogo')) {
                $r = $catalogo->sincronizar();
                $this->info("Catálogo atualizado: {$r['total']} municípios.");
            }
        }

        if ($this->option('so-catalogo')) {
            return self::SUCCESS;
        }

        $conciliacao = $catalogo->conciliar();
        $problemas = array_values(array_filter(
            $conciliacao,
            fn ($i) => $i['criterio'] !== 'codigo',
        ));

        $this->newLine();
        $this->info('Cidades: '.count($conciliacao).' — '.count($problemas).' precisam de atenção.');

        if ($problemas !== []) {
            $this->table(
                ['Cidade', 'UF', 'IBGE atual', 'Situação', 'Vira'],
                array_map(fn ($i) => [
                    mb_substr((string) $i['cidade']->descricao, 0, 30),
                    $i['cidade']->uf,
                    $i['cidade']->cod_ibge ?? '—',
                    match ($i['criterio']) {
                        'nome' => 'código ausente/inválido',
                        'codigo_uf_divergente' => 'código de OUTRA UF',
                        default => 'sem correspondência',
                    },
                    $i['municipio']?->cod_ibge ?? '— (revisar à mão)',
                ], $problemas),
            );
        }

        if (! $aplicar) {
            $this->newLine();
            $this->warn('Somente leitura: nada foi alterado. Use --aplicar.');

            return self::SUCCESS;
        }

        $r = $catalogo->aplicar($conciliacao);
        $this->info("Vinculadas: {$r['vinculadas']} | Códigos corrigidos: {$r['corrigidas']} | Sem correspondência: {$r['orfas']}");

        if ($r['orfas'] > 0) {
            $this->warn('As cidades sem correspondência ficaram sem vínculo — precisam de decisão humana (nome errado ou cidade que não existe).');
        }

        return self::SUCCESS;
    }
}
