<?php

namespace App\Domain\Mobile;

use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteEndereco;
use Illuminate\Support\Facades\DB;

/**
 * EnderecoMobileService (F3b) — múltiplos endereços de entrega do cliente do app.
 * Porta o `address/*` do legado (listar/criar/editar/favoritar/excluir). Garante 1
 * favorito por cliente. Tudo escopado por empresa via os models tenant-scoped.
 */
class EnderecoMobileService
{
    /** @return list<array<string,mixed>> */
    public function listar(Cliente $cliente): array
    {
        return ClienteEndereco::query()
            ->where('cliente_id', $cliente->id)
            ->orderByDesc('favorito')->orderBy('id')
            ->get()->map(fn (ClienteEndereco $e) => $this->serializar($e))->all();
    }

    /** @param array<string,mixed> $dados */
    public function criar(Cliente $cliente, array $dados): ClienteEndereco
    {
        return DB::transaction(function () use ($cliente, $dados) {
            $primeiro = ! ClienteEndereco::query()->where('cliente_id', $cliente->id)->exists();
            $favorito = (bool) ($dados['favorito'] ?? false) || $primeiro;

            $endereco = new ClienteEndereco($dados);
            $endereco->empresa_id = $cliente->empresa_id;
            $endereco->cliente_id = $cliente->id;
            $endereco->favorito = $favorito;
            $endereco->save();

            if ($favorito) {
                $this->desmarcarOutros($cliente->id, $endereco->id);
            }

            return $endereco;
        });
    }

    /** @param array<string,mixed> $dados */
    public function atualizar(ClienteEndereco $endereco, array $dados): ClienteEndereco
    {
        return DB::transaction(function () use ($endereco, $dados) {
            $endereco->fill($dados)->save();
            if (! empty($dados['favorito'])) {
                $this->desmarcarOutros($endereco->cliente_id, $endereco->id);
            }

            return $endereco->refresh();
        });
    }

    public function favoritar(ClienteEndereco $endereco): ClienteEndereco
    {
        return DB::transaction(function () use ($endereco) {
            $endereco->update(['favorito' => true]);
            $this->desmarcarOutros($endereco->cliente_id, $endereco->id);

            return $endereco->refresh();
        });
    }

    public function excluir(ClienteEndereco $endereco): void
    {
        DB::transaction(function () use ($endereco) {
            $eraFavorito = $endereco->favorito;
            $clienteId = $endereco->cliente_id;
            $endereco->delete();

            // Se removeu o favorito, promove o mais antigo restante.
            if ($eraFavorito) {
                $proximo = ClienteEndereco::query()->where('cliente_id', $clienteId)->orderBy('id')->first();
                $proximo?->update(['favorito' => true]);
            }
        });
    }

    private function desmarcarOutros(int $clienteId, int $manterId): void
    {
        ClienteEndereco::query()
            ->where('cliente_id', $clienteId)
            ->where('id', '!=', $manterId)
            ->where('favorito', true)
            ->update(['favorito' => false]);
    }

    /** @return array<string,mixed> */
    public function serializar(ClienteEndereco $e): array
    {
        return [
            'id' => $e->id,
            'titulo' => $e->titulo,
            'endereco' => $e->endereco,
            'numero' => $e->numero,
            'complemento' => $e->complemento,
            'ponto_referencia' => $e->ponto_referencia,
            'bairro' => $e->bairro,
            'cidade' => $e->cidade,
            'cep' => $e->cep,
            'uf' => $e->uf,
            'latitude' => $e->latitude !== null ? (float) $e->latitude : null,
            'longitude' => $e->longitude !== null ? (float) $e->longitude : null,
            'favorito' => (bool) $e->favorito,
        ];
    }
}
