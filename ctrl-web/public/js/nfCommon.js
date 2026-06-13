/**
 * @file funções comuns para NF, Lançamento de Documentos e SAT
 * @author Jeferson
 */

let calculateOnSubmit = false;

$(window).load(function () {
    $("#btnCalculaTotais").prop('disabled', show);
});

$(document).ready(function () {

    $("#addProdutos").on("click", buscaQuantidadeProdutoNF);

    $(".dinheiro").trigger('mask.maskMoney');

    $("form").addClass("js-allow-double-submission");

    $("#btnPconta").on("click", function () {
        abrirPlanoContaPorOperacao('planoconta_id', 'planoconta_descricao');
    });

    $("#btnRateioPconta").on("click", function () {
        abrirPlanoContaPorOperacao('rateioplanoconta_id', 'rateioplanoconta_descricao', '2');
    });

    $("#addRateio").on("click", adicionarRateio);

    $("#btnRateioCcusto").on("click", function () {
        abrirCentroCusto('jstreecc3', 'rateiocentrocusto_id', 'rateiocentrocusto_descricao');
    });

    $("#btnCcusto").on("click", function () {
        abrirCentroCusto('jstreecc', 'centrocusto_id', 'centrocusto_descricao');
    });

    $("#tblRateios").on('click', 'button', function () {
        var $self = $(this);
        var trElem = $self.closest("tr");
        var cod = $($(trElem).children("td")[0]).text();
        if (! isEmpty(cod) && $self.context.id === 'btnRemoverRateio') {
            tblRateios.row($self.parents('tr')).remove().draw();
        }
        if (tblRateios.rows().data().length === 0) {
            $("#user_change_rateio").val(0);
        }
    });

    $("#tblProdutos").on('click', 'button', function () {
        var $self = $(this);
        var trElem = $self.closest("tr");
        var cod = $($(trElem).children("td")[0]).text();
        if (! isEmpty(cod) && $self.context.id === 'btnRemoverProduto') {
            tblProdutos.row($self.parents('tr')).remove().draw();
        }
    });

    if (isEmissaoPropria() && (tipoNf === "SAT" || $("#nfefinalidade").intVal() !== 2)) {
        $("#produto_valor").on('change', function () {
            let $self = $(this);
            let _ignoreAlert = tipoNf === "SAT" ? false : ! isCfopVenda();
            let _value = validatePrecoMinimo($("#precosProdutosPadrao"), $("#produto_id").val(), $self.val(), _ignoreAlert);
            $self.val(_value.indexOf("R$") >= 0 ? _value : "R$ " + _value);
        });
    }

    if (! show) {
        $("#fmCadastro").on('submit', function (e) {
            if (! calculateOnSubmit) {
                e.preventDefault();
                calcularTotais(true, function () {
                    calculateOnSubmit = true;
                    $("#fmCadastro").trigger('submit');
                });
                return false;
            }
            calculateOnSubmit = false;
            if (typeof needChaveRef === "function" && needChaveRef() && $("#chaveacessoref").val().length === 0) {
                e.preventDefault();
                var msg = "É preciso informar a Chave de Acesso de Referência para NF de finalidade 4 ou com presença de comprador 5 ou com CFOP for 5929!";
                bootbox.alert(msg, function () {
                    $(".nav-tabs a[href='#tab_8']").tab("show");
                    $("#chaveacessoref").focus();
                });
                return false;
            }
            var produtos = [];
            tblProdutos.rows().every(function () {
                var d = this.data();
                produtos.push(d);
            });
            if (produtos.length === 0) {
                e.preventDefault();
                bootbox.alert("Ao menos um produto deve ser adicionado.");
                return false;
            }
            $("#produtos").val(JSON.stringify(produtos));
            var rateios = [];
            let ratRows = tblRateios.rows();
            ratRows.every(function () {
                var d = this.data();
                rateios.push(d);
            });
            if ($("#user_change_rateio").intVal() && ratRows.data().length === 1) {
                e.preventDefault();
                let msg = "O rateio deve ser usado para informar mais de um Plano de Contas e/ou Centro de custos. " +
                    "Para gerar somente um rateio informe o Plano de Contas e Centro de custos na aba Financeiro " +
                    "que o sistema o fará automaticamente.";
                bootbox.alert(msg);
                return;
            }
            $("#rateios").val(JSON.stringify(rateios));

            if (tipoNf !== "SAT") {
                if (typeof needChaveRef !== "undefined" && needChaveRef() && $("#chaveacessoref").val().length === 0) {
                    e.preventDefault();
                    var msg = "É preciso informar a Chave de Acesso de Referência para NF de finalidade 4 ou para presença do comprador 5 ou com CFOP for 5929!";
                    bootbox.alert(msg, function () {
                        $(".nav-tabs a[href='#tab_8']").tab("show");
                        $("#chaveacessoref").focus();
                    });
                    return false;
                }
                changeIdDest(false);
                if (tipoNf === "E" && ! indFinalChecked && $("#nfmodelo").intVal() === 65) {
                    e.preventDefault();
                    checkIndFinal();
                    return;
                }
                try {
                    $("#nfefinalidade, #nfmodelo, #iddest, #indfinal").prop('disabled', false);
                    let $indFinal = $("#indfinal");
                    $indFinal.val($indFinal.is(":checked") ? "1" : "0");
                    if (duplicate) {
                        $("#duplicate").val("1");
                    } else {
                        $("#duplicate").val("0");
                    }
                    $("select").trigger('chosen:updated');
                } catch (e) {
                    hideLoaderAjax();
                }
            }
            hideLoaderAjax(() => {
                setTimeout(function () {
                    showLoaderAjax("Aguarde..", "Enviando requisição..", false);
                }, 100);
            });
        });

        $("#produto_quantidade").on('keyup', function () {
            enableDisableBtnAddProd();
        }).change(function () {
            if (tipoNf !== "SAT") {
                var produto_quantidade = floatVal($("#produto_quantidade").val().replace(".", "").replace(",", "."));
                if (produto_quantidade > 0) {
                    var $qVol = $("#qVol");
                    $qVol.val(parseInt(produto_quantidade)).trigger('mask.maskNumber');
                    if (produto_quantidade < 1)
                        $qVol.val(1).trigger('mask.maskNumber');
                    $("#pesoL").val(formataDecimal(produto_quantidade * $("#pesoLhidden").val(), 3));
                    $("#pesoB").val(formataDecimal(produto_quantidade * $("#pesoBhidden").val(), 3));
                } else {
                    $("#qVol, #pesoL, #pesoB").val('');
                }
            }
        }).blur(function () {
            enableDisableBtnAddProd();
            validateQdeValueProd();
        });
        enableDisableBtnAddProd();
        $("#produto_id").change(function () {
            enableDisableBtnAddProd();
            var value = $(this).intVal();
            $("#produto_valor").val("");
            if (value) {
                if (! $("#cliente_id").intVal()) {
                    if (tipoNf === "SAT") {
                        bootbox.alert('Selecione o Cliente');
                    } else {
                        bootbox.alert('Selecione o ' + descriptionForClient);
                    }
                    $(this).val("").trigger('chosen:updated');
                    return;
                }
                setDetailsProd(value);
            }
        });
        $("#centrocusto_descricao, #planoconta_descricao").on('change', function () {
            updateRateioUnique();
        });

        $("#condicaopagamento_id").change(function () {
            if ($(this).val() > 0) {
                showLoaderAjax();
                buscarParcelasAjax(tipoNf === "SAT" ? "dataparcela" : "datahoraemissao", "vencimentoprimeiraparcela").then(() => {
                    hideLoaderAjax();
                }).catch((response) => {
                    bootbox.alert("Erro ao buscar parcelas: " + response.responseText.substr(0, 100));
                });
            }
            if (tipoNf !== "SAT") {
                if (tipoNf === "E" && $("#nfmodelo").intVal() === 65) {
                    checkIfIsDup($(this).val());
                } else {
                    hideTipoPgtoDup();
                }
            }
        });
    }

    $("#btnVisualizarParcelas").click(viewParcelas);
    $("#btnCalculaTotais").on('click', function () {
        calcularTotais();
    });
});

