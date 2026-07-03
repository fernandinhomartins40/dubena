<?php

namespace App\Domain\Fiscal;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Financeiro\FinanceiroService;
use App\Models\Fiscal\NfRecebida;
use App\Models\Produto\Produto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use NFePHP\NFe\Common\Standardize;

/**
 * NfEntradaService (C7c) — NF de entrada (recebida).
 *
 * Importa o XML do fornecedor (Standardize, PHP puro — testável no CI), registra
 * a nota + itens; o processamento (entrada de estoque + financeiro a pagar) passa
 * pelos Services existentes para manter os saldos auditáveis.
 */
class NfEntradaService
{
    public function __construct(
        private EstoqueService $estoque,
        private FinanceiroService $financeiro,
    ) {}

    /**
     * Importa um XML de NF-e e grava a NF recebida + itens (sem processar ainda).
     * Casa cada item com um produto existente pelo código, quando possível.
     */
    public function importarXml(int $empresaId, int $grupoId, string $xml): NfRecebida
    {
        $std = $this->parse($xml);
        $inf = $std->NFe->infNFe ?? null;
        if (! $inf) {
            throw ValidationException::withMessages(['xml' => 'XML não é uma NF-e válida.']);
        }

        // Standardize expõe os atributos do nó em ->attributes (Id = "NFe<chave>").
        $id = $inf->attributes->Id ?? ($inf->{'@attributes'}->Id ?? null);
        $chave = $id ? preg_replace('/^NFe/', '', (string) $id) : null;

        if ($chave && NfRecebida::query()->where('chave', $chave)->exists()) {
            throw ValidationException::withMessages(['xml' => 'Esta NF já foi importada (chave duplicada).']);
        }

        return DB::transaction(function () use ($empresaId, $grupoId, $xml, $inf, $chave) {
            $emit = $inf->emit ?? null;
            $total = $inf->total->ICMSTot ?? null;

            $nota = NfRecebida::create([
                'empresa_id' => $empresaId,
                'grupo_id' => $grupoId,
                'chave' => $chave,
                'numero' => (string) ($inf->ide->nNF ?? ''),
                'serie' => (string) ($inf->ide->serie ?? ''),
                'emitente_cnpj' => (string) ($emit->CNPJ ?? ''),
                'emitente_nome' => (string) ($emit->xNome ?? ''),
                'data_emissao' => isset($inf->ide->dhEmi) ? substr((string) $inf->ide->dhEmi, 0, 10) : null,
                'valor_produtos' => (float) ($total->vProd ?? 0),
                'valor_total' => (float) ($total->vNF ?? 0),
                'situacao' => 'importada',
                'xml' => $xml,
            ]);

            foreach ($this->itensDoXml($inf) as $it) {
                // Casamento do item do XML com o produto: por descrição sempre, e por
                // id SÓ quando o código do fornecedor é numérico. Antes comparava
                // `id = cProd` direto — no Postgres (id bigint) um código alfanumérico
                // como "X1" estourava "invalid input syntax for bigint" (Q-6).
                $codigo = (string) ($it['codigo'] ?? '');
                $produto = Produto::query()->where('grupo_id', $grupoId)
                    ->where(function ($q) use ($codigo, $it) {
                        $q->where('descricao', $it['descricao']);
                        if (ctype_digit($codigo)) {
                            $q->orWhere('id', (int) $codigo);
                        }
                    })
                    ->first();

                $nota->itens()->create([
                    'produto_id' => $produto?->id,
                    'codigo_fornecedor' => $it['codigo'],
                    'descricao' => $it['descricao'],
                    'ncm' => $it['ncm'],
                    'cfop' => $it['cfop'],
                    'quantidade' => $it['quantidade'],
                    'valor_unitario' => $it['valor_unitario'],
                    'valor_total' => $it['valor_total'],
                ]);
            }

            return $nota->refresh()->load('itens');
        });
    }

    /**
     * Processa a NF importada: dá ENTRADA no estoque (itens casados com produto)
     * e gera o financeiro A PAGAR ao fornecedor. Idempotente.
     */
    public function processar(NfRecebida $nota, int $setorId): NfRecebida
    {
        if ($nota->movimentou_estoque) {
            return $nota; // já processada
        }

        return DB::transaction(function () use ($nota, $setorId) {
            foreach ($nota->itens as $item) {
                if ($item->produto_id) {
                    $this->estoque->entrada(
                        $setorId,
                        $item->produto_id,
                        (float) $item->quantidade,
                        (float) $item->valor_unitario,
                        'nf-entrada',
                        $nota->id,
                    );
                }
            }

            $financeiro = (float) $nota->valor_total > 0
                ? $this->financeiro->criar([
                    'empresa_id' => $nota->empresa_id,
                    'grupo_id' => $nota->grupo_id,
                    'cliente_id' => $nota->cliente_id,
                    'pagarreceber' => 'P',
                    'descricao' => "NF entrada {$nota->numero} - {$nota->emitente_nome}",
                    'valor' => (float) $nota->valor_total,
                    'origem' => 'nf_entrada',
                    'origem_id' => $nota->id,
                ])
                : null;

            $nota->update([
                'situacao' => 'processada',
                'movimentou_estoque' => true,
                'financeiro_id' => $financeiro?->id,
            ]);

            return $nota->refresh();
        });
    }

    private function parse(string $xml): \stdClass
    {
        try {
            return (new Standardize($xml))->toStd();
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['xml' => 'XML inválido: '.$e->getMessage()]);
        }
    }

    /**
     * Normaliza os itens (det) do XML — det pode ser objeto único ou array.
     *
     * @return list<array<string,mixed>>
     */
    private function itensDoXml(\stdClass $inf): array
    {
        $dets = $inf->det ?? [];
        if (! is_array($dets)) {
            $dets = [$dets];
        }

        $itens = [];
        foreach ($dets as $det) {
            $prod = $det->prod ?? null;
            if (! $prod) {
                continue;
            }
            $itens[] = [
                'codigo' => (string) ($prod->cProd ?? ''),
                'descricao' => (string) ($prod->xProd ?? 'Item'),
                'ncm' => (string) ($prod->NCM ?? ''),
                'cfop' => (string) ($prod->CFOP ?? ''),
                'quantidade' => (float) ($prod->qCom ?? 0),
                'valor_unitario' => (float) ($prod->vUnCom ?? 0),
                'valor_total' => (float) ($prod->vProd ?? 0),
            ];
        }

        return $itens;
    }
}
