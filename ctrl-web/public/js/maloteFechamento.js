const urlAjax = root + "/malote.pedidos";
let condicoes = {};
let condicoesValeGas = [];
tblCodValeGas = undefined;

$(document).ready(function () {
    condicoes = JSON.parse($("#condicoes").val());
    condicoesValeGas = JSON.parse($("#condicoes-valegas").val());
    tblParcelas = undefined;

    tblPedidos = new Tabulator("#tbl_pedidos", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: "500px",
        layouy: "fitDataFill",
        ajaxContentType: "json",
        ajaxParams: function () {
            let inicio = $("#datainicio").val();
            let fim = $("#datafim").val();
            let setor_id = $("#setor_id").val();
            let colaborador_id = $("#colaborador_id").val();

            let returnObj = { inicio, fim };

            if (setor_id) returnObj["setor_id"] = setor_id;

            if (colaborador_id) returnObj["colaborador_id"] = colaborador_id;

            return returnObj;
        },
        ajaxRequesting: function () {
            let inicio = $("#datainicio").val();
            let fim = $("#datafim").val();

            if (!inicio) {
                bootbox.alert("Por favor, informe a Data de Início.");
                return false;
            }

            if (!fim) {
                bootbox.alert("Por favor, informe a Data de Fim.");
                return false;
            }

            return true;
        },
        ajaxResponse: processResponse,
        editTriggerEvent: "dblclick",
        columns: [
            { title: "Cód", field: "id" },
            { title: "Cliente", field: "cliente" },
            { title: "Endereço", field: "endereco" },
            {
                title: "Valor",
                field: "valorvenda",
                formatter: "money",
                formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "R$ ",
                    negativeSign: true,
                    precision: 2,
                },
            },
            { title: "Status", field: "status" },
            {
                title: "Pagamento",
                field: "condicao_id",
                width: 180,
                formatter: function (cell) {
                    return condicoes[cell.getValue()] || "";
                },
                editor: "list",
                editorParams: {
                    values: condicoes,
                },
            },
            {
                width: 80,
                title: "CV",
                field: "cartaoautorizacao",
                editor: "input",
                editorParams: {
                    search: true,
                },
                validator: "integer",
            },
            {
                title: "Cancelar?",
                field: "cancelar",
                formatter: (cell) => {
                    const checked = cell.getValue() ? "checked" : "";
                    return `<input type="checkbox" ${checked}>`;
                },
                cellClick: (_e, cell) => {
                    const currentValue = cell.getValue();
                    cell.setValue(!currentValue);
                    cell.getRow().reformat();
                },
            },
            {
                title: "Operações",
                field: "actions",
                formatter: createButton,
            },
        ],
        rowFormatter: function (row) {
            const data = row.getData();

            row.getElement().classList.remove("cancelado");

            if (data.cancelar) {
                row.getElement().classList.add("cancelado");
            }
        },
    });

    tblCodValeGas = $("#tblCodValeGas").DataTable({
        language: { url: urlDataTable },
        processing: false,
        bPaginate: false,
        bLengthChange: false,
        bFilter: false,
        bSort: true,
        bInfo: false,
        bAutoWidth: false,
    });

    tblPedidos.on("cellEdited", function (cell) {
        let field = cell.getColumn().getField();

        if (field == "condicao_id") {
            let validated = parcValidated(cell);

            if (validated) dispatchOrderUpdate(cell);
            else cell.restoreOldValue();
        }
    });

    $("#setor_id").trigger("change");
});

$("#btnConsultaPedidos").click(function () {
    $("#condicoes-container").html("");
    $("#btnSubmit").hide();

    tblPedidos.setData(urlAjax);
});

