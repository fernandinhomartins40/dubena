<?php

namespace App\Http\Requests;

use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\Rua;
use App\Models\Regiao;
use App\Rules\ExisteNoTenant;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação de criação/edição de Empresa. Valores numéricos como número (lat/long),
 * sem string-BR. A autorização (RBAC) é feita no controller.
 */
class EmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'razao_social' => 'required|string|max:255',
            'nome_fantasia' => 'nullable|string|max:255',
            'nome_informal' => 'nullable|string|max:255',
            'cnpj' => 'nullable|string|max:20',
            'inscricao_estadual' => 'nullable|string|max:30',
            'inscricao_municipal' => 'nullable|string|max:30',
            'cep' => 'nullable|string|max:10',
            'uf' => 'nullable|string|max:2',
            // Texto: continua aceito (o PDF fiscal imprime a string), mas é
            // DERIVADO das FKs abaixo quando elas vêm — ver EnderecoEmpresaSync.
            'cidade' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'endereco' => 'nullable|string|max:255',
            // FKs: o formulário já as enviava; sem estas regras o `validated()`
            // as descartava e o endereço da empresa nunca era gravado.
            'cidade_id' => ['nullable', 'integer', new ExisteNoTenant(Cidade::class)],
            'bairro_id' => ['nullable', 'integer', new ExisteNoTenant(Bairro::class)],
            'rua_id' => ['nullable', 'integer', new ExisteNoTenant(Rua::class)],
            'regiao_id' => ['nullable', 'integer', new ExisteNoTenant(Regiao::class)],
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:255',
            'telefone1' => 'nullable|string|max:20',
            'telefone2' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'matriz' => 'nullable|boolean',
            'ativo' => 'nullable|boolean',
        ];
    }
}
