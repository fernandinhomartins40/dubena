var urlCancelarNFAjax = root + "/nfemitida/evento/cancelar?id=:id&just=:xJust";
var urlCartaCorrecaoNFAjax = root + "/nfemitida/evento/cce?id=:id&correcao=:xCorrecao&create=1";
var urlEnviarEmailNFAjax = root + "/nfemitida/enviarEmailNF/:id";

var tblNfFinalidade2;
var editingRowComp;

var isComplementar = false;
var ajaxSubmitComplementar = false;

var clipboard = new Clipboard(".xmlCopyClipboard");
var clipboardChaveAcesso = new Clipboard(".chaveacessoCopyClipboard");
var clipboardChaveAcessoRef = new Clipboard(".chaveacessorefCopyClipboard");
clipboardsEvs();

$("#btnProcessCCE").on("click", processCCE);

$("#btnTransmitirNF").on("click", function () {
    showLoaderAjax("", "Aguardando retorno do SEFAZ", false, function () {
        setTimeout(function () {
            transmitirNF();
        }, 500);
    });
});

$("#btnCancelarNF").on("click", function () {
    cancelarNF();
});

$("#btnAtualizarStatus").on("click", function () {
    atualizarStatusNF();
});

$("#btnEnviarEmail").on("click", function () {
    enviarEmailNF();
});

$("#btnCartaCorrecao").on("click", function () {
    cartaCorrecao();
});

$("#nfmodelo").on("change", treatChaveRef);

$(document).ready(function () {
    changeAmbienteNf();

    $("#duplicateNf, #duplicateNf2").on("click", function () {
        duplicateNFe();
    });
});

//essa função é chamada somente quando é edição/criaçào de NF
function onDocReady(callback) {
    operacoesDB = JSON.parse($("#objOperacoes").val());
    let $modelo = $("#nfmodelo");
    if (editOrShow) {
        $modelo.prop("disabled", true).trigger("chosen:updated");
    }

    $modelo.on("change", function () {
        changeAmbienteNf();
        enableDisableCondPgto();
        trataCpfCnpjNfc();
        changeIndFinal();
        changeIdDest();
    });

    var $pres = $("#presencacomprador");
    $pres.on("change", function () {
        treatChaveRef();
    });

    treatChaveRef();

    $("#btnSalvaProdComp").on("click", function () {
        var value = floatVal($("#valor_complementar").val());
        var qde = parseInt($("#quantidade_complementar").val());
        var row = tblNfFinalidade2.row(editingRowComp);
        var data = row.data();
        data[2] = "R$ " + formataDecimal(value, 2);
        data[3] = qde;
        row.data(data).draw();
        $("#modalUpdateProdComp").modal("hide");
    });

    $("#fmModalFinalidade2Ajax").on("submit", function (e) {
        e.preventDefault();
        if (ajaxSubmitComplementar) return;
        ajaxSubmitComplementar = true;

        var $self = $(this);
        var nfnumero = $("#numnfe_complementar").val();
        var nfserie = $("#nfserie_complementar").val();
        if (isEmpty(nfnumero) || isEmpty(nfserie)) {
            bootbox.alert("Por favor, informe o número e a série da NFe.");
            isComplementar = false;
            ajaxSubmitComplementar = false;
            return;
        }
        var data = [];
        tblNfFinalidade2.rows().every(function () {
            var d = this.data();
            data.push([d[0], d[1], d[2], d[3]]);
        });
        $("#nfcomplementar").val(1);
        $("#produtos_complementar").val(JSON.stringify(data));
        if (data.length === 0) {
            bootbox.alert("Nenhum produto encontrado.");
            isComplementar = false;
            ajaxSubmitComplementar = false;
            return;
        }
        var formData = new FormData($self[0]);
        formData.append("complementar", "1");
        ajaxGenerator(
            $self.attr("action"),
            "POST",
            function (data) {
                if (typeof data === "string") {
                    var response = data.substr(0, 3);
                    if (response !== "OK|") {
                        bootbox.alert("Erro: " + data);
                    } else {
                        var id = data.substr(3, data.length);
                        var url = root + "/nfemitida/" + id + "?index=";
                        url += getParametro("index") ? getParametro("index") : root + "/nfemitida";
                        showDialogRedirect(url);
                    }
                } else {
                    bootbox.alert("Erro ao gerar a NFe");
                }
            },
            null,
            formData,
            false,
            function () {
                isComplementar = false;
                setTimeout(function () {
                    ajaxSubmitComplementar = false;
                }, 100);
            }
        );
    });

    $("#modalFinalidade2")
        .on("hidden.bs.modal", function () {
            $("#nfcomplementar").val(0);
            setTimeout(function () {
                if (!isComplementar) $("#nfefinalidade").val("1").trigger("chosen:updated");
            }, 250);
        })
        .on("shown.bs.modal", function () {
            setTimeout(function () {
                $("#numnfe_complementar").focus();
            }, 500);
        });

    $("#btnGetNfFinalidade2").on("click", function () {
        tblNfFinalidade2.clear().draw();
        $("#chavenfe_complementar").val("");
        var nfnumero = $("#numnfe_complementar").val();
        var nfserie = $("#nfserie_complementar").val();
        if (isEmpty(nfnumero) || isEmpty(nfserie)) {
            bootbox.alert("Por favor, informe o número e a série da NFe.");
            return;
        }
        var url = root + "/api/getNfBySerieNum?nfnumero=" + nfnumero + "&nfserie=" + nfserie;

        ajaxGenerator(url, "GET", function (data) {
            if (typeof data !== "object") {
                bootbox.alert("Erro: " + data);
                return;
            }
            $("#chavenfe_complementar").val(data.chaveAcesso);
            var items = data.items;
            for (var i = 0; i < items.length; i++) {
                tblNfFinalidade2.row.add([items[i].id, items[i].xprod, "R$ 0,00", 0, getButtonsNfComplementar()]);
            }

            tblNfFinalidade2.draw();
        });
    });

    $("#nfefinalidade").change(function () {
        trataFinalidade();
    });

    $("#chaveacessoref").change(function () {
        var $self = $(this);
        if (!$self.val().isEmpty()) validateLengthChaveAcesso($self);
    });

    $("#btnProcessModalFinalidade2").on("click", function () {
        isComplementar = true;
    });

    trataFinalidade();
    trataCpfCnpjNfc();

    $("#tblNfFinalidade2").on("click", "button", function () {
        var $self = $(this);
        var trElem = $self.closest("tr");
        var prod = $($(trElem).children("td")[1]).text();
        var value = $($(trElem).children("td")[2]).text();
        var qde = $($(trElem).children("td")[3]).text();
        if (!isEmpty(prod) && $self.context.id === "btnRemoverProdutoComplementar") tblNfFinalidade2.row($self.parents("tr")).remove().draw();
        else {
            $("#modalUpdateProdComp").modal("show");
            $("#divEditProdComp").text("Editando produto " + prod);
            $("#valor_complementar").val(value);
            $("#quantidade_complementar").val(qde);
        }
        editingRowComp = $self.parents("tr");
    });
    if (typeof callback === "function") callback();
}

