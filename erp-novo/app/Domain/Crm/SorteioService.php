<?php

namespace App\Domain\Crm;

use App\Models\Crm\Sorteio;
use App\Models\Crm\SorteioNumero;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sorteio como evento: o resultado é decidido AQUI, uma vez, antes de qualquer
 * animação.
 *
 * A tela faz contagem regressiva e gira a roleta, mas só depois de o servidor
 * ter gravado o vencedor — a animação apresenta o resultado, não o produz.
 * Assim, fechar a aba no meio, perder a conexão ou recarregar a página não
 * muda quem ganhou, e reapresentar para gravar o vídeo mostra o MESMO
 * ganhador.
 */
class SorteioService
{
    /**
     * Sorteia e registra. Recusa sortear de novo: num evento público, um
     * segundo clique (ou duas abas) trocando o ganhador é exatamente a
     * contestação que o cliente não pode sofrer.
     */
    public function sortear(int $sorteioId, ?int $userId): Sorteio
    {
        return DB::transaction(function () use ($sorteioId, $userId) {
            // Lock antes de olhar a situação: sem ele, duas requisições
            // simultâneas leem "aberto" e as duas sorteiam.
            $sorteio = Sorteio::query()->whereKey($sorteioId)->lockForUpdate()->firstOrFail();

            if ($sorteio->situacao === 'sorteado') {
                throw ValidationException::withMessages([
                    'sorteio' => "Este sorteio já foi realizado (número {$sorteio->numero_sorteado}). Use \"Reapresentar\" para exibir o resultado de novo.",
                ]);
            }

            $ids = SorteioNumero::query()->where('sorteio_id', $sorteio->id)->orderBy('id')->pluck('id')->all();
            if ($ids === []) {
                throw ValidationException::withMessages(['sorteio' => 'Não há números para sortear.']);
            }

            // `random_int` usa o gerador criptográfico do sistema; o
            // `Collection::random()` de antes caía no `array_rand`, que não é.
            // Para prêmio, a imprevisibilidade é o que dá legitimidade.
            $ganhador = SorteioNumero::query()->findOrFail($ids[random_int(0, count($ids) - 1)]);

            $sorteio->forceFill([
                'situacao' => 'sorteado',
                'numero_sorteado' => $ganhador->numero,
                'ganhador_numero_id' => $ganhador->id,
                'sorteado_em' => now(),
                'sorteado_por' => $userId,
            ])->save();

            return $sorteio;
        });
    }

    /**
     * Tudo que o palco precisa: o sorteio, os números que giram na roleta e,
     * se já houve resultado, o ganhador.
     *
     * A roleta leva só número e PRIMEIRO nome: a tela é feita para ser
     * gravada e exibida, e a lista inteira de clientes com sobrenome não
     * precisa ir para um vídeo público. Do ganhador vai o nome completo e o
     * local — é o que o cliente pediu para anunciar — mas telefone mascarado.
     *
     * @return array<string, mixed>
     */
    public function palco(int $sorteioId): array
    {
        $sorteio = Sorteio::query()->with(['condutor:id,name'])->findOrFail($sorteioId);

        $participantes = SorteioNumero::query()
            ->where('sorteio_id', $sorteio->id)
            ->with('cliente:id,nome')
            ->orderBy('id')
            ->get()
            ->map(fn (SorteioNumero $n) => [
                'numero' => $n->numero,
                'nome' => $this->primeiroNome($n->cliente?->nome),
            ])
            ->values();

        return [
            'sorteio' => [
                'id' => $sorteio->id,
                'descricao' => $sorteio->descricao,
                'premio' => $sorteio->premio,
                'data_sorteio' => $sorteio->data_sorteio?->toDateString(),
                'situacao' => $sorteio->situacao,
            ],
            'participantes' => $participantes,
            'resultado' => $sorteio->situacao === 'sorteado' ? $this->resultado($sorteio) : null,
        ];
    }

    /** @return array<string, mixed> */
    public function resultado(Sorteio $sorteio): array
    {
        $ganhador = $sorteio->ganhador_numero_id !== null
            ? SorteioNumero::query()->with(['cliente.cidade:id,descricao,uf', 'cliente.bairro:id,descricao', 'cliente.telefones'])
                ->find($sorteio->ganhador_numero_id)
            : null;
        $cliente = $ganhador?->cliente;

        return [
            'numero' => $ganhador->numero ?? $sorteio->numero_sorteado,
            'cliente' => $cliente === null ? null : [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'bairro' => $cliente->bairro?->descricao,
                'cidade' => $cliente->cidade?->descricao,
                'uf' => $cliente->cidade?->uf,
                'telefone' => $this->mascararTelefone($cliente->telefones->first()?->telefone),
            ],
            'sorteado_em' => $sorteio->sorteado_em?->toIso8601String(),
            'sorteado_por' => $sorteio->condutor?->name ?? $sorteio->condutor()->value('name'),
            'participantes' => SorteioNumero::query()->where('sorteio_id', $sorteio->id)->count(),
        ];
    }

    private function primeiroNome(?string $nome): ?string
    {
        $nome = trim((string) $nome);

        return $nome === '' ? null : mb_convert_case(explode(' ', $nome)[0], MB_CASE_TITLE);
    }

    /** "(42) 99123-4567" → "(42) •••••-4567": dá para o ganhador se reconhecer, não para ligar. */
    private function mascararTelefone(?string $telefone): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $telefone) ?? '';
        if (strlen($digitos) < 8) {
            return null;
        }
        $ddd = strlen($digitos) >= 10 ? '('.substr($digitos, 0, 2).') ' : '';

        return $ddd.'•••••-'.substr($digitos, -4);
    }
}
