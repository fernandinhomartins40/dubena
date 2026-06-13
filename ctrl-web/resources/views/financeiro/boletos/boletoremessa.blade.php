@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-6" style="margin-bottom:1%">
                                @can('create', App\Boletoremessa::class)
                                    <a href="{{ url("") }}/remessa/create" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Remessas de Boletos</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                {{ Form::open(['id' => 'fmReport','class'=>'form-horizontal'])}}
                                    <div class="form-group crud_space">
                                        {{ Form::label('conta_id', 'Conta:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-sm-3">
                                            {{Form::select('conta_id', $contas, null, ['class' => 'selectChosen', 'id' => 'conta_id'])}}
                                        </div>
                                        {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            <div class="input-group generalDatePicker">
                                                {{ Form::text('datainicio',@requestDataOracle($dataInicio),['id' => 'datainicio','class'=>'form-control generalDatePicker input-sm']) }}
                                                <span class="input-group-addon">
                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                </span>
                                            </div>
                                        </div>
                                        {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            <div class="input-group generalDatePicker">
                                                {{ Form::text('datafim',@requestDataOracle($dataFim),['id' => 'datafim','class'=>'form-control generalDatePicker input-sm']) }}
                                                <span class="input-group-addon">
                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                </span>    
                                            </div>
                                        </div>
                                        <div class="col-sm-2">
                                            <button id="btnUpload" data- type="button" class="btn btn-sm btn-nw-registro" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Arquivo de Retorno">
                                                <span class="fa fa-upload fa-lg"></span>
                                            </button>
                                            <button type="button" id='btnLimpar' class="btn btn-sm btn-github" onclick="window.location.href = '{{route('remessa.index')}}'" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar">
                                                <span class="fa fa-recycle fa-lg"></span>
                                            </button>
                                            <button id="btnFiltro" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                                <span class="fa fa-search fa-lg"></span>
                                            </button>
                                        </div>
                                    </div>
                                {{ Form::close() }}
                                <div class="col-md-12">
                                    <table id="tblRemessas" class="table table-bordered table-hover table-condensed">
                                        <thead>
                                            <tr>
                                                <th>Cód.</th>
                                                <th>Conta</th>
                                                <th>Data Remessa</th>
                                                <th>Sequência</th>
                                                <th>Cancelado</th>
                                                <th>Arq. Remessa</th>
                                                <th>Efetivado</th>
                                                <th>Operações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($remessas as $remessa)
                                                <tr>
                                                    <td>{{$remessa->id}}</td>
                                                    <td>{{$remessa->conta->descricao}}</td>
                                                    <td>{{requestDataOracle($remessa->datahora)}}</td>
                                                    <td>{{$remessa->numerosequencia}}</td>
                                                    <td>{{$remessa->cancelado == 0 ? 'Não' : 'Sim'}}</td>
                                                    <td>{{$remessa->gerouremessa == 0 ? 'Não Gerou' : 'Gerou'}}</td>
                                                    <td>{{$remessa->efetivado == 0 ? 'Não' : 'Sim'}}</td>
                                                    <td>
                                                        @if($remessa->cancelado == 0)
                                                            @if(!$remessa->gerouremessa)
                                                                @can('update',$remessa)
                                                                    <button class="btn btn-nw-geral btn-xs" id="btnRemessa" type="button">Arq de Remessa</button>
                                                                @endcan
                                                            @endif
                                                            @cannot('update', $remessa)
                                                                <button class="btn btn-nw-geral btn-xs" disabled type="button">Arq de Remessa</button>
                                                            @endcannot
                                                            @if($remessa->efetivado == 0)
                                                                @can('update', $remessa)
                                                                    <button class="btn btn-nw-registro btn-xs" id="btnCancelar" type="button">Cancelar</button>
                                                                @endcan
                                                                @cannot('update', $remessa)
                                                                        <button class="btn btn-nw-registro btn-xs" disabled type="button">Cancelar</button>
                                                                @endcannot
                                                            @endif
                                                        @endif
                                                        <button onclick="window.location.href = root + '/remessa/{{$remessa->id}}'" class="btn btn-nw-buscas btn-xs" type="button"data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                        </button>
                                                        @if (($remessa->gerouremessa == 1 || $remessa->cancelado == 1) && $remessa->efetivado == 0)
                                                            @can('update', $remessa)
                                                                <button class="btn btn-nw-geral btn-xs" id="btnEfetivar" type="button">Efetivar</button>
                                                            @endcan
                                                            @cannot('update', $remessa)
                                                                <button class="btn btn-nw-geral btn-xs" disabled type="button">Efetivar</button>
                                                            @endcannot
                                                        @endif
                                                        @if($remessa->cancelado == 0 && $remessa->gerouremessa == 0)
                                                            @can('update', $remessa)
                                                                <button onclick="window.location.href = root + '/remessa/{{$remessa->id}}/edit'" class="btn btn-nw-geral btn-xs" type="button"data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                                    <span class="fa fa-pencil fa-lg"></span>
                                                                </button>
                                                            @endcan
                                                            @cannot('update', $remessa)
                                                                <button disabled class="btn btn-nw-geral btn-xs" type="button"data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                                    <span class="fa fa-pencil fa-lg"></span>
                                                                </button>
                                                                <button class="btn btn-nw-geral btn-xs" disabled type="button">Efetivar</button>
                                                            @endcannot
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div><!-- /.box-body -->
                        </div><!-- /.box -->
                    </div><!-- /.col -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="col-md-5">
                                    @can('create', App\Boletoremessa::class)
                                        <a href="{{ url("") }}/remessa/create" class="btn btn-nw-registro">Novo Registro</a>
                                    @endcan
                                </div>
                            </div>
                        </div>  
                </div><!-- /.row -->
                @include('general.modals.upload_file')
                @include('general.modal_del')
                <!--Rota para deletar via ajax-->
                <div id='rotaDel' class="hidden">{{url('')}}/remessa</div>
                <!--Rota para redirecionar via ajax-->
                <div id='rotaIndex' class="hidden">{{url('')}}/remessa</div>
            </div><!-- /.content-wrapper -->
        </div>
    </div>
</div>
<!--<link href="http://hayageek.github.io/jQuery-Upload-File/4.0.11/uploadfile.css" rel="stylesheet">
<script src="http://hayageek.github.io/jQuery-Upload-File/4.0.11/jquery.uploadfile.min.js"></script>-->
{{ Form::open(['id'=>'fmRemessa',  'class' => 'form-horizontal', 'files' => true, 'method' => 'get']) }}
@include('general.modal_report_iframe')
<script type="text/javascript">
   var validFormatUpload = ['ret'];
    $("#file-upload").attr('accept', '.ret');
    
    var callbackUpload = function () {
        var url = root + '/importRetorno';
        $("#fmUpload").off().attr({
            'action': url,
            'method': 'post'
        }).on('submit', function () {
            if(isEmpty($("#file-upload").val())) {
                bootbox.alert('Selecione um arquivo');
                return false;
            }
        });
    }

    $(document).ready(function () {
        tblRemessas = $("#tblRemessas").DataTable({
            "language": {"url": urlDataTable},
            "processing": true,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": false,
            "bInfo": false,
            "bAutoWidth": false,
            "scrollY": '350'
        }); 
    });

    $("#tblRemessas").on('click', 'button' ,function () {
        var btn_id = $(this).context.id;
        var tr = $(this).closest('tr');
        if(btn_id == 'btnRemessa'){
            var id = parseInt($($(tr).children('td')[0]).html());
            if(isNaN(id)) {
                bootbox.alert("Não foi possível gerar a remessa");
                return;
            }
            gerarRemessa(id, tr);
        } else if (btn_id == 'btnCancelar') {
            cancelarRemessa(tr);
        }
    });

    function gerarRemessa (id, tr) {
        var seq = parseInt($($(tr).children('td')[3]).html());
        
        var msg = "Deseja mesmo exportar a remessa " + seq + "? <br />\n\
                    Após exportar você deve executar a operação \"Efetivar\" \n\
                    para confirmar as alterações nos boletos.";
        functionConfirm(msg, function () {
            var url = root + `/exportRemessa?remessa_id=${id}`;
            window.open(url, '_blank');
            setTimeout(function () {
                location.reload();
            }, 1000);
        });
    }

    function cancelarRemessa (tr) {
        var id = parseInt($($(tr).children('td')[0]).html());
        var seq = parseInt($($(tr).children('td')[3]).html());
        if(isNaN(id)) {
            bootbox.alert("Não foi possível cancelar a remessa");
            return;
        }
        var msg = "Deseja mesmo cancelar a remessa " + seq + "? <br />\n\
                    Após o cancelamento você deve executar a operação \"Efetivar\" \n\
                    para confirmar as alterações nos boletos.";
        functionConfirm(msg, function () {
            var url = root + '/cancelarRemessa/' + id;
            ajaxGenerator(url, 'GET', function (data) {
                if(data == 'OK|')
                    bootbox.alert({message: 'Remessa cancelada com sucesso.', callback: function () {location.reload();}});
                else
                    bootbox.alert('Erro' + data);    
            });
        });
    }

    var functionConfirm = function (msg,callback) {
        if (typeof callback != "function") {
            return false;
        }
        bootbox.confirm({
            title: "Atenção!",
            className: "dontHideEsc",
            message: msg,
            buttons: {
                confirm: {
                    label: "Sim",
                    className: "btn-nw-registro"
                },
                cancel: {
                    label: "Não",
                    className: "btn-nw-geral"
                }
            },
            backdrop: true,
            closeButton: false,
            callback: function (res) {
                if (res) {
                    callback();
                }
            }
        });
    };

    $("#btnFiltro").on('click', function () {
        var url = root + '/remessa?conta_id=:conta_id&datainicio=:datainicio&datafim=:datafim';
        var dataInicio = $("#datainicio").val();
        var dataFim = $("#datafim").val();
        var conta_id = isNaN(parseInt($("#conta_id").val())) ? '' : $("#conta_id").val();
        if(isEmpty(dataInicio)){
            bootbox.alert('O campo Data Inicio é obrigatório.');
            return;
        }
        if(isEmpty(dataFim)){
            bootbox.alert('O campo Data Fim é obrigatório.');
            return;
        }
        url = url.replace(':datainicio', dataInicio);
        url = url.replace(':datafim', dataFim);
        url = url.replace(':conta_id', conta_id);
        window.location.href = url;
    });

    $("#btnLimpar").on('click', function () {
        $(".selectChosen").val('').trigger('chosen:updated');
        $("#datainicio, #datafim").val(dataAtual());
    });

    $("#btnUpload").on('click', function () {
        $("#modal-upload-file").modal('show');
    });

    $("#tblRemessas tbody").on('click', "#btnEfetivar", function (e) {
        e.stopPropagation();
        var row = $(this).closest('tr');
        var id = tblRemessas.row( row ).data()[0];
        var seq = tblRemessas.row( row ).data()[3];

        var msg = "Deseja mesmo efetivar a remessa " + seq + "?";
        functionConfirm(msg, function () {
            efetivarRemessa(id);
        });
    });

    var efetivarRemessa = function (id) {
        var url = root + `/efetivarRemessa?remessa_id=${id}`;
        ajaxGenerator(url, 'GET', function (data) {
            if (data.substr(0, 3) == 'OK|') {
                bootbox.alert({message: 'Remessa efetivada com sucesso.', callback: function () {location.reload();}});
            } else {
                bootbox.alert(data);
            }
        }, null, null, true);
    };
</script>
@endsection