$("#setor_id").change(function () {
    const setor_id = this.value;
    let url = root + "/setorcolaboradores/buscacolaboradorsetor/";

    if (!setor_id) return;

    $("#btnSubmit").prop("disabled", true);
    $("#btnConsultaPedidos").prop("disabled", true);

    ajaxGenerator(
        url + setor_id,
        "GET",
        (response) => fillOptions(response),
        () => {
            bootbox.alert("Erro ao buscar dados de Colaboradores do Setor.");
        },
        null,
        true
    );
});

$("#modalValidaGasBolso").on("shown.bs.modal", function () {
    tblCodValeGas.clear().draw();
    $("#cod_gasbolso").val("").focus();
});

$("#tblCodValeGas").on("click", "#btnRemoverValeGas", function () {
    let parent = $(this).parents("tr");
    let tableRow = tblCodValeGas.row(parent);
    let data = tableRow.getData();

    $(`#valegasproduto_id option[value='${data[2]}'`).prop("disabled", false);

    tableRow.remove().draw();
});

$("#btnAddValeGas").click(function () {
    addValeGas();
});

$("#btnSubmit").click(function () {
    if (!validated()) return;

    $("#popup_fecharmalote").modal("show");
});

$("#btnFecharMalote").click(function () {
    let setor_id = $("#setor_id").val();
    let setor = $("#setor_id option:selected").text();
    let colaborador_id = $("#colaborador_id").val();
    let colaborador = $("#colaborador_id option:selected").text();
    let message = "Deseja fechar o Malote ";

    if (setor_id) {
        message += "do Setor: " + setor + " ";
    }

    if (colaborador_id) {
        message += "e Colaborador: " + colaborador + " ";
    }

    message += "no dia selecionado?";

    bootbox.confirm({
        title: "Fechamento de Malote",
        message: message,
        buttons: {
            cancel: {
                label: "Não",
                className: "btn-nw-geral pull-center",
            },
            confirm: {
                label: "Sim",
                className: "btn-nw-registro pull-center",
            },
        },
        callback: (result) => {
            if (!result) return;

            showLoaderAjax("Fechando Malote", " Esta ação pode demorar, por favor aguarde!", false, () => {
                $("#popup_fecharmalote").modal("hide");
                setTimeout(() => {
                    fecharMalote();
                }, 150);
            });
        },
    });
});

$("#btnValidaGasBolso").click(function () {
    let pedido_id = $("#valegaspedido_id").val();
    let pedRow = null;
    let pedData = null;
    let rows = tblPedidos.getRows();
    for (const row of rows) {
        let data = row.getData();
        if (data.id == pedido_id) {
            pedRow = row;
            pedData = data;
            break;
        }
    }

    if (pedData == null) {
        bootbox.alert("Algo de errado ocorreu.");
        console.error("pedido não encontrado", pedido_id, rows);
        return;
    }

    let total = 0;
    let vales = [];
    tblCodValeGas.rows().every(function () {
        let row = this.data();
        let valor = parseDinheiro(row[1]);
        total += valor;
        vales.push(row);
    });

    const $newCond = $(`#${pedData.condicao_id}-value`);
    let newCondTotal = parseFloat($newCond.attr("data-rawvalue"));
    let pedVal = parseFloat(pedData.valorvenda);
    let newValue = newCondTotal - pedVal + total;
    $newCond.attr("data-rawvalue", newValue);
    $newCond.html("R$ " + formataDecimal(newValue));

    pedData.valegas = JSON.stringify(vales);
    pedData.valorvenda = total;

    pedRow.update(pedData);

    $("#modalValidaGasBolso").modal("hide");
    $("#valegaspedido_id").val("");
});

$("#popup_parcelas").on("shown.bs.modal", function () {
    const url = $("#url_parcelas").val();

    if (!tblParcelas || !url) {
        console.warn("tblParcelas not found");
        return;
    }

    tblParcelas.setData(url);
});

