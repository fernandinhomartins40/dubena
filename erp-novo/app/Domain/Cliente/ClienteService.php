<?php

namespace App\Domain\Cliente;

use App\Domain\Auditoria\RegistroAcao;
use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClientePapel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Regra de negócio do Cliente (sem HTTP). O controller valida (Form Request),
 * chama estes métodos e devolve Resource.
 *
 * - payload com sub-relações ANINHADAS (telefones[]) — substitui arrays posicionais;
 * - anti-duplicidade de endereço por QUERY PARAMETRIZADA (sem SQLi do legado);
 * - geocoding ASSÍNCRONO via Job (não trava o request).
 */
class ClienteService
{
    /** @param array<string, mixed> $dados */
    public function criar(array $dados): Cliente
    {
        return DB::transaction(function () use ($dados) {
            $telefones = $dados['telefones'] ?? null;
            unset($dados['telefones']);

            $cliente = Cliente::create($dados);

            if (is_array($telefones)) {
                $this->sincronizarTelefones($cliente, $telefones);
            }

            $this->sincronizarPapeis($cliente);

            GeocodificarClienteJob::dispatch($cliente->id);

            return $cliente->load('telefones');
        });
    }

    /** @param array<string, mixed> $dados */
    public function atualizar(Cliente $cliente, array $dados): Cliente
    {
        $enderecoMudou = $this->enderecoMudou($cliente, $dados);

        return DB::transaction(function () use ($cliente, $dados, $enderecoMudou) {
            $telefones = $dados['telefones'] ?? null;
            unset($dados['telefones']);

            $cliente->update($dados);

            if (is_array($telefones)) {
                $this->sincronizarTelefones($cliente, $telefones);
            }

            $this->sincronizarPapeis($cliente);

            if ($enderecoMudou) {
                GeocodificarClienteJob::dispatch($cliente->id);
            }

            return $cliente->refresh()->load('telefones');
        });
    }

    /**
     * "Excluir" um cliente é DESATIVÁ-LO, nunca apagá-lo.
     *
     * O delete físico anterior era inviável nos dois sentidos: com pedido, o
     * Postgres recusava (pedidos.cliente_id é restrictOnDelete) e o operador
     * contornava renomeando o cadastro para "FULANO - EXCLUIDO" — perdendo o
     * nome real; sem pedido, o registro sumia levando telefones, endereços,
     * interações e convênio por cascade, e desvinculava silenciosamente
     * títulos financeiros e notas fiscais (nullOnDelete).
     *
     * @throws ValidationException se houver pedido em aberto ou parcela a receber
     */
    public function desativar(Cliente $cliente, ?string $motivo = null, ?int $usuarioId = null): Cliente
    {
        if ($cliente->ativo === false) {
            throw ValidationException::withMessages([
                'cliente' => 'Este cliente já está desativado.',
            ]);
        }

        $this->garantirSemPendencia($cliente);

        $cliente->forceFill([
            'ativo' => false,
            'desativado_em' => now(),
            'desativado_por' => $usuarioId,
            'motivo_desativacao' => $motivo,
        ])->save();

        // Ação semântica ALÉM do update automático do trait: sem ela, desativar
        // e corrigir o CEP viram a mesma linha "clientes/atualizado" na trilha.
        app(RegistroAcao::class)->registrar($cliente, 'desativou', $motivo);

        return $cliente->refresh();
    }

    /**
     * Encerramento pedido pelo PRÓPRIO titular no app (LGPD).
     *
     * Sem a trava de pendência: o titular tem direito de encerrar a conta, e o
     * título em aberto segue existindo e cobrável pelo ERP. O vínculo com o
     * usuário do app é cortado (user_id = null) para que um cadastro futuro com
     * o mesmo telefone não caia neste registro encerrado.
     */
    public function encerrarPeloTitular(Cliente $cliente): Cliente
    {
        $cliente->forceFill([
            'ativo' => false,
            'user_id' => null,
            'desativado_em' => now(),
            'motivo_desativacao' => 'Conta encerrada pelo titular no aplicativo.',
        ])->save();

        // Verbo próprio: encerramento pelo TITULAR não é decisão do operador, e
        // a trilha precisa distinguir os dois (um é LGPD, o outro é gestão).
        app(RegistroAcao::class)->registrar($cliente, 'encerrou_conta', 'Solicitado pelo titular no aplicativo.');

        return $cliente->refresh();
    }

    /**
     * Devolve o cliente à lista de ativos, limpando a trilha de desativação —
     * o cadastro volta a ser indistinguível de um que nunca saiu.
     */
    public function reativar(Cliente $cliente): Cliente
    {
        // O motivo da desativação some do cadastro ao reativar; guardá-lo na
        // trilha é o que preserva por que ele tinha saído da lista.
        $motivoAnterior = $cliente->motivo_desativacao;

        $cliente->forceFill([
            'ativo' => true,
            'desativado_em' => null,
            'desativado_por' => null,
            'motivo_desativacao' => null,
        ])->save();

        app(RegistroAcao::class)->registrar($cliente, 'reativou', null, [
            'desativado_antes_por_motivo' => $motivoAnterior,
        ]);

        return $cliente->refresh();
    }

