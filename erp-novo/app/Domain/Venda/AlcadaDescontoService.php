<?php

namespace App\Domain\Venda;

use App\Models\Rh\Colaborador;
use App\Models\User;
use App\Models\Venda\AlcadaDesconto;
use Illuminate\Support\Carbon;

/**
 * Alçada de desconto (F2) — quanto cada um pode tirar do preço, e o que acontece
 * quando pede mais.
 *
 * **O problema que isto resolve.** No legado não existe teto: `PedidoFragment2.java:80`
 * libera o campo de preço em pedido novo, e o backend do NFWEB aceita o valor que
 * o app mandar (`MobileRepository::getPreco:602` — `if ($isAppNf) return $preco`).
 * Busca por alçada no ctrl-web e no erp-novo não retorna nada. Ou seja: hoje
 * qualquer vendedor ou franqueado zera a margem sem que ninguém aprove.
 *
 * **Fail-closed.** Sem regra cadastrada, o teto é ZERO — não "sem limite". É o
 * que o CLAUDE.md manda para dinheiro, e é o comportamento seguro: uma empresa
 * que ainda não configurou alçada não deve ficar exposta.
 *
 * **A base de cálculo importa.** O legado tem três caminhos de preço: tabela,
 * preço especial do cliente e convênio. Se o teto incidisse sempre sobre o preço
 * cheio, um cliente com preço especial acumularia desconto sobre desconto. Por
 * isso `base_calculo` decide se o percentual corre sobre o `preco_venda` do
 * produto ou sobre o preço já praticado no item.
 */
class AlcadaDescontoService
{
    /**
     * Teto em REAIS para um item, e o que fazer se o pedido passar disso.
     *
     * @param  array{produto_id:int,quantidade:float,preco_unitario?:float,setor_id?:int,condicaopagamento_id?:int}  $item
     * @return array{teto: float, permite_solicitar: bool, regra_id: int|null}
     */
    public function tetoDoItem(?User $usuario, array $item, float $precoTabela): array
    {
        $regra = $this->escolher($usuario, $item);

        // Fail-closed: sem regra, sem desconto.
        if ($regra === null) {
            return ['teto' => 0.0, 'permite_solicitar' => true, 'regra_id' => null];
        }

        $qtd = (float) ($item['quantidade'] ?? 0);
        $praticado = isset($item['preco_unitario']) ? (float) $item['preco_unitario'] : $precoTabela;

        // A base decide sobre o que o percentual corre (ver cabeçalho).
        $base = $regra->base_calculo === 'praticado' ? $praticado : $precoTabela;
        $bruto = $base * $qtd;

        $tetoPercentual = $bruto * ((float) $regra->percentual_max / 100);

        // Os dois tetos coexistem: vence o menor, porque cada um é um limite.
        $teto = $regra->valor_max !== null
            ? min($tetoPercentual, (float) $regra->valor_max)
            : $tetoPercentual;

        return [
            'teto' => round(max($teto, 0.0), 2),
            'permite_solicitar' => (bool) $regra->permite_solicitar,
            'regra_id' => $regra->id,
        ];
    }

    /**
     * A regra mais específica que se aplica, ou null.
     *
     * Precedência por especificidade (colaborador nominal > papel; produto >
     * setor). Empate resolve pela mais recente — a última cadastrada é a que o
     * negócio quis dizer.
     */
    public function escolher(?User $usuario, array $item): ?AlcadaDesconto
    {
        $hoje = Carbon::today();
        $colaboradorId = $this->colaboradorDe($usuario);
        $papeisIds = $usuario?->roles?->pluck('id')->all() ?? [];

        $candidatas = AlcadaDesconto::query()
            ->where('ativo', true)
            ->where(fn ($q) => $q->whereNull('data_inicio')->orWhere('data_inicio', '<=', $hoje))
            ->where(fn ($q) => $q->whereNull('data_fim')->orWhere('data_fim', '>=', $hoje))
            // Regra do sujeito: nominal, do papel dele, ou geral da empresa.
            ->where(function ($q) use ($colaboradorId, $papeisIds) {
                $q->whereNull('colaborador_id')->whereNull('role_id');
                if ($colaboradorId !== null) {
                    $q->orWhere('colaborador_id', $colaboradorId);
                }
                if ($papeisIds !== []) {
                    $q->orWhereIn('role_id', $papeisIds);
                }
            })
            // Regra do objeto: null = "vale para todos", não "nenhum".
            ->where(fn ($q) => $q->whereNull('produto_id')->orWhere('produto_id', $item['produto_id'] ?? 0))
            ->where(fn ($q) => $q->whereNull('setor_id')->orWhere('setor_id', $item['setor_id'] ?? 0))
            ->where(fn ($q) => $q->whereNull('condicaopagamento_id')
                ->orWhere('condicaopagamento_id', $item['condicaopagamento_id'] ?? 0))
            ->get();

        if ($candidatas->isEmpty()) {
            return null;
        }

        return $candidatas
            ->sortByDesc(fn (AlcadaDesconto $a) => [$a->especificidade(), $a->id])
            ->first();
    }

    /**
     * O colaborador por trás do usuário — a alçada é da pessoa, não do login.
     * O vínculo mora em `colaboradores.user_id` (a tabela `users` não guarda o
     * caminho inverso), então a busca é sempre por ali.
     */
    private function colaboradorDe(?User $usuario): ?int
    {
        if ($usuario === null) {
            return null;
        }

        $id = Colaborador::query()->where('user_id', $usuario->id)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
