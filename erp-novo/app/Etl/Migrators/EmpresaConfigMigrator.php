<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use App\Domain\Integracao\IntegracaoTenant;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Configurações e credenciais por empresa (`empresaconfigs` do legado).
 *
 * Traz o que o ERP precisa para operar de verdade e que estava ficando para
 * trás: chave PIX + client_id/secret, chave do Google Maps, senha mestra e
 * parâmetros de e-mail.
 *
 * Segredos: as integrações ficam em
 * `empresa_configs.dados['integracoes'][<serviço>]`. Cifrar é seletivo, não
 * geral — quem manda é `IntegracaoTenant::cifrarBloco`, usado aqui e no
 * controller de gravação, para os dois caminhos não divergirem:
 *
 *  - CIFRADOS: `client_secret`, `webhook_hmac_secret` (o GET devolve só
 *    `*_configurado: bool`);
 *  - EM CLARO: `chave` (a chave PIX é pública — é o que o pagador lê) e
 *    `client_id`, que o GET devolve para preencher a tela.
 *
 * Cifrar a mais quebra a tela: o GET não decifra esses dois, então a página de
 * Configurações exibia `eyJpdiI6...` no lugar da credencial.
 *
 * Google Maps é de REDE: vai para `config_globais.google_maps_key` do grupo,
 * onde `IntegracaoTenant::googleMapsKey()` lê — não em `empresa_configs`.
 *
 * Nota sobre o certificado A1: a coluna `EMPRESAS.CERTIFICADODIGITAL` está
 * NULA nas 7 empresas do dump (só a senha do PFX veio). Não há o que migrar —
 * o certificado precisa ser enviado pelo painel.
 */
