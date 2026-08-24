<?php

namespace App\Domain\Satelite;

use App\Domain\Estoque\EstoqueService;
use App\Models\Satelite\Comodato;
use App\Models\Satelite\ComodatoContrato;
use App\Models\Satelite\ComodatoMovimento;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ComodatoService (N8) — vasilhame emprestado ao cliente.
 *
 * **Estoque.** Emprestar baixa (SAÍDA); devolver repõe (ENTRADA); estornar uma
 * devolução baixa de novo. Sempre via `EstoqueService`, para o saldo continuar
 * auditável.
 *
 * **Extrato.** Toda alteração de saldo gera um `ComodatoMovimento`. O acumulado
 * em `comodatos.quantidade_devolvida` continua existindo porque é o que a
 * listagem lê, mas ele agora é DERIVADO do extrato — quem manda é a série de
 * movimentos, e `recalcular()` prova isso.
 *
 * **Contrato.** Devolução parcial reemite o contrato com o saldo já descontado,
 * numa versão nova. A versão anterior — a que o cliente assinou — continua
 * existindo. Ver `ComodatoContrato`.
 *
 * **Correção de erro.** Devolução lançada errada não é editada nem apagada: é
 * ESTORNADA. O estorno é um movimento como outro qualquer, some no extrato e
 * devolve o saldo. Editar o histórico destruiria a prova de que a entrega
 * aconteceu.
 */
class ComodatoService
{
    /** Tolerância de arredondamento: o decimal do banco tem 3 casas. */
    private const EPSILON = 0.0001;

    public function __construct(private EstoqueService $estoque)
    {
    }

    /** @param array<string,mixed> $dados */
    public function emprestar(array $dados, ?int $userId = null): Comodato
    {
        $qtd = (float) $dados['quantidade'];
        if ($qtd <= 0) {
            throw ValidationException::withMessages(['quantidade' => 'Quantidade deve ser positiva.']);
        }

        return DB::transaction(function () use ($dados, $qtd, $userId) {
            $comodato = Comodato::create(array_merge($dados, [
                'quantidade' => $qtd,
                'quantidade_devolvida' => 0,
                'situacao' => 'ATIVO',
                'data_emprestimo' => $dados['data_emprestimo'] ?? now()->toDateString(),
            ]));

            if ($comodato->setor_id) {
                $this->estoque->saida($comodato->setor_id, $comodato->produto_id, $qtd, 'comodato', $comodato->id, $userId);
            }

            $movimento = $this->registrarMovimento(
                $comodato,
                ComodatoMovimento::EMPRESTIMO,
                $qtd,
                $qtd,
                (string) $comodato->data_emprestimo?->toDateString(),
                $userId,
            );

            $this->emitirContrato($comodato->refresh(), ComodatoContrato::EMISSAO_INICIAL, $movimento, $userId);

            return $comodato->refresh();
        });
    }

