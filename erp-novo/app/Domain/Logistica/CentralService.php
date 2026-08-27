<?php

namespace App\Domain\Logistica;

use App\Domain\Logistica\Events\PedidoAtribuido;
use App\Domain\Mobile\PushService;
use App\Domain\Pedido\EfeitoPedido;
use App\Models\Logistica\EntregadorBloqueio;
use App\Models\Logistica\Jornada;
use App\Models\Logistica\PedidoAtribuicao;
use App\Models\Mobile\EntregadorPosicao;
use App\Models\Pedido\Pedido;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CentralService (L1) — a Central de Logística. Transforma a tabela `pedidos` numa
 * FILA OPERÁVEL: lista o que precisa sair, atribui/redistribui a entregadores (com
 * trilha de auditoria), bloqueia entregadores e prioriza/reagenda.
 *
 * Toda decisão vive aqui (backend). A atribuição grava `entregador_user_id` +
 * `veiculo_id` no pedido, registra `pedido_atribuicoes`, dispara push ao entregador
 * e emite o evento de tempo real (L2). Manual por padrão (L1); a auto-atribuição
 * (L3) chama `atribuir(..., automatico: true)`.
 */
class CentralService
{
    public function __construct(
        private PushService $push,
    ) {}

    /**
     * Fila de distribuição: pedidos PENDENTE. Por padrão, só os SEM entregador
     * (bandeja), ordenados por urgência e idade (mais antigo primeiro).
     *
     * @param  array{incluir_atribuidos?:bool, setor_id?:int|null}  $filtros
     * @return Collection<int, Pedido>
     */
    public function filaDistribuicao(int $empresaId, array $filtros = []): Collection
    {
        return Pedido::query()
            ->where('empresa_id', $empresaId)
            ->whereHas('situacao', fn ($q) => $q->where('efeito', EfeitoPedido::PENDENTE->value))
            ->when(! ($filtros['incluir_atribuidos'] ?? false), fn ($q) => $q->whereNull('entregador_user_id'))
            ->when($filtros['setor_id'] ?? null, fn ($q, $s) => $q->where('setor_id', $s))
            ->with([
                'cliente:id,nome,endereco,numero,latitude,longitude',
                'situacao:id,descricao,efeito',
                'entregador:id,name',
            ])
            ->orderByDesc('entrega_urgente')
            ->orderBy('datahora')
            ->get();
    }

    /**
     * Entregadores da empresa com o estado logístico: jornada ativa, veículo,
     * última posição (celular) e carga atual (pedidos ativos atribuídos).
     *
     * @return list<array<string,mixed>>
     */
    public function entregadores(int $empresaId): array
    {
        // Entregadores = usuários com jornada (já foram a campo) OU papel de entrega.
        // Aqui usamos as jornadas ativas + qualquer user com pedidos ativos.
        $jornadas = Jornada::query()
            ->where('empresa_id', $empresaId)->where('status', 'ativa')
            ->with(['entregador:id,name', 'veiculo:id,placa,descricao'])
            ->get()->keyBy('entregador_user_id');

        $posicoes = EntregadorPosicao::query()
            ->where('empresa_id', $empresaId)->get()->keyBy('entregador_user_id');

        $carga = $this->cargaPorEntregador($empresaId);
        $bloqueados = $this->entregadoresBloqueados($empresaId);

        return $jornadas->map(function (Jornada $j) use ($posicoes, $carga, $bloqueados) {
            $uid = (int) $j->entregador_user_id;
            $pos = $posicoes->get($uid);

            return [
                'entregador_user_id' => $uid,
                'nome' => $j->entregador?->name,
                'em_servico' => true,
                'jornada_id' => $j->id,
                'veiculo' => $j->veiculo ? ['id' => $j->veiculo->id, 'placa' => $j->veiculo->placa, 'descricao' => $j->veiculo->descricao] : null,
                'posicao' => $pos ? ['lat' => (float) $pos->latitude, 'lng' => (float) $pos->longitude, 'em' => $pos->atualizado_em?->toIso8601String()] : null,
                'carga' => (int) ($carga[$uid] ?? 0),
                'bloqueado' => in_array($uid, $bloqueados, true),
            ];
        })->values()->all();
    }

