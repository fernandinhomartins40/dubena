<?php

namespace App\Http\Resources;

use App\Domain\Acesso\CamposPermitidos;
use App\Models\Cliente\Cliente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cliente
 */
class ClienteResource extends JsonResource
{
    /** Campos sob controle field-level (A7) — só viajam se o usuário tiver `view`. */
    private const CAMPOS_SENSIVEIS = ['credito_limite', 'credito_saldo', 'convenio_limite'];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $dados = [
            'id' => $this->id,
            'nome' => $this->nome,
            'fantasia' => $this->fantasia,
            'tipopessoa_id' => $this->tipopessoa_id,
            'segmento_id' => $this->segmento_id,
            'sexo' => $this->sexo,
            'datanascimento' => $this->datanascimento?->toDateString(),
            'observacoes' => $this->observacoes,
            // Documentos
            'cpf' => $this->cpf,
            'rg' => $this->rg,
            'cnpj' => $this->cnpj,
            'inscricao_estadual' => $this->inscricao_estadual,
            'indicador_ie' => $this->indicador_ie,
            'suframa' => $this->suframa,
            // Flags
            'cliente' => (bool) $this->cliente,
            'fornecedor' => (bool) $this->fornecedor,
            'transportador' => (bool) $this->transportador,
            'simples' => (bool) $this->simples,
            'nfemite' => (bool) $this->nfemite,
            'gasdopovo' => (bool) $this->gasdopovo,
            'ativo' => (bool) $this->ativo,
            // Endereço
            'endereco' => $this->endereco,
            'numero' => $this->numero,
            'complemento' => $this->complemento,
            'ponto_referencia' => $this->ponto_referencia,
            'cep' => $this->cep,
            'uf' => $this->uf,
            'cidade_id' => $this->cidade_id,
            'bairro_id' => $this->bairro_id,
            'rua_id' => $this->rua_id,
            'email' => $this->email,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            // Convênio / crédito (número cru)
            'convenio' => (bool) $this->convenio,
            'convenio_ativo' => (bool) $this->convenio_ativo,
            'convenio_id' => $this->convenio_id,
            'convenio_limite' => $this->convenio_limite !== null ? (float) $this->convenio_limite : null,
            'credito_limite' => $this->credito_limite !== null ? (float) $this->credito_limite : null,
            'credito_saldo' => $this->credito_saldo !== null ? (float) $this->credito_saldo : null,
            'data_ultima_compra' => $this->data_ultima_compra?->toDateString(),
            // Sub-relação aninhada (quando carregada)
            'telefones' => $this->whenLoaded('telefones', fn () => $this->telefones->map(fn ($t) => [
                'id' => $t->id,
                'telefone' => $t->telefone,
                'whatsapp' => (bool) $t->whatsapp,
                'telefonetipo_id' => $t->telefonetipo_id,
            ])),
        ];

        // Field-level (A7): oculta campos sensíveis sem `cliente.campo.{nome}.view`.
        return app(CamposPermitidos::class)
            ->filtrarLeitura($request->user(), 'cliente', $dados, self::CAMPOS_SENSIVEIS);
    }
}
