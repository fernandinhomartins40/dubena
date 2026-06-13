
@extends('layouts.mainmenu')

@section('content')
<!--
<link href="{{URL::to('plugins/chosen/chosen.css')}}" rel="stylesheet" type="text/css" />
<link href="{{URL::to('css/custom.css')}}" rel="stylesheet" type="text/css" />
<link href="{{URL::to('plugins/datatables/dataTables.bootstrap.css')}}" rel="stylesheet" type="text/css" />

<script src="{{URL::to('plugins/jQuery/jQuery-2.1.4.min.js')}}"></script>
<script src="{{URL::to('plugins/bootbox.min.js')}}"></script>
<script src="{{URL::to('plugins/chosen/chosen.jquery.latin.js')}}"></script>
<script src="{{URL::to('js/jqueryMaskMoney.js')}}"></script>
<script src="{{URL::to('js/shortcut.js')}}"></script>
<script src="{{URL::to('plugins/datatables/jquery.dataTables.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/datatables/dataTables.bootstrap.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/datepicker1/moment/moment-with-locales.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/datepicker1/js/bootstrap-datetimepicker.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('js/jquery.mask.min.js')}}"></script>
<script src="{{URL::to('js/custom.js')}}"></script>
<script>
    root = '{{url("")}}';
    var urlDataTable = "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}";