    /**
     * Devolve (parcial ou total) o vasilhame, repondo o estoque, e REEMITE o
     * contrato com o saldo já descontado.
     *
     * A reemissão é o ponto: até aqui o operador que recebia 2 de 5 ficava sem
     * documento que descrevesse a posse nova, e por isso fazia devolução total
     * na mão para "acertar o papel" — perdendo o registro de que 3 seguiam com
     * o cliente.
     */
    public function devolver(
        Comodato $comodato,
        float $quantidade,
        ?int $userId = null,
        ?string $data = null,
        ?string $observacao = null,
    ): Comodato {
        if ($quantidade <= 0) {
            throw ValidationException::withMessages(['quantidade' => 'Quantidade deve ser positiva.']);
        }

        // `ENCERRADO` vem do ETL: o legado marca o comodato como inativo sem
        // registrar quantidade devolvida, então o saldo aparenta estar todo com
        // o cliente. Aceitar devolução aqui daria ENTRADA em estoque de um
        // vasilhame que já voltou anos atrás.
        if (in_array((string) $comodato->situacao, ['CANCELADO', 'ENCERRADO', 'DEVOLVIDO'], true)) {
            throw ValidationException::withMessages([
                'quantidade' => 'Comodato encerrado não recebe devolução.',
            ]);
        }

        $pendente = $this->emPosse($comodato);
        if ($quantidade > $pendente + self::EPSILON) {
            throw ValidationException::withMessages([
                'quantidade' => "Devolução ({$this->num($quantidade)}) maior que o pendente ({$this->num($pendente)}).",
            ]);
        }

        return DB::transaction(function () use ($comodato, $quantidade, $userId, $data, $observacao, $pendente) {
            if ($comodato->setor_id) {
                $this->estoque->entrada($comodato->setor_id, $comodato->produto_id, $quantidade, null, 'comodato-devolucao', $comodato->id, $userId);
            }

            $saldo = round($pendente - $quantidade, 3);

            $movimento = $this->registrarMovimento(
                $comodato,
                ComodatoMovimento::DEVOLUCAO,
                $quantidade,
                $saldo,
                $data ?? now()->toDateString(),
                $userId,
                observacao: $observacao,
            );

            $this->aplicarSaldo($comodato, $saldo, $movimento->data->toDateString());

            // Devolução TOTAL encerra: não há posse a descrever, então não há
            // contrato novo. Parcial reemite — é para isso que ela existe.
            if ($saldo > self::EPSILON) {
                $this->emitirContrato($comodato->refresh(), ComodatoContrato::DEVOLUCAO_PARCIAL, $movimento, $userId);
            }

            return $comodato->refresh();
        });
    }

    /**
     * Estorna uma devolução lançada errada: o vasilhame volta a constar em
     * poder do cliente e o estoque é baixado de novo.
     *
     * Não apaga nem edita o movimento original — a entrega ACONTECEU e o
     * registro dela é prova. O estorno é uma linha nova apontando para ela.
     */
    public function estornar(ComodatoMovimento $movimento, ?int $userId = null, ?string $observacao = null): Comodato
    {
        if ($movimento->tipo !== ComodatoMovimento::DEVOLUCAO) {
            throw ValidationException::withMessages([
                'movimento' => 'Só devolução é estornável: o empréstimo se desfaz cancelando o comodato.',
            ]);
        }

        if ($movimento->foiEstornado()) {
            throw ValidationException::withMessages(['movimento' => 'Esta devolução já foi estornada.']);
        }

        $comodato = $movimento->comodato;
        $quantidade = (float) $movimento->quantidade;

        return DB::transaction(function () use ($comodato, $movimento, $quantidade, $userId, $observacao) {
            if ($comodato->setor_id) {
                $this->estoque->saida($comodato->setor_id, $comodato->produto_id, $quantidade, 'comodato-estorno', $comodato->id, $userId);
            }

            $saldo = round($this->emPosse($comodato) + $quantidade, 3);

            $novo = $this->registrarMovimento(
                $comodato,
                ComodatoMovimento::ESTORNO,
                $quantidade,
                $saldo,
                now()->toDateString(),
                $userId,
                estornaId: $movimento->id,
                observacao: $observacao,
            );

            $this->aplicarSaldo($comodato, $saldo, null);

            // O contrato precisa voltar a descrever a posse restaurada — senão
            // a última versão emitida diz um saldo que já não é o vigente.
            $this->emitirContrato($comodato->refresh(), ComodatoContrato::REEMISSAO, $novo, $userId);

            return $comodato->refresh();
        });
    }

