@extends('layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-sm-12">

                {{ Form::open(['id'=>'fmCadastro', 'route' => 'sorteio.store', 'class' => 'form-horizontal', 'files' => true]) }}

            <ul>
                <div class="panel panel-default form-horizontal">
                    <div class="panel-heading">
                        <h3 class="panel-title">Novo Sorteio</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {!! Form::label('datainicio', 'Data inicial:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    <div class="input-group generalDatePicker">
                                                        {!! Form::text('datainicio',null,['class'=>'form-control generalDatePicker']) !!}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {!! Form::label('datafim', 'Data final:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    <div class="input-group generalDatePicker">
                                                        {!! Form::text('datafim',null,['class'=>'form-control generalDatePicker']) !!}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
                                                    <label for="app" class="col-sm-9 control-label input-sm required">Somente Pedidos APP:</label>
                                                    <div class="col-sm-3 checkbox">
                                                        {{ Form::checkbox('app', 1, null, ['id'=>'app']) }}
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
													<button id="btnFiltrar" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-search fa-lg"></span></button>
												</div>
                                            </div>
                                            <div class="form-group crud_space">
                                                <div class="col-sm-12">
                                                    <div id="tbl_pedidos"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <div class="col-sm-4">
                            <button id="btnGraver" type="button" class="btn btn-nw-registro">Sortear</button>
                        </div>
                    </div>
                </div>
            </ul>

            {!! Form::close() !!}
        </div>
    </div>
</div>

<link href="{{URL::to('plugins/tabulator/css/tabulator_bootstrap3.min.css')}}" rel="stylesheet" type="text/css" />
<script src="{{URL::to('plugins/tabulator/js/tabulator.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('js/tabulatorLocalization.js')}}" type="text/javascript"></script>

<script>
    const getPedUrl = "{{route('sorteio.getPedidos')}}";
    $(document).ready(function () {
        tblPedidos = null;

        initTable();
    });

    $("#btnFiltrar").click(function () {
        if (tblPedidos) {
            tblPedidos.setData(getPedUrl);
        }
    });

    $("#btnGraver").click(function () {
        let inicio = $("#datainicio").val();
        let fim = $("#datafim").val();

        if (!inicio || !fim) {
            bootbox.alert("Informe a data inicial e a data final antes de sortear.");
            return;
        }

        bootbox.confirm({
            message: "Deseja continuar com o Sorteio? Após confirmar, o vencedor será salvo.",
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
            callback: function (confirmado) {
                if (!confirmado) {
                    return;
                }

                realizarSorteio();
            }
        });
    });

    function initTable() {
        tblPedidos = new Tabulator("#tbl_pedidos", {
            locale: "pt-br",
            langs: {
                "pt-br": tabulatorPtBr,
            },
            ajaxParams: function() {
                let inicio = $("#datainicio").val();
                let fim = $("#datafim").val();
                let onlyApp = $("#app").prop("checked")

                let obj = {
                    inicio,
                    fim,
                };

                if (onlyApp) {
                    return {...obj, app: true};
                }

                return obj;
            },
            ajaxResponse: (_url, _params, response) => response.data,
            height: "47vh",
            layout: "fitDataFill",
            columns: [
                { title: "Cód", field: "id" },
                { title: "Data", field: "data" },
                { title: "Cliente", field: "nome" },
            ],
            rowFormatter: function (row) {
                const data = row.getData();
                const cells = row.getCells();

                if (data.is_app && cells.length > 0) {
                    const el = cells[0].getElement();

                    el.style.backgroundColor = "#AB24B7";
                    el.style.color = "#FFF";
                }
            },
        });
    }

    function realizarSorteio() {
        let btn = $("#btnGraver");
        btn.prop("disabled", true);

        $.ajax({
            type: "POST",
            headers: {
                "X-CSRF-TOKEN": $("meta[name='csrf-token']").attr("content")
            },
            url: $("#fmCadastro").attr("action"),
            data: $("#fmCadastro").serialize(),
            success: function (res) {
                if (res.status !== "OK") {
                    bootbox.alert(res.msg || "Não foi possível realizar o sorteio.");
                    return;
                }

                mostrarVencedor(res.data);

                if (tblPedidos) {
                    tblPedidos.setData(getPedUrl);
                }
            },
            error: function (err) {
                let msg = "Erro ao realizar o sorteio.";

                if (err.responseJSON && err.responseJSON.msg) {
                    msg = err.responseJSON.msg;
                }

                bootbox.alert(msg);
            },
            complete: function () {
                btn.prop("disabled", false);
            }
        });
    }

    function mostrarVencedor(data) {
        let html = `
            <div style="text-align:center; padding: 20px 10px;">
                <div style="font-size: 22px; margin-bottom: 15px;">
                    Vencedor do Sorteio
                </div>
                <div style="font-size: 34px; font-weight: bold; margin-bottom: 15px;">
                    ${data.cliente_nome}
                </div>
                <div style="font-size: 24px; margin-bottom: 8px;">
                    Pedido: <strong>${data.pedido_id}</strong>
                </div>
                <div style="font-size: 16px;">
                    Sorteio salvo com sucesso.
                </div>
            </div>
        `;

        bootbox.alert({
            title: "Resultado",
            message: html,
            callback: function () {
                window.location.href = "{{route('sorteio.index')}}";
            }
        });
    }
</script>

@endsection