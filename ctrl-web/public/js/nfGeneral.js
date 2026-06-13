let urlCarregaDestinatarioAjax = root + "/cliente/buscaporid/:idcliente?nfe=1";
let tblProdutos;
let tblRateios;
let operacoesDB;
let condicoesPgtoDB;
let codAnpDB;
let cfopGLP;
let codAnpProdAtual;
let tipoGLPAtual;
let lastProdChanged;
let hasChaveAcesso = false;
let indFinalChecked = false;
let descriptionForClient = "Destinatário";
let descriptionForEmpresa = "Emitente";

$(document).ready(function () {
    if (editOrShow && !erros && !show) {
        changeIndFinal(false);
        changeIdDest(false);
    }
});

$(window).load(function () {
    if (tipoNf === "E") {
        $("#nfmodelo").focus().trigger("chosen:activate");
    } else {
        $("#chaveacesso").focus();
    }

    $(".dinheiro").trigger("mask.maskMoney");
    $("#btnCalculaTotais").prop("disabled", show);
    $("#setor_id").change(function () {
        enableDisableBtnAddProd();
    });

    if (!show) {
        changeVFrete();
    }

    if (tipoNf === "E") {
        let $chaveRef = $("#chaveacessoref");
        if ($chaveRef.prop("disabled")) {
            $chaveRef.prop("disabled", false).prop("readonly", true);
        }
        let $chave = $("#chaveacesso");
        if ($chave.prop("disabled")) {
            $chave.prop("disabled", false).prop("readonly", true);
        }
    }

    $(".selectize-input")
        .find("input")
        .focus(function () {
            var nfoperacao_id = $("#nfoperacao_id").intVal();
            if (tipoNf === "R" && hasChaveAcesso) {
                bootbox.alert("Não é possível selecionar o fornecedor manualmente num documento eletrônico!", function () {
                    $("#informacaocomplementar").focus();
                });
            } else if (!nfoperacao_id) {
                var msg = "Operação deve ser informado antes.";
                bootbox.alert(msg, function () {
                    setTimeout(function () {
                        $("#nfoperacao_id").trigger("chosen:activate");
                    }, 100);
                });
            }
        });
});

function onDocReadyGeneral(callback) {
    if (tipoNf === "E") {
        //ajusta os campos na tela
        setTimeout(function () {
            $("#presencacomprador_chosen").css("cssText", "width: 96%; !important");
            $("#nfoperacao_id_chosen").css("cssText", "width: 113% !important; right: 13% !important;");
        }, 1);
        $("#tab_1 .crud_space").each(function (i, el) {
            $(el).find("label").first().css("cssText", "left: 2% !important");
        });
    } else {
        $("#iddest_chosen").css("cssText", "min-width: 680% !important;");
    }
    condicoesPgtoDB = JSON.parse($("#condicoesPgtoDB").val());
    setHasChaveAcessoEntrada();
    initTable();
    $("#reloadDestinatario").on("click", function () {
        carregaDestinatarioAjax(function () {
            calcularTotais();
        });
    });

    $("#btnFretePconta").on("click", function () {
        abrirPlanoContaPorModalidadeFrete("freteplanoconta_id", "freteplanoconta_descricao", "");
    });

    $("#btnFreteCcusto").on("click", function () {
        abrirCentroCusto("jstreecc2", "fretecentrocusto_id", "fretecentrocusto_descricao");
    });

    $("#nfoperacao_id_2").on("change", enableDisableFieldsGLP);

    codAnpDB = JSON.parse($("#cProdAnpGLP").val());
    cfopGLP = JSON.parse($("#cfopGLP").val());

    if (!show) {
        if (isEmissaoPropria() && $("#nfefinalidade").intVal() !== 2) {
            $("#produto_valor").on("change", function () {
                let $self = $(this);
                let value = validatePrecoMinimo($("#precosProdutosPadrao"), $("#produto_id").val(), $self.val(), !isCfopVenda());
                $self.val(value.indexOf("R$") >= 0 ? value : "R$ " + value);
            });
        }
        var $cliente_id = $("#cliente_id");
        $cliente_id.change(function () {
            if ($(this).val() > 0) {
                carregaDestinatarioAjax();
            }
        });

        checkIfIsDup($("#condicaopagamento_id").val());

        $("#nfoperacao_id").change(function () {
            carregaOperacaoTrataTela();

            if (typeof expandOpeChange === "function") {
                expandOpeChange();
            }
        });
        treatFrete();
        var cliente_id = $cliente_id.val();

        if (cliente_id !== null && typeof cliente_id !== "undefined" && cliente_id !== "0") carregaDestinatarioAjax();

        desabilitaCamposDefinicao();
        desabilitaCamposEmitente();
        desabilitaCamposDestinatario();
        desabilitaCamposFrete();
        desabilitaCamposPermanentesFinanceiro();
        desabilitaCamposTotal();
        desabilitaCamposOperacao();
        onDocReady();
    } else {
        if (typeof changeTipoLancamento === "function") {
            changeTipoLancamento(true);
        }
        checkIfIsDup($("#condicaopagamento_id").val());
    }

    initSelectize();

    if (typeof callback === "function") callback();
}

