var tblParcelas;
var tblParcelasGerar;
var lastSelectedRow;
var totalInicial = 0;
var totalFinalEditParcela = 0;
var gerouAtualizou = false;
var atualizar = false;
var btnClick = false;
var contaAtual = {};
var vencimentoOriginal = null;
var valorOriginal = 0;
var jurosOriginal = 0;
var valorLiquido = 0;
var multaOriginal = 0;
var editboleto_id = 0;
var lastRowEdited;

$(document).ready(function () {
    adjustPaginate();

    tblParcelas = initTable('tblParcelas');
    tblParcelasGerar = initTable('tblParcelasGerar', [{targets: [9], visible: false}]);
    $("#tblParcelas").css('cssText', 'margin-top: -10px !important');

    initSelectize();
    initFields();
});
$("#tblParcelas").on('click', 'button', function () {
    btnClick = true;
}).on('click', 'td', function () {
    if (!btnClick) {
        var trElem = $(this).parent('tr');
        if (window.event.ctrlKey) {
            marcarVariasLinhas(trElem);
        }

        if (window.event.button === 0) {
            if (!window.event.ctrlKey && !window.event.shiftKey) {
                clearAllRows();
                marcarVariasLinhas(trElem);
            }
            if (window.event.shiftKey) {
                selectRowsBetweenIndexes(lastSelectedRow, trElem)
            }
        }
        contTotalSelected();
    }
    btnClick = false;
});

$("#tblParcelasGerar").on('dblclick', 'tr', function () {
    var data = tblParcelasGerar.row(this).data();
    $("#divDescricao").html('Editando a parcela nº ' + data[1] + ' código ' + data[0] + '.');
    totalInicial = data[8].moneyToFloat() - data[6].moneyToFloat() - data[7].moneyToFloat();
    $("#total").val(data[8]);
    $("#juros").val(data[6]);
    $("#multa").val(data[7]);
    jurosOriginal = data[6].moneyToFloat();
    multaOriginal = data[7].moneyToFloat();
    $("#datavencimento").val(data[3]);
    vencimentoOriginal = data[3];
    valorOriginal = data[5].moneyToFloat();
    valorLiquido = data[8].moneyToFloat();
    totalFinalEditParcela = data[8].moneyToFloat();
    $("#modal_editaparcela").modal('show');
    lastRowEdited = this;

    if (!isEmpty(data[11])) {
        var url = root + "/getContaById/" + data[11];
        ajaxGenerator(url, "GET", function (data) {
            if (typeof data === 'object')
                contaAtual = data;
            else if (typeof data === 'string')
                bootbox.alert("Erro: " + data);
            else
                bootbox.alert("Erro desconhecido");
        }, function () {
            bootbox.alert('Erro ao carregar dados da conta');
        });
    }
});

$("#salvarParcela").on('click', function () {
    if (totalFinalEditParcela <= 0) {
        bootbox.alert('O valor total da parcela não pode ficar menor ou igual a zero.');
    } else {
        var data = tblParcelasGerar.row(lastRowEdited).data();
        data[8] = $("#total").val();
        data[6] = $("#juros").val();
        data[7] = $("#multa").val();
        data[3] = $("#datavencimento").val();
        tblParcelasGerar.row(lastRowEdited).data(data).draw();
        $("#modal_editaparcela").modal('hide');
    }
});

$("#juros, #multa").change(function () {
    var juros = $("#juros").moneyToFloat();
    var multa = $("#multa").moneyToFloat();
    totalFinalEditParcela = totalInicial + juros + multa;
    $("#total").val("R$ " + formataDecimal(totalFinalEditParcela, 2, true));
});

$("#datavencimento").blur(function () {
    var date1 = moment(vencimentoOriginal, "DD/MM/YYYY");
    var date2 = moment($(this).val(), "DD/MM/YYYY");
    var diffDays = date2.diff(date1, 'days');
    if (typeof contaAtual.id !== "undefined")
        calcularJurosMulta(diffDays);
});

