<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Cliente\ClienteService;
use App\Domain\Mobile\EnderecoMobileService;
use App\Http\Controllers\Api\Mobile\Concerns\ResolveClienteDoApp;
use App\Http\Controllers\Controller;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteEndereco;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * App do cliente — PERFIL e ENDEREÇOS (B-1): dados do cliente, endereço inline e
 * múltiplos endereços de entrega. Extraído do AppClienteController — mesmas rotas.
 */
class AppPerfilController extends Controller
{
    use ResolveClienteDoApp;

    public function __construct(private EnderecoMobileService $enderecos) {}

    // ── Perfil ────────────────────────────────────────────────────────────────

    /** GET /app/v1/perfil — dados do cliente do token. */
    public function perfil(Request $request): JsonResponse
    {
        $c = $this->clienteDoUsuario($request);
        $c->load('telefones:id,cliente_id,telefone');

        return response()->json(['data' => [
            'id' => $c->id,
            'nome' => $c->nome,
            'cpf' => $c->cpf,
            'email' => $c->email,
            'datanascimento' => $c->datanascimento?->toDateString(),
            'gasdopovo' => (bool) $c->gasdopovo,
            'telefones' => $c->telefones->pluck('telefone'),
        ]]);
    }

    /** PUT /app/v1/perfil — atualiza dados do cliente do token. */
    public function atualizarPerfil(Request $request, ClienteService $clientes): JsonResponse
    {
        $d = $request->validate([
            'nome' => 'sometimes|string|max:160',
            'cpf' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:160',
            'datanascimento' => 'nullable|date',
        ]);

        $cliente = $clientes->atualizar($this->clienteDoUsuario($request), $d);

        return response()->json(['data' => ['id' => $cliente->id, 'nome' => $cliente->nome]]);
    }

    /**
     * DELETE /app/v1/perfil — encerra a conta do cliente e revoga os tokens.
     *
     * DESATIVA em vez de apagar. Apagar era inviável de qualquer forma: com
     * pedido no histórico, pedidos.cliente_id (restrictOnDelete) derrubava o
     * request com erro 500 — o titular pedia a exclusão e recebia falha.
     *
     * A desativação aqui NÃO passa pela trava de pendência financeira: o
     * titular tem direito de encerrar a conta, e o título em aberto continua
     * existindo e cobrável pelo ERP.
     */
    public function excluirConta(Request $request, ClienteService $clientes): JsonResponse
    {
        $cliente = $this->clienteDoUsuario($request);
        $user = $request->user();

        $clientes->encerrarPeloTitular($cliente);
        $user->tokens()->delete(); // revoga o acesso imediatamente

        return response()->json(['data' => ['excluido' => true]]);
    }

    // ── Endereço inline (compat) ────────────────────────────────────────────────

    /** GET /app/v1/perfil/endereco — endereço (inline) do cliente do token. */
    public function obterEndereco(Request $request): JsonResponse
    {
        $cliente = $this->clienteDoUsuario($request);

        return response()->json(['data' => $this->serializarEndereco($cliente)]);
    }

    /** PUT /app/v1/perfil/endereco — atualiza o endereço (inline) do cliente do token. */
    public function atualizarEndereco(Request $request): JsonResponse
    {
        $d = $request->validate([
            'endereco' => 'required|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:120',
            'ponto_referencia' => 'nullable|string|max:160',
            'cep' => 'nullable|string|max:12',
            'uf' => 'nullable|string|max:2',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $cliente = $this->clienteDoUsuario($request);
        $cliente->fill($d)->save();

        return response()->json(['data' => $this->serializarEndereco($cliente->refresh())]);
    }

    /** @return array<string,mixed> */
    private function serializarEndereco(Cliente $c): array
    {
        return [
            'endereco' => $c->endereco,
            'numero' => $c->numero,
            'complemento' => $c->complemento,
            'ponto_referencia' => $c->ponto_referencia,
            'cep' => $c->cep,
            'uf' => $c->uf,
            'latitude' => $c->latitude !== null ? (float) $c->latitude : null,
            'longitude' => $c->longitude !== null ? (float) $c->longitude : null,
        ];
    }

    // ── Múltiplos endereços de entrega (F3b) ──────────────────────────────────

    /** GET /app/v1/enderecos — lista os endereços do cliente do token. */
    public function listarEnderecos(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->enderecos->listar($this->clienteDoUsuario($request))]);
    }

    /** POST /app/v1/enderecos — cria um endereço de entrega. */
    public function criarEndereco(Request $request): JsonResponse
    {
        $d = $this->validarEndereco($request);
        $endereco = $this->enderecos->criar($this->clienteDoUsuario($request), $d);

        return response()->json(['data' => $this->enderecos->serializar($endereco)], 201);
    }

    /** PUT /app/v1/enderecos/{id} — edita um endereço do cliente. */
    public function editarEndereco(Request $request, int $id): JsonResponse
    {
        $endereco = $this->enderecoDoCliente($request, $id);
        $d = $this->validarEndereco($request);

        return response()->json(['data' => $this->enderecos->serializar($this->enderecos->atualizar($endereco, $d))]);
    }

    /** PUT /app/v1/enderecos/{id}/favorito — marca como endereço padrão. */
    public function favoritarEndereco(Request $request, int $id): JsonResponse
    {
        $endereco = $this->enderecoDoCliente($request, $id);

        return response()->json(['data' => $this->enderecos->serializar($this->enderecos->favoritar($endereco))]);
    }

    /** DELETE /app/v1/enderecos/{id} — exclui um endereço do cliente. */
    public function excluirEndereco(Request $request, int $id): JsonResponse
    {
        $this->enderecos->excluir($this->enderecoDoCliente($request, $id));

        return response()->json(['data' => ['id' => $id]]);
    }

    /** @return array<string,mixed> */
    private function validarEndereco(Request $request): array
    {
        return $request->validate([
            'titulo' => 'nullable|string|max:100',
            'endereco' => 'required|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:120',
            'ponto_referencia' => 'nullable|string|max:160',
            'bairro' => 'nullable|string|max:120',
            'cidade' => 'nullable|string|max:120',
            'cep' => 'nullable|string|max:12',
            'uf' => 'nullable|string|max:2',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'favorito' => 'boolean',
        ]);
    }

    /** Resolve o endereço garantindo que pertence ao cliente do token (anti-IDOR). */
    private function enderecoDoCliente(Request $request, int $id): ClienteEndereco
    {
        $cliente = $this->clienteDoUsuario($request);

        return ClienteEndereco::query()
            ->where('cliente_id', $cliente->id)
            ->findOr($id, fn () => abort(404, 'Endereço não localizado.'));
    }
}
