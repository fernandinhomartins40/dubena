let lastProdChanged;
let operacoesDB;
let clientSearch = null;
let tblProdutos;
let tblRateios;

var xmlClipboard = new Clipboard('.xmlCopyClipboard');
xmlClipboard.on('success', function (e) {
    tooltipChange("Copiado", true, '.xmlCopyClipboard');
    e.clearSelection();
});

xmlClipboard.on('error', function (e) {
    console.error('Action:', e.action, 'Trigger:', e.trigger);
    tooltipChange("Impossível Copiar", true, '.xmlCopyClipboard');
});

$(".xmlCopyClipboard").on('mouseout', function ( ) {
    tooltipChange("Copiar XML", false, '.xmlCopyClipboard');
});

function tooltipChange(text, show = true, classChange)
{
    var $t = $(classChange);
    $t.attr('title', text)
        .tooltip('fixTitle');
    if (show)
        $t.tooltip('show');
}

function onDocReady() {
    initSatSelectize();
    initSatTables();
    jQueryFunctions();
    validations();
    desabilitaCamposPermanentesFinanceiro();
    desabilitaCamposTotal();
    if (show) {
        prepareToShow();
    }

    $("#btnTransmitirCFe").on("click", function () {
        showLoaderAjax("Aguarde..", "Transmitindo..", false, function () {
            fetchPOSTString(urlTransmitir, {}).then((data) => {
                hideLoaderAjax(() => bootbox.alert(data));
            }).catch((e) => {
                hideLoaderAjax(() => bootbox.alert(e.message));
            });
        });
    });

    $("#btnCancelarCFe").on("click", function () {
        showLoaderAjax("Aguarde..", "Cancelando..", false, function () {
            fetchPOSTString(urlCancelar, {}).then((data) => {
                hideLoaderAjax(() => bootbox.alert(data));
            }).catch((e) => {
                hideLoaderAjax(() => bootbox.alert(e.message));
            });
        });
    });

}

function prepareToShow() {
    let ignoreList = [
        "#btnVisualizarParcelas", "#xml", "#btnTransmitirCFe", "#btnCancelarCFe"
    ];
    $("#fmCadastro").find(":input:not(" + ignoreList.join(",") + ")").prop("disabled", true).trigger("chosen:updated");
    $("#reloadDest").attr("disabled", "disabled");
    disableOrEnableDest(false);
    setTimeout(() => {
        $("#actionEdit").removeAttr("disabled");
    }, 100);
}

function disableOrEnableDest(enable) {
    if (enable) {
        $("#nomecliente").selectize()[0].selectize.enable();
    } else {
        $("#nomecliente").selectize()[0].selectize.disable();
    }
}

function desabilitaCamposTotal() {
    $("#icmsvprod").prop("readonly", true).attr("tabindex", -1);
    $("#icmsvdesc").prop("readonly", true).attr("tabindex", -1);
    $("#icmsvcfe").prop("readonly", true).attr("tabindex", -1);
    $("#icmsvicms").prop("readonly", true).attr("tabindex", -1);
    $("#icmsvcofins").prop("readonly", true).attr("tabindex", -1);
    $("#icmsvpis").prop("readonly", true).attr("tabindex", -1);
}

function jQueryFunctions() {
    $("#nfoperacao_id").change(changeOperacao);

    $("#reloadDest").click(() => {
        let id = $("#cliente_id").val();
        getDest(id, () => {
            let nome = $("#cliente_nome_erro").val();
            clearDest();
            fillDest(id, nome);
            calcularTotais();
        }, "id");
    });
}

function validations() {
    operacoesDB = JSON.parse($("#objOperacoes").val());
    if (hasErrors || editOrShow) {
        var $nomeCliente = $("#nomecliente");
        var select = $nomeCliente.selectize()[0].selectize;
        addOptionNomeSelectize(select);
        carregarProdutosErro();
        if (hasErrors) {
            carregarRateiosErro();
        }
    } else {
        changeOperacao(true).catch((e) => {
            bootbox.alert(e.message);
        });
    }
}
function initSatTables() {
    tblProdutos = $('#tblProdutos').DataTable({
        "language": {
            "url": urlDataTable
        },
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": true,
        "bInfo": false,
        "bAutoWidth": false,
        "sScrollY": "200px",
        "columnDefs": [{
            "targets": [0, 2, 4, 9, 10, 11, 12, 13, 14, 15],
            "visible": false
        }]
    });

    tblRateios = $('#tblRateios').DataTable({
        "language": {
            "url": urlDataTable
        },
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": true,
        "bInfo": false,
        "bAutoWidth": false,
        "columnDefs": [{
            "targets": [0, 3],
            "visible": false
        }]
    });
}

function addOptionNomeSelectize(select) {
    let cliente_id = $("#cliente_id").val();
    select.addOption([{
        nome: $("#destxnome").val(),
        nomecompleto: $("#cliente_nome_erro").val(),
        id: cliente_id
    }]);
    select.addItem(cliente_id);
}

function clearSelectize($selector) {
    let select = $selector.selectize()[0].selectize;
    select.clearOptions();
    select.refreshOptions(true);
    select.refreshItems();
}

