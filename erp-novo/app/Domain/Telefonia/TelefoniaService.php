<?php

namespace App\Domain\Telefonia;

use App\Models\Cliente\Cliente;
use App\Models\Telefonia\ChamadaEntrante;
use App\Models\Telefonia\Ligacao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bina no atendimento (T4.4) — identifica a chamada entrante e acha o cliente.
 *
 * **O fluxo, herdado do legado.** O PABX faz POST quando o telefone toca
 * (`ApiController@gravarTelefone`) → a linha entra na fila → o operador vê na
 * tela → aceita (abre a ficha do cliente) ou rejeita (vira registro).
 *
 * **A parte que o legado errava.** Ele FORMATAVA o telefone na gravação, com um
 * laço que inseria parênteses e hífen posição a posição. Isso torna a busca
 * refém do formato: um número que o PABX manda como `04236279900` virava
 * `(0423) 6279-900` e não casava com `(42) 3627-9900` do cadastro. Aqui a regra
 * é oposta — **guarda cru, compara só dígitos**.
 *
 * ⚠️ **Condicionada à decisão do dono.** O plano pergunta se o call-center usa
 * bina hoje. Se a resposta for "não", remover é apagar migration, models,
 * service, controller e 4 rotas.
 */
class TelefoniaService
{
    /** Chamada some da fila depois disto (minutos) — ninguém atende o que já desligou. */
    private const MINUTOS_NA_FILA = 30;

    /**
     * Registra uma chamada entrante vinda do PABX.
     *
     * @return array{chamada: ChamadaEntrante, clientes: list<array<string,mixed>>}
     */
    public function receber(int $empresaId, ?int $grupoId, string $telefone, ?string $ramal = null): array
    {
        $clientes = $this->clientesPorTelefone($empresaId, $telefone);

        $chamada = ChamadaEntrante::create([
            'empresa_id' => $empresaId,
            'grupo_id' => $grupoId,
            // Cru: normalizar na gravação é o erro do legado.
            'telefone' => trim($telefone),
            'ramal' => $ramal,
            // Só vincula quando há UM cliente. Com vários, escolher o primeiro
            // abriria a ficha errada — pior que não abrir nenhuma, porque o
            // atendente trata a pessoa pelo nome de outra.
            'cliente_id' => count($clientes) === 1 ? $clientes[0]['id'] : null,
            'recebida_em' => now(),
        ]);

        return ['chamada' => $chamada->fresh(), 'clientes' => $clientes];
    }