function viewParcelas() {
    let idFieldValue = tipoNf === "SAT" ? "vliq" : "liquidoParcelas";
    let idFieldDate = tipoNf === "SAT" ? "dataparcela" : "datahoraemissao";
    let value = $("#" + idFieldValue).val();
    if (parseDinheiro(value) > 0) {
        let onError = (error) => {
                let errorString = (typeof error === "string" ? (error.message ? error.message : "Erro desconhecido") : "Erro desconhecido");
                bootbox.alert("Erro ao visualizar parcelas: " + errorString);
                console.error(error);
        };
        let onSuccess = () => {
            calcularParcelas(idFieldValue, idFieldDate, "parcelas_financeiro")
        };
        if (show && ! $("#parcelas_financeiro").isEmpty()) {
            onSuccess();
        } else {
            calculateParcelas().then(onSuccess).catch(onError);
        }
    } else {
        bootbox.alert("Valor líquido não calculado, clique em \"Calcular Totais\"!");
    }
}

function calculateParcelas(isFrete) {
    return new Promise((resolve, reject) => {
        if ((isFrete && isEmpty($("#fretecondicaopagamento_id").val())) || (! isFrete && isEmpty($("#condicaopagamento_id").val()))) {
            reject("Selecione a condição de pagamento!");
            return;
        }
        let value;
        if (tipoNf === "SAT") {
            value = $("#vliq").val();
        } else if (isFrete) {
            value = $("#vfrete").val();
        } else {
            value = $("#liquidoParcelas").val();
        }
        value = parseDinheiro(value);
        let idFieldValue = tipoNf === "SAT" ? "vliq" : "liquidoParcelas";
        let idFieldDate = tipoNf === "SAT" ? "dataparcela" : "datahoraemissao";
        if (value > 0) {
            if (! isFrete) {
                tblparc.clear();
                calcularParcelas(idFieldValue, idFieldDate, undefined, undefined, undefined, undefined, undefined, undefined, undefined, false).then(() => {
                    setarParcelasFinanceiro("parcelas_financeiro", "tblparc");
                    resolve();
                }).catch((err) => {
                    reject(err);
                });
            } else {
                tblfreteparc.clear();
                calcularParcelas("vfrete", "datahoraemissao", undefined, "fretevista", "freteboleto", "fretecartao", "tblfreteparc", "fretecondicaoparcelas", "fretecondicao", false).then(() => {
                    setarParcelasFinanceiro("frete_parcelas_financeiro", "tblfreteparc");
                    resolve();
                }).catch((err) => {
                    reject(err);
                });
            }
        } else {
            if (isFrete) {
                reject("Sem valor de frete para visualizar as parcelas!");
            } else {
                reject("Valor líquido não calculado, clique em \"Calcular Totais\"!");
            }
        }
    });
}

