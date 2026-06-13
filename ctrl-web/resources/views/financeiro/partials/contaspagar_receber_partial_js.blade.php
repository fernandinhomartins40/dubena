
    <script type="text/javascript">

    var root = '{{url("/")}}';
    var tblLancamentos;
    var tipoPesquisa = 0;
    var valorPesquisa = 0;
    $(window).load(function () {
            breakFocusPrevInput = true;
            @if(isset($parcelas))
                    carregarLancamentosById('{{$parcelas}}');
            @endif
    });
    $(document).ready(function () {
        $("#fmAbrirRecebimento").addClass('js-allow-double-submission');
        $("#fmAbrirFinanceiro").addClass('js-allow-double-submission');
        tblLancamentos = $("#tblLancamentos");
        var sortObj = {'sort': 'id', 'order': 'asc'};

        $(document).on('click', '.great-table-checkbox', function () {
            let isChecked = this.checked;
            let $tr = $(this.closest('tr'));

            if (isChecked && !$tr.hasClass("linhaselecionada")) {
                $tr.addClass("linhaselecionada");
            } else if (!isChecked && $tr.hasClass("linhaselecionada")) {
                $tr.removeClass("linhaselecionada");
            }

        });

        @if(!isset($parcelas))
            sortObj.noSortOnTable = true;
        @endif
        var tablePars = {
            'checkbox': true,
            'sort': sortObj,
            'cols': {
                'id': {
                    showHide: false,
                    callbackClick: function (that) {
                        abrirTelaConsultar($(that).text());
                    }
                },
                'documento': { showHide: true},
                'numero': {showHide: true},
                'dataemissao': {showHide: true},
                'datavencimento': {showHide: true},
                'datahorabaixa': {showHide: true},
                'valor': {showHide: true},
                'multa': {showHide: true},
                'juros': {showHide: true},
                'desconto': {showHide: true},
                'valorefetivado': {showHide: true},
                'descricao': {showHide: true},
                'numcheque': {showHide: true},
                'nossonumeroboleto': {showHide: true},
                'status': {showHide: true},
                'cliente_id': {showHide: true},
                'nome': {showHide: true},
                'agrupamento_status': {showHide: true},
                'cartaoautorizacao': {showHide: true}
            },
            'cache': false,
            'contentHeight': 400
        };
        @if(!isset($parcelas))
            tablePars.paginateServerSide = {
                'serverSide': true,
                'url': false,
                'onclick': function () {
                    getOnclickPaginateFunction()
                },
                'atualPage': 0,
                'totalPages': 0
            };
        @endif
        initSearchbox();
        initTables();
        tblLancamentos = new GreatTable(tblLancamentos, tablePars);
        tblLancamentos.render();
        tblLancamentos.prevSort = {
            sort: sortObj.sort,
            order: sortObj.order
        };

        @if(!isset($parcelas))
            $("#great-table-header-tblLancamentos").on('click', 'th', function () {
                if(typeof $(this).attr('sort-by') !== 'undefined') {
                    if($(this).hasClass('sort-asc') && !$(this).hasClass('sort'))
                        var order = 'desc';
                    else
                        var order = 'asc';
                    var sort = $(this).attr('field-id');
                    tblLancamentos.prevSort.sort = sort;
                    tblLancamentos.prevSort.order = order;
                    tblLancamentos.putSortIcons({sort: sort, order:order});
                    carregarLancamentos(tblLancamentos.atualPage);
                }
            });
        @endif
        $("#popup_recebercaixa, #popup_financeiro").on('hide.bs.modal', function(){
            @if(isset($parcelas))
                carregarLancamentosById('{{$parcelas}}');
            @else
                $("#btnPrint").trigger('click');
            @endif
        });

        $("#tblParcelasDisponiveis").on('click', 'button', function() {
            var row = $(this).closest('tr');
            var data = $("#tblParcelasDisponiveis").dataTable().fnGetData(row);
            data[4] = "<button type='button' class='btn btn-xs btn-nw-registro'><i class='glyphicon glyphicon-remove'></i></button>";
            tblParcelasAdicionadas.row.add(data).draw();
            tblParcelasDisponiveis.row($(this).parents('tr')).remove().draw();
            enableDisableBtnEncontroContas();
            calculaParcelasEncontroContas();
        });

        $("#tblParcelasAdicionadas").on('click', 'button', function() {
            var row = $(this).closest('tr');
            var data = $("#tblParcelasAdicionadas").dataTable().fnGetData(row);
            data[4] = "<button type='button' class='btn btn-xs btn-nw-geral'><i class='glyphicon glyphicon-plus'></i></button>";
            tblParcelasDisponiveis.row.add(data).draw();
            tblParcelasAdicionadas.row($(this).parents('tr')).remove().draw();
            enableDisableBtnEncontroContas();
            calculaParcelasEncontroContas();
        });

        $("#btnReceberEncontroContas, #btnReceberChequeEncontroContas").on('click', function () {
            var data = $("#tblParcelasAdicionadas").dataTable().fnGetData();
            var encontrocontas = [];
            $.each(data, function(i, el) {
                encontrocontas.push(el[0]);
            });
            var valorLiquido = $("#valorLiquidoEncontroContas").val().replace('R$ ', '').replace('.', '').replace(',', '.');
            if(parseFloat(valorLiquido) < 0) {
                bootbox.alert('O valor das parcelas adicionadas não deve exceder o valor das parcelas a serem baixadas!');
                return;
            }
            if($(this).attr('id') == 'btnReceberEncontroContas')
                abrirTelaReceber(null, JSON.stringify(encontrocontas));
            else
                abrirTelaCheque(JSON.stringify(encontrocontas));
            $("#popup_encontrocontas").modal('hide');
        });
        $("#btnSalvarAlteraParc").on('click', function () {
            alterarDescricaoLancamento();
        });
    });

