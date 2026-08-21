<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Venda\CargaFranqueadoService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Rh\Colaborador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Carga e prestação de contas de mercadoria do franqueado (F5).
 *
 * Quem opera é o depósito/central, não o próprio franqueado: entregar mercadoria
 * a si mesmo derrubaria a conferência. Por isso as rotas ficam no admin, sob
 * `estoque.edit` — quem movimenta estoque já tem essa permissão.
 *
 * O saldo em poder (`emPoder`) é a base do acerto: no fim do turno, o que saiu
 * menos o que voltou menos o que virou venda tem de fechar.
 */
class CargaFranqueadoController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private CargaFranqueadoService $carga) {}

    /** GET /franqueados/{id}/estoque — o que está com ele agora. */
    public function emPoder(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'estoque.view');
        $colaborador = $this->localizar($request, $id);

        return response()->json([
            'data' => [
                'colaborador' => ['id' => $colaborador->id, 'nome' => $colaborador->nome],
                'modo_estoque' => $colaborador->modo_estoque?->value,
                'itens' => $this->carga->emPoder($colaborador),
            ],
        ]);
    }

    /** POST /franqueados/{id}/carga — entrega mercadoria. */
    public function carregar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $this->validarItens($request);

        $resultado = $this->carga->carregar(
            $this->localizar($request, $id),
            (int) $d['setor_origem_id'],
            $d['itens'],
            $request->user()->id,
        );

        return response()->json(['data' => $resultado], 201);
    }

    /** POST /franqueados/{id}/devolucao — recebe de volta o que sobrou. */
    public function devolver(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $this->validarItens($request);

        $resultado = $this->carga->devolver(
            $this->localizar($request, $id),
            (int) $d['setor_origem_id'],
            $d['itens'],
            $request->user()->id,
        );

        return response()->json(['data' => $resultado]);
    }

    /** @return array{setor_origem_id:int, itens:list<array{produto_id:int,quantidade:float}>} */
    private function validarItens(Request $request): array
    {
        /** @var array{setor_origem_id:int, itens:list<array{produto_id:int,quantidade:float}>} $d */
        $d = $request->validate([
            // Na devolução este é o setor de DESTINO (o depósito). Mesmo campo
            // porque, dos dois lados, é sempre "o depósito da empresa".
            'setor_origem_id' => 'required|integer|exists:setores,id',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer|exists:produtos,id',
            'itens.*.quantidade' => 'required|numeric|gt:0',
        ]);

        return $d;
    }

    /** Anti-IDOR: o colaborador tem de ser da empresa do token. */
    private function localizar(Request $request, int $id): Colaborador
    {
        return Colaborador::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->findOrFail($id);
    }
}
