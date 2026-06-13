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
        <div class="col-md-12">
            <!-- Custom Tabs -->
            {{ Form::open(['id'=>'fmCadastro', 'class' => 'form-horizontal', 'files' => true]) }}
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">EFD ICMS IPI</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1"><!-- form start -->
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
                                                {{ Form::label('empresa_id', 'Empresa:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-4">
                                                    {{ Form::text('empresa_id', $empresa,['class' => 'form-control','readonly']) }}
                                                </div>
                                                {{ Form::label('tipoescrit', 'Original:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::radio('tipoescrit', '0') }}
                                                </div>
                                                {{ Form::label('tipoescrit', 'Substituto:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::radio('tipoescrit', '1') }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('validacoes', 'Validações:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-11">
                                                    {{ Form::textarea('validacoes',null,['rows'=>'10', 'class'=>'form-control input-sm sped-errors', 'id'=>'validacoes', 'readonly']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('', '', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                {{ Form::label('c170', 'Gerar registro C170 para saídas (por padrão, deixar desmarcado):', ['class'=>'col-sm-8 control-label input-sm']) }}
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::checkbox('c170') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div><!-- /.tab-pane -->
                            </div><!-- /.tab-pane -->
                        </div><!-- /.tab-content -->
                        <div class="box-footer">
                            <div class="col-md-4">
                                @can('create', App\Spedfiscal::class)
                                <button type="submit" id="btnGerarArquivo" class="btn btn-nw-registro">Gerar Arquivo</button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
        </div>
        {{ Form::close() }}
        </ul><!-- /.col -->
    </div>
</div>
<script src="{{URL::to('js/spedfiscal.js')}}"></script>
<script type="text/javascript">
var loader;
$("#fmCadastro").on('submit', function (e) {
    loader = bootbox.dialog({
        title: 'Validando arquivo',
        message: '<p><i class="fa fa-spin fa-spinner"></i> Por favor, aguarde..</p>',
        onEscape: false,
        backdrop: 'static',
        closeButton: false
    });
    e.preventDefault();
    var formData = new FormData($(this)[0]);
    setTimeout(function () {
        var url = root + '/spedfiscal/gerarsped';
        $("#validacoes").val('');
        ajaxGenerator(url, "POST", function (data) {
            if (data.substr(0, 3) === 'NOK') {
                $("#validacoes").val(data.substr(3, data.length));
            } else if (data.substr(0, 3) === "OK|") {
                bootbox.alert("Arquivo gerado com sucesso!");
                window.open(data.substr(3, data.length), '_blank');
            } else {
                bootbox.alert("Erro ao gerar arquivo:" + data);
            }
        }, null, formData, false, function () {
            loader.modal('hide');
        });
    }, 500);
    return false;
});
</script>
@endsection
