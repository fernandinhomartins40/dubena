@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($vendaativa))
            {{ Form::model($vendaativa, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files'=> true, 'route' => array('vendaativa.update', $vendaativa->id))) }}
            @else 
            {{ Form::open(['id'=>'fmCadastro','route'=> 'vendaativa.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="header panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Venda Ativa</h3>
                        </div>
                    </div><!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li id="li-tab_1" class="active"><a href="#tab_1" data-toggle="tab">Endereço</a></li>
                            <li id="li-tab_2" class=""><a href="#tab_2" data-toggle="tab">Não compram a X dias</a></li>
                            <li id="li-tab_3" class=""><a href="#tab_3" data-toggle="tab">Com previsão de giro</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {{ Form::label('cidade_id', 'Cidade:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('cidade_id', $cidades, null, ['id'=>'cidade_id', 'class' => 'form-control selectChosen']) }}
                                                </div>
                                                {{ Form::label('bairro_id', 'Bairro:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('bairro_id', [], null, ['id'=>'bairro_id', 'class' => 'form-control selectChosen']) }}
                                                </div>
                                                {{ Form::label('rua_id', 'Rua:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('rua_id', [], null, ['id'=>'rua_id', 'class' => 'form-control selectChosen']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('setor_id', $setores, null, ['id'=>'setor_id', 'class' => 'form-control selectChosen']) }}
                                                </div>
                                                {{ Form::label('segmento_id', 'Segmento:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('segmento_id', $segmentos, null, ['id'=>'segmento_id', 'class' => 'form-control selectChosen']) }}
                                                </div>
                                                <div class="col-sm-2 col-md-push-1">
                                                    <button disabled id="btnFiltrarEndereco" type="button" class="btn btn-nw-geral btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar Clientes"><span class="fa fa-search fa-lg"></span></button>
                                                    <a disabled type="button" href="{{ route('vendaativa.create') }}" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane" id="tab_2"><!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                <div class="form-group crud_space">
                                                    {{ Form::label('cidadecompra', 'Cidade:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::select('cidadecompra', $cidades, @$cidade_empresa, ['id'=>'cidadecompra', 'class' => 'form-control selectChosen']) }}
                                                    </div>
                                                    {{ Form::label('bairrocompra', 'Bairro:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::select('bairrocompra', [], null, ['id'=>'bairrocompra', 'class' => 'form-control selectChosen']) }}
                                                    </div>
                                                    {{ Form::label('ruacompra', 'Rua:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::select('ruacompra', [], null, ['id'=>'ruacompra', 'class' => 'form-control selectChosen']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('setorcompra', 'Setor:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::select('setorcompra', $setores, null, ['id'=>'setorcompra', 'class' => 'form-control selectChosen']) }}
                                                    </div>
                                                    {{ Form::label('segmentocompra', 'Segmento:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::select('segmentocompra', $segmentos, null, ['id'=>'segmentocompra', 'class' => 'form-control selectChosen']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('naocompra', 'Não Compram a(dias):', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('naocompra', null, ['id'=>'naocompra', 'class' => 'form-control input-sm  number']) }}
                                                    </div>
                                                    {{ Form::label('temcompras', 'Tem compras a(dias):', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('temcompras', null, ['id'=>'temcompras', 'class' => 'form-control input-sm  number']) }}
                                                    </div>
                                                    <div class="col-sm-2">
                                                        <button disabled id="btnFiltrarCompram" type="button" class="btn btn-nw-geral btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar Clientes"><span class="fa fa-search fa-lg"></span></button>
                                                        <a disabled type="button" href="{{ route('vendaativa.create') }}?tab=2" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane" id="tab_3"><!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {{ Form::label('cidademedia', 'Cidade:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('cidademedia', $cidades, @$cidade_empresa, ['id'=>'cidademedia', 'class' => 'form-control selectChosen']) }}
                                                </div>
                                                {{ Form::label('bairromedia', 'Bairro:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('bairromedia', [], null, ['id'=>'bairromedia', 'class' => 'form-control selectChosen']) }}
                                                </div>
                                                {{ Form::label('setormedia', 'Setor:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('setormedia', $setores, null, ['id'=>'setormedia', 'class' => 'form-control selectChosen']) }}
                                                </div>
                                            </div>  
                                            <div class="form-group crud_space">
                                                {{ Form::label('mediagiro', 'Média de giro até:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group generalDatePicker">
                                                        {{ Form::text('mediagiro',null,['id'=>'mediagiro','class'=>'form-control input-sm ']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('segmentomedia', 'Segmento:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('segmentomedia', $segmentos, null, ['id'=>'segmentomedia', 'class' => 'form-control selectChosen']) }}
                                                </div>
                                                <div class="col-sm-2 col-md-push-1">
                                                    <button disabled id="btnFiltrarComMedia" type="button" class="btn btn-nw-geral btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar Clientes"><span class="fa fa-search fa-lg"></span></button>
                                                    <a disabled type="button" href="{{ route('vendaativa.create') }}?tab=3" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></a>
                                                </div>
                                            </div>  
                                            <div class="form-group crud_space">
                                                {{ Form::label('mediamesantes', 'Média meses antes:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-1">
                                                    {{ Form::text('mediamesantes', null, ['id'=>'mediamesantes', 'class' => 'form-control input-sm  number']) }}
                                                </div>
                                                {{ Form::label('compraentre', 'Compra entre(dias):', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-1">
                                                    {{ Form::text('compraentre', null, ['id'=>'compraentre', 'class' => 'form-control input-sm  number']) }}
                                                </div>
                                            </div>  
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div id="tabCadastro" class="col-md-12">
                                    <div class="box-body">
                                        <div class="form-group crud_space">
                                            <div class="col-md-6" style="margin-left:45px;" id="">
                                                <p style="font-style:italic;font-weight:bold;"><span id="filtrosusados">{{@$vendaativa->descricaofiltro}}</span></p>
                                            </div>
                                            <div class="col-sm-5 col-md-push-1">
                                                <div id="checkbox">
                                                    {{ Form::label('ativo', 'Ativo', ['class'=>'col-md-1 control-label input-sm']) }}
                                                    <div class="col-md-1 checkbox">
                                                        {{ Form::checkbox('ativo',1) }}
                                                    </div>
                                                </div>
                                                <button class="btn btn-nw-buscas btn-sm" type='button' id='btnGerarPedido'>Gerar Pedido</button>
                                                <button class="btn btn-nw-buscas btn-sm" type='button' id='btnGerarOcorrencia'>Gerar Ocorrência</button>
                                                <button class="btn btn-nw-buscas btn-sm" type='button' id='btnHistorico'>Histórico</button>
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            <div class="col-md-10 col-md-push-1" style='background-color: white'>
                                                <table id="tblPedidosFiltrado" class="no-select table table-bordered table-striped table-hover table-condensed">
                                                    <thead>
                                                        <tr>
                                                            <th>Cód. Cliente</th>
                                                            <th>usuario_id</th>
                                                            <th>usuario_name</th>
                                                            <th>Cliente</th>
                                                            <th>Endereço</th>
                                                            <th>Telefones</th>
                                                            <th>Segmento</th>
                                                            <th>Setor</th>
                                                            <th>Data Nascimento</th>
                                                            <th>Data Último Pedido</th>
                                                            <th>Previsão Próxima Compra</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="respostas-list" name="respostas-list">
                                                        @if(isset($clientes))
                                                        @foreach($clientes as $cliente)
                                                            <tr>
                                                                <td>{{$cliente->id}}</td>
                                                                <td>{{$cliente->usuario_id}}</td>
                                                                <td>{{$cliente->usuario_nome}}</td>
                                                                <td>{{$cliente->nome}}</td>
                                                                <td>{{$cliente->endereco}}</td>
                                                                <td>{{$cliente->telefones}}</td>
                                                                <td>{{$cliente->segmento}}</td>
                                                                <td>{{$cliente->setor_cliente}}</td>
                                                                <td>{{$cliente->datanascimento}}</td>
                                                                <td>{{$cliente->ultimopedido}}</td>
                                                                <td>{{isset($cliente->datarevisao) ? $cliente->datarevisao : null}}</td>
                                                            </tr>
                                                        @endforeach
                                                        @endif
                                                    </tbody>
                                                </table>
                                                <div class="col-md-12 margTop_10">
                                                    <div class="col-md-3">
                                                        <div class="col-sm-8">
                                                            <span class="info-box-icon" style="width:12px;height:12px;background-color:gray;"></span>
                                                            <span class="info-box-text fontSize_11" style="padding-left: 5px !important"> Total 
                                                                <br />
                                                                <div id="divQdeClientes"></div>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-5">
                                                        <div class="col-sm-4">
                                                            <span class="info-box-icon atrasado" style="width:12px;height:12px;"></span>
                                                            <span class="info-box-text fontSize_11" style="padding-left: 5px !important"> Ligar Novamente 
                                                                <br />
                                                                <div id="divQdeClientesNovamente"></div>
                                                            </span>
                                                        </div>
                                                        <div class="col-sm-4">
                                                            <span class="info-box-icon cancelado" style="width:12px;height:12px;"></span>
                                                            <span class="info-box-text fontSize_11" style="padding-left: 5px !important"> Ocorrência
                                                                <br />
                                                                <div id="divQdeClientesOcorrencia"></div>
                                                            </span>
                                                        </div>
                                                        <div class="col-sm-4">
                                                            <span class="info-box-icon emEntrega" style="width:12px;height:12px;"></span>
                                                            <span class="info-box-text fontSize_11" style="padding-left: 1px !important"> Pedido
                                                                <br />
                                                                <div id="divQdeClientesPedido"></div>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer" style="margin-top: -20px;">
                        <div class="col-md-4" style="margin-top: -20px;">
                            {{ Form::hidden('vendaativa_id', @$id,['id'=>'vendaativa_id']) }}
                            {{ Form::hidden('vendaativaclientes', @$ativaclientes,['id'=>'vendaativaclientes']) }}
                            {{ Form::hidden('filtro', @$filtro,['id'=>'filtro']) }}
                            {{ Form::hidden('novamente', null,['id'=>'novamente']) }}
                            {{ Form::hidden('modificados', null,['id'=>'modificados']) }}
                            {{ Form::hidden('clientes', null,['id'=>'clientes']) }}
                            {{ Form::hidden('atividade', @$vendaativa->ativo,['id'=>'atividade']) }}
                            {{ Form::submit('Gravar', ['id'=>'btngravar','class' => 'btn btn-nw-registro btn-sm'])}}
                            <a id="goback" href="{{ route('vendaativa.index') }}" type="button" class="btn btn-nw-geral btn-sm">Voltar</a>
                        </div>
                    </div>
                </div>
            </ul>
            {{Form::close()}}
        </div>
    </div>
</div>
@include('vendaativa.modals.modal_historico_cliente')
@include('vendaativa.modals.vendaativaocorrencia_modal')
@include('vendaativa.partials.js')
@endsection