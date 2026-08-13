<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use Illuminate\Support\Facades\DB;

/**
 * Migra o sistema de rastreamento "Monitora" (MySQL) para o ERP novo.
 *
 * Não confundir com o MonitoraMigrator: aquele lê um monitora já espelhado no
 * Postgres com o schema NOVO; este lê o banco ORIGINAL do produto (MySQL), que
 * tem estrutura própria.
 *
 * A diferença que exige tradução: o Monitora tem a PRÓPRIA numeração de
 * empresas (1=Distribuidora Dubena, 2=Central Gás, 3=Dubena Particular, 4=QTI),
 * independente da do ERP (2, 114..117, 134, 135). O casamento é por NOME; as
 * empresas do Monitora que não existem no ERP têm seus veículos pulados — não se
 * inventa tenant.
 *
 * Cercas: no legado o formato é POLÍGONO (tabela cercapoligonos); o schema novo
 * guarda centro+raio. Converte-se o polígono no seu círculo circunscrito
 * (centroide + maior distância ao centroide), preservando a área de cobertura.
 *
 * Fonte: conexão `monitora_legado` (MySQL).
 */
final class MonitoraLegadoMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'monitora-legado';
    }

    public function dependeDe(): array
    {
        return ['empresas'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        if (! $this->disponivel()) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['conexão `monitora_legado` indisponível — nada a migrar']);
        }

        $mapaEmpresa = $this->mapearEmpresas();
        $avisos = [];
        if ($mapaEmpresa === []) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['nenhuma empresa do Monitora casou com o ERP (comparação por nome)']);
        }

        [$veiculos, $veiculosPulados] = $this->lerVeiculos($mapaEmpresa);
        [$cercas, $cercasPuladas] = $this->lerCercas($mapaEmpresa);

        $gravados = 0;
        if (! $ctx->dryRun) {
            $gravados += $this->gravarPreservandoId('monitora_veiculos', $veiculos);
            $gravados += $this->gravarPreservandoId('monitora_cercas', $cercas);

            // A última posição herda a empresa do veículo (`empresa_id` é NOT
            // NULL nas filhas, por causa do isolamento multi-tenant).
            $empresaDoVeiculo = [];
            foreach ($veiculos as $v) {
                $empresaDoVeiculo[(int) $v['id']] = (int) $v['empresa_id'];
            }
            $ultimas = $this->lerUltimasPosicoes($empresaDoVeiculo);
            $gravados += $this->gravarPreservandoId(
                'monitora_ultima_posicao', $ultimas, ['veiculo_id']
            );
        }

        if ($veiculosPulados) {
            $avisos[] = "{$veiculosPulados} veículo(s) de empresa do Monitora sem "
                .'correspondente no ERP — pulados';
        }
        if ($cercasPuladas) {
            $avisos[] = "{$cercasPuladas} cerca(s) sem polígono ou de empresa "
                .'não mapeada — puladas';
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($veiculos) + count($cercas) + $veiculosPulados + $cercasPuladas,
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: $veiculosPulados + $cercasPuladas,
            avisos: $avisos,
        );
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if (! $this->disponivel()) {
            return [];
        }

        return [
            new IntegrityInvariant($ctx, 'monitora_veiculos', 'empresa_id', 'empresas'),
            new IntegrityInvariant($ctx, 'monitora_ultima_posicao', 'veiculo_id', 'monitora_veiculos'),
        ];
    }

    /**
     * empresa do Monitora => [empresa_id, grupo_id] do ERP, casando por nome
     * normalizado (o legado grava em caixa alta e sem acento consistente).
     *
     * @return array<int, array{empresa_id:int, grupo_id:int}>
     */
    private function mapearEmpresas(): array
    {
        $doErp = [];
        foreach (DB::table('empresas')->select('id', 'grupo_id', 'razao_social')->get() as $e) {
            $doErp[$this->normalizar((string) $e->razao_social)] ??= [
                'empresa_id' => (int) $e->id,
                'grupo_id' => (int) $e->grupo_id,
            ];
        }

        $mapa = [];
        foreach ($this->fonte()->table('empresas')->get() as $e) {
            $chave = $this->normalizar((string) $e->razao_social);
            if (isset($doErp[$chave])) {
                $mapa[(int) $e->id] = $doErp[$chave];
            }
        }

        return $mapa;
    }

    /** Caixa baixa, sem acento e sem sufixo societário, para casar nomes. */
    private function normalizar(string $v): string
    {
        $v = mb_strtolower(trim($v));
        $v = strtr($v, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e',
            'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
        ]);
        $v = preg_replace('/\b(ltda|s\.?a\.?|me|epp|eireli)\b\.?/', '', $v);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $v));
    }

    /** @return array{0: list<array<string,mixed>>, 1: int} */
    private function lerVeiculos(array $mapaEmpresa): array
    {
        $out = [];
        $pulados = 0;
        foreach ($this->fonte()->table('veiculos')->get() as $v) {
            $emp = $mapaEmpresa[(int) $v->empresa_id] ?? null;
            if ($emp === null) {
                $pulados++;

                continue;
            }
            $out[] = [
                'id' => (int) $v->id,
                'empresa_id' => $emp['empresa_id'],
                'grupo_id' => $emp['grupo_id'],
                'placa' => mb_substr(trim((string) ($v->placa ?? '')), 0, 10),
                'descricao' => $v->descricao ?? null,
                // `deviceid` do rastreador é o que liga o veículo às posições.
                'imei' => $v->deviceid !== null ? mb_substr((string) $v->deviceid, 0, 30) : null,
                'ativo' => (bool) ($v->ativo ?? true),
            ];
        }

        return [$out, $pulados];
    }

    /**
     * Cercas: polígono (N pontos) → centro + raio, o formato do schema novo.
     *
     * @return array{0: list<array<string,mixed>>, 1: int}
     */
    private function lerCercas(array $mapaEmpresa): array
    {
        $pontos = [];
        foreach ($this->fonte()->table('cercapoligonos')
            ->select('cerca_id', 'latitude', 'longitude')->get() as $p) {
            if ($p->latitude === null || $p->longitude === null) {
                continue;
            }
            $pontos[(int) $p->cerca_id][] = [(float) $p->latitude, (float) $p->longitude];
        }

        $out = [];
        $pulados = 0;
        foreach ($this->fonte()->table('cercas')->get() as $c) {
            $emp = $mapaEmpresa[(int) $c->empresa_id] ?? null;
            $meus = $pontos[(int) $c->id] ?? [];
            if ($emp === null || $meus === []) {
                $pulados++;

                continue;
            }

            $lat = array_sum(array_column($meus, 0)) / count($meus);
            $lng = array_sum(array_column($meus, 1)) / count($meus);

            // Raio = maior distância do centroide a um vértice (círculo que
            // circunscreve o polígono, para não encolher a área coberta).
            $raio = 0.0;
            foreach ($meus as [$plat, $plng]) {
                $raio = max($raio, $this->metrosEntre($lat, $lng, $plat, $plng));
            }

            $out[] = [
                'id' => (int) $c->id,
                'empresa_id' => $emp['empresa_id'],
                'descricao' => mb_substr((string) ($c->descricao ?? 'Cerca'), 0, 255),
                'centro_lat' => round($lat, 7),
                'centro_lng' => round($lng, 7),
                'raio_metros' => round($raio, 2),
                'ativo' => (bool) ($c->ativo ?? true),
            ];
        }

        return [$out, $pulados];
    }

    /**
     * @param  array<int,int>  $empresaDoVeiculo  veiculo_id => empresa_id
     * @return list<array<string,mixed>>
     */
    private function lerUltimasPosicoes(array $empresaDoVeiculo): array
    {
        $out = [];
        foreach ($this->fonte()->table('ultimaposicaos')->get() as $u) {
            $empresa = $empresaDoVeiculo[(int) $u->veiculo_id] ?? null;
            if ($empresa === null || $u->latitude === null || $u->longitude === null) {
                continue;
            }
            $out[] = [
                'veiculo_id' => (int) $u->veiculo_id,
                'empresa_id' => $empresa,
                'latitude' => round((float) $u->latitude, 7),
                'longitude' => round((float) $u->longitude, 7),
                'velocidade' => round((float) ($u->velocidade ?? 0), 2),
                'ignicao' => false, // o legado não registra ignição
                'registrado_em' => $u->datahora ?? now(),
            ];
        }

        return $out;
    }

    /** Distância aproximada em metros (haversine). */
    private function metrosEntre(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function fonte()
    {
        return DB::connection('monitora_legado');
    }

    private function disponivel(): bool
    {
        try {
            $this->fonte()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