    /**
     * Recusa a desativação enquanto houver pedido em aberto ou parcela a
     * receber em aberto.
     *
     * Sem esta trava a desativação vira um jeito de sumir com uma dívida: o
     * cliente sai da lista padrão e o cobrador deixa de vê-lo na rotina.
     */
    private function garantirSemPendencia(Cliente $cliente): void
    {
        $pedidosAbertos = DB::table('pedidos')
            ->join('pedidosituacoes', 'pedidosituacoes.id', '=', 'pedidos.pedidosituacao_id')
            ->where('pedidos.cliente_id', $cliente->id)
            ->where('pedidosituacoes.efeito', EfeitoPedido::PENDENTE->value)
            ->count();

        // Só título A RECEBER ('R') conta: um título a pagar é dívida NOSSA com
        // ele (fornecedor), e não impede tirá-lo da lista de clientes.
        $parcelasAbertas = DB::table('financeiroparcelas')
            ->join('financeiros', 'financeiros.id', '=', 'financeiroparcelas.financeiro_id')
            ->where('financeiros.cliente_id', $cliente->id)
            ->where('financeiros.pagarreceber', 'R')
            ->where('financeiros.cancelado', false)
            ->where('financeiroparcelas.baixado', false)
            ->count();

        if ($pedidosAbertos === 0 && $parcelasAbertas === 0) {
            return;
        }

        $partes = [];
        if ($pedidosAbertos > 0) {
            $partes[] = $pedidosAbertos.' pedido(s) em aberto';
        }
        if ($parcelasAbertas > 0) {
            $partes[] = $parcelasAbertas.' parcela(s) a receber em aberto';
        }

        throw ValidationException::withMessages([
            'cliente' => 'Não é possível desativar: o cliente tem '.implode(' e ', $partes)
                .'. Conclua ou cancele antes de desativar.',
        ]);
    }

    /**
     * Substitui o conjunto de telefones do cliente pelo informado.
     *
     * @param  list<array<string, mixed>>  $telefones
     */
    private function sincronizarTelefones(Cliente $cliente, array $telefones): void
    {
        $cliente->telefones()->delete();
        foreach ($telefones as $tel) {
            if (empty($tel['telefone'])) {
                continue;
            }
            $cliente->telefones()->create([
                'telefone' => $tel['telefone'],
                'whatsapp' => (bool) ($tel['whatsapp'] ?? false),
                'telefonetipo_id' => $tel['telefonetipo_id'] ?? null,
            ]);
        }
    }

    /**
     * Bloqueia cadastro de cliente com MESMO endereço (numero+cidade+bairro) na
     * empresa — query parametrizada (corrige a SQLi por concatenação do legado).
     *
     * @param  array<string, mixed>  $dados
     */
    /**
     * A TRAVA DE ENDEREÇO DUPLICADO FOI REMOVIDA (lançava ValidationException).
     *
     * Ela recusava o cadastro quando cidade + número (+ bairro/logradouro) já
     * existiam. O problema: prédio, vila e condomínio têm DEZENAS de clientes
     * legítimos no mesmo endereço — a trava bloqueava venda real, e o operador
     * contornava alterando o número ou o logradouro, corrompendo o endereço de
     * entrega para poder concluir a venda.
     *
     * Endereço repetido agora é SINAL, não barreira: vale 25 pontos no motor de
     * identidade (`PesoTraco::ENDERECO`), que sozinho não alcança nem a faixa de
     * revisão. Só quando somado a nome idêntico ou telefone é que aponta
     * duplicata — e aí o par vai para decisão humana, sem travar ninguém.
     */

    /** @param array<string, mixed> $dados */
    private function enderecoMudou(Cliente $cliente, array $dados): bool
    {
        foreach (['endereco', 'numero', 'cidade_id', 'bairro_id', 'cep'] as $campo) {
            if (array_key_exists($campo, $dados) && (string) $dados[$campo] !== (string) $cliente->{$campo}) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mantem `cliente_papeis` em sincronia com os booleanos (F3-01).
     *
     * As duas fontes convivem por enquanto: os booleanos ainda sao escritos pelo
     * formulario e lidos pelo `ClienteResource` e pelo ETL. Enquanto isso durar,
     * escrever so numa delas faria a outra mentir.
     *
     * Marcar um papel ABRE uma vigencia; desmarcar ENCERRA a vigente com a data
     * de hoje — nao apaga a linha. E essa a diferenca que a tabela existe para
     * fazer: um fornecedor que deixou de fornecer sai da lista de hoje sem que
     * as notas de entrada antigas passem a apontar para alguem que "nunca foi
     * fornecedor".
     */
    private function sincronizarPapeis(Cliente $cliente): void
    {
        foreach (PapelPessoa::cases() as $papel) {
            $marcado = (bool) $cliente->{$papel->colunaLegada()};

            $vigente = ClientePapel::query()
                ->where('cliente_id', $cliente->id)
                ->where('papel', $papel->value)
                ->whereNull('fim')
                ->first();

            if ($marcado && $vigente === null) {
                ClientePapel::create([
                    'empresa_id' => $cliente->empresa_id,
                    'grupo_id' => $cliente->grupo_id,
                    'tenant_account_id' => $cliente->tenant_account_id,
                    'cliente_id' => $cliente->id,
                    'papel' => $papel->value,
                    'inicio' => now()->toDateString(),
                ]);

                continue;
            }

            if (! $marcado && $vigente !== null) {
                $vigente->update(['fim' => now()->toDateString()]);
            }
        }
    }
}
