var dataParcela = [];
var dialog;
var hotParcelas;

numeral.language('pt-br', {
    delimiters: {
        thousands: '.',
        decimal: ','
    },
    abbreviations: {
        thousand: 'k',
        million: 'm',
        billion: 'b',
        trillion: 't'
    },
    ordinal: function (number) {
        return number === 1 ? 'er' : 'ème';
    },
    currency: {
        symbol: '$'
    }
});

$(document).ready(function () {
    initTable();
    submitted = false;
    $('.modal-wide').on('show.bs.modal', function () {
        var height = $(window).height() - 200;
        $(this).find('.modal-body').css('max-height', height);
    });

    $('#mainNav li:eq(1) a').hide();
    $('#jstreecc1').jstree({
        'core': {
            'data': $menudatacc,
            'multiple': false
        },
        "plugins": ["checkbox"],
        "checkbox": {
            "three_state": false
        }
    }).on('loaded.jstree', function () {
        $('#jstreecc1').jstree('close_all');
    });

    $('#jstreepc1').jstree({
        'core': {
            'data': $menudatapc,
            'multiple': false
        },
        "plugins": ["checkbox"],
        "checkbox": {
            "three_state": false
        }
    }).on('loaded.jstree', function () {
        $('#jstreepc1').jstree('close_all');
    });

    var myDate = new Date();
    $('#datetimepicker1').datetimepicker({
        locale: 'pt-br',
        viewMode: 'days',
        format: 'DD/MM/YYYY',
        defaultDate: myDate
    });
    $('#datetimepicker2').datetimepicker({
        locale: 'pt-br',
        viewMode: 'days',
        format: 'DD/MM/YYYY',
        defaultDate: myDate
    });
    $('#datetimepicker3').datetimepicker({
        locale: 'pt-br',
        viewMode: 'days',
        format: 'DD/MM/YYYY',
        defaultDate: myDate
    });
    $('#datetimepicker4').datetimepicker({
        locale: 'pt-br',
        format: 'DD/MM/YYYY HH:mm:ss',

    });

    tblRateio = $('#tblRateio').DataTable({
        "language": {"url": urlDataTable},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": true,
        "bInfo": false,
        "bAutoWidth": false,
        "columnDefs": [
            {
                "targets": [0],
                "visible": false
            },
            {
                "targets": [1],
                "visible": true
            },
            {
                "targets": [2],
                "visible": false
            },
            {
                "targets": [3],
                "visible": true
            },
            {
                "targets": [4],
                "visible": true
            },
            {
                "targets": [5],
                "visible": true
            }
        ]

    });
    $('#tblRateio').on('click', 'button', function () {
        var trElem = $(this).closest("tr");// grabs the button's parent tr element
        var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
        if ($(firstTd).text() !== "" && $(this).context.id === 'btnRemoverRateio')
            tblRateio.row($(this).parents('tr')).remove().draw();

    });
    $('.dinheiro').each(function () {
        var value = parseDinheiro($(this).val(), 2);
        $(this).val(value.toFixed(2));
        $(this).maskMoney('mask', $(this).val());
    });

    if (errorsAny) {
        dataParcela = JSON.parse($('#parcelas').val()).data;
        carregarRateioErro();
        preencheDadosCliente($('#cliente_id_erro').val());
    }
    carregarParcelas();
    if (origemAgrupar)
        $('#searchbox')[0].selectize.disable();
});

$( document ).on("hidden.bs.modal", ".bootbox.modal", function () {
    submitted = false;
});

$("#condicaopagamento_id").on('blur', function () {
    if (! isEmpty($(this).val()) ) carregarParcelamento();
});

$("#valor").on('change', function () {
    if (! isEmpty($("#condicaopagamento_id").val()) ) carregarParcelamento();
});

