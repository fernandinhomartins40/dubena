<?php

namespace App\Http\Resources;

use App\Domain\Tenant\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Empresa
 */
class EmpresaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grupo_id' => $this->grupo_id,
            'razao_social' => $this->razao_social,
            'nome_fantasia' => $this->nome_fantasia,
            'nome_informal' => $this->nome_informal,
            'cnpj' => $this->cnpj,
            'inscricao_estadual' => $this->inscricao_estadual,
            'inscricao_municipal' => $this->inscricao_municipal,
            'cep' => $this->cep,
            'uf' => $this->uf,
            'cidade' => $this->cidade,
            'bairro' => $this->bairro,
            'endereco' => $this->endereco,
            'numero' => $this->numero,
            'complemento' => $this->complemento,
            'telefone1' => $this->telefone1,
            'telefone2' => $this->telefone2,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'matriz' => (bool) $this->matriz,
            'ativo' => (bool) $this->ativo,
            // `ativa` = é a empresa do tenant ativo nesta requisição (a SPA usa isso).
            'ativa' => $this->id === app(TenantContext::class)->empresaId(),
        ];
    }
}