function initSatSelectize() {
    //configura o plugin selectize para a busca de ruas
    $("#nomecliente").selectize({
        valueField: "id",
        labelField: "nome",
        searchField: ["nome", "nomecompleto"],
        maxOptions: 10,
        hideSelected: true,
        options: [],
        create: false,
        render: {
            option: function (item, escape) {
                if (typeof item.nomecompleto === "undefined") {
                    return;
                }
                var dataSplited = item.nomecompleto.split('||');
                var name = typeof dataSplited[0] === "undefined" ? "" : dataSplited[0];
                var fantasia = typeof dataSplited[1] === "undefined" ? "" : dataSplited[1];
                fantasia = isEmpty(fantasia) ? "" : " - " + fantasia;
                return "<div><b>" + escape(name) + "</b>" + escape(fantasia) + "</div>";
            }
        },
        optgroups: [{
            value: "cliente",
            label: "Clientes"
        }],
        optgroupField: "class",
        optgroupOrder: ["cliente"],
        load: function (query, callback) {
            let select = $("#nomecliente").selectize()[0].selectize;
            select.clearOptions();
            if (! query.length)
                return callback();
            getDest(query, callback, "name");
        },
        onChange: function (data) {
            if (typeof buscaSelectize !== "undefined" && buscaSelectize !== false) {
                var $selectize = $("#nomecliente").selectize()[0].selectize;
                if (typeof $selectize.getItem(this.items[0]).context === "object") {
                    fillDest($selectize.getValue(), $selectize.getItem(this.items[0]).context.innerText);
                }
                buscaSelectize = false;
            }
            if (isEmpty(data) && ! hasErrors) {
                clearDest();
            }
        },
        onDropdownOpen: function ($dropdown) {
            $dropdown.css('visibility', this.lastQuery !== null && this.lastQuery.length ? 'visible' : 'hidden');
        }
    });
}

/**
 * @var objOperacao
 * @property objOperacao.informacoesadicionalfisco
 * @property objOperacao.tiponf
 * @property objOperacao.cadastronf
 */
function changeOperacao(loading) {
    clearDest();
    clearSelectize($("#nomecliente"));
    return new Promise((resolve) => {
        loading = typeof loading === "undefined" ? false : loading;
        var nfoperacao_id = floatVal($("#nfoperacao_id").val());
        var objOperacao = {};
        if (nfoperacao_id > 0) {
            objOperacao = getOperacaoAtual(nfoperacao_id);
            if (! objOperacao) {
                return;
            }
            $("#operacaotiponf").val(objOperacao.tiponf);
            $("#operacaocadastronf").val(objOperacao.cadastronf);
            $("#nfoperacao_id_2").val(nfoperacao_id).trigger('chosen:updated');

            if (! show) {
                if (objOperacao.movimentafinanceiro === '0') {
                    if (! loading) {
                        $("#vdesc").val("0,00");
                        $("#descricaofinanceiro").val("");
                        $("#centrocusto_id").val("");
                        $("#centrocusto_descricao").val("");
                        $("#condicaopagamento_id").val("");
                    }
                    bloquearCamposFinanceiro(false);
                } else {
                    liberarCamposFinanceiro();
                }
            }
        }
        resolve();
    });
}

function getDest(query, callback, getBy) {
    let operacaocadastronf = $("#operacaocadastronf").val();
    $.ajax({
        url: root + "/api/searchClienteNF",
        type: "GET",
        dataType: "json",
        data: {
            q: query,
            clientefornecedor: operacaocadastronf,
            mode: "C",
            get_by: getBy
        },
        error: getErrorFunctionAjaxGeneric(callback),
        success: function (res) {
            buscaSelectize = true;
            clientSearch = res.data;
            callback(res.data);
        }
    });
}

function fillDest(id, nomeCompleto) {
    if (clientSearch == null) {
        bootbox.alert("Erro desconhecido ao buscar cliente, entre em contato com o suporte.");
        return;
    }
    let data = clientSearch.where("id", id).first(true);
    if (data == null) {
        bootbox.alert("Erro desconhecido ao buscar cliente, entre em contato com o suporte.");
        return;
    }
    setPrecoProduto();
    $("#cliente_id").val(id);
    $("#cliente_nome_erro").val(nomeCompleto);
    $("#destxnome").val(data.nome);
    $("#destcnpj").val(data.cnpj);
    $("#destcpf").val(data.cpf);
    $("#destxlgr").val(data.rua);
    $("#destxbairro").val(data.bairro);
    $("#destnro").val(data.numero);
    $("#destxcpl").val(data.complemento);
    $("#destxmun").val(data.cidade);
    $("#destuf").val(data.uf);
    clientSearch = null;
}

function clearDest() {
    $("#destxnome").val("");
    $("#destcnpj").val("");
    $("#destcpf").val("");
    $("#destxlgr").val("");
    $("#destxbairro").val("");
    $("#destnro").val("");
    $("#destxcpl").val("");
    $("#destxmun").val("");
    $("#destuf").val("");
    $("#cliente_id").val("");
    $("#cliente_nome_erro").val("");
}