function setarParcelasFinanceiro(idParcelaFinanceiro, idTblParcelas) {
    var $idParc = $("#" + idParcelaFinanceiro);
    $idParc.val("");
    var parcelas = [];
    idTblParcelas = eval(idTblParcelas);
    idTblParcelas.rows().every(function () {
        var data = this.data();
        parcelas.push({
            'datavencimento': data[1],
            'valorefetivado': parseDinheiro(data[2], 2)
        });
    });
    $idParc.val(JSON.stringify(parcelas));
}

function validateQdeValueProd() {
    var $prodVal = $("#produto_valor");
    var $prodQde = $("#produto_quantidade");
    $prodQde.removeClass('hasError');
    $prodVal.removeClass('hasError');
    if (floatVal($prodVal.val()) <= 0)
        $prodVal.addClass('hasError');
    if (floatVal($prodVal.val()) <= 0)
        $prodQde.addClass('hasError');
}

function enableDisableBtnAddProd() {
    $("#addProdutos").prop('disabled', isEmptyFieldsProd());
}

function isEmptyFieldsProd() {
    var $qde = $("#produto_quantidade");
    var $vlr = $("#produto_valor");
    var qde = floatVal($qde.val());
    var value = floatVal($vlr.val());
    var empty = isEmptyMultiple([
        $("#nfoperacao_id_2").val(),
        $("#setor_id").val(),
        $("#produto_id").val(),
        $vlr.val(),
        $qde.val()
    ]);
    return empty || ((qde <= 0 || value <= 0) && $("#nfefinalidade").intVal() !== 2);
}

