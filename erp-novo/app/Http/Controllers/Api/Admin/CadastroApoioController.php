<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Apoio\CadastroApoioRegistry;
use App\Domain\Apoio\CadastroApoioService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cadastros de apoio (genérico, parametrizado por TIPO) — N1.
 * Contrato consumido pela SPA: GET /cadastros/{tipo}, POST /cadastros/{tipo},
 * PUT/DELETE /cadastros/{tipo}/{id}. Resposta sempre { data: ... }.
 */
class CadastroApoioController extends Controller
{
    public function __construct(
        private CadastroApoioRegistry $registry,
        private CadastroApoioService $service,
    ) {
    }

    public function index(Request $request, string $tipo): JsonResponse
    {
        $cfg = $this->registry->config($tipo);
        $this->autorizar($request, $cfg['modulo'], 'view');

        $rows = $this->service->listar($tipo, trim((string) $request->query('q', '')) ?: null);

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request, string $tipo): JsonResponse
    {
        $cfg = $this->registry->config($tipo);
        $this->autorizar($request, $cfg['modulo'], 'create');

        $dados = $this->validar($request, $cfg['extras']);
        $registro = $this->service->criar($tipo, $dados);

        return response()->json(['data' => $registro], 201);
    }

    public function update(Request $request, string $tipo, int $id): JsonResponse
    {
        $cfg = $this->registry->config($tipo);
        $this->autorizar($request, $cfg['modulo'], 'edit');

        $dados = $this->validar($request, $cfg['extras']);
        $registro = $this->service->atualizar($tipo, $id, $dados);

        return response()->json(['data' => $registro]);
    }

    public function destroy(Request $request, string $tipo, int $id): JsonResponse
    {
        $cfg = $this->registry->config($tipo);
        $this->autorizar($request, $cfg['modulo'], 'delete');

        $this->service->excluir($tipo, $id);

        return response()->json(['message' => 'Registro excluído.']);
    }

    /**
     * Valida descricao + ativo + os extras declarados no registry.
     *
     * @param array<string, string> $extras
     * @return array<string, mixed>
     */
    private function validar(Request $request, array $extras): array
    {
        $regras = array_merge(
            ['descricao' => 'required|string|max:255', 'ativo' => 'nullable|boolean'],
            $extras,
        );

        return $request->validate($regras);
    }

    private function autorizar(Request $request, string $modulo, string $acao): void
    {
        abort_unless(
            $request->user()->temPermissao("{$modulo}.{$acao}"),
            403,
            'Sem permissão.',
        );
    }
}