function setDetailsProd(produto_id) {
    var precoF = 0;
    if (! isEmpty(produto_id)) {
        let callback = (prod) => {
            let preco = formataDecimal(prod.precovenda, 2);
            $("#produto_valor").val("R$ " + preco);
        };
        precoF = getPrecoProdutoCliente($("#precosProdutosPadrao"), $("#precoprodutos"), produto_id, true, callback);
    }
    $("#produto_valor").val("R$ " + formataDecimal(precoF, 2));
}

function setPricesItems(data) {
    var precosProdutosPadrao = [];
    for (let i = 0; i < data.length; i++) {
        precosProdutosPadrao.push({
            "produto_id": data[i].id,
            "precovenda": parseFloat(data[i].precovenda),
            "precovendaminimo": parseFloat(data[i].precovendaminimo),
            "pesoliquido": data[i].pesoliquido,
            "pesobruto": data[i].pesobruto,
            "pgni": data[i].pgni,
            "pgnn": data[i].pgnn,
            "pglp": data[i].pglp,
            "tipo_glp": data[i].tipo_glp,
            "cprodanp": data[i].cprodanp
        });
    }
    $("#precosProdutosPadrao").val(JSON.stringify(precosProdutosPadrao));
}

function addProdutosClick() {
    if (! $("#cliente_id").intVal()) {
        hideLoaderAjax();
        bootbox.alert("Selecione o cliente antes de adicionar produtos!");
        return;
    }
    var $produto = $("#produto_id");
    var produto_id = $produto.intVal();
    var produto_valor = parseDinheiro($("#produto_valor").val(), 2);
    var produto_quantidade = floatVal($("#produto_quantidade").val());

    carregaProdutoAjax(produto_id, function (arrayProduto) {
        validateImpostoProduto(arrayProduto).then(() => {
            hideLoaderAjax();
            let $operacao = $("#nfoperacao_id_2");
            let $setor = $("#setor_id");
            tblProdutos.rows().eq(0).each(function (i) {
                var row = tblProdutos.row(i);
                var data = row.data();
                if (typeof data === "undefined") {
                    return;
                }
                if (parseInt(data[4]) === produto_id
                    && $operacao.val() === data[0]
                    && $setor.val() === data[2]
                    && "R$ " + formataDecimal(produto_valor, 2) === data[6]
                ) {
                    produto_quantidade += floatVal(data[7]);
                    tblProdutos.row(i).remove();
                }
            });
            produto_quantidade = numberFormat(produto_quantidade, 4);
            tblProdutos.row.add([
                $operacao.val(), // 0
                $operacao.find("option:selected").text(), // 1
                $setor.val(), // 2
                $setor.find("option:selected").text(), // 3
                produto_id, // 4
                $produto.find("option:selected").text(), // 5
                "R$ " + formataDecimal(produto_valor, 2), // 6
                produto_quantidade, // 7
                '<button class="btn btn-nw-registro btn-xs" id="btnRemoverProduto" type="button">Remover</button>', // 8
                arrayProduto['ncm'], // 9
                arrayProduto['unidademedida']['sigla'], // 10
                arrayProduto['ean'], // 11
                arrayProduto['nfeextipi'], // 12
                arrayProduto['nfcest'], //13
                arrayProduto['nfedescricaofiscal'], //14
                arrayProduto['nfgrupofiscal_id'], //15
            ]);

            tblProdutos.draw(false);
            $("#produto_id").val('0').trigger('chosen:updated');
            $("#produto_valor").val('');
            $("#produto_quantidade").val('');
            $setor.val('0').trigger('chosen:updated').focus().trigger('chosen:activate');
        }).catch((er) => {
            console.warn(er);
            hideLoaderAjax();
        });
    });
}

function calcularTotais(calculaImposto, callbackT) {
    var totalvalorproduto = 0;
    var vdesc = floatVal($("#vdesc").val(), 2);
    var voutro = floatVal($("#icmsvoutro").val());
    tblProdutos.rows().eq(0).each(function (i) {
        var row = tblProdutos.row(i);
        var data = row.data();
        if (typeof data !== "undefined") {
            var valorproduto = floatVal(data[6], 2);
            var quantidadeproduto = floatVal(data[7], 4);
            var qdeCalcular = floatVal(quantidadeproduto) === 0 ? 1 : quantidadeproduto;
            totalvalorproduto += qdeCalcular * valorproduto;
        }
    });
    $("#icmsvcfe").val(formataDecimal(totalvalorproduto + voutro - vdesc));

    var vbruto = totalvalorproduto + voutro;
    var liquido = vbruto - vdesc;
    $("#icmsvprod").val(formataDecimal(totalvalorproduto, 2));
    $("#icmsvdesc").val(formataDecimal(vdesc, 2));
    $("#vliq").val(formataDecimal(liquido, 2));
    $("#vbruto").val(formataDecimal(vbruto, 2));
    $(".dinheiro").trigger('mask.maskMoney');
    updateRateioUnique();
    if (typeof callbackT === "function")
        callbackT();
}
