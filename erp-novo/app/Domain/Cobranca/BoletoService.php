<?php

namespace App\Domain\Cobranca;

use App\Domain\Cobranca\Contracts\BoletoDriver;
use App\Domain\Financeiro\BaixaService;
use App\Models\Cobranca\Boleto;
use App\Models\Cobranca\RemessaCnab;
use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * BoletoService (N7 — GATE bancário). Orquestra geração, remessa CNAB e retorno,
 * delegando a parte bancária ao BoletoDriver (porta a lib por banco em produção).
 * Gera registros rastreáveis (boletos, ocorrências, remessas).
 */
class BoletoService
{
    public function __construct(
        private BoletoDriver $driver,
        private BaixaService $baixas,
    ) {}

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
     * @param  Collection<int,Boleto>|list<Boleto>  $boletos
     */
    public function gerarRemessa(iterable $boletos, int $empresaId): RemessaCnab
    {
        $ids = collect($boletos)->map(fn (Boleto $boleto) => $boleto->getKey());

        if ($empresaId <= 0 || $ids->isEmpty() || $ids->contains(null) || $ids->uniqueStrict()->count() !== $ids->count()) {
            throw ValidationException::withMessages(['boletos' => 'Informe boletos validos e sem duplicidade para a empresa ativa.']);
        }

        return DB::transaction(function () use ($ids, $empresaId) {
            $boletos = Boleto::withoutTenant()
                ->where('empresa_id', $empresaId)
                ->where('banco_codigo', $this->driver->bancoCodigo())
                ->where('situacao', SituacaoBoleto::PENDENTE->value)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if ($boletos->count() !== $ids->count()) {
                throw ValidationException::withMessages(['boletos' => 'A remessa aceita somente boletos pendentes da empresa e banco ativos.']);
            }

            $numero = (int) (RemessaCnab::withoutTenant()->where('empresa_id', $empresaId)->max('numero_remessa') ?? 0) + 1;

            // Conteúdo CNAB real (uma linha por boleto). Em produção o banco recebe
            // este arquivo .rem; aqui ele é GRAVADO em disco privado, segregado por
            // empresa (F08 + F02), e o caminho fica na remessa para download/auditoria.
            $linhas = $boletos->map(fn (Boleto $b) => $this->driver->linhaRemessa($b))->implode("\r\n");
            $caminho = "remessas/empresa_{$empresaId}/CB{$this->driver->bancoCodigo()}_{$numero}.rem";
            Storage::disk('local')->put($caminho, $linhas);

            $remessa = RemessaCnab::create([
                'empresa_id' => $empresaId,
                'banco_codigo' => $this->driver->bancoCodigo(),
                'numero_remessa' => $numero,
                'arquivo' => $caminho,
                'total_boletos' => $boletos->count(),
                'valor_total' => round((float) $boletos->sum('valor'), 2),
                'situacao' => 'GERADA',
            ]);

            // Os boletos da remessa passam a REGISTRADO (enviados ao banco).
            Boleto::withoutTenant()->whereIn('id', $boletos->pluck('id'))
                ->where('empresa_id', $empresaId)
                ->where('situacao', SituacaoBoleto::PENDENTE->value)
                ->update(['situacao' => SituacaoBoleto::REGISTRADO->value]);

            return $remessa->refresh();
        });
    }

    /**
     * Processa um arquivo de retorno CNAB (linhas), atualizando situação e
     * registrando ocorrências. Liquidação confirma a parcela.
     *
     * @param  list<string>  $linhas
     * @return int nº de ocorrências processadas
     */
    public function processarRetorno(array $linhas, int $empresaId): int
    {
        if ($empresaId <= 0) {
            throw ValidationException::withMessages(['empresa_id' => 'Empresa ativa obrigatoria para processar o retorno.']);
        }

        $processadas = 0;

        foreach ($linhas as $linha) {
            if (trim($linha) === '') {
                continue;
            }
            $oc = $this->driver->interpretarRetorno($linha);

            // O driver extrai o identificador somente do campo posicional do seu
            // layout. A consulta exata também exige empresa e banco ativos.
            $boletoId = $this->driver->boletoIdRetorno($linha);
            if ($boletoId === null) {
                continue;
            }

            $processada = DB::transaction(function () use ($boletoId, $empresaId, $oc) {
                $boleto = Boleto::withoutTenant()
                    ->whereKey($boletoId)
                    ->where('empresa_id', $empresaId)
                    ->where('banco_codigo', $this->driver->bancoCodigo())
                    ->lockForUpdate()
                    ->first();
                if (! $boleto) {
                    return false;
                }

                $boleto->ocorrencias()->create([
                    'codigo' => $oc['codigo'],
                    'descricao' => $oc['descricao'],
                    'valor' => $oc['valor'],
                    'data_ocorrencia' => now()->toDateString(),
                ]);
                $boleto->update(['situacao' => $oc['situacao']]);

                // Liquidação → baixa a parcela do financeiro.
                if ($oc['situacao'] === SituacaoBoleto::LIQUIDADO->value && $boleto->financeiroparcela_id) {
                    $this->baixas->baixar(
                        (int) $boleto->financeiroparcela_id,
                        $empresaId,
                        (float) ($oc['valor'] ?? $boleto->valor),
                        'cnab',
                        reentregaIdempotente: true,
                    );
                }

                return true;
            });
            $processadas += $processada ? 1 : 0;
        }

        return $processadas;
    }
}