    /**
     * ACRESCENTA vasilhames a um comodato existente.
     *
     * O caso real: o cliente cresceu e pediu mais 5 botijões. Antes disso, a
     * única saída era criar um comodato novo — o cliente ficava com dois
     * contratos para a mesma relação, e o total em poder dele só aparecia
     * somando registros na mão.
     *
     * Aumenta a quantidade contratada, baixa o estoque da diferença e reemite o
     * contrato com o total atualizado.
     */
    public function acrescentar(
        Comodato $comodato,
        float $quantidade,
        ?int $userId = null,
        ?string $observacao = null,
    ): Comodato {
        if ($quantidade <= 0) {
            throw ValidationException::withMessages(['quantidade' => 'Quantidade deve ser positiva.']);
        }

        if (in_array((string) $comodato->situacao, ['CANCELADO', 'ENCERRADO', 'DEVOLVIDO'], true)) {
            throw ValidationException::withMessages([
                'quantidade' => 'Comodato encerrado não recebe acréscimo. Abra um comodato novo.',
            ]);
        }

        return DB::transaction(function () use ($comodato, $quantidade, $userId, $observacao) {
            if ($comodato->setor_id) {
                $this->estoque->saida($comodato->setor_id, $comodato->produto_id, $quantidade, 'comodato-acrescimo', $comodato->id, $userId);
            }

            $saldo = round($this->emPosse($comodato) + $quantidade, 3);

            // A quantidade CONTRATADA cresce; a devolvida não se mexe. Somar no
            // lugar errado faria o saldo bater e o contrato mentir.
            $comodato->update([
                'quantidade' => round((float) $comodato->quantidade + $quantidade, 3),
                'situacao' => (float) $comodato->quantidade_devolvida > 0 ? 'PARCIAL' : 'ATIVO',
                'data_devolucao' => null,
            ]);

            $movimento = $this->registrarMovimento(
                $comodato->refresh(),
                ComodatoMovimento::EMPRESTIMO,
                $quantidade,
                $saldo,
                now()->toDateString(),
                $userId,
                observacao: $observacao ?? 'Acréscimo ao comodato',
            );

            $this->emitirContrato($comodato->refresh(), ComodatoContrato::ACRESCIMO, $movimento, $userId);

            return $comodato->refresh();
        });
    }

    /**
     * Emite uma versão do contrato SEM mexer em saldo — para quando os dados do
     * cliente ou do signatário mudam e o papel precisa ser refeito.
     */
    public function reemitirContrato(Comodato $comodato, ?int $userId = null): ComodatoContrato
    {
        if ($this->emPosse($comodato) <= self::EPSILON) {
            throw ValidationException::withMessages([
                'comodato' => 'Não há vasilhame em poder do comodatário — nada a contratar.',
            ]);
        }

        return DB::transaction(fn () => $this->emitirContrato($comodato, ComodatoContrato::REEMISSAO, null, $userId));
    }

    /**
     * RENOVA o comodato: nova data de vencimento e contrato reemitido.
     *
     * O alerta de vencimento pede uma decisão — renovar ou recolher. Renovar
     * sem reemitir o papel deixaria o cliente com um contrato vencido na gaveta
     * e a revenda sem instrumento para reaver o vasilhame, que é exatamente o
     * risco que o alerta aponta.
     *
     * @param  array<string,mixed>  $dadosSignatario  nome/cpf/rg do representante, quando mudaram
     */
    public function renovar(
        Comodato $comodato,
        string $novoVencimento,
        ?int $userId = null,
        array $dadosSignatario = [],
    ): ComodatoContrato {
        if ($this->emPosse($comodato) <= self::EPSILON) {
            throw ValidationException::withMessages([
                'comodato' => 'Não há vasilhame em poder do comodatário — nada a renovar.',
            ]);
        }

        if (strtotime($novoVencimento) <= strtotime(now()->toDateString())) {
            throw ValidationException::withMessages([
                'data_vencimento' => 'O novo vencimento precisa ser futuro.',
            ]);
        }

        return DB::transaction(function () use ($comodato, $novoVencimento, $userId, $dadosSignatario) {
            $comodato->update(array_merge(
                array_intersect_key($dadosSignatario, array_flip([
                    'nome_representante', 'cpf_representante', 'rg_representante',
                ])),
                ['data_vencimento' => $novoVencimento],
            ));

            return $this->emitirContrato($comodato->refresh(), ComodatoContrato::RENOVACAO, null, $userId);
        });
    }

    /** Registra que a via assinada voltou — contrato sem assinatura não protege nada. */
    public function marcarAssinado(ComodatoContrato $contrato): ComodatoContrato
    {
        $contrato->update(['assinado_em' => now()]);

        return $contrato->refresh();
    }

    /** Quantidade ainda em poder do comodatário. */
    public function emPosse(Comodato $comodato): float
    {
        return round((float) $comodato->quantidade - (float) $comodato->quantidade_devolvida, 3);
    }