function buscaQuantidadeProdutoNF() {
    var produto_id = $("#produto_id").val();
    var setor_id = $("#setor_id").val();
    var movimentaestoque = 2; //2=Saida
    if (validateFileldsBuscaProduto()) {
        var nfoperacao_id_2 = $("#nfoperacao_id_2").val();
        let objOperacao = getOperacaoAtual(nfoperacao_id_2);
        movimentaestoque = floatVal(objOperacao.movimentaestoque);
        if (movimentaestoque === 0 || ! isEmissaoPropria()) {
            addProdutosClick();
            return;
        }
        showLoaderAjax("Validando informações e Verificando estoque");
        var url = root + '/consultaestoquesetor/buscaquantidadeprodutopermitenegativar/:produto_id/:setor_id/:qtdemovimentar/:entradasaida';
        var quantidade = parseFloat($("#produto_quantidade").val());
        url = url.replace(':produto_id', produto_id);
        url = url.replace(':setor_id', setor_id);
        url = url.replace(':qtdemovimentar', quantidade.toString());
        url = url.replace(':entradasaida', movimentaestoque.toString());
        fetchGET(url).then((data) => {
            var qdeEstoque = parseFloat(data);
            if (isNaN(qdeEstoque)) {
                hideLoaderAjax();
                bootbox.alert("Erro: " + data);
            } else if (qdeEstoque >= quantidade || movimentaestoque === 1) {
                addProdutosClick();
            } else {
                confirmNegativaEstoque();
            }
        }).catch(e => {
            console.warn(e);
            hideLoaderAjax();
            bootbox.alert(e);
        });
    }
}

function confirmNegativaEstoque() {
    bootbox.confirm({
        title: 'Atenção!',
        className: 'warning',
        message: 'Se utilizar esse produto com essa quantidade, o estoque será negativado, deseja continuar?',
        buttons: {
            confirm: {
                label: 'Sim',
                className: 'btn-nw-geral'
            },
            cancel: {
                label: 'Não',
                className: 'btn-nw-registro'
            }
        },
        callback: function (result) {
            if (result)
                addProdutosClick();
            else
                hideLoaderAjax();
        }
    });
}

function validateFileldsBuscaProduto() {
    var nfoperacao_id_2 = $("#nfoperacao_id_2").val();
    var produto_id = $("#produto_id").val();
    var setor_id = $("#setor_id").val();
    var produto_valor = $("#produto_valor").val();
    var produto_quantidade = $("#produto_quantidade").val();
    var faltacampos = "";
    if (isEmptyNullOrUndefined(nfoperacao_id_2))
        faltacampos += "Operação";
    if (isEmptyNullOrUndefined(produto_id)) {
        if (!faltacampos.isEmpty())
            faltacampos += ", ";
        faltacampos += "Produto";
    }
    if (isEmptyNullOrUndefined(setor_id)) {
        if (!faltacampos.isEmpty())
            faltacampos += ", ";
        faltacampos += "Setor";
    }
    if (isEmptyNullOrUndefined(produto_valor)) {
        if (!faltacampos.isEmpty())
            faltacampos += ", ";
        faltacampos += "Valor";
    }
    if (isEmptyNullOrUndefined(produto_quantidade)) {
        if (!faltacampos.isEmpty())
            faltacampos += ", ";
        faltacampos += "Quantidade";
    }
    if (! faltacampos.isEmpty()) {
        bootbox.alert("Existem campo(s) obrigatórios que não foram adicionados: " + faltacampos + ".");
        return false;
    }
    return true;
}

