<?php

namespace App\Domain\Mobile;

use App\Domain\Cliente\ClienteService;
use App\Domain\Identidade\IdentificarOuCriarCliente;
use App\Domain\Mobile\Contracts\FirebaseVerifier;
use App\Domain\Tenant\ConexaoDeOwner;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Saas\TenantCompany;
use App\Models\Saas\TenantCompanyGrant;
use App\Models\Saas\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ClienteAuthService (F1) — login real do CLIENTE pelo app.
 *
 * Fluxo: o app faz o phone-auth no Firebase (SMS) e envia o ID token. Aqui:
 *  1) verificamos o token (FirebaseVerifier — fake no CI, kreait em prod) e extraímos o telefone;
 *  2) resolvemos o cliente DENTRO da empresa informada (telefone não é único entre tenants);
 *  3) garantimos um User vinculado ao cliente (cliente.user_id), com empresa/grupo do cliente;
 *  4) o controller emite o token Sanctum e registra o device.
 *
 * Substitui o token-mestre via app_key do legado: cada cliente passa a ter identidade própria.
 */
class ClienteAuthService
{
    public function __construct(
        private FirebaseVerifier $firebase,
        private ClienteService $clientes,
    ) {}

    /**
     * Autentica o cliente e devolve o User (criando/vinculando se necessário).
     *
     * @param  array{firebase_id_token:string, empresa_id:int}  $dados
     */
    public function autenticar(array $dados): User
    {
        $verificado = $this->firebase->verify($dados['firebase_id_token']);
        $telefone = $verificado['phone'] ?? null;

        if (! $telefone) {
            throw ValidationException::withMessages(['telefone' => 'Token sem telefone verificado.']);
        }

        $cliente = $this->clientePorTelefone((int) $dados['empresa_id'], $telefone);
        if (! $cliente) {
            throw ValidationException::withMessages([
                'cliente' => 'Telefone não encontrado nesta empresa. Procure a revenda para se cadastrar.',
            ]);
        }

        return $this->garantirUsuario($cliente, $verificado['uid']);
    }

    /**
     * Cadastra um cliente do app (fluxo newuser) — F3b. Verifica o token do Firebase,
     * impede duplicidade pelo telefone na empresa, cria o cliente com o telefone
     * verificado e o vincula a um usuário. Retorna o User para emitir o token Sanctum.
     *
     * @param  array{firebase_id_token:string, empresa_id:int, nome:string, cpf?:?string, email?:?string, datanascimento?:?string}  $dados
     */
    public function cadastrar(array $dados): User
    {
        $verificado = $this->firebase->verify($dados['firebase_id_token']);
        $telefone = $verificado['phone'] ?? null;
        if (! $telefone) {
            throw ValidationException::withMessages(['telefone' => 'Token sem telefone verificado.']);
        }

        $empresaId = (int) $dados['empresa_id'];

        $grupoId = (int) Empresa::query()->whereKey($empresaId)->value('grupo_id');

        // Nao rejeita mais telefone repetido com "faca login".
        //
        // O telefone JA foi verificado por SMS aqui: se ele bate com um cadastro
        // existente, e a mesma pessoa e o certo e ADOTAR aquele cadastro — com
        // todo o historico de compra dela — em vez de mandar o cliente embora
        // para uma tela de login que ele pode nao conseguir usar. Era um ponto
        // real de perda de venda: quem ja comprou pelo entregador tinha o
        // telefone na base e nao conseguia se cadastrar no app.
        $resultado = app(IdentificarOuCriarCliente::class)->executar(
            $empresaId,
            $grupoId,
            [
                'nome' => $dados['nome'],
                'cpf' => $dados['cpf'] ?? null,
                'email' => $dados['email'] ?? null,
                'datanascimento' => $dados['datanascimento'] ?? null,
                'cliente' => true,
                'telefones' => [['telefone' => $this->formatarTelefone($telefone)]],
            ],
            'app',
        );

        return $this->garantirUsuario($resultado->cliente, $verificado['uid']);
    }

    /** Normaliza o E.164 do Firebase para um formato de armazenamento simples. */
    private function formatarTelefone(string $e164): string
    {
        return $this->somenteDigitos($e164);
    }

