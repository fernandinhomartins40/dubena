$(document).ready(function () {
    tblParcelas = $("#tblParcelas").DataTable({
        language: {
            url: urlDataTable,
        },
        processing: false,
        bPaginate: false,
        bLengthChange: false,
        bFilter: false,
        bSort: false,
        bInfo: false,
        bAutoWidth: false,
        destroy: true,
    });

    setDatesFromUrl();
});

$("#btnBuscar").click(function () {
    let datainicio = insertDataOracle($("#datainicio").val());
    let datafim = insertDataOracle($("#datafim").val());

    let url = root + `/pix.index?inicio=${datainicio}&fim=${datafim}`;

    window.location.href = url;
});

$("#tblParcelas").on("click", "tr", function () {
    let row = $(this);
    let data = tblParcelas.row(row).data();

    if (data[8] != "CONCLUIDA") {
        bootbox.alert("Não é possível baixar parcela de PIX não concluído!");
        return;
    }

    if (row.hasClass("linhaselecionada")) {
        row.removeClass("linhaselecionada");
    } else {
        row.addClass("linhaselecionada");
    }
});

$("#fmParcelas").on("submit", function (e) {
    e.preventDefault();
    let rows = tblParcelas.rows(".linhaselecionada").data();

    if (rows.length <= 0) {
        bootbox.alert("Por favor selecione as parcelas para baixar!");
        return;
    }

    let parcelas = rows
        .map(function (row) {
            return row[0];
        })
        .join(",");

    let url = root + `/pix.baixar/${parcelas}`;

    window.location.href = url;
});

function setDatesFromUrl() {
    let inicio = getParametro("inicio");
    let fim = getParametro("fim");

    if (inicio) $("#datainicio").val(requestDataOracle(inicio, false));

    if (fim) $("#datafim").val(requestDataOracle(fim, false));
}