    /**
     * Atribui o pedido a um entregador (opcionalmente com veículo). Registra a
     * trilha, dispara push e emite o evento de tempo real.
     */
    public function atribuir(Pedido $pedido, int $empresaId, int $entregadorUserId, ?int $veiculoId = null, ?int $operadorUserId = null, bool $automatico = false, ?string $motivo = null): Pedido
    {
        $this->garantirDisponivel($empresaId, $entregadorUserId);

        // Veículo: o informado, senão o da jornada ativa do entregador.
        $veiculoId ??= Jornada::withoutTenant()
            ->where('empresa_id', $empresaId)
            ->where('entregador_user_id', $entregadorUserId)
            ->where('status', 'ativa')
            ->latest('iniciada_em')
            ->value('veiculo_id');

        if ($veiculoId !== null && ! \App\Models\Monitora\Veiculo::withoutTenant()
            ->whereKey($veiculoId)->where('empresa_id', $empresaId)->where('ativo', true)->exists()) {
            throw ValidationException::withMessages(['veiculo_id' => 'Veiculo invalido para a empresa ativa.']);
        }

        [$atualizado, $de] = DB::transaction(function () use ($pedido, $empresaId, $entregadorUserId, $veiculoId, $operadorUserId, $automatico, $motivo) {
            $pedido = Pedido::withoutTenant()
                ->whereKey($pedido->getKey())
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->first();
            if (! $pedido) {
                throw ValidationException::withMessages(['pedido' => 'Pedido invalido para a empresa ativa.']);
            }

            $de = $pedido->entregador_user_id;
            $acao = $de === null ? 'atribuir' : 'redistribuir';
            $pedido->forceFill([
                'entregador_user_id' => $entregadorUserId,
                'veiculo_id' => $veiculoId,
            ])->save();

            PedidoAtribuicao::create([
                'empresa_id' => $pedido->empresa_id,
                'pedido_id' => $pedido->id,
                'de_entregador_user_id' => $de,
                'para_entregador_user_id' => $entregadorUserId,
                'veiculo_id' => $veiculoId,
                'operador_user_id' => $operadorUserId,
                'acao' => $acao,
                'automatico' => $automatico,
                'motivo' => $motivo,
            ]);

            return [$pedido->refresh(), $de];
        });

        // Push ao entregador (fora da transação).
        $this->push->paraUsuario($entregadorUserId, 'Nova entrega', 'Você recebeu um pedido para entrega.', [
            'pedido_id' => (string) $pedido->id, 'acao' => 'novaEntrega',
        ]);

        // Tempo real (L2): a Central e o entregador são notificados.
        PedidoAtribuido::dispatch($atualizado->load('entregador'), $de, $automatico);

        return $atualizado;
    }

    /** Redistribui para outro entregador (troca com histórico e motivo). */
    public function redistribuir(Pedido $pedido, int $empresaId, int $novoEntregadorUserId, ?int $operadorUserId = null, ?string $motivo = null): Pedido
    {
        return $this->atribuir($pedido, $empresaId, $novoEntregadorUserId, null, $operadorUserId, false, $motivo ?? 'Redistribuição manual');
    }

    /** Bloqueia um entregador (não recebe novas atribuições até `ate`). */
    public function bloquearEntregador(int $empresaId, int $entregadorUserId, ?int $operadorUserId, ?string $motivo, ?\DateTimeInterface $ate = null): EntregadorBloqueio
    {
        // Encerra bloqueios ativos anteriores (mantém 1 vigente).
        EntregadorBloqueio::query()
            ->where('empresa_id', $empresaId)
            ->where('entregador_user_id', $entregadorUserId)
            ->where('ativo', true)->update(['ativo' => false]);

        return EntregadorBloqueio::create([
            'empresa_id' => $empresaId,
            'entregador_user_id' => $entregadorUserId,
            'operador_user_id' => $operadorUserId,
            'motivo' => $motivo,
            'ate' => $ate,
            'ativo' => true,
        ]);
    }

    /** Desbloqueia (encerra bloqueios ativos). */
    public function desbloquearEntregador(int $empresaId, int $entregadorUserId): void
    {
        EntregadorBloqueio::query()
            ->where('empresa_id', $empresaId)
            ->where('entregador_user_id', $entregadorUserId)
            ->where('ativo', true)->update(['ativo' => false]);
    }

    /** Marca/desmarca o pedido como urgente (priorização na fila). */
    public function priorizar(Pedido $pedido, bool $urgente = true): Pedido
    {
        $pedido->forceFill(['entrega_urgente' => $urgente])->save();

        return $pedido->refresh();
    }

    /** Reagenda a data/hora prevista do pedido. */
    public function reagendar(Pedido $pedido, \DateTimeInterface $quando): Pedido
    {
        $pedido->forceFill(['datahora' => $quando])->save();

        return $pedido->refresh();
    }

    // ── internos ──

    /** Garante que o entregador está apto (não bloqueado). */
    private function garantirDisponivel(int $empresaId, int $entregadorUserId): void
    {
        $entregador = User::query()->whereKey($entregadorUserId)->where('ativo', true)->first();
        if (! $entregador || ! $entregador->podeAcessarEmpresa($empresaId)) {
            throw ValidationException::withMessages(['entregador' => 'Entregador inválido para a empresa ativa.']);
        }

        if (in_array($entregadorUserId, $this->entregadoresBloqueados($empresaId), true)) {
            throw ValidationException::withMessages(['entregador' => 'Entregador bloqueado para distribuição.']);
        }
    }

    /** @return array<int,int> entregador_user_id => nº de pedidos ativos */
    public function cargaPorEntregador(int $empresaId): array
    {
        return Pedido::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('entregador_user_id')
            ->whereHas('situacao', fn ($q) => $q->where('efeito', EfeitoPedido::PENDENTE->value))
            ->selectRaw('entregador_user_id, count(*) as total')
            ->groupBy('entregador_user_id')
            ->pluck('total', 'entregador_user_id')
            ->map(fn ($v) => (int) $v)->all();
    }

    /** @return list<int> ids de entregadores com bloqueio em vigor. */
    public function entregadoresBloqueados(int $empresaId): array
    {
        return EntregadorBloqueio::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->where(fn ($q) => $q->whereNull('ate')->orWhere('ate', '>', now()))
            ->pluck('entregador_user_id')
            ->map(fn ($v) => (int) $v)->all();
    }
}
