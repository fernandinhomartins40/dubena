<?php

namespace App\Http\Requests;

use App\Models\Apoio\Segmento;
use App\Models\Apoio\TelefoneTipo;
use App\Models\Apoio\TipoPessoa;
use App\Models\Cliente\Cliente;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\Rua;
use App\Rules\ExisteNoTenant;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação de cliente. Telefones como array ANINHADO (não posicional).
 * Autorização (RBAC) é feita no controller.
 */
class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:200',
            'fantasia' => 'nullable|string|max:200',
            'tipopessoa_id' => ['nullable', 'integer', new ExisteNoTenant(TipoPessoa::class)],
            'segmento_id' => ['nullable', 'integer', new ExisteNoTenant(Segmento::class)],
            'sexo' => 'nullable|string|size:1',
            'datanascimento' => 'nullable|date',
            'observacoes' => 'nullable|string',

            'cpf' => 'nullable|string|max:20',
            'rg' => 'nullable|string|max:20',
            'cnpj' => 'nullable|string|max:20',
            'inscricao_estadual' => 'nullable|string|max:30',
            'indicador_ie' => 'nullable|integer|in:1,2,9',
            'suframa' => 'nullable|string|max:20',

            'cliente' => 'nullable|boolean',
            'fornecedor' => 'nullable|boolean',
            'transportador' => 'nullable|boolean',
            'simples' => 'nullable|boolean',
            'nfemite' => 'nullable|boolean',
            'gasdopovo' => 'nullable|boolean',
            'ativo' => 'nullable|boolean',

            'endereco' => 'nullable|string|max:500',
            'numero' => 'nullable|string|max:10',
            'complemento' => 'nullable|string|max:100',
            'ponto_referencia' => 'nullable|string|max:255',
            'cep' => 'nullable|string|max:10',
            'uf' => 'nullable|string|size:2',
            'cidade_id' => ['nullable', 'integer', new ExisteNoTenant(Cidade::class)],
            'bairro_id' => ['nullable', 'integer', new ExisteNoTenant(Bairro::class)],
            'rua_id' => ['nullable', 'integer', new ExisteNoTenant(Rua::class)],
            'email' => 'nullable|email|max:150',

            'convenio' => 'nullable|boolean',
            'convenio_ativo' => 'nullable|boolean',
            'convenio_id' => ['nullable', 'integer', new ExisteNoTenant(Cliente::class)],
            'convenio_limite' => 'nullable|numeric',
            'credito_limite' => 'nullable|numeric',

            // Sub-relação aninhada
            'telefones' => 'nullable|array',
            'telefones.*.telefone' => 'required_with:telefones|string|max:30',
            'telefones.*.whatsapp' => 'nullable|boolean',
            'telefones.*.telefonetipo_id' => ['nullable', 'integer', new ExisteNoTenant(TelefoneTipo::class)],
        ];
    }
}