function atualizarParcelamento(condicao) {
    //A VISTA
    if (condicao.tipo == 0 || condicao.tipo == 2) {
        if (condicao.tipo == 0) {
            $('#divCartao').hide();
            $('#cartaonsu').val('');
            $('#cartaoautorizacao').val('');
        } else {
            $('#divCartao').show();
        }
        $('#mainNav li:eq(1) a').hide();
        $('#divVencimento').show();
        if (condicao.dias_primeira != null) {
            dt = trazerData($('#dataemissao').val());
            $('#datavencimento').val(padronizacaoData(dt.addDays(parseInt(condicao.dias_primeira))));
        }
    } else {
        //A PRAZO
        $('#mainNav li:eq(1) a').show();
        $('#divVencimento').hide();
        if (condicao.tipo == 1) {
            $('#divCartao').hide();
            $('#cartaonsu').val('');
            $('#cartaoautorizacao').val('');
            if (condicao.condicao_pagamento_parcela != null) {
                parcs = condicao.condicao_pagamento_parcela;
                dataParcela = [];
                total = parseDinheiro($('#valor').val(), 2);
                var num_dias = 0;
                for (i = 0; i < parcs.length; i++) {
                    dt = trazerData($('#dataemissao').val());
                    valorParcela = Math.round(parseFloat(parcs[i].percentualvalor) / 100 * parseDinheiro($('#valor').val(), 2) * 100) / 100;
                    total = Math.round(total * 100) / 100;
                    if (i == (parcs.length - 1)) {
                        valorParcela = total;
                    }
                    num_dias += parseInt(parcs[i].dias);
                    var data = [];
                    data.push(padronizacaoData(dt.addDays(parseInt(num_dias))));
                    data.push(valorParcela);
                    data.push(parcs[i].percentualvalor / 100);
                    dataParcela.push(data);
                    total -= valorParcela;
                }
                updateTblParcelas();
            }
        } else if (condicao.tipo == 3) {
            $('#divCartao').show();
            condicao.num_parcelas = isNaN(parseInt(condicao.num_parcelas)) ? 0 : parseInt(condicao.num_parcelas);
            if (condicao.num_parcelas > 0) {
                dataParcela = [];
                var percentualRestante = 1;
                total = parseDinheiro($('#valor').val(), 2);
                var num_dias = 0;
                for (var i = 0; i < condicao.num_parcelas; i++) {
                    dt = trazerData($('#dataemissao').val());
                    valorParcela = Math.round(parseDinheiro($('#valor').val(), 2) / condicao.num_parcelas * 100) / 100;

                    total = Math.round(total * 100) / 100;
                    percentualAtual = 1 / condicao.num_parcelas;
                    if (i == (condicao.num_parcelas - 1)) {
                        valorParcela = total;
                        percentualAtual = percentualRestante;
                    }
                    num_dias += parseInt(condicao.dias_primeira);
                    var data = [padronizacaoData(dt.addDays(num_dias)), valorParcela, percentualAtual];
                    dataParcela.push(data);
                    total -= valorParcela;
                    percentualRestante -= percentualAtual;
                }
                updateTblParcelas();
            }
        }
    }
    closeLoaderProcess();
    return true;
}

function updateTblParcelas() {
    dataParcela.push(['', 0, 0]);
    hotParcelas = undefined;
    carregarParcelas(function () {
        hotParcelas.loadData(data);
        hotParcelas.render();
        hotParcelas.updateSettings({
            cells: function (row, col, prop) {
                var cellProperties = {};
                if (row == dataParcela.length - 1) {
                    cellProperties.readOnly = true;
                }

                return cellProperties;
            }
        });
    });
}

function carregarParcelamento() {
    if ($('#condicaopagamento_id').val() == '' || $('#condicaopagamento_id').val() == 0) {
        clearTblParc();
        return;
    }
    if (!isEmpty($("#condicaopagamento_id").val()) && $('#condicaopagamento_id').val() != 0) {
        openLoaderProccess();
        $.ajax({
            url: root + '/api/searchCondicaoPagamento',
            type: 'GET',
            dataType: 'json',
            data: {
                q: $('#condicaopagamento_id').val(),
            },
            success: function (res) {
                clearTblParc();
                atualizarParcelamento(res);
            }, error: function () {
                clearTblParc();
                closeLoaderProcess();
            }
        });
    }
}

function openLoaderProccess() {
    if (typeof dialog === "undefined") {
        dialog = bootbox.dialog({
            closeButton: false,
            class: 'dontHideEsc',
            title: 'Gerando parcelas!',
            message: '<p><i class="fa fa-spin fa-spinner"></i> Por favor aguarde..</p>'
        });
    }
}

function closeLoaderProcess() {
    if (typeof dialog !== "undefined") {
        dialog.modal('hide');
        dialog = undefined;
    }
}