function calcularJurosMulta(days) {
    if (days > 0) {
        days = days > 0 ? days : 1;
        var juros = (valorLiquido * parseFloat(contaAtual.boletojuros) / 100 * days) + jurosOriginal;
        var multa = (valorLiquido * parseFloat(contaAtual.boletomulta) / 100) + multaOriginal;

        $("#total").val('R$ ' + formataDecimal(valorLiquido + juros + multa, 2));
        $("#juros").val('R$ ' + formataDecimal(juros, 2));
        $("#multa").val('R$ ' + formataDecimal(multa, 2));
    }
}

$("#fmGeraBoleto").on('submit', function () {
    if (isEmpty($("#conta_id").val()) && !atualizar) {
        bootbox.alert("Selecione a conta para gerar boletos.");
        return false;
    }
    var parcelas = [];
    tblParcelasGerar.rows().every(function () {
        var d = this.data();
        parcelas.push({
            id: d[0],
            numero: d[1],
            datacompetencia: d[2],
            datavencimento: d[3],
            cliente: d[4],
            valor: d[5],
            juros: d[6],
            multa: d[7],
            valorefetivado: d[8],
            cliente_id: d[10]
        });
    });
    // var formData = new FormData($(this)[0]);
    var inseredescparcela = typeof $("#inseredescparcela:checked").val() !== "undefined";
    var url = root + '/boleto';
    $('#inseredescparcelaInput').val(inseredescparcela);
    $('#parcelas').val(JSON.stringify(parcelas));

    $(this).attr('action', url);
    gerouAtualizou = true;
});

$("#btnFiltrar").on('click', function () {
    var tipofiltro = $("#tipofiltro:checked").val();
    var datainicio = $("#datainicio").val();
    var datafim = $("#datafim").val();
    var cliente_id = $("#cliente_id").val();
    var gerouboleto = typeof $("#gerouboleto:checked").val() !== "undefined" ? 1 : 0;
    var gerouremessa = typeof $("#gerouremessa:checked").val() !== "undefined" ? 1 : 0;
    var url = root + '/boleto?datainicio=' + datainicio;
    url += '&datafim=' + datafim + '&tipofiltro=' + tipofiltro;
    url += '&gerouboleto=' + gerouboleto + '&gerouremessa=' + gerouremessa;
    url += '&cliente_id=' + cliente_id;
    window.location.href = url;
});

$("#gerouboleto").on('change', function () {
    $("#gerouremessa").prop('disabled', !$(this).prop('checked'));
});

$("#btnGerarBoleto").on('click', function () {
    tblParcelasGerar.clear().draw();
    var clienteAnterior = null;
    var erro = false;
    var qdeGerou = 0;
    var qdeNaoGerou = 0;
    tblParcelas.rows('.linhaselecionada').every(function () {
        var data = this.data();
        if (erro)
            return;
        if (clienteAnterior !== null && clienteAnterior !== data[4]) {
            bootbox.alert('As parcelas selecionadas devem pertencer ao mesmo cliente');
            erro = true;
        }
        if (data[9] === "Sim")
            qdeGerou++;
        else
            qdeNaoGerou++;
        if (qdeGerou > 0 && qdeNaoGerou > 0 && !erro) {
            bootbox.alert('As parcelas selecionadas devem ter o status de "Gerou Boleto" iguais.');
            erro = true;
        }
        tblParcelasGerar.row.add(data);
        clienteAnterior = data[4];
    });
    if (erro === false) {
        $("#juros, #multa").prop('disabled', qdeGerou === 0);
        if (qdeGerou > 0) {
            $(".divConta").hide();
            $("label[for=inseredescparcela]").addClass('col-sm-offset-3');
            atualizar = true;
        } else {
            $(".divConta").show();
            $("label[for=inseredescparcela]").removeClass('col-sm-offset-3');
            atualizar = false;
        }
        tblParcelasGerar.draw();
        $("#modalGerarBoleto").modal('show');
    }
});
$("#modalGerarBoleto").on('hide.bs.modal', function () {
    if (gerouAtualizou)
        window.location.href = location.href;
        setTimeout(function () {
            $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
        }, 500);
});