function transmitirNF() {
    botoesAcaoBloquear();
    var id = $("#id").val();
    if (id > 0) {
        var nfsituacao_id = $("#nfsituacao_id").intVal();
        var url = root + "/nfemitida/evento/transmitir?id=:id";
        url = url.replace(":id", id);
        if ((nfsituacao_id < 100 && nfsituacao_id !== 3) || nfsituacao_id === 136) {
            ajaxGenerator(
                url,
                "GET",
                function (data) {
                    if (typeof data === "string" && data.split(":")[0] === "Sucesso") {
                        atualizarStatusNF();
                        setTimeout(function () {
                            reload("await=1");
                        }, 2000);
                    } else {
                        bootbox.alert(data, function () {
                            setTimeout(function () {
                                reload("await=1");
                            }, 2000);
                        });
                    }
                },
                null,
                null,
                false,
                function () {
                    hideLoaderAjax();
                    botoesAcaoDesbloquear();
                }
            );
        } else {
            botoesAcaoDesbloquear();
            hideLoaderAjax();
            bootbox.alert("NF já foi transmitida.");
        }
    } else {
        botoesAcaoDesbloquear();
        hideLoaderAjax();
        bootbox.alert("NF precisa ser salva para ser transmitida.");
    }
}

function botoesAcaoBloquear() {
    $("#btnTransmitirNF").prop("disabled", true);
    $("#btnTransmitirDPEC").prop("disabled", true);
    $("#btnCancelarNF").prop("disabled", true);
    $("#btnAtualizarStatus").prop("disabled", true);
    $("#btnEnviarEmail").prop("disabled", true);
    $("#btnCartaCorrecao").prop("disabled", true);
}

function botoesAcaoDesbloquear() {
    $("#btnTransmitirNF").prop("disabled", false);
    $("#btnTransmitirDPEC").prop("disabled", false);
    $("#btnCancelarNF").prop("disabled", false);
    $("#btnAtualizarStatus").prop("disabled", false);
    $("#btnEnviarEmail").prop("disabled", false);
    $("#btnCartaCorrecao").prop("disabled", false);
}