function enableDisableBtnEncontroContas() {
    var data = $("#tblParcelasAdicionadas").dataTable().fnGetData();
    if (data.length == 0)
        $("#btnReceberChequeEncontroContas, #btnReceberEncontroContas").prop('disabled', true)
    else
        $("#btnReceberChequeEncontroContas, #btnReceberEncontroContas").prop('disabled', false)
}

function getOnclickPaginateFunction (page) {
    carregarLancamentos(page);
}

function carregarLancamentos(page = 1) {
    @if(!isset($parcelas))
        $('#divLancamentos').show();
        var headers = {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
        tblLancamentos.clear();
        var url = root + '/api/getLancamentosFinanceiros';
        if(typeof tblLancamentos.prevSort !== "undefined" && tblLancamentos.prevSort !== null) {
            var sort = tblLancamentos.prevSort.sort;
            var order = tblLancamentos.prevSort.order;
        } else {
            var sort = 'id';
            var order = 'asc';
        }

        var data = {
            "_token": "{{ csrf_token() }}",
            tipoPesquisa: tipoPesquisa,
            valorPesquisa: valorPesquisa,
            cliente_id: $('#searchboxcliente').selectize()[0].selectize.getValue(),
            @if($tipo_lancamento=='R')
            colaborador_id: $('#searchboxcolaborador').selectize()[0].selectize.getValue(),
            @endif
            tipodata: $('input[name=tipodata]:checked').val(),
            datainicio: $('#datainicio').val(),
            datafinal: $('#datafinal').val(),
            status_id: $('input[name=status_id]:checked').val(),
            pagarreceber: '{{$tipo_lancamento}}',
            sort: sort,
            order: order,
            page: page
        };
        openLoaderProccess();
        tblLancamentos.loadDataAjax(headers, url, 'GET', data,
            function (res) {
                var info = res[res.length - 1];
                updateTotais(info);
                if(typeof info.info !== "undefined" && info.info) {
                    res.splice(-1,1);
                                }
                                tblLancamentos.clear();
                                tblLancamentos.addRow(res, true);
                var pagePars = {
                    'serverSide': true,
                    'url': false,
                    'onclick': function (page) {
                        getOnclickPaginateFunction(page)
                    },
                    'atualPage': info.atualPage,
                    'totalPages': info.totalPages
                };
                tblLancamentos.writeHtmlPaginate(pagePars);
            }, function () {
                closeLoaderProcess();
            }, true, false);
    @endif
}

function updateTotais (res) {
    var totalReceber = 0;
    var totalRecebido = 0;
    if(typeof res.info === "undefined") {
        for (var i = 0; i < res.length; i++) {
            if (typeof res[i].valorefetivado !== "undefined") {
                var value = parseFloat(parseDinheiro(res[i].valorefetivado, 2));
                if (res[i].baixado==0)
                    totalReceber += value;
                else if(res[i].agrupamento_status <= 1)
                    totalRecebido += value;
            }
        }
    } else {
        totalRecebido = parseFloat(res.totalRecebido);
        totalReceber = parseFloat(res.totalReceber);
    }
    $('#total_receber').val(totalReceber.toFixed(2));
    $('#total_recebido').val(totalRecebido.toFixed(2));
    $('.dinheiro').each(function(){ // function to apply mask on load!
        $(this).maskMoney('mask', $(this).val());
    })
    /*
    if (tipoPesquisa > 0) {
        $("#divLegendaPendentes").removeClass('col-md-offset-2').addClass('col-md-offset-1');
        $("#divLegendaAgr").show();
    } else {
        $("#divLegendaPendentes").addClass('col-md-offset-2').removeClass('col-md-offset-1');
        $("#divLegendaAgr").hide();
    }
    */
    $('#divTotal, #divLegenda').show();
}

function abrirTelaReceber(res, encontrocontas = null) {
    var duplicatas = [];
    var dataChecked = tblLancamentos.getDataChecked();
    for (i = 0; i < dataChecked.length; i++) {
        if (dataChecked[i].status == 'BAI') {
            bootbox.alert('Parcelas baixadas não podem ser recebidas novamente.');
            return false;
        } else if (dataChecked[i].numcheque != '') {
            bootbox.alert('Parcelas vinculadas a algum cheque não podem ser recebidas.');
            return false;
        }
        duplicatas.push(dataChecked[i].id);
    }
    if (duplicatas.length == 0){
        bootbox.alert('Escolha os títulos para serem baixados.');
        return false;
    }
    $("#fmAbrirRecebimento").attr('action', '{{URL::route('financeiro.baixartitulosbycaixa')}}');
    $('#parcelas').val(JSON.stringify(duplicatas));
    $('#baixarfechado').val(0);
    $('#encontrocontas').val(encontrocontas);
    duplicatas = [];
    $('#fmAbrirRecebimento').submit();
    $('#popup_recebercaixa').modal('show');
}

function abrirTelaCheque(encontrocontas = null) {
    var duplicatas = [];
    var cliente = 0;
    var clienteAnterior = 0;
    var dataChecked = tblLancamentos.getDataChecked();
    for (var i = 0; i < dataChecked.length; i++) {
        cliente = dataChecked[i].cliente_id;
        if (dataChecked[i].numcheque != '') {
            bootbox.alert('Parcelas já vinculadas a algum cheque não podem ser vinculadas novamente.');
            return false;
        } else if(cliente != clienteAnterior && clienteAnterior != 0) {
            bootbox.alert('As parcelas selecionadas precisam ser do mesmo cliente');
            return false;
        }
        clienteAnterior = cliente;
        duplicatas.push(dataChecked[i].id);
    }
    if (duplicatas.length == 0){
        bootbox.alert('Escolha os títulos para serem pagos.');
        return false;
    }
    var qdeDuplicatas = duplicatas.length;
    @if($tipo_lancamento=='P')
        var url = root + '/chequeemitido/create';
    @else
        var url = root + '/chequerecebido/create';
    @endif
    $("#fmAbrirRecebimento").attr('action', url);
    $('#parcelas').val(JSON.stringify(duplicatas));
    $('#encontrocontas').val(encontrocontas);
    $('#baixarfechado').val(0);
    $('#qdeDuplicatas').val(qdeDuplicatas);
    duplicatas = [];
    $('#fmAbrirRecebimento').submit();
    $('#popup_recebercaixa').modal('show');
}
function abrirTelaEncontroContas(){
    $("#valorParcelasAdicionadas, #valorParcelasSelecionadas, #valorLiquidoEncontroContas").val('');
    enableDisableBtnEncontroContas();
    calculaParcelasEncontroContas();
    var duplicatas = [];
    var cliente = 0;
    var clienteAnterior = 0;
    var valorParcelasSelecionadas = 0;
    var dataChecked = tblLancamentos.getDataChecked();
    for (i = 0; i < dataChecked.length; i++) {
        cliente = dataChecked[i].cliente_id;
        var valor = dataChecked[i].valor;
        valorParcelasSelecionadas += parseDinheiro(valor);
        if (dataChecked[i].numcheque != '') {
            bootbox.alert('Parcelas já vinculadas a algum cheque não podem ser vinculadas novamente.');
            return false;
        } else if(cliente != clienteAnterior && clienteAnterior != 0) {
            bootbox.alert('As parcelas selecionadas precisam ser do mesmo cliente.');
            return false;
        } else if (dataChecked[i].status == 'BAI') {
            bootbox.alert('Parcelas baixadas não podem ser recebidas novamente.');
            return false;
        }
        clienteAnterior = cliente;
        duplicatas.push(dataChecked[i].id);
    }

    valorParcelasSelecionadas = "R$ " + formataDecimal(valorParcelasSelecionadas, 2);
    $("#valorParcelasSelecionadas").val(valorParcelasSelecionadas);
    $("#valorLiquidoEncontroContas").val(valorParcelasSelecionadas);

    if (duplicatas.length == 0){
        bootbox.alert('Escolha os títulos para serem pagos.');
        return false;
    }
    var qdeDuplicatas = duplicatas.length;

    @if($tipo_lancamento=='P')
    var url = root + '/api/encontrocontas.searchContasReceber/' + cliente;
    @else
    var url = root + '/api/encontrocontas.searchContasPagar/' + cliente;
    @endif

    tblParcelasDisponiveis.clear().draw();
    tblParcelasAdicionadas.clear().draw();
    $("#valorParcelasAdicionadas").val("R$ 0,00");
    ajaxGenerator(url, "GET", function (data) {
        if(data == '404') {
            bootbox.alert('A parcela selecionada não está atrelada a um cadastro do tipo "Cliente/Fornecedor"');
            return false;
        } else if(typeof data === 'object' || typeof data === 'array'){
            $.each(data, function(i, el) {
                tblParcelasDisponiveis.row.add([
                    el.parcela_id,
                    requestDataOracle(el.dataemissao, false),
                    requestDataOracle(el.datavencimento, false),
                    "R$ " + formataDecimal(el.valor, 2),
                    "<button type='button' class='btn btn-xs btn-nw-geral'><i class='glyphicon glyphicon-plus'></i></button>"
                ]).draw();
            });
            $("#popup_encontrocontas").modal('show');
        } else {
            bootbox.alert('' + data);
        }
    });
}

function calculaParcelasEncontroContas() {
    var data = $("#tblParcelasAdicionadas").dataTable().fnGetData();
    var valorParcelasAdicionadas = 0;
    $.each(data, function(i, el) {
        var valor = el[3].replace('.', '').replace(',', '.').replace('R$ ', '');
        valorParcelasAdicionadas += parseFloat(valor);
    });

    var valorParcelasSelecionadas = $("#valorParcelasSelecionadas").val().replace('R$ ', '').replace('.', '').replace(',', '.');
    var liquido =  parseFloat(valorParcelasSelecionadas) - valorParcelasAdicionadas;
    liquido = "R$ " + formataDecimal(liquido, 2, true);
    valorParcelasAdicionadas = "R$ " + formataDecimal(valorParcelasAdicionadas, 2);

    $("#valorParcelasAdicionadas").val(valorParcelasAdicionadas).trigger("mask.maskMoney");
    $("#valorLiquidoEncontroContas").val(liquido).trigger("mask.maskMoney");
}

function abrirTelaReceberFechado() {
    var duplicatas = [];
    var dataChecked = tblLancamentos.getDataChecked();
    for (i = 0; i < dataChecked.length; i++) {
        if (dataChecked[i].status == 'BAI') {
            bootbox.alert('Parcelas baixadas não podem ser recebidas novamente.');
            return false;
        }
        duplicatas.push(dataChecked[i].id);
    }
    if (duplicatas.length == 0){
        bootbox.alert('Escolha os títulos para serem baixados.');
        return false;
    }
    $("#fmAbrirRecebimento").attr('action', '{{URL::route('financeiro.baixartitulosbycaixa')}}');
    $('#parcelas').val(JSON.stringify(duplicatas));
    $('#baixarfechado').val(1);
    duplicatas = [];
    $('#fmAbrirRecebimento').submit();
    $('#popup_recebercaixa').modal('show');
}

function abrirTelaConsultar(res) {
    $("#fmAbrirRecebimento").attr('action', '{{URL::route('financeiro.consultartitulos')}}');
    $('#parcelas').val(res);
    $('#fmAbrirRecebimento').submit();
    $('#popup_recebercaixa').modal('show');
}

function abrirTelaCancelar(res) {
    $("#fmAbrirRecebimento")[0].reset();
    let duplicatas = [];
    let dataChecked = tblLancamentos.getDataChecked();
    for (i = 0; i < dataChecked.length; i++) {
        if (dataChecked[i].status == 'BAI') {
            bootbox.alert('Parcelas baixadas não podem ser canceladas.');
            return false;
        }
        duplicatas.push(dataChecked[i].id);
    }
    $('#parcelas').val(JSON.stringify(duplicatas));
    if (duplicatas.length == 0) {
        bootbox.alert('Escolha os títulos para serem cancelados.');
        return false;
    }
    bootbox.confirm({
        message: "Confirma o CANCELAMENTO dessas parcelas?",
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
            callbackSenha = function () {
                $("#fmAbrirRecebimento").attr('action', '{{URL::route('financeiro.cancelartitulosbycaixa')}}');
                duplicatas = [];
                $('#fmAbrirRecebimento').submit();
                $('#popup_recebercaixa').modal('show');
            };
            if (result){
                $("#modalSenha").modal("show");
            }
        }
    });
}