$("#btnReparcelar").click(function () {
    let rows = tblParcelas.getData();

    let parcelas = [];
    for (const row of rows) {
        if (row.marcar) {
            parcelas.push(row);
        }
    }

    if (parcelas.length <= 0) {
        bootbox.alert("Por favor, selecione as parcelas a serem Reparceladas/Agrupadas.");
        return;
    }

    $("#parcelas_financeiro").val(JSON.stringify(parcelas));
    $("#fmAbrirFinanceiro").submit();
    $("#popup_financeiro").modal("show");
    $("#popup_parcelas").modal("hide");
});

function fillOptions(data) {
    const $sel = $("#colaborador_id");
    if (data.length <= 0) {
        $("#btnSubmit").prop("disabled", false);
        $("#btnConsultaPedidos").prop("disabled", false);
        bootbox.alert("Nenhum Colaborador encontrado para o Setor.");
        $sel.empty().trigger("chosen:updated");
        return;
    }

    const options = [];
    for (let i = 0; i < data.length; i++) {
        const colab = data[i];
        options.push(new Option(colab.nome, colab.id));
    }

    $sel.empty().trigger("chosen:updated");
    $sel.append(options).trigger("chosen:updated");

    $("#btnSubmit").prop("disabled", false);
    $("#btnConsultaPedidos").prop("disabled", false);
}

function processResponse(_url, _params, response) {
    fillCounting(response.condicoes);

    return response.pedidos;
}

function fillCounting(condicoes) {
    if (Object.keys(condicoes).length <= 0) return;

    let html = "";
    let count = 0;
    for (const id in condicoes) {
        const item = condicoes[id];
        let value = "R$ " + formataDecimal(item.valor);

        if (item.id == -1) {
            value = item.valor;
        }

        if (count == 0) {
            html += "<div class='row'>";
        }

        let colSize = 1;

        if (String(item.descricao).length > 14) {
            colSize = 2;
            count++;
        }

        html += `<div id="${item.id}-container" class="col-sm-${colSize} p-t-20">
                    <div class="row">
                        ${item.descricao}
                    </div>
                    <div id="${item.id}-value" class="row" data-rawvalue="${item.valor}">
                        ${value}
                    </div>
                </div>`;

        if (count == 10) {
            html += "</div>";
            count = -1;
        }

        count++;
    }

    $("#condicoes-container").html(html);
    $("#btnSubmit").show();
}

function createButton(cell) {
    let row = cell.getRow();
    let data = row.getData();

    let div = document.createElement("div");
    div.style = "display: flex; flex-direction: row; justify-content: flex-start; align-items: center; gap: 6px";

    let btnReparc = document.createElement("button");
    btnReparc.classList.add("btn", "btn-nw-buscas", "btn-xs");
    btnReparc.type = "button";
    btnReparc.innerText = "Parcelas";
    btnReparc.setAttribute("title", "Agrupar/Reparcelar");

    btnReparc.addEventListener("click", () => {
        let data = cell.getRow().getData();

        showModalReparc(data);
    });

    let btnVale = document.createElement("button");
    btnVale.classList.add("btn", "btn-nw-registro", "btn-xs");
    btnVale.type = "button";
    btnVale.innerText = "Vale Gás";
    btnVale.setAttribute("title", "Adicionar Vale Gás");

    if (condicoesValeGas.includes(parseInt(data.condicao_id))) {
        btnVale.addEventListener("click", () => showModalValeGas(cell));
        div.appendChild(btnVale);
    } else {
        div.appendChild(btnReparc);
    }

    return div;
}

function showModalValeGas(cell) {
    const data = cell.getRow().getData();
    const url = root + "/malote.getProdPedido/" + data.id;

    showLoaderAjax();

    ajaxGenerator(
        url,
        "GET",
        function (response) {
            if (!("itens" in response)) {
                console.error(response);
                return;
            }
            let itens = response.itens;

            const options = [];
            for (let i = 0; i < itens.length; i++) {
                const item = itens[i];
                options.push(new Option(item.produto_descricao, item.produto_id));
            }

            $("#valegaspedido_id").val(data.id);
            $("#valegasproduto_id").empty().trigger("chosen:updated");
            $("#valegasproduto_id").append(options).trigger("chosen:updated");
        },
        null,
        null,
        true,
        function () {
            setTimeout(() => {
                $("#modalValidaGasBolso").modal("show");
                hideLoaderAjax();
            }, 200);
        }
    );
}

