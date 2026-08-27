<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Domain\Saas\TransformationFreeze;
use App\Etl\MigratorRegistry;
use App\Http\Controllers\Controller;
use App\Jobs\ExecutarMigracaoJob;
use App\Models\Migracao\Migracao;
use App\Services\Migracao\MigracaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ferramenta de migração de sistemas antigos (SuperAdmin).
 *
 * Fica no SuperAdmin porque a migração é cross-tenant por natureza: lê o banco
 * de um sistema legado inteiro e pode CRIAR empresas. O fluxo é um assistente:
 * criar → conectar → diagnosticar → mapear empresas → executar → conferir.
 */
class MigracaoController extends Controller
{
    public function __construct(
        private MigracaoService $service,
        private TransformationFreeze $freeze,
    ) {}

    /** GET /superadmin/migracoes */
    public function index(): JsonResponse
    {
        $migracoes = Migracao::query()
            ->withCount('descartes')
            ->orderByDesc('id')
            ->limit(100)
            ->get([
                'id', 'descricao', 'origem_tipo', 'status', 'progresso',
                'etapa_atual', 'iniciada_em', 'concluida_em', 'created_at',
            ]);

        return response()->json(['data' => $migracoes]);
    }

    /** GET /superadmin/migracoes/{id} */
    public function show(int $id): JsonResponse
    {
        $migracao = Migracao::withCount('descartes')->findOrFail($id);

        return response()->json(['data' => $migracao]);
    }