function carregarParcelas(callback) {
    var containerParcelas = document.querySelector('#parcelasGrid');
    hotParcelas = new Handsontable(containerParcelas, {
        data: dataParcela,
        columnSorting: false,
        sortingEnabled: false,
        contextMenu: false,
        rowHeaders: false,
        formulas: true,
        readOnly: false,
        width: 700,
        height: 250,
        colHeaders: ["Dia", "Valor", "%"],
        colWidths: [100, 150, 100],
        maxRows: dataParcela.length,
        columns: [
            {
                readOnly: false,
                className: "htCenter",
                type: 'date', dateFormat: 'DD/MM/YYYY', correctFormat: true
            },
            {
                type: 'numeric',
                format: '0,0.00',
                language: 'pt-br',
                readOnly: false,
                className: "htCenter",
                renderer: function (instance, td, row, col, prop, value) {
                    if (row == instance.countRows() - 1) {
                        value = getTotal();
                    }
                    Handsontable.NumericRenderer.apply(this, arguments);
                }
            },
            {
                className: "htRight",
                type: 'numeric',
                format: '0,0.00%',
                language: 'pt-br',
                renderer: function (instance, td, row, col, prop, value) {
                    if (row == instance.countRows() - 1) {
                        value = getTotalPerc();
                    }
                    Handsontable.NumericRenderer.apply(this, arguments);
                }
            }
        ],
        afterChange: function (changes, source) {
            if (changes != null) {
                if (source != '%') {
                    var b, i, value;
                    var total = parseDinheiro($('#valor').val(), 2);

                    for (var i = 0; i < changes.length; i++) {
                        var change = changes[i];
                        var line = change[0];

                        b = parseFloat(this.getDataAtCell(line, 1));
                        if (total != '' && total != undefined)
                            value = b / total;

                        this.setDataAtCell(change[0], 2, value, '%');
                    }

                }
            }
        },
        afterRender: function () {
            closeLoaderProcess();
        }
    });
    if (typeof callback === "funcion")
        callback();
}

function gravar() {
    if ($('#baixar').val()) {
        $('#datahorabaixaM').val(currentDateTimeComplete());
        $('#popup_caixa').modal('show');
    } else {
        save();
    }
}
function setDadosCaixa() {
    $('#contamovimentotipo_id').val($('#contamovimentotipo_idM').val());
    $('#datahorabaixa').val($('#datahorabaixaM').val());
    save();
}

function getTotal() {
    return dataParcela.sum(1);
}

function getTotalPerc() {
    return dataParcela.sum(2);
}

function addRateio() {
    var cc = $('#jstreecc1').jstree(true).get_selected()[0];
    var pc = $('#jstreepc1').jstree(true).get_selected()[0];
    var cc_descricao = $('<textarea />').html($('#jstreecc1').jstree().get_selected(true)[0].text).text();
    var pc_descricao = $('<textarea />').html($('#jstreepc1').jstree().get_selected(true)[0].text).text();

    if (!isInt(cc)) {
        bootbox.alert('Escolha o centro de custo.');
        return;
    }
    if (!isInt(pc)) {
        bootbox.alert('Escolha o plano de contas.');
        return;
    }
    if ($('#valor_rateio').val() == '' || parseDinheiro($('#valor_rateio').val(), 2) == 0) {
        bootbox.alert('Informe o valor.');
        return;
    }
    if (parseFloat($('#valor_rateio').val()) == 0) {
        bootbox.alert('Informe o valor.');
        return;
    }
    tblRateio.row.add([
        cc,
        cc_descricao,
        pc,
        pc_descricao,
        $('#valor_rateio').val(),
        "<button type='button' class='btn btn-nw-registro small' id='btnRemoverRateio'>Remover</button>"
    ]).draw(false);
    $('#valor_rateio').val('');
}
function carregarRateioErro() {
    tblRateio.clear();
    var us = JSON.parse($('#rateio').val());
    for (var i = 0; i < us.length; i++) {
        tblRateio.row.add([
            us[i][0],
            us[i][1],
            us[i][2],
            us[i][3],
            us[i][4],
            us[i][5]
        ]).draw(false);
    }
}

function preencheDadosCliente(cod_cliente) {
    if (cod_cliente == '')
        return;
    $.ajax({
        url: root + '/api/searchClienteCompleto',
        type: 'GET',
        dataType: 'json',
        data: {
            q: cod_cliente
        },
        success: function (res) {

            if (res.tipopessoacadastro == "F") {
                $('#cpf').val(res.cpf);
                $('#rg').val(res.rg);
            } else {
                $('#cpf').val(res.cnpj);
                $('#rg').val(res.inscricao_estadual);
            }
            if (tipoTela === 'COMPLEXA') {
                if (res.condicaopagamento_id != null) {
                    $('#condicaopagamento_id')
                            .find('option')
                            .remove()
                            .end()
                            .append('<option value="' + res.condicaopagamento_id + '">' + res.condicaopagamento_descricao + '</option>')
                            .val(res.condicaopagamento_id);
                }
            }
        }
    });
}

