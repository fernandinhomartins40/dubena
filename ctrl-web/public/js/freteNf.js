$("#btnFreteVisualizarParcelas").click(function () {

    calculateParcelas(true).then(() => {
        calcularParcelas("vfrete", "datahoraemissao", "frete_parcelas_financeiro");
    }).catch((error) => {
        bootbox.alert("Erro ao visualizar parcelas do frete: " + (typeof error === "string" ? error : "Erro desconhecido"));
        console.log(error);
    });
});

function treatFrete() {
    $("#fretemodalidade").change(function () {
        var fretemodalidade = $("#fretemodalidade").val();
        if (fretemodalidade === '9') {
            bloquearCamposFrete();
            $("#fretecliente_id").val("").trigger('change').trigger('chosen:updated');
            $("#fretecondicaopagamento_id, #freteplacauf, #freteplaca").val("");
            $("#vfrete").val("R$ 0,00");
        } else if ($.inArray(fretemodalidade, ["2", "3", "4"]) > -1) {
            bloquearCamposFrete();
            liberarCamposFreteTerceiros();
        } else {
            liberarCamposFrete();
        }
        if ((!editOrShow && !erros) || fretemodalidade === "9") {
            changeVFrete();
        }
    });
    $("#vfrete").on("blur", function () {
        changeVFrete();
        let $formaPgto = $("#formapagamento");
        setTimeout(function() {
            if (!$formaPgto.prop("disabled")) {
                $formaPgto.focus().trigger("chosen:activate");
            } else {
                $("#btnCadastro").focus();
            }
        }, 150);
    });

    $("#formapagamento").on('change', function () {
        changeFreteFinanceiro($(this).val() === "0");
    });

    $("#fretemodalidade, #formapagamento").trigger('change');

    // $("#btnFreteCalcularParcelas").click(function () {
    //     if (floatVal($("#vfrete").val()) > 0) {
    //         tblfreteparc.clear();
    //         calcularParcelas("vfrete", "datahoraemissao", undefined, "fretevista", "freteboleto", "fretecartao", "tblfreteparc", "fretecondicaoparcelas", "fretecondicao");
    //         setarParcelasFinanceiro("frete_parcelas_financeiro", "tblfreteparc");
    //     } else {
    //         bootbox.alert("Total Frete não informado!");
    //     }
    // });

    $("#fretecliente_id").change(function () {
        if ($("#fretecliente_id").val() > 0)
            carregaFreteAjax();
        else
            $("#freterazaosocial, #freteenderecocompl, #fretecidadenome, #fretuf, #fretecpf, #fretecnpj, #fretie").val("");
    });

    $("#fretecondicaopagamento_id").change(function () {
        if ($(this).intVal() > 0) {
            showLoaderAjax();
            buscarParcelasAjax("datahoraemissao", "vencimentoprimeiraparcela", undefined,
                "fretevista", "freteboleto", "fretecartao", "fretecondicaopagamento_id",
                "fretecondicaoparcelas", "fretecondicao").then(() => {
                    hideLoaderAjax();
            }).catch((response) => {
                    bootbox.alert("Erro ao buscar parcelas: " + response.responseText.substr(0, 100));
            });
        }
    });
}

function fillFieldsFrete(objCliente) {
    if (typeof objCliente.nome === "undefined") {
        bootbox.alert('Erro ao carregar transportadora');
        return;
    }
    $("#freterazaosocial").val(objCliente.nome);
    $("#fretie").val(objCliente.inscricao_estadual);
    $("#fretecnpj").val(objCliente.cnpj);
    $("#fretecpf").val(objCliente.cpf);
    $("#fretuf").val(objCliente.uf);
    if (typeof objCliente.cidade !== "undefined") {
        $("#fretecidadecodigoibge").val(objCliente.cidade.codigoibge);
        $("#fretecidadenome").val(objCliente.cidade.descricao);
        $("#freteenderecocompl").val(objCliente.rua.descricao + ', ' + objCliente.numero + ', ' + objCliente.bairro.descricao);
    } else {
        $("#fretecidadecodigoibge").val(objCliente.codigoibge);
        $("#fretecidadenome").val(objCliente.cidadedesc);
        $("#freteenderecocompl").val(objCliente.ruadesc + ', ' + objCliente.numero + ', ' + objCliente.bairrodesc);
    }
}

