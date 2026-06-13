
@extends('layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Cheques Recebidos</h3>
                    </div>
                    <div class="panel-body">
                        <form class="form-horizontal" id='fmFiltros'>
                            {{ Form::hidden('cliente_id_reload', $cliente_id, ['id'=>'cliente_id_reload', 'disabled']) }}
                            {{ Form::hidden('cliente_nome_reload', $cliente_nome, ['id'=>'cliente_nome_reload', 'disabled']) }}
                            <div class="form-group crud_space">
                                {{ Form::select('contas',$contas , null, ['id'=>'contas', 'class' => 'hidden'])}}
                                {{ Form::label('cliente_id', 'Cliente:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                <div class="col-sm-4">
                                    <select id="cliente_id" name="cliente_id" placeholder="Buscar cliente" class="form-control" value=""
                                    data-selectize-value = '[]'></select>
                                </div>

                                <div class="col-sm-4">
                                    {{ Form::label('numerochequeinicial', 'Nº Cheque:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                    <div class="col-sm-4">
                                        {{ Form::text('numerochequeinicial' , null, ['id'=>'numerochequeinicial', 'class' => 'form-control input-sm number'])}}
                                    </div>
                                    {{ Form::label('numerochequefinal', 'a:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                    <div class="col-sm-4">
                                        {{ Form::text('numerochequefinal' , null, ['id'=>'numerochequefinal', 'class' => 'form-control input-sm number'])}}
                                    </div>
                                </div>
                                {{ Form::label('chequesituacao_id', 'Status:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                <div class="col-sm-2">
                                    {{ Form::select('chequesituacao_id',$chequesituacaos , null, ['id'=>'chequesituacao_id', 'class' => 'form-control selectChosen'])}}
                                </div>
                            </div>
                            <div class="form-group crud_space">
                                {{ Form::label('tipodata', 'Tipo de Data:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                <div class="col-sm-5">
                                    <div class="col-sm-3">
                                        {{ Form::radio('tipodata', '0', true, ['id' => 'tipodata_nenhum']) }} Nenhum
                                    </div>
                                    <div class="col-sm-3">
                                        {{ Form::radio('tipodata', '3', false) }} Emissão
                                    </div>
                                    <div class="col-sm-3">
                                        {{ Form::radio('tipodata', '1', false) }} Vencimento
                                    </div>
                                    <div class="col-sm-3">
                                        {{ Form::radio('tipodata', '2', false) }} Pagamento
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    {{Form::label('datainicio', 'De:', ['class' => 'input-sm control-label col-sm-1'])}}
                                    <div class="col-sm-3">
                                        <div class="input-group generalDatePicker">
                                            {{Form::text('datainicio', null, ['id' => 'datainicio', 'class' => 'input-sm form-control generalDatePicker'])}}
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                    {{Form::label('datafim', 'a:', ['class' => 'input-sm control-label col-sm-1'])}}
                                    <div class="col-sm-3">
                                        <div class="input-group generalDatePicker">
                                            {{Form::text('datafim', null, ['id' => 'datafim', 'class' => 'input-sm form-control generalDatePicker'])}}
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm btn-nw-buscas" id='btnFiltrar' type="submit" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                        <span class="fa fa-search fa-lg"></span>
                                    </button>
                                    <a class="btn btn-sm btn-github" type="button" href="{{route('chequerecebido.index')}}" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar">
                                        <span class="fa fa-recycle fa-lg"></span>
                                    </a>
                                </div>
                            </div>
                        </form>
                        <div class="col-md-12 col-md-offset-4 margTop_15 btnAcoes">
                            @can('editar', App\Chequerecebido::class)
                                <button type="button" id='btnDepositar' class="btn btn-primary">Depositar</button>
                            @endcan
                            @cannot('editar', App\Chequerecebido::class)
                                <button type="button" class="btn btn-primary" disabled>Depositar</button>
                            @endcannot

                            @can('baixarambos', App\Chequerecebido::class)
                                <button type="button" id='btnBaixar' class="btn btn-success">Baixar</button>
                            @endcan
                            @cannot('baixarambos', App\Chequerecebido::class)
                                <button type="button" class="btn btn-success" disabled>Baixar</button>
                            @endcannot

                            @can('estornar', App\Chequerecebido::class)
                                <button type="button" id='btnEstornar' class="btn btn-info">Estornar</button>
                            @endcan
                            @cannot('estornar', App\Chequerecebido::class)
                                <button type="button" disabled class="btn btn-info">Estornar</button>
                            @endcannot

                            @can('devolver', App\Chequerecebido::class)
                                <button type="button" id='btnDevolver' class="btn btn-nw-geral">Devolver</button>
                            @endcan
                            @cannot('devolver', App\Chequerecebido::class)
                                <button type="button" class="btn btn-nw-geral" disabled>Devolver</button>
                            @endcannot

                            @can('excluir', App\Chequerecebido::class)
                                <button type="button" id='btnExcluir' class="btn btn-nw-registro">Excluir</button>
                            @endcan
                            @cannot('excluir', App\Chequerecebido::class)
                                <button type="button" class="btn btn-nw-registro" disabled>Excluir</button>
                            @endcannot
                        </div>
                        <div class="col-md-12 margTop_15">
                            <table id="tblCheques" url="" cellpadding="0" cellspacing="0" urlupdate="" btnClick="false" class="no-select table table-bordered table-hover table-condensed">
                                <thead>
                                    <tr>
                                        <th class="hidden">Cód.</th>
                                        <th>Nº</th>
                                        <th>Banco/Ag/Conta</th>
                                        <th>Emissão</th>
                                        <th>Vencimento</th>
                                        <th>Pagamento</th>
                                        <th>Valor</th>
                                        <th class="hidden">situacao_id</th>
                                        <th class="hidden">depositoconta_Id</th>
                                        <th class="hidden">baixaconta_id</th>
                                        <th>Situação</th>
                                        <th>Cód. Parcelas</th>
                                        <th>Cliente</th>
                                        <th>Débito</th>
                                    </tr>
                                </thead>
                                <tbody id="clientes-list" name="clientes-list">
                                    @foreach ($chequesrecebidos as $chequerecebido)
                                    
                                    @if($chequerecebido->chequesituacao_id != 3)
                                    <tr>
                                        @else
                                        <tr class="cancelado">
                                            @endif
                                            <!-- {{$chequesituacao_id = $chequerecebido->chequesituacao_id}} -->
                                            <td class="hidden">{{$chequerecebido->id}}</td>
                                            <td>{{$chequerecebido->numerocheque}}</td>
                                            <td>{{$chequerecebido->banco->codigo . '/' . $chequerecebido->agencia . '/' . $chequerecebido->numeroconta}}</td>
                                            <td>{{requestDataOracle($chequerecebido->dataemissao, false)}}</td>
                                            <td>{{requestDataOracle($chequerecebido->datavencimento, false)}}</td>
                                            <td>{{requestDataOracle($chequerecebido->datapagamento, false)}}</td>
                                            <td>{{requestNumeroDecimalOracle($chequerecebido->valor)}}</td>
                                            <td class="hidden">{{$chequesituacao_id}}</td>
                                            <td class="hidden">{{$chequerecebido->depositoconta_id}}</td>
                                            <td class="hidden">{{$chequerecebido->baixaconta_id}}</td>
                                            <td>{{$chequerecebido->chequesituacao->descricao}}</td>
                                            <td>{{implode(', ', $chequerecebido->chequeRecebidoFinanceiro->pluck('financeiroparcela_id')->toArray())}}</td>
                                            <td>{{is_null($chequerecebido->chequeRecebidoFinanceiro->first()) ? '' : substr($chequerecebido->chequeRecebidoFinanceiro->first()->financeiro->cliente->nome, 0, 30)}}</td>
                                            <td>{{is_null($chequerecebido->chequeRecebidoEncontroContas) ? '' : requestNumeroDecimalOracle($chequerecebido->chequeRecebidoEncontroContas->sum('valortotal'))}}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-12 margTop_15">
                                <i>Para visualizar o cheque, pressione "Ctrl" + "Duplo Clique" no cheque a ser visualizado.</i>
                            </div>
                            <div class="col-md-12 margTop_15 form-horizontal">
                                {{ Form::label('total', 'Valor Total:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                <div class="col-sm-2">
                                    {{ Form::text('total' , @$total, ['id'=>'total', 'class' => 'form-control input-sm', 'disabled'])}}
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.content-wrapper -->
    </div>
</div>
{{Session::put('urlAtual', Request::url() . '?' . Request::getQueryString())}}
<script src="{{URL::to('js/cheque.js')}}"></script>
<script type="text/javascript">
     var lastSelected;
    var urlAtual = "{{Session::get('urlAtual')}}".replace('amp;', '').replace('amp;', '').replace('amp;', '').replace('amp;', '').replace('amp;', '').replace('amp;', '').replace('amp;', '').replace('amp;', '');
    $(document).ready(function() {
        $(".btnAcoes .btn").prop('disabled', true);
        tblCheques = $("#tblCheques").DataTable({
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
    $("#btnLimpar").on('click', function () {
        $('input[type="text"]').val('');
        $(".selectChosen").val('').trigger('chosen:updated');
        $("#tipodata_nenhum").trigger('click');
        var select = $("#cliente_id").selectize()[0].selectize;
        select.clearOptions();
    });
    $("#tblCheques").on('click', 'td', function () {
        $(".btnAcoes .btn").prop('disabled', true);
        var trElem = $(this).parent('tr');

        var callback = function () {
            if(tblCheques.rows('.linhaselecionada').any()){
                var chequesituacao_id = $($(trElem).children('td')[7]).text();
                if(chequesituacao_id != 2 && chequesituacao_id != 5 && chequesituacao_id != 7)
                    $("#btnDepositar").prop('disabled', false);
                if(chequesituacao_id != 2)
                    $("#btnBaixar").prop('disabled', false);
                if(chequesituacao_id == 2 || chequesituacao_id == 5 || chequesituacao_id == 7)
                    $("#btnDevolver, #btnEstornar").prop('disabled', false);
                if(chequesituacao_id == 4)
                    $("#btnExcluir").prop('disabled', false);
            }
        }
        marcarLinha(tblCheques, trElem, callback);
        lastSelected = trElem;
    });
    $("#tblCheques").on('dblclick', 'td', function () {
        if (window.event.ctrlKey) {
            var trElem = $(this).parent('tr');
            var id = $(trElem).children('td')[0];
            window.location.href = root + '/chequerecebido/' + $(id).text();
        }
    });
    $(".btnAcoes").on('click', 'button', function () {
        var indexSelected = tblCheques.row(lastSelected).index();
        var trElem = $(tblCheques.row(indexSelected).node());
        var id = $(trElem).children('td')[0];
        var situacao = $(trElem).children('td')[7];
        var depositoconta_id = $(trElem).children('td')[8];
        var baixaconta_id = $(trElem).children('td')[9];
        id = $(id).text();
        situacao = $(situacao).text();
        depositoconta_id = $(depositoconta_id).text();
        baixaconta_id = $(baixaconta_id).text();
        var numerocheque = $(trElem).children('td')[1];
        numerocheque = $(numerocheque).text();
        var acao = '';
        if($(this).context.id == 'btnExcluir')
            acao = 'excluir';
        else if($(this).context.id == 'btnDevolver')
            acao = 'devolver';
        else if($(this).context.id == 'btnDepositar')
            acao = 'depositar';
        else if($(this).context.id == 'btnBaixar')
            acao = 'baixar';
        else if($(this).context.id == 'btnEstornar'){
            // callbackSenha = function () {
                estornarCheque(id);
            // }
            // $("#modalSenha").modal('show');
            return;
        }
        bootboxConfirm(numerocheque, acao, null, null, function () {
            if (acao === 'depositar')
                selecionarContaDeposito(id, numerocheque);
            else if (acao === 'baixar')
                selecionarContaBaixa(id, numerocheque, situacao, depositoconta_id);
            else if (acao === 'excluir')
                editarCheque(root + '/chequerecebido.editar/excluir/' + id, urlAtual);
            else if (acao === 'devolver'){
                contaantiga_id = !isEmpty(depositoconta_id) ? depositoconta_id : baixaconta_id;
                selecionarContaDevolucao(id, numerocheque, contaantiga_id);
            }
        });
    });

    function selecionarContaDevolucao(id, numerocheque, contaantiga_id) {
        callback = function () {
            var conta_id = $("#conta_id").val();
            var datadevolucao = $("#dataacao").val();
            if(isEmpty(conta_id) || isEmpty(datadevolucao)){
                bootbox.alert({
                    message: 'Os campos Conta e Data são obrigatórios.',
                    callback: function () {
                        bootboxSelecionarConta(callback, 'Baixar', datadevolucao, conta_id, isEmpty(conta_id) ? false : true);
                    }
                });
            } else {
                var formData = new FormData();
                formData.append('baixaconta_id', conta_id);
                formData.append('datadevolucao', datadevolucao);
                var url = root + '/chequerecebido.devolver/' + id;
                var redirect = urlAtual;
                editarCheque(url, redirect, 'POST', formData);
            }
        }
        if(typeof contaantiga_id != 'undefined' && !isEmpty(contaantiga_id)) {
            bootboxSelecionarConta(callback, 'Devolução',null, contaantiga_id, true);
        }
        else
            bootboxSelecionarConta(callback, 'Devolução',null);
    }

    function selecionarContaBaixa(id, numerocheque, situacaoAnterior, depositoconta_id) {
        callback = function () {
            var conta_id = $("#conta_id").val();
            var datadeposito = $("#dataacao").val();
            if(isEmpty(conta_id) || isEmpty(datadeposito)){
                bootbox.alert({
                    message: 'Os campos Conta e Data são obrigatórios.',
                    callback: function () {
                        bootboxSelecionarConta(callback, 'Baixar', datadeposito, conta_id, isEmpty(conta_id) ? false : true);
                    }
                });
            } else {
                var formData = new FormData();
                formData.append('datapagamento', datadeposito);
                formData.append('baixaconta_id', conta_id);
                formData.append('databaixa', datadeposito);
                var url = root + '/chequerecebido.baixar/' + id;
                var redirect = urlAtual;
                editarCheque(url, redirect, 'POST', formData);
            }
        }
        if(typeof situacaoAnterior != 'undefined' && (situacaoAnterior == 5 || situacaoAnterior == 7)) {
            bootboxSelecionarConta(callback, 'Baixar',null, depositoconta_id, isEmpty(depositoconta_id) ? false : true);
        }
        else
            bootboxSelecionarConta(callback, 'Baixar',null);
    }

    function selecionarContaDeposito(id, numerocheque) {
        callback = function () {
            var conta_id = $("#conta_id").val();
            var datadeposito = $("#dataacao").val();
            if(isEmpty(conta_id) || isEmpty(datadeposito)){
                bootbox.alert({
                    message: 'Os campos Conta e Data são obrigatórios.',
                    callback: function () {
                        bootboxSelecionarConta(callback, 'Depositar', datadeposito, conta_id);
                    }
                });
            } else {
                var formData = new FormData();
                formData.append('depositoconta_id', conta_id);
                formData.append('datadeposito', datadeposito);
                var url = root + '/chequerecebido.depositar/' + id;
                var redirect = urlAtual;
                editarCheque(url, redirect, 'POST', formData);
            }
        }
        bootboxSelecionarConta(callback, 'Depositar',null);
    }

    function bootboxSelecionarConta(callback, labelButton, data, conta_id, disableSelect) {
        if(typeof conta_id == 'undefined')
            var conta_id = null;
        if(typeof disableSelect == 'undefined')
            var disableSelect = false;
        var contas = $('#contas').html();
        var endScript = 'script>';
        var message = '<br /><form class="form-horizontal" <div class="form-group crud_space">';
        message += '<label for="conta_id" class="col-sm-1 control-label input-sm"> Conta: </label>';
        message += '<div class="col-sm-6">';
        message += '<select id="conta_id" name="conta_id" class="selectChosen bootbox-input bootbox-input-select form-control">' + contas + '</select></div>';
        message += '<label for="dataacao" class="col-sm-1 control-label input-sm"> Data: </label>';
        message += '<div class="col-sm-4"><div class="input-group datepicker">';
        message += '<input type="text" name="dataacao" id="dataacao" class="bootbox-input input-sm bootbox-input-select form-control datepicker">';
        message += '<span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span></div></div> <br /> <br /></form>';
        message += '<script>$(".selectChosen").chosen({no_results_text: "nenhum registro encontrado", placeholder_text_single: "Selecione", width: "100%"});';
        message += '$("#conta_id").val("' + conta_id + '").prop("disabled", ' + disableSelect + ').trigger("chosen:updated");'
        message += "$('.datepicker').datetimepicker({defaultDate: moment(), locale: 'pt-br', viewMode: 'days', format: 'DD/MM/YYYY HH:mm:ss'});";
        message +=  isEmpty(data) ? '' : "$('#dataacao').val('" + data + "');";
        message += '</' + endScript;

        bootbox.dialog({
            title: "Selecione a conta",
            message: message,
            buttons: {
                confirm : {
                    label: labelButton,
                    className: "btn-nw-registro",
                    callback: callback
                },
                cancel : {
                    label: 'Cancelar',
                    className: "btn-nw-geral"
                }
            }
        });
    }

    function estornarCheque(id) {
        bootbox.confirm({
            title: "Digite o motivo do estorno!",
            message: '<input type="text" class="bootbox-input bootbox-input-text form-control" id="motivoestorno-bootbox">',
            backdrop: false,
            buttons: {
                confirm: {
                    label: "Estornar",
                    className: "btn-nw-registro"
                },
                cancel: {
                    label: "Cancelar",
                    className: "btn-nw-geral"
                }
            },
            callback: function (result) {
                if(result){
                    var motivoestorno = $("#motivoestorno-bootbox").val();
                    if(isEmpty(motivoestorno))
                        bootbox.alert({message: 'Informe o motivo!', callback: function() { estornarCheque(id); }});
                    else {
                        var formData = new FormData();
                        formData.append('motivo', motivoestorno);
                        var url = root + '/chequerecebido.estornar/' + id;
                        var redirect = urlAtual;
                        editarCheque(url, redirect, 'POST', formData);
                    }
                }
            }
        });
        setTimeout(function () {
            $("#motivoestorno-bootbox").focus();
        }, 500);
    }
</script>
@endsection
