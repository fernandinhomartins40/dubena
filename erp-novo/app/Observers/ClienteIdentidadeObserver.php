<?php

namespace App\Observers;

use App\Domain\Identidade\IdentidadeCliente;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteTelefone;

/**
 * Mantém os traços de identidade em dia, venha o cliente de onde vier.
 *
 * A porta única (`IdentificarOuCriarCliente`) sincroniza os traços do que passa
 * por ela — mas nem tudo passa: ETL, seeder, factory de teste, edição direta
 * pelo admin, importação do legado. Um cliente sem traço é INVISÍVEL ao motor
 * de identidade, e o próximo cadastro igual vira duplicata sem nem ser
 * comparado.
 *
 * Ligar no evento do model é o que garante a cobertura: não existe caminho de
 * escrita no Eloquent que escape daqui.
 */
class ClienteIdentidadeObserver
{
    public function __construct(private IdentidadeCliente $identidade) {}

    public function created(Cliente $cliente): void
    {
        $this->identidade->sincronizar($cliente);
    }

    public function updated(Cliente $cliente): void
    {
        // Só recalcula se mudou algo que VIRA traço. Uma edição de observação
        // ou de limite de crédito não altera identidade, e refazer os traços a
        // cada salvamento seria trabalho jogado fora.
        $relevantes = ['nome', 'cpf', 'cnpj', 'email', 'endereco', 'numero', 'cidade_id'];

        if ($cliente->wasChanged($relevantes)) {
            $this->identidade->sincronizar($cliente);
        }
    }

    /**
     * Telefone é traço, e vive em outra tabela.
     *
     * Registrado aqui (e não num observer próprio) para manter num só lugar a
     * regra de "o que mantém a identidade atualizada".
     */
    public function telefoneAlterado(ClienteTelefone $telefone): void
    {
        $cliente = $telefone->cliente;

        if ($cliente !== null) {
            $this->identidade->sincronizar($cliente);
        }
    }
}