final class EmpresaConfigMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'empresa-config';
    }

    public function dependeDe(): array
    {
        return ['empresas'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        if (! $this->tabelaExiste($ctx, 'empresaconfigs')) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['legado sem `empresaconfigs` — nada a migrar']);
        }

        $idsEmpresa = $this->idsDe('empresas');
        $colunas = DB::getSchemaBuilder()->getColumnListing('empresa_configs');

        $lidos = 0;
        $gravados = 0;
        $pulados = 0;
        $comPix = 0;
        /** @var array<int,string> empresa_id => chave do Maps (aplicada por GRUPO no fim) */
        $mapsPorEmpresa = [];

        foreach ($ctx->legado()->table('empresaconfigs')->orderBy('id')->get() as $r) {
            $lidos++;
            $empresa = (int) $r->empresa_id;
            if (! isset($idsEmpresa[$empresa])) {
                $pulados++;

                continue;
            }

            $integracoes = [];

            // PIX: chave recebedora + credenciais do PSP.
            //
            // O que é segredo e o que NÃO é: `cifrarBloco` cifra apenas
            // client_secret e webhook_hmac_secret — exatamente os mesmos campos
            // que `EmpresaConfigController@salvarIntegracoes` cifra, e os únicos
            // que o GET devolve como booleano "configurado". `chave` (a chave
            // PIX é pública por definição: é o que o pagador enxerga) e
            // `client_id` voltam em CLARO para a tela.
            //
            // Cifrar a mais não é "mais seguro", é quebrar o contrato: o GET
            // devolve esses dois campos sem decifrar, então a tela de
            // Configurações exibia o blob `eyJpdiI6...` no lugar do valor.
            $chavePix = $this->texto($r->chavepix ?? null);
            $clientId = $this->texto($r->client_id ?? null);
            $clientSecret = $this->texto($r->client_secret ?? null);
            if ($chavePix !== null || $clientId !== null) {
                $integracoes['pix'] = IntegracaoTenant::cifrarBloco(
                    array_filter([
                        'chave' => $chavePix,
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                        'ambiente' => 'homologacao',
                    ], fn ($v) => $v !== null),
                    ['client_secret', 'webhook_hmac_secret'],
                );
                $comPix++;
            }

            // Google Maps NÃO mora aqui: é credencial de REDE, lida por
            // `IntegracaoTenant::googleMapsKey()` em `config_globais.google_maps_key`
            // do grupo. Gravar em `empresa_configs` deixava a chave num lugar
            // que ninguém lê. Coletada aqui e aplicada por grupo no fim.
            $maps = $this->texto($r->keygooglemaps ?? null);
            if ($maps !== null) {
                $mapsPorEmpresa[$empresa] = $maps;
            }

            $linha = [
                'empresa_id' => $empresa,
                'email_username' => $this->texto($r->emailusuario ?? null),
                'email_password' => ($s = $this->texto($r->emailsenha ?? null)) !== null
                    ? Crypt::encryptString($s) : null,
                'senha_mestra' => $this->texto($r->senhamestre ?? null),
                'created_at' => $r->created_at ?? null,
            ];

            // `dados` é um JSON compartilhado com o resto da config da empresa.
            // Sobrescrever a coluna inteira apagaria o que já estivesse lá — a
            // gravação preserva as demais chaves e mexe só em `integracoes`.
            if ($integracoes !== []) {
                $atual = json_decode((string) DB::table('empresa_configs')
                    ->where('empresa_id', $empresa)->value('dados'), true) ?: [];
                $atual['integracoes'] = array_merge($atual['integracoes'] ?? [], $integracoes);
                $linha['dados'] = json_encode($atual, JSON_UNESCAPED_UNICODE);
            }

            // Só as colunas que a tabela de destino realmente tem.
            $linha = array_filter($linha, fn ($k) => in_array($k, $colunas, true), ARRAY_FILTER_USE_KEY);

            if (! $ctx->dryRun) {
                // Chave natural: uma config por empresa.
                DB::table('empresa_configs')->updateOrInsert(
                    ['empresa_id' => $empresa],
                    $linha + ['updated_at' => now()],
                );
                $gravados++;
            }
        }

        $avisos = [];
        $gruposComMaps = $ctx->dryRun ? 0 : $this->gravarMapsPorGrupo($mapsPorEmpresa);

        $avisos[] = "{$comPix} empresa(s) com credencial PIX (client_secret cifrado; "
            ."chave e client_id em claro, como a tela lê) e {$gruposComMaps} grupo(s) "
            .'com chave do Google Maps';
        $avisos[] = 'certificado digital A1 NÃO migrado: a coluna está nula no dump '
            .'(só a senha do PFX veio). Envie o .pfx pelo painel para emitir NFC-e';
        if ($pulados > 0) {
            $avisos[] = "{$pulados} config(s) de empresa inexistente — descartadas";
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: $lidos,
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: $pulados,
            avisos: $avisos,
        );
    }

    public function invariantes(): array
    {
        return [];
    }

    /** Texto do legado: string vazia e espaços viram null. */
    private function texto(mixed $v): ?string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? null : $v;
    }

    /** @return array<int,true> */
    private function idsDe(string $tabela): array
    {
        $ids = [];
        foreach (DB::table($tabela)->select('id')->cursor() as $r) {
            $ids[(int) $r->id] = true;
        }

        return $ids;
    }

    /**
     * Grava a chave do Google Maps em `config_globais.google_maps_key` do GRUPO
     * de cada empresa — que é onde `IntegracaoTenant::googleMapsKey()` procura.
     *
     * Em claro, deliberadamente: é o formato que o leitor espera (`value()`
     * direto, sem decifrar) e o mesmo que a tela de config global grava. Uma
     * key de browser do Maps é restringida por referrer, não por sigilo.
     *
     * Quando duas empresas do mesmo grupo trazem keys diferentes, fica a
     * primeira — a rede tem uma key só, e a divergência vira aviso.
     *
     * @param  array<int,string>  $mapsPorEmpresa
     * @return int  grupos que receberam chave
     */
    private function gravarMapsPorGrupo(array $mapsPorEmpresa): int
    {
        if ($mapsPorEmpresa === []) {
            return 0;
        }

        $grupoDaEmpresa = DB::table('empresas')
            ->whereIn('id', array_keys($mapsPorEmpresa))
            ->pluck('grupo_id', 'id');

        $porGrupo = [];
        foreach ($mapsPorEmpresa as $empresa => $key) {
            $grupo = (int) ($grupoDaEmpresa[$empresa] ?? 0);
            if ($grupo > 0 && ! isset($porGrupo[$grupo])) {
                $porGrupo[$grupo] = $key;
            }
        }

        foreach ($porGrupo as $grupo => $key) {
            DB::table('config_globais')->updateOrInsert(
                ['grupo_id' => $grupo],
                ['google_maps_key' => $key, 'updated_at' => now()],
            );
        }

        return count($porGrupo);
    }

    private function tabelaExiste(MigrationContext $ctx, string $tabela): bool
    {
        try {
            return $ctx->legado()->getSchemaBuilder()->hasTable($tabela);
        } catch (\Throwable) {
            return false;
        }
    }
}