function initSelectize() {
    //configura o plugin selectize para a busca de cliente pelo nome
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
                var dataSplited = item.nomecompleto.split("||");
                var name = typeof dataSplited[0] === "undefined" ? "" : dataSplited[0];
                var fantasia = typeof dataSplited[1] === "undefined" ? "" : dataSplited[1];
                fantasia = isEmpty(fantasia) ? "" : " - " + fantasia;
                return "<div><b>" + escape(name) + "</b>" + escape(fantasia) + "</div>";
            },
        },
        optgroups: [
            {
                value: "cliente",
                label: "Clientes",
            },
        ],
        optgroupField: "class",
        optgroupOrder: ["cliente"],
        load: function (query, callback) {
            var select = $("#nomecliente").selectize()[0].selectize;
            var operacaocadastronf = $("#operacaocadastronf").val();
            select.clearOptions();
            if (!query.length) return callback();
            $.ajax({
                url: root + "/api/searchClienteNF",
                type: "GET",
                dataType: "json",
                data: {
                    q: query,
                    clientefornecedor: operacaocadastronf,
                    empresa_id: $("#empresa_id").val(),
                },
                error: getErrorFunctionAjaxGeneric(callback),
                success: function (res) {
                    buscaSelectize = true;
                    callback(res.data);
                },
            });
        },
        onChange: function (data) {
            if (typeof buscaSelectize !== "undefined" && buscaSelectize !== false) {
                var $selectize = $("#nomecliente").selectize()[0].selectize;
                if (typeof $selectize.getItem(this.items[0]).context === "object") {
                    $("#cliente_nome_erro").val($selectize.getItem(this.items[0]).context.innerText);
                    $("#cliente_id_erro").val($selectize.getValue());
                    $("#cliente_id").val($selectize.getValue());
                    carregaDestinatarioAjax();
                }
                setPrecoProduto();
                buscaSelectize = false;
            }
            if (isEmpty(data) && !erros) {
                $("#cliente_nome_erro").val("");
                $("#cliente_id_erro").val("");
                $("#cliente_id").val("");
                limparCamposDestinatario();
            }
        },
        onDropdownOpen: function ($dropdown) {
            $dropdown.css("visibility", this.lastQuery !== null && this.lastQuery.length ? "visible" : "hidden");
        },
    });
}

function desabilitaCamposOperacao() {
    var ids = "#statusevento, #descricaoreceita, #motivoevendo, #xml, #descricaoreceita_2";
    if (tipoNf === "E") {
        ids += ", #chaveacesso, #chaveacessoref";
        $("#nfsituacao_id_2").prop("disabled", true);
    }
    readonly(ids);
    $("#nfsituacao_descricao").prop("disabled", true);
    $("select").trigger("chosen:updated");
}

function desabilitaCamposEdicao() {
    $("#nfmodelo").prop("readonly", true).prop("tabindex", -1);
    $("#nftipoambiente").prop("disabled", true).trigger("chosen:updated");
}

function desabilitaCamposTotal() {
    var ids = "#vbc, #vicms, #vbcst, #vst, #vbcfunrural, #totalqtdeprodutos, ";
    ids += "#totalpesobruto, #totalpesoliquido, #vprod, #totalvfrete, ";
    ids += "#totalvdesc, #vipi, #vpfunrural, #vfunrural, ";
    ids += "#vpis, #vcofins, #totalvbruto, #vicmsdeson, #vfcp, #vfcpst";
    readonly(ids);
}

function desabilitaCamposDefinicao() {
    if (tipoNf === "E") {
        $("#nftipoambiente").prop("disabled", true);
        $("#nfnumero, #nfserie").prop("readonly", true).prop("tabindex", -1);
    }
    if (!editOrShow || erros) {
        changeIdDest(!erros);
    }
    $("#informacaoadicionalfisco").prop("readonly", true).prop("tabindex", -1);
    $("select").trigger("chosen:updated");
}

function desabilitaCamposEmitente() {
    var ids = "#emittelefone, #emitrazaosocial, #emitnomefantasia, #emitie, #emitcnpj, ";
    ids += "#emitcpf, #emitinscricaomunicipal, #emitcnae, #codcrt, #emitpaiscodigoibge, ";
    ids += "#emitpaisnome, #emitufcodigoibge, #emituf, #emitcidadecodigoibge, #emitcidadenome, ";
    ids += "#emitcidade_id, #emitbairro, #emitendereco, #emitnumero, #emitcep, #emitcomplemento";
    readonly(ids);
}

function desabilitaCamposDestinatario() {
    var ids = "#destcliente_id,#destrazaosocial,#destie,#destcnpj,#destcpf,";
    ids += "#destindicadorietext,#destpaiscodigoibge,#destpaisnome,#destuf,";
    ids += "#destcidadecodigoibge,#destcidadenome,#destcidade_id,#destbairro,";
    ids += "#destendereco,#destnumero,#destcep,#destcomplemento,#desttelefone,#destemail";
    readonly(ids);
}

