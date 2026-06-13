<?php

use Illuminate\Database\Seeder;
use App\Ocorrenciasremessas;

class PopulateOcorrenciasRemessasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
    	
    	Ocorrenciasremessas::where('seed', true)->delete();
    	/** 
    	*	Pars
    	* 	codigo_bamco, codigo, descricao, tipo, uso_banco, allowed_user
    	*
    	* 	TIPO: ARQUIVO DE REMESSA
    	*/
    	$this->insert('104', '01', 'Entrada de Título', 0);
    	$this->insert('104', '02', 'Pedido de Baixa', 0, 0, 1);
    	$this->insert('104', '03', 'Concessão de Abatimento', 0, 0, 1);
    	$this->insert('104', '04', 'Cancelamento de Abatimento', 0, 0, 1);
    	$this->insert('104', '05', 'Alteração de Vencimento', 0, 0, 1);
    	$this->insert('104', '06', 'Alteração do uso da Empresa', 0);
    	$this->insert('104', '07', 'Alteração do Prazo de Protesto', 0, 0, 1);
    	$this->insert('104', '08', 'Alteração do Prazo de Devolução', 0, 0, 1);
    	$this->insert('104', '09', 'Alteração de outros dados', 0);
    	$this->insert('104', '10', 'Alt de dados c/ emissão / emissão de boleto', 0);
    	$this->insert('104', '11', 'Alteração da opção de Protesto para Devolução', 0, 0, 1);
    	$this->insert('104', '12', 'Alteração da opção de Devolução para Protesto', 0, 0, 1);
    	/**
    	* 	TIPO: ARQUIVO DE REMESSA
    	*/
    	$this->insert('104', '01', 'Movimento sem Beneficiário Correspondente', 1);
    	$this->insert('104', '02', 'Movimento sem Título Correspondente', 1);
    	$this->insert('104', '08', 'Movimento para título já com movimentação no dia', 1);
    	$this->insert('104', '09', 'Nosso Número não pertence ao Beneficiário', 1);
    	$this->insert('104', '10', 'Inclusão de título já existente na base', 1);
    	$this->insert('104', '12', 'Movimento duplicado', 1);
    	$this->insert('104', '13', 'Entrada Inválida para Cobrança Caucionada (Beneficiário não possui conta Caução)', 1);
    	$this->insert('104', '20', 'CEP do Pagador não encontrado (não foi possível a determinação da Agência Cobradora para o título)', 1);
    	$this->insert('104', '21', 'Agência cobradora não encontrada (agência designada para cobradora não cadastrada no sistema)', 1);
    	$this->insert('104', '22', 'Agência Beneficiário não encontrada (Agência do Beneficiário não cadastrada no sistema)', 1);
    	$this->insert('104', '26', 'Data de vencimento inválida', 1);
    	$this->insert('104', '44', 'CEP do sacado inválido', 1);
    	$this->insert('104', '45', 'Data de Vencimento com prazo superior ao limite', 1);
    	$this->insert('104', '49', 'Movimento inválido para título Baixado/Liquidado', 1);
    	$this->insert('104', '50', 'Movimento inválido para título enviado a Cartório', 1);
    	$this->insert('104', '54', 'Faixa de CEP da Agência Cobradora não abrange CEP do Pagador', 1);
    	$this->insert('104', '55', 'Título já com opção de Devolução', 1);
    	$this->insert('104', '56', 'Processo de Protesto em andamento', 1);
    	$this->insert('104', '57', 'Título já com opção de Protesto', 1);
    	$this->insert('104', '58', 'Processo de devolução em andamento', 1);
    	$this->insert('104', '59', 'Novo prazo p/ Protesto/Devolução inválido', 1);
    	$this->insert('104', '76', 'Alteração do prazo de protesto inválida', 1);
    	$this->insert('104', '77', 'Alteração do prazo de devolução inválida', 1);
    	$this->insert('104', '81', 'CEP do Pagador inválido', 1);
    	$this->insert('104', '82', 'CNPJ/CPF do Pagador inválido (dígito não confere)', 1);
    	$this->insert('104', '83', 'Número do Documento (seu número) inválido', 1);
    	$this->insert('104', '84', 'Protesto inválido para título sem Número do documento (seu número)', 1);

    	$this->insert('104', '01', 'Entrada Confirmada', 1, true);
    	$this->insert('104', '02', 'Baixa Manual Confirmada', 1, true);
    	$this->insert('104', '03', 'Abatimento Concedido', 1, true);
    	$this->insert('104', '04', 'Abatimento Cancelado', 1, true);
    	$this->insert('104', '05', 'Vencimento Alterado', 1, true);
    	$this->insert('104', '06', 'Uso da Empresa Alterado', 1, true);
    	$this->insert('104', '07', 'Prazo de Protesto Alterado', 1, true);
    	$this->insert('104', '08', 'Prazo de Devolução Alterado', 1, true);
    	$this->insert('104', '09', 'Alteração Confirmada', 1, true);
    	$this->insert('104', '10', 'Alteração com reemissão de boleto confirmada', 1, true);
    	$this->insert('104', '11', 'Alteração da opção de Protesto para Devolução Confirmada', 1, true);
    	$this->insert('104', '12', 'Alteração da opção de Devolução para Protesto Confirmada', 1, true);
    	$this->insert('104', '20', 'Em Ser', 1, true);
    	$this->insert('104', '21', 'Liquidação', 1, true);
    	$this->insert('104', '22', 'Liquidação em Cartório', 1, true);
    	$this->insert('104', '23', 'Baixa por Devolução', 1, true);
    	$this->insert('104', '25', 'Baixa por Protesto', 1, true);
    	$this->insert('104', '26', 'Título enviado para Cartório', 1, true);
    	$this->insert('104', '27', 'Sustação de Protesto', 1, true);
    	$this->insert('104', '28', 'Estorno de Protesto', 1, true);
    	$this->insert('104', '29', 'Estorno de Sustação de Protesto', 1, true);
    	$this->insert('104', '30', 'Alteração de Título', 1, true);
    	$this->insert('104', '31', 'Tarifa sobre Título Vencido', 1, true);
    	$this->insert('104', '32', 'Outras Tarifas de Alteração', 1, true);
    	$this->insert('104', '33', 'Estorno de Baixa / Liquidação', 1, true);
    	$this->insert('104', '34', 'Tarifas Diversas', 1, true);
    	$this->insert('104', '35', 'Liquidação On-line', 1, true);
    	$this->insert('104', '36', 'Estorno de Liquidação On-line', 1, true);
    	$this->insert('104', '37', 'Transferência para a cobrança simples', 1, true);
    	$this->insert('104', '38', 'Transferência para a cobrança descontada', 1, true);
    	$this->insert('104', '51', 'Reconhecido pelo sacado', 1, true);
    	$this->insert('104', '52', 'Não reconhecido pelo sacado', 1, true);
    	$this->insert('104', '53', 'Recusado no DDA', 1, true);
    	$this->insert('104', '99', 'Rejeição do Título – Código rejeição informado nas pos 80 a 82', 1, true);
    	$this->insert('104', 'A4', 'Pagador DDA', 1, true);



    	// $this->insert('104', '', '', 1);

		$this->insert('104', '01', 'Remessa sem registro tipo 0', 2);
		$this->insert('104', '02', 'Identificação inválida da Empresa na CAIXA', 2);
		$this->insert('104', '03', 'Número Inválido da Remessa', 2);
		$this->insert('104', '04', 'Beneficiário não pertence a Cobrança Eletrônica', 2);
		$this->insert('104', '05', 'Código da Remessa Inválido', 2);
		$this->insert('104', '06', 'Literal da Remessa Inválido', 2);
		$this->insert('104', '07', 'Código de Serviço Inválido', 2);
		$this->insert('104', '08', 'Literal de Serviço Inválido', 2);
		$this->insert('104', '09', 'Código do Banco Inválido', 2);
		$this->insert('104', '10', 'Nome do Banco Inválido', 2);
		$this->insert('104', '11', 'Data de gravação Inválida', 2);
		$this->insert('104', '12', 'Número de Remessa já Processada', 2);
		$this->insert('104', '13', 'Tipo de registro esperado Inválido', 2);
		$this->insert('104', '14', 'Tipo de Ocorrência Inválido', 2);
		$this->insert('104', '15', 'Literal Remessa Inválida para fase de Testes', 2);
		$this->insert('104', '16', 'Identificação da empresa no Registro tipo 0 difere da identificação no Registro Tipo 1', 2);
		$this->insert('104', '17', 'Identificação na CAIXA inválida (Nosso Número)', 2);
		$this->insert('104', '18', 'Código da Carteira inválido', 2);
		$this->insert('104', '19', 'Número seqüencial do Registro Inválido', 2);
		$this->insert('104', '20', 'Tipo de Inscrição da empresa Inválido', 2);
		$this->insert('104', '21', 'Número de Inscrição da empresa Inválido', 2);
		$this->insert('104', '22', 'Literal REM.TST válida somente para a fase de Testes', 2);
		$this->insert('104', '23', 'Taxa de Comissão de Permanência Inválida', 2);
		$this->insert('104', '24', 'Nosso Número inválido para Cobrança Registrada emissão Beneficiário (14)', 2);
		$this->insert('104', '25', 'Dígito do Nosso Número não confere', 2);
		$this->insert('104', '26', 'Data de vencimento inválida', 2);
		$this->insert('104', '27', 'Valor do título inválido', 2);
		$this->insert('104', '28', 'Espécie de título Inválida', 2);
		$this->insert('104', '29', 'Código de Aceite Inválido', 2);
		$this->insert('104', '30', 'Data de emissão do título inválida', 2);
		$this->insert('104', '31', 'Instrução de Cobrança 1 Inválida', 2);
		$this->insert('104', '32', 'Instrução de Cobrança 2 Inválida', 2);
		$this->insert('104', '33', 'Instrução de Cobrança 3 Inválida', 2);
		$this->insert('104', '34', 'Valor de Juros Inválido', 2);
		$this->insert('104', '35', 'Data do Desconto Inválida', 2);
		$this->insert('104', '36', 'Valor do Desconto Inválido', 2);
		$this->insert('104', '37', 'Valor do IOF Inválido', 2);
		$this->insert('104', '38', 'Valor do Abatimento Inválido', 2);
		$this->insert('104', '39', 'Tipo de Inscrição do Pagador Inválido', 2);
		$this->insert('104', '40', 'Número de Inscrição do Pagador Inválido', 2);
		$this->insert('104', '41', 'Número de Inscrição do Pagador obrigatório', 2);
		$this->insert('104', '42', 'Nome do Pagador obrigatório', 2);
		$this->insert('104', '43', 'Endereço do Pagador obrigatório', 2);
		$this->insert('104', '44', 'CEP do Pagador Inválido', 2);
		$this->insert('104', '45', 'Cidade do Pagador obrigatório', 2);
		$this->insert('104', '46', 'Estado do Pagador obrigatório', 2);
		$this->insert('104', '47', 'Data da multa inválida', 2);
		$this->insert('104', '48', 'Valor da multa inválido', 2);
		$this->insert('104', '49', 'Prazo de protesto/devolução inválido', 2);
		$this->insert('104', '50', 'Prazo do protesto inválido', 2);
		$this->insert('104', '51', 'Prazo de devolução inválido', 2);
		$this->insert('104', '52', 'Moeda inválida', 2);
		$this->insert('104', '53', '“USO DA EMPRESA” obrigatório', 2);
		$this->insert('104', '54', 'Remessa sem registro tipo 9', 2);
		$this->insert('104', '55', 'Solicitacao nao permitida para titulo incluido somente para protesto', 2);
		$this->insert('104', '56', 'Identificacao inválida da empresa na CAIXA', 2);
		$this->insert('104', '57', 'Identificacao inválida da empresa na CAIXA', 2);
		$this->insert('104', '58', 'Identificacao inválida da empresa na CAIXA', 2);
		$this->insert('104', '59', 'Identificacao inválida da empresa na CAIXA', 2);
		$this->insert('104', '60', 'Identificação da emissão do boleto inválida', 2);
		$this->insert('104', '61', 'Tipo de entrega inválido', 2);
		$this->insert('104', '62', 'Modalidade do título inválida', 2);
		$this->insert('104', '63', 'Forma de entrega de bloq.inválida para emis. banco ', 2);
		$this->insert('104', '64', 'Forma de entrega de bloq.inválida para emis.beneficiário', 2);
		$this->insert('104', '65', 'Forma de emissao de boleto inválida', 2);
		$this->insert('104', '66', 'E-mail inválido', 2);
		$this->insert('104', '67', 'Número do DDD do celular do sacado inválido', 2);
		$this->insert('104', '68', 'Número do celular do sacado inválido', 2);
		$this->insert('104', '69', 'Tipo de mensagem de envio SMS inválido', 2);
		$this->insert('104', '70', 'Envio de sms do beneficiário inválido', 2);
		$this->insert('104', '71', 'Reenvio de SMS diferente de SMS ou SMS e postagem inválido', 2);
		$this->insert('104', '83', 'Número do Documento de Cobrança (Seu Número) inválido', 2);
		$this->insert('104', '84', 'Identificação do tipo de pagamento inválida', 2);
		$this->insert('104', '85', 'Quantidade de pagamentos possíveis inválida', 2);
		$this->insert('104', '86', 'Tipo de valor máximo inválido', 2);
		$this->insert('104', '87', 'Valor máximo inválido', 2);
		$this->insert('104', '88', 'Percentual máximo inválido', 2);
		$this->insert('104', '89', 'Tipo de valor mínimo inválido', 2);
		$this->insert('104', '90', 'Valor mínimo inválido', 2);
		$this->insert('104', '91', 'Percentual mínimo inválido', 2);
		$this->insert('104', '92', 'Tipos de valor máximo e mínimo divergentes', 2);
		$this->insert('104', '93', 'Título autorizado para pagamentos parciais não pode ser desautorizado', 2);
		$this->insert('104', '94', 'Quantidade de pagamentos possíveis menor que a quantidade de pagamentos realizados', 2);
    }

	/** 
	*	Pars
	* 	codigo_bamco, codigo, descricao, tipo
	*
	*/
    private function insert($codigo_banco, $codigo, $descricao, $tipo, $uso_banco = 0, $allowed_user = 0)
    {
        $uso_banco = $uso_banco == 0 ? 0 : 1;
        $allowed_user = $allowed_user == 0 ? 0 : 1;
        $data = [   'codigo_banco'  => $codigo_banco, 
                    'codigo'        => $codigo, 
                    'descricao'     => $descricao, 
                    'tipo'          => $tipo, 
                    'uso_banco'     => $uso_banco, 
                    'seed'          => 1, 
                    'allowed_user'  => $allowed_user];
        Ocorrenciasremessas::create($data);
    }
}
