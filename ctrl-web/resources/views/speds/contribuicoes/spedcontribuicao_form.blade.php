@extends('layouts.mainmenu')
@section('content')
<style>
    .modal-body {
        max-height: 500px;
        overflow-y: scroll;
    }
</style>
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12"><!-- Custom Tabs -->
            {{ Form::open(['id'=>'fmCadastro', 'class' => 'form-horizontal', 'files' => true]) }}
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">EFD Contribuições</h3>
                    </div>
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
                                            <div class="form-group crud_space">
                                                {{ Form::label('mesano', 'Mês/Ano:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group generalDateMesAno">
                                                        {{ Form::text('mesano',null,['class'=>'form-control input-sm generalDateMesAno', 'id' => 'mesano']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {!! Form::label('reciboanterior', 'Rec. Anterior:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-3">
                                                    {!! Form::text('reciboanterior',null,['class'=>'form-control input-sm']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('empresa_id', 'Empresa:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-5">
                                                    {!! Form::text('empresa_id', $empresa, ['class' => 'form-control', 'readonly']) !!}
                                                </div>
                                                {!! Form::label('tipo', 'Original:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::radio('tipoescrit', '0') }}
                                                </div>
                                                {!! Form::label('tipo', 'Retificadora:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::radio('tipoescrit', '1') }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('validacoes', 'Validações:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-11">
                                                    {!! Form::textarea('validacoes',null,['rows'=>'10', 'class'=>'form-control input-sm sped-errors', 'id'=>'validacoes', 'readonly']) !!}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div><!-- /.tab-pane -->
                            </div><!-- /.tab-pane -->
                        </div><!-- /.tab-content -->
                        <div class="box-footer">
                            <div class="col-md-4">
                                @can('create', App\Spedcontribuicao::class)
                                <button type="submit" id="btnGerarArquivo" class="btn btn-nw-registro">Gerar Arquivo</button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            </ul><!-- /.col -->
            {!! Form::close() !!}
        </div>
    </div>
</div>
<!-- <script type="text/javascript" src="{{asset('js/spedcontribuicao.js')}}"></script> -->

<script type="text/javascript">
    var loader;
    $("#btnGerarArquivo").on('click', function () {
        loader = bootbox.dialog({
            title: 'Validando arquivo',
            message: '<p><i class="fa fa-spin fa-spinner"></i> Por favor, aguarde..</p>',
            onEscape: false,
            backdrop: 'static',
            closeButton: false
        });
    });
    $("#fmCadastro").on('submit', function (e) {
        e.preventDefault();
        var url = root + '/spedcontribuicao/gerarSped';
        var formData = new FormData($(this)[0]);
        ajaxGenerator(url, "POST", function (data) {
            if (data.substr(0, 3) === 'NOK') {
                $("#validacoes").val(data.substr(3, data.length));
            } else if (data.substr(0, 3) === "OK|") {
                $("#validacoes").val('');
                bootbox.alert("Arquivo gerado com sucesso!");
                window.open(data.substr(3, data.length), '_blank');
            } else {
                $("#validacoes").val('');
                bootbox.alert("Erro ao gerar arquivo:" + data);
            }
        }, null, formData, false, function () {
            loader.modal('hide');
        });
        return false;
    });
</script>
@endsection
