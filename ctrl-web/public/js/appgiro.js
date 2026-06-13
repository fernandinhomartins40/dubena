$(document).ready(function () {
    tblGiro = undefined;
    tblNoti = undefined;

    initTable();
});

$("#btnNotificar").click(function () {
    let selectedRows = tblGiro.getSelectedData();

    if (selectedRows.length <= 0) {
        bootbox.alert("Por favor, selecione os clientes para serem notificados.");
        return;
    }

    if (!validated(selectedRows)) {
        bootbox.alert("Um ou mais clientes já foram notificados.");
        return;
    }

    $("#popup_notificacao").modal("show");
});

$("#btnEnviarNoti").click(notifyDevices);

function initTable() {
    tblGiro = new Tabulator("#tbl_giro", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: "500px",
        layout: "fitDataFill",
        ajaxURL: `${root}/appgiro.getGiro`,
        ajaxContentType: "json",
        ajaxResponse: (_url, _params, response) => response.data,
        columns: [
            {
                title: "",
                field: "notificar",
                formatter: "rowSelection",
                titleFormatter: "rowSelection",
                headerSort: false,
            },
            { title: "Cód", field: "cliente_id" },
            { title: "Cliente", field: "cliente" },
            { title: "Primeira Compra", field: "primeira_compra" },
            { title: "Última Compra", field: "ultima_compra" },
            { title: "Previsão Compra", field: "previsao_compra" },
            {
                title: "Qtde Dias",
                field: "qtde_dias",
                formatter: "money",
                formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "",
                    negativeSign: true,
                    precision: 0,
                },
            },
            {
                title: "Qtde Itens",
                field: "qtde_itens",
                formatter: "money",
                formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "",
                    negativeSign: true,
                    precision: 0,
                },
            },
            {
                title: "Giro",
                field: "giro",
                formatter: "money",
                formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "",
                    negativeSign: true,
                    precision: 2,
                },
            },
        ],
        rowFormatter: function (row) {
            const data = row.getData();

            row.getElement().classList.remove("emEntrega");

            if (data.notificado) {
                row.getElement().classList.add("emEntrega");
            }
        },
        rowUpdated: function (row) {
            console.log(row);
            row.reformat();
        },
    });

    tblNoti = new Tabulator("#tbl_notificacao", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: "250px",
        layout: "fitData",
        selectableRowsRangeMode: 1,
        columns: [
            {
                title: "",
                field: "utilizar",
                formatter: "rowSelection",
                headerSort: false,
            },
            { title: "Cód", field: "id" },
            { title: "Titulo", field: "fcmtitle" },
            { title: "Corpo", field: "fcmbody" },
        ],
    });

    // tblNoti.on("rowDblClick", function (_e, row) {
    //     const data = row.getData();

    //     selectdNotificationId = data.id;

    //     $("#popup_notificacao").modal("hide");
    // });
}

function validated(rows) {
    for (const row of rows) {
        if (row.notificado) {
            return false;
        }
    }

    return true;
}

function notifyDevices() {
    let layout = tblNoti.getSelectedData();

    if (layout.length <= 0 || layout.length > 1) {
        bootbox.alert("Por favor, selecione o layout da notificação a ser enviada.");
        return;
    }

    let clients = tblGiro.getSelectedData();
    let clients_id = clients.map((it) => it.cliente_id);

    showLoaderAjax("Enviando Notificações", " Por favor aguarde", false, () => {
        setTimeout(() => {
            sendNotificationRequest(clients_id, layout);
        }, 150);
    });
}

function sendNotificationRequest(clients_id, layoutArr) {
    let url = root + "/appgiro.notify";
    let layout_id = layoutArr[0].id;
    let formData = new FormData();
    formData.append("clientes_id", JSON.stringify(clients_id));
    formData.append("layout_id", layout_id);

    ajaxGenerator(
        url,
        "POST",
        function (response) {
            if (response.status == "OK") {
                bootbox.alert(
                    "Notificações enviadas! " +
                        response.msg +
                        "<br />" +
                        "<br />" +
                        "<i>*Falhas significam que o aplicativo desses clientes foi desinstalado.</i>",
                );

                updateRows();
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
        },
    );
}

function updateRows() {
    let rows = tblGiro.getSelectedRows();

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const data = row.getData();
        data.notificado = true;
        row.update(data);
        row.reformat();
    }

    tblGiro.deselectRow();
    tblNoti.deselectRow();

    $("#popup_notificacao").modal("hide");
}
