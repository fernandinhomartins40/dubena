$(document).on("ready", function () {
    hideContainers();
    hideTipoPessoa();
    checkAusente();

    initializeTables();

    if (typeof errors !== "undefined" && errors) fillErrors();
});

$("#tipopessoa_id").on("change", hideTipoPessoa);

$("#buscarClienteByName").on("click", function () {
    let cliente = $("#cliente").val();

    if (isEmpty(cliente)) return;

    searchByClientName(cliente);
});

$("#buscarRua").on("click", function () {
    let rua = $("#rua").val();

    if (isEmpty(rua)) return;

    searchRoad(rua);
});

$("#buscarBairro").on("click", function () {
    let bairro = $("#bairro").val();

    if (isEmpty(bairro)) return;

    searchNeighborhood(bairro);
});

$("#buscarCliente").on("click", function () {
    let rua_id = $("#rua_id").val();
    let numero = $("#numero").val();
    let $ausente = $("#ausente");

    if ($ausente.prop("checked")) {
        $ausente.trigger("click");
    }

    if (isEmpty(rua_id) || isEmpty(numero)) {
        bootbox.alert("Por favor digite o número e busque a rua!");
        return;
    }

    searchCliente(rua_id, numero);
});

$("#ausente").on("click", function () {
    clearClientFields(true);
    hideContainers();

    if (!this.checked) clearAddress(false);

    showHideContainers("endereco-container", this.checked);
});

$("#btnNovoCliente").on("click", function () {
    $("#popup_selects").modal("hide");

    showContainers();
});

$("#popup_historico").on("shown.bs.modal", function () {
    adjustTables();
});

$("#telefone").on("keyup", function () {
    $("#btnAddFone").prop("disabled", $("#telefone").val().length === 0);
});

$("#btnAddFone").on("click", function () {
    buscarTelefonesClientes();
});

$("#btnAddPreco").click(function () {
    let prod = !$("#selectProdutosPrecos").find("option:selected").isEmpty();
    let $valor0 = $("#valor0");
    let desc = $valor0.isEmpty() ? !$("#valor1").isEmpty() : !$valor0.isEmpty();
    if (prod && desc) {
        addPrecoProdutoCliente();
    }
});

$("#tblTelefones").on("click", "#btnEditarTelefone", function () {
    let trElem = $(this).closest("tr"); // grabs the button's parent tr element;

    editarTelefone(trElem);

    removerLinha(trElem, tblFone, "tblFone");
});

$("#tblTelefones").on("click", "#btnRemoverTelefone", function () {
    let trElem = $(this).closest("tr"); // grabs the button's parent tr element;

    removerLinha(trElem, tblFone, "tblFone");
});

$("#tblProdutosPrecos").on("click", "#btnRemoverProduto", function () {
    let trElem = $(this).closest("tr"); // grabs the button's parent tr element;

    removerLinha(trElem, tblProdutosPrecos, "tblProdutosPrecos", true);
});

$("#fmCadastro").on("submit", function (e) {
    if ($("#telefone").is(":focus")) {
        e.preventDefault();
        return false;
    }

    contentTablesToJSON();
});

var containers = ["endereco-container", "cliente-container"];

var allTables = {
    tblCondPgto: {
        added: false,
        removed: [],
    }, //usa o cond_id como primary
    tblProdConvenio: {
        added: false,
        removed: [],
    }, //tblProdConvenio
    tblClientePromocoes: {
        added: false,
        removed: [],
    }, //tblClitenteProcmocoes
    tblCont: {
        added: false,
        removed: [],
    }, //tblContatos
    tblFone: {
        added: false,
        removed: [],
    }, //tblTelefones
    tblParentesco: {
        added: false,
        removed: [],
    }, //tblParentesco
    tblProdutosPrecos: {
        added: false,
        removed: [],
    }, //tblProdutosPrecos
};

var respObj = {};

function hideContainers() {
    containers.forEach(function (cont) {
        showHideContainers(cont, false);
    });
}

function showContainers() {
    containers.forEach(function (cont) {
        showHideContainers(cont, true);
    });
}