$(document).on("hide.bs.modal", ".bootbox.modal", function () {
    setTimeout(function () {
        $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
    }, 500);
});

$("#btnLimpar").on('click', function () {
    $("#datainicio, #datafim").val(dataAtual());
    $("#tipofiltro[zero='true']").prop('checked', true);
    var select = $("#cliente_id").selectize()[0].selectize;
    select.clearOptions();
    select.refreshOptions(true);
    select.refreshItems();
    $("#gerouboleto, #gerouremessa").prop('checked', false);
    $("#gerouremessa").prop('disabled', true);
    $("#btnFiltrar").trigger('click');
});

function contTotalSelected() {
    var totalSelecionados = tblParcelas.rows('.linhaselecionada').data().length;
    $("#totalSelecionados").html(totalSelecionados + ' de 90 parcelas selecionados.');
    if (totalSelecionados === 0)
        $("#btnGerarBoleto").prop('disabled', true);
    else
        $("#btnGerarBoleto").prop('disabled', false);
}

//
function selectRowsBetweenIndexes(lastSelected, init) {
    var indexLastSelected = tblParcelas.row(lastSelected).index();
    var indexInit = tblParcelas.row(init).index();

    if (indexLastSelected > indexInit) {
        for (let i = indexInit; i <= indexLastSelected; i++) {
            $(tblParcelas.row(i).node()).addClass('linhaselecionada');
        }
    } else if (indexLastSelected < indexInit) {
        for (let i = indexLastSelected; i <= indexInit; i++) {
            $(tblParcelas.row(i).node()).addClass('linhaselecionada');
        }
    }
}

//seleciona várias linhas e muda o contador de parcelas selecionados
function marcarVariasLinhas(row) {
    if (row.hasClass('linhaselecionada')) {
        row.removeClass('linhaselecionada');
    } else {
        row.addClass('linhaselecionada');
    }
    lastSelectedRow = row;
}

function clearAllRows() {
    tblParcelas.rows('.linhaselecionada').nodes().to$().removeClass('linhaselecionada');
}

function initTable(tableId, defs = []) {
    return $("#" + tableId).DataTable({
        "language": {"url": urlDataTable},
        'paginate': false,
        'filter': false,
        'sort': false,
        'scrollY': "350",
        "bAutoWidth": false,
        'bInfo': false,
        'columnDefs': defs
    });
}

function openModalEdit(id) {
    editboleto_id = id;
    ajaxGenerator(root + '/api/getCodMovRemessaByBanco/' + id + '/0', "GET", function (data) {
        if ($.isArray(data) || typeof data === 'object') {
            var ocorrencias = '';
            $.each(data, function (i, el) {
                ocorrencias += "<option value='" + el.id + "'>" + el.codigo + ' - ' + el.descricao + "</option>";
            });
            $("#ocorrencia_id").html(ocorrencias).trigger('chosen:updated');
            $("#modal_editBoleto").modal('show');
        } else {
            bootbox.alert('' + data);
        }
    });
    showHideFieldsBoleto();
}

$("#ocorrencia_id").on('change', function () {
    showHideFieldsBoleto();
});

function showHideFieldsBoleto() {
    var $ocorrencia = $("#ocorrencia_id");
    var ocorrencia_id = $ocorrencia.val();
    if (isEmpty(ocorrencia_id)) {
        bootbox.alert("O campo Ocorrência não pode ser vazio");
        return false;
    }
    var ocorrencia = $ocorrencia.find("option:selected").text().substr(0, 2);
    var tipo = null;
    if (ocorrencia === '02') {
        tipo = 'baixa';
    } else if (ocorrencia === '03') {
        tipo = 'consessaoAAbatimento';
    } else if (ocorrencia === '04') {
        tipo = 'cancAbatimento';
    } else if (ocorrencia === '07') {
        tipo = 'alteracaoPrazoProtesto';
    } else if (ocorrencia === '08') {
        tipo = 'alteracaoPrazoDevolucao';
    } else if (ocorrencia === '11') {
        tipo = 'protestoParaDevolucao';
    } else if (ocorrencia === '12') {
        tipo = 'devolucaoParaProtesto';
    }

    $(".divFielsEditBoleto input, .divFielsEditBoleto label").hide().prop('disabled', true);
    if (tipo == null) {
        bootbox.alert('Erro ao validar o tipo de ocorrência');
        $("#btnUpdateBoleto").prop('disabled', true);
        return false;
    } else {
        $("#btnUpdateBoleto").prop('disabled', false);
    }

    $("." + tipo + " input, ." + tipo + " label").show().prop('disabled', false);
}