function showModalReparc(data) {
    let parcelasJson = data.parcelas;

    if (!parcelasJson) {
        console.error("Algo de errado com as parcelas", data);
        return;
    }

    let parcelas = JSON.parse(parcelasJson);
    let ids = [];
    for (const parc of parcelas) {
        if (parc.baixado) {
            bootbox.alert("Parcelas baixadas não podem ser agrupadas.");
            return false;
        }

        if (parc.possui_cheque) {
            bootbox.alert("Parcelas vinculadas a algum cheque não podem ser agrupadas.");
            return false;
        }

        if (parc.possui_boleto) {
            bootbox.alert("Parcela: " + parc.id + " possui um boleto gerado então não pode ser agrupada.");
            return false;
        }

        ids.push(parc.id);
    }

    $("#fmAbrirFinanceiro").attr("action", root + "/financeiro.createbyagrupar");
    $("#cliente_id").val(data.cliente_id);
    $("#nome").val(data.cliente);
    $("#pedido_id").val(data.id);
    // $("#parcelas_financeiro").val(JSON.stringify(parcelas));
    // $("#fmAbrirFinanceiro").submit();
    // $("#popup_financeiro").modal("show");

    $("#popup_parcelas").modal("show");

    getParcelas(ids);
}

function getParcelas(ids) {
    let url = root + "/malote.parcelas?parc=:parcelas";
    url = url.replace(":parcelas", ids.join(","));

    if (tblParcelas) {
        tblParcelas.destroy();
    }

    tblParcelas = new Tabulator("#tbl_parcelas", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: "200px",
        layouy: "fitData",
        ajaxContentType: "json",
        ajaxResponse: (_url, _params, response) => response.parcelas,
        columns: [
            {
                title: "",
                field: "marcar",
                formatter: (cell) => {
                    const checked = cell.getValue() ? "checked" : "";
                    return `<input type="checkbox" ${checked}>`;
                },
                cellClick: (_e, cell) => {
                    const currentValue = cell.getValue();
                    cell.setValue(!currentValue);
                    cell.getRow().reformat();
                },
            },
            { title: "Cód", field: "id" },
            { title: "Doc", field: "documento" },
            { title: "N° Parcela", field: "numero" },
            {
                title: "Emissão",
                field: "dataemissao",
                formatter: (cell) => {
                    if (!cell.getValue()) return "";

                    return moment(cell.getValue()).format("DD/MM/YYYY");
                },
            },
            {
                title: "Vencto",
                field: "datavencimento",
                formatter: (cell) => {
                    if (!cell.getValue()) return "";

                    return moment(cell.getValue()).format("DD/MM/YYYY");
                },
            },
            {
                title: "Valor",
                field: "valor",
                formatter: "money",
                formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "R$ ",
                    negativeSign: true,
                    precision: 2,
                },
            },
            { title: "Agrupamento Status", field: "agrupamento_status" },
        ],
    });

    $("#url_parcelas").val(url);
}

window.closeModal = function () {
    let pedido_id = $("#pedido_id").val();
    let url = root + "/malote.getPedido/" + pedido_id;
    $("#popup_financeiro").modal("hide");
    showLoaderAjax();
    ajaxGenerator(
        url,
        "GET",
        function (response) {
            let pedido = response.pedido;
            tblPedidos.updateData([pedido]);

            hideLoaderAjax();
            setTimeout(() => {
                showModalReparc(pedido);
            }, 150);
        },
        null,
        null,
        true,
        function () {
            $("#fmAbrirFinanceiro").removeData("submitted");
            // $("#fmAbrirFinanceiro").reset();
        }
    );
};

