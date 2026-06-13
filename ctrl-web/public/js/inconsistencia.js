const Types = {
    rua: "1",
    bairro: "2",
};

$(document).ready(function () {
    tblRuaIncon = undefined;
    tblBairroIncon = undefined;
    tblToComp = undefined;
    tblComp = undefined;

    fillCities(null, defaultCidadeId);
    fillCities(null, defaultCidadeId, "_bairro");

    initTable();
});

$("#uf").on("change", fillCities);

$("#uf_bairro").on("change", () => fillCities(null, null, "_bairro"));

$("#btnBuscar").click(function () {
    tblRuaIncon.setData(urlGetRuas);
});

$("#btnBuscarBairro").click(function () {
    tblBairroIncon.setData(urlGetBairros);
});

function fillCities(_e, cidade_id = null, sufix = "") {
    let uf = $("#uf" + sufix).val();
    let $cidade = $("#cidade_id" + sufix);
    $cidade.empty().trigger("chosen:updated");

    let url = root + "/cidade/dropdown/" + uf;
    if (!isEmpty(uf)) {
        ajaxGenerator(
            url,
            "GET",
            function (data) {
                data = data.replace('<select name="cidade_id">', "");
                data = data.replace("</select>", "");
                $cidade.html(data).trigger("chosen:updated");

                if (cidade_id) $cidade.val(cidade_id).trigger("chosen:updated");
            },
            null,
            null,
            true
        );
    }
}

function initTable() {
    const ruasCols = getColumns(Types.rua);
    const bairrosCols = getColumns(Types.bairro);

    tblRuaIncon = new Tabulator("#tbl_ruainc", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: "500px",
        layout: "fitDataFill",
        ajaxContentType: "json",
        ajaxParams: getQueryParam,
        ajaxRequesting: () => validateAjaxReq(),
        ajaxResponse: (_url, _params, response) => response.data,
        columns: ruasCols,
    });

    tblBairroIncon = new Tabulator("#tbl_bairroinc", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: "500px",
        layout: "fitDataFill",
        ajaxContentType: "json",
        ajaxParams: () => getQueryParam("_bairro"),
        ajaxRequesting: () => validateAjaxReq("_bairro"),
        ajaxResponse: (_url, _params, response) => response.data,
        columns: bairrosCols,
    });
}

function getColumns(type) {
    const cellDblClick = (cell, url, replaceStr) => {
        let id = cell.getValue();

        window.open(String(url).replace(replaceStr, id), "_blank");
    };

    return [
        {
            title: "Cód",
            field: "id",
            cellDblClick: (_e, cell) => cellDblClick(cell, urlRuaEdit, "rua_id"),
        },
        { title: "Descrição", field: "descricao" },
        {
            title: "Cód Comparativo",
            field: "id_comp",
            cellDblClick: (_e, cell) => cellDblClick(cell, urlRuaEdit, "rua_id"),
        },
        { title: "Descrição Comparativa", field: "descricao_comp" },
        {
            title: "Ações",
            field: "",
            formatter: function (cell) {
                let rowData = cell.getRow().getData();

                let btnVis = document.createElement("button");
                btnVis.classList.add("btn", "btn-nw-buscas", "btn-xs");
                btnVis.innerHTML = '<span class="fa fa-eye fa-lg"></span>';
                btnVis.addEventListener("click", () => {
                    showLoaderAjax("Buscando..", " Por favor aguarde!", false, () => {
                        setTimeout(() => {
                            showRecords(rowData, type);
                        }, 150);
                    });
                });

                let btnIgn = document.createElement("button");
                btnIgn.classList.add("btn", "btn-nw-registro", "btn-xs", "m-l-5");
                btnIgn.innerHTML = '<span class="fa fa-ban fa-lg"></span>';
                btnIgn.style = "margin-left: 5px";
                btnIgn.addEventListener("click", () => {
                    bootbox.confirm({
                        title: "Atenção",
                        message: `Deseja adicionar essa combinação: ${rowData.descricao} e ${rowData.descricao_comp} a lista de ignorados?`,
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

                            showLoaderAjax("Salvando..", " Por favor aguarde!", false, () => {
                                setTimeout(() => {
                                    saveIgnore(cell, type);
                                }, 150);
                            });
                        },
                    });
                });

                let div = document.createElement("div");
                div.appendChild(btnVis);
                div.appendChild(btnIgn);

                return div;
            },
        },
    ];
}

function getQueryParam(sufix = "") {
    let uf = $("#uf" + sufix).val();
    let cidade_id = $("#cidade_id" + sufix).val();

    return { uf, cidade_id };
}

function validateAjaxReq(sufix = "") {
    let uf = $("#uf" + sufix).val();
    let cidade_id = $("#cidade_id" + sufix).val();

    if (!uf) {
        bootbox.alert("Por favor, informe o Estado.");
        return false;
    }

    if (!cidade_id) {
        bootbox.alert("Por favor, informe a Cidade.");
        return false;
    }

    return true;
}

function showRecords(row, type) {
    let url = "";

    if (type == Types.rua) url = urlRegistrosRuas;
    else url = urlRegistrosBairros;

    ajaxGenerator(
        `${url}&data_id=${row.id}&datacomp_id=${row.id_comp}`,
        "GET",
        (response) => {
            renderModal(row, response, type);
        },
        (err) => {
            console.error(err);
        },
        null,
        true,
        () => {
            hideLoaderAjax();
        }
    );
}

function saveIgnore(cell, type) {
    let row = cell.getRow();
    let data = row.getData();
    let url = "";
    let formData = new FormData();

    switch (type) {
        case Types.rua:
            url = urlIgnorarRuas;
            formData.append("rua_id", data.id);
            formData.append("ruaignore_id", data.id_comp);
            break;
        case Types.bairro:
            url = urlIgnorarBairros;
            formData.append("bairro_id", data.id);
            formData.append("bairroignore_id", data.id_comp);
            break;
    }

    ajaxGenerator(
        url,
        "POST",
        (_resp) => {
            // bootbox.alert("Rua ignorada com sucesso.");
            row.delete();
        },
        (err) => {
            bootbox.alert("Erro ao ignorar combinação.");
            console.error(err);
        },
        formData,
        true,
        () => {
            hideLoaderAjax();
        }
    );
}

function renderModal(row, response, type) {
    let record = response.data;
    let recordcomp = response.data_comp;

    let options = {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: "500px",
        layout: "fitDataFill",
        columns: [
            { title: "Tipo", field: "tipo" },
            { title: "Cód", field: "id" },
            { title: "Descrição", field: "descricao" },
            { title: "Quantidade", field: "qtd" },
        ],
    };

    let title = "";
    switch (type) {
        case Types.rua:
            title = "Registros que utilizam a rua";
            break;
        case Types.bairro:
            title = "Registros que utilizam o bairro";
            break;
    }

    $("#tocomp_title").html(`${title}: ${row.descricao}`);
    $("#comp_title").html(`${title}: ${row.descricao_comp}`);

    $("#popup_registros").modal("show");

    tblToComp = new Tabulator("#tbl_tocomp", {
        ...options,
        data: record,
    });

    tblComp = new Tabulator("#tbl_comp", {
        ...options,
        data: recordcomp,
    });

    const callback = (_e, row) => {
        let data = row.getData();

        if (data.rota == null) return;

        window.open(data.rota_url, "_blank");
    };

    tblToComp.on("rowDblClick", callback);

    tblComp.on("rowDblClick", callback);
}