</script>
-->
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Cheques Emitidos</h3>
                    </div>
                    <div class="panel-body">
                        <form class="form-horizontal" id='fmFiltros'>
                            <div class="form-group crud_space">
                                {!! Form::label('conta_id', 'Conta:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                <div class="col-sm-3">
                                    {!! Form::select('conta_id',$contas , null, ['id'=>'conta_id', 'class' => 'form-control selectChosen'])!!}
                                </div>
                                <div class="col-sm-4">
                                    {!! Form::label('numerochequeinicial', 'Nº Cheque:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                    <div class="col-sm-4">
                                        {!! Form::text('numerochequeinicial' , null, ['id'=>'numerochequeinicial', 'class' => 'form-control input-sm number'])!!}
                                    </div>
                                    {!! Form::label('numerochequefinal', 'a:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                    <div class="col-sm-4">
                                        {!! Form::text('numerochequefinal' , null, ['id'=>'numerochequefinal', 'class' => 'form-control input-sm number'])!!}
                                    </div>
                                </div>
                                {!! Form::label('chequesituacao_id', 'Status:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                <div class="col-sm-2">
                                    {!! Form::select('chequesituacao_id',$chequesituacaos , null, ['id'=>'chequesituacao_id', 'class' => 'form-control selectChosen'])!!}
                                </div>
                            </div>
                            <div class="form-group crud_space">
                                {!! Form::label('tipodata', 'Tipo de Data:', ['class'=>'col-sm-1 control-label input-sm']) !!}
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
                                        <a class="btn btn-sm btn-github" type="button" href="{{route('chequeemitido.index')}}" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar">
                                            <span class="fa fa-recycle fa-lg"></span>
                                        </a>
                                </div>
                            </div>
                            <div class="form-group crud_space">
                                <div class="col-sm-2 col-sm-offset-1">
                                    @can('inutilizar', App\Chequeemitido::class)
                                        <button type="button" id='btnInutilizar' class="btn btn-nw-geral btn-xs" disabled>Inutilizar Cheque</button>
                                    @endcan
                                    @cannot('inutilizar', App\Chequeemitido::class)
                                        <button type="button" class="btn btn-nw-geral btn-xs" disabled>Inutilizar Cheque</button>
                                    @endcannot
                                </div>
                            </div>
                        </form>
                        <div class="col-md-12 col-md-offset-4 margTop_15 btnAcoes">
                            @can('baixar', App\Chequeemitido::class)
                                @can('pagar', App\Financeiro::class)<!-- Baixar -->
                                    <button type="button" id='btnBaixar' class="btn btn-success">Baixar</button>
                                @endcan
                                @cannot('pagar', App\Financeiro::class)
                                    <button type="button" class="btn btn-success" disabled>Baixar</button>
                                @endcannot
                            @endcan
                            @cannot('baixar', App\Chequeemitido::class)
                                <button type="button" class="btn btn-success" disabled>Baixar</button>
                            @endcannot
                            @can('viewUpdate', App\Chequeemitido::class)<!-- Baixar/Edição -->
                                @can('estornarPagamento', App\Financeiro::class)
                                    <button type="button" id='btnEstornar' class="btn btn-info">Estornar</button>
                                @endcan
                                @cannot('estornarPagamento', App\Financeiro::class)
                                    <button type="button" class="btn btn-info" disabled>Estornar</button>
                                @endcannot
                            @endcan
                            @cannot('viewUpdate', App\Chequeemitido::class)
                                <button type="button" class="btn btn-info" disabled>Estornar</button>
                            @endcannot
                            @can('excluir', App\Chequeemitido::class)
                                <button type="button" id='btnCancelar' class="btn btn-nw-registro">Cancelar</button>
                            @endcan
                            @cannot('excluir', App\Chequeemitido::class)
                                <button type="button" class="btn btn-nw-registro" disabled>Cancelar</button>
                            @endcannot
                        </div>
                        <div class="col-md-12 margTop_15">
                            <table id="tblCheques" url="" cellpadding="0" cellspacing="0" urlupdate="" btnClick="false" class="no-select table table-bordered table-hover table-condensed">
                                <thead>
                                    <tr>
                                        <th class="hidden">Cód.</th>
                                        <th>Nº</th>
                                        <th>Conta</th>
                                        <th>Emissão</th>
                                        <th>Vencimento</th>
                                        <th>Pagamento</th>
                                        <th>Valor</th>
                                        <th>Situação</th>
                                        <th class="hidden"></th>
                                        <th>Cód. Parcelas</th>
                                        <th>Fornecedor</th>
                                        <th>Crédito</th>
                                    </tr>
                                </thead>
                                <tbody id="clientes-list" name="clientes-list">
                                    @foreach ($chequesemitidos as $chequeemitido)
                                    @if($chequeemitido->chequesituacao_id == 3)
                                    <!-- {{$class = 'cancelado'}} -->
                                    @else
                                    <!-- {{$class = ''}} -->
                                    @endif
                                    <tr class="{{$class}}">
                                        <td class="hidden">{{$chequeemitido->id}}</td>
                                        <td>{{$chequeemitido->numerocheque}}</td>
                                        <td>{{$chequeemitido->conta->descricao}}</td>
                                        <td>{{requestDataOracle($chequeemitido->dataemissao, false)}}</td>
                                        <td>{{requestDataOracle($chequeemitido->datavencimento, false)}}</td>
                                        <td>{{requestDataOracle($chequeemitido->datapagamento, false)}}</td>
                                        <td>{{requestNumeroDecimalOracle($chequeemitido->valor)}}</td>
                                        <td class="hidden">{{$chequeemitido->chequesituacao_id}}</td>
                                        <td>{{$chequeemitido->chequesituacao->descricao}}</td>
                                        <td>{{implode('; ', $chequeemitido->chequeEmitidoFinanceiro->pluck('financeiroparcela_id')->toArray())}}</td>
                                        <td>{{is_null($chequeemitido->chequeEmitidoFinanceiro->first()) ? '' : $chequeemitido->chequeEmitidoFinanceiro->first()->financeiro->cliente->nome}}</td>
                                        <td>{{is_null($chequeemitido->chequeEmitidoEncontroContas) ? '' : requestNumeroDecimalOracle($chequeemitido->chequeEmitidoEncontroContas->sum('valortotal'))}}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-12 margTop_15">
                            <i>
                                Para visualizar o cheque, pressione "Ctrl" + "Duplo Clique" no cheque a ser visualizado.
                            </i>
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
    var urlAtual = "{{Session::get('urlAtual')}}".replace('amp;', '').replace('amp;', '').replace('amp;', '').replace('amp;', '').replace('amp;', '').replace('amp;', '').replace('amp;', '');
    $(document).ready(function() {
        $(".btnAcoes .btn").prop('disabled', true);
        if(!isEmpty($("#conta_id").val())) {
            $("#btnInutilizar").prop('disabled', false);
        } else {
            $("#btnInutilizar").prop('disabled', true);
        }
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
    });
    $("#tblCheques").on('dblclick', 'td', function () {
        if (window.event.ctrlKey) {
            var trElem = $(this).parent('tr');
            var id = $(trElem).children('td')[0];
            window.location.href = root + '/chequeemitido/' + $(id).text();
        }
    });
    $("#tblCheques").on('click', 'td', function () {
        $(".btnAcoes .btn").prop('disabled', true);
        var trElem = $(this).parent('tr');

        var chequesituacao_id = $($(trElem).children('td')[7]).text();
        if(chequesituacao_id != 3){
            var callback = function () {

                if(tblCheques.rows('.linhaselecionada').any()){
                    if(chequesituacao_id != 2)
                        $("#btnBaixar").prop('disabled', false);
                    else
                        $("#btnEstornar").prop('disabled', false);
                    if(chequesituacao_id == 1)
                        $("#btnCancelar").prop('disabled', false);
                }
            }
            marcarLinha(tblCheques, trElem, callback);
            lastSelected = trElem;
        }
    });
    $(".btnAcoes").on('click', 'button', function () {
        var indexSelected = tblCheques.row(lastSelected).index();
        var trElem = $(tblCheques.row(indexSelected).node());
        var id = $(trElem).children('td')[0];
        id = $(id).text();
        var numerocheque = $(trElem).children('td')[1];
        numerocheque = $(numerocheque).text();
        var acao = '';
        if($(this).context.id == 'btnCancelar')
            acao = 'cancelar';
        else if($(this).context.id == 'btnBaixar')
            acao = 'baixar';
        else if($(this).context.id == 'btnEstornar'){
            estornarCheque(id);
            return;
        }
        var callbackBaixar = function () {
            var endScript = 'script>';
            var message = '<br /><form class="form-horizontal"> <div class="form-group crud_space">';
            message += '<label for="databaixa" class="col-sm-1  col-sm-offset-3 control-label input-sm"> Data: </label>';
            message += '<div class="col-sm-4"><div class="input-group datepicker">';
            message += '<input type="text" name="databaixa" id="databaixa" class="bootbox-input input-sm form-control datepicker">';
            message += '<span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span></div></div> <br /> <br /></form>';
            message += "<script>$('.datepicker').datetimepicker({defaultDate: moment(), locale: 'pt-br', viewMode: 'days', format: 'DD/MM/YYYY HH:mm:ss'});";
            message += '</' + endScript;
            bootbox.dialog({
                title: "Selecione!",
                message: message,
                buttons: {
                    confirm : {
                        label: 'Gravar',
                        className: "btn-nw-registro",
                        callback: function() {
                            var databaixa = $("#databaixa").val();
                            if(isEmpty(databaixa)){
                                bootbox.alert({message: 'Informe a data!', callback: function () {callbackBaixar()}});
                            } else {
                                var url = root + '/chequeemitido.editar/baixar/' + id;
                                var redirect = urlAtual;
                                var formData = new FormData();
                                formData.append('databaixa', databaixa);
                                editarCheque(url, redirect, 'POST', formData);
                            }
                        }
                    },
                    cancel : {
                        label: 'Cancelar',
                        className: "btn-nw-geral"
                    }
                }
            });
        }
        bootboxConfirm(numerocheque, acao, root + '/chequeemitido.editar/' + acao + '/' + id, root + '/chequeemitido', acao == 'baixar' ? callbackBaixar : null);
    });

    $("#btnInutilizar").on('click', function () {
        inutilizarCheque();
    });

    $("#conta_id").on('change', function () {
        if(!isEmpty($(this).val())) {
            $("#btnInutilizar").prop('disabled', false);
        } else {
            $("#btnInutilizar").prop('disabled', true);
        }
    });

    function inutilizarCheque(){
        bootbox.confirm({
            title: "Digite o número do cheque a ser inutilizado!",
            message: '<input type="number" class="bootbox-input bootbox-input-number form-control" id="numerocheque-bootbox">',
            backdrop: false,
            buttons: {
                confirm: {
                    label: "Inutilizar",
                    className: "btn-nw-registro"
                },
                cancel: {
                    label: "Cancelar",
                    className: "btn-nw-geral"
                }
            },
            callback: function (result) {
                if(result){
                    var numerocheque = $("#numerocheque-bootbox").val();
                    var conta_id = $("#conta_id").val();
                    numerocheque = isEmpty(numerocheque) ? numerocheque : numerocheque.replace(/[^\d]+/g,'');

                    if(isEmpty(numerocheque))
                        bootbox.alert({message: 'Informe um número de cheque válido!', callback: function() { inutilizarCheque(); }});
                    else
                        bootboxConfirm(numerocheque, 'inutilizar', root + '/chequeemitido.editar/inutilizar/' + numerocheque + '/' + conta_id, root + '/chequeemitido');
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
                    var conta_id = $("#conta_id").val();

                    if(isEmpty(motivoestorno))
                        bootbox.alert({message: 'Informe o motivo!', callback: function() { estornarCheque(id); }});
                    else {
                        var formData = new FormData();
                        formData.append('motivo', motivoestorno);
                        ajaxGenerator(root + '/chequeemitido.editar/estornar/' + id, 'POST', function (data) {
                            if (data.substr(0, 3) === "OK|") {
                                var dialog = bootbox.dialog({
                                    title: 'Operação realizada com sucesso!',
                                    message: '<p><i class="fa fa-spin fa-spinner"></i> Aguarde, você será redirecionado..</p>'
                                });
                                dialog.init(function () {
                                    window.setTimeout("location.href='"+urlAtual+"'", 1500);
                                });
                            } else {
                                bootbox.alert('Erro: ' + data);
                            }
                        }, null, formData);
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