function parcValidated(cell) {
    let data = cell.getRow().getData();
    let parcelasJson = data.parcelas;

    if (!parcelasJson) {
        console.error("Algo de errado com as parcelas", data);
        return false;
    }

    let parcelas = JSON.parse(parcelasJson);
    for (const parc of parcelas) {
        if (parc.baixado) {
            bootbox.alert("Pedido possui parcela baixada e não pode ser alterado.");
            return false;
        }

        if (parc.possui_cheque) {
            bootbox.alert("Pedido possui parcela vinculada a algum cheque e não pode ser alterado.");
            return false;
        }

        if (parc.possui_boleto) {
            bootbox.alert("Pedido possui parcela vinculada a um boleto e não pode ser alterado.");
            return false;
        }

        if (parc.agrupamento > 0) {
            bootbox.alert("Pedido possui parcela agrupada e não pode ser alterado.");
            return false;
        }
    }

    return true;
}

function dispatchOrderUpdate(cell) {
    showLoaderAjax("Aguarde...", "Atualizando o pedido.", false);

    let row = cell.getRow();
    let data = row.getData();
    let url = root + "/malote.updatePedido/" + data.id;
    let formData = new FormData();
    formData.append("modalpedido_id", data.id);
    formData.append("pedidomotivoatraso_id", "");
    formData.append("modalsetor_id", data.entregasetor_id);
    formData.append("modalcolaborador_id", data.colaborador_id);
    formData.append("modalpedidosituacao_id", data.pedidosituacao_id);
    formData.append("cartaoautorizacao", data.cartaoautorizacao ?? "");
    formData.append("produtosvalegas", JSON.stringify([]));
    formData.append("modalcondicaopagamento_id", cell.getValue());

    ajaxGenerator(
        url,
        "POST",
        function (response) {
            if (!("pedido" in response)) {
                console.error("Algo deu errado..", response);
                return;
            }

            const pedido = response.pedido;
            const oldValue = cell.getOldValue();
            const $oldCond = $(`#${oldValue}-value`);
            const $newCond = $(`#${pedido.condicao_id}-value`);
            const $lastCond = $("#-2-container");
            let newCondTotal = parseFloat($newCond.attr("data-rawvalue"));
            let oldCondTotal = parseFloat($oldCond.attr("data-rawvalue"));
            let pedVal = parseFloat(pedido.valorvenda);
            oldCondTotal -= pedVal;

            if (isNaN(newCondTotal)) {
                let colSize = String(pedido.condicao).length > 14 ? 1 : 2;
                let value = "R$ " + formataDecimal(pedVal);
                let html = `<div id="${pedido.condicao_id}-container" class="col-sm-${colSize} p-t-20">
                    <div class="row">
                        ${pedido.condicao}
                    </div>
                    <div id="${pedido.condicao_id}-value" class="row" data-rawvalue="${pedVal}">
                        ${value}
                    </div>
                </div>`;
                $lastCond.after(html);
            } else {
                newCondTotal += pedVal;
                $newCond.attr("data-rawvalue", newCondTotal);
                $newCond.html("R$ " + formataDecimal(newCondTotal));
            }

            $oldCond.attr("data-rawvalue", oldCondTotal);
            $oldCond.html("R$ " + formataDecimal(oldCondTotal));

            row.update(pedido);
        },
        function (err) {
            console.error(err.responseText);

            let msg = "Erro desconhecido.";
            if ("msg" in err.responseJSON) {
                msg = err.responseJSON.msg;
            }

            bootbox.alert(msg);

            cell.restoreOldValue();
        },
        formData,
        true,
        function () {
            hideLoaderAjax();
        }
    );
}