function atualizarStatusNF() {
    botoesAcaoBloquear();
    notClearIdFieldOnHideModal = 1;
    var id = $("#id").val();
    if (id > 0) {
        var url = root + "/nfemitida/evento/consultar?id=:id";
        url = url.replace(":id", id);
        window.open(url, "_blank");
    } else {
        bootbox.alert("NF precisa ser salva para Consulta Status/Imprimir.");
    }
    botoesAcaoDesbloquear();
}

function trataFinalidade() {
    let $finalidade = $("#nfefinalidade");
    var nfefinalidade = $finalidade.intVal();
    isComplementar = false;
    if (nfefinalidade !== 4) {
        if (nfefinalidade === 2 && !editOrShow) {
            tblNfFinalidade2.clear().draw();
            $("#fmModalFinalidade2Ajax")[0].reset();
            $("#modalFinalidade2").modal("show");
        }
    }
    treatChaveRef();
    if (editOrShow && nfefinalidade === 2) $finalidade.prop("disabled", true).trigger("chosen:updated");
}

function treatChaveRef() {
    let readonly = !needChaveRef() || show;
    let $ref = $("#chaveacessoref");
    $ref.prop("readonly", readonly);
    if (readonly && !editOrShow) $ref.val("");
    changeTabIndexAttr();
}

function needChaveRef() {
    let opDb = JSON.parse($("#objOperacoes").val());
    let operacao_id = $("#nfoperacao_id").val();
    let nfmodelo = $("#nfmodelo").intVal();
    let fin_val = $("#nfefinalidade").val();
    let finalidade = fin_val === "4" || fin_val === "2";
    let presenca = $("#presencacomprador").val() === "5";
    let operacao = opDb
        .filter(function (item) {
            return item.id == operacao_id;
        })
        .first();

    return finalidade || presenca || (["5929"].includes(operacao.cfop) && nfmodelo == 55);
}

function processCCE() {
    var id = $("#id").val();
    var nfnumero = $("#nfnumero").intVal();
    var nfmodelo = $("#nfmodelo").intVal();
    var xCorrecao = $("#xCorrecao").val();
    var nfsituacao_id = $("#nfsituacao_id").intVal();
    var authorized = nfsituacao_id === 100 || nfsituacao_id === 135;
    if (nfmodelo && id && authorized && nfnumero && xCorrecao) {
        if (xCorrecao.length >= 15 && xCorrecao.length <= 1000) {
            var url = urlCartaCorrecaoNFAjax;
            url = url.replace(":id", id);
            url = url.replace(":xCorrecao", xCorrecao);
            var date = new Date();
            var hash = new IDGenerator(10);
            var timeHash = date.getDay() + date.getMonth() + date.getFullYear() + date.getMilliseconds() + "";
            url += "&hash=" + hash.generate() + timeHash;
            window.open(url, "_blank");
            $("#myModalCCE").modal("hide");
            return true;
        } else {
            bootbox.alert("Correção deve ter entre 15 a 1000 caracteres.");
            return false;
        }
    } else {
        var msg;
        if (authorized) {
            msg = "Para correção a nota precisa estar salva e com status de autorizada na receita.";
        } else {
            msg = "Para Carta de Correção é preciso informar o campo de Correção.";
        }
        bootbox.alert(msg);
        return false;
    }
}

function cancelarNF() {
    botoesAcaoBloquear();
    var id = $("#id").val();
    if (id > 0) {
        var nfsituacao_id = $("#nfsituacao_id").intVal();
        var status = $("#statusevento").val().toLowerCase();
        var isCanc = (nfsituacao_id === 101 || nfsituacao_id === 135) && status.indexOf("cancelado") !== -1;
        if (isCanc) {
            bootbox.alert("NF já esta com status de cancelada.");
            botoesAcaoDesbloquear();
        } else {
            promptCancela(id);
        }
    } else {
        bootbox.alert("NF precisa estar salva para ser cancelada.");
        botoesAcaoDesbloquear();
    }
}

function promptCancela(id, value = "") {
    var callback = function (result) {
        if (result !== "" && result === null) {
            bootbox.alert("Operação cancelada.");
            botoesAcaoDesbloquear();
            return;
        }
        if (result.length < 15 || result.length > 255) {
            botoesAcaoDesbloquear();
            bootbox.alert("A justificativa deve ter pelo menos 15 digitos e no máximo 255!", function () {
                promptCancela(id, result);
            });
        } else {
            var url = urlCancelarNFAjax;
            url = url.replace(":id", id);
            url = url.replace(":xJust", result);
            ajaxGenerator(
                url,
                "GET",
                function (data) {
                    bootbox.alert(" " + data, function () {
                        reload("await=1");
                    });
                },
                null,
                false,
                null,
                function () {
                    botoesAcaoDesbloquear();
                }
            );
        }
    };
    bootbox.prompt({
        title: "Qual a justificativa do Cancelamento?",
        value: value,
        callback: callback,
        buttons: {
            confirm: {
                label: "Continuar",
                className: "btn-nw-registro",
            },
            cancel: {
                label: "Cancelar",
                className: "btn-nw-geral",
            },
        },
    });
}

