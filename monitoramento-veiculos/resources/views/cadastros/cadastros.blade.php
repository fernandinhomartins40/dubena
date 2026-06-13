@extends('layouts.mainmenu')

@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <ul>
                <div class="nav-tabs-custom">
                    <div class="header panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                Atualização de Cadastros 
                            </h3>
                        </div>
                    </div>
                    <div class=" col-md-10  col-md-offset-1">
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <div class="col-md-12" style="margin-top:10px;">
                                    <div class="box box-solid">
                                        <div class="box-body">
                                            <h4 style="background-color:#f7f7f7; font-size: 18px; text-align: center; padding: 7px 10px; margin-top: 0;">
                                                ATUALIZAÇÃO DE CADASTROS
                                            </h4>
                                            <div class="media">

                                                <div class="media-body">
                                                    <div class="clearfix">
                                                        <p style="text-align: center;">
                                                            Utilize esta funcionalidade para buscar os dados no sistema de Revendas da Nacional Gás e atualizar o sistema de Rastreamento de Veículos.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div><!-- /.box-body -->
                        </div>
                    </div><!-- /.box -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-12 text-center">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-success">Atualizar</button>
                                    <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                        <span class="caret"></span>
                                        &nbsp;
                                    </button>
                                    <ul class="dropdown-menu" role="menu">
                                        <li><a onclick="atualizar(1);" href="#">Grupo de Revenda</a></li>
                                        <li><a onclick="atualizar(2);" href="#">Revendas</a></li>
                                        <li><a onclick="atualizar(3);" href="#">Veículos</a></li>
                                        <li><a onclick="atualizar(4);" href="#">Setores</a></li>
                                        <li><a onclick="atualizar(5);" href="#">Usuários</a></li>
                                        <li><a onclick="atualizar(99);" href="#">Tudo</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <br/>


                    <div id="divLancamentos" class="box" style="display:none;">
                        {{ Form::open(['id'=>'fmCadastro', 'route' => 'cadastro.index', 'class' => 'form-horizontal', 'files' => true]) }}
                        <div class="box-body" >
                            {!! Form::hidden('inputCartoes',null,['id'=>'inputCartoes']) !!}
                            {!! Form::hidden('inputData',null,['id'=>'inputData']) !!}
                            <input type="hidden" id="metodo" name="_method">
                            <div id="tabPresenca" class="col-md-10 col-md-offset-1">
                                <div style="width: auto; height: auto;overflow: hidden;" id="presencaGrid" class="scroll-container"></div>
                            </div><!-- /.box -->
                        </div>
                        {!! Form::close() !!}
                    </div>

                </div><!-- /.col -->
            </ul>
        </div><!-- /.row -->

    </div>
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <script type="text/javascript">
        var root = '{{url("/")}}';
        $(document).ready(function () {
            $('.modal').on('show.bs.modal', function () {
                if ($(document).height() > $(window).height()) {
                    // no-scroll
                    $('body').addClass("modal-open-noscroll");
                } else {
                    $('body').removeClass("modal-open-noscroll");
                }
            });
            $('.modal').on('hide.bs.modal', function () {
                $('body').removeClass("modal-open-noscroll");
            });
        });

        function atualizarCadastros(res) {
            $('#pleaseWaitDialog').modal('show');
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.ajax({
                url: root + '/api/atualizarCadastro',
                type: 'POST',
                dataType: 'json',
                data: {
                    "_token": "{{ csrf_token() }}",
                    tipocadastro: res
                },
                success: function (ret) {
                    $('#pleaseWaitDialog').modal('hide');
                    if (ret.toString().substr(0, 3) == 'OK|') {
                        bootbox.alert('Atualização realizada com sucesso.');
                    } else {
                        bootbox.alert('Houve um problema ao atualizar: ' + ret);
                    }
                },
                error: function (data) {
                    $('#pleaseWaitDialog').modal('hide');
                    if (typeof (data) == 'object') {
                        $("input").prop('disabled', false);
                        var msg = '';
                        var responseText = '';
                        for (var key in data) {
                            if (key == 'responseJSON') {
                                for (var key1 in data['responseJSON']) {
                                    msg += '<br />' + data['responseJSON'][key1];
                                }
                            }
                            if (key == 'responseText') {
                                responseText = data['responseText'];
                            }
                        }
                        if (msg != '')
                            bootbox.alert('Erro ao gravar: <br />' + msg);
                        else
                            bootbox.alert('Erro ao gravar: ' + responseText);
                    } else if (typeof (data) == 'string') {
                        bootbox.alert('Erro ao gravar: ' + data);
                    } else {
                        bootbox.alert('Houve um erro desconhecido ao gravar!');
                    }
                }
            });
        }
        function atualizar(res) {
            var msg = 'Confirma a atualização do cadastro: ' + (res == 1 ? 'GRUPO DE REVENDAS' : res == 2 ? 'REVENDAS' : res == 3 ? 'VEÍCULOS' : res == 4 ? 'SETORES' : res == 5 ? 'USUÁRIOS' : 'TODOS');
            bootbox.confirm({
                message: msg,
                buttons: {
                    confirm: {
                        label: 'Sim',
                        className: 'btn-success'
                    },
                    cancel: {
                        label: 'Não',
                        className: 'btn-default'
                    }
                },
                callback: function (result) {
                    if (result) {
                        atualizarCadastros(res);
                    }
                }
            });
        }
        

    </script>
</div>


<div id="pleaseWaitDialog" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"  data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-sm" role="document" style="max-width: 70%; max-height: 40%">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="myModalLabelCadastro">Atualização de Cadastro</h4>
            </div>
            <div class="modal-body">
                <div class="modal-header">
                    <h4><i class="fa fa-circle-o-notch fa-spin" style="font-size:24px"></i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Processando...</h4>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection