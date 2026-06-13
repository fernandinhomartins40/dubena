
@extends('layouts.mainmenu')
@section('content')
<script src='{{url("js/lib/collection.js")}}'></script>
<script src='{{url("js/estoquetransferencias.js")}}'></script>
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">

            @if(isset($estoqueTransferencia))
            {{ Form::model($estoqueTransferencia, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'route' => array('estoquetransferencias.update', $estoqueTransferencia->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'estoquetransferencias.store', 'class' => 'form-horizontal']) }}
            @endif
            <ul><div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Transferência de Estoque</h3>
                </div><!-- /.box-header -->
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab_1">
                            <!-- form start -->
                            <div class="row">
                                <div id="tabCadastro" class="col-md-10">
                                    <div class="box-body">
                                        <div class="form-group crud_space col-sm-12">
                                            {{ Form::label('data', 'Data:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;']) }}
                                            <div class="col-sm-4">
                                                <div class="input-group date generalDateTimePicker" id="datetimepicker1">
                                                    {{ Form::datetime('datahora',requestDataOracle(@$estoqueTransferencia->datahora),['class'=>'form-control input-sm generalDateTimePicker']) }}
                                                    <span class="input-group-addon">
                                                        <span class="glyphicon glyphicon-calendar"></span>
                                                    </span>
                                                </div>
                                            </div>
                                            {{ Form::label('colaborador', 'Colaborador:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                            <div class="col-sm-4">
                                                {{ Form::text('user_id', $colaborador, array('class'=>'form-control input-sm', 'disabled'))}}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space col-sm-12">
                                            {{ Form::label('origemsetor_id', 'Setor Origem:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                            <div class="col-sm-4">
                                                {{ Form::select('origemsetor_id', $setorOrigem , null,['class'=>'form-control input-sm selectChosen', 'onChange' => 'atualizarProdutos()']) }}
                                            </div>
                                            {{ Form::label('destinosetor_id', 'Setor Destino:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                            <div class="col-sm-4">
                                                {{ Form::select('destinosetor_id', $setorDestino, null, ['class'=>'form-control input-sm selectChosen']) }}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space col-sm-12">
                                            {{ Form::label('observacoes', 'Observações:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                            <div class="col-sm-10">
                                                {{ Form::textarea('observacoes',null,['rows'=>'2', 'class'=>'form-control input-sm', 'id'=>'observacoes']) }}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space col-sm-12 onlyCreate">
                                            {{ Form::label('produto', 'Produto:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                            <div class="col-sm-4">
                                                {{ Form::select('produto', $produtosEstoque, null, ['class' => 'form-control selectChosen']) }}
                                            </div>
                                            {{ Form::label('quantidade', 'Quantidade:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                            <div class="col-sm-2">
                                                {{ Form::text('quantidade', null, ['class' => 'form-control mask4Decimal']) }}
                                            </div>
                                            <div class="col-sm-2">
                                                <button type="button" onclick="buscaQuantidadeProduto('{{url('consultaestoquesetor/buscaquantidadeproduto/:produto_id/:setor_id')}}')" id="addProdutos" class='btn btn-nw-buscas btn-xs' >
                                                    Adicionar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-8 col-sm-push-3">
                                        <hr class='thin'>
                                        <table id="tblProdutosTransferencia" class="table table-bordered table-hover table-condensed  bg-success">
                                            <thead>
                                                <tr>
                                                    <th style="width:75px">C&oacute;digo</th>
                                                    <th style="width:300px">Produto</th>
                                                    <th style="width:75px">Qtde</th>
                                                    @if(!isset($estoqueTransferencia))
                                                    <th style="width:50px;">Operação</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody id="tbodyProdutosList" name="tbodyProdutosList">
                                                @if(isset($estoqueTransferencia))
                                                @foreach($estoqueTransferencia->estoqueTransferenciaItem as $item)
                                                <tr>
                                                    <td>{{$item->produto_id}}</td>
                                                    <td>{{$item->produto->descricao}}</td>
                                                    <td>{{$item->quantidade}}</td>
                                                </tr>
                                                @endforeach
                                                @endif
                                            </tbody>
                                            {{ Form::hidden('produtos', null, ['class' => 'form-control', 'id' => 'produtos']) }}
                                            {{ Form::text('setor_id', null, ['class' => 'form-control hidden', 'id' => 'setor_id']) }}
                                        </table>
                                    </div>
                                </div>

                            </div><!-- /.tab-pane -->

                        </div><!-- /.tab-pane -->
                    </div><!-- /.tab-content -->
                    <div class="box-footer">
                        <div class="col-md-4">
                            {{ Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) }}
                            <a type="button" href="{{url('estoquetransferencias')}}" class="btn btn-nw-geral">Voltar</a>
                        </div>
                    </div>
                </div>
            </div>
        </ul><!-- /.col -->
        {{ Form::close() }}
    </div>
</div>
</div>

<script>
    var listaProdutos = '';
    var qdeEidProdutos = '';
    var produtoId = '';
    var urlProduto = '{{ url("produto/ajax/:id") }}';
    var urlBuscaProdutosAjax = '{{ url("produto/buscaporsetor/:id") }}';
    setTimeout(function() {
        @if($errors -> any())
        carregarProdutosErro();
        @endif
        @if (isset($show))
        desativarInputs();
        $(".onlyCreate").hide();
        @endif
    }, $(document).ready);
</script>
@endsection
