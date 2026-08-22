<?php

namespace App\Domain\Missao;

use App\Domain\Cliente\ClienteService;
use App\Domain\Mobile\PedidoMobileService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Domain\Satelite\ValeGasService;
use App\Models\Cliente\Cliente;
use App\Models\Missao\MissaoAtribuicao;
use App\Models\Missao\MissaoVisita;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Satelite\ValeGas;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * VendaCampoService (L8) — converte missão em receita SEM reescrever venda:
 *
 *  - venderGas():   cria um Pedido pelo PedidoService (preço server-side pelo
 *                   preco_venda do produto; nasce CONCLUIDO — entregue na hora,
 *                   baixa estoque + gera financeiro pela máquina de estados) e
 *                   vincula à visita da missão.
 *  - venderValeGas(): emite pelo ValeGasService (financeiro a receber).
 *  - cadastrarCliente(): cadastro rápido pelo ClienteService (geocodifica async).
 *
 * O entregador NUNCA define preço — o servidor precifica.
 */
class VendaCampoService
{
    public function __construct(
        private PedidoService $pedidos,
        private PedidoMobileService $pedidoMobile,
        private ClienteService $clientes,
        private ValeGasService $valeGas,
    ) {}

    /**
     * Venda de gás em campo, entregue na hora.
     *
     * @param  array{cliente_id:int, itens:list<array{produto_id:int, quantidade:float}>,
     *   condicaopagamento_id?:int|null, lat?:float|null, lng?:float|null, observacao?:string|null}  $dados
     */
    public function venderGas(MissaoAtribuicao $atribuicao, MissaoVisita $visita, array $dados, int $entregadorUserId): MissaoVisita
    {
        $empresaId = (int) $atribuicao->empresa_id;
        $grupoId = $this->grupoDa($atribuicao);

        $cliente = Cliente::query()->where('empresa_id', $empresaId)->findOrFail($dados['cliente_id']);

        // Setor por geofence (posição da venda) — mesmo resolvedor do app cliente.
        $setor = $this->pedidoMobile->setorDeEntrega($empresaId, $dados['lat'] ?? null, $dados['lng'] ?? null);
        if (! $setor) {
            throw ValidationException::withMessages(['setor' => 'Nenhum setor de entrega ativo.']);
        }

        return DB::transaction(function () use ($atribuicao, $visita, $dados, $entregadorUserId, $empresaId, $grupoId, $cliente, $setor) {
            // Entregue na hora → nasce CONCLUIDO (baixa estoque + financeiro na criação).
            $pedido = $this->pedidos->criar([
                'empresa_id' => $empresaId,
                'grupo_id' => $grupoId,
                'cliente_id' => $cliente->id,
                'pedidosituacao_id' => $this->situacaoPorEfeito($grupoId, EfeitoPedido::CONCLUIDO)->id,
                'condicaopagamento_id' => $dados['condicaopagamento_id'] ?? null,
                'setor_id' => $setor->id,
                'entregador_user_id' => $entregadorUserId,
                'observacao' => trim('Venda em campo (missão #'.$atribuicao->id.'). '.($dados['observacao'] ?? '')),
                'user_id' => $entregadorUserId,
            ], array_map(fn (array $i) => [
                // SEM preco_unitario: o PedidoService usa o preco_venda do produto
                // (preço 100% server-side — o app não define preço).
                'produto_id' => (int) $i['produto_id'],
                'quantidade' => (float) $i['quantidade'],
            ], $dados['itens']));

            $visita->forceFill(['status' => 'venda', 'cliente_id' => $cliente->id, 'pedido_id' => $pedido->id])->save();

            return $visita->refresh();
        });
    }

    /**
     * Emissão de Vale Gás em campo (reusa o satélite — financeiro a receber).
     *
     * @param  array{cliente_id:int, valor:float}  $dados
     */
    public function venderValeGas(MissaoAtribuicao $atribuicao, MissaoVisita $visita, array $dados): ValeGas
    {
        $cliente = Cliente::query()
            ->where('empresa_id', $atribuicao->empresa_id)
            ->findOrFail($dados['cliente_id']);

        $vale = $this->valeGas->emitir([
            'empresa_id' => $atribuicao->empresa_id,
            'grupo_id' => $this->grupoDa($atribuicao),
            'cliente_id' => $cliente->id,
            'valor' => (float) $dados['valor'],
        ]);

        $visita->forceFill(['status' => 'venda', 'cliente_id' => $cliente->id])->save();

        return $vale;
    }

    /**
     * Cadastro rápido de cliente em campo (prospecção). Geocodifica async; a
     * posição capturada no ato já entra como lat/lng inicial.
     *
     * @param  array{nome:string, endereco?:string|null, numero?:string|null,
     *   cidade_id?:int|null, telefone?:string|null, lat?:float|null, lng?:float|null}  $dados
     */
    public function cadastrarCliente(MissaoAtribuicao $atribuicao, array $dados): Cliente
    {
        // Porta unica de identidade: em prospeccao o mesmo endereco e visitado
        // por entregadores diferentes, e sem isto cada visita virava um cadastro.
        $resultado = app(\App\Domain\Identidade\IdentificarOuCriarCliente::class)->executar(
            (int) $atribuicao->empresa_id,
            $this->grupoDa($atribuicao),
            [
                'nome' => $dados['nome'],
                'endereco' => $dados['endereco'] ?? null,
                'numero' => $dados['numero'] ?? null,
                'cidade_id' => $dados['cidade_id'] ?? null,
                'latitude' => $dados['lat'] ?? null,
                'longitude' => $dados['lng'] ?? null,
                'cliente' => true,
                'telefones' => ! empty($dados['telefone']) ? [['telefone' => $dados['telefone'], 'whatsapp' => true]] : null,
            ],
            'campo',
        );

        return $resultado->cliente;
    }

    private function grupoDa(MissaoAtribuicao $atribuicao): int
    {
        return (int) ($atribuicao->missao?->grupo_id
            ?? \App\Models\Empresa::query()->whereKey($atribuicao->empresa_id)->value('grupo_id'));
    }

    private function situacaoPorEfeito(int $grupoId, EfeitoPedido $efeito): PedidoSituacao
    {
        $situacao = PedidoSituacao::query()
            ->where('grupo_id', $grupoId)
            ->where('efeito', $efeito->value)
            ->where('ativo', true)
            ->orderBy('id')->first();

        if (! $situacao) {
            throw ValidationException::withMessages(['situacao' => "Nenhuma situação de efeito {$efeito->value} configurada."]);
        }

        return $situacao;
    }
}