    /**
     * Reconstrói `quantidade_devolvida` a partir do extrato.
     *
     * Existe para provar que o acumulado é derivado, não uma segunda verdade:
     * se este método mudar algum comodato, houve escrita fora do serviço.
     *
     * @return array{antes:float, depois:float, divergiu:bool}
     */
    public function recalcular(Comodato $comodato): array
    {
        $antes = (float) $comodato->quantidade_devolvida;

        $movimentos = ComodatoMovimento::query()
            ->where('comodato_id', $comodato->id)
            ->orderBy('id')
            ->get();

        // Comodato do legado não tem extrato; sem movimentos não há o que
        // reconstruir, e zerar destruiria o acumulado migrado.
        if ($movimentos->isEmpty()) {
            return ['antes' => $antes, 'depois' => $antes, 'divergiu' => false];
        }

        $devolvida = 0.0;
        foreach ($movimentos as $m) {
            $devolvida += match ($m->tipo) {
                ComodatoMovimento::DEVOLUCAO => (float) $m->quantidade,
                ComodatoMovimento::ESTORNO => -(float) $m->quantidade,
                default => 0.0,
            };
        }

        $devolvida = round($devolvida, 3);

        if (abs($devolvida - $antes) > self::EPSILON) {
            $this->aplicarSaldo($comodato, round((float) $comodato->quantidade - $devolvida, 3), null);
        }

        return ['antes' => $antes, 'depois' => $devolvida, 'divergiu' => abs($devolvida - $antes) > self::EPSILON];
    }

    /** @return list<array{contrato:ComodatoContrato}>|\Illuminate\Support\Collection<int,ComodatoContrato> */
    public function contratos(Comodato $comodato)
    {
        return ComodatoContrato::query()
            ->where('comodato_id', $comodato->id)
            ->orderByDesc('versao')
            ->get();
    }

    /** Grava saldo e situação derivados da quantidade em posse. */
    private function aplicarSaldo(Comodato $comodato, float $emPosse, ?string $dataDevolucao): void
    {
        $total = (float) $comodato->quantidade;
        $devolvida = round($total - $emPosse, 3);

        $situacao = match (true) {
            $emPosse <= self::EPSILON => 'DEVOLVIDO',
            $devolvida > self::EPSILON => 'PARCIAL',
            default => 'ATIVO',
        };

        $comodato->update([
            'quantidade_devolvida' => $devolvida,
            'situacao' => $situacao,
            'data_devolucao' => $situacao === 'DEVOLVIDO'
                ? ($dataDevolucao ?? now()->toDateString())
                : null,
        ]);
    }

    private function registrarMovimento(
        Comodato $comodato,
        string $tipo,
        float $quantidade,
        float $saldoApos,
        string $data,
        ?int $userId,
        ?int $estornaId = null,
        ?string $observacao = null,
    ): ComodatoMovimento {
        return ComodatoMovimento::create([
            'empresa_id' => $comodato->empresa_id,
            'grupo_id' => $comodato->grupo_id,
            'comodato_id' => $comodato->id,
            'tipo' => $tipo,
            'quantidade' => round($quantidade, 3),
            'saldo_apos' => round($saldoApos, 3),
            'data' => $data,
            'estorna_id' => $estornaId,
            'observacao' => $observacao,
            'user_id' => $userId,
        ]);
    }

    /** Congela o estado atual numa versão nova do contrato. */
    private function emitirContrato(
        Comodato $comodato,
        string $motivo,
        ?ComodatoMovimento $movimento,
        ?int $userId,
    ): ComodatoContrato {
        $proxima = (int) ComodatoContrato::query()
            ->where('comodato_id', $comodato->id)
            ->max('versao') + 1;

        return ComodatoContrato::create([
            'empresa_id' => $comodato->empresa_id,
            'grupo_id' => $comodato->grupo_id,
            'comodato_id' => $comodato->id,
            'versao' => $proxima,
            'quantidade_contratada' => $comodato->quantidade,
            'quantidade_devolvida' => $comodato->quantidade_devolvida,
            'quantidade_em_posse' => $this->emPosse($comodato),
            'motivo' => $motivo,
            'movimento_id' => $movimento?->id,
            'user_id' => $userId,
        ]);
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, ',', '.'), '0'), ',');
    }
}