    /** POST /superadmin/migracoes — cria e guarda as credenciais (cifradas). */
    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'descricao' => ['required', 'string', 'max:255'],
            'origem_tipo' => ['required', 'string', 'in:'.implode(',', array_keys(MigracaoService::ORIGENS))],
            'config.host' => ['required', 'string', 'max:255'],
            'config.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'config.database' => ['required', 'string', 'max:255'],
            'config.username' => ['required', 'string', 'max:255'],
            'config.password' => ['nullable', 'string', 'max:255'],
            'config.schema' => ['nullable', 'string', 'max:100'],
        ]);

        $migracao = Migracao::create([
            'descricao' => $dados['descricao'],
            'origem_tipo' => $dados['origem_tipo'],
            'config' => $dados['config'],
            'status' => Migracao::STATUS_PENDENTE,
            'platform_admin_id' => $request->user()?->id,
        ]);

        return response()->json(['data' => $migracao->only([
            'id', 'descricao', 'origem_tipo', 'status',
        ])], 201);
    }

    /** POST /superadmin/migracoes/{id}/conectar — testa a conexão. */
    public function conectar(int $id): JsonResponse
    {
        $migracao = Migracao::findOrFail($id);

        try {
            return response()->json(['data' => $this->service->conectar($migracao)]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Não foi possível conectar na origem.',
                'erro' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /superadmin/migracoes/{id}/diagnosticar
     *
     * Conta as entidades e levanta problemas SEM gravar nada. Devolve as
     * empresas da origem para o passo de mapeamento.
     */
    public function diagnosticar(int $id): JsonResponse
    {
        $migracao = Migracao::findOrFail($id);
        $migracao->update(['status' => Migracao::STATUS_DIAGNOSTICANDO]);

        try {
            $diagnostico = $this->service->diagnosticar($migracao);
            $migracao->update([
                'diagnostico' => $diagnostico,
                'status' => Migracao::STATUS_AGUARDANDO_MAPEAMENTO,
            ]);

            return response()->json(['data' => $diagnostico]);
        } catch (\Throwable $e) {
            $migracao->update([
                'status' => Migracao::STATUS_FALHOU,
                'erro' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            return response()->json([
                'message' => 'Falha no diagnóstico.',
                'erro' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * PUT /superadmin/migracoes/{id}/mapeamento
     *
     * Grava a decisão do usuário: para cada empresa da origem, mapear num
     * tenant existente, criar novo, ou ignorar. É o único passo que exige
     * humano — nome de empresa não é chave confiável.
     */
    public function mapeamento(Request $request, int $id): JsonResponse
    {
        $migracao = Migracao::findOrFail($id);

        $dados = $request->validate([
            'mapa' => ['required', 'array', 'min:1'],
            'mapa.*.id_origem' => ['required', 'integer'],
            'mapa.*.acao' => ['required', 'string', 'in:mapear,criar,ignorar'],
            'mapa.*.empresa_id' => ['nullable', 'integer', 'exists:empresas,id'],
        ]);

        foreach ($dados['mapa'] as $linha) {
            if ($linha['acao'] === 'mapear' && empty($linha['empresa_id'])) {
                return response()->json([
                    'message' => 'Ação "mapear" exige o tenant de destino (empresa_id).',
                ], 422);
            }
        }

        $migracao->update(['mapa_empresas' => $dados['mapa']]);

        return response()->json(['data' => ['mapa' => $dados['mapa']]]);
    }

    /** POST /superadmin/migracoes/{id}/executar — enfileira a carga. */
    public function executar(Request $request, int $id): JsonResponse
    {
        $this->freeze->assertMigrationWritesAllowed();

        $migracao = Migracao::findOrFail($id);

        if ($migracao->emAndamento()) {
            return response()->json(['message' => 'Esta migração já está em andamento.'], 409);
        }

        $dados = $request->validate([
            'apenas' => ['nullable', 'array'],
            'apenas.*' => [
                'string',
                'max:50',
                Rule::in(array_map(fn ($m) => $m->nome(), MigratorRegistry::resolved())),
            ],
        ]);

        $migracao->update([
            'status' => Migracao::STATUS_MIGRANDO,
            'progresso' => 0,
            'erro' => null,
        ]);

        ExecutarMigracaoJob::dispatch($migracao->id, $dados['apenas'] ?? []);

        return response()->json(['data' => ['status' => $migracao->status]], 202);
    }

    /** POST /superadmin/migracoes/{id}/simular — dry-run, não grava. */
    public function simular(int $id): JsonResponse
    {
        $migracao = Migracao::findOrFail($id);

        try {
            return response()->json(['data' => $this->service->executar($migracao, [], true)]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Falha na simulação.',
                'erro' => $e->getMessage(),
            ], 422);
        }
    }

    /** GET /superadmin/migracoes/{id}/validar — invariantes origem×destino. */
    public function validar(int $id): JsonResponse
    {
        $migracao = Migracao::findOrFail($id);

        return response()->json(['data' => $this->service->validar($migracao)]);
    }

    /** GET /superadmin/migracoes/{id}/descartes — o que não entrou, e por quê. */
    public function descartes(Request $request, int $id): JsonResponse
    {
        $migracao = Migracao::findOrFail($id);

        $descartes = $migracao->descartes()
            ->when($request->query('entidade'), fn ($q, $e) => $q->where('entidade', $e))
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json($descartes);
    }

    /** GET /superadmin/migracoes/{id}/descartes.csv — para conferência offline. */
    public function descartesCsv(int $id): StreamedResponse
    {
        $migracao = Migracao::findOrFail($id);

        return Response::streamDownload(function () use ($migracao) {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\xEF\xBB\xBF"); // BOM: Excel abre com acento correto
            fputcsv($saida, ['migrador', 'entidade', 'motivo', 'chave_origem', 'dados']);

            $migracao->descartes()->orderBy('id')->chunk(1000, function ($linhas) use ($saida) {
                foreach ($linhas as $d) {
                    fputcsv($saida, [
                        $d->migrador, $d->entidade, $d->motivo, $d->chave_origem,
                        $d->dados !== null ? json_encode($d->dados, JSON_UNESCAPED_UNICODE) : '',
                    ]);
                }
            });

            fclose($saida);
        }, "migracao-{$migracao->id}-descartes.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
