<?php

namespace App\Domain\Venda;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Estoque\TipoLocalEstoque;
use App\Domain\Rh\ModoEstoque;
use App\Models\Estoque\Setor;
use App\Models\Rh\Colaborador;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Carga e devolução do franqueado — F5.
 *
 * **O que resolve.** A auditoria apontou que não existe estoque em poder do
 * franqueado: o que o `CentralService` chama de "carga" é *número de pedidos
 * atribuídos*, não botijão físico. Num modelo de franquia isso pesa — a
 * mercadoria sai do depósito e fica na rua, e alguém responde por ela.
 *
 * **Dois modos, decididos por pessoa** (`ModoEstoque`):
 *  - *consignação*: a mercadoria continua da EMPRESA. Sai do depósito para o
 *    setor do franqueado e volta o que não vendeu.
 *  - *compra*: o franqueado comprou. A saída já é venda da empresa para ele;
 *    devolução não se aplica, porque o estoque passou a ser dele.
 *
 * **Reusa o EstoqueService inteiro.** A carga é uma transferência entre setores
 * (`EstoqueService::transferir:123`), que já tem lock pessimista, custo médio e
 * histórico auditável. Criar um controle de saldo paralelo obrigaria conciliar
 * dois números que deveriam ser um só.
 */
class CargaFranqueadoService
{
    public function __construct(private EstoqueService $estoque) {}

    /**
     * Entrega mercadoria ao franqueado.
     *
     * @param  list<array{produto_id:int, quantidade:float}>  $itens
     * @return array{colaborador_id:int, modo:string, setor_destino:int, itens:int}
     */
    public function carregar(Colaborador $colaborador, int $setorOrigem, array $itens, ?int $userId = null): array
    {
        $modo = $this->modoDe($colaborador);
        $destino = $this->setorDo($colaborador);
        $empresaId = (int) $colaborador->empresa_id;

        if ($itens === []) {
            throw ValidationException::withMessages(['itens' => 'Informe ao menos um item.']);
        }

        DB::transaction(function () use ($setorOrigem, $destino, $itens, $userId, $empresaId) {
            foreach ($itens as $i) {
                $this->estoque->transferir(
                    $setorOrigem,
                    $destino,
                    (int) $i['produto_id'],
                    (float) $i['quantidade'],
                    $userId,
                    $empresaId,
                );
            }
        });

        return [
            'colaborador_id' => (int) $colaborador->id,
            'modo' => $modo->value,
            'setor_destino' => $destino,
            'itens' => count($itens),
        ];
    }

    /**
     * Devolve ao depósito o que sobrou.
     *
     * **Só faz sentido na consignação.** Na compra a mercadoria é do franqueado:
     * aceitar "devolução" ali seria, na verdade, uma compra de volta — outra
     * operação, com efeito fiscal próprio, que o negócio precisa decidir antes
     * de existir no sistema.
     *
     * @param  list<array{produto_id:int, quantidade:float}>  $itens
     */
    public function devolver(Colaborador $colaborador, int $setorDeposito, array $itens, ?int $userId = null): array
    {
        $modo = $this->modoDe($colaborador);

        if (! $modo->aceitaDevolucao()) {
            throw new \DomainException(
                'Este franqueado trabalha por compra: a mercadoria é dele e não retorna ao estoque da empresa.'
            );
        }

        $origem = $this->setorDo($colaborador);
        $empresaId = (int) $colaborador->empresa_id;

        DB::transaction(function () use ($origem, $setorDeposito, $itens, $userId, $empresaId) {
            foreach ($itens as $i) {
                $this->estoque->transferir(
                    $origem,
                    $setorDeposito,
                    (int) $i['produto_id'],
                    (float) $i['quantidade'],
                    $userId,
                    $empresaId,
                );
            }
        });

        return [
            'colaborador_id' => (int) $colaborador->id,
            'setor_origem' => $origem,
            'itens' => count($itens),
        ];
    }

    /**
     * O que está em poder do franqueado agora — a base da prestação de contas.
     *
     * @return list<array{produto_id:int, produto:string, quantidade:float}>
     */
    public function emPoder(Colaborador $colaborador): array
    {
        $setorId = $colaborador->setor_estoque_id;

        if ($setorId === null) {
            return [];
        }

        return DB::table('estoquesaldos as s')
            ->join('produtos as p', 'p.id', '=', 's.produto_id')
            ->where('s.setor_id', $setorId)
            ->where('s.quantidade', '>', 0)
            ->orderBy('p.descricao')
            ->get(['s.produto_id', 'p.descricao as produto', 's.quantidade'])
            ->map(fn ($r) => [
                'produto_id' => (int) $r->produto_id,
                'produto' => (string) $r->produto,
                'quantidade' => (float) $r->quantidade,
            ])
            ->all();
    }

    private function modoDe(Colaborador $colaborador): ModoEstoque
    {
        $modo = $colaborador->modo_estoque;

        if ($modo instanceof ModoEstoque) {
            return $modo;
        }

        $resolvido = ModoEstoque::tryFrom((string) $modo);

        if ($resolvido === null) {
            // Fail-closed: sem modo definido não se movimenta mercadoria. Adivinhar
            // erraria de quem é o botijão que está na rua.
            throw new \DomainException(
                'Colaborador sem modo de estoque definido (consignação ou compra).'
            );
        }

        return $resolvido;
    }

    /** Cria o setor do franqueado na primeira carga, se ainda não existir. */
    private function setorDo(Colaborador $colaborador): int
    {
        if ($colaborador->setor_estoque_id !== null) {
            return (int) $colaborador->setor_estoque_id;
        }

        $setor = Setor::create([
            'empresa_id' => $colaborador->empresa_id,
            'grupo_id' => $colaborador->grupo_id,
            'descricao' => 'Em poder de '.$colaborador->nome,
            // F3-06: declarado na criacao. Antes este setor entrava na lista de
            // armazens como qualquer deposito, e o operador podia lancar uma
            // entrada de mercadoria "em poder de Fulano" — o que nao da erro,
            // da um saldo que so nao bate no inventario.
            'tipo' => TipoLocalEstoque::CUSTODIA_PESSOA->value,
            'ativo' => true,
        ]);

        $colaborador->forceFill(['setor_estoque_id' => $setor->id])->save();

        return (int) $setor->id;
    }
}
