<?php

namespace App\Domain\Cliente;

use App\Domain\Auditoria\RegistroAcao;
use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use Illuminate\Database\Eloquent\Builder;
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
        $this->garantirEnderecoNaoDuplicado($dados);

        return DB::transaction(function () use ($dados) {
            $telefones = $dados['telefones'] ?? null;
            unset($dados['telefones']);

            $cliente = Cliente::create($dados);

            if (is_array($telefones)) {
                $this->sincronizarTelefones($cliente, $telefones);
            }

            GeocodificarClienteJob::dispatch($cliente->id);

            return $cliente->load('telefones');
        });
    }

    /** @param array<string, mixed> $dados */
    public function atualizar(Cliente $cliente, array $dados): Cliente
    {
        $this->garantirEnderecoNaoDuplicado($dados, $cliente->id);
        $enderecoMudou = $this->enderecoMudou($cliente, $dados);

        return DB::transaction(function () use ($cliente, $dados, $enderecoMudou) {
            $telefones = $dados['telefones'] ?? null;
            unset($dados['telefones']);

            $cliente->update($dados);

            if (is_array($telefones)) {
                $this->sincronizarTelefones($cliente, $telefones);
            }

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
     * @param list<array<string, mixed>> $telefones
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
     * @param array<string, mixed> $dados
     */
    private function garantirEnderecoNaoDuplicado(array $dados, ?int $ignorarId = null): void
    {
        if (empty($dados['cidade_id']) || empty($dados['numero'])) {
            return; // sem endereço suficiente, nada a checar
        }

        $existe = Cliente::query()
            ->where('cidade_id', $dados['cidade_id'])
            ->where('numero', $dados['numero'])
            ->when(! empty($dados['bairro_id']), fn (Builder $q) => $q->where('bairro_id', $dados['bairro_id']))
            ->when(! empty($dados['endereco']), fn (Builder $q) => $q->where('endereco', $dados['endereco']))
            ->when($ignorarId, fn (Builder $q) => $q->where('id', '<>', $ignorarId))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'endereco' => 'Já existe um cliente cadastrado neste endereço.',
            ]);
        }
    }

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
}
