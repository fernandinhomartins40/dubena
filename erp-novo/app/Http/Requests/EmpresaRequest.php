<?php

namespace App\Http\Requests;

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
            'cidade' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'endereco' => 'nullable|string|max:255',
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
