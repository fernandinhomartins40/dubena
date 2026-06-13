<?php
namespace App\Services;

use Eduardokum\LaravelBoleto\Boleto\Banco\Caixa as Caixa;


class BoletoCaixaService extends Caixa
{

    protected $especiesCodigo = [
        'CH'    => '01',
        'DM'    => '02',
        'DMI'   => '03',
        'DS'    => '04',
        'DSI'   => '05',
        'DR'    => '06',
        'LC'    => '07',
        'NCC'   => '08',
        'NCE'   => '09',
        'NCI'   => '10',
        'NCR'   => '11',
        'NP'    => '12',
        'NPR'   => '13',
        'TM'    => '14',
        'TS'    => '15',
        'NS'    => '16',
        'RC'    => '17',
        'FAT'   => '18',
        'ND'    => '19',
        'AP'    => '20',
        'ME'    => '21',
        'PC'    => '22',
        'NF'    => '23',
        'DD'    => '24',
        'CPR'   => '25',
        'CC'    => '31',
        'BP'    => '32',
        'OU'    => '99',
    ];

    protected $cedente;

    public function getCedente()
    {
        return $this->cedente;
    }

    public function setCedente($cedente)
    {
        $this->cedente = $cedente;
    }

    /**
     * Adiciona uma instrução (máximo 8)
     *
     * @param string $instrucao
     *
     * @return AbstractBoleto
     * @throws \Exception
     */
    public function addInstrucao($instrucao)
    {
        if (count($this->getInstrucoes()) > 8) {
            throw new \Exception('Atingido o máximo de 8 instruções.');
        }
        array_push($this->instrucoes, $instrucao);

        return $this;
    }

    /**
     * Define um array com instruções (máximo 8) para pagamento
     *
     * @param array $instrucoes
     *
     * @return AbstractBoleto
     * @throws \Exception
     */
    public function setInstrucoes(array $instrucoes)
    {
        if (count($instrucoes) > 8) {
            throw new \Exception('Máximo de 8 instruções.');
        }
        $this->instrucoes = $instrucoes;

        return $this;
    }

    /**
     * Retorna um array com instruções (máximo 8) para pagamento
     *
     * @return array
     */
    public function getInstrucoes()
    {
        return array_slice((array) $this->instrucoes + [null, null, null, null, null, null, null, null], 0, 8);
    }




    /**
     * Adiciona um demonstrativo (máximo 8)
     *
     * @param string $descricaoDemonstrativo
     *
     * @return AbstractBoleto
     * @throws \Exception
     */
    public function addDescricaoDemonstrativo($descricaoDemonstrativo)
    {
        if (count($this->getDescricaoDemonstrativo()) > 8) {
            throw new \Exception('Atingido o máximo de 8 demonstrativos.');
        }
        array_push($this->descricaoDemonstrativo, $descricaoDemonstrativo);

        return $this;
    }

    /**
     * Define um array com a descrição do demonstrativo (máximo 8)
     *
     * @param array $descricaoDemonstrativo
     *
     * @return AbstractBoleto
     * @throws \Exception
     */
    public function setDescricaoDemonstrativo(array $descricaoDemonstrativo)
    {
        if (count($descricaoDemonstrativo) > 8) {
            throw new \Exception('Máximo de 8 demonstrativos.');
        }
        $this->descricaoDemonstrativo = $descricaoDemonstrativo;

        return $this;
    }

    /**
     * Retorna um array com a descrição do demonstrativo (máximo 8)
     *
     * @return array
     */
    public function getDescricaoDemonstrativo()
    {
        return array_slice((array) $this->descricaoDemonstrativo + [null, null, null, null, null], 0, 8);
    }

}