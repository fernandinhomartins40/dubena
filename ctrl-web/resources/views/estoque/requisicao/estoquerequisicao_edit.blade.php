@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            {{ Form::model($estoqueRequisicao, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'route' => array('estoquerequisicao.update', $estoqueRequisicao->id))) }}
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Requisição de Estoque</h3>
                    </div><!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            <div class="form-group crud_space col-sm-12">
                                                {{ Form::label('data', 'Data:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;']) }}
                                                <div class="col-sm-3">
                                                    <div class="input-group date generalDateTimePickerDefaultDateFalse" >
                                                        {{ Form::datetime('datahora',null,['class'=>'form-control input-sm generalDateTimePickerDefaultDateFalse']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('colaborador', 'Colaborador:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::text('user_id', $colaborador, array('class'=>'form-control input-sm', 'disabled'))}}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space col-sm-12">
                                                {{ Form::label('centrocusto_id', 'Centro Custo:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{Form::hidden('centrocusto_id',$centrocusto_id, ['id'=>'centrocusto_id'])}}
                                                    <div class="input-group">
                                                        {{ Form::text('centrocusto_descricao',$centrocusto_descricao,['id'=>'centrocusto_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
                                                        <span class="input-group-btn">
                                                            <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcusto" onclick="abrirCentroCusto();">Mudar</button>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('planoconta_id', 'Plano Conta:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{Form::hidden('planoconta_id',$planoconta_id, ['id'=>'planoconta_id'])}}
                                                    <div class="input-group">
                                                        {{ Form::text('planoconta_descricao',$planoconta_descricao,['id'=>'planoconta_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
                                                        <span class="input-group-btn">
                                                            <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPconta" onclick="abrirPlanoConta();">Mudar</button>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space col-sm-12">
                                                {{ Form::label('observacoes', 'Observações:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-8">
                                                    {{ Form::textarea('observacoes',$observacoes,['rows'=>'2', 'class'=>'form-control input-sm', 'id'=>'observacoes']) }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group crud_space col-sm-8 col-sm-push-2">
                                            <hr class='thin'>
                                            <table id="tblListaProdutos" class="table table-bordered table-hover table-condensed  ">
                                                <thead>
                                                    <tr>
                                                        <th style="width:25px">C&oacute;digo</th>
                                                        <th style="width:200px">Produto</th>
                                                        <th style="width:100px">Setor</th>
                                                        <th style="width:20px">Qtde</th>
                                                        <th style="width:20px">Custo</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tbodyProdutosList" name="tbodyProdutosList">
                                                    @foreach ($estoquerequisicaoitems as $estoquerequisicaoitem)
                                                    <tr id="estoquerequisicao{{$estoquerequisicaoitem->id}}">
                                                        <td>{{$estoquerequisicaoitem->id}}</td>
                                                        <td>{{$estoquerequisicaoitem->produto->descricao}}</td>
                                                        <td>{{$estoquerequisicaoitem->setor->descricao}}</td>
                                                        <td>{{$estoquerequisicaoitem->quantidade}}</td>
                                                        <td>{{requestNumeroDecimalOracle($estoquerequisicaoitem->customedio)}}</td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div><!-- /.tab-pane -->
                            </div><!-- /.tab-pane -->
                        </div><!-- /.tab-content -->
                        <div class="box-footer">
                            <div class="col-md-4">
                                {{ Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) }}
                                <a id="buttonBack" type="button" href="{{url('estoquerequisicao')}}" class="btn btn-nw-geral">Voltar</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{ Form::close() }}
        </ul><!-- /.col -->
    </div>
</div>
@include('financeiro.centrocustos_partial1_js')
@include('financeiro.planocontas_partial1_js')
@include('financeiro.centrocustos_partial2_js')
@include('financeiro.planocontas_partial2_js')
@include('financeiro.centrocustos_partial1')
@include('financeiro.planocontas_partial1')
<script src="{{ asset('js/estoquerequisicao.js') }}"></script>
<script type="text/javascript">
    $(document).ready(function() {
        @if(isset($show))
        desativarInputs();
        $(".btn-nw-buscas").prop('disabled', true);
        @endif
    });
</script>
@endsection
