@extends('layouts.contrato')

@section('content')
<div class="fontSize_15 negrito">
    {{ $empresa->razao_social }}
</div>
<div class="fontSize_13 negrito">
    {{ $empresa->rua->descricao }}, {{ $empresa->numero }}
    {{ $empresa->bairro->descricao }} - 
    {{ $empresa->cidade->descricao }}/{{ $empresa->uf }}
</div>
<div class="fontSize_13 negrito">CNPJ: {{ $empresa->cnpj }} I.E.: {{ $empresa->inscricao_estadual }}</div>
</div>
<div style="padding-top: -55px;float:right;">
    @if($empresa->logo != null)
    <img id="imgInicial" class="img-circle" style="max-height:50px;" src="data:image/png;base64,{{ $empresa->logo }}" alt="Logotipo"/>
    @else
    <img id="imgInicial" class="img-circle"  style="max-height:50px;" src="{{URL::to('dist/img/userdefault.png')}}" alt="Logotipo"/>
    @endif
</div>
<div style="text-align: center; margin-bottom: 0.5%; margin-top: 45px" class="fontSize_14 negrito margLeft_40">CONTRATO PARTICULAR DE CONVÊNIOS</div>
<div class="" style="border:1px solid black; padding-top: 10px;"></div>
<div class="fontSize_11 text-justify " style="padding-top: 30px; max-width: 93%; margin-left: 3.5%">
    <div class="margBottom_10">
        {{ $empresa->razao_social }} estabelecida à 
        {{ $empresa->rua->descricao }}, 
        {{ $empresa->numero }} na Cidade de 
        {{ $empresa->cidade->descricao }}/{{ $empresa->uf }}, devidamente inscrita no CNPJ sob 
        nº {{ $empresa->cnpj }} aqui denominada apenas como CONTRATADA, neste ato representada por seu 
        representante legal Sr(a). {{ $empresa->contratonome }}, portador da cédula de identidade 
        nº {{ $empresa->contratorg }} e CPF nº {{ $empresa->contratocpf }} e, 
        de outro lado a empresa {{ strtoupper($cliente->nome) }}, situada à {{ @$cliente->rua->descricao }}, 
        {{ @$cliente->numero }}, {{ @$cliente->bairro->descricao }}, {{ @$cliente->cidade->descricao }}/{{ @$cliente->uf }}, 
        devidamente inscrita no CNPJ sob nº {{ @$cliente->cnpj }} e inscrição estadual nº {{ @$cliente->inscricao_estadual }}, 
        aqui denominada apenas como CONTRATANTE, neste ato representada por seu representante legal 
        Sr(a). {{@$cliente->clienteConvenio->nomerepresentante}}, portador da cédula de identidade RG 
        nº {{@$cliente->clienteConvenio->rgrepresentante}} e CPF nº {{@$cliente->clienteConvenio->cpfrepresentante}}.
    </div>

    <div class="margBottom_10">CONTRATADA e CONTRATANTE identificadas, respectivamente, no preâmbulo deste instrumento, tem entre si justo e acordado o
        presente Contrato Particular de Convênios mediante as cláusulas e condições seguintes:
    </div>

    <div class="margBottom_10">CLÁUSULA PRIMEIRA: Fechado o acordo entre CONTRATADA E CONTRATANTE, será entregue aos funcionários da CONTRATANTE
        em no máximo sete dias, um cartão denominado 'CARTÃO CONVÊNIO', onde constará o nome da empresa conveniada e o nome
        completo do conveniado.
    </div>

    <div class="margBottom_10">CLÁUSULA SEGUNDA: No ato da entrega do GLP o entregador preencherá um 'CONTROLE' com o nome da CONTRATANTE, nome do
        conveniado, tipo, quantidade e valor do GLP entregue, assinatura da pessoa que recebeu o GLP e a data da entrega.
    </div>

    <div class="margBottom_10">CLÁUSULA TERCEIRA: Todo {{@$cliente->clienteConvenio->diafechamento}}º dia de cada mês, fica a CONTRATADA incumbida de passar à CONTRATANTE, uma relação com os
        nomes e valores das despesas dos respectivos funcionários para ser debitado em folha de pagamento.
    </div>

    <div class="margBottom_10">CLÁUSULA QUARTA: Todo {{@$cliente->clienteConvenio->diavencimento}}º dia de cada mês, fica a CONTRATANTE responsável em repassar os valores para a
        CONTRATADA.
    </div>

    <div class="margBottom_10">CLÁUSULA QUINTA: A CONTRATADA se compromete a dar prioridade às chamadas efetuadas pelos conveniados fazendo a
        entrega solicitada em no máximo 15 (quinze) minutos.
    </div>

    <div class="margBottom_10">CLÁUSULA SEXTA: A CONTRATADA entregará aos conveniados botijões de boa qualidade devidamente e testados por pessoas
        qualificadas.
    </div>

    <div class="margBottom_10">CLÁUSULA SÉTIMA: A CONTRATADA coloca seus serviços a disposição dos conveniados.</div>

    <div class="margBottom_10">CLÁUSULA OITAVA: É de responsabilidade da CONTRATANTE avisar a CONTRATADA imediatamente após o desligamento de
        funcionários, verificar a existência de débitos destes junto a CONTRATADA e recolher o cartão convênio do mesmo, para que
        seu crédito seja encerrado.
    </div>

    <div class="margBottom_10">CLÁUSULA NONA: O presente contrato é por prazo indeterminado, podendo ser rescindido por vontade de umas das partes
        mediante comunicado com prazo antecipado de 30 dias.
    </div>

    <div class="margBottom_10">CLÁUSULA DÉCIMA: CONTRATADA E CONTRATANTE elegem o foro da comarca de 
        {{ $empresa->cidade->descricao }}/{{ $empresa->uf }} para dirimir quaisquer
        dúvidas oriundas do presente contrato.
    </div>
    @if (@$cliente->clienteConvenio->comissao == '' or @$cliente->clienteConvenio->comissao == null or @$cliente->clienteConvenio->comissao == 0)

    @elseif(@$cliente->clienteConvenio->comissaodestino == 1)
    <div class="margBottom_10">CLÁUSULA DÉCIMA PRIMEIRA: A CONTRATADA cederá desconto de 
        {{requestPercentualOracle(@$cliente->clienteConvenio->comissao)}} para o colaborador da CONTRATANTE em cada compra 
        realizada através do convênio.
    </div>
    @elseif(@$cliente->clienteConvenio->comissaodestino == 2)
    <div class="margBottom_10">CLÁUSULA DÉCIMA PRIMEIRA: A CONTRATADA cederá desconto de 
        {{requestPercentualOracle(@$cliente->clienteConvenio->comissao)}} para a CONTRATANTE em cada compra 
        realizada através do convênio.
    </div>
    @endif

    <div class="margBottom_10">Por ser expressão da verdade, firmam o presente instrumento de contrato CONTRATADA e CONTRATANTE em duas vias de
        igual teor e forma, para um só efeito, na presença das testemunhas abaixo.
    </div>
    <span class="fontSize_12 magTop_20">{{ $empresa->cidade->descricao }}, {{$dataAtual}}</span>
    <div class="fontSize_15 margLeft_40" style="margin-right: 8%; padding-top: 50px">
        <div style="width:45%; border-top: 1px solid black; float:left">
            <div class="negrito" style="text-align: center;">CONTRATADA</div>
        </div>
        <div style="width:45%; border-top: 1px solid black; float:right">
            <div class="negrito" style="text-align: center;">CONTRATANTE</div>
        </div>
    </div>
    <div style="margin-bottom: 3%; padding-top: 80px; margin-left: 42%;" class="fontSize_14">TESTEMUNHAS</div>
    <div class="fontSize_13" style="padding-top: 50px">
        <div style="width:35%; border-top: 1px solid black; float:left;">
            <div  style="text-align: center;">NOME</div>
        </div>
        <div style="width:35%; border-top: 1px solid black; float:left; margin: 0 18px">
            <div  style="text-align: center;">ASSINATURA</div>
        </div>
        <div style="width:25%; border-top: 1px solid black; float:right">
            <div  style="text-align: center;">RG/CPF</div>
        </div>
        <br />
        <br />
        <div style="padding-top: 50px">
            <div style="width:35%; border-top: 1px solid black; float:left;">
                <div  style="text-align: center;">NOME</div>
            </div>
            <div style="width:35%; border-top: 1px solid black; float:left; margin: 0 17px;">
                <div  style="text-align: center;">ASSINATURA</div>
            </div>
            <div style="width:25%; border-top: 1px solid black; float:right">
                <div  style="text-align: center;">RG/CPF</div>
            </div>
        </div>
    </div>
</div>