function carregaDestinatarioAjax(callback) {
    if (loadEditOrShow) return;
    var url = urlCarregaDestinatarioAjax;
    var cliente_id = $("#cliente_id").intVal();
    if (cliente_id === 0) {
        limparCamposDestinatario();
        return;
    }
    url = url.replace(":idcliente", cliente_id) + "?getPrecos=1";
    getClienteAjax(url, 0, callback);
}

//tipo 0 = destinatario, tipo 1 = frete
function getClienteAjax(url, tipo, callback) {
    ajaxGenerator(url, "GET", function (data) {
        var objCliente = data;
        if (tipo === 0) fillFieldsCliente(objCliente);
        else if (tipo === 1) fillFieldsFrete(objCliente);
        if (typeof callback === "function") callback();
    });
}

function fillFieldsCliente(objCliente) {
    var naoautorizado = "";
    if (objCliente.nfemite === "0") {
        naoautorizado = "Nota Fiscal.";
        bootbox.alert("Cliente " + objCliente.nome + " não esta marcado para emitir " + naoautorizado);
        return;
    }

    $("#destcliente_id").val(objCliente.id);
    $("#destrazaosocial").val(objCliente.nome);
    $("#destie").val(objCliente.inscricao_estadual);
    $("#destcnpj").val(objCliente.cnpj);
    $("#destcpf").val(objCliente.cpf);
    $("#destindicadorie").val(objCliente.indicador_ie);
    putTxtIndicadorIE(objCliente.indicador_ie);
    $("#destpaiscodigoibge").val($("#emitpaiscodigoibge").val());
    $("#destpaisnome").val("Brasil");
    $("#destuf").val(objCliente.uf);
    $("#destcidadecodigoibge").val(objCliente.cidade.cod_ibge);
    $("#destcidadenome").val(objCliente.cidade.descricao);
    $("#destcidade_id").val(objCliente.cidade.id);
    $("#destbairro").val(objCliente.bairro.descricao);
    $("#destendereco").val(objCliente.rua.descricao);
    $("#destnumero").val(objCliente.numero);
    $("#destcep").val(objCliente.cep);
    $("#destcomplemento").val(objCliente.complemento);
    if (objCliente.telefones.length !== 0) {
        $("#desttelefone").val(objCliente.telefones[0].telefone);
    }
    $("#destemail").val(objCliente.email);
    changeIdDest();
    // changeIndFinal();
}

function changeIdDest(changeValue = true) {
    let $idDest = $("#iddest");
    let modelo = $("#nfmodelo").intVal();
    let destuf = getUf("dest");
    let emituf = getUf();
    let ufDiff = destuf && emituf && emituf !== destuf;
    let emitEqualsDest = getCnpj("dest") === getCnpj();
    let value = $idDest.val();
    let oldValue = value;
    let disabled = !ufDiff || modelo === 65;
    if (isEmissaoPropria()) {
        value = modelo !== 65 && ufDiff ? "2" : "1";
    } else {
        value = ufDiff ? "2" : "1";
    }
    if (!ufDiff && modelo !== 65 && emitEqualsDest) {
        disabled = false;
    }
    if (!changeValue) {
        value = oldValue;
    }
    $idDest.val(value).prop("disabled", disabled).trigger("chosen:updated");
    return disabled;
}

function changeIndFinal(changeValue = true) {
    let checked = false;
    let disabled = false;
    if ($("#nfmodelo").intVal() === 65) {
        checked = true;
        disabled = true;
    }
    let $indFinal = $("#indfinal");
    if (changeValue) {
        $indFinal.prop("checked", checked);
    }
    $indFinal.prop("disabled", disabled);
}

function checkIndFinal() {
    if (!isClienteConfig() && onlyNumbers($("#destcnpj").val())) {
        let msg =
            "Na transmissão da NFC-e é somente aceito destinatário que é Consumidor Final. " +
            "Portanto, o destinatário informado será considerado consumidor final, deseja continuar?";
        bootboxConfirm("Atenção", msg, function (confirm) {
            if (confirm) {
                indFinalChecked = true;
                $("#fmCadastro").submit();
            }
        });
    } else {
        indFinalChecked = true;
        $("#fmCadastro").submit();
    }
}

function isClienteConfig() {
    return $("#cliente_config").intVal() === $("#cliente_id").intVal();
}

function getUf(type = "emit") {
    let destuf;
    let emituf;
    if (isEmissaoPropria()) {
        destuf = $("#destuf").val();
        emituf = $("#emituf").val();
    } else {
        destuf = $("#emituf").val();
        emituf = $("#destuf").val();
    }
    switch (type) {
        case "emit":
            return emituf;
        default:
            return destuf;
    }
}