function hideTipoPessoa() {
    let tipo = $("#tipopessoa_id").val();
    let boolFis = true;
    let boolJur = false;

    if (tipo.includes("J")) {
        boolFis = false;
        boolJur = true;
    }

    showHideContainers("fisica", boolFis);
    showHideContainers("juridica", boolJur);
}

function checkAusente() {
    let isAusente = $("#ausente").prop("checked");

    showHideContainers("endereco-container", isAusente);
}

function initializeTables() {
    let basicObj = {
        language: { url: urlDataTable },
        processing: false,
        bPaginate: false,
        bLengthChange: false,
        bFilter: false,
        bSort: false,
        bInfo: false,
        bAutoWidth: false,
    };

    if (window.innerWidth < 768) {
        respObj = {
            sScrollX: true,
            responsive: true,
            scrollY: "100px",
            scrollCollapse: true,
        };
    }

    tblProdutosPrecos = $("#tblProdutosPrecos").DataTable({
        ...basicObj,
        ...respObj,
        columnDefs: [
            {
                visible: false,
                targets: [0, 3, 5, 6],
            },
        ],
    });

    tblFone = $("#tblTelefones").DataTable({
        ...basicObj,
        ...respObj,
        columnDefs: [
            {
                targets: [0, 1],
                visible: false,
            },
        ],
    });

    tblHistorico = undefined;
}

function showHideContainers(cont, remove = true) {
    let $cont = $(`.${cont}`);

    if (remove) {
        if ($cont.hasClass("hidden")) $cont.removeClass("hidden");
    } else {
        if (!$cont.hasClass("hidden")) $cont.addClass("hidden");
    }
}

//#region Data Fetching
async function searchByClientName(cliente) {
    let cidade_id = $("#cidade_id").val();
    let url = root + `/promotor.getClienteByNome?nome=${cliente}&cidade_id=${cidade_id}`;
    let data = await fetchData(url);
    clearClientFields();
    clearAddress();

    if (data.length <= 0) {
        bootbox.alert("Cliente não encontrado nesta cidade ou não existe no sistema.");
    }

    return processReturnData(data);
}

async function searchRoad(rua) {
    let cidade_id = $("#cidade_id").val();
    let url = root + `/promotor.getStreet?rua=${rua}&cidade_id=${cidade_id}`;
    let data = await fetchData(url);
    clearClientFields();
    clearAddress();

    if (data.length == 1) {
        return fillRoads(data[0]);
    }

    if (data.length > 1) {
        let columns = [{ data: "rua_id" }, { data: "rua_desc" }, { data: "selecionar" }];

        data = data.map(function (rua) {
            return {
                rua_desc: rua.rua_desc,
                rua_id: rua.rua_id,
                selecionar: "<button id='btnSelectRow' class='btn btn-xs btn-nw-geral'><i class='fa fa-check'></i></button>",
            };
        });

        return fillModal(data, columns, fillRoads);
    }

    bootbox.alert("Rua não encontrada na cidade selecionada ou não existe no sistema.");
}

async function searchNeighborhood(bairro) {
    let cidade_id = $("#cidade_id").val();
    let url = root + `/promotor.getNeighborhood?bairro=${bairro}&cidade_id=${cidade_id}`;
    let data = await fetchData(url);

    if (data.length == 1) {
        return fillNeighborhood(data[0]);
    }

    if (data.length > 1) {
        let columns = [{ data: "bairro_id" }, { data: "bairro_desc" }, { data: "selecionar" }];

        data = data.map(function (bairro) {
            return {
                bairro_desc: bairro.bairro_desc,
                bairro_id: bairro.bairro_id,
                selecionar: "<button id='btnSelectRow' class='btn btn-xs btn-nw-geral'><i class='fa fa-check'></i></button>",
            };
        });

        return fillModal(data, columns, fillNeighborhood);
    }

    bootbox.alert("Bairro não encontrado no sistema, verifique a ortografia.");
}