function abrirTelaEstornar(res) {
    var duplicatas = [];
    var dataChecked = tblLancamentos.getDataChecked();
    for (i = 0; i < dataChecked.length; i++) {
        if (dataChecked[i].status != 'BAI') {
            bootbox.alert('Parcelas pendentes não podem ser estornadas.');
            return false;
        }
        duplicatas.push(dataChecked[i].id);
    }
    if (duplicatas.length == 0) {
        bootbox.alert('Escolha os títulos para serem estornados.');
        return false;
    }
    bootbox.prompt('Motivo do estorno da baixa: ',
        function(result){
            if(result){
                estornarLancamentoCaixa(duplicatas, result);
            }
        });
}

function estornarLancamentoCaixa(parcelas, motivo) {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    //console.log(hotCategorias.getData());
    $.ajax({
        url: root + '/api/estornarLancamentoCR',
        type: 'POST',
        dataType: 'json',
        data: {
            "_token": "{{ csrf_token() }}",
            parcelas: parcelas.join(','),
            motivo: motivo,
            contafechamento_id: -1,
            tipo_estorno: 'CR'
        },
        success: function (res) {
            if (res.substr(0, 3) == 'OK|') {
                setTimeout(function(){ bootbox.alert('Estorno efetivado com sucesso.'); }, 500);
                carregarLancamentos();
            } else {
                setTimeout(function(){ bootbox.alert(res); }, 500);
            }
        },
        error: function (data) {
            if (typeof (data) == 'object') {
                var msg = '';
                var responseText = '';
                for (var key in data) {
                    if (key == 'responseJSON') {
                        for (var key1 in data['responseJSON']) {
                            msg += data['responseJSON'][key1];
                        }
                    }
                    if (key == 'responseText') {
                        responseText = data['responseText'];
                    }
                }
                if (msg != '')
                    bootbox.alert('Erro ao estornar: ' + msg);
                else
                    bootbox.alert('Erro ao estornar: ' + responseText);
                    //bootbox.alert('Erro ao gravar: ' + data.responseJSON.descricao);
                } else if (typeof (data) == 'string') {
                    bootbox.alert('Erro ao estornar: ' + data);
                } else {
                    bootbox.alert('Houve um erro desconhecido ao estornar!');
                }
            }
        });
}
function abrirFinanceiroJuntar(tipo) {
    var duplicatas = [];
    var cliente_id = -1;
    var nome = "";
    var dataChecked = tblLancamentos.getDataChecked();
    for (i = 0; i < dataChecked.length; i++) {
        if (dataChecked[i].status == 'BAI') {
            bootbox.alert('Parcelas baixadas não podem ser agrupadas.');
            return false;
        } else if (dataChecked[i].numcheque != '') {
            bootbox.alert('Parcelas vinculadas a algum cheque não podem ser agrupadas.');
            return false;
        }
        nome = dataChecked[i].nome;
        if (cliente_id == -1) {
            cliente_id = dataChecked[i].cliente_id;
        } else {
            if (dataChecked[i].cliente_id != cliente_id) {
                bootbox.alert('Todas as parcelas devem pertencer ao mesmo cliente.');
                return false;
            }
        }

        if (tipo == "R" && dataChecked[i].nossonumeroboleto != "") {
            bootbox.alert('Parcela: ' + dataChecked[i].id + " possui um boleto gerado então não pode ser agrupada.");
            return false;
        }

        duplicatas.push(dataChecked[i]);
    }
    if (duplicatas.length == 0){
        bootbox.alert('Escolha os títulos para serem agrupados.');
        return false;
    }
    $("#fmAbrirFinanceiro").attr('action', '{{URL::route('financeiro.createbyagrupar')}}');
    $('#cliente_id').val(cliente_id);
    $('#nome').val(nome);
    $('#parcelas_financeiro').val(JSON.stringify(duplicatas));
    $('#tipo_lancamento').val(tipo);
    $('#fmAbrirFinanceiro').submit();
    $('#popup_financeiro').modal('show');
}
window.closeModal = function () {
    $('#popup_recebercaixa').modal('hide');
    $('#popup_financeiro').modal('hide');
    carregarLancamentos();
};
function carregarLancamentosPesquisa(tipo){
    question = '';
    if(tipo==1){
        question = 'Informe o Código do Lançamento';
    } else
    if(tipo==2){
        question = 'Informe o Código do Pedido';
    } else
    if(tipo==3||tipo==5){
        question = 'Informe o Número da Nota Fiscal';
    } else
    if(tipo==4){
        question = 'Informe o Código do Fechamento de Convênio';
    }
    bootbox.prompt(question, function(result){
        if(result){
            tipoPesquisa = tipo;
            valorPesquisa = result;
            carregarLancamentos();
        }
    });
}
function carregarLancamentosFiltro(){
    tipoPesquisa = 0;
    valorPesquisa = 0;
    carregarLancamentos();
}
function limparPesquisa(){
    tipoPesquisa = 0;
    valorPesquisa = 0;
    var $select = $('#searchboxcliente').selectize();
    var control = $select[0].selectize;
    control.clear();
        @if($tipo_lancamento === 'R')
    $select = $('#searchboxcolaborador').selectize();
    control = $select[0].selectize;
    control.clear();
        @endif
    $('#datainicio').val(currentDate());
    $('#datafinal').val(currentDate());
    $('#divLancamentos').hide();
    $('input[name=tipodata][value="1"]').prop("checked", true);
    $('input[name=status_id][value="1"]').prop("checked", true);
    $("#total_receber, #total_recebido").val('R$ 0,00');
}


