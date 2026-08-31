<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\EmpresaConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * F5-06 — vigilância do certificado A1.
 *
 * ## Por que isto existe
 *
 * O certificado é lido, validado e tem a validade gravada (`cert_validade`). E
 * o status era **passivo**: `CertificadoService::status()` responde quando
 * alguém abre a tela fiscal. Ninguém abre a tela fiscal para conferir uma data
 * que só importa uma vez por ano.
 *
 * Um A1 vale 12 meses. Quando vence, a emissão simplesmente para — e a revenda
 * descobre pela primeira nota recusada, com o cliente esperando na porta e a
 * mercadoria no caminhão. Renovar leva dias: agendar, validar identidade,
 * baixar o novo arquivo.
 *
 * Num SaaS isso deixa de ser problema de uma revenda: com N empresas, alguma
 * está sempre a poucas semanas de vencer, e nenhuma delas vai lembrar sozinha.
 *
 * ## Por que só reporta
 *
 * O comando não desabilita emissão nem bloqueia nada. Certificado vencendo é
 * fato para agir, não motivo para parar a operação — e desligar a emissão de
 * quem ainda tem certificado válido por engano seria muito pior que o silêncio
 * que este comando resolve.
 *
 * Sai com FAILURE quando há algo vencido: assim serve de portão em deploy e de
 * alarme no cron, sem precisar de integração nenhuma.
 */
class FiscalCertificadoVigilancia extends Command
{
    protected $signature = 'fiscal:certificado-vigilancia
                            {--dias=30 : a partir de quantos dias antes do vencimento avisar}
                            {--empresa= : limitar a uma empresa}';

    protected $description = 'Verifica a validade dos certificados A1 por empresa e reporta os que vencem ou venceram.';

    public function handle(): int
    {
        $limiteDias = max(1, (int) $this->option('dias'));
        $empresaId = $this->option('empresa') !== null ? (int) $this->option('empresa') : null;

        // A vigilância é sobre TODAS as revendas — num cron não há usuário
        // logado, e uma lista recortada por tenant sairia vazia. Vazio aqui é o
        // modo de falhar mais perigoso: "nenhum certificado vencendo" é
        // exatamente o que se espera ler, e ninguém desconfia.
        //
        // `EmpresaConfig` não usa `BelongsToTenant` (é 1:1 com a empresa e o
        // acesso passa pela empresa), então `query()` já enxerga tudo. Se um dia
        // ganhar o trait, esta linha precisa virar `withoutTenant()` — e o teste
        // que conta as empresas é o que vai avisar.
        $configs = EmpresaConfig::query()
            ->when($empresaId !== null, fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereNotNull('cert_path')
            ->get();

        $nomes = Empresa::query()->pluck('nome_fantasia', 'id');

        $vencidos = [];
        $vencendo = [];
        $semValidade = [];

        foreach ($configs as $config) {
            $nome = (string) ($nomes[$config->empresa_id] ?? "empresa #{$config->empresa_id}");
            $validade = $config->cert_validade;

            // Tem arquivo e não tem validade lida: o upload não passou pela
            // leitura do .pfx, ou a coluna foi preenchida por fora. Não dá para
            // afirmar que está bom nem que está ruim — e no fiscal a dúvida é
            // um achado, não um silêncio.
            if ($validade === null) {
                $semValidade[] = $nome;

                continue;
            }

            $dias = (int) now()->startOfDay()->diffInDays($validade->startOfDay(), false);

            if ($dias < 0) {
                $vencidos[] = [$nome, $validade->toDateString(), abs($dias)];
            } elseif ($dias <= $limiteDias) {
                $vencendo[] = [$nome, $validade->toDateString(), $dias];
            }
        }

        $this->relatar($vencidos, $vencendo, $semValidade, $configs->count(), $limiteDias);

        return $vencidos === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<array{string, string, int}>  $vencidos
     * @param  list<array{string, string, int}>  $vencendo
     * @param  list<string>  $semValidade
     */
    private function relatar(array $vencidos, array $vencendo, array $semValidade, int $total, int $limiteDias): void
    {
        $this->info("{$total} empresa(s) com certificado cadastrado.");

        foreach ($vencidos as [$nome, $data, $dias]) {
            $this->error("VENCIDO ha {$dias} dia(s) ({$data}): {$nome} — a emissao ja esta parada.");
            Log::error('Certificado A1 vencido.', ['empresa' => $nome, 'validade' => $data]);
        }

        foreach ($vencendo as [$nome, $data, $dias]) {
            $this->warn("vence em {$dias} dia(s) ({$data}): {$nome}");
            Log::warning('Certificado A1 proximo do vencimento.', [
                'empresa' => $nome, 'validade' => $data, 'dias_restantes' => $dias,
            ]);
        }

        foreach ($semValidade as $nome) {
            $this->warn("sem validade lida: {$nome} — tem arquivo, mas nao da para afirmar ate quando vale.");
        }

        if ($vencidos === [] && $vencendo === [] && $semValidade === []) {
            $this->info("Nenhum certificado vencido ou vencendo nos proximos {$limiteDias} dias.");
        }
    }
}
