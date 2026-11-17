<?php

namespace App\Http\Requests;

use App\Domain\Identidade\DocumentoFiscal;
use App\Models\Apoio\Segmento;
use App\Models\Apoio\TelefoneTipo;
use App\Models\Apoio\TipoPessoa;
use App\Models\Cliente\Cliente;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\Rua;
use App\Rules\ExisteNoTenant;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação de cliente. Telefones como array ANINHADO (não posicional).
 * Autorização (RBAC) é feita no controller.
 */
class ClienteRequest extends FormRequest
{
    use AutorizaPorPermissao;

    public function authorize(): bool
    {
        // Autoriza ANTES de validar (PIE R3-F-05): sem isto o usuário sem
        // permissão recebia 422 com as regras do formulário em vez de 403.
        // O controller repete a checagem; aqui só antecipa.
        $this->autorizar($this, $this->isMethod('post') ? 'cliente.create' : 'cliente.edit');

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

            // PIE-35: dígito verificador no servidor. Chega normalizado (só
            // dígitos) pelo prepareForValidation.
            'cpf' => ['nullable', 'string', 'max:20', $this->documentoValido('cpf')],
            'rg' => 'nullable|string|max:20',
            'cnpj' => ['nullable', 'string', 'max:20', $this->documentoValido('cnpj')],
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

    /**
     * CPF/CNPJ chegam com ou sem máscara; grava-se só dígitos — a forma do ETL
     * e a que o XML da NF-e exige (PIE-35).
     */
    protected function prepareForValidation(): void
    {
        foreach (['cpf', 'cnpj'] as $campo) {
            if ($this->has($campo)) {
                $this->merge([$campo => DocumentoFiscal::canonico($this->input($campo))]);
            }
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome do cliente.',
            'nome.max' => 'O nome pode ter no máximo :max caracteres.',
            'email.email' => 'E-mail inválido.',
            'telefones.*.telefone.required_with' => 'Informe o número do telefone.',
            'telefones.*.telefone.max' => 'Telefone com mais de :max caracteres.',
        ];
    }

    /**
     * Regra de dígito verificador — com uma exceção: na EDIÇÃO, o documento
     * que já estava gravado passa como está. A base migrada tem documentos
     * inválidos do legado; recusá-los travaria qualquer edição do cadastro
     * (trocar o telefone, por exemplo) até alguém descobrir o CPF certo.
     * Documento NOVO ou ALTERADO é sempre conferido.
     */
    private function documentoValido(string $campo): \Closure
    {
        return function (string $atributo, mixed $valor, \Closure $falhar) use ($campo): void {
            if ($valor === null || $valor === '') {
                return;
            }

            $id = $this->route('id');
            if ($id !== null) {
                $atual = Cliente::query()->whereKey((int) $id)->value($campo);
                if ($atual !== null && DocumentoFiscal::canonico($atual) === $valor) {
                    return;
                }
            }

            $ok = $campo === 'cpf' ? DocumentoFiscal::cpfValido($valor) : DocumentoFiscal::cnpjValido($valor);
            if (! $ok) {
                $falhar($campo === 'cpf'
                    ? 'CPF inválido: confira os números (o dígito verificador não confere).'
                    : 'CNPJ inválido: confira os números (o dígito verificador não confere).');
            }
        };
    }
}
