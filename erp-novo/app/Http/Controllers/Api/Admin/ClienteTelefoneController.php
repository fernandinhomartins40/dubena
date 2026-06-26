<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Cliente\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Telefones do cliente (1:N) — N2. Escopo garantido pela relação do cliente
 * (que já é escopado por empresa via BelongsToTenant).
 */
class ClienteTelefoneController extends Controller
{
    use AutorizaPorPermissao;

    public function index(Request $request, int $clienteId): JsonResponse
    {
        $this->autorizar($request, 'cliente.view');
        $cliente = Cliente::query()->findOrFail($clienteId);

        return response()->json(['data' => $cliente->telefones()->get()]);
    }

    public function store(Request $request, int $clienteId): JsonResponse
    {
        $this->autorizar($request, 'cliente.edit');
        $cliente = Cliente::query()->findOrFail($clienteId);

        $dados = $request->validate([
            'telefone' => 'required|string|max:30',
            'whatsapp' => 'nullable|boolean',
            'telefonetipo_id' => 'nullable|integer|exists:telefonetipos,id',
        ]);

        $tel = $cliente->telefones()->create($dados);

        return response()->json(['data' => $tel], 201);
    }

    public function destroy(Request $request, int $clienteId, int $telId): JsonResponse
    {
        $this->autorizar($request, 'cliente.edit');
        $cliente = Cliente::query()->findOrFail($clienteId);
        $cliente->telefones()->whereKey($telId)->delete();

        return response()->json(['message' => 'Telefone excluído.']);
    }
}