function abrirTelaAlterarDescricao() {
    duplicatasAlteradas = [];
    var data = tblLancamentos.getDataChecked();
    for (i = 0; i < data.length; i++) {
        $("#descricao_parcela").val(data[i].descricao);
        if (data[i].status == 'BAI') {
            bootbox.alert('Parcelas baixadas não podem ser alteradas.');
            return false;
        }
        duplicatasAlteradas.push(data[i].id);
    }
    if(duplicatasAlteradas.length > 1){
        bootbox.alert('Você só pode editar uma parcela por vez.');
        return false;
    }
    if (duplicatasAlteradas.length == 0) {
        bootbox.alert('Escolha o título para alterar.');
        return false;
    }

    @if($tipo_lancamento == "P")
        //$("label[for='descricao_parcela']").removeClass('col-sm-1').addClass('col-sm-4');
        $("#popup_alterarparcelas").children('.modal-dialog').attr('style', 'max-width: 40%;');
        //$("#divDescricaoParcela").removeClass('col-sm-3').addClass('col-sm-6');
    @endif
    var url = root + '/api/getTipoPgtoParcela/' + duplicatasAlteradas[0];
    ajaxGenerator(url, "GET", function (data) {
        if(typeof data == 'object') {
            $("#documento_parcela").val(data.documento);
            $("#descricao_parcela").val(data.descricao);
            $("#vencimento_parcela").val(data.vencimento);
            //$("label[for='descricao_parcela']").removeClass('col-sm-4').addClass('col-sm-1');
            $("#popup_alterarparcelas").children('.modal-dialog').attr('style', 'max-width: 40%;');
            //$("#divDescricaoParcela").removeClass('col-sm-6').addClass('col-sm-3');
            if(data.tipo == 2 || data.tipo == 3){
                $("#cartaonsu").val(data.cartaonsu);
                $("#cartaoautorizacao").val(data.cartaoautorizacao);
                $("#divInfoCartao").show();
            } else {
                $("#cartaonsu").val("");
                $("#cartaoautorizacao").val("");
                $("#divInfoCartao").hide();
            }
            $("#popup_alterarparcelas").modal('show');
        } else if(data === 'OK|') {
            //$("label[for='descricao_parcela']").removeClass('col-sm-1').addClass('col-sm-4');
            $("#popup_alterarparcelas").children('.modal-dialog').attr('style', 'max-width: 40%;');
            //$("#divDescricaoParcela").removeClass('col-sm-3').addClass('col-sm-6');
            $("#divInfoCartao").hide();
            $("#popup_alterarparcelas").modal('show');
        } else {
            bootbox.alert('Erro: ' + data);
        }
    });
}

