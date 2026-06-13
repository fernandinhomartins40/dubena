
@extends('layouts.mainmenu')

@section('content')
<style>
    .popupModal .modal-dialog{width:53%;}
    .popupModal .modal-dialog{width:600px;}
    .btn-submit:hover {
            background-color: #a58f2a;
    }
    .btn-submit {
            display: block;
            padding: 12px;
            width: 100%;
            color: #fff;
            border: 0;
            margin-top: 40px;
            background-color: #f58f2a;
    }
    .checkbox input[type="checkbox"], .checkbox-inline input[type="checkbox"]{
            margin-left: 0px;
    }
    .dt-center { text-align: center;}
    .dt-right { text-align: right;}
</style>
<div id="mainContent" class="content">
	<div id="divCadastro" class="row">
		<div class="col-md-12">
			@if(isset($Conta))
			{{ Form::model($Conta, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('conta.update', $Conta->id))) }}
			@else
			{{ Form::open(['id'=>'fmCadastro', 'route' => 'conta.store', 'class' => 'form-horizontal', 'files' => true]) }}
			@endif
			<ul>
				<div class="nav-tabs-custom">
                                    <div class="header panel-default">
                                        <div class="panel-heading">
                                                <h3 class="panel-title">
                                                        Conta
                                                </h3>
                                        </div>
                                    </div><!-- /.box-header -->
                                    <ul class="nav nav-tabs" id="mainNav">
                                        <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                                        <li class=""><a href="#tab_2" data-toggle="tab">Usuários Permitidos</a></li>
                                        <li class=""><a href="#tab_3" data-toggle="tab">Boleto</a></li>
                                        <li class=""><a href="#tab_4" data-toggle="tab">Cheque</a></li>
                                        @if(isset($Conta))
                                        <li class=""><a href="#tab_5" data-toggle="tab">Configuração Extrato (OFX)</a></li>
                                        @endif
                                    </ul>
                                    <div class="tab-content">
                                        <div class="tab-pane active" id="tab_1">
                                                <!-- form start -->
                                            <div class="row">
                                                <div id="tabCadastro" class="col-md-10">
                                                    <div class="box-body">
                                                        <div class="form-group crud_space">
                                                            {{Form::hidden('banco_id_erro',"", ['id'=>'banco_id_erro'])}}
                                                            {{Form::hidden('banco_descricao_erro',"", ['id'=>'banco_descricao_erro'])}}
                                                            {{Form::hidden('banco_id_erro_corresp',"", ['id'=>'banco_id_erro_corresp'])}}
                                                            {{Form::hidden('banco_descricao_erro_corresp',"", ['id'=>'banco_descricao_erro_corresp'])}}
                                                            {!! Form::label('contatipo_id', 'Tipo:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                            <div class="col-sm-4">
                                                                    {!! Form::select('contatipo_id', $tipos, null, ['class' => 'form-control  selectChosen', 'onchange'=>'mudarTipoConta();']) !!}
                                                            </div>
                                                        </div>
                                                        <div class="form-group crud_space" id="divContaBanco1">
                                                            {!! Form::label('banco_id', 'Banco:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                            <div class="col-sm-6">
                                                                @if(isset($Conta) && $Conta->banco != null)
                                                                    <select id="searchbox" name="banco_id" placeholder="Buscar banco" class="form-control" style="float:left;width:100%;" value="" data-selectize-value = '[{"id":{{$Conta->banco->id}},"descricao":"{{$Conta->banco->descricao}}"}]'></select>
                                                                @else
                                                                    <select id="searchbox" name="banco_id" placeholder="Buscar banco" class="form-control" style="float:left;width:100%;" value="" data-selectize-value = '[]'></select>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        @if(isset($Conta) && $Conta->boletoemite)
                                                            <div class="form-group crud_space"  id="divContaBanco2">
                                                                {!! Form::label('agencia', 'Agência:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                                <div class="col-sm-2">
                                                                    {!! Form::text('agencia',null,['class'=>'form-control input-sm', 'id'=>'agencia', 'readonly' => 'readonly', 'tab-index' => '-1']) !!}
                                                                </div>
                                                            </div>
                                                            <div class="form-group crud_space">
                                                                {!! Form::label('conta', 'Número Conta:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                                <div class="col-sm-2">
                                                                    {!! Form::text('conta',null,['class'=>'form-control input-sm', 'id'=>'conta', 'readonly' => 'readonly', 'tab-index' => '-1']) !!}
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div class="form-group crud_space"  id="divContaBanco2">
                                                                {!! Form::label('agencia', 'Agência:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                                <div class="col-sm-2">
                                                                    {!! Form::text('agencia',null,['class'=>'form-control input-sm', 'id'=>'agencia']) !!}
                                                                </div>
                                                            </div>
                                                            <div class="form-group crud_space">
                                                                {!! Form::label('conta', 'Número Conta:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                                <div class="col-sm-2">
                                                                    {!! Form::text('conta',null,['class'=>'form-control input-sm', 'id'=>'conta']) !!}
                                                                </div>
                                                            </div>
                                                        @endif
                                                        <div class="form-group crud_space">
                                                            {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                            <div class="col-sm-5">
                                                                {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id'=>'descricao']) !!}
                                                            </div>
                                                        </div>
                                                        <div class="form-group crud_space">
                                                            {!! Form::label('saldoinicial', 'Saldo Inicial:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                            <div class="col-sm-2">
                                                                {!! Form::text('saldoinicial',null,['class'=>'form-control input-sm '.(isset($Conta)?'':'dinheiro'), 'id'=>'saldoinicial', (isset($Conta)?'readonly':'')]) !!}
                                                            </div>
                                                        </div>
                                                        <div class="form-group crud_space">
                                                            {!! Form::label('saldoatual', 'Saldo Atual:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                            <div class="col-sm-2">
                                                                {!! Form::text('saldoatual',null,['class'=>'form-control input-sm '.(isset($Conta)?'':'dinheiro'), 'id'=>'saldoatual', (isset($Conta)?'readonly':'')]) !!}
                                                            </div>
                                                        </div>
                                                        <div class="form-group crud_space">
                                                            {!! Form::label('ativo', 'Ativo:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                            <div class="col-sm-3 checkbox">
                                                                {{ Form::checkbox('ativo') }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div><!-- /.tab-pane -->
                                        </div><!-- /.tab-pane -->
                                        <div class="tab-pane" id="tab_2">
                                                <!-- form start -->
                                            <div class="row">
                                                <div id="tabCadastro" class="col-md-10 col-sm-offset-1">
                                                    <div class="box-body">
                                                        <div class="panel panel-default">
                                                            <div class="panel-heading">Adicionar/Atualizar Usuário</div>
                                                            <div class="panel-body">
                                                                <div class="form-group col-md-4">
                                                                    {!! Form::select('user_id', $users, null, ['id'=>'user_id', 'class' => 'form-control', 'style'=>'border-radius: 6px ! important;']) !!}
                                                                </div>
                                                                <div class="col-sm-1" style="text-align:center;margin-top:-10px;">
                                                                    {!! Form::label('visualizar', 'Visualizar', ['class'=>'control-label input-sm']) !!}
                                                                    <div class="checkbox">
                                                                        <input type="checkbox" id="visualizar" class="checkbox-inline" style="margin-left: 0px;float:right;"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-1" style="text-align:center;padding-left:30px;margin-top:-10px;">
                                                                    {!! Form::label('operar', 'Operar', ['class'=>'control-label input-sm']) !!}
                                                                    <div class="checkbox">
                                                                        <input type="checkbox" id="operar" class="checkbox-inline" style="margin-left: 5px;float:right;"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-1" style="text-align:center;padding-left:50px;margin-top:-10px;">
                                                                    {!! Form::label('transferir', 'Transf.p/', ['class'=>'control-label input-sm', 'checked']) !!}
                                                                    <div class="checkbox">
                                                                        <input type="checkbox" id="transferir" class="checkbox-inline" style="margin-left: 15px;float:right;"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-1" style="text-align:center;padding-left:50px;margin-top:-10px;">
                                                                    {!! Form::label('estornar', 'Estornar', ['class'=>'control-label input-sm', 'checked']) !!}
                                                                    <div class="checkbox">
                                                                        <input type="checkbox" id="estornar" class="checkbox-inline" style="margin-left: 15px;float:right;"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-2" style="text-align:center;padding-left:50px;margin-top:-10px;">
                                                                    {!! Form::label('lancarfechado', 'Retroativo', ['class'=>'control-label input-sm', 'checked']) !!}
                                                                    <div class="checkbox">
                                                                        <input type="checkbox" id="lancarfechado" class="checkbox-inline" style="margin-left: 0px;float:right;"/>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-1" style="text-align:center;padding-left:30px;margin-top:10px;">
                                                                    <button type="button" id="btnAddUser" class="btn btn-xs btn-nw-buscas" onclick="addUser();">Vincular/Atualizar</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-12">
                                                            {{Form::hidden('users',"", ['id'=>'users'])}}
                                                            <table id="tblUsers" class="table table-bordered table-hover table-condensed">
                                                                <thead>
                                                                    <tr>
                                                                        <th></th>
                                                                        <th>Nome</th>
                                                                        <th>Visualizar</th>
                                                                        <th>Operar</th>
                                                                        <th>Transferir</th>
                                                                        <th>Estornar</th>
                                                                        <th>Lançar retroativo</th>
                                                                        <th>Operação</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="users-list" name="users-list">
                                                                    @if(isset($Conta))
                                                                    @foreach ($Conta->ContaUsers()->get() as $user)
                                                                    <tr id="fone{{$user->id}}">
                                                                        <td>{{$user->user->id}}</td>
                                                                        <td>{{$user->user->name}}</td>
                                                                        <td>{{$user->visualizar==1?'Sim':'Não'}}</td>
                                                                        <td>{{$user->operar==1?'Sim':'Não'}}</td>
                                                                        <td>{{$user->transferir==1?'Sim':'Não'}}</td>
                                                                        <td>{{$user->estornar==1?'Sim':'Não'}}</td>
                                                                        <td>{{$user->lancarfechado==1?'Sim':'Não'}}</td>
                                                                        <td><button type='button' class='btn btn-nw-registro btn-xs btnRemover' id='btnRemoverUser'>Remover</button></td>
                                                                    </tr>
                                                                    @endforeach
                                                                    @endif
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div><!-- /.box -->
                                                </div>
                                            </div>
                                        </div><!-- /.tab-pane -->
                                        <div class="tab-pane" id="tab_3">
                                                <!-- form start -->
                                            <div class="row">
                                                <div id="tabCadastroBoleto" class="col-md-12">
                                                    <div class="box-body">
                                                        <div class="form-group crud_space">
                                                            {!! Form::label('boletoemite', 'Emite boleto:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                            <div class="col-sm-1 checkbox">
                                                                {{ Form::checkbox('boletoemite') }}
                                                            </div>
                                                            {!! Form::label('boletocomprovanteentrega', 'Imprime comprovante entrega:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                            <div class="col-sm-1 checkbox">
                                                                {{ Form::checkbox('boletocomprovanteentrega') }}
                                                            </div>
                                                        </div>
                                                        <div class="form-group crud_space">
                                                            {!! Form::label('layoutbanco_id', 'Layout de Cobrança:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                            <div class="col-sm-3">
                                                                {{ Form::select('layoutbanco_id', $layouts, null, ['class' => 'selectChosen', 'id' => 'layoutbanco_id']) }}
                                                            </div>
                                                        </div>
                                                        <div class="form-group crud_space">
                                                            {!! Form::label('boletosequencia', 'Último boleto:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                            <div class="col-sm-1">
                                                                {!! Form::text('boletosequencia',null,['id'=>'boletosequencia', 'class'=>'form-control input-sm number']) !!}
                                                            </div>
                                                            {!! Form::label('boletoremessasequencia', 'Última remessa:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                            <div class="col-sm-1">
                                                                {!! Form::text('boletoremessasequencia',null,['id'=>'boletoremessasequencia', 'class'=>'form-control input-sm number']) !!}
                                                            </div>
                                                            {!! Form::label('boletocarteira', 'Carteira:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                            <div class="col-sm-1">
                                                                {!! Form::text('boletocarteira',null,['id'=>'boletocarteira', 'class'=>'form-control input-sm', 'maxlength'=>'3']) !!}
                                                            </div>
                                                            {!! Form::label('boletoespecie', 'Espécie:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                            <div class="col-sm-1">
                                                                {!! Form::text('boletoespecie',null,['id'=>'boletoespecie', 'class'=>'form-control input-sm', 'maxlength'=>'5']) !!}
                                                            </div>
                                                        </div>
                                                        <div class="form-group crud_space">
                                                            {!! Form::label('boletoaceite', 'Aceite:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                            <div class="col-sm-1">
                                                                {!! Form::text('boletoaceite',null,['id'=>'boletoaceite', 'class'=>'form-control input-sm', 'maxlength'=>'5']) !!}
                                                            </div>
                                                            {!! Form::label('boletobyte', 'Byte/prefixo:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                            <div class="col-sm-1">
                                                                {!! Form::text('boletobyte',null,['id'=>'boletobyte', 'class'=>'form-control input-sm number']) !!}
                                                            </div>
                                                            {!! Form::label('boletocedente', 'Cedente:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                            <div class="col-sm-1">
                                                                {!! Form::text('boletocedente',null,['id'=>'boletocedente', 'class'=>'form-control input-sm']) !!}
                                                            </div>
                                                            {!! Form::label('boletocedentedigito', 'Dígito:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                            <div class="col-sm-1">
                                                                {!! Form::text('boletocedentedigito',null, ['id'=>'boletocedentedigito', 'class'=>'form-control input-sm', 'maxlength'=>'2']) !!}
                                                            </div>
                                                        </div>
                                                        <div class="form-group crud_space">
                                                            <span id="span_protesto_baixadevolucao">
                                                                {!! Form::label('boletoprotesto_baixadevolucao', 'Instrução:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                                <div class="col-sm-2">
                                                                    {{ Form::select('boletoprotesto_baixadevolucao', ['' => 'Selecione', 0 => 'Baixa/Devolução', 1 => 'Protesto'], null, ['class' => 'selectChosen']) }}
                                                                </div>
                                                            </span>
                                                            {!! Form::label('boletodiasprotesto', '', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                            <div class="col-sm-1">
                                                                {!! Form::text('boletodiasprotesto',null,['id'=>'boletodiasprotesto', 'class'=>'form-control input-sm number']) !!}
                                                            </div>
                                                            {!! Form::label('boletomulta', '% Multa:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                            <div class="col-sm-1">
                                                                {!! Form::text('boletomulta',null,['id'=>'boletomulta', 'class'=>'form-control input-sm percentagem']) !!}
                                                            </div>
                                                            {!! Form::label('boletojuros', '% Juros/dia:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                            <div class="col-sm-1">
                                                                {!! Form::text('boletojuros',number_format(@$Conta->boletojuros, 3, ',', '.') . ' %',['id'=>'boletojuros', 'class'=>'form-control input-sm percentagemQuatroDig']) !!}
                                                            </div>
                                                        </div>
                                                        <div class="form-group crud_space">
                                                            {!! Form::label('boletoinstrucoes', 'Instruções:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                            <div class="col-sm-7">
                                                                {!! Form::textarea('boletoinstrucoes',null,['class'=>'form-control input-sm', 'rows'=>'3']) !!}
                                                            </div>
                                                        </div>
                                                        <div class="form-group crud_space">
                                                            {!! Form::label('boletocorrespondente', 'Correspondente:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                            <div class="col-sm-1 checkbox">
                                                                {{ Form::checkbox('boletocorrespondente') }}
                                                            </div>
                                                            <div class="col-sm-3">
                                                                <select id="searchboxcorresp" name="boletocorrespondentebanco_id"
                                                                        placeholder="Buscar correspondente"
                                                                        data-selectize-value='[{"id":{{isset($Conta->correspondente) ? $Conta->correspondente->id : ""}},"descricao":"{{isset($Conta->correspondente) ? $Conta->correspondente->descricao: ""}}"}]'></select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div><!-- /.tab-pane -->
                                        <div class="tab-pane" id="tab_4">
                                                <!-- form start -->
                                            <div class="row">
                                                <div id="tabCadastro" class="col-md-8 col-md-offset-2">
                                                    <div class="box-body">
                                                        <div class="panel panel-default">
                                                            <div class="panel-heading">Adicionar Talão</div>
                                                            <div class="panel-body">
                                                                <div class="col-sm-3" style="text-align:right;">
                                                                    {!! Form::label('chequenuminicial', 'Número Inicial:', ['class'=>'control-label input-sm']) !!}
                                                                </div>
                                                                <div class="col-sm-2" style="text-align:left;">
                                                                    <input type="text" class="number input-sm form-control" id="chequenuminicial"/>
                                                                </div>
                                                                <div class="col-sm-3" style="text-align:right;">
                                                                    {!! Form::label('chequenumfinal', 'Número Final:', ['class'=>'control-label input-sm']) !!}
                                                                </div>
                                                                <div class="col-sm-2" style="text-align:left;">
                                                                    <input type="text" class="numbe input-sm form-control" id="chequenumfinal"/>
                                                                </div>
                                                                <div class="col-sm-2" style="text-align:right; padding-top: 3px">
                                                                    <button id="btnAddTalao" type="button" class="btn btn-nw-buscas btn-xs" onclick="addTalao();">Adicionar</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-8  col-md-offset-2">
                                                            {{Form::hidden('talaos',"", ['id'=>'talaos'])}}
                                                            <table id="tblTalaos" class="table table-bordered table-hover table-condensed">
                                                                <thead>
                                                                    <tr>
                                                                        <th></th>
                                                                        <th>Número Inicial</th>
                                                                        <th>Número Final</th>
                                                                        <th>Número Atual</th>
                                                                        <th>Operação</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="talaos-list" name="talaos-list">
                                                                    @if(isset($Conta))
                                                                    @foreach ($Conta->contaTalaos()->get() as $talao)
                                                                    <tr id="talao{{$talao->id}}">
                                                                        <td>{{$talao->id}}</td>
                                                                        <td>{{$talao->chequenuminicial}}</td>
                                                                        <td>{{$talao->chequenumfinal}}</td>
                                                                        <td>{{$talao->chequenumatual}}</td>
                                                                        <td><button type='button' class='btn btn-nw-registro btn-xs btnRemover' id='btnRemoverTalao'>Remover</button></td>
                                                                    </tr>
                                                                    @endforeach
                                                                    @endif
                                                                </tbody>
                                                            </table>
                                                        </div><!-- /.box -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div><!-- /.tab-pane -->
                                        <div class="tab-pane" id="tab_5">
                                            <div class="row">
                                                <div id="tabCadastroExtratoconfig" class="col-md-10">
                                                    <div class="box-body">
                                                        <div class="col-md-12 col-md-offset-1">
                                                            {!! Form::label('codigoofx', 'Código OFX:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                            <div class="col-sm-3">
                                                                {!! Form::text('codigoofx',null,['class'=>'form-control input-sm', 'id'=>'codigoofx']) !!}
                                                            </div>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <hr>
                                                        </div>
                                                        <div class="col-md-12 col-md-offset-2" style="margin-bottom:10px;">
                                                            <div class="col-sm-3">
                                                                <button type="button" id="btnAddExtratoconfig" class="btn btn-xs btn-nw-buscas" onclick="addExtratoconfig();">Adicionar configuração de extrato</button>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-12  col-md-offset-1">
                                                            {{ Form::hidden('versoes',"", ['id'=>'versoes']) }}
                                                            <table id="tblExtratoconfig" class="table table-bordered table-hover table-condensed">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Id</th>
                                                                        <th>Descrição</th>
                                                                        <th>Ação Id</th>
                                                                        <th>Ação</th>
                                                                        <th>Cliente Id</th>
                                                                        <th>Cliente</th>
                                                                        <th>Cond.Pagto Id</th>
                                                                        <th>Cond.Pagto</th>
                                                                        <th>P. Contas Id</th>
                                                                        <th>P. Contas</th>
                                                                        <th>C. Custo Id</th>
                                                                        <th>C. Custo</th>
                                                                        <th>Tipo Movto Id</th>
                                                                        <th>Tipo Movto</th>
                                                                        <th>Conta Origem/Destino Id</th>
                                                                        <th>Conta Origem/Destino</th>
                                                                        <th style='width: 12%'>Operação</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="versoes-list" name="versoes-list">
                                                                    @if(isset($Conta))
                                                                    @foreach ($Conta->contaExtratoconfigs() as $extratoconfig)
                                                                    <tr id="doc{{$extratoconfig->id}}">
                                                                        <td>{{$extratoconfig->id}}</td>
                                                                        <td>{{$extratoconfig->descricao}}</td>
                                                                        <td>{{$extratoconfig->acao}}</td>
                                                                        <td>{{$extratoconfig->acaodescricao()}}</td>
                                                                        <td>{{@$extratoconfig->cliente_id}}</td>
                                                                        <td>{{@$extratoconfig->cliente->nome}}</td>
                                                                        <td>{{@$extratoconfig->condicaopagamento_id}}</td>
                                                                        <td>{{@$extratoconfig->condicaoPagamento->descricao}}</td>
                                                                        <td>{{@$extratoconfig->planoconta_id}}</td>
                                                                        <td>{{@$extratoconfig->planoConta->descricao}}</td>
                                                                        <td>{{@$extratoconfig->centrocusto_id}}</td>
                                                                        <td>{{@$extratoconfig->centroCusto->descricao}}</td>
                                                                        <td>{{@$extratoconfig->contamovimentotipo_id}}</td>
                                                                        <td>{{@$extratoconfig->contaMovimentoTipo->descricao}}</td>
                                                                        <td>{{@$extratoconfig->contaorigem_id}}</td>
                                                                        <td>{{@$extratoconfig->contaOrigem->descricao}}</td>
                                                                        <td><button type='button' onclick="editarExtratoconfig({{$extratoconfig->id}})" class='btnEditarExtratoconfig btn btn-nw-geral btn-xs' id='btnEditarExtratoconfig'><span class="fa fa-pencil-square-o fa-lg" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar Versão"></span></button>
                                                                            <button type='button' onclick="removerExtratoconfig({{$extratoconfig->id}}, '{{$extratoconfig->descricao}}')" class='btn btn-nw-registro btn-xs' id='btnRemoverExtratoconfig' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover Versão"><span class="fa fa-trash fa-lg"></span></button>
                                                                        </td>
                                                                    </tr>
                                                                    @endforeach
                                                                    @endif
                                                                </tbody>
                                                            </table>
                                                        </div><!-- /.box -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div><!-- /.tab-pane -->
                                    </div><!-- /.tab-content -->
                                    <div class="box-footer">
                                        <div class="col-md-4">
                                            {!! Form::submit('Gravar', ['class' => 'btn btn btn-nw-registro']) !!}
                                            <a type="button" href="{!!url('conta')!!}" class="btn btn-nw-geral">Voltar</a>
                                        </div>
                                        <div class="fright">
                                            <i>Pressione F1 para obter ajuda.</i>
                                        </div>
                                    </div>
				</div>
				{!! Form::close() !!}
			</ul><!-- /.col -->
		</div>
	</div>
</div>

<div class="modal fade" id="modal_ajuda" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabelCadastro">Ajuda</h4>
            </div>
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12">
                        <div class="col-sm-12">
                        	<strong class="fontSize_15">Lista de palavras chave das instruções para boleto</strong><br /><br />
	                    	<table class="table table-bordered table-hover table-condensed margTop_40" style="padding:0px; margin:0px">
	                            <thead>
	                                <tr>
	                                    <th>Palavra Chave</th>
	                                    <th>Substituída pelo(a)</th>
	                                </tr>
	                            </thead>
	                            <tbody>
	                                <tr>
	                                    <td>#multa</td>
	                                    <td>Valor aplicado com base no percentual do campo "% Multa"</td>
	                                </tr>
	                                <tr>
	                                    <td>#juros</td>
	                                    <td>Valor aplicado com base no percentual do campo "% Juros/dia"</td>
	                                </tr>
	                                <tr>
	                                    <td>#diasprotesto</td>
	                                    <td>Valor do campo "Protestar em (dias)"</td>
	                                </tr>
	                                <tr>
	                                    <td>#vencimento</td>
	                                    <td>Data de vencimento do boleto</td>
	                                </tr>
	                            </tbody>
	                        </table>
                        </div>
                        <div class="col-sm-12 margTop_40">
                        	<i>Atenção! Só são permitidos 2 linhas de instruções. Caso precise inserir mais, agrupe-as separando por um hífen (-).</i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseModalClientes" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                <div id="saveError" class="alert alert-danger alert-dismissable" style="display:none;">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fa fa-ban"></i>Erro</h5>
                    <div id="save_result"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="extratoconfig_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document" style="width:50%">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span
                        class="sr-only">Fechar</span></button>
                <h4 class="modal-title">Versão</h4>
            </div>
            <div class="modal-body col-md-12">
                <div class="form-horizontal">
                    <div class="col-sm-12">
                        <div class="form-group crud_space">
                            {{ Form::label('descricaoextratoconfig', 'Texto no Extrato:', ['class'=>'col-sm-3 control-label input-sm']) }}
                            <div class="col-sm-8">
                                {!! Form::text('descricaoextratoconfig',null,['id'=>'descricaoextratoconfig', 'class'=>'form-control input-sm', 'placeholder'=>'Texto']) !!}
                                <input type="hidden" id="extratoconfig_id">
                            </div>
                        </div>
                        <div class="form-group crud_space">
                            {{ Form::label('acaoextratoconfig', 'Tipo de Ação:', ['class'=>'col-sm-3 control-label input-sm']) }}
                            <div class="col-sm-5">
                                {{ Form::select('acaoextratoconfig', $extratoacoes, null,['id'=>'acaoextratoconfig','class'=>'form-control input-sm selectChosen']) }}
                            </div>
                        </div>
                        <div class="form-group crud_space extratoconfiglancar">
                            {!! Form::label('clienteextratoconfig_id', 'Cliente/Forn:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                            <div class="col-sm-8">
                                <select id="searchboxClienteExtratoconfig" name="clienteextratoconfig_id" placeholder="Buscar Cliente/Fornecedor" class="form-control" value="" data-selectize-value = '[]'></select>
                            </div>
                        </div>
                        <div class="form-group crud_space extratoconfigtransferir">
                            {!! Form::label('contaextratoconfig_id', 'Conta Origem/Destino:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                            <div class="col-sm-8">
                                {!! Form::select('contaextratoconfig_id', $contas, null, ['id'=>'contaextratoconfig_id', 'class'=>'form-control input-sm selectChosen']) !!}
                            </div>
                        </div>
                        <div class="form-group crud_space extratoconfiglancar">
                            {!! Form::label('condicaopagamentoextratoconfig_id', 'Condição Pagamento:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                            <div class="col-sm-8">
                                {!! Form::select('condicaopagamentoextratoconfig_id', $condicaopagamentos, null, ['id'=>'condicaopagamentoextratoconfig_id', 'class'=>'form-control input-sm selectChosen']) !!}
                            </div>
                        </div>
                        <div class="form-group crud_space extratoconfiglancar">
                            {!! Form::label('contamovimentotipoextratoconfig_id', 'Tipo Recebimento:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                            <div class="col-sm-8">
                                {!! Form::select('contamovimentotipoextratoconfig_id', $contamovimentotipos, null, ['id'=>'contamovimentotipoextratoconfig_id', 'class'=>'form-control input-sm selectChosen']) !!}
                            </div>
                        </div>
                        <div class="form-group crud_space extratoconfiglancar">
                            {{ Form::label('pcextratoconfig_id', 'P.Contas:', ['class'=>'col-sm-3 control-label input-sm']) }}
                            <div class="col-sm-8">
                                {{Form::hidden('pcextratoconfig_id',null, ['id'=>'pcextratoconfig_id'])}}
                                <div class="input-group">
                                    {{ Form::text('pcextratoconfig_descricao',@$pcextratoconfig_descricao,['id'=>'pcextratoconfig_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPcontaCartao" onclick="abrirPlanoConta('jstreepc4','pcextratoconfig_id','pcextratoconfig_descricao');">Mudar</button>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group crud_space extratoconfiglancar">
                            {{ Form::label('ccextratoconfig_id', 'C.Custos:', ['class'=>'col-sm-3 control-label input-sm']) }}
                            <div class="col-sm-8">
                                {{Form::hidden('ccextratoconfig_id',@$ccextratoconfig_id, ['id'=>'ccextratoconfig_id'])}}
                                <div class="input-group">
                                    {{ Form::text('ccextratoconfig_descricao',@$ccextratoconfig_desc,['id'=>'ccextratoconfig_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcextratoconfig" onclick="abrirCentroCusto('jstreecc10','ccextratoconfig_id','ccextratoconfig_descricao');">Mudar</button>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btnGravarExtratoconfig" class="btn btn-nw-registro" type="button">Gravar</button>
                <button type="button" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script src="{{URL::to('plugins/selectize/js/standalone/selectize.min.js')}}"></script>

@include('financeiro.centrocustos_partial1_js')
@include('financeiro.centrocustos_partial2_js')
@include('financeiro.centrocustos_partial1')
@include('financeiro.planocontas_partial1_js')
@include('financeiro.planocontas_partial2_js')
@include('financeiro.planocontas_partial1')

@include('conta.conta_js')

<script type="text/javascript">
    var tblExtratoconfig;
    var operacaoExtratoconfig;
    var conta_id = {{isset($Conta)?$Conta->id:'-1'}};
    var extratoconfigLancar = {{$extratoacaolancar->getValue()}};
    var extratoconfigTransferir = {{$extratoacaotransferir->getValue()}};
    var extratoacaolancarbaixar = {{$extratoacaolancarbaixar->getValue()}};
    var urlLanguage = "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}";
    var show = {{isset($show)?'true':'false'}};
    var acoes = {!! json_encode($extratoacoes) !!};
</script>
<script src="{{URL::to('js/contaextratoconfig.js')}}" type="text/javascript"></script>

@endsection
