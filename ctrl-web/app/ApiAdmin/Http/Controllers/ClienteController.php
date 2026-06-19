<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * S2 (SPA React) — API admin do CLIENTE (página completa).
 *
 * Escopada pela empresa do usuário (support vê todas). Lista paginada/filtrada
 * server-side (corrige o IDOR/`User::all` do legado e o "só lista após buscar").
 * Reusa o model legado App\Cliente; permissões via RBAC (cliente.*).
 */
class ClienteController extends Controller
{
    private function escopo($query, Request $request)
    {
        $user = $request->user();
        if ((string) $user->support === '1') {
            return $query;
        }
        return $query->where('empresa_id', $user->empresa_id);
    }

    /** GET /api/admin/clientes?q=&page=&per_page= */
    public function index(Request $request)
    {
        $this->autorizar($request, 'cliente.view');

        $q = trim((string) $request->query('q', ''));
        $perPage = min((int) $request->query('per_page', 20), 100);

        $query = $this->escopo(Cliente::query(), $request)
            ->select('id', 'nome', 'fantasia', 'cpf', 'cnpj', 'email', 'cidade_id', 'uf', 'cliente', 'fornecedor', 'ativo');

        if ($q !== '') {
            $like = '%' . str_replace(' ', '%', $q) . '%';
            $query->where(function ($w) use ($like, $q) {
                $w->where('nome', 'ilike', $like)
                  ->orWhere('fantasia', 'ilike', $like)
                  ->orWhere('cpf', 'ilike', $like)
                  ->orWhere('cnpj', 'ilike', $like);
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q);
                }
            });
        }

        // Ordena por nome E id (desempate) — evita parecer "repetido" quando há
        // muitos homônimos; cada linha é um registro distinto.
        $page = $query->orderBy('nome')->orderBy('id')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    /** GET /api/admin/clientes/{id} */
    public function show(Request $request, $id)
    {
        $this->autorizar($request, 'cliente.view');
        $cliente = $this->escopo(Cliente::query(), $request)->findOrFail($id);

        // Labels p/ os selects assíncronos (cidade/bairro) no modo edição.
        $extra = [
            'cidade_label' => optional(\App\Cidade::find($cliente->cidade_id))->descricao,
            'bairro_label' => $cliente->bairro_id ? optional(\App\Bairro::find($cliente->bairro_id))->descricao : null,
            'rua_label'    => $cliente->rua_id ? optional(\App\Rua::find($cliente->rua_id))->descricao : null,
            'segmento_label'   => $cliente->segmento_id ? optional(\App\Segmento::find($cliente->segmento_id))->descricao : null,
            'tipopessoa_label' => $cliente->tipopessoa_id ? optional(\App\Tipopessoa::find($cliente->tipopessoa_id))->descricao : null,
        ];

        return response()->json(['data' => array_merge($cliente->toArray(), $extra)]);
    }

    /** POST /api/admin/clientes */
    public function store(Request $request)
    {
        $this->autorizar($request, 'cliente.create');
        $data = $this->validar($request);

        $user = $request->user();
        $data = $this->normalizarFlags($data);
        $data['empresa_id'] = $user->empresa_id;
        $data['grupo_id']   = optional($user->empresa)->grupo_id;
        $data['user_id']    = $user->id;
        $data += $this->defaults();

        $cliente = DB::transaction(fn () => Cliente::create($data));
        return response()->json(['data' => $cliente], 201);
    }

    /** PUT /api/admin/clientes/{id} */
    public function update(Request $request, $id)
    {
        $this->autorizar($request, 'cliente.edit');
        $cliente = $this->escopo(Cliente::query(), $request)->findOrFail($id);
        $data = $this->normalizarFlags($this->validar($request));

        DB::transaction(fn () => $cliente->update($data));
        return response()->json(['data' => $cliente->fresh()]);
    }

    /** Flags do React vêm como bool; as colunas são smallint (0/1). */
    private function normalizarFlags(array $data): array
    {
        foreach (['cliente', 'fornecedor', 'transportador', 'simples', 'ativo', 'nfemite', 'gasdopovo'] as $f) {
            if (array_key_exists($f, $data)) {
                $data[$f] = ! empty($data[$f]) ? 1 : 0;
            }
        }
        return $data;
    }

    /** DELETE /api/admin/clientes/{id} */
    public function destroy(Request $request, $id)
    {
        $this->autorizar($request, 'cliente.delete');
        $cliente = $this->escopo(Cliente::query(), $request)->findOrFail($id);
        DB::transaction(fn () => $cliente->delete());
        return response()->json(['message' => 'Cliente excluído.']);
    }

    /** GET /api/admin/clientes/{id}/telefones */
    public function telefones(Request $request, $id)
    {
        $this->autorizar($request, 'cliente.view');
        $cliente = $this->escopo(Cliente::query(), $request)->findOrFail($id);

        $tels = DB::table('clientetelefones')
            ->where('cliente_id', $cliente->id)
            ->orderBy('id')
            ->get(['id', 'telefone', 'whatsapp', 'telefonetipo_id']);

        return response()->json(['data' => $tels]);
    }

    /** POST /api/admin/clientes/{id}/telefones */
    public function addTelefone(Request $request, $id)
    {
        $this->autorizar($request, 'cliente.edit');
        $cliente = $this->escopo(Cliente::query(), $request)->findOrFail($id);

        $data = $request->validate([
            'telefone'        => 'required|string|max:20',
            'whatsapp'        => 'nullable|boolean',
            'telefonetipo_id' => 'required|integer',
        ]);

        $novoId = DB::table('clientetelefones')->insertGetId([
            'cliente_id'      => $cliente->id,
            'grupo_id'        => $cliente->grupo_id,
            'empresa_id'      => $cliente->empresa_id,
            'telefone'        => $data['telefone'],
            'whatsapp'        => (int) ($data['whatsapp'] ?? 0),
            'telefonetipo_id' => $data['telefonetipo_id'],
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json(['id' => $novoId], 201);
    }

    /** DELETE /api/admin/clientes/{id}/telefones/{telId} */
    public function delTelefone(Request $request, $id, $telId)
    {
        $this->autorizar($request, 'cliente.edit');
        $cliente = $this->escopo(Cliente::query(), $request)->findOrFail($id);

        DB::table('clientetelefones')
            ->where('cliente_id', $cliente->id)
            ->where('id', $telId)
            ->delete();

        return response()->json(['message' => 'Telefone removido.']);
    }

    private function validar(Request $request): array
    {
        // Paridade com o ClienteRequest legado (SPEC_CLIENTE §4): todos os campos
        // das abas Dados/Endereço/Fiscal. Sub-recursos (telefones/contatos/convênio/
        // preços) têm endpoints próprios.
        return $request->validate([
            // Dados gerais
            'nome'           => 'required|string|min:3|max:255',
            'fantasia'       => 'nullable|string|max:255',
            'tipopessoa_id'  => 'nullable|integer',
            'segmento_id'    => 'nullable|integer',
            'sexo'           => 'nullable|string|max:1',
            'datanascimento' => 'nullable|date',
            'observacoes'    => 'nullable|string',
            // Fiscal / documentos
            'cpf'            => 'nullable|string|max:14',
            'rg'             => 'nullable|string|max:20',
            'cnpj'           => 'nullable|string|max:18',
            'inscricao_estadual' => 'nullable|string|max:30',
            'indicador_ie'   => 'nullable|integer',
            'suframa'        => 'nullable|string|max:9',
            'consisa_id'     => 'nullable|string|max:30',
            // Flags
            'cliente'        => 'nullable|boolean',
            'fornecedor'     => 'nullable|boolean',
            'transportador'  => 'nullable|boolean',
            'simples'        => 'nullable|boolean',
            'ativo'          => 'nullable|boolean',
            'nfemite'        => 'nullable|boolean',
            'gasdopovo'      => 'nullable|boolean',
            // Endereço
            'numero'         => 'required|string|max:10',
            'cidade_id'      => 'required|integer',
            'bairro_id'      => 'nullable|integer',
            'rua_id'         => 'nullable|integer',
            'uf'             => 'nullable|string|max:2',
            'cep'            => 'nullable|string|max:9',
            'complemento'    => 'nullable|string|max:255',
            'ponto_referencia' => 'nullable|string|max:255',
            'email'          => 'nullable|email|max:255',
        ]);
    }

    /** Defaults dos campos NOT NULL que o form não cobre (paridade c/ legado). */
    private function defaults(): array
    {
        return [
            'observacoes'    => '',
            'consumidor_final' => 0,
            'simples'        => 0,
            'fornecedor'     => 0,
            'cliente'        => 1,
            'transportador'  => 0,
            'conveniolimite' => 0,
            'latitude'       => 0,
            'longitude'      => 0,
            'locationtype'   => 'APPROXIMATE',
            'nfemite'        => 0,
            'convenio'       => 0,
            'ativo'          => 1,
        ];
    }

    private function autorizar(Request $request, string $permissao): void
    {
        $user = $request->user();
        [$modulo, $acao] = explode('.', $permissao);
        abort_unless($user->podeRecurso($modulo, $acao), 403, 'Sem permissão.');
    }
}