function openLoaderProccess () {
    if (typeof dialog === "undefined") {
        dialog = bootbox.dialog({
            closeButton: false,
            class: 'dontHideEsc',
            title: 'Carregando Lançamentos!',
            message: '<p><i class="fa fa-spin fa-spinner"></i> Por favor aguarde..</p>'
        });
    }
}

function closeLoaderProcess () {
    if(typeof dialog !== "undefined"){
        dialog.modal('hide');
        dialog = undefined;
    }
}

function alterarDescricaoLancamento() {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    //console.log(hotCategorias.getData());
    $.ajax({
        url: root + '/api/alterarDescricaoLancamento',
        type: 'POST',
        dataType: 'json',
        data: {
            "_token": "{{ csrf_token() }}",
            parcelas: duplicatasAlteradas.join(','),
            motivo: $("#descricao_parcela").val(),
            documento: $("#documento_parcela").val(),
            vencimento: $("#vencimento_parcela").val(),
            cartaonsu: $("#cartaonsu").val(),
            cartaoautorizacao: $("#cartaoautorizacao").val(),
            contafechamento_id: -1,
            tipo_estorno: 'CR'
        },
        success: function (res) {
            if (res.substr(0, 3) == 'OK|') {
                setTimeout(function(){ bootbox.alert('Alteração efetivada com sucesso.'); }, 500);
                carregarLancamentos();
                $("#popup_alterarparcelas").modal('hide');
            } else {
                setTimeout(function(){ bootbox.alert(res); }, 500);
            }
        },
        error: function (data) {
            if (typeof (data) == 'object') {
                var msg = '';
                var responseText = '';
                for (var key in data) {
                    if (key == 'responseJSON') {
                        for (var key1 in data['responseJSON']) {
                            msg += data['responseJSON'][key1];
                        }
                    }
                    if (key == 'responseText') {
                        responseText = data['responseText'];
                    }
                }
                if (msg != '')
                    bootbox.alert('Erro ao alterar: ' + msg);
                else
                    bootbox.alert('Erro ao alterar: ' + responseText);
                //bootbox.alert('Erro ao gravar: ' + data.responseJSON.descricao);
            } else if (typeof (data) == 'string') {
                bootbox.alert('Erro ao alterar: ' + data);
            } else {
                bootbox.alert('Houve um erro desconhecido ao alterar!');
            }
        }
    });
}

