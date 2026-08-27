<?php

namespace App\Domain\Cobranca\Drivers;

use App\Domain\Cobranca\Cnab\CnabHelper;
use App\Domain\Cobranca\Cnab\ContaCobranca;
use App\Domain\Cobranca\Contracts\BoletoDriver;
use App\Domain\Cobranca\SituacaoBoleto;
use App\Domain\Shared\NumeroSequencialService;
use App\Models\Cobranca\Boleto;
use Illuminate\Validation\ValidationException;

/**
 * Base dos drivers de boleto REAIS (F08). Implementa o que é comum entre bancos:
 * montagem do código de barras (44) com DV geral, linha digitável e a estrutura de
 * remessa/retorno. Cada banco concreto fornece o "campo livre" (posições 20-44 do
 * código de barras) e o mapa de ocorrências do seu layout CNAB.
 *
 * NÃO depende de lib externa — a matemática FEBRABAN está em CnabHelper.
 */
abstract class CnabDriverBase implements BoletoDriver
{
    /** Moeda 9 (Real) — padrão FEBRABAN. */
    protected const MOEDA = '9';

    abstract public function bancoCodigo(): int;

    /** Campo livre (25 posições) do código de barras, específico do banco. */
    public function __construct(private NumeroSequencialService $sequencias) {}

    abstract protected function campoLivre(Boleto $boleto, ContaCobranca $conta, string $nossoNumero): string;

    /** Nosso número (sem DV) calculado conforme o banco. */
    abstract protected function nossoNumero(int $sequencial, ContaCobranca $conta): string;

    abstract protected function sequencialDoNossoNumero(string $nossoNumero, ContaCobranca $conta): ?int;

    abstract protected function limiteSequencial(): int;

    abstract protected function carteiraNormalizada(ContaCobranca $conta): string;

    /** Uma linha de remessa (CNAB do banco) para o boleto. */
    abstract public function linhaRemessa(Boleto $boleto): string;

    /** Mapa código-de-ocorrência → [descrição, SituacaoBoleto] do layout de RETORNO. */
    abstract protected function mapaOcorrencias(): array;

    public function gerar(Boleto $boleto): array
    {
        $conta = ContaCobranca::daEmpresa((int) $boleto->empresa_id, $this->bancoCodigo());
        $carteira = $this->carteiraNormalizada($conta);
        $maiorExistente = Boleto::withoutTenant()
            ->where('empresa_id', $boleto->empresa_id)
            ->where('banco_codigo', $this->bancoCodigo())
            ->where('carteira', $carteira)
            ->whereNotNull('nosso_numero')
            ->pluck('nosso_numero')
            ->map(fn ($numero) => $this->sequencialDoNossoNumero((string) $numero, $conta))
            ->filter(fn ($numero) => $numero !== null)
            ->max() ?? 0;

        $chave = sprintf(
            'boleto:empresa:%d:banco:%03d:carteira:%s',
            $boleto->empresa_id,
            $this->bancoCodigo(),
            $carteira,
        );
        $sequencial = $this->sequencias->proximo($chave, (int) $maiorExistente);
        if ($sequencial > $this->limiteSequencial()) {
            throw ValidationException::withMessages([
                'nosso_numero' => 'A sequencia de nosso-numero esgotou o limite do banco/carteira.',
            ]);
        }

        $nosso = $this->nossoNumero($sequencial, $conta);
        $codigoBarras = $this->montarCodigoBarras($boleto, $conta, $nosso);

        return [
            'nosso_numero' => $nosso,
            'linha_digitavel' => CnabHelper::linhaDigitavel($codigoBarras),
            'codigo_barras' => $codigoBarras,
            'carteira' => $carteira,
        ];
    }

    /**
     * Código de barras (44): banco(3) moeda(1) DV(1) fator(4) valor(10) campoLivre(25).
     * O DV geral (posição 5) é módulo 11 sobre os 43 dígitos restantes.
     */
    protected function montarCodigoBarras(Boleto $boleto, ContaCobranca $conta, string $nossoNumero): string
    {
        $banco = CnabHelper::numero($this->bancoCodigo(), 3);
        $fator = CnabHelper::fatorVencimento($boleto->vencimento->format('Y-m-d'));
        $valor = CnabHelper::valor((float) $boleto->valor, 10);
        $livre = CnabHelper::numero($this->campoLivre($boleto, $conta, $nossoNumero), 25);

        $semDv = $banco.self::MOEDA.$fator.$valor.$livre; // 43 dígitos
        $dv = CnabHelper::modulo11($semDv, 9, true);

        return $banco.self::MOEDA.$dv.$fator.$valor.$livre;
    }

    public function interpretarRetorno(string $linha): array
    {
        $codigo = $this->codigoOcorrenciaRetorno($linha);
        $valor = $this->valorRetorno($linha);
        [$descricao, $situacao] = $this->mapaOcorrencias()[$codigo]
            ?? ['Ocorrência '.$codigo, SituacaoBoleto::REGISTRADO->value];

        return [
            'codigo' => $codigo,
            'descricao' => $descricao,
            'valor' => $valor,
            'situacao' => $situacao instanceof SituacaoBoleto ? $situacao->value : $situacao,
        ];
    }

    /** Extrai o código de ocorrência da linha de retorno (posição depende do banco). */
    abstract protected function codigoOcorrenciaRetorno(string $linha): string;

    /** Extrai o valor (R$) da linha de retorno, se houver. */
    abstract protected function valorRetorno(string $linha): ?float;
}
