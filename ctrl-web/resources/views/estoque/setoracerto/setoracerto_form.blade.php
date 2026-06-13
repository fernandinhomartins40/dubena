@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">

            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($estoquesetor))
            {{ Form::model($estoquesetor, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('estoquesetor.update', $estoquesetor->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'estoquesetor.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Ajuste de Estoque</h3>
                    </div>
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
                                            <div class="form-group crud_space">
                                                {!! Form::label('setor_id', 'Setor:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-4">
                                                    {!! Form::select('setor_id', $setores, @$setor_id, ['id'=>'setor_id', 'class' => 'form-control  selectChosen', 'style'=>'padding:0px;max-height:24px;']) !!}
                                                </div>
                                                {!! Form::label('produto_id', 'Produto:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-4">
                                                    {!! Form::select('produto_id', $produtos, @$produto_id, ['id'=>'produto_id', 'class' => 'form-control  selectChosen', 'style'=>'padding:0px;max-height:24px;']) !!}
                                                </div>
                                            </div>

                                            <div class="form-group crud_space">
                                                {!! Form::label('data', 'Data:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;']) !!}
                                                <div class="col-sm-3">
                                                    <div class="input-group date generalDateTimePicker" id="datetimepicker1">
                                                        {!! Form::datetime('datahora',requestDataOracle(@$estoquesetor->datahora),['class'=>'form-control input-sm generalDateTimePicker']) !!}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {!! Form::label('quantidade', 'Qde Antiga:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::hidden('quantidadeantiga',null,['id' => 'quantidadeantiga']) !!}
                                                    {!! Form::text('quantidadeatual',@$estoquesetor->quantidade,['class'=>'form-control input-sm number', 'id' => 'quantidadeatual', 'disabled']) !!}
                                                </div>
                                                {!! Form::label('quantidadenova', 'Nova Qde:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::text('quantidadenova',null,['class'=>'form-control input-sm number']) !!}
                                                </div>
                                            </div>

                                            <div class="form-group crud_space">
                                                {!! Form::label('observacao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-10">
                                                    {!! Form::text('observacao',@$estoquesetor->motivo,['class'=>'form-control input-sm']) !!}
                                                </div>
                                            </div>

                                            <div class="form-group crud_space">
                                                {!! Form::label('usuario', 'Usuário:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-6">
                                                    @if (isset($estoquesetor->user->name))
                                                    {!! Form::text('user_id',@$estoquesetor->user->name,['class'=>'form-control input-sm', 'disabled']) !!}
                                                    @else
                                                    {!! Form::text('user_id',@$user_id,['class'=>'form-control input-sm', 'disabled']) !!}
                                                    @endif
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div><!-- /.tab-pane -->
                            </div><!-- /.tab-pane -->
                            <div class="box-footer">
                                <div class="col-md-4">
                                    <span data-toggle="modal" data-target="#modalSenha">
                                        {!! Form::submit('Gravar', ['id' => 'btn_gravar', 'class' => 'btn btn-nw-registro']) !!}
                                    </span>
                                    <a type="button" href="{{url('estoquesetor')}}" class="btn btn-nw-geral">Voltar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </ul><!-- /.col -->
        </div>
        {!! Form::close() !!}
    </div>
</div>
@include('general.modal_senhamestra')
<!-- DATA TABES SCRIPT -->
<!-- page script -->
<script type="text/javascript"src="{{url('js/estoqueSetorAcerto.js')}}"></script>
<script type="text/javascript">
$(document).ready(function () {
    var urlInfoProduto = '{{ url("estoquesetor/buscaajax/:setor_id/:produto_id") }}';
    $("#produto_id").change(function () {
        buscarInfoProduto(urlInfoProduto);
    });
    $("#setor_id").change(function () {
        buscarInfoProduto(urlInfoProduto);
    });
});
    setTimeout(function () {
        @if (isset($show))
        desativarInputs();
        @endif
    }, $(document).ready());
</script>
@endsection