function enviarEmailNF() {
    var id = $("#id").val();
    if (id > 0) {
        var url = urlEnviarEmailNFAjax;
        url = url.replace(":id", id);
        url += "?empresa_id_config=" + $("#empresa_id").val();
        ajaxGenerator(url, "GET", function (data) {
            if (typeof data === "string" && data.substr(0, 3) === "OK|") {
                bootbox.alert(" " + data.substr(3, data.length));
            } else {
                bootbox.alert(" " + data);
            }
        });
        return false;
    }
}

function changeAmbienteNf() {
    let mod = $("#nfmodelo").val();
    var ambiente = $("#ambiente" + mod).val();
    $("#nftipoambiente").val(ambiente).trigger("chosen:updated");
    if (mod === "55") {
        $("#info-ambiente55").show();
        $("#info-ambiente65").hide();
    } else {
        $("#info-ambiente65").show();
        $("#info-ambiente55").hide();
    }
}

function trataCpfCnpjNfc() {
    var disable = $("#nfmodelo").val() === "55";
    $("#destcnpj, #destcpf").prop("readonly", disable);
}

function getButtonsNfComplementar() {
    return '<button class="btn btn-nw-geral btn-xs" id="btnEditProdutoComplementar" type="button">Editar</button>';
}

function cartaCorrecao() {
    var nfmodelo = $("#nfmodelo").val();
    $("#xCorrecao").val("");
    $("#nfmodelocomplementar").val(nfmodelo);
    $("#myModalCCE").modal("show");

    $("#btnCloseCCE, #btnProcessCCE").prop("disabled", false);
}

function clipboardsEvs() {
    clipboard.on("success", function (e) {
        tooltipChange("Copiado", true, ".xmlCopyClipboard");
        e.clearSelection();
    });

    clipboard.on("error", function (e) {
        console.error("Action:", e.action);
        console.error("Trigger:", e.trigger);
        tooltipChange("Impossível Copiar", true, ".xmlCopyClipboard");
    });

    $(".xmlCopyClipboard").on("mouseout", function () {
        tooltipChange("Copiar XML", false, ".xmlCopyClipboard");
    });

    clipboardChaveAcesso.on("success", function (e) {
        tooltipChange("Copiado", true, ".chaveacessoCopyClipboard");
        e.clearSelection();
    });

    clipboardChaveAcesso.on("error", function (e) {
        console.error("Action:", e.action);
        console.error("Trigger:", e.trigger);
        tooltipChange("Impossível Copiar", false, ".chaveacessoCopyClipboard");
    });

    $(".chaveacessoCopyClipboard").on("mouseout", function () {
        tooltipChange("Copiar Chave", true, ".chaveacessoCopyClipboard");
    });

    clipboardChaveAcessoRef.on("success", function (e) {
        tooltipChange("Copiado", true, ".chaveacessorefCopyClipboard");
        e.clearSelection();
    });

    clipboardChaveAcessoRef.on("error", function (e) {
        console.error("Action:", e.action);
        console.error("Trigger:", e.trigger);
        tooltipChange("Impossível Copiar", true, ".chaveacessorefCopyClipboard");
    });

    $(".chaveacessorefCopyClipboard").on("mouseout", function () {
        tooltipChange("Copiar Chave Ref", true, ".chaveacessorefCopyClipboard");
    });

    function tooltipChange(text, show = true, classChange) {
        var $t = $(classChange);
        $t.attr("title", text).tooltip("fixTitle");
        if (show) $t.tooltip("show");
    }
}

function duplicateNFe() {
    if (_nfe_id) {
        bootboxConfirm(
            "Deseja mesmo duplicar esta NF-e/NFC-e? ",
            "Você será redirecionado para a tela de Emissão de Notas Fiscais com " + "um formulário preenchido com base na NF atual",
            function (result) {
                if (!result) {
                    return;
                }
                let url = root + "/";
                url += (tipoNf === "E" ? "nfemitida" : "nfrecebida") + "/" + _nfe_id.toString() + "/edit?act=dup";
                window.open(url);
            }
        );
    }
}

function expandOpeChange() {
    treatChaveRef();
}