function desabilitaCamposFrete() {
    var ids = "#freterazaosocial, #fretie, #fretecnpj, #fretecpf, #fretuf, #fretecidadenome, #freteenderecocompl";
    $(ids).prop('readonly', true).attr('tabindex', '-1');
    $('#formapagamento').prop('disabled', true).trigger('chosen:updated');
    changeTabIndexAttr();
}

function liberarCamposFrete() {
    $("#fretecliente_id").prop('disabled', false).trigger('chosen:updated');
    $("#freteplacauf").prop('readonly', false).removeAttr('tabindex');
    $("#freteplaca").prop('readonly', false).removeAttr('tabindex');
    $("#vfrete").prop('readonly', false).removeAttr('tabindex');
    changeTabIndexAttr();
}

function bloquearCamposFrete() {
    $("#fretecliente_id").prop('disabled', true).trigger('chosen:updated');
    $("#freteplacauf").prop('readonly', true).prop('tabindex', -1);
    $("#freteplaca").prop('readonly', true).prop('tabindex', -1);
    $("#vfrete").prop('readonly', true).prop('tabindex', -1);
    changeTabIndexAttr();
}

function changeFreteFinanceiro(isNothing) {
    let $cond = $("#fretecondicaopagamento_id");
    $("#btnFreteVisualizarParcelas").prop('disabled', isNothing);
    $("#btnFreteCcusto").prop('disabled', isNothing);
    $("#btnFretePconta").prop('disabled', isNothing);
    if (isNothing) {
        $("#fretecentrocusto_descricao").val("");
        $("#fretecentrocusto_id").val("");
        $cond.val("");
    }
    $cond.prop('disabled', isNothing).trigger('chosen:updated');
    $("#freteplanoconta_descricao").val("");
    $("#freteplanoconta_id").val("");
    changeTabIndexAttr();
}

function liberarCamposFreteTerceiros() {
    $("#fretecliente_id").prop('disabled', false).trigger('chosen:updated');
    $("#freteplacauf").prop('readonly', false);
    $("#freteplaca").prop('readonly', false);
    $("#vfrete").prop('readonly', false);
    changeTabIndexAttr();
}

function carregaFreteAjax() {
    var url = urlCarregaDestinatarioAjax;
    var fretecliente_id = $("#fretecliente_id").val();
    url = url.replace(':idcliente', fretecliente_id);
    $("#freterazaosocial").empty();
    getClienteAjax(url, 1);
}

function abrirPlanoContaPorModalidadeFrete(id, descricao, treeid) {
    var formapagamento = parseInt($("#formapagamento").val());
    if (formapagamento) {
        if (formapagamento === 1) {
            abrirPlanoConta('jstreepcD' + treeid, id, descricao);
        } else if (formapagamento === 2) {
            abrirPlanoConta('jstreepcR' + treeid, id, descricao);
        }
    } else {
        bootbox.alert("O campo Gera Financeiro deve estar preenchido com \"Pagar\" ou \"Receber\"");
    }
}

function treatFormaPag(fretemodalidade) {
    var $pgto = $("#formapagamento");
    var vfrete = $("#vfrete").moneyToFloat();
    var newValue = "2";

    if ($.inArray(fretemodalidade, ["2", "3", "4", "9"]) !== -1 || !vfrete) {
        newValue = "0";
    } else if (fretemodalidade === "0") {
        newValue = "1";
    }
    $pgto.prop('disabled', fretemodalidade === "9" || !vfrete);
    //quando está carregando a tela faz a alteração para o que veio da controller
    if ((typeof loadEditOrShow !== "undefined" && loadEditOrShow) || (typeof erros !== "undefined" && erros)) {
        newValue = $pgto.val();
    }
    $pgto.val(newValue).trigger('chosen:updated').trigger('change');
}

function changeVFrete(disabledByOperacao = false, fretemodalidade = null) {
    if (fretemodalidade === null) {
        fretemodalidade = $("#fretemodalidade").val();
    }
    treatFormaPag(fretemodalidade);
    var $pag = $("#formapagamento");

    //pedido pode ou não gerar financeiro dependendo da operaçao da config
    if (disabledByOperacao) {
        $pag.val(0).prop("disabled", true).trigger('change');
    }
    $("select").trigger('chosen:updated');
}