function carregarLancamentosById(parc) {
    $('#divLancamentos').show();
    tblLancamentos.clear();
    openLoaderProccess();
    ajaxGenerator(root + '/api/searchParcelasByIds/' + parc, "GET", function (res) {
        if(typeof res == 'string') {
            bootbox.alert('' + res);
        } else {
            tblLancamentos.appendDataToTable(res);
            updateTotais(res);
            setTimeout(function () {
                tblLancamentos.draw();
                tblLancamentos.adjustWidth();
            }, 500);
        }
    }, null, null, false, function () {
        closeLoaderProcess();
    });

}
function initSearchbox(){
    $('#searchboxcliente').selectize({
        valueField: 'id',
        labelField: 'nome',
        searchField: ['nome'],
        maxOptions: 10,
        options: [],
        create: false,
        render: {
            option: function (item, escape) {
                return '<div>' + escape(item.nome) + '</div>';
            }
        },
        optgroups: [
        {value: 'cliente', label: 'Clientes'},
        ],
        optgroupField: 'class',
        optgroupOrder: ['cliente'],
        load: function (query, callback) {
            if (!query.length)
                return callback();
            $.ajax({
                url: root+"/api/{{$tipo_lancamento=='P'?'searchFornecedores':'searchClientes'}}",
                type: 'GET',
                dataType: 'json',
                data: {q: query},
                error: function () {
                    callback();
                },
                success: function (res) {
                    callback(res.data);
                }
            });
        },
        onChange: function () {
        }, onInitialize: function () {
            var existingOptions = JSON.parse(this.$input.attr('data-selectize-value'));
            var self = this;
            if (Object.prototype.toString.call(existingOptions) === "[object Array]") {
                existingOptions.forEach(function (existingOption) {
                    self.addOption(existingOption);
                    self.addItem(existingOption[self.settings.valueField]);
                });
            } else if (typeof existingOptions === 'object') {
                self.addOption(existingOptions);
                self.addItem(existingOptions[self.settings.valueField]);
            }
        }
    });

    @if($tipo_lancamento=='R')
        $('#searchboxcolaborador').selectize({
            valueField: 'id',
            labelField: 'nome',
            searchField: ['nome'],
            maxOptions: 10,
            options: [],
            create: false,
            render: {
                option: function (item, escape) {
                    return '<div>' + escape(item.nome) + '</div>';
                }
            },
            optgroups: [
            {value: 'cliente', label: 'Clientes'},
            ],
            optgroupField: 'class',
            optgroupOrder: ['cliente'],
            load: function (query, callback) {
                if (!query.length)
                    return callback();
                $.ajax({
                    url: root + '/api/searchColaboradores',
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        q: query
                    },
                    error: function () {
                        callback();
                    },
                    success: function (res) {
                        callback(res.data);
                    }
                });
            },
            onChange: function () {
            }, onInitialize: function () {
                var existingOptions = JSON.parse(this.$input.attr('data-selectize-value'));
                var self = this;
                if (Object.prototype.toString.call(existingOptions) === "[object Array]") {
                    existingOptions.forEach(function (existingOption) {
                        self.addOption(existingOption);
                        self.addItem(existingOption[self.settings.valueField]);
                    });
                } else if (typeof existingOptions === 'object') {
                    self.addOption(existingOptions);
                    self.addItem(existingOptions[self.settings.valueField]);
                }
            }
        });
    @endif
}

function initTables () {
    tblParcelasDisponiveis = $("#tblParcelasDisponiveis").DataTable({
        "language": {"url": urlDataTable},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": false,
        "bInfo": false,
        "bAutoWidth": false,
        "destroy": true,
        "sScrollY": "120"
    });
    tblParcelasAdicionadas = $("#tblParcelasAdicionadas").DataTable({
        "language": {"url": urlDataTable},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": false,
        "bInfo": false,
        "bAutoWidth": false,
        "destroy": true,
        "sScrollY": "120"
    });
}
</script>