async function searchCliente(rua_id, numero) {
    let cidade_id = $("#cidade_id").val();
    let url = root + `/promotor.getClientByStreet?rua_id=${rua_id}&numero=${numero}&cidade_id=${cidade_id}`;
    let data = await fetchData(url);
    let $btnNovo = $("#container-nenhum");
    clearClientFields();
    clearAddress(false);

    if (data.length <= 0) {
        return showContainers();
    }

    processReturnData(data);

    if ($btnNovo.hasClass("hidden")) {
        $btnNovo.removeClass("hidden");
    }
}

async function buscarTelefonesClientes() {
    let url = root + "/clientetelefone/buscaclientetelefone/:tel?cliente_id=:cliente_id&validateExists=1";
    if (!$.isNumeric($("#telefonetipo_id").val())) {
        bootbox.alert("Preencha o tipo de telefone.");
        return;
    }
    let $tel = $("#telefone");
    if (!$tel.val().trim()) {
        bootbox.alert("Preencha o telefone.");
        return;
    }
    if ($tel.val().length < 14) {
        bootbox.alert("O telefone está incompleto");
        $tel.focus();
        return;
    }
    let tel = $tel.val();
    let cliente_id = $("#cliente_id").val();
    url = url.replace(":tel", tel);
    if (typeof cliente_id === "undefined" || isEmpty(cliente_id)) {
        url = url.replace(":cliente_id", "0");
    } else {
        url = url.replace(":cliente_id", cliente_id);
    }
    url = url.replace(" ", "--");

    let foneExists = false;
    tblFone
        .column(3)
        .data()
        .each(function (value) {
            if ($("#telefone").val() === value) {
                foneExists = true;
            }
        });

    if (foneExists) {
        bootbox.alert("Telefone já consta na lista desse usuário.");
    } else {
        let response = await fetchData(url, false, false);

        if (response.substr(0, 3) === "OK|") {
            addFone();
        } else if (response.substr(0, 3) === "OPS") {
            if ($("#tblTelefones").text().indexOf(tel) !== -1) {
                bootbox.alert("Este telefone já está cadastrado para este cliente!");
            } else {
                addFone();
            }
        } else {
            bootbox.alert("Este telefone já está cadastrado para outro cliente: " + response);
        }
    }
}

async function getHistorico(cliente_id) {
    let url = root + `/promotor.getHistoricoCliente/${cliente_id}/5`;
    let data = await fetchData(url);

    if (data && data.length > 0) fillHistoricoTable(data);
    else destroyHistoricoTable();
}

async function fetchData(url, post = false, isJson = true) {
    try {
        const payload = await fetch(url, {
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            method: post ? "POST" : "GET",
        });

        if (isJson) {
            const response = await payload.json();

            if (response.status == "OK") {
                return response.data;
            }
        } else {
            const response = await payload.text();

            return response;
        }
    } catch (error) {
        console.error("error: ", error);
        return false;
    }
}
// #endregion

function processReturnData(data) {
    if (data.length == 1) {
        return fillClientFromSearch(data[0], false);
    }

    if (data.length > 1) {
        let columns = [{ data: "cliente_json" }, { data: "nome" }, { data: "selecionar" }];

        data = data.map(function (cliente) {
            return {
                nome: cliente.nome,
                cliente_json: JSON.stringify(cliente),
                selecionar: "<button id='btnSelectRow' class='btn btn-xs btn-nw-geral'><i class='fa fa-check'></i></button>",
            };
        });

        return fillModal(data, columns, fillClientFromSearch);
    }
}

function fillRoads(street) {
    let $rua = $("#rua");
    let $rua_id = $("#rua_id");

    $rua.val(street.rua_desc);
    $rua_id.val(street.rua_id);
}

function fillNeighborhood(neigh) {
    let $bairro = $("#bairro");
    let $bairro_id = $("#bairro_id");

    $bairro.val(neigh.bairro_desc);
    $bairro_id.val(neigh.bairro_id);
}

function getBtns(name, withEdit = false) {
    let btnEdit = `<button type='button' class='btn btn-nw-geral btn-xs' id='btnEditar${name}'>Editar</button>`;
    let btnRemov = `<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover${name}'>Remover</button>`;

    if (withEdit) return btnEdit + " " + btnRemov;

    return btnRemov;
}