$("#fmEditBoleto").on('submit', function (e) {
    e.preventDefault();
    var error = false;
    $('.divFielsEditBoleto input').each(function () {
        var self = $(this);
        if (!self.prop('disabled') && isEmpty(self.val())) {
            bootbox.alert("O campo " + $("label[for='" + self.attr('name') + "']").html().replace(':', '') + " não pode ficar vazio!");
            error = true;
            return false;
        }
    });
    if (error)
        return false;
    var formData = new FormData($(this)[0]);
    formData.append('_method', 'PATCH');
    ajaxGenerator(root + '/boleto/' + editboleto_id, 'POST', function (data) {
        if (data === 'OK|')
            bootbox.alert({message: "Boleto atualizado com sucesso!", callback: function () {
                    location.reload()
                }});
        else
            bootbox.alert("" + data);
    }, null, formData);
});

function initSelectize() {
    $("#cliente_id").selectize({
        valueField: "id",
        labelField: "nome",
        searchField: ["nome"],
        maxOptions: 10,
        hideSelected: true,
        options: [],
        create: false,
        render: {
            option: function (item, escape) {
                return "<div><b>" + escape(item.nome) + "</b>" + "</div>";
            }
        },
        optgroups: [
            {value: "cliente", label: "Clientes"}
        ],
        optgroupField: "class",
        optgroupOrder: ["cliente"],
        load: function (query, callback) {
            var select = $("#cliente_id").selectize()[0].selectize;
            select.clearOptions();
            if (!query.length)
                return callback();
            $.ajax({
                url: root + "/api/searchClientes",
                type: "GET",
                dataType: "json",
                data: {
                    q: query
                },
                error: function (data) {
                    console.log(data);
                    callback();
                },
                success: function (res) {
                    callback(res.data);
                }
            });
        },
        onChange: function () {
            var select = $("#cliente_id").selectize()[0].selectize;
            if (typeof select.getItem(this.items[0]).context === "object") {
                $("#cliente_nome_reload").val(select.getItem(this.items[0]).context.innerText);
                $("#cliente_id_reload").val(select.getValue());
            }
        }, onInitialize: function () {
            var select = $("#cliente_id").selectize()[0].selectize;
            var cliente_id = $("#cliente_id_reload").val();
            var cliente_nome = $("#cliente_nome_reload").val();
            if (typeof select.getItem(this.items[0]).context !== "object" && !isEmpty(cliente_nome)) {

                select.addOption([{
                        nome: cliente_nome,
                        id: cliente_id}]);
                select.refreshOptions(true);
                select.refreshItems();
                select.addItem(cliente_id);
            }
        }, onDropdownOpen: function ($dropdown) {
            $dropdown.css('visibility', this.lastQuery != null && this.lastQuery.length ? 'visible' : 'hidden');
        }
    });
}

function initFields() {
    var $bol = $("#gerouboleto");
    var $rem = $("#gerouremessa");
    var bolChecked = getParametro('gerouboleto') === "1";
    $bol.prop('checked', bolChecked);
    $rem.prop('checked', getParametro('gerouremessa') === "1" && bolChecked);
    $rem.prop('disabled', !$bol.prop('checked'));
    $("#datainicio").val(getParametro('datainicio') ? getParametro('datainicio') : dataAtual());
    $("#datafim").val(getParametro('datafim') ? getParametro('datafim') : dataAtual());
}