function carregaProdutoAjax(produto_id, callback) {
    var url = root + '/produto/ajax/:id';
    if (typeof lastProdChanged === "object" && lastProdChanged.id === parseInt(produto_id)) {
        callback(lastProdChanged);
        return;
    }
    if (typeof produto_id !== 'undefined') {
        url = url.replace(':id', produto_id);
        fetchGET(url).then((data) => {
            callback(data[0]);
            lastProdChanged = data[0];
        }).catch((e) => {
            bootbox.alert(e.message);
            hideLoaderAjax();
        });
    } else {
        hideLoaderAjax();
    }
}

function setPrecoProduto() {
    var url = root + "/clienteproduto/buscaprecosprodutos/:cliente_id";
    var $cliente = $("#cliente_id");
    url = url.replace(":cliente_id", $cliente.val());
    if (typeof $cliente.val() !== "undefined" && $cliente.val() !== "") {
        ajaxGenerator(url, "GET", function (data) {
            var produtos = [];
            for (let i = 0; i < data.length; i++) {
                var d = data[i];
                d.preco = parseFloat(d.preco) ? parseFloat(d.preco) : 0;
                d.desconto = parseFloat(d.desconto) ? parseFloat(d.desconto) : 0;
                produtos.push(d);
            }
            $("#precoprodutos").val(JSON.stringify(produtos));
        });
    }
    setDetailsProd($("#produto_id").val());
}

function validateImpostoProduto(prod) {
    var $operacao = $("#nfoperacao_id_2");
    var empresa_id = $("#empresa_id").val();
    let emituf = $("#emituf").val();
    let destuf = $("#destuf").val();
    let iddest = $("#iddest").val();
    let fretemodalidade = $("#fretemodalidade").val();
    let nfmodelo = $("#nfmodelo").val();
    let presencacomprador = $("#presencacomprador").val();
    let tipolancamento = isEmissaoPropria() ? 0 : 1;
    var url = root + '/issetImpostoProdutoAjax';
    url += '?nfoperacao_id=' + $operacao.val();
    url += '&empresa_id=' + empresa_id;
    url += '&nfgrupofiscal_id=' + prod.nfgrupofiscal_id;
    url += '&emituf=' + emituf;
    url += '&destuf=' + destuf;
    url += '&fretemodalidade=' + fretemodalidade;
    url += '&nfmodelo=' + nfmodelo;
    url += '&presencacomprador=' + presencacomprador;
    url += '&tipolancamento=' + tipolancamento;
    if (tipoNf === "SAT") {
        url += '&tela=sat';
    } else if (tipoNf === "E") {
        url += "&tela=emitida";
    } else {
        url += "&tela=recebida";
    }
    url += '&iddest=' + iddest;
    return new Promise((resolve, reject) => {
        fetchGET(url).then((data) => {
            if (typeof data === "object" && data.status) {
                resolve();
            } else {
                let msg = data;
                if (typeof data === "object" && ! data.status) {
                    if (prod.grupo_fiscal === null) {
                        msg = "Nenhum grupo fiscal está vinculado ao produto \"" + prod.descricao + '\"';
                    } else {
                        msg = "Não foi encontrado nenhum imposto para produto \"" + prod.descricao + "\"";
                        if (emituf !== destuf) {
                            if (isEmissaoPropria()) {
                                msg += " saindo do \"" + emituf + "\" para \"" + destuf + "\"";
                            } else {
                                msg += " saindo do \"" + destuf + "\" para \"" + emituf + "\"";
                            }
                        }
                        msg += " com o grupo fiscal \"" + prod.grupo_fiscal.descricao + "\" e operação \"";
                        msg += $operacao.find("option:selected").html() + "\".";
                    }
                }
                bootbox.alert(msg);
                reject(new Error(msg));
            }
        }).catch((e) => {
            bootbox.alert(e.message);
            reject(e);
        });
    });
}

