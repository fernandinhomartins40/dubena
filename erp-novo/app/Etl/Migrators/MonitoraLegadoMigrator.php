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
 * Cercas: o legado guarda POLÍGONO (`cercapoligonos`) e o schema novo guarda as
 * DUAS formas — os vértices em `monitora_cerca_pontos` (a cerca de verdade, que
 * a tela desenha) e um círculo circunscrito em `monitora_cercas` (centroide +
 * maior distância ao centroide), útil para enquadrar o mapa e para uma checagem
 * barata de "está longe demais" antes do teste exato.
 *
 * A primeira versão gravava só o círculo, e isso DEFORMAVA a área: um setor em L
 * virava um círculo cobrindo bairros vizinhos, e a tela mostrava "0 pts" porque
 * não havia polígono nenhum para desenhar.
 *
 * Fonte: conexão `monitora_legado` (MySQL).
 */
final class MonitoraLegadoMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    /** @var list<array<string, mixed>> vértices das cercas, gravados após as cercas */
    private array $pontos = [];

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

        $avisos = [];
        $mapaEmpresa = $this->mapearEmpresas();

        // Empresa que existe no Monitora e não no ERP (Central Gás, QTI,
        // Dubena Particular) é CRIADA como tenant próprio. Descartar seus
        // veículos perderia frota e histórico de GPS de operação real.
        $criadas = $this->criarEmpresasFaltantes($mapaEmpresa, $ctx);
        if ($criadas > 0) {
            $avisos[] = "{$criadas} empresa(s) do Monitora sem correspondente no "
                .'ERP foram criadas como tenant próprio';
        }

        if ($mapaEmpresa === []) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['nenhuma empresa do Monitora pôde ser mapeada nem criada']);
        }

        $tipos = $this->lerTiposVeiculo($mapaEmpresa);
        [$veiculos, $veiculosPulados] = $this->lerVeiculos($mapaEmpresa);
        [$cercas, $cercasPuladas, $cercasSemPoligono] = $this->lerCercas($mapaEmpresa);

        // Tipo que não veio do legado não pode virar FK órfã no veículo.
        $idsTipo = array_flip(array_column($tipos, 'id'));
        foreach ($veiculos as &$v) {
            if ($v['tipo_id'] !== null && ! isset($idsTipo[$v['tipo_id']])) {
                $v['tipo_id'] = null;
            }
        }
        unset($v);

        $gravados = 0;
        if (! $ctx->dryRun) {
            // Antes dos veículos: `tipo_id` é FK.
            $gravados += $this->gravarPreservandoId('monitora_veiculo_tipos', $tipos);
            $gravados += $this->gravarPreservandoId('monitora_veiculos', $veiculos);
            $gravados += $this->gravarPreservandoId('monitora_cercas', $cercas);

            // Depois das cercas: `cerca_id` é FK. Recarga apaga e regrava os
            // vértices da cerca — o polígono é substituído por inteiro, nunca
            // mesclado, senão uma cerca redesenhada no legado ficaria com os
            // pontos velhos misturados aos novos.
            if ($this->pontos !== []) {
                $idsCerca = array_column($cercas, 'id');
                DB::table('monitora_cerca_pontos')->whereIn('cerca_id', $idsCerca)->delete();
                foreach (array_chunk($this->pontos, 500) as $bloco) {
                    DB::table('monitora_cerca_pontos')->insert(array_map(
                        fn ($p) => $p + ['created_at' => now(), 'updated_at' => now()],
                        $bloco,
                    ));
                }
                $gravados += count($this->pontos);
            }

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
            $avisos[] = "{$cercasPuladas} cerca(s) de empresa não mapeada — puladas";
        }
        if ($cercasSemPoligono) {
            $avisos[] = "{$cercasSemPoligono} cerca(s) sem polígono no legado — "
                .'migradas inativas, com área a definir';
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
        $ctx = $this->ctxAtual ?? new MigrationContext;
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

    /**
     * Cria como tenant as empresas que só existem no Monitora, e completa o
     * mapa com elas. Preserva a frota e o histórico de GPS dessas operações.
     *
     * @param  array<int, array{empresa_id:int, grupo_id:int}>  $mapa
     */
    private function criarEmpresasFaltantes(array &$mapa, MigrationContext $ctx): int
    {
        $semEmpresa = [];
        foreach ($this->fonte()->table('empresas')->get() as $e) {
            if (! isset($mapa[(int) $e->id])) {
                $semEmpresa[] = $e;
            }
        }
        if ($semEmpresa === [] || $ctx->dryRun) {
            return 0;
        }

        // Herdam o grupo da primeira empresa já existente (mesma rede Dubena).
        $grupoId = (int) (DB::table('empresas')->min('grupo_id')
            ?? DB::table('grupos')->min('id'));
        $proximoId = (int) DB::table('empresas')->max('id');

        $novas = [];
        foreach ($semEmpresa as $e) {
            $proximoId++;
            $nome = trim((string) ($e->razao_social ?? $e->nome_fantasia ?? 'Empresa'));
            $novas[] = [
                'id' => $proximoId,
                'grupo_id' => $grupoId,
                'razao_social' => mb_substr($nome, 0, 255),
                'nome_fantasia' => mb_substr((string) ($e->nome_fantasia ?? $nome), 0, 255),
                'nome_informal' => mb_substr((string) ($e->nome_informal ?? $nome), 0, 255),
                'cnpj' => $this->soDigitos($e->cnpj ?? null),
                'latitude' => $e->latitude !== null ? round((float) $e->latitude, 7) : null,
                'longitude' => $e->longitude !== null ? round((float) $e->longitude, 7) : null,
                'matriz' => false,
                'ativo' => (bool) ($e->ativo ?? true),
            ];
            $mapa[(int) $e->id] = ['empresa_id' => $proximoId, 'grupo_id' => $grupoId];
        }

        $this->gravarPreservandoId('empresas', $novas);

        return count($novas);
    }

    /** CNPJ do legado vem com máscara; o schema novo é varchar(14). */
    private function soDigitos(mixed $v): ?string
    {
        $d = preg_replace('/\D/', '', (string) ($v ?? ''));

        return $d === '' ? null : substr($d, 0, 14);
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
    /**
     * Tipos de veículo do Monitora (CARRO, CAMINHÃO, CAMINHONETE, MOTO).
     *
     * Não confundir com `veiculo_tipos`, que é o cadastro da frota do ERP e vem
     * de outro dump. Este aqui existe para o mapa: é o tipo que decide o ícone
     * desenhado e a velocidade a partir da qual o relatório aponta excesso.
     *
     * O legado não tem `grupo_id` nesta tabela — o cadastro era global. Como no
     * schema novo o tipo pertence a um grupo, todos entram no grupo da primeira
     * empresa mapeada, que é o da revenda.
     *
     * @return list<array<string,mixed>>
     */
    private function lerTiposVeiculo(array $mapaEmpresa): array
    {
        if ($mapaEmpresa === []) {
            return [];
        }

        // Espelho antigo pode não ter a tabela: o migrator roda contra dumps de
        // idades diferentes, e ausência de tipos não pode impedir a frota de
        // migrar — só deixa os veículos sem ícone.
        if (! $this->fonte()->getSchemaBuilder()->hasTable('veiculotipos')) {
            return [];
        }

        $grupoId = (int) reset($mapaEmpresa)['grupo_id'];
        $out = [];

        foreach ($this->fonte()->table('veiculotipos')->get() as $t) {
            $out[] = [
                'id' => (int) $t->id,
                'grupo_id' => $grupoId,
                'descricao' => mb_substr(trim((string) ($t->descricao ?? '')), 0, 255),
                // O legado guardava caminho de imagem ('veiculos/caminhao'); a
                // tela nova escolhe o desenho pelo nome do tipo, então só o
                // rótulo curto interessa aqui.
                'icone' => $this->iconeDoTipo((string) ($t->descricao ?? '')),
                'velocidade_maxima' => (int) ($t->velocidade_maxima ?? 0) ?: null,
                'ativo' => true,
            ];
        }

        return $out;
    }

    /**
     * Rótulo do ícone a partir da descrição do tipo.
     *
     * Comparação sem acento porque o legado grava 'CAMINHÃO' e um dump com
     * codificação trocada vira 'CAMINHÃO' — casar por prefixo sem acento
     * sobrevive aos dois casos.
     */
    private function iconeDoTipo(string $descricao): string
    {
        $limpo = mb_strtolower($descricao);
        $limpo = strtr($limpo, [
            'á' => 'a', 'â' => 'a', 'ã' => 'a', 'à' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o',
            'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
        ]);

        return match (true) {
            str_contains($limpo, 'caminhonete') => 'caminhonete',
            str_contains($limpo, 'caminh') => 'caminhao',
            str_contains($limpo, 'moto') => 'moto',
            str_contains($limpo, 'carro') => 'carro',
            default => 'outro',
        };
    }

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
                // `deviceid` do rastreador é o que liga o veículo às posições —
                // e é o mesmo valor que o Traccar chama de `uniqueId`, o que
                // permite casar os dois sistemas sem tabela de-para.
                'imei' => $v->deviceid !== null ? mb_substr((string) $v->deviceid, 0, 30) : null,
                // Sem o tipo o mapa desenha todo veículo como um ponto igual:
                // não dá para distinguir o caminhão da moto no meio da frota.
                'tipo_id' => ($v->veiculotipo_id ?? null) !== null ? (int) $v->veiculotipo_id : null,
                'motorista' => $v->motorista !== null
                    ? mb_substr(trim((string) $v->motorista), 0, 255) : null,
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
        $semPoligono = 0;
        foreach ($this->fonte()->table('cercas')->get() as $c) {
            $emp = $mapaEmpresa[(int) $c->empresa_id] ?? null;
            $meus = $pontos[(int) $c->id] ?? [];
            if ($emp === null) {
                $pulados++;

                continue;
            }

            // Cerca sem polígono no legado (cadastro iniciado e não concluído):
            // migra com raio 0 no centro da empresa, preservando o cadastro em
            // vez de apagá-lo. Fica visível para o usuário completar.
            if ($meus === []) {
                $semPoligono++;
                $out[] = [
                    'id' => (int) $c->id,
                    'empresa_id' => $emp['empresa_id'],
                    'descricao' => mb_substr((string) ($c->descricao ?? 'Cerca'), 0, 255),
                    'centro_lat' => 0,
                    'centro_lng' => 0,
                    'raio_metros' => 0,
                    'ativo' => false, // sem área definida, não deve valer como geofence
                ];

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
                // A cor identifica o setor no mapa — o legado a define por cerca
                // e ela vinha sendo descartada.
                'cor' => $this->corValida($c->cor ?? null),
                'ativo' => (bool) ($c->ativo ?? true),
            ];

            // O POLÍGONO é a cerca de verdade. O círculo acima é aproximação
            // (centroide + raio circunscrito) e serve para enquadrar o mapa;
            // guardar só ele deformava a área — um setor em L virava um círculo
            // que cobre bairros vizinhos. Os vértices vão para
            // `monitora_cerca_pontos`, que é o que a tela desenha.
            foreach ($meus as $ordem => [$plat, $plng]) {
                $this->pontos[] = [
                    'cerca_id' => (int) $c->id,
                    'empresa_id' => $emp['empresa_id'],
                    'grupo_id' => $emp['grupo_id'],
                    'latitude' => round($plat, 7),
                    'longitude' => round($plng, 7),
                    'ordem' => $ordem,
                ];
            }
        }

        return [$out, $pulados, $semPoligono];
    }

    /** Cor no formato #RRGGBB; qualquer outra coisa vira null (a tela usa o padrão). */
    private function corValida(mixed $cor): ?string
    {
        $c = trim((string) ($cor ?? ''));

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $c) === 1 ? $c : null;
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
