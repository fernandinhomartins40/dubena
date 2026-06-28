<?php

namespace App\Domain\Mobile;

use App\Domain\Mobile\Contracts\FirebaseVerifier;
use App\Models\Cliente\Cliente;
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
    public function __construct(private FirebaseVerifier $firebase) {}

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
     * Encontra o cliente da empresa cujo telefone bate com o do token. A comparação é
     * por DÍGITOS (ignora máscara/+55), casando pelos últimos 8 dígitos para tolerar
     * variações de DDD/país entre o cadastro e o E.164 do Firebase.
     *
     * A normalização é feita em PHP (não em SQL) para ser portável entre Postgres
     * (prod) e sqlite (testes) — o conjunto de candidatos por empresa é pequeno.
     */
    public function clientePorTelefone(int $empresaId, string $telefoneE164): ?Cliente
    {
        $sufixo = substr($this->somenteDigitos($telefoneE164), -8);
        if (strlen($sufixo) < 8) {
            return null;
        }

        return Cliente::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->whereHas('telefones')
            ->with('telefones:id,cliente_id,telefone')
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
        return DB::transaction(function () use ($cliente, $firebaseUid) {
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
                    'support' => false,
                    'ativo' => true,
                ],
            );

            $cliente->user_id = $user->id;
            $cliente->save();

            return $user;
        });
    }

    private function somenteDigitos(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }
}