function fillClientFromSearch(data, json = true) {
    cliente = data;
    if (json) {
        cliente = JSON.parse(data.cliente_json);
    }

    let $rua = $("#rua");
    let $rua_id = $("#rua_id");
    let $numero = $("#numero");
    let $cidade = $("#cidade_id");

    $rua.val(cliente.rua.descricao);
    $rua_id.val(cliente.rua.id);
    $numero.val(cliente.numero);
    $cidade.val(cliente.cidade_id);

    getHistorico(cliente.id);

    return fillClientFields(cliente);
}

function fillClientFields(cliente) {
    let $cliente_id = $("#cliente_id");
    let $bairro = $("#bairro");
    let $uf = $("#uf");
    let $cidade_id = $("#cidade_id");
    let $bairro_id = $("#bairro_id");
    let $complemento = $("#complemento");
    let $nome = $("#nome");
    let $cliente = $("#cliente");
    let $cpf = $("#cpf");
    let $rg = $("#rg");
    let $cnpj = $("#cnpj");
    let $inscricao_estadual = $("#inscricao_estadual");
    let $datanascimento = $("#datanascimento");
    let $ponto_referencia = $("#ponto_referencia");
    let $convCont = $("#convenio-container");
    let $convDiv = $("#convenio-div");
    let descpara = JSON.parse($("#descpara").val());
    let selects = ["setor_id", "tipopessoa_id", "segmento_id", "sexo"];
    let telBtn = getBtns("Telefone", true);
    let proBtn = getBtns("Produto");
    let opts = Array.apply(null, document.querySelector("#tipopessoa_id").options);
    let options = opts.map(function (opt) {
        return opt.value;
    });

    selects.forEach(function (sel) {
        let value = cliente[sel];

        if (sel == "tipopessoa_id") {
            value = options.find(function (item) {
                return item.startsWith(value);
            });
        }

        $(`#${sel}`).val(value).trigger("chosen:updated");
    });

    $cliente.val(cliente.nome);
    $cliente_id.val(cliente.id);
    $bairro.val(cliente.bairro.descricao);
    $uf.val(cliente.uf);
    $cidade_id.val(cliente.cidade_id);
    $bairro_id.val(cliente.bairro.id);
    $complemento.val(cliente.complemento);
    $nome.val(cliente.nome);
    $cpf.val(cliente.cpf);
    $rg.val(cliente.rg);
    $cnpj.val(cliente.cnpj);
    $inscricao_estadual.val(cliente.inscricao_estadual);
    $datanascimento.val(cliente.datanascimento);
    $ponto_referencia.val(cliente.ponto_referencia);

    if (cliente.conveniado) {
        $convDiv.html("Cliente Conveniado: " + cliente.convenio);

        if ($convCont.hasClass("hidden")) $convCont.removeClass("hidden");
    }

    tblFone.clear();
    tblProdutosPrecos.clear();

    if (cliente.telefones.length > 0) {
        for (const telefone of cliente.telefones) {
            tblFone.row.add([telefone.id, telefone.teltipo.id, telefone.teltipo.descricao, telefone.telefone, telefone.whatsapp == 1 ? "Sim" : "Não", telBtn]);
        }

        tblFone.draw();
    }

    if (cliente.clienteProduto.length > 0) {
        for (const produto of cliente.clienteProduto) {
            let preco = " ";
            let desc = " ";

            if (!isEmpty(produto.preco)) {
                preco = formataDecimal(produto.preco);
            }

            if (produto.tipo == 1) {
                desc = formataDecimal(produto.desconto);
            } else {
                desc = formataDecimal(produto.desconto * 100);
            }

            disableOption($("#selectProdutosPrecos"), produto.prod.id);

            tblProdutosPrecos.row.add([
                produto.id,
                produto.prod.id,
                produto.prod.descricao,
                preco,
                `R$ ${desc}`,
                produto.tipo,
                produto.descontopara,
                descpara[produto.descontopara],
                proBtn,
            ]);
        }

        tblProdutosPrecos.draw();
    }

    showContainers();

    adjustTables();
}

