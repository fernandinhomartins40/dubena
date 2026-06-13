$(document).ready(function () {
    tblSaldos = undefined;
    tblVencidos = undefined;
    tblGiro = undefined;

    renderSaldos();

    renderVencidos();

    renderGiro();
});

$("#btnRefresh").click(function () {
    $(this).prop("disabled", true);
    const fetchSaldos = tblSaldos.setData();
    const fetchVencidos = tblVencidos.setData();
    const fetchGiro = tblGiro.setData();

    Promise.all([fetchSaldos, fetchVencidos, fetchGiro]).finally(() => {
        $(this).prop("disabled", false);
    });
});

$("#btnModalImpressao").click(function () {
    $("#modalImpressao").modal("show");
});

$("#btnImprimir").click(function () {
    let grid = $("#grid").val();
    let url = root + "/";

    if (grid == 1) {
        url += "comodatogestao.vencidosPdf";
    } else {
        url += "comodatogestao.giroPdf";
    }

    window.open(url, "_blank");
});

function renderSaldos() {
    tblSaldos = new Tabulator("#tbl_saldos", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: "100%",
        layout: "fitColumns",
        ajaxURL: root + "/comodatogestao.getSaldos",
        ajaxResponse: (_url, _params, response) => response.data,
        columns: [
            { title: "Descrição", field: "descricao" },
            {
                title: "Estoque Atual",
                field: "qtde_estoque",
                hozAlign: "center",
                formatter: "money",
                formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    negativeSign: true,
                    precision: 0,
                },
            },
            {
                title: "Comodato Cliente",
                field: "qtde_cliente",
                hozAlign: "center",
                formatter: "money",
                formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    negativeSign: true,
                    precision: 0,
                },
            },
            {
                title: "Comodato Companhia",
                field: "qtde_comp",
                hozAlign: "center",
                formatter: "money",
                formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    negativeSign: true,
                    precision: 0,
                },
            },
            {
                title: "Saldo",
                field: "saldo",
                hozAlign: "center",
                formatter: function (cell) {
                    let value = cell.getValue();
                    let $el = cell.getElement();

                    if (value < 0) {
                        $el.style.color = "red";
                    }

                    return formataDecimal(value, 0);
                },
            },
        ],
    });
}

function renderVencidos() {
    tblVencidos = new Tabulator("#tbl_vencimentos", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: 500,
        layout: "fitColumns",
        ajaxURL: root + "/comodatogestao.getVencidos",
        pagination: true,
        paginationMode: "remote",
        paginationSize: 15,
        paginationSizeSelector: [15, 25, 50, 100],
        dataReceiveParams: {
            last_page: "last_page",
        },
        rowFormatter: function (row) {
            let data = row.getData();
            let $el = row.getElement();

            if (data.ordem == 1) {
                $el.style.backgroundColor = "#7ed07e";
                return;
            }

            if (data.vencido == 1) {
                $el.style.backgroundColor = "#c77272";
                return;
            }
        },
        columns: [
            {
                title: "Cliente",
                field: "nome",
                cellDblClick: function (_e, cell) {
                    let data = cell.getRow().getData();

                    window.open(root + "/comodato/" + data.id);
                },
            },
            { title: "Representante", field: "representante" },
            {
                title: "Vencto",
                field: "datavencimento",
                formatter: (cell) => {
                    if (!cell.getValue()) return "";

                    return moment(cell.getValue()).format("DD/MM/YYYY");
                },
            },
            { title: "Qtde Itens", field: "quantidade" },
        ],
    });
}

function renderGiro() {
    tblGiro = new Tabulator("#tbl_giro", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: 780,
        layout: "fitColumns",
        ajaxURL: root + "/comodatogestao.getGiro",
        ajaxResponse: (_url, _params, response) => response.data,
        columns: [
            {
                title: "Cliente",

                width: 160,
                field: "nome",
                cellDblClick: function (_e, cell) {
                    let data = cell.getRow().getData();

                    window.open(root + "/comodato/" + data.id);
                },
            },
            {
                title: "Qtde Comodato",
                field: "qtde_comodato",
                width: 110,
                hozAlign: "center",
                formatter: "money",
                formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    negativeSign: true,
                    precision: 0,
                },
            },
            {
                title: "",
                field: "alerta_qtde",
                width: 10,
                formatter: (cell) => renderAlert(cell, "qtde"),
                tooltip: (_e, cell) => renderTooltip(cell, "qtde"),
            },
            {
                title: "Meses desde Contrato",
                field: "diff",
                hozAlign: "center",
                width: 120,
                formatter: "money",
                formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    negativeSign: true,
                    precision: 0,
                },
            },
            {
                title: "Compras",
                field: "qtde_compras",
                width: 100,
                hozAlign: "center",
                formatter: "money",
                formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    negativeSign: true,
                    precision: 0,
                },
            },
            {
                title: "Giro",
                field: "giro",
                width: 80,
                hozAlign: "center",
                formatter: "money",
                formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    negativeSign: true,
                    precision: 3,
                },
            },
            {
                title: "",
                field: "alerta_giro",
                width: 10,
                formatter: (cell) => renderAlert(cell, "giro"),
                tooltip: (_e, cell) => renderTooltip(cell, "giro"),
            },
        ],
    });
}

function renderAlert(cell, which) {
    let data = cell.getRow().getData();
    let qtde = parseInt(data.qtde_comodato);
    let giro = parseFloat(data.giro);
    let green = "green";
    let red = "red";
    let alert_color = "";

    switch (which) {
        case "qtde":
            if (qtde > giro) {
                alert_color = red;
            }

            if (qtde <= giro) {
                alert_color = green;
            }
            break;
        case "giro":
            if (giro >= 1) {
                alert_color = green;
            }

            if (giro < 1) {
                alert_color = red;
            }
            break;
    }

    return `<div class="alert-circle" style="background-color: ${alert_color};"></div>`;
}

function renderTooltip(cell, which) {
    let data = cell.getRow().getData();
    let msg = "";
    let qtde = parseInt(data.qtde_comodato);
    let giro = parseFloat(data.giro);

    switch (which) {
        case "qtde":
            if (qtde > giro) {
                msg = "Qtde Comodato é maior que o Giro";
            }

            if (qtde <= giro) {
                msg = "Qtde Comodato é menor ou igual ao Giro";
            }
            break;
        case "giro":
            if (giro >= 1) {
                msg = "Giro maior ou igual a 1";
            }

            if (giro < 1) {
                msg = "Giro menor que 1";
            }
            break;
    }

    return msg;
}