function getCnpj(type = "emit") {
    let destcnpj;
    let emitcnpj;
    if (isEmissaoPropria()) {
        destcnpj = $("#destcnpj").val();
        emitcnpj = $("#emitcnpj").val();
    } else {
        destcnpj = $("#emitcnpj").val();
        emitcnpj = $("#destcnpj").val();
    }
    switch (type) {
        case "emit":
            return emitcnpj;
        default:
            return destcnpj;
    }
}

function putTxtIndicadorIE(indicador_ie) {
    indicador_ie = parseInt(indicador_ie) ? parseInt(indicador_ie) : 0;
    if (indicador_ie === 1) {
        $("#destindicadorietext").val(indicador_ie + " - Contribuinte  ICMS");
    } else if (indicador_ie === 2) {
        $("#destindicadorietext").val(indicador_ie + " - Contribuinte Isento");
    } else if (indicador_ie === 9) {
        $("#destindicadorietext").val(indicador_ie + " - Não Contribuinte");
    } else {
        if ($("#cliente_id").intVal()) {
            bootbox.alert("Informe o Indicador IE no cadastro de clientes");
        }
    }
}

function limparCamposDestinatario() {
    $("#destcliente_id").val("");
    $("#destrazaosocial").val("");
    $("#destie").val("");
    $("#destcnpj").val("");
    $("#destcpf").val("");
    $("#destindicadorie").val("");
    $("#destindicadorietext").val("");
    $("#destpaiscodigoibge").val("");
    $("#destpaisnome").val("");
    $("#destuf").val("");
    $("#destcidadecodigoibge").val("");
    $("#destcidadenome").val("");
    $("#destcidade_id").val("");
    $("#destbairro").val("");
    $("#destendereco").val("");
    $("#destnumero").val("");
    $("#destcep").val("");
    $("#destcomplemento").val("");
    $("#desttelefone").val("");
    $("#destemail").val("");
    $("#precoprodutos").val("");
}

function trataComboDestinatarioShowEdit() {
    if (!erros) refreshSelectize();
}

/**
 * @var objOperacao
 * @property objOperacao.informacoesadicionalfisco
 * @property objOperacao.tiponf
 * @property objOperacao.cadastronf
 */
