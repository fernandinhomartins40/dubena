
@extends('layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Android</h3>
                    </div>
                    <div class="nav-tabs-custom">

                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Dados do Dispositivo</a></li>
                        </ul>
                        @if(isset($android))
                        {{ Form::model($android, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('android.update', $android->id))) }}
                        @else
                        {{ Form::open(['id'=>'fmCadastro', 'route' => 'android.store', 'class' => 'form-horizontal', 'files' => true]) }}
                        @endif
                        <div class="tab-content">

                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->

                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-3">
                                                    {!! Form::text('descricao',null,['class'=>'form-control input-sm']) !!}
                                                </div>
                                                <label for="ativo" class="col-sm-1 control-label input-sm required">Ativo:</label>
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::checkbox('ativo', 1, null, ['id'=>'ativo']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('setor_id', 'Setor:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-5">
                                                    {!! Form::select('setor_id', $setors, null, ['class' => 'form-control selectChosen', 'readonly'=>'readonly']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('user_id', 'Usuário:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-5">
                                                    {!! Form::select('user_id', $users, null, ['class' => 'form-control selectChosen', 'readonly'=>'readonly']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('colaborador_id', 'Colaborador:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-5">
                                                    {!! Form::select('colaborador_id', $colaboradors, null, ['class' => 'form-control selectChosen', 'readonly'=>'readonly']) !!}
                                                </div>
                                            </div>
                                            @if(!isset($show))
                                            <div class="form-group crud_space">
                                                {!! Form::label('', '', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-5">
                                                    <button type="button" class='btn btn-nw-geral btn-xs' id="btnRecarregar" onclick="recarregarDados();">Recarregar dados de setor, usuário e colaborador</button>
                                                </div>
                                            </div>
                                            @endif
                                            <div class="form-group crud_space">
                                                {!! Form::label('androidid', 'Id. Android:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-5">
                                                    {!! Form::text('androidid',null,['class'=>'form-control input-sm', 'readonly']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('urlservidor', 'Endereço Servidor:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-5">
                                                    {!! Form::text('urlservidor',null,['class'=>'form-control input-sm', 'readonly']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('registrationid', 'Registration Id.:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-5">
                                                    {!! Form::textarea('registrationid',null,['class'=>'form-control input-sm', 'readonly', 'rows'=>3]) !!}
                                                </div>
                                            </div>
                                            @if(isset($android) && $android->registrationid)
                                                <div class="form-group crud_space">
                                                    {!! Form::label('', '', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-5">
                                                        <button type="button" class='btn btn-nw-geral btn-xs' id="btnTestar" onclick="testarNotificacao();">Testar Notificação</button>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div><!-- /.tab-pane -->
                            </div><!-- /.tab-pane -->
                        </div><!-- /.tab-content -->
                        <div class="box-footer">
                            <div class="col-md-4">
                                {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                                <a href="{{url('android')}}" class="btn btn-nw-geral">Voltar</a>
                            </div>
                        </div>
                    </div><!-- /.col -->
                </div>

        </div>
    </div>
</div>
</div>
<!-- page script -->
<script type="text/javascript">

    jQuery(document).ready(function () {
        var root = '{{url("/")}}';
        var select = $('#colaborador_id');
        select.chosen();

        select.on('chosen:updated', function () {
            if (select.attr('readonly')) {
                var wasDisabled = select.is(':disabled');

                select.attr('disabled', 'disabled');
                select.data('chosen').search_field_disabled();

                if (wasDisabled) {
                    select.attr('disabled', 'disabled');
                } else {
                    select.removeAttr('disabled');
                }
            }
        });
        select.trigger('chosen:updated');
        select = $('#setor_id');
        select.chosen();

        select.on('chosen:updated', function () {
            if (select.attr('readonly')) {
                var wasDisabled = select.is(':disabled');
                select.attr('disabled', 'disabled');
                select.data('chosen').search_field_disabled();
                if (wasDisabled) {
                    select.attr('disabled', 'disabled');
                } else {
                    select.removeAttr('disabled');
                }
            }
        });
        select.trigger('chosen:updated');
        select = $('#user_id');
        select.chosen();

        select.on('chosen:updated', function () {
            if (select.attr('readonly')) {
                var wasDisabled = select.is(':disabled');

                select.attr('disabled', 'disabled');
                select.data('chosen').search_field_disabled();

                if (wasDisabled) {
                    select.attr('disabled', 'disabled');
                } else {
                    select.removeAttr('disabled');
                }
            }
        });
        select.trigger('chosen:updated');

    });
    setTimeout(function () {
        @if (isset($show))
                desativarInputs();
        @endif

        }, $(document).ready());

    function recarregarDados(){
        var url = root + "/getAndroidData/{{isset($android)?$android->id:-1}}";
        var method = "GET";
        ajaxGenerator(url, method,
            function (data) {
                if(typeof(data)=="object"){
                    if (data.status !== "OK") {
                        bootbox.alert("Ocorreu um erro ao buscar os dados!");
                    } else {
                        $('#colaborador_id').val(data.colaborador_id);
                        select = $('#colaborador_id');
                        select.chosen();
                        select.on('chosen:updated', function () {
                            if (select.attr('readonly')) {
                                var wasDisabled = select.is(':disabled');

                                select.attr('disabled', 'disabled');
                                select.data('chosen').search_field_disabled();

                                if (wasDisabled) {
                                    select.attr('disabled', 'disabled');
                                } else {
                                    select.removeAttr('disabled');
                                }
                            }
                        });
                        select.trigger('chosen:updated');
                        $('#setor_id').val(data.setor_id);
                        select = $('#setor_id');
                        select.chosen();
                        select.on('chosen:updated', function () {
                            if (select.attr('readonly')) {
                                var wasDisabled = select.is(':disabled');

                                select.attr('disabled', 'disabled');
                                select.data('chosen').search_field_disabled();

                                if (wasDisabled) {
                                    select.attr('disabled', 'disabled');
                                } else {
                                    select.removeAttr('disabled');
                                }
                            }
                        });
                        select.trigger('chosen:updated');
                        $('#user_id').val(data.user_id);
                        select = $('#user_id');
                        select.chosen();
                        select.on('chosen:updated', function () {
                            if (select.attr('readonly')) {
                                var wasDisabled = select.is(':disabled');

                                select.attr('disabled', 'disabled');
                                select.data('chosen').search_field_disabled();

                                if (wasDisabled) {
                                    select.attr('disabled', 'disabled');
                                } else {
                                    select.removeAttr('disabled');
                                }
                            }
                        });
                        select.trigger('chosen:updated');
                    }
                } else {
                    bootbox.alert("Ocorreu um erro ao buscar os dados!");
                }
            },null);

    }
    function testarNotificacao(){
        var url = root + "/testAndroidNotify/{{isset($android)?$android->id:-1}}";
        var method = "GET";
        ajaxGenerator(url, method,
            function (data) {
                if(typeof(data)=="object"){
                    if (data.status !== "OK") {
                        bootbox.alert("Ocorreu um erro ao enviar notificação. Resposta: " + data.message);
                    } else {
                        bootbox.alert("Notificação enviada. Resposta: " + data.message);
                    }
                } else {
                    bootbox.alert("Ocorreu um erro ao enviar notificação. Resposta: " + data.message);
                }
            },null);

    }
</script>
@endsection