function adjustTables() {
    tblProdutosPrecos.columns.adjust().draw();
    tblFone.columns.adjust().draw();
}

function contentTablesToJSON() {
    putTableInSelector($("#telefones"), tblFone);
    putTableInSelector($("#produtos"), tblProdutosPrecos);

    $("#alltables").val(JSON.stringify(allTables));
}

function putTableInSelector($selector, tbl) {
    let data = [];
    tbl.rows().every(function () {
        var d = this.data();
        data.push(d);
    });
    data = JSON.stringify(data);
    if (data) {
        $selector.val(data);
    } else {
        $selector.val("");
    }
}

function editarTelefone(row) {
    let data = tblFone.row(row).data();
    let tipo = data[1];
    let telefone = data[3];
    let whats = data[4] === "Sim";

    $("#telefonetipo_id").val(tipo).trigger("chosen:updated");
    $("#whatsapp").prop("checked", whats);

    $("#btnAddFone").prop("disabled", false);
    $("#telefone").val(telefone).focus();
}

function removerLinha(element, table, strTable, hasDisabled = false) {
    let tableRow = table.row(element);
    let data = tableRow.data();

    if (data[0]) {
        allTables[strTable].removed.push(data[0]);
    }

    if (hasDisabled) {
        disableOption($("#selectProdutosPrecos"), data[1], false);
    }

    tableRow.remove().draw();
}

function carregarTelefonesErro() {
    tblFone.clear();
    let telefones = JSON.parse($("#telefones").val());
    tblFone.rows.add(telefones).draw();
}

function carregarAllTablesErro() {
    allTables = JSON.parse($("#alltables").val());
}

function addedTable(strTable) {
    allTables[strTable].added = true;
}

function addFone() {
    let wpp = "Não";
    let $tel = $("#telefone");
    let $wpp = $("#whatsapp");
    let $btnAdd = $("#btnAddFone");
    let $telTipo = $("#telefonetipo_id");
    let btn = getBtns("Telefone", true);

    if ($wpp.prop("checked")) {
        wpp = "Sim";
    }

    if (!$btnAdd.prop("disabled")) {
        addedTable("tblFone");
        tblFone.row.add(["", $telTipo.val(), $telTipo.find("option:selected").text(), $tel.val(), wpp, btn]).draw(false);
    }
    $tel.val("");
    $wpp.prop("checked", false);
    $btnAdd.prop("disabled", true);
    $telTipo.focus().trigger("chosen:activate");
}

function addPrecoProdutoCliente() {
    let button = getBtns("Produto");
    let $select = $("#selectProdutosPrecos");
    let $selected = $select.find("option:selected");
    let $desc = $("#valor0");

    addedTable("tblProdutosPrecos");
    tblProdutosPrecos.row.add(["", $selected.val(), $selected.text(), "", $desc.val(), "1", "2", "Aplicativo", button]).draw();

    $selected.prop("disabled", true).trigger("chosen:updated");
    $select.val("").trigger("chosen:updated").focus().trigger("chosen:activate");
    $desc.val("");
}

function disableOption($selector, value, disable = true) {
    let options = $selector.find("option");

    for (let i = 0; i < options.length; i++) {
        const opt = options[i];

        if (opt.value == value) {
            opt.disabled = disable;
            break;
        }
    }

    $selector.trigger("chosen:updated");
}

function enableAllOptions() {
    let options = $("#selectProdutosPrecos").find("option");

    for (let i = 0; i < options.length; i++) {
        const opt = options[i];
        opt.disabled = false;
    }

    $("#selectProdutosPrecos").trigger("chosen:updated");
}

function clearClientFields(ausente = false) {
    let ausenteFields = ["cliente", "cliente_id", "bairro", "uf", "bairro_id", "setor_id", "ponto_referencia"];
    let clienteFields = [
        "cliente",
        "cliente_id",
        "bairro",
        "uf",
        "bairro_id",
        "complemento",
        "nome",
        "cpf",
        "rg",
        "cnpj",
        "inscricao_estadual",
        "datanascimento",
        "setor_id",
        "tipopessoa_id",
        "segmento_id",
        "sexo",
        "ponto_referencia",
    ];
    let $convCont = $("#convenio-container");
    let $convDiv = $("#convenio-div");

    for (const field of clienteFields) {
        if (ausente && ausenteFields.includes(field)) {
            continue;
        }
        $(`#${field}`).val("").trigger("chosen:updated");
    }

    if (!$convCont.hasClass("hidden")) $convCont.addClass("hidden");

    $convDiv.html("");

    tblFone.clear().draw();
    tblProdutosPrecos.clear().draw();
    enableAllOptions();
}