function save() {
    if (submitted)  return;
    submitted = true;
    var valor = $('#valor').val().replace('R$ ', '');
    valor = valor.replace(/\./g, '');
    valor = valor.replace(',', '.');
    valor = parseFloat(valor);
    if (!($.isNumeric(valor))) {
        bootbox.alert('O valor do documento deve ser informado.');
        return false;
    }
    var valor = $('#valor').val().replace('R$ ', '');
    valor = valor.replace(/\./g, '');
    valor = valor.replace(',', '.');
    valor = parseFloat(valor);

    if (valor <= 0) {
        bootbox.alert('Por favor, insira um valor maior que 0.');
        return;
    }

    var rateio = [];
    var total = 0;
    tblRateio.rows().every(function () {
        var d = this.data();
        var val = d[4].replace('R$ ', '');
        val = val.replace(/\./g, '');
        val = val.replace(',', '.');
        total += parseFloat(val);
        rateio.push(d);
    });
    total = parseFloat(total.toFixed(2));
    if (isEmpty($('#condicaopagamento_id').val()) || !parseInt($('#condicaopagamento_id').val()) > 0) {
        bootbox.alert('Informe a condição de pagamento.');
        return;
    }
    if (total != 0 && total != valor) {
        bootbox.alert('Total do rateio difere do valor do documento.');
        return false;
    }
    if (total == 0 && ($('#planoconta_id').val() == '' || $('#centrocusto_id').val() == '')) {
        bootbox.alert('Informe o Plano de Contas/Centro de Custo ou o rateio.');
        return false;
    }
    if ($('#datavencimento').val() == '' && parseFloat(getTotal().toFixed(2)) == 0) {
        bootbox.alert('Informe a data de vencimento ou as parcelas.');
        return false;
    }
    if (parseFloat(getTotal().toFixed(2)) != 0 && parseFloat(getTotal().toFixed(2)) != valor) {
        bootbox.alert('Total das parcelas difere do valor do documento.');
        return false;
    }
    if ($('#searchbox').val() == '') {
        bootbox.alert('Informe o cliente/fornecedor.');
        return false;
    }

    if ($('#baixar').val()) {
        if ($('#contamovimentotipo_id').val() == '') {
            bootbox.alert('Informe o tipo de recebimento.');
            return false;
        }
        if ($('#datahorabaixa').val() == '') {
            bootbox.alert('Informe a data de baixa.');
            return false;
        }
    }
    $('#searchbox')[0].selectize.enable();
    $('#rateio').val(JSON.stringify(rateio));
    var data = hotParcelas.getData();
    $('#parcelas').val(JSON.stringify({data: data, desconto: $("#desconto").val()}));

    if (voltar) {
        $('form#fmCadastroR').submit();
        return;
    }
    var myForm = document.getElementById('fmCadastroR');
    var formData = new FormData(myForm);
    ajaxGenerator(urlStore, "POST", function (res) {
        $('#popup_caixa').modal('hide');
        if (res.substr(0, 3) == 'OK|') {
            id = res.split('|')[1];
            window.location.href = urlret;
        } else {
            bootbox.alert(res);
        }
    }, null, formData, false, function () {
        if (origemAgrupar)
            $('#searchbox')[0].selectize.disable();
            submitted = false;
    });
}

function initTable() {
    $('#searchbox').selectize({
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
                url: urlClientes,
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
            $('#cliente_id_erro').val($('#searchbox').selectize()[0].selectize.getValue());
            $('#cliente_nome_erro').val($('#searchbox').selectize()[0].selectize.getItem(this.items[0]).context.innerText);
            preencheDadosCliente($('#searchbox').selectize()[0].selectize.getValue());
        }, onInitialize: function () {
            var existingOptions = JSON.parse(this.$input.attr('data-selectize-value'));
            var self = this;
            if (errorsAny) {
                var opt = [{"id": $('#cliente_id_erro').val(), "nome": $('#cliente_nome_erro').val()}];
                opt.forEach(function (existingOption) {
                    self.addOption(existingOption);
                    self.addItem(existingOption[self.settings.valueField]);
                });
            } else if (origemAgrupar) {
                var opt = optCliente;
                opt.forEach(function (existingOption) {
                    self.addOption(existingOption);
                    self.addItem(existingOption[self.settings.valueField]);
                });
            } else {
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
        }
    });
}

function clearTblParc() {
    var count = hotParcelas.countRows() > 0;
    if (count) {
        try {
            hotParcelas.clear();
        } catch (e) {
            console.log(e);
        }
    }
}