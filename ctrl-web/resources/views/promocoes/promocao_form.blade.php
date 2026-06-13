@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    @if($vinculado)
    <div class="form-group crud_space">
        <div class="col-sm-10 col-sm-push-1">
            <div class="alert alert-informacao" id="info-alert">
                <button type="button" class="close" data-dismiss="alert">x</button>
                Atenção: essa promoção possui um ou mais clientes vinculados a ela.
            </div>
        </div>
    </div>
    @endif
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($promocao))
            {{ Form::model($promocao, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'route' => array('promocao.update', $promocao->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'promocao.store', 'class' => 'form-horizontal']) }}
            @endif
            <ul>
                <div class="nav-tabs-custom">
                    <div class="header panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                Promoção
                            </h3>
                        </div>
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
                                            <div class="form-group crud_space">
                                                {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-10">
                                                    {!! Form::text('descricao',null,['class'=>'form-control input-sm']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('inicio', 'Início:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDateTimePicker" id="datetimepicker1">
                                                        {!! Form::datetime('datahorainicio',$datahorainicio,['class'=>'form-control input-sm generalDateTimePicker', 'id' => 'datahorainicio']) !!}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {!! Form::label('fim', 'Fim:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDateTimePicker" id="datetimepicker2">
                                                        {!! Form::datetime('datahorafim',$datahorafim,['class'=>'form-control input-sm generalDateTimePicker', 'id' => 'datahorafim']) !!}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {!! Form::label('produtos', 'Produto:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-4">
                                                    {!! Form::select('produto_id', $produtos , null, ['id'=>'produto_id', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;width: 325px;']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('quantidadepedidos', 'Entregue em X compras:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::number('quantidadepedidos',null,['class'=>'form-control input-sm']) !!}
                                                </div>
                                                {!! Form::label('quantidadepremios', 'Qde brindes:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::number('quantidadepremios',null,['class'=>'form-control input-sm']) !!}
                                                </div>
                                                {!! Form::label('produtos', 'Brinde:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-4">
                                                    {!! Form::select('premioproduto_id', $premioProduto , null, ['id'=>'premioproduto_id', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;width: 325px;']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('ativo', 'Ativo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2 checkbox">
                                                    {{ Form::checkbox('ativo') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div><!-- /.tab-pane -->
                            </div><!-- /.tab-pane -->
                        </div><!-- /.tab-content -->
                        <div class="box-footer">
                            <div class="col-md-4">
                                {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro', 'id' => 'btnGravar']) !!}
                                <button type="button" onclick="window.location ='{{route("promocao.index")}}'" class="btn btn-nw-geral">Voltar</button>
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" name='vinculado' id='vinculado'>
            </div>
        </ul><!-- /.col -->
        {!! Form::close() !!}
    </div>
</div>
<script>

    
    $(document).ready(function () {
        @if (isset($show))
        desativarInputs();
        @endif
        @if ($vinculado)
        $(".selectChosen").prop('disabled', true).trigger('chosen:updated');
        $("#descricao").prop('disabled', true);
        $("#datahorainicio").prop('disabled', true);
        $("#datahorafim").prop('disabled', true);
        $("#quantidadepedidos").prop('disabled', true);
        $("#quantidadepremios").prop('disabled', true);
        @endif
        var v = {{@$vinculado}} + '';
        $("#vinculado").val(v);
    });
</script>
@endsection
