<?php

namespace App\Domain\Cliente;

use App\Models\Cliente\Cliente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Regra de negócio do Cliente (sem HTTP). O controller valida (Form Request),
 * chama estes métodos e devolve Resource.
 *
 * - payload com sub-relações ANINHADAS (telefones[]) — substitui arrays posicionais;
 * - anti-duplicidade de endereço por QUERY PARAMETRIZADA (sem SQLi do legado);
 * - geocoding ASSÍNCRONO via Job (não trava o request).
 */
class ClienteService
{
    /** @param array<string, mixed> $dados */
    public function criar(array $dados): Cliente
    {
        $this->garantirEnderecoNaoDuplicado($dados);

        return DB::transaction(function () use ($dados) {
            $telefones = $dados['telefones'] ?? null;
            unset($dados['telefones']);

            $cliente = Cliente::create($dados);

            if (is_array($telefones)) {
                $this->sincronizarTelefones($cliente, $telefones);
            }

            GeocodificarClienteJob::dispatch($cliente->id);

            return $cliente->load('telefones');
        });
    }

    /** @param array<string, mixed> $dados */
    public function atualizar(Cliente $cliente, array $dados): Cliente
    {
        $this->garantirEnderecoNaoDuplicado($dados, $cliente->id);
        $enderecoMudou = $this->enderecoMudou($cliente, $dados);

        return DB::transaction(function () use ($cliente, $dados, $enderecoMudou) {
            $telefones = $dados['telefones'] ?? null;
            unset($dados['telefones']);

            $cliente->update($dados);

            if (is_array($telefones)) {
                $this->sincronizarTelefones($cliente, $telefones);
            }

            if ($enderecoMudou) {
                GeocodificarClienteJob::dispatch($cliente->id);
            }

            return $cliente->refresh()->load('telefones');
        });
    }

    public function excluir(Cliente $cliente): void
    {
        $cliente->delete(); // sub-tabelas caem por cascadeOnDelete
    }

    /**
     * Substitui o conjunto de telefones do cliente pelo informado.
     *
     * @param list<array<string, mixed>> $telefones
     */
    private function sincronizarTelefones(Cliente $cliente, array $telefones): void
    {
        $cliente->telefones()->delete();
        foreach ($telefones as $tel) {
            if (empty($tel['telefone'])) {
                continue;
            }
            $cliente->telefones()->create([
                'telefone' => $tel['telefone'],
                'whatsapp' => (bool) ($tel['whatsapp'] ?? false),
                'telefonetipo_id' => $tel['telefonetipo_id'] ?? null,
            ]);
        }
    }

    /**
     * Bloqueia cadastro de cliente com MESMO endereço (numero+cidade+bairro) na
     * empresa — query parametrizada (corrige a SQLi por concatenação do legado).
     *
     * @param array<string, mixed> $dados
     */
    private function garantirEnderecoNaoDuplicado(array $dados, ?int $ignorarId = null): void
    {
        if (empty($dados['cidade_id']) || empty($dados['numero'])) {
            return; // sem endereço suficiente, nada a checar
        }

        $existe = Cliente::query()
            ->where('cidade_id', $dados['cidade_id'])
            ->where('numero', $dados['numero'])
            ->when(! empty($dados['bairro_id']), fn (Builder $q) => $q->where('bairro_id', $dados['bairro_id']))
            ->when(! empty($dados['endereco']), fn (Builder $q) => $q->where('endereco', $dados['endereco']))
            ->when($ignorarId, fn (Builder $q) => $q->where('id', '<>', $ignorarId))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'endereco' => 'Já existe um cliente cadastrado neste endereço.',
            ]);
        }
    }

    /** @param array<string, mixed> $dados */
    private function enderecoMudou(Cliente $cliente, array $dados): bool
    {
        foreach (['endereco', 'numero', 'cidade_id', 'bairro_id', 'cep'] as $campo) {
            if (array_key_exists($campo, $dados) && (string) $dados[$campo] !== (string) $cliente->{$campo}) {
                return true;
            }
        }

        return false;
    }
}
