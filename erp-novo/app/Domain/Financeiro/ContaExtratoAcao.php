<?php

namespace App\Domain\Financeiro;

/**
 * Ação que uma regra de extrato dispara ao casar com a descrição (T4.2).
 *
 * Espelha o `ContaextratoAcao` do legado. Cada ação exige campos diferentes, e
 * é essa validação condicional que dá sentido à regra: uma regra de LANÇAR sem
 * plano de contas não classifica nada; uma de TRANSFERIR sem conta de origem
 * não sabe de onde o dinheiro veio.
 */
enum ContaExtratoAcao: string
{
    /** Cria o lançamento financeiro, deixando-o em aberto. */
    case LANCAR = 'LANCAR';

    /** Cria o lançamento E já o baixa — o dinheiro entrou/saiu de fato. */
    case LANCAR_BAIXAR = 'LANCAR_BAIXAR';

    /** Transferência entre contas próprias: não é receita nem despesa. */
    case TRANSFERIR = 'TRANSFERIR';

    /**
     * Campos obrigatórios desta ação, no formato de regras do validador.
     *
     * A regra vem do legado (`ContaController::addEditExtratoconfig`): as duas
     * primeiras ações exigem condição de pagamento, tipo de movimento, plano de
     * contas e centro de custo; a transferência exige a conta de origem.
     *
     * @return array<string,string>
     */
    public function camposObrigatorios(): array
    {
        return match ($this) {
            self::LANCAR, self::LANCAR_BAIXAR => [
                'condicaopagamento_id' => 'required|integer',
                'contamovimentotipo_id' => 'required|integer',
                'plano_conta_id' => 'required|integer',
                'centro_custo_id' => 'required|integer',
            ],
            self::TRANSFERIR => [
                'conta_origem_id' => 'required|integer',
            ],
        };
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::LANCAR => 'Lançar',
            self::LANCAR_BAIXAR => 'Lançar e baixar',
            self::TRANSFERIR => 'Transferir entre contas',
        };
    }
}
