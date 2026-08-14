<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Configurações e credenciais por empresa (`empresaconfigs` do legado).
 *
 * Traz o que o ERP precisa para operar de verdade e que estava ficando para
 * trás: chave PIX + client_id/secret, chave do Google Maps, senha mestra e
 * parâmetros de e-mail.
 *
 * Segredos: o schema novo guarda as integrações em
 * `empresa_configs.dados['integracoes'][<serviço>]` com os campos sensíveis
 * CIFRADOS por valor (mesmo contrato do IntegracaoTenant, que é quem lê). Aqui
 * a migração cifra na escrita — nunca grava segredo em claro.
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
        $comMaps = 0;

        foreach ($ctx->legado()->table('empresaconfigs')->orderBy('id')->get() as $r) {
            $lidos++;
            $empresa = (int) $r->empresa_id;
            if (! isset($idsEmpresa[$empresa])) {
                $pulados++;

                continue;
            }

            $integracoes = [];

            // PIX: chave recebedora + credenciais do PSP.
            $chavePix = $this->texto($r->chavepix ?? null);
            $clientId = $this->texto($r->client_id ?? null);
            $clientSecret = $this->texto($r->client_secret ?? null);
            if ($chavePix !== null || $clientId !== null) {
                $integracoes['pix'] = array_filter([
                    'chave' => $chavePix,
                    'client_id' => $clientId,
                    // Cifrado: é o contrato que o IntegracaoTenant espera no read.
                    'client_secret' => $clientSecret !== null ? Crypt::encryptString($clientSecret) : null,
                    'ambiente' => 'homologacao',
                ], fn ($v) => $v !== null);
                $comPix++;
            }

            $maps = $this->texto($r->keygooglemaps ?? null);
            if ($maps !== null) {
                $integracoes['maps'] = ['api_key' => Crypt::encryptString($maps)];
                $comMaps++;
            }

            $linha = [
                'empresa_id' => $empresa,
                'email_username' => $this->texto($r->emailusuario ?? null),
                'email_password' => ($s = $this->texto($r->emailsenha ?? null)) !== null
                    ? Crypt::encryptString($s) : null,
                'senha_mestra' => $this->texto($r->senhamestre ?? null),
                'dados' => $integracoes !== [] ? json_encode(['integracoes' => $integracoes],
                    JSON_UNESCAPED_UNICODE) : null,
                'created_at' => $r->created_at ?? null,
            ];

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
        $avisos[] = "{$comPix} empresa(s) com credencial PIX e {$comMaps} com chave "
            .'do Google Maps — segredos gravados CIFRADOS';
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

    private function tabelaExiste(MigrationContext $ctx, string $tabela): bool
    {
        try {
            return $ctx->legado()->getSchemaBuilder()->hasTable($tabela);
        } catch (\Throwable) {
            return false;
        }
    }
}
