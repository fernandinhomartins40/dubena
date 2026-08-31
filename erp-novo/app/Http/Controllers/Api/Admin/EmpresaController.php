<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Empresa\EnderecoEmpresaSync;
use App\Domain\Saas\LimiteContratado;
use App\Domain\Saas\TransformationFreeze;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmpresaRequest;
use App\Http\Resources\EmpresaResource;
use App\Models\Empresa;
use App\Models\Saas\TenantCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Empresas do grupo (entidade-tenant) — N1.
 * Lista escopada pelo GRUPO do usuário; `ativar` troca o tenant ativo
 * (equivalente ao EmpresaController::change() do legado).
 */
class EmpresaController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private TransformationFreeze $freeze) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->autorizar($request, 'empresa.view');

        $query = $this->doGrupo($request);

        // O seletor do cabeçalho consome esta lista, então ela precisa conter
        // só o que o usuário REALMENTE acessa — mostrar uma filial que ele não
        // pode abrir vira um erro na cara dele. Ver todas é acesso elevado
        // (F2-05): exige break-glass vigente, não mais o flag permanente.
        $user = $request->user();
        if ($user !== null && ! $user->acessoElevado() && method_exists($user, 'empresasVisiveis')) {
            $query->whereIn('id', $user->empresasVisiveis($this->grupoDoUsuario($request)));
        }

        return EmpresaResource::collection($query->orderBy('razao_social')->get());
    }

    public function show(Request $request, int $id): EmpresaResource
    {
        $empresa = $this->empresaAcessivel($request, $id);
        $this->autorizarNaEmpresa($request, 'empresa.view', $id);

        return new EmpresaResource(
            $empresa->load(['cidadeCadastro', 'bairroCadastro', 'rua', 'regiao']),
        );
    }

    public function store(EmpresaRequest $request): JsonResponse
    {
        $this->autorizar($request, 'empresa.create');
        $this->freeze->assertCompanyCreationAllowed();
        // F2-03: teto de unidades do plano contratado (402 se estourou).
        app(LimiteContratado::class)->exigirEspaco('empresas');

        // O texto do endereço é derivado das FKs: a DANFE imprime a string, e
        // sem isto ela sairia vazia mesmo com a cidade escolhida no formulário.
        $empresa = Empresa::create(array_merge(
            app(EnderecoEmpresaSync::class)->aplicar($request->validated()),
            ['grupo_id' => $this->grupoDoUsuario($request)],
        ));

        $this->vincularAoTenantDoAtor($request, $empresa);

        return (new EmpresaResource(
            $empresa->load(['cidadeCadastro', 'bairroCadastro', 'rua', 'regiao']),
        ))->response()->setStatusCode(201);
    }

    public function update(EmpresaRequest $request, int $id): EmpresaResource
    {
        $empresa = $this->empresaAcessivel($request, $id, exigirAtiva: true);
        $this->autorizarNaEmpresa($request, 'empresa.edit', $id);

        $empresa->update(app(EnderecoEmpresaSync::class)->aplicar($request->validated()));

        return new EmpresaResource(
            $empresa->refresh()->load(['cidadeCadastro', 'bairroCadastro', 'rua', 'regiao']),
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $empresa = $this->empresaAcessivel($request, $id, exigirAtiva: true);
        $this->autorizarNaEmpresa($request, 'empresa.delete', $id);

        $empresa->delete();

        return response()->json(['message' => 'Empresa excluída.']);
    }

    /** Troca o tenant ativo para esta empresa (persistido no usuário). */
    public function ativar(Request $request, int $id, TenantContext $tenant): JsonResponse
    {
        $empresa = $this->doGrupo($request)->findOrFail($id);
        $user = $request->user();

        abort_unless($user->podeAcessarEmpresa($empresa->id), 403, 'Sem acesso a esta empresa.');

        // A empresa de ORIGEM vira vínculo permanente antes da troca.
        //
        // `podeAcessarEmpresa` aceita a empresa padrão (`users.empresa_id`) OU
        // uma da pivot. Como esta ação MUDA a padrão, sem gravar o vínculo o
        // usuário perderia o acesso à empresa de onde saiu — ficaria preso na
        // nova, com 403 ao tentar voltar. Regra da plataforma, não configuração
        // de tenant: vale para qualquer rede, presente ou futura.
        $origem = (int) $user->empresa_id;
        if ($origem > 0 && $origem !== (int) $empresa->id) {
            $user->empresas()->syncWithoutDetaching([$origem]);
        }

        $user->forceFill(['empresa_id' => $empresa->id])->save();
        $tenant->set($empresa->id, (int) $empresa->grupo_id);

        return response()->json([
            'message' => 'Empresa ativada.',
            'tenant' => ['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id],
        ]);
    }

    /**
     * A unidade nova entra no MESMO tenant de quem a criou — F2-03.
     *
     * Sem isto ela nascia fora da fronteira SaaS, com duas consequências: não
     * contava no teto do plano (o limite vazava) e o resolver a negava quando o
     * enforcement de tenant liga — o usuário criava a filial e não conseguia
     * usá-la.
     *
     * Não é inferência de titularidade: o tenant vem do ATOR autenticado, e a
     * evidência registra isso. Ator fora da fronteira não cria vínculo nenhum —
     * a empresa fica como estava, e a conversão documental decide depois.
     */
    private function vincularAoTenantDoAtor(Request $request, Empresa $empresa): void
    {
        $origem = TenantCompany::query()
            ->where('empresa_id', $request->user()?->empresa_id)
            ->where('status', TenantCompany::STATUS_APPROVED)
            ->first();

        if ($origem === null) {
            return;
        }

        TenantCompany::query()->create([
            'tenant_account_id' => $origem->tenant_account_id,
            'empresa_id' => $empresa->id,
            'status' => TenantCompany::STATUS_APPROVED,
            'approved_at' => now(),
            'ownership_evidence_ref' => 'api:empresa.store:user:'.$request->user()->id,
        ]);

        $empresa->forceFill(['ownership_status' => Empresa::OWNERSHIP_APPROVED])->save();
    }

    /** Query de empresas restrita ao grupo do usuário (sem escopo de empresa_id). */
    private function doGrupo(Request $request)
    {
        return Empresa::query()->where('grupo_id', $this->grupoDoUsuario($request));
    }

    private function grupoDoUsuario(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->empresa?->grupo_id ?? $user->grupo_id);
    }

    private function empresaAcessivel(Request $request, int $id, bool $exigirAtiva = false): Empresa
    {
        $empresa = $this->doGrupo($request)->findOrFail($id);
        $user = $request->user();

        abort_unless($user !== null && $user->podeAcessarEmpresa($id), 404);

        if ($exigirAtiva && ! $user->acessoElevado($id)) {
            abort_unless(app(TenantContext::class)->empresaId() === $id, 404);
        }

        return $empresa;
    }
}