    /**
     * A fila: o que está tocando agora nesta empresa.
     *
     * @return list<array<string,mixed>>
     */
    public function fila(int $empresaId): array
    {
        $corte = Carbon::now()->subMinutes(self::MINUTOS_NA_FILA);

        return ChamadaEntrante::query()
            ->with('cliente:id,nome')
            ->where('empresa_id', $empresaId)
            ->where('recebida_em', '>=', $corte)
            ->orderByDesc('recebida_em')
            ->get()
            ->map(fn (ChamadaEntrante $c) => [
                'id' => $c->id,
                'telefone' => $c->telefone,
                'telefone_formatado' => $this->formatar($c->telefone),
                'ramal' => $c->ramal,
                'cliente_id' => $c->cliente_id,
                'cliente' => $c->cliente->nome ?? null,
                // Quando não vinculou, a tela oferece as opções — pode ser
                // telefone compartilhado (condomínio, comércio) ou cadastro
                // duplicado que o dedup não pegou.
                'candidatos' => $c->cliente_id === null
                    ? $this->clientesPorTelefone($empresaId, $c->telefone)
                    : [],
                'recebida_em' => $c->recebida_em?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Operador atendeu: sai da fila e vira registro.
     */
    public function atender(int $chamadaId, int $userId, ?int $clienteId = null): Ligacao
    {
        return DB::transaction(function () use ($chamadaId, $userId, $clienteId) {
            $chamada = ChamadaEntrante::query()->findOrFail($chamadaId);

            $ligacao = Ligacao::create([
                'empresa_id' => $chamada->empresa_id,
                'grupo_id' => $chamada->grupo_id,
                'telefone' => $chamada->telefone,
                'cliente_id' => $clienteId ?? $chamada->cliente_id,
                'user_id' => $userId,
                'atendida' => true,
                'rejeitada' => false,
            ]);

            // Sai da fila: o legado fazia delete() aqui, e faz sentido — a fila
            // é "o que está tocando", não histórico.
            $chamada->delete();

            return $ligacao;
        });
    }

    /**
     * Operador rejeitou (trote, engano, desligou): sai da fila com o motivo.
     */
    public function rejeitar(int $chamadaId, int $userId, ?string $motivo = null): Ligacao
    {
        return DB::transaction(function () use ($chamadaId, $userId, $motivo) {
            $chamada = ChamadaEntrante::query()->findOrFail($chamadaId);

            $ligacao = Ligacao::create([
                'empresa_id' => $chamada->empresa_id,
                'grupo_id' => $chamada->grupo_id,
                'telefone' => $chamada->telefone,
                'cliente_id' => $chamada->cliente_id,
                'user_id' => $userId,
                'atendida' => false,
                'rejeitada' => true,
                'motivo' => $motivo,
            ]);

            $chamada->delete();

            return $ligacao;
        });
    }

    /**
     * Clientes cujo telefone bate com o número da chamada.
     *
     * **Compara só os dígitos, pelos últimos 8.** Três razões, todas vividas:
     * o cadastro guarda com máscara (`(42) 99960-2233`) e o PABX manda cru; o
     * DDD às vezes vem e às vezes não; e o 9º dígito de celular foi adicionado
     * no meio da vida de bases antigas, então o mesmo assinante aparece com 8 e
     * com 9 dígitos. Casar pelos últimos 8 atravessa os três casos.
     *
     * @return list<array<string,mixed>>
     */
    public function clientesPorTelefone(int $empresaId, string $telefone): array
    {
        $digitos = preg_replace('/\D/', '', $telefone) ?? '';

        if (mb_strlen($digitos) < 8) {
            // Menos que isso não identifica ninguém — e um sufixo curto casaria
            // com meia base.
            return [];
        }

        $sufixo = mb_substr($digitos, -8);

        return DB::table('clientetelefones as t')
            ->join('clientes as c', 'c.id', '=', 't.cliente_id')
            ->where('c.empresa_id', $empresaId)
            // `regexp_replace` no Postgres; no sqlite dos testes o REPLACE
            // encadeado cobre os separadores que o cadastro usa.
            ->whereRaw($this->sqlSoDigitos('t.telefone').' LIKE ?', ['%'.$sufixo])
            ->orderBy('c.nome')
            ->limit(10)
            ->get(['c.id', 'c.nome', 't.telefone', 'c.endereco', 'c.numero'])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'nome' => $r->nome,
                'telefone' => $r->telefone,
                'endereco' => trim(((string) $r->endereco).' '.((string) $r->numero)),
            ])
            ->all();
    }

    /**
     * Expressão que reduz a coluna a dígitos, no dialeto do banco ativo.
     *
     * O Postgres tem `regexp_replace`; o sqlite (usado nos testes) não — daí o
     * REPLACE encadeado com os separadores que aparecem no cadastro real.
     */
    private function sqlSoDigitos(string $coluna): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return "regexp_replace({$coluna}, '[^0-9]', '', 'g')";
        }

        $expr = $coluna;
        foreach (['(', ')', '-', ' ', '.', '+'] as $sep) {
            $expr = "REPLACE({$expr}, '{$sep}', '')";
        }

        return $expr;
    }

    /** Máscara brasileira para exibição — só na saída, nunca na gravação. */
    private function formatar(string $telefone): string
    {
        $d = preg_replace('/\D/', '', $telefone) ?? '';

        return match (mb_strlen($d)) {
            11 => sprintf('(%s) %s-%s', mb_substr($d, 0, 2), mb_substr($d, 2, 5), mb_substr($d, 7)),
            10 => sprintf('(%s) %s-%s', mb_substr($d, 0, 2), mb_substr($d, 2, 4), mb_substr($d, 6)),
            default => $telefone,
        };
    }
}