function isEmissaoPropria() {
    return tipoNf === "E" || tipoNf === "SAT" || $("#tipolancamento").intVal() === 0;
}

function adicionarRateio() {
    var rateiocentrocusto_id = $("#rateiocentrocusto_id").intVal();
    var rateiocentrocusto_descricao = $("#rateiocentrocusto_descricao").val();
    var rateioplanoconta_id = $("#rateioplanoconta_id").intVal();
    var rateioplanoconta_descricao = $("#rateioplanoconta_descricao").val();
    var rateiovalor = floatVal($("#rateiovalor").val());
    if (! rateiocentrocusto_id) {
        bootbox.alert("Informe o Centro de Custos do rateio!");
        return;
    }
    if (! rateioplanoconta_id) {
        bootbox.alert("Informe o Plano de Contas do rateio!");
        return;
    }
    if (! rateiovalor) {
        bootbox.alert("Informe o valor do rateio!");
        return;
    }
    $("#user_change_rateio").val(1);

    tblRateios.rows().eq(0).each(function (i) {
        var row = tblRateios.row(i);
        var data = row.data();
        if (typeof data !== "undefined") {
            if (parseInt(data[0]) === rateiocentrocusto_id && parseInt(data[3]) === rateioplanoconta_id) {
                tblRateios.row(i).remove();
            }
        }
    });

    getPCCCById(rateiocentrocusto_id, rateioplanoconta_id, rateiovalor, function (data) {
        tblRateios.row.add([
            rateiocentrocusto_id, // 0
            data.centrocusto.codigo, // 1
            rateiocentrocusto_descricao, // 2
            rateioplanoconta_id, // 3
            data.planoconta.codigo, // 4
            rateioplanoconta_descricao, // 5
            "R$ " + formataDecimal(rateiovalor, 2), // 6
            '<button class="btn btn-nw-registro btn-xs" id="btnRemoverRateio" type="button">Remover</button>' // 7
        ]);
        tblRateios.draw(false);
        $("#rateiocentrocusto_id").val('0');
        $("#rateiocentrocusto_descricao").val('');
        $("#rateioplanoconta_id").val('0');
        $("#rateioplanoconta_descricao").val('');
        $("#rateiovalor").val('');
    });
}

function getPCCCById(rateiocentrocusto_id, rateioplanoconta_id, rateiovalor, callback, alertIfError) {
    if (!alertIfError) {
        alertIfError = false
    }
    if (rateiocentrocusto_id && rateioplanoconta_id && rateiovalor) {
        var url = root + '/api/getPcCcById?pc_id=:pc_id&cc_id=:cc_id';
        url = url.replace(':cc_id', rateiocentrocusto_id);
        url = url.replace(':pc_id', rateioplanoconta_id);
        ajaxGenerator(url, "GET", function (data) {
            if (typeof data.status !== "undefined") {
                if (data.status === 200) {
                    callback(data.data);
                } else {
                    bootbox.alert("Erro: " + data.data);
                }
            } else {
                bootbox.alert("Erro: " + data);
            }
        });
    } else {
        if (alertIfError) {
            bootbox.alert("É preciso informar o Centro de Custo, Plano de Conta e Valor para adicionar o Rateio.");
        }
    }
}

function bloquearCamposFinanceiro(planoconta) {
    if (typeof planoconta === "undefined") {
        planoconta = false;
    }
    enableDisableCondPgto();
    $("#btnVisualizarParcelas").prop('disabled', true);
    //$("#vdesc").prop('readonly', true).prop('tabindex', -1);
    $("#descricaofinanceiro").prop('readonly', true).prop('tabindex', -1);
    $("#btnCcusto").prop('disabled', true);
    $("#btnPconta").prop('disabled', planoconta);
    changeTabIndexAttr();
}