    /**
     * Encontra o cliente da empresa cujo telefone bate com o do token. A comparação é
     * por DÍGITOS (ignora máscara/+55), casando pelos últimos 8 dígitos para tolerar
     * variações de DDD/país entre o cadastro e o E.164 do Firebase.
     *
     * A normalização é feita em PHP (não em SQL) para ser portável entre Postgres
     * (prod) e sqlite (testes); o banco só reduz os candidatos (ver o pré-filtro).
     */
    public function clientePorTelefone(int $empresaId, string $telefoneE164): ?Cliente
    {
        $sufixo = substr($this->somenteDigitos($telefoneE164), -8);
        if (strlen($sufixo) < 8) {
            return null;
        }

        // Pré-filtro no banco pelos 4 últimos dígitos EM ORDEM, com qualquer
        // coisa entre eles ('%0%0%0%1%'): casa toda máscara possível do mesmo
        // número, então nunca perde um cliente que a comparação exata abaixo
        // aceitaria. Sem ele o login carregava todos os clientes da empresa —
        // 44 mil na Dubena, 12 s por login em homologação (13/10/2026).
        //
        // O pré-filtro roda pela conexão de owner e devolve só ids, presos à
        // empresa pedida. Pelo runtime ele custava 4 s: a policy roda em cada
        // linha ANTES do `like` (que não é leakproof), ~1,6 s por tabela de 45
        // mil linhas. O cliente em si continua sendo lido pelo runtime, abaixo,
        // e é a RLS que decide se ele é entregue.
        $aproximado = '%'.implode('%', str_split(substr($sufixo, -4))).'%';
        $candidatos = ConexaoDeOwner::executar(fn () => DB::table('clientetelefones')
            ->where('empresa_id', $empresaId)
            ->where('telefone', 'like', $aproximado)
            ->distinct()
            ->pluck('cliente_id')
            ->all());

        if ($candidatos === []) {
            return null;
        }

        return Cliente::query()
            ->whereIn('id', $candidatos)
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->with(['telefones' => fn ($q) => $q->select('id', 'cliente_id', 'telefone')])
            ->orderBy('id')
            ->get()
            ->first(function (Cliente $cliente) use ($sufixo) {
                foreach ($cliente->telefones as $tel) {
                    if (substr($this->somenteDigitos((string) $tel->telefone), -8) === $sufixo) {
                        return true;
                    }
                }

                return false;
            });
    }

    /** Garante um User para o cliente, vinculando cliente.user_id na primeira vez. */
    private function garantirUsuario(Cliente $cliente, string $firebaseUid): User
    {
        $user = DB::transaction(function () use ($cliente, $firebaseUid) {
            if ($cliente->user_id) {
                $user = User::query()->find($cliente->user_id);
                if ($user) {
                    return $user;
                }
            }

            // Cria um usuário "cliente do app": sem login por senha (password aleatório),
            // herdando empresa/grupo do cliente (tenant). E-mail sintético quando ausente,
            // garantindo unicidade exigida pela tabela.
            $email = $cliente->email ?: 'cliente'.$cliente->id.'+'.$firebaseUid.'@app.local';

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $cliente->nome,
                    'password' => Hash::make(Str::random(40)),
                    'empresa_id' => $cliente->empresa_id,
                    'grupo_id' => $cliente->grupo_id,
                    // `support` sai do $fillable (T1.8) e é ignorado por
                    // firstOrCreate: o default da coluna (false) já é o correto
                    // aqui — um cliente do app JAMAIS deve ter bypass de RBAC.
                    'ativo' => true,
                ],
            );

            $cliente->user_id = $user->id;
            $cliente->save();

            return $user;
        });

        // Fora da transação e pela conexão de owner. `tenant_company_grants` só
        // aceita escrita de owner (`WITH CHECK (false)` para o runtime), e o
        // owner é outra conexão: dentro da transação ele não enxergaria o
        // usuário recém-criado. Roda em todo login, e não só no primeiro, para
        // o cliente que ganhou usuário antes desta correção também ganhar a
        // fronteira — sem ela o token sai, mas toda requisição seguinte é 403.
        ConexaoDeOwner::executar(fn () => $this->garantirFronteiraSaas($user, (int) $cliente->empresa_id));

        return $user;
    }

    /**
     * O usuario do cliente do app tambem precisa existir na fronteira SaaS.
     *
     * Sem isto, com o enforcement ligado, o cliente faz login mas toma 403 na
     * requisicao seguinte: o resolver exige membership ativa. O grant cobre
     * SOMENTE a empresa do proprio cliente e inclui operacao porque ele cria
     * pedido pelo app; o que ele pode ver e fazer dentro dela continua sendo
     * decidido pelo RBAC e pelo escopo de cliente do controller, nao aqui.
     *
     * Empresa fora da fronteira (sem TenantCompany aprovado) nao ganha
     * membership: ali o 403 e a resposta correta.
     */
    private function garantirFronteiraSaas(User $user, int $empresaId): void
    {
        $company = TenantCompany::query()
            ->where('empresa_id', $empresaId)
            ->where('status', TenantCompany::STATUS_APPROVED)
            ->first();
        if ($company === null) {
            return;
        }

        $membership = TenantMembership::query()->firstOrCreate(
            ['tenant_account_id' => $company->tenant_account_id, 'user_id' => $user->id],
            [
                'status' => TenantMembership::STATUS_ACTIVE,
                'membership_role' => 'MEMBER',
                'approved_at' => now(),
                'approval_evidence_ref' => 'app:cliente:'.$user->id,
            ],
        );

        TenantCompanyGrant::query()->firstOrCreate(
            ['tenant_membership_id' => $membership->id, 'empresa_id' => $empresaId],
            [
                'tenant_account_id' => $company->tenant_account_id,
                'tenant_company_id' => $company->id,
                'can_read' => true,
                'can_operate' => true,
                'approved_at' => now(),
                'grant_evidence_ref' => 'app:cliente:'.$user->id,
            ],
        );
    }

    private function somenteDigitos(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }
}