function clearAddress(clearNumber = true) {
    let addressFields = ["uf", "bairro", "bairro_id", "complemento", "ponto_referencia"];

    if (clearNumber) addressFields.push("numero");

    for (const field of addressFields) {
        $(`#${field}`).val("").trigger("chosen:updated");
    }
}

function fillModal(data, columns, callback) {
    let $tbl_descs = $("#tbl_descricoes");
    let $btnNovo = $("#container-nenhum");
    let objetoTbl = getDefinicaoTbl(columns, data);
    let table = $tbl_descs.DataTable({
        ...objetoTbl,
        columnDefs: [
            {
                visible: false,
                targets: [0],
            },
        ],
    });

    if (!$btnNovo.hasClass("hidden")) {
        $btnNovo.addClass("hidden");
    }

    $tbl_descs.off().on("click", "#btnSelectRow", function () {
        let trElem = $(this).closest("tr");
        let rowData = table.row(trElem).data();

        if (typeof callback === "function") callback(rowData);

        $("#popup_selects").modal("hide");
    });

    $("#popup_selects")
        .off()
        .on("shown.bs.modal", function () {
            table.columns.adjust().draw();
        });

    $("#popup_selects").modal("show");

    setTimeout(function () {
        $tbl_descs.find("button:first").focus();
    }, 1100);
}

function getDefinicaoTbl(columns, data) {
    return {
        language: { url: urlDataTable },
        processing: false,
        bPaginate: false,
        bLengthChange: false,
        bFilter: false,
        bSort: false,
        bInfo: false,
        bAutoWidth: false,
        data: data,
        destroy: true,
        sScrollY: "300px",
        columns: columns,
        ...respObj,
    };
}

function fillErrors() {
    let ausente = $("#ausente").prop("checked");

    if (ausente) return;

    let prods = $("#produtos").val();
    let tels = $("#telefones").val();

    if (!isEmpty(prods)) carregarProdutosErro(prods);

    if (!isEmpty(tels)) carregarTelefonesErro(tels);

    showContainers();
}

function carregarProdutosErro(prods) {
    tblProdutosPrecos.clear();
    let produtos = JSON.parse(prods);
    tblProdutosPrecos.rows.add(produtos).draw();
}

function carregarTelefonesErro(tels) {
    tblFone.clear();
    let telefones = JSON.parse(tels);
    tblFone.rows.add(telefones).draw();
}

function fillHistoricoTable(data) {
    let $tbl_hist = $("#tbl_historico");
    let $histmsg = $("#hist-msg");
    let $tblrow = $("#tbl-row");
    let columns = [{ data: "pedido_id" }, { data: "data" }, { data: "condicao" }, { data: "status" }, { data: "produto" }, { data: "quantidade" }, { data: "valor" }];
    let objetoTbl = getDefinicaoTbl(columns, data);

    if (!$histmsg.hasClass("hidden")) $histmsg.addClass("hidden");

    if ($tblrow.hasClass("hidden")) $tblrow.removeClass("hidden");

    tblHistorico = $tbl_hist.DataTable(objetoTbl);

    $("#popup_historico")
        .off()
        .on("shown.bs.modal", function () {
            tblHistorico.columns.adjust().draw();
        });
}

function destroyHistoricoTable() {
    if (typeof tblHistorico == "undefined") return;

    let $histmsg = $("#hist-msg");
    let $tblrow = $("#tbl-row");

    tblHistorico.destroy();

    if ($histmsg.hasClass("hidden")) $histmsg.removeClass("hidden");

    if (!$tblrow.hasClass("hidden")) $tblrow.addClass("hidden");
}
