const urlAjax = root + "/conciliacao.get";
let urlDet = "";
let detType = 1;

$(document).ready(function () {
    tblFinCon = undefined;
    tblDocs = undefined;
    tblComp = undefined;

    initTable();
});

$("#btnConsultaDocs").click(function () {
    tblFinCon.setData(urlAjax);
});

$("#popup_documentos").on("shown.bs.modal", function () {
    if (urlDet === "" || typeof urlDet !== "string") {
        $(this).modal("hide");
        return;
    }

    let columns = [];

    // ? 1 = Financeiro
    if (detType == 1) {
        columns = getColumnsFin();
    }

    // ? 2 = Contábil
    if (detType == 2) {
        columns = getColumnsCont();
    }

    tblDocs = new Tabulator("#tbl_documentos", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: "300px",
        layout: "fitColumns",
        ajaxContentType: "json",
        ajaxResponse: (_url, _params, response) => response.data,
        columns: columns,
    });

    setTimeout(() => {
        tblDocs.setData(urlDet);
    }, 200);
});

$("#popup_documentos").on("hidden.bs.modal", function () {
    if (typeof tblDocs !== "undefined") {
        tblDocs.destroy();
    }
});

$("#popup_saldo").on("shown.bs.modal", function () {
    if (typeof urlDet !== "object") {
        $(this).modal("hide");
        return;
    }

    let finCols = getColumnsFin();
    let contCols = getColumnsCont();

    tblComp = new Tabulator("#tbl_financeiro", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: "300px",
        layout: "fitColumns",
        ajaxContentType: "json",
        ajaxResponse: (_url, _params, response) => response.data,
        columns: finCols,
    });

    tblDocs = new Tabulator("#tbl_contabil", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: "300px",
        layout: "fitColumns",
        ajaxContentType: "json",
        ajaxResponse: (_url, _params, response) => response.data,
        columns: contCols,
    });

    setTimeout(() => {
        tblDocs.setData(urlDet.url_fin);
        tblComp.setData(urlDet.url_cont);
    }, 200);
});

$("#popup_saldo").on("hidden.bs.modal", function () {
    if (typeof tblDocs !== "undefined") {
        tblDocs.destroy();
    }

    if (typeof tblComp !== "undefined") {
        tblComp.destroy();
    }
});

function initTable() {
    tblFinCon = new Tabulator("#tbl_contfin", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: "500px",
        layout: "fitDataFill",
        ajaxContentType: "json",
        ajaxParams: getQueryParam,
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
        ajaxResponse: (_url, _params, response) => response.data,
        columns: [
            { title: "Cód", field: "id" },
            { title: "Cód Consisa", field: "consisa_id", visible: false },
            { title: "Cliente", field: "cliente" },
            {
                title: "Financeiro",
                field: "valor_fin",
                formatter: "money",
                formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "R$ ",
                    negativeSign: true,
                    precision: 2,
                },
            },
            {
                title: "Crédito Contábil",
                field: "credito",
                formatter: "money",
                formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "R$ ",
                    negativeSign: true,
                    precision: 2,
                },
            },
            {
                title: "Débito Contábil",
                field: "debito",
                formatter: "money",
                formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "R$ ",
                    negativeSign: true,
                    precision: 2,
                },
            },
            {
                title: "Diferença",
                field: "diff",
                formatter: "money",
                formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "R$ ",
                    negativeSign: true,
                    precision: 2,
                },
            },
        ],
        rowFormatter: function (row) {
            let cells = row.getCells();
            let cell = cells[6];
            let value = cell.getValue();
            let element = cell.getElement();

            if (value == null) return;

            if (value == 0) {
                element.style.backgroundColor = "#99fa96";
            } else {
                element.style.backgroundColor = "#fc8365";
            }
        },
    });

    tblFinCon.on("cellClick", function (_e, cell) {
        let data = cell.getRow().getData();
        let field = cell.getField();
        let qParam = getQueryParam();
        let query = `?inicio=${qParam.inicio}&fim=${qParam.fim}&pagarReceber=${qParam.pagarReceber}`;

        if (field == "valor_fin") {
            urlDet = `${root}/conciliacao.getFinDet${query}&cliente_id=${data.id}`;
            detType = 1;
            $("#docTitle").html(`Detlhando dados Financeiros do Cliente: ${data.cliente}`);
            $("#popup_documentos").modal("show");
            return;
        }

        if (field == "valor_cont") {
            urlDet = `${root}/conciliacao.getContDet${query}&cliente_id=${data.consisa_id}`;
            detType = 2;
            $("#docTitle").html(`Detlhando dados Contábeis do Cliente: ${data.cliente}`);
            $("#popup_documentos").modal("show");
            return;
        }

        if (field != "diff") return;

        let urlFin = `${root}/conciliacao.getContDet${query}&cliente_id=${data.consisa_id}`;
        let urlCont = `${root}/conciliacao.getFinDet${query}&cliente_id=${data.id}`;
        urlDet = {
            url_fin: urlFin,
            url_cont: urlCont,
        };
        $("#saldoTitle").html(`Saldo do Cliente: ${data.cliente}`);
        $("#popup_saldo").modal("show");
    });
}

function getQueryParam() {
    let inicio = $("#datainicio").val();
    let fim = $("#datafim").val();
    let pagarReceber = $("[name='pagarReceber']:checked").val();

    return { inicio, fim, pagarReceber };
}

function getColumnsFin() {
    return [
        { title: "Cód Fin", field: "id", visible: false },
        { title: "Emissão", field: "emissao" },
        { title: "Documento", field: "documento" },
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
    ];
}

function getColumnsCont() {
    return [
        { title: "Plano Cód", field: "plano_cod" },
        { title: "Plano Nome", field: "plano_desc" },
        { title: "Documento", field: "documento" },
        { title: "Tipo", field: "tipo" },
        { title: "Emissão", field: "emissao" },
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
        { title: "Obs.", field: "observacao" },
    ];
}