function addValeGas() {
    $("#btnCloseModalValidaGasBolso").prop("disabled", true);
    $("#btnValidaGasBolso").prop("disabled", true);
    $("#btnAddValeGas").prop("disabled", true);
    $(".btn-remove-vale").prop("disabled", true);

    let url = root + "/validaGasBolso";
    let pedido_id = $("#valegaspedido_id").val();
    let empresa_id = $("#malote_empresa_id").val();
    let codVale = $("#cod_gasbolso").val().trim();
    let produto_id = $("#valegasproduto_id").val();

    if (!produto_id) {
        bootbox.alert("Informe o produto.");
        return;
    }

    if (!codVale) {
        bootbox.alert("Informe o Vale Gás.");
        return;
    }

    let formData = new FormData();
    formData.append("codigo", codVale);
    formData.append("produto_id", produto_id);
    formData.append("empresa_id", empresa_id);
    formData.append("pedido_id", pedido_id);

    let produtoExistente = false;
    tblCodValeGas.rows().every(function () {
        let row = this.data();
        if (row[0] == codVale) {
            produtoExistente = true;
        }
    });

    let pedRows = tblPedidos.getData();
    for (const row of pedRows) {
        if (row.valegas == null || row.valegas == "") continue;

        let vales = JSON.parse(row.valegas);
        let exist = vales.some((val) => val[0] == codVale);

        if (exist) {
            produtoExistente = true;
            break;
        }
    }

    if (produtoExistente) {
        bootbox.alert("Este Vale Gás já foi validado!");
        return;
    }

    ajaxGenerator(
        url,
        "POST",
        function (data) {
            if (typeof data === "object") {
                tblCodValeGas.row
                    .add([
                        data.codigo,
                        data.valor,
                        data.produto_id,
                        data.produto,
                        "<button id='btnRemoverValeGas' type='button' class='btn btn-nw-registro btn-xs btn-remove-vale'>Remover</button>",
                    ])
                    .draw();
                $("#valegasproduto_id option:selected").attr("disabled", "true").trigger("chosen:updated");
            } else {
                bootbox.alert("" + data);
            }
            $("#cod_gasbolso").val("");
            $("#valegasproduto_id").children("option:enabled").eq(0).prop("selected", true).trigger("chosen:updated");
        },
        null,
        formData,
        true,
        function () {
            $("#btnCloseModalValidaGasBolso").prop("disabled", false);
            $("#btnValidaGasBolso").prop("disabled", false);
            $("#btnAddValeGas").prop("disabled", false);
            $(".btn-remove-vale").prop("disabled", false);
        }
    );
}

function validated() {
    let rows = tblPedidos.getRows();
    let valido = true;

    if (rows.length <= 0) {
        return false;
    }

    for (const row of rows) {
        const data = row.getData();

        if (condicoesValeGas.includes(parseInt(data.condicao_id)) && !data.valegas) {
            valido = false;
            break;
        }
    }

    if (!valido) {
        bootbox.alert("Um ou mais pedidos possuem a forma de pagamento Vale Gás e o código não foi informado.");
        return false;
    }

    return true;
}

function fecharMalote() {
    $("#popup_fecharmalote").modal("hide");
    let url = root + "/malote.fechar";
    let data = tblPedidos.getData();
    let dataFechamento = $("#data_fechamento").val();
    let formData = new FormData();
    formData.append("pedidos", JSON.stringify(data));
    formData.append("data_fechamento", dataFechamento);

    ajaxGenerator(
        url,
        "POST",
        function (response) {
            if (response == "OK") {
                bootbox.alert("Malote fechado com sucesso!");
                setTimeout(() => {
                    $("#btnLimpar")[0].click();
                }, 100);
            }
        },
        function (err) {
            console.error(err);
            let msg = "Erro desconhecido.";
            if ("responseJSON" in err && "msg" in err?.responseJSON) {
                msg = err.responseJSON.msg;
            }

            bootbox.alert(msg);
        },
        formData,
        false,
        function () {
            hideLoaderAjax();
        }
    );
}