function carregaOperacaoTrataTela(loading) {
    return new Promise((resolve) => {
        loading = typeof loading === "undefined" ? false : loading;
        var nfoperacao_id = floatVal($("#nfoperacao_id").val());
        var objOperacao = {};
        if (nfoperacao_id > 0) {
            objOperacao = getOperacaoAtual(nfoperacao_id);
            if (!objOperacao) {
                return;
            }
            $("#informacaoadicionalfisco").val(objOperacao.informacoesadicionalfisco);
            $("#operacaotiponf").val(objOperacao.tiponf);
            $("#operacaocadastronf").val(objOperacao.cadastronf);
            if (editOrShow === false && erros === false && !hasChaveAcesso) {
                clearNomeCliente(tipoNf === "E");
            }

            if (!show) {
                if (objOperacao.movimentafinanceiro === "0") {
                    if (!loading) {
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
                if (tipoNf === "E") {
                    var nfmodelo = $("#nfmodelo").val();
                    if (objOperacao.movimentafinanceiro === "0" && nfmodelo === "65") {
                        $("#condicaopagamento_id").prop("disabled", false).trigger("chosen:updated");
                    }
                }
            }
            $("#nfoperacao_id_2").val(nfoperacao_id).trigger("chosen:updated");
        }
        if (!loading) {
            $("#prev_operacao_id").val(nfoperacao_id);
        }
        resolve();
    });
}

function setDetailsProd(produto_id) {
    var precoF = 0;

    if (!isEmpty(produto_id)) {
        var callback = function (prod) {
            var preco = 0;
            let pGNi = numberFormat(parseFloat(prod.pgni), 4);
            let pGNn = numberFormat(parseFloat(prod.pgnn), 4);
            let pGLP = numberFormat(parseFloat(prod.pglp), 4);
            if (isEmissaoPropria()) {
                preco = formataDecimal(prod.precovenda, 2);
            } else {
                preco = formataDecimal(prod.customedio, 2);
            }
            $("#produto_valor").val("R$ " + preco);
            var pesoliquido = prod.pesoliquido;
            var pesobruto = prod.pesobruto;
            var produto_quantidade = floatVal($("#produto_quantidade").val());
            if (!produto_quantidade) produto_quantidade = 1;
            $("#qVol").val(produto_quantidade);
            var $pesoL = $("#pesoLhidden");
            var $pesoB = $("#pesoBhidden");
            $pesoL.val(pesoliquido);
            $pesoB.val(pesobruto);
            $("#pesoL").val(formataDecimal(produto_quantidade * $pesoL.val(), 3));
            $("#pesoB").val(formataDecimal(produto_quantidade * $pesoB.val(), 3));

            $("#pGNi").val(pGNi);
            $("#pGNn").val(pGNn);
            $("#pGLP").val(pGLP);
            codAnpProdAtual = prod.cprodanp;
            tipoGLPAtual = prod.tipo_glp;
            enableDisableFieldsGLP();
        };
        precoF = getPrecoProdutoCliente($("#precosProdutosPadrao"), $("#precoprodutos"), produto_id, isEmissaoPropria(), callback);
    }

    $("#produto_valor").val("R$ " + formataDecimal(precoF, 2));
}

function setPricesItems(data) {
    var precosProdutosPadrao = [];
    for (let i = 0; i < data.length; i++) {
        if (isEmissaoPropria()) {
            precosProdutosPadrao.push({
                produto_id: data[i].id,
                precovenda: parseFloat(data[i].precovenda),
                precovendaminimo: parseFloat(data[i].precovendaminimo),
                pesoliquido: data[i].pesoliquido,
                pesobruto: data[i].pesobruto,
                pgni: data[i].pgni,
                pgnn: data[i].pgnn,
                pglp: data[i].pglp,
                tipo_glp: data[i].tipo_glp,
                cprodanp: data[i].cprodanp,
            });
        } else {
            precosProdutosPadrao.push({
                produto_id: data[i].id,
                customedio: parseFloat(data[i].customedio),
                pesoliquido: data[i].pesoliquido,
                pesobruto: data[i].pesobruto,
                pgni: data[i].pgni,
                pgnn: data[i].pgnn,
                pglp: data[i].pglp,
                tipo_glp: data[i].tipo_glp,
                cprodanp: data[i].cprodanp,
            });
        }
    }
    $("#precosProdutosPadrao").val(JSON.stringify(precosProdutosPadrao));
}

function addProdutosClick(nItem = "") {
    let $pGNi = $("#pGNi");
    let $pGNn = $("#pGNn");
    let $pGLP = $("#pGLP");
    var $produto = $("#produto_id");
    var produto_id = $produto.intVal();
    var produto_valor = parseDinheiro($("#produto_valor").val(), 2);
    var produto_quantidade = floatVal($("#produto_quantidade").val());
    var qVol = parseInt($("#qVol").val());
    var pesoL = numberFormat($("#pesoL").val(), 4, "n");
    var pesoB = numberFormat($("#pesoB").val(), 4, "n");
    var pGNi = numberFormat($pGNi.val(), 4, "n");
    var pGNn = numberFormat($pGNn.val(), 4, "n");
    var pGLP = numberFormat($pGLP.val(), 4, "n");
    var sum = pGNi + pGLP + pGNn;
    if (sum !== 100 && sum !== 0) {
        bootbox.alert("A soma dos percentuais de GNi, GNn e GLP precisa ser 100 ou 0");
        return;
    }
    carregaProdutoAjax(produto_id, function (arrayProduto) {
        validateImpostoProduto(arrayProduto)
            .then(() => {
                let $operacao = $("#nfoperacao_id_2");
                let $setor = $("#setor_id");
                tblProdutos
                    .rows()
                    .eq(0)
                    .each(function (i) {
                        var row = tblProdutos.row(i);
                        var data = row.data();
                        if (typeof data === "undefined") {
                            return;
                        }
                        if (
                            parseInt(data[4]) === produto_id &&
                            $operacao.val() === data[0] &&
                            $setor.val() === data[2] &&
                            "R$ " + formataDecimal(produto_valor, 2) === data[6] &&
                            !nfeImporting
                        ) {
                            produto_quantidade += floatVal(data[7]);
                            qVol += parseInt(data[18]);
                            pesoL += floatVal(data[19]);
                            pesoB += floatVal(data[20]);
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
                    '<button class="btn btn-nw-registro btn-xs" id="btnRemoverProduto" nitem="' + nItem + '" type="button">Remover</button>', // 8
                    arrayProduto["pesoliquido"], // 9
                    arrayProduto["pesobruto"], // 10
                    arrayProduto["ncm"], // 11
                    arrayProduto["unidademedida"]["sigla"], // 12
                    arrayProduto["ean"], // 13
                    arrayProduto["nfeextipi"], // 14
                    arrayProduto["nfcest"], //15
                    arrayProduto["nfedescricaofiscal"], //16
                    arrayProduto["nfgrupofiscal_id"], //17
                    qVol, //18
                    pesoL, //19
                    pesoB, //20
                    numberFormat(pGNi, 4),
                    numberFormat(pGNn, 4),
                    numberFormat(pGLP, 4),
                ]);

                tblProdutos.draw(false);
                $("#produto_id").val("0").trigger("chosen:updated");
                $("#produto_valor").val("");
                $("#produto_quantidade").val("");
                $("#qVol").val("");
                $("#pesoLhidden").val("");
                $("#pesoL").val("");
                $("#pesoBhidden").val("");
                $("#pesoB").val("");
                $pGNi.val("0,0000").prop("disabled", true);
                $pGNn.val("0,0000").prop("disabled", true);
                $pGLP.val("0,0000").prop("disabled", true);
                $setor.val("0").trigger("chosen:updated").focus().trigger("chosen:activate");
                hideLoaderAjax();
            })
            .catch((e) => console.log(e));
    });
}

function liberarCamposFinanceiro() {
    enableDisableCondPgto();
    $("#btnVisualizarParcelas").prop("disabled", false);
    $("#vdesc").prop("readonly", false).removeAttr("tabindex");
    $("#descricaofinanceiro").prop("readonly", false).removeAttr("tabindex");
    $("#btnCcusto").prop("disabled", false);
    $("#btnPconta").prop("disabled", false);
    changeTabIndexAttr();
}

function initTable() {
    tblProdutos = $("#tblProdutos").DataTable({
        language: {
            url: urlDataTable,
        },
        processing: false,
        bPaginate: false,
        bLengthChange: false,
        bFilter: false,
        bSort: true,
        bInfo: false,
        bAutoWidth: false,
        sScrollY: "200px",
        columnDefs: [
            {
                targets: [0, 2, 4, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23],
                visible: false,
            },
        ],
    });
    tblRateios = $("#tblRateios").DataTable({
        language: {
            url: urlDataTable,
        },
        processing: false,
        bPaginate: false,
        bLengthChange: false,
        bFilter: false,
        bSort: true,
        bInfo: false,
        bAutoWidth: false,
        columnDefs: [
            {
                targets: [0, 3],
                visible: false,
            },
        ],
    });
    if (tipoNf === "E") {
        tblNfFinalidade2 = $("#tblNfFinalidade2").DataTable({
            language: {
                url: urlDataTable,
            },
            processing: false,
            bPaginate: false,
            bLengthChange: false,
            bFilter: false,
            bSort: true,
            bInfo: false,
            bAutoWidth: false,
        });
    }
}

function validateLengthChaveAcesso($obj) {
    if ($obj.val().length !== 44) {
        bootbox.alert("Atenção: a Chave de Ref. deve ter 44 caracteres.");
        $obj.val("");
        return false;
    }
    return true;
}

function tratarErros() {
    return new Promise(() => {
        erros = true;
        if ($.fn.dataTable.isDataTable("#tblProdutos") && $.fn.dataTable.isDataTable("#tblRateios")) {
            carregarProdutosErro();
            carregarRateiosErro();
            refreshSelectize();
        } else {
            treatErrors();
        }
    });
}

function refreshSelectize() {
    let select = $("#nomecliente").selectize()[0].selectize;
    let cliente_id = $("#cliente_id").val();
    let nome = $("#cliente_nome_erro").val();

    if (!isEmpty(nome) && !isEmpty(cliente_id)) {
        select.clearOptions();
        select.addOption([
            {
                nome: nome,
                rua: {
                    descricao: "",
                },
                numero: "",
                bairro: {
                    descricao: "",
                },
                cidade: {
                    descricao: "",
                },
                id: cliente_id,
            },
        ]);
        select.addItem(cliente_id);
        select.refreshOptions(true);
        select.refreshItems();
    }
}

function tratarDadosShow() {
    return new Promise((resolve) => {
        show = true;
        $(".btn-nw-registro").attr("disabled", "disabled");
        $("input[type=submit]").hide();
        desativarInputs();
        $("#xml").prop("disabled", false).removeAttr("disabled", false).prop("readonly", true).prop("tabindex", -1);
        var ids = [
            ".btn-nw-registro",
            ".btn-nw-geral",
            ".btnBuscarEndereco",
            "#btnBuscarCEP",
            "#btnAddProduto",
            "#btnCcusto",
            "#btnPconta",
            "#btnFreteCcusto",
            "#btnFretePconta",
            "#addRateio",
            "#btnRateioCcusto",
            "#btnRateioPconta",
        ];
        desativarInputsEspecificos(ids);
        var selectCliente = $("#nomecliente").selectize();
        var selectize = selectCliente[0].selectize;
        selectize.disable();
        setTimeout(function () {
            $("#btnEditar").removeAttr("disabled");
        }, 500);
        $("#btnParcelasVoltar").removeAttr("disabled");
        $("#btnParcelasFreteVoltar").removeAttr("disabled");
        resolve();
    });
}

function tratarOutrosDados() {
    buscarParcelasAjax("datahoraemissao", "vencimentoprimeiraparcela", function () {
        buscarParcelasAjax("datahoraemissao", "vencimentoprimeiraparcela", undefined, "fretevista", "freteboleto", "fretecartao", "fretecondicaopagamento_id");
    });
    trataComboDestinatarioShowEdit();
    let indie = $("#destindicadorie").val();
    if (indie) {
        putTxtIndicadorIE(indie);
    }
}

function calcularImpostoProduto(pars, callback, callbackError) {
    if (JSON.parse(pars.prod).length === 0) {
        callback([{ vnf: "R$ 0,00" }, { vnf: 0 }]);
        return;
    }
    if (show) return;
    showLoaderAjax("Aguarde", "Calculando impostos", false);
    var url = root + "/api/calcularImpostoProduto";
    var form = new FormData();
    let finalidade = $("#nfefinalidade").val();
    form.append("prod", pars.prod);
    form.append("complementar", finalidade === "2" ? "1" : "0");
    form.append("destindicadorie", $("#destindicadorie").val());
    form.append("nfefinalidade", finalidade);
    form.append("vdesc", pars.vdesc);
    form.append("cliente_id", pars.cliente_id);
    form.append("empresa_id", pars.empresa_id);
    form.append("fretemodalidade", $("#fretemodalidade").val());
    form.append("nfmodelo", $("#nfmodelo").val());
    form.append("presencacomprador", $("#presencacomprador").val());
    form.append("vprod", pars.vprod.toString());
    form.append("vfrete", pars.vfrete);
    form.append("vipi", pars.vipi);
    form.append("vseg", pars.vseg);
    form.append("voutro", pars.voutro);
    let $tipoLanc = $("#tipolancamento");
    if (tipoNf === "R") {
        form.append("tipolancamento", $tipoLanc.val());
    }
    form.append("nfmodelo", pars.nfmodelo);
    form.append("indfinal", $("#indfinal").is(":checked") ? "1" : "0");
    form.append("datahoraentradasaida", insertDataOracle($("#datahoraentradasaida").val()));
    if (typeof actualProductXml !== "undefined" && actualProductXml) {
        let originalContent = actualProductXml ? actualProductXml : [];
        form.append("originalContent", JSON.stringify(originalContent));
    } else if (typeof nfeImporting !== "undefined" && nfeImporting) {
        let originalContent = nfeImporting.det ? nfeImporting.det : [];
        if (!$.isArray(originalContent)) {
            originalContent = [originalContent];
        }
        form.append("originalContent", JSON.stringify(originalContent));
    } else {
        form.append("originalContent", "[]");
    }
    if (typeof tagCombXml === "object" && tagCombXml !== null) {
        form.append("tagCombXml", JSON.stringify(tagCombXml));
    } else {
        form.append("tagCombXml", "{}");
    }

    form.append("tiponf", isEmissaoPropria() ? "E" : "R");
    form.append("iddest", $("#iddest").val());
    ajaxGenerator(
        url,
        "POST",
        function (data) {
            if (typeof data !== "object") {
                if (typeof callbackError === "function") {
                    callbackError(data);
                } else {
                    bootbox.alert("Erro: " + data);
                }
            } else {
                $("#vbc").val(data[0].vbc);
                $("#vicms").val(data[0].vicms);
                $("#vbcst").val(data[0].vbcst);
                $("#vst").val(data[0].vst);
                $("#vipi").val(data[0].vipi);
                $("#vicmsdeson").val(data[0].vicmsdeson);
                $("#vfcp").val(data[0].vfcp);
                $("#vfcpst").val(data[0].vfcpst);
                $("#vpis").val(data[0].vpis);
                $("#vcofins").val(data[0].vcofins);
                $("#totalvfrete").val(data[0].vfrete);
                callback(data);
            }
        },
        null,
        form,
        true,
        function () {
            hideLoaderAjax();
        }
    );
}

function calcularTotais(calculaImposto, callbackT, importing) {
    if (typeof calculaImposto === "undefined") {
        calculaImposto = true;
    }

    if (!importing) {
        importing = false;
    }

    var totalvalorproduto = 0;
    var totalqtdeproduto = 0;
    var totalpesoliquidoproduto = 0;
    var totalpesobrutoproduto = 0;
    var vdesc = floatVal($("#vdesc").val(), 2);
    var prod = [];
    let callbackError;
    if (importing) {
        var valorproduto = $("#produto_valor_import").val();
        var quantidadeproduto = $("#produto_quantidade_import").val();
        var qdeCalcular = floatVal(quantidadeproduto) === 0 ? 1 : quantidadeproduto;
        totalvalorproduto += parseDinheiro(qdeCalcular, 4) * parseDinheiro(valorproduto, 2);
        totalqtdeproduto += quantidadeproduto;
        prod.push([$("#nfoperacao_import").val(), "", $("#setor_import").val(), "", $("#produtos_import").val(), "", valorproduto, quantidadeproduto, "", 0, 0]);
        setValuesComb(0, 0, 0);
        try {
            let comb = actualProductXml.prod.comb;
            if (comb.pGNi && comb.pGNn && comb.pGLP) {
                setValuesComb(comb.pGNi, comb.pGNn, comb.pGLP);
            }
        } catch (e) {
            console.log(e);
        }
        callbackError = confirmNewImposto;
    } else {
        tblProdutos
            .rows()
            .eq(0)
            .each(function (i) {
                var row = tblProdutos.row(i);
                var data = row.data();
                if (typeof data !== "undefined") {
                    var valorproduto = floatVal(data[6], 2);
                    var quantidadeproduto = floatVal(data[7], 4);
                    var pesoliquido = floatVal(data[19]);
                    var pesobruto = floatVal(data[20]);
                    var qdeCalcular = floatVal(quantidadeproduto) === 0 ? 1 : quantidadeproduto;
                    totalvalorproduto += qdeCalcular * valorproduto;
                    totalpesoliquidoproduto += pesoliquido; //quantidadeproduto *
                    totalpesobrutoproduto += pesobruto; //quantidadeproduto *
                    totalqtdeproduto += quantidadeproduto;
                    prod.push(data);
                }
            });
    }
    var cliente_id = $("#cliente_id").intVal();
    var empresa_id = $("#empresa_id").val();
    var vfrete = $("#vfrete").val();
    var vseg = $("#vseg").val();
    var voutro = $("#voutro").val();
    var vipi = $("#vipi").val();
    var nfmodelo = $("#nfmodelo").val();
    var pars = {
        prod: JSON.stringify(prod),
        vdesc: vdesc,
        cliente_id: cliente_id,
        empresa_id: empresa_id,
        vfrete: vfrete,
        vprod: totalvalorproduto,
        vseg: vseg,
        vipi: vipi,
        voutro: voutro,
        nfmodelo: nfmodelo,
    };
    let callback;
    if (importing) {
        callback = callbackT;
    } else {
        callback = function (d) {
            var dataFloat = d[1];
            var data = d[0];

            $.each(dataFloat, function (i, el) {
                dataFloat[i] = parseFloat(el);
            });
            $("#vnf").val(data.vnf);

            var vbruto = formataDecimal(dataFloat.vnf + dataFloat.vdesc - dataFloat.vfrete, 2);
            var liquido = formataDecimal(dataFloat.vnf - dataFloat.vfrete, 2);
            $("#totalqtdeprodutos").val(totalqtdeproduto);
            $("#totalpesobruto").val(formataDecimal(totalpesobrutoproduto, 3) + " Kg");
            $("#totalpesoliquido").val(formataDecimal(totalpesoliquidoproduto, 3) + " Kg");
            $("#vprod").val(data.vprod);
            $("#totalvdesc").val(data.vdesc);
            $("#liquidoParcelas").val(liquido);
            $("#vbruto").val(vbruto);
            $(".dinheiro").trigger("mask.maskMoney");
            updateRateioUnique();
            if (typeof callbackT === "function") callbackT();
        };
    }

    if (cliente_id > 0) {
        if (calculaImposto) {
            calcularImpostoProduto(
                pars,
                function (data) {
                    callback(data);
                },
                callbackError
            );
        } else {
            var vnf = floatVal($("#liquidoParcelas").val(), 2);
            callback({ vnf: vnf }, false);
        }
    } else {
        if (typeof callbackT === "function") callbackT();
    }
}

function reload(extra = "") {
    if (extra) {
        location.reload();
    } else {
        let url = location.href;
        var char = url.indexOf("?") === -1 ? "?" : "&";
        location.href = url + char + extra;
    }
}

function clearNomeCliente(changeCombo) {
    var select = $("#nomecliente").selectize()[0].selectize;
    select.clearOptions();
    $("#cliente_id").val("");
    if (changeCombo) {
        carregaDestinatarioAjax();
    }
}

function setHasChaveAcessoEntrada() {
    hasChaveAcesso = tipoNf === "E" ? false : !!$("#chaveacesso").val();
}

function allowedToUsePGLP(cAnp) {
    var isCodANP = $.inArray(cAnp, codAnpDB) > -1;
    if (!isEmissaoPropria()) {
        return isCodANP;
    } else {
        var objOperacao = getOperacaoAtual($("#nfoperacao_id_2").val());
        if (!objOperacao) {
            return false;
        }
        return isCodANP && $.inArray(objOperacao.cfop, cfopGLP) > -1;
    }
}

function enableDisableFieldsGLP() {
    if (parseInt(tipoGLPAtual) && allowedToUsePGLP(codAnpProdAtual)) {
        $("#pGNi").prop("disabled", false);
        $("#pGNn").prop("disabled", false);
        $("#pGLP").prop("disabled", false);
    } else {
        $("#pGNi").prop("disabled", true);
        $("#pGNn").prop("disabled", true);
        $("#pGLP").prop("disabled", true);
    }
}

function bootboxConfirm(title, message, callback, btnConfirm = "Sim", btnCancel = "Não") {
    bootbox.confirm({
        title: title,
        message: message,
        buttons: {
            confirm: {
                label: btnConfirm,
                className: "btn-nw-registro",
            },
            cancel: {
                label: btnCancel,
                className: "btn-nw-geral",
            },
        },
        callback: function (result) {
            callback(result);
        },
    });
}

function isCfopVenda() {
    let objOperacao = getOperacaoAtual($("#nfoperacao_id_2").val());
    return $.inArray(objOperacao.cfop, JSON.parse($("#cfop_venda").val())) > -1;
}

function checkIfIsDup(cond) {
    if (!cond) {
        hideTipoPgtoDup();
        return;
    }

    if (condicoesPgtoDB.nfc_tpag === "14") {
        $("#div_condicaopagamento").removeClass("col-sm-8").addClass("col-sm-5");
        $("#div_tpag").show();
    } else {
        hideTipoPgtoDup();
    }
}

function hideTipoPgtoDup() {
    $("#div_condicaopagamento").removeClass("col-sm-5").addClass("col-sm-8");
    $("#div_tpag").hide();
}
