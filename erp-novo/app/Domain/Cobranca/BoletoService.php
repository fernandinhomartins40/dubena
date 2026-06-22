<?php

namespace App\Domain\Cobranca;

use App\Domain\Cobranca\Contracts\BoletoDriver;
use App\Models\Cobranca\Boleto;
use App\Models\Cobranca\RemessaCnab;
use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * BoletoService (N7 — GATE bancário). Orquestra geração, remessa CNAB e retorno,
 * delegando a parte bancária ao BoletoDriver (porta a lib por banco em produção).
 * Gera registros rastreáveis (boletos, ocorrências, remessas).
 */
class BoletoService
{
    public function __construct(private BoletoDriver $driver)
    {
    }

    /** Gera um boleto para uma parcela de financeiro. */
    public function gerarParaParcela(FinanceiroParcela $parcela): Boleto
    {
        return DB::transaction(function () use ($parcela) {
            $financeiro = $parcela->financeiro;
            $boleto = Boleto::create([
                'empresa_id' => $financeiro->empresa_id,
                'financeiroparcela_id' => $parcela->id,
                'cliente_id' => $financeiro->cliente_id,
                'banco_codigo' => $this->driver->bancoCodigo(),
                'valor' => $parcela->valor,
                'vencimento' => $parcela->vencimento,
                'situacao' => SituacaoBoleto::PENDENTE->value,
            ]);

            $dados = $this->driver->gerar($boleto);
            $boleto->update($dados);

            return $boleto->refresh();
        });
    }

    /**
     * Gera o arquivo de remessa CNAB a partir de boletos pendentes.
     *
     * @param Collection<int,Boleto>|list<Boleto> $boletos
     */
    public function gerarRemessa(iterable $boletos, int $empresaId): RemessaCnab
    {
        $boletos = collect($boletos);

        return DB::transaction(function () use ($boletos, $empresaId) {
            $numero = (int) (RemessaCnab::withoutTenant()->where('empresa_id', $empresaId)->max('numero_remessa') ?? 0) + 1;
            $linhas = $boletos->map(fn (Boleto $b) => $this->driver->linhaRemessa($b))->implode("\n");

            $remessa = RemessaCnab::create([
                'empresa_id' => $empresaId,
                'banco_codigo' => $this->driver->bancoCodigo(),
                'numero_remessa' => $numero,
                'arquivo' => "remessas/CB{$this->driver->bancoCodigo()}_{$numero}.rem",
                'total_boletos' => $boletos->count(),
                'valor_total' => round((float) $boletos->sum('valor'), 2),
                'situacao' => 'GERADA',
            ]);

            // Os boletos da remessa passam a REGISTRADO (enviados ao banco).
            Boleto::withoutTenant()->whereIn('id', $boletos->pluck('id'))
                ->where('situacao', SituacaoBoleto::PENDENTE->value)
                ->update(['situacao' => SituacaoBoleto::REGISTRADO->value]);

            // (linhas do CNAB seriam escritas no arquivo pelo storage em produção)
            unset($linhas);

            return $remessa->refresh();
        });
    }

    /**
     * Processa um arquivo de retorno CNAB (linhas), atualizando situação e
     * registrando ocorrências. Liquidação confirma a parcela.
     *
     * @param list<string> $linhas
     * @return int nº de ocorrências processadas
     */
    public function processarRetorno(array $linhas): int
    {
        $processadas = 0;

        foreach ($linhas as $linha) {
            if (trim($linha) === '') {
                continue;
            }
            $oc = $this->driver->interpretarRetorno($linha);

            // Casa a ocorrência ao boleto pelo nosso número embutido na linha (fake:
            // procura por qualquer boleto cujo nosso_numero apareça na linha).
            $boleto = $this->localizarBoleto($linha);
            if (! $boleto) {
                continue;
            }

            DB::transaction(function () use ($boleto, $oc) {
                $boleto->ocorrencias()->create([
                    'codigo' => $oc['codigo'],
                    'descricao' => $oc['descricao'],
                    'valor' => $oc['valor'],
                    'data_ocorrencia' => now()->toDateString(),
                ]);
                $boleto->update(['situacao' => $oc['situacao']]);

                // Liquidação → baixa a parcela do financeiro.
                if ($oc['situacao'] === SituacaoBoleto::LIQUIDADO->value && $boleto->financeiroparcela_id) {
                    FinanceiroParcela::query()->whereKey($boleto->financeiroparcela_id)->update([
                        'baixado' => true,
                        'valor_efetivado' => $oc['valor'] ?? $boleto->valor,
                        'datahora_baixa' => now(),
                    ]);
                }
            });
            $processadas++;
        }

        return $processadas;
    }

    private function localizarBoleto(string $linha): ?Boleto
    {
        return Boleto::withoutTenant()->get()->first(
            fn (Boleto $b) => $b->nosso_numero && str_contains($linha, $b->nosso_numero),
        );
    }
}
