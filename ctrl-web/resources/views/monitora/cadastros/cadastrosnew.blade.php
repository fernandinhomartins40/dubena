@extends('monitora.layouts.mainmenu')

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
                                                CRIAÇÃO DE NOVA EMPRESA PARA RASTREAMENTO
                                            </h4>
                                            <div class="media">

                                                <div class="media-body">
                                                    <div class="clearfix">
                                                        <p style="text-align: center;">
                                                            Utilize esta funcionalidade para buscar os dados no sistema de Revendas da Nacional Gás e atualizar o sistema de Rastreamento de Veículos.
                                                            Antes de utilizar essa opção, verifique se os usuários, veículos e setores foram marcados para usar o rastreamento no sistema de Revendas.
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
                        <div class="col-md-12 text-center">
                            <div class="col-sm-8 col-md-offset-4 text-left">
                                {!! Form::label('empresa_id', 'Código da Empresa:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                <div class="col-sm-2">
                                    {!! Form::text('empresa_id',null,['class'=>'form-control input-sm']) !!}
                                </div>
                                <button type="button" onclick="atualizar(99);" class="btn btn-success">Atualizar</button>
                            </div>
                        </div>

                    </div>
                    <br/>
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
                url: root + '/api/criarCadastro',
                type: 'POST',
                dataType: 'json',
                data: {
                    "_token": "{{ csrf_token() }}",
                    tipocadastro: res,
                    empresa_id: $('#empresa_id').val()
                },
                success: function (ret) {
                    $('#pleaseWaitDialog').modal('hide');
                    if (ret.toString().substr(0, 3) == 'OK|') {
                        bootbox.alert('Atualização realizada com sucesso.');
                    } else {
                        bootbox.alert('Houve um problema ao atualizar: ' + ret);
                    }
                },
                error: function (x) {
                    $('#pleaseWaitDialog').modal('hide');
                    bootbox.alert('Erro ao atualizar cadastro.');
                }
            });
        }
        function atualizar(res) {
            var msg = 'Confirma a criação de nova empresa no rastreamento?'
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
                <h4 class="modal-title" id="myModalLabelCadastro">Criação de nova Empresa</h4>
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