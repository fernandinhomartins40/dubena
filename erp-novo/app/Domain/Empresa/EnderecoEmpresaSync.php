<?php

namespace App\Domain\Empresa;

use App\Models\Empresa;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\Rua;

/**
 * Mantém as colunas de TEXTO do endereço da empresa derivadas das FKs.
 *
 * A empresa tem as duas representações e isso é deliberado:
 *
 *  - **FK** (`cidade_id`/`bairro_id`/`rua_id`) é a fonte da verdade — é o que o
 *    formulário edita, o que casa com o cadastro geográfico e o que o
 *    roteirizador e a logística conseguem usar;
 *  - **texto** (`cidade`/`bairro`/`endereco`) é derivado — existe porque
 *    `DanfePdfService`, `ComodatoPdfService` e `ValeGasPdfService` imprimem a
 *    string direto, e um PDF fiscal não deve depender de join com o cadastro
 *    (nem quebrar se a cidade for renomeada depois da emissão).
 *
 * Sem esta sincronização as duas divergem: o usuário troca a cidade no
 * formulário e a nota fiscal continua imprimindo a antiga.
 *
 * O texto NUNCA é apagado quando a FK vem vazia — empresa cadastrada só com
 * texto (antes desta normalização) continua imprimindo o endereço que tem.
 */
final class EnderecoEmpresaSync
{
    /**
     * Preenche as colunas de texto a partir das FKs presentes em `$dados`.
     *
     * @param  array<string, mixed>  $dados  dados validados do request
     * @return array<string, mixed> os mesmos dados, com o texto derivado
     */
    public function aplicar(array $dados): array
    {
        if (array_key_exists('cidade_id', $dados) && $dados['cidade_id'] !== null) {
            $cidade = Cidade::query()->find($dados['cidade_id']);
            if ($cidade !== null) {
                $dados['cidade'] = $cidade->descricao;
                // A UF vem da cidade: digitar as duas é convite a divergirem.
                // Só sobrescreve se o formulário não mandou uma explicitamente.
                if (($dados['uf'] ?? null) === null && $cidade->uf !== null) {
                    $dados['uf'] = $cidade->uf;
                }
            }
        }

        if (array_key_exists('bairro_id', $dados) && $dados['bairro_id'] !== null) {
            $bairro = Bairro::query()->find($dados['bairro_id']);
            if ($bairro !== null) {
                $dados['bairro'] = $bairro->descricao;
            }
        }

        if (array_key_exists('rua_id', $dados) && $dados['rua_id'] !== null) {
            $rua = Rua::query()->find($dados['rua_id']);
            if ($rua !== null) {
                $dados['endereco'] = $rua->descricao;
            }
        }

        return $dados;
    }

    /**
     * Ressincroniza uma empresa já gravada (uso do ETL e de correção pontual).
     *
     * Diferente de `aplicar()`, lê as FKs do próprio model em vez do request.
     */
    public function ressincronizar(Empresa $empresa): void
    {
        $dados = $this->aplicar([
            'cidade_id' => $empresa->cidade_id,
            'bairro_id' => $empresa->bairro_id,
            'rua_id' => $empresa->rua_id,
            'uf' => $empresa->uf,
        ]);

        $empresa->forceFill(array_intersect_key($dados, array_flip([
            'cidade', 'bairro', 'endereco', 'uf',
        ])))->save();
    }
}