function enableDisableCondPgto() {
    var objOperacao = getOperacaoAtual(parseInt($("#nfoperacao_id").val()));
    if (! objOperacao) {
        return;
    }
    var disabled;
    if (isEmissaoPropria()) {
        disabled = false;
    } else {
        disabled = ! parseInt(objOperacao.movimentafinanceiro);
    }
    $("#condicaopagamento_id").prop('disabled', disabled).trigger('chosen:updated');
}

function getOperacaoAtual(nfoperacao_id) {
    var objOperacao = operacoesDB.where('id', "==", nfoperacao_id).first();
    if (typeof objOperacao === "undefined" && objOperacao.length > 0) {
        bootbox.alert("Erro ao carregar dados da operacao");
        return false;
    }
    return objOperacao;
}

function abrirPlanoContaPorOperacao(id, descricao, treeid) {
    if (typeof treeid === "undefined") {
        treeid = "";
    }
    var nfoperacao_id = $("#nfoperacao_id").val();
    if (nfoperacao_id > 0) {
        var operacaotiponf = $("#operacaotiponf").intVal();
        if (operacaotiponf === 0)//Entrada
            abrirPlanoConta('jstreepcD' + treeid, id, descricao);
        else if (operacaotiponf === 1)//Saída
            abrirPlanoConta('jstreepcR' + treeid, id, descricao);
        else
            abrirPlanoConta('jstreepc', id, descricao);
    } else {
        bootbox.alert("Operação deve ser informado antes.");
    }
}

function desabilitaCamposPermanentesFinanceiro() {
    var ids = "#vbruto, #vliq, #liquidoParcelas, #quantidadeparcelas, #vencimentoprimeiraparcela, #vnf";
    readonly(ids);
}

function liberarCamposFinanceiro() {
    enableDisableCondPgto();
    $("#btnVisualizarParcelas").prop('disabled', false);
    $("#vdesc").prop('readonly', false).removeAttr('tabindex');
    $("#descricaofinanceiro").prop('readonly', false).removeAttr('tabindex');
    $("#btnCcusto").prop('disabled', false);
    $("#btnPconta").prop('disabled', false);
    changeTabIndexAttr();
}

function updateRateioUnique() {
    var rowsRateios = tblRateios.rows();
    var cc_id = $("#centrocusto_id").val();
    var pc_id = $("#planoconta_id").val();
    var valor = $("#vbruto").val();
    var dataR = rowsRateios.data();
    if (dataR.length === 1 && ! $("#user_change_rateio").intVal()) {
        dataR = dataR[0];
        if (! cc_id) {
            cc_id = dataR[0];
        }
        if (! pc_id) {
            pc_id = dataR[3];
        }
        getPCCCById(cc_id, pc_id, valor, function (data) {
            var row = rowsRateios.row(0);
            var dataRow = row.data();
            dataRow[0] = cc_id;
            dataRow[1] = data.centrocusto.codigo;
            dataRow[2] = $("#centrocusto_descricao").val();
            dataRow[3] = pc_id;
            dataRow[4] = data.planoconta.codigo;
            dataRow[5] = $("#planoconta_descricao").val();
            dataRow[6] = valor;
            row.data(dataRow).draw();
        }, false);
    }
}

function carregarProdutosErro() {
    var produtos = $('#produtos').val();
    if (! isEmpty(produtos)) {
        tblProdutos.clear().rows.add(JSON.parse(produtos)).draw();
    }
    calcularTotais();
}

function carregarRateiosErro() {
    tblRateios.clear();
    var rateios = $('#rateios').val();
    if (! isEmpty(rateios)) {
        tblRateios.clear().rows.add(JSON.parse(rateios)).draw();
    }
}
