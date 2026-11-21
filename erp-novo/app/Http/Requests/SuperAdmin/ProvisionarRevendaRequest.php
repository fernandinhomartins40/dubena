<?php

namespace App\Http\Requests\SuperAdmin;

use App\Domain\Identidade\DocumentoFiscal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * PIE P2-A-01 — dados da revenda nova. Quem chega aqui já passou por
 * `auth:platform` e pela sessão da plataforma; a autorização é a da rota.
 */
class ProvisionarRevendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'dono_email' => mb_strtolower(trim((string) $this->input('dono_email'))),
            'cnpj' => DocumentoFiscal::canonico($this->input('cnpj')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'razao_social' => 'required|string|max:200',
            'nome_fantasia' => 'nullable|string|max:200',
            'cnpj' => 'nullable|string|size:14',
            'dono_nome' => 'required|string|max:200',
            // Recusa, nunca reuso: `users` é compartilhada com os clientes do
            // app (PIE R1-01). O serviço confere de novo dentro da transação.
            'dono_email' => 'required|email:rfc|max:200|unique:users,email',
            'plano_id' => 'nullable|integer',
            'trial_dias' => 'nullable|integer|min:0|max:365',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $cnpj = $this->input('cnpj');
            if ($cnpj !== null && $cnpj !== '' && ! DocumentoFiscal::cnpjValido($cnpj)) {
                $v->errors()->add('cnpj', 'CNPJ inválido.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'razao_social.required' => 'Informe a razão social da revenda.',
            'dono_nome.required' => 'Informe o nome do dono da revenda.',
            'dono_email.required' => 'Informe o e-mail do dono da revenda.',
            'dono_email.email' => 'E-mail do dono inválido.',
            'dono_email.unique' => 'Já existe um usuário com este e-mail.',
            'cnpj.size' => 'O CNPJ precisa ter 14 dígitos.',
        ];
    }
}
