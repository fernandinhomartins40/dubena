var tblExtrato = undefined;
var tblLancamentos = undefined;
var tblParcelas = undefined;

const corIgnorar = "#ffffde";
const corLancar = "#d9ffd9";
const corBaixar = "#e8fdff";
const corSugestaoBaixar = "#ffefe8";
const corBaixado = "#f0f2f0";
var conta_id = null;
var rowDataBaixaOfx = null;
var ids_baixa = [];
var contamovimentotipos = [];
var tipoBuscaCliente = "C";

jQuery(document).ready(function ($) {
    searchboxCliente();
});

$("#file-upload").attr("accept", ".ofx");
var validFormatUpload = ["ofx"];
var callbackUpload = function () {
    var url = root + "/importExtrato";
    $("#fmUpload")
        .off()
        .attr({
            action: url,
            method: "post",
        })
        .on("submit", function (e) {
            e.preventDefault();
            if (isEmpty($("#file-upload").val())) {
                bootbox.alert("Selecione um arquivo");
                return false;
            }
            renderEnviarExtrato();
            return false;
        });
};
$("#btnUpload").on("click", function () {
    $("#modal-upload-file").modal("show");
});

function renderEnviarExtrato() {
    $("#modal-upload-file").modal("hide");
    showLoaderAjax("Aguarde", "Processando extrato", false);
    setTimeout(() => {
        enviarExtrato();
    }, 500);
}

function enviarExtrato() {
    var myForm = document.getElementById("fmUpload");
    var formData = new FormData(myForm);

    const url = root + "/importExtrato";
    $.ajax({
        type: "POST",
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
        url: url,
        data: formData,
        async: false,
        success: function (data) {
            hideLoaderAjax();
            if (typeof data === "object")
                if (data.status == "OK") {
                    renderTabelaExtrato(data.data.transactions);
                    renderTabelaLancamentos(data.data.lancamentos);
                    conta_id = data.data.conta.id;
                    contamovimentotipos = data.data.contamovimentotipos;
                    $("#tituloConta").html(
                        `Conta: ${data.data.conta.agencia}/${data.data.conta.conta} - ${data.data.conta.descricao}`,
                    );
                    $("#divLegenda").html(`
                            <div class="alert-circle" style="background-color: ${corLancar};">Lançar no Caixa</div>
                            <div class="alert-circle" style="background-color: ${corSugestaoBaixar};">Tem Sugestão de Baixa</div>
                            <div class="alert-circle" style="background-color: ${corBaixar};">Baixar C.Receber/C.Pagar</div>
                            <div class="alert-circle" style="background-color: ${corIgnorar};"><input class="chkStatus" id="chkIgnorado" type="checkbox" checked>Ignorar Lançamento</div>
                            <div class="alert-circle" style="background-color: ${corBaixado};"><input class="chkStatus" id="chkBaixado" type="checkbox" checked>Já Baixado Anteriormente por OFX</div>
                        `);
                    $(".showRender").show();
                    $("#chkIgnorado, #chkBaixado").on("change", function () {
                        if (!$("#chkBaixado").is(":checked") || !$("#chkIgnorado").is(":checked")) {
                            tblExtrato.setFilter(
                                function (data, filterParams) {
                                    if (!filterParams.baixado && !filterParams.ignorado) {
                                        return !data.baixado && !data.ignorar;
                                    } else if (!filterParams.baixado) {
                                        return !data.baixado;
                                    } else {
                                        return !data.ignorar;
                                    }
                                },
                                {
                                    baixado: $("#chkBaixado").is(":checked"),
                                    ignorado: $("#chkIgnorado").is(":checked"),
                                },
                            );
                        } else {
                            tblExtrato.clearFilter();
                        }
                    });
                } else {
                    bootbox.alert(data.msg);
                }
            else if (typeof data === "string") bootbox.alert("Erro: " + data);
            else bootbox.alert("Erro desconhecido");
        },
        error: function (data) {
            hideLoaderAjax();
            bootbox.alert("Erro ao fazer o upload");
        },
        cache: false,
        contentType: false,
        processData: false,
    });
}

function renderTabelaExtrato(dados) {
    const colunas = [
        {
            title: "Data",
            field: "date",
            headerHozAlign: "center",
            width: 110,
            formatter: "datetime",
            formatterParams: {
                inputFormat: "yyyy-MM-dd HH:mm:ss", // Format of the date in your data
                outputFormat: "dd/MM/yyyy", // Desired display format
                invalidPlaceholder: "(data inválida)", // Text for invalid dates
            },
        },
        { title: "OFX Id", field: "uniqueid", width: 100 },
        { title: "Histórico", field: "memo", width: 230 },
        {
            title: "Valor",
            field: "amount",
            hozAlign: "right",
            headerHozAlign: "right",
            width: 100,
            formatter: function (cell, formatterParams, onRendered) {
                const rowData = cell.getData();
                const value = cell.getValue();
                const formattedValue = value.toLocaleString("pt-BR", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
                const classColor = value < 0 ? "negativo" : "positivo";
                // Adiciona a classe de cor condicional à célula
                cell.getElement().classList.add(classColor);
                return `<span class="${classColor}">${formattedValue}</span>`;
            },
        },
        {
            title: "Ações",
            field: "actions",
            width: 80,
            hozAlign: "center",
            headerHozAlign: "center",
            headerSort: false,
            // 1. Usa um formatter para criar o botão
            formatter: function (cell, formatterParams, onRendered) {
                let row = cell.getRow();
                let rowData = row.getData();

                if (rowData.lancarbaixar) {
                    return "<i class='fa fa-bars'></i>";
                }

                if (!rowData.lancar && !rowData.baixar && !rowData.baixado) {
                    return "<i class='fa fa-bars'></i>";
                }
            },
            // 2. Lida com o evento de clique na célula
            cellClick: function (e, cell) {
                // Evita que o Tabulator selecione a linha
                e.stopPropagation();

                var row = cell.getRow();
                var rowData = row.getData();
                if (rowData.lancar || rowData.baixar || rowData.baixado) {
                    return;
                }

                // Remove qualquer menu de contexto existente para evitar múltiplos menus
                var existingMenu = document.querySelector(".context-menu");
                if (existingMenu) {
                    existingMenu.remove();
                }

                // Cria o menu de contexto
                var menu = document.createElement("ul");
                menu.className = "context-menu";

                var menuItems = [];

                menuItems.push({
                    label: "Baixar Contas a Pagar/Receber",
                    action: () => {
                        showModalBuscar(rowData);
                        menu.remove();
                    },
                });

                if (rowData.sugestaobaixar) {
                    const { dadosbaixa } = rowData;
                    let suggestions = rowData.lancarbaixar ? dadosbaixa.titulos : dadosbaixa;

                    suggestions.map((baixa) => {
                        if (!ids_baixa.includes(baixa.financeiroparcela_id)) {
                            const label = `Baixar doc '${baixa.documento ? baixa.documento : ""}' cliente '${baixa.nome}' venc. ${requestDataOracle(baixa.datavencimento, false, false, true)}`;
                            menuItems.push({
                                label: label,
                                action: () => {
                                    rowDataBaixaOfx = rowData;
                                    validarAdicionarParcela(baixa);
                                    menu.remove();
                                },
                            });
                        }
                    });
                }

                menuItems.push({
                    label: "Lançar no Caixa",
                    action: () => {
                        showModalLancar(rowData);
                        menu.remove();
                    },
                });

                if (!rowData.ignorar && !rowData.lancarbaixar) {
                    menuItems.push({
                        label: "Ignorar",
                        action: () => {
                            row.update({ ignorar: true, actions: "" });
                            menu.remove();
                        },
                    });
                }
                // menuItems.push({ label: 'Transferir', action: () => alert("Detalhes de " + rowData.name) });

                menuItems.forEach((item) => {
                    var li = document.createElement("li");
                    li.textContent = item.label;
                    li.addEventListener("click", item.action);
                    menu.appendChild(li);
                });

                // Posiciona o menu ao lado do botão clicado
                menu.style.left = e.pageX + "px";
                menu.style.top = e.pageY + "px";

                document.body.appendChild(menu);

                // Fecha o menu quando o usuário clica em qualquer outro lugar
                document.addEventListener("click", function closeMenu(event) {
                    if (!menu.contains(event.target)) {
                        menu.remove();
                        document.removeEventListener("click", closeMenu);
                    }
                });
            },
        },
    ];
    tblExtrato = new Tabulator("#tbl_extrato", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: "100%",
        layout: "fitColumns",
        data: dados,
        columns: colunas,
        rowFormatter: function (row) {
            var rowData = row.getData();

            if (rowData.baixar) {
                row.getElement().style.backgroundColor = corBaixar;
            } else if (rowData.lancar || rowData.lancarbaixar) {
                row.getElement().style.backgroundColor = corLancar;
            } else if (rowData.sugestaobaixar) {
                let rem = [];
                rowData.dadosbaixa.map((baixa) => {
                    if (!ids_baixa.includes(baixa.financeiroparcela_id)) {
                        rem.push(baixa);
                    }
                });
                if (rem.length > 0) {
                    row.getElement().style.backgroundColor = corSugestaoBaixar;
                } else {
                    row.getElement().style.backgroundColor = "";
                }
            } else if (rowData.baixado) {
                row.getElement().style.backgroundColor = corBaixado;
            } else if (rowData.ignorar) {
                row.getElement().style.backgroundColor = corIgnorar;
            } else {
                // Garante que a cor volte ao normal se a operação for alterada
                row.getElement().style.backgroundColor = "";
            }
        },
    });
}

function renderTabelaLancamentos(dados) {
    const colunas = [
        {
            title: "Data",
            field: "date",
            headerHozAlign: "center",
            width: 110,
            formatter: "datetime",
            formatterParams: {
                inputFormat: "yyyy-MM-dd HH:mm:ss", // Format of the date in your data
                outputFormat: "dd/MM/yyyy", // Desired display format
                invalidPlaceholder: "(data inválida)", // Text for invalid dates
            },
        },
        { title: "OFX Id", field: "uniqueid", width: 100 },
        { title: "Histórico", field: "memo", width: 230 },
        {
            title: "Valor",
            field: "amount",
            hozAlign: "right",
            headerHozAlign: "right",
            width: 100,
            formatter: function (cell, formatterParams, onRendered) {
                const value = cell.getValue();
                const formattedValue = value.toLocaleString("pt-BR", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
                const classColor = value < 0 ? "negativo" : "positivo";
                // Adiciona a classe de cor condicional à célula
                cell.getElement().classList.add(classColor);
                return `<span class="${classColor}">${formattedValue}</span>`;
            },
        },
        {
            title: "Ações",
            field: "actions",
            width: 80,
            hozAlign: "center",
            headerHozAlign: "center",
            headerSort: false,
            formatter: function () {
                return "<i class='fa fa-bars'></i>";
            },
            cellClick: function (e, cell) {
                e.stopPropagation();

                // Remove qualquer menu de contexto existente para evitar múltiplos menus
                var existingMenu = document.querySelector(".context-menu");
                if (existingMenu) {
                    existingMenu.remove();
                }

                // Cria o menu de contexto
                var menu = document.createElement("ul");
                menu.className = "context-menu";

                var menuItems = [];
                menuItems.push({
                    label: "Cancelar",
                    action: () => {
                        cancelarLancamento(cell);
                        menu.remove();
                    },
                });
                menuItems.push({
                    label: "Consultar",
                    action: () => {
                        consultarLancamento(cell);
                        menu.remove();
                    },
                });

                menuItems.forEach((item) => {
                    var li = document.createElement("li");
                    li.textContent = item.label;
                    li.addEventListener("click", item.action);
                    menu.appendChild(li);
                });

                // Posiciona o menu ao lado do botão clicado
                menu.style.left = e.pageX + "px";
                menu.style.top = e.pageY + "px";

                document.body.appendChild(menu);

                // Fecha o menu quando o usuário clica em qualquer outro lugar
                document.addEventListener("click", function closeMenu(event) {
                    if (!menu.contains(event.target)) {
                        menu.remove();
                        document.removeEventListener("click", closeMenu);
                    }
                });
            },
        },
    ];
    tblLancamentos = new Tabulator("#tbl_lancamentos", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: "100%",
        layout: "fitColumns",
        data: dados,
        columns: colunas,
        rowFormatter: function (row) {
            var rowData = row.getData();
            if (rowData.acao == "lancar") {
                row.getElement().style.backgroundColor = corLancar;
            } else if (rowData.acao == "baixar") {
                row.getElement().style.backgroundColor = corBaixar;
            } else {
                // Garante que a cor volte ao normal se a operação for alterada
                row.getElement().style.backgroundColor = "";
            }
        },
    });
}

function showModalLancar(rowData) {
    let $condLanc = $("#condicaopagamentolancamento_id");
    let $contMovTipoId = $("#contamovimentotipolancamento_id");
    let $pcLanId = $("#pclancamento_id");
    let $ccLanId = $("#cclancamento_id");
    let $pcLanDesc = $("#pclancamento_descricao");
    let $ccLanDesc = $("#cclancamento_descricao");
    let $pcLanBtn = $("#btnPclancamento");
    let $ccLanBtn = $("#btnCclancamento");

    tipoBuscaCliente = rowData.amount < 0 ? "F" : "C";
    $("#acaolancamento").val("lancar");
    $condLanc.val("").trigger("chosen:updated");
    $contMovTipoId.val("").trigger("chosen:updated");
    $pcLanId.val("");
    $ccLanId.val("");
    $pcLanDesc.val("");
    $ccLanDesc.val("");
    $("#datalancamento").val(rowData.date);
    $("#datalancamento_f").val(requestDataOracle(rowData.date, false, false, true));
    $("#valorlancamento").val(rowData.amount);
    $("#valorlancamento_f").val(formataDecimal(rowData.amount, 2));
    $("#descricaolancamento").val(rowData.memo);
    $("#uniqueidlancamento").val(rowData.uniqueid);

    const selectizeInstance = $("#searchboxClienteLancamento")[0].selectize;
    selectizeInstance.clear();
    selectizeInstance.clearOptions();
    selectizeInstance.enable();

    $(".lancamentolancar").show();
    $(".lancamentotransferir").hide();
    $("#h4TituloLancamento").html("Lançar Movimento no Caixa");
    $("#btnGravarLancamento").prop("disabled", false);
    $condLanc.prop("disabled", false).trigger("chosen:updated");
    $contMovTipoId.prop("disabled", false).trigger("chosen:updated");
    $pcLanBtn.prop("disabled", false);
    $ccLanBtn.prop("disabled", false);

    if (rowData.lancarbaixar) {
        const { dadosbaixa } = rowData;
        const { lancamento } = dadosbaixa;

        $pcLanId.val(lancamento.planoconta_id);
        $ccLanId.val(lancamento.centrocusto_id);
        $pcLanDesc.val(lancamento.pcdescricao);
        $ccLanDesc.val(lancamento.ccdescricao);
        $pcLanBtn.prop("disabled", true);
        $ccLanBtn.prop("disabled", true);
        $condLanc.val(lancamento.condicaopagamento_id).prop("disabled", true).trigger("chosen:updated");
        $contMovTipoId.val(lancamento.contamovimentotipo_id).prop("disabled", true).trigger("chosen:updated");
    }

    $("#lancamento_modal").modal("show");
}

function searchboxCliente() {
    $("#searchboxClienteLancamento").selectize({
        valueField: "id",
        labelField: "nome",
        searchField: ["nome"],
        maxOptions: 10,
        options: [],
        create: false,
        render: {
            option: function (item, escape) {
                return "<div>" + escape(item.nome) + "</div>";
            },
        },
        optgroups: [{ value: "cliente", label: "Clientes" }],
        optgroupField: "class",
        optgroupOrder: ["cliente"],
        load: function (query, callback) {
            if (!query.length) return callback();
            $.ajax({
                url: root + (tipoBuscaCliente == "C" ? "/api/searchClientes" : "/api/searchFornecedores"),
                type: "GET",
                dataType: "json",
                data: {
                    q: query,
                },
                error: function () {
                    callback();
                },
                success: function (res) {
                    callback(res.data);
                },
            });
        },
        onChange: function (data) {
            //buscaClientePorId(data);
            //$('#cliente_id_erro').val($('#searchboxPF').selectize()[0].selectize.getValue());
            //$('#cliente_nome_erro').val($('#searchboxPF').selectize()[0].selectize.getItem(this.items[0]).context.innerText);
        },
        onInitialize: function () {
            var existingOptions = JSON.parse(this.$input.attr("data-selectize-value"));
            var self = this;
            if (Object.prototype.toString.call(existingOptions) === "[object Array]") {
                existingOptions.forEach(function (existingOption) {
                    self.addOption(existingOption);
                    self.addItem(existingOption[self.settings.valueField]);
                });
            } else if (typeof existingOptions === "object") {
                self.addOption(existingOptions);
                self.addItem(existingOptions[self.settings.valueField]);
            }
        },
    });
    $("#searchboxClienteBaixa").selectize({
        valueField: "id",
        labelField: "nome",
        searchField: ["nome"],
        maxOptions: 10,
        options: [],
        create: false,
        render: {
            option: function (item, escape) {
                return "<div>" + escape(item.nome) + "</div>";
            },
        },
        optgroups: [{ value: "cliente", label: "Clientes" }],
        optgroupField: "class",
        optgroupOrder: ["cliente"],
        load: function (query, callback) {
            if (!query.length) return callback();
            $.ajax({
                url: root + (tipoBuscaCliente == "C" ? "/api/searchClientes" : "/api/searchFornecedores"),
                type: "GET",
                dataType: "json",
                data: {
                    q: query,
                },
                error: function () {
                    callback();
                },
                success: function (res) {
                    callback(res.data);
                },
            });
        },
        onChange: function (data) {
            //buscaClientePorId(data);
            //$('#cliente_id_erro').val($('#searchboxPF').selectize()[0].selectize.getValue());
            //$('#cliente_nome_erro').val($('#searchboxPF').selectize()[0].selectize.getItem(this.items[0]).context.innerText);
        },
        onInitialize: function () {
            var existingOptions = JSON.parse(this.$input.attr("data-selectize-value"));
            var self = this;
            if (Object.prototype.toString.call(existingOptions) === "[object Array]") {
                existingOptions.forEach(function (existingOption) {
                    self.addOption(existingOption);
                    self.addItem(existingOption[self.settings.valueField]);
                });
            } else if (typeof existingOptions === "object") {
                self.addOption(existingOptions);
                self.addItem(existingOptions[self.settings.valueField]);
            }
        },
    });
}

$("#btnGravarLancamento").on("click", () => {
    validarLancamento();
});

function validarLancamento() {
    let cliente = $("#searchboxClienteLancamento").selectize()[0].selectize.getValue();
    let cliente_nome = $($("#searchboxClienteLancamento").selectize()[0].selectize.getItem(cliente)).text();
    if ($("#acaolancamento").val() == "lancar") {
        if ($("#condicaopagamentolancamento_id").val().trim() == "") {
            bootbox.alert("Preencha a condição de pagamento.");
            return;
        }
        if ($("#contamovimentotipolancamento_id").val().trim() == "") {
            bootbox.alert("Preencha o tipo de movimento.");
            return;
        }
        if (!cliente) {
            bootbox.alert("Preencha o cliente.");
            return;
        }
        if ($("#pclancamento_id").val().trim() == "") {
            bootbox.alert("Preencha o plano de contas.");
            return;
        }
        if ($("#cclancamento_id").val().trim() == "") {
            bootbox.alert("Preencha o centro de custos.");
            return;
        }
    } else if ($("#acaolancamento").val() == lancamentoTransferir) {
        if ($("#contalancamento_id").val().trim() == "") {
            bootbox.alert("Preencha a conta origem/destino.");
            return;
        }
    }
    let erro = false;
    const rows = tblLancamentos.getData();
    rows.map((row) => {
        if (row.uniqueid.toUpperCase() == $("#uniqueidlancamento").val().trim().toUpperCase()) {
            erro = true;
        }
    });
    if (erro) {
        bootbox.alert(
            "Esse lançamento do extrato já está na tabela de lançamentos a serem efetivados no sistema. Se necessário, remova e inclua novamente.",
        );
        return;
    }
    if ($("#acaolancamento").val() == "lancar") {
        const record = {
            acao: "lancar",
            date: $("#datalancamento").val(),
            amount: parseFloat($("#valorlancamento").val()),
            uniqueid: $("#uniqueidlancamento").val(),
            memo: $("#descricaolancamento").val(),
            dadosbaixa: {
                id: null,
                descricao: $("#descricaolancamento").val(),
                conta_id: conta_id,
                condicaopagamento_id: $("#condicaopagamentolancamento_id").val(),
                planoconta_id: $("#pclancamento_id").val(),
                centrocusto_id: $("#cclancamento_id").val(),
                cliente_id: cliente,
                contamovimentotipo_id: $("#contamovimentotipolancamento_id").val(),
                acao: extratoconfigLancar,
                contaorigem_id: null,
                nome: cliente_nome,
                pcdescricao: $("#pclancamento_descricao").val(),
                ccdescricao: $("#cclancamento_descricao").val(),
                condpagtodescricao: $("#condicaopagamentolancamento_id option:selected").text(),
                movtotipodescricao: $("#contamovimentotipolancamento_id option:selected").text(),
            },
        };
        tblLancamentos.addRow(record);
        let rowsL = tblExtrato.getRows("all");
        rowsL.forEach(function (row) {
            var data = row.getData();
            if (data.uniqueid === $("#uniqueidlancamento").val()) {
                row.update({ lancar: true, actions: "" });
            }
        });
        $("#lancamento_modal").modal("hide");
    }
}

function showModalBuscar(rowData) {
    const selectizeInstance = $("#searchboxClienteBaixa")[0].selectize;
    const pagarreceber = rowData.amount < 0 ? "P" : "R";
    tipoBuscaCliente = rowData.amount < 0 ? "F" : "C";
    $("#pagarreceberbaixa").val(pagarreceber);
    $("#valorbaixaofx").val(formataDecimal(rowData.amount, 2));
    $("#valorbaixasel").val("");
    rowDataBaixaOfx = rowData;

    selectizeInstance.clear();
    selectizeInstance.clearOptions();

    if (tblParcelas != undefined) tblParcelas.clearData();

    $("#btnGravarParcelas").hide();
    $("#descricaobaixa").val(rowData.memo);
    $("#uniqueidbaixa").val(rowData.uniqueid);
    $("#contamovimentotipobaixa_id").val("");
    $("#contamovimentotipobaixa_id").prop("disabled", false).trigger("chosen:updated");
    $("#databaixa_f").val(requestDataOracle(rowData.date, false, false, true));
    $("#h4TituloBaixa").html("Baixar Título");
    $(".divAddBaixa").show();
    $("#btnGravarParcelas").prop("disabled", false);
    $("#baixar_modal").modal("show");
}

$("#btnBuscarParcelas").on("click", () => {
    renderBuscarParcelas();
});

function renderBuscarParcelas() {
    showLoaderAjax("Aguarde", "Buscando Títulos", false);
    setTimeout(() => {
        buscarParcelas();
    }, 500);
}

function buscarParcelas() {
    var formData = new FormData();
    formData.append("pagarreceber", $("#pagarreceberbaixa").val());
    formData.append("dataini", $("#datainibaixa").val());
    formData.append("datafim", $("#datafimbaixa").val());
    formData.append("cliente_id", $("#searchboxClienteBaixa").selectize()[0].selectize.getValue());
    const rowsL = tblLancamentos.getRows("all");
    let ids = [];
    rowsL.forEach(function (row) {
        var data = row.getData();
        if (data.acao == "baixar") {
            if (data.dadosbaixa && Array.isArray(data.dadosbaixa)) {
                data.dadosbaixa.map((bx) => {
                    ids.push(bx.financeiroparcela_id);
                });
            }
        }
    });
    formData.append("ids_ignorar", ids);
    const url = root + "/importExtrato.getParcelas";
    $.ajax({
        type: "POST",
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
        url: url,
        data: formData,
        async: false,
        success: function (data) {
            hideLoaderAjax();
            if (typeof data === "object")
                if (data.status == "OK") {
                    renderTabelaParcelas(data.data);
                } else {
                    bootbox.alert(data.msg);
                }
            else if (typeof data === "string") bootbox.alert("Erro: " + data);
            else bootbox.alert("Erro desconhecido");
        },
        error: function (data) {
            hideLoaderAjax();
            bootbox.alert("Erro ao buscar parcelas");
        },
        cache: false,
        contentType: false,
        processData: false,
    });
}

function renderTabelaParcelas(dados) {
    const colunas = [
        { formatter: "rowSelection", titleFormatter: "rowSelection", hozAlign: "center", headerSort: false },
        {
            title: "Vencimento",
            field: "datavencimento",
            headerHozAlign: "center",
            width: 130,
            formatter: "datetime",
            formatterParams: {
                inputFormat: "yyyy-MM-dd HH:mm:ss", // Format of the date in your data
                outputFormat: "dd/MM/yyyy", // Desired display format
                invalidPlaceholder: "(data inválida)", // Text for invalid dates
            },
        },
        { title: "Cliente", field: "nome", width: 250 },
        { title: "Documento", field: "documento", width: 120 },
        {
            title: "Valor",
            field: "valor",
            hozAlign: "right",
            headerHozAlign: "right",
            width: 150,
            formatter: function (cell, formatterParams, onRendered) {
                const rowData = cell.getData();
                const value = parseFloat(cell.getValue());
                const formattedValue = value.toLocaleString("pt-BR", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
                return `<span>${formattedValue}</span>`;
            },
        },
    ];
    tblParcelas = new Tabulator("#tbl_parcelas", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: "30vh",
        layout: "fitColumns",
        data: dados,
        columns: colunas,

        rowSelectionChanged: function (data, rows) {
            let total = 0;
            data.forEach(function (row) {
                total += row.valor;
            });
            var formattedTotal = formataDecimal(total, 2);
            $("#valorbaixasel").val(formattedTotal);
        },
    });

    tblParcelas.on("rowSelected", function (row) {
        atualizarTotalSelecionado();
    });

    tblParcelas.on("rowDeselected", function (row) {
        atualizarTotalSelecionado();
    });
    $("#btnGravarParcelas").show();
}

function atualizarTotalSelecionado() {
    const rows = tblParcelas.getSelectedData();
    let total = 0;
    rows.map((row) => {
        total += parseFloat(row.valor);
    });
    var formattedTotal = formataDecimal(total, 2);
    $("#valorbaixasel").val(formattedTotal);
}

$("#btnGravarParcelas").on("click", () => {
    validarAdicionarParcelas();
});

function validarAdicionarParcelas() {
    if (!$("#contamovimentotipobaixa_id").val()) {
        bootbox.alert("Informe o tipo de recebimento");
        return;
    }
    const rows = tblParcelas.getSelectedData();
    let total = 0;
    rows.map((row) => {
        total += parseFloat(row.valor);
    });
    if (total != rowDataBaixaOfx.amount) {
        const msg = `O valor das parcelas selecionadas (${formataDecimal(total, 2)}) é ${total < rowDataBaixaOfx.amount ? "menor" : "maior"} que o valor do extrato (${formataDecimal(rowDataBaixaOfx.amount, 2)}). A diferença (${formataDecimal(Math.abs(total - rowDataBaixaOfx.amount), 2)}) será lançada como ${total < rowDataBaixaOfx.amount ? "juros" : "desconto"}. Deseja continuar?`;
        bootbox.confirm({
            title: "Atenção!",
            className: "dontHideEsc",
            message: msg,
            buttons: {
                confirm: {
                    label: "Sim",
                    className: "btn-nw-registro",
                },
                cancel: {
                    label: "Não",
                    className: "btn-nw-geral",
                },
            },
            backdrop: true,
            closeButton: false,
            callback: function (res) {
                if (res) {
                    adicionarParcelas();
                }
            },
        });
    } else {
        adicionarParcelas();
    }
}

function adicionarParcelas() {
    let erro = false;
    const rows = tblLancamentos.getData();
    rows.map((row) => {
        if (row.uniqueid.toUpperCase() == rowDataBaixaOfx.uniqueid.trim().toUpperCase()) {
            erro = true;
        }
    });
    if (erro) {
        bootbox.alert(
            "Esse lançamento do extrato já está na tabela de lançamentos a serem efetivados no sistema. Se necessário, remova e inclua novamente.",
        );
        return;
    }
    tblParcelas.getSelectedData().map((rw) => {
        if (ids_baixa.includes(rw.financeiroparcela_id)) {
            erro = true;
        }
    });
    if (erro) {
        bootbox.alert(
            "Esse titúlo já está na tabela de lançamentos a serem efetivados no sistema. Se necessário, remova e inclua novamente.",
        );
        return;
    }

    const record = {
        acao: "baixar",
        date: rowDataBaixaOfx.date,
        amount: rowDataBaixaOfx.amount,
        uniqueid: rowDataBaixaOfx.uniqueid,
        memo: rowDataBaixaOfx.memo,
        dadosbaixa: tblParcelas.getSelectedData(),
        contamovimentotipo_id: $("#contamovimentotipobaixa_id").val(),
    };
    tblLancamentos.addRow(record);
    let rowsE = tblExtrato.getRows("all");
    rowsE.forEach(function (row) {
        var data = row.getData();
        if (data.uniqueid === rowDataBaixaOfx.uniqueid) {
            row.update({ baixar: true, actions: "" });
        }
    });
    $("#baixar_modal").modal("hide");
    tblParcelas.getSelectedData().map((rw) => {
        ids_baixa.push(rw.financeiroparcela_id);
    });
}

function validarAdicionarParcela(dadosbaixa) {
    let erro = false;
    const rows = tblLancamentos.getData();
    rows.map((row) => {
        if (row.uniqueid.toUpperCase() == rowDataBaixaOfx.uniqueid.trim().toUpperCase()) {
            erro = true;
        }
    });
    if (erro) {
        bootbox.alert(
            "Esse lançamento do extrato já está na tabela de lançamentos a serem efetivados no sistema. Se necessário, remova e inclua novamente.",
        );
        return;
    }
    if (ids_baixa.includes(dadosbaixa.financeiroparcela_id)) {
        erro = true;
    }
    if (erro) {
        bootbox.alert(
            "Esse titúlo já está na tabela de lançamentos a serem efetivados no sistema. Se necessário, remova e inclua novamente.",
        );
        return;
    }
    bootbox.prompt({
        title: "Selecione o tipo de recebimento",
        inputType: "select",
        inputOptions: contamovimentotipos,
        callback: function (result) {
            if (result) {
                adicionarParcela(dadosbaixa, result);
            }
        },
    });
}

function adicionarParcela(dadosbaixa, contamovimentotipo_id) {
    const record = {
        acao: "baixar",
        date: rowDataBaixaOfx.date,
        amount: rowDataBaixaOfx.amount,
        uniqueid: rowDataBaixaOfx.uniqueid,
        memo: rowDataBaixaOfx.memo,
        dadosbaixa: [dadosbaixa],
        contamovimentotipo_id: contamovimentotipo_id,
    };
    tblLancamentos.addRow(record);
    let rowsE = tblExtrato.getRows("all");
    rowsE.forEach(function (row) {
        var data = row.getData();
        if (data.uniqueid === rowDataBaixaOfx.uniqueid) {
            row.update({ baixar: true, actions: "" });
        }
    });
    ids_baixa.push(dadosbaixa.financeiroparcela_id);
    tblExtrato.redraw(true);
}

function cancelarLancamento(cell) {
    let row = cell.getRow();
    let rowData = row.getData();

    let rowsE = tblExtrato.getRows("all");
    rowsE.forEach(function (row) {
        let data = row.getData();
        if (data.uniqueid === rowData.uniqueid) {
            row.update({ baixar: false, lancar: false, actions: "<i class='fa fa-bars'></i>" });
        }
    });
    if (rowData.acao == "baixar") {
        rowData.dadosbaixa.map((bx) => {
            ids_baixa = ids_baixa.filter((item) => item !== bx.financeiroparcela_id);
        });
    }
    row.delete();
    tblExtrato.redraw(true);
}

function consultarLancamento(cell) {
    let row = cell.getRow();
    let rowData = row.getData();
    if (rowData.acao == "lancar") {
        const { dadosbaixa } = rowData;
        const selectizeInstance = $("#searchboxClienteLancamento")[0].selectize;
        selectizeInstance.clear();
        selectizeInstance.clearOptions();
        selectizeInstance.addOption([{ id: dadosbaixa.cliente_id, nome: dadosbaixa.nome, class: "cliente" }]);
        selectizeInstance.refreshOptions(true);
        selectizeInstance.refreshItems();
        selectizeInstance.addItem(dadosbaixa.cliente_id);
        selectizeInstance.disable();

        $(".lancamentolancar").show();
        $(".lancamentotransferir").hide();
        $("#h4TituloLancamento").html("Consultar Movimento no Caixa");

        $("#datalancamento").val(rowData.date);
        $("#datalancamento_f").val(requestDataOracle(rowData.date, false, false, true));
        $("#valorlancamento").val(rowData.amount);
        $("#valorlancamento_f").val(formataDecimal(rowData.amount, 2));
        $("#descricaolancamento").val(rowData.memo);
        $("#uniqueidlancamento").val(rowData.uniqueid);
        $("#btnGravarLancamento").prop("disabled", true);
        $("#condicaopagamentolancamento_id").val(dadosbaixa.condicaopagamento_id);
        $("#condicaopagamentolancamento_id").prop("disabled", true).trigger("chosen:updated");
        $("#contamovimentotipolancamento_id").val(dadosbaixa.contamovimentotipo_id);
        $("#contamovimentotipolancamento_id").prop("disabled", true).trigger("chosen:updated");
        $("#pclancamento_descricao").val(dadosbaixa.pcdescricao);
        $("#btnPclancamento").prop("disabled", true);
        $("#cclancamento_descricao").val(dadosbaixa.ccdescricao);
        $("#btnCclancamento").prop("disabled", true);
        $("#lancamento_modal").modal("show");
    } else if (rowData.acao == "baixar") {
        if (tblParcelas != undefined) tblParcelas.clearData();
        $("#btnGravarParcelas").hide();
        $("#descricaobaixa").val(rowData.memo);
        $("#uniqueidbaixa").val(rowData.uniqueid);
        $("#databaixa_f").val(requestDataOracle(rowData.date, false, false, true));
        $("#h4TituloBaixa").html("Consultar Título(s) a serem baixado(s)");
        $(".divAddBaixa").hide();
        setTimeout(() => {
            renderTabelaParcelas(rowData.dadosbaixa);
        }, 200);
        $("#btnGravarParcelas").prop("disabled", true);
        $("#valorbaixaofx").val(formataDecimal(rowData.amount, 2));
        $("#contamovimentotipobaixa_id").val(rowData.contamovimentotipo_id);
        $("#contamovimentotipobaixa_id").prop("disabled", true).trigger("chosen:updated");
        let total = 0;
        rowData.dadosbaixa.map((row) => {
            total += parseFloat(row.valor);
        });
        var formattedTotal = formataDecimal(total, 2);
        $("#valorbaixasel").val(formattedTotal);
        $("#baixar_modal").modal("show");
    }
}

$("#btnAtualizarLancamentos").on("click", () => {
    bootbox.confirm({
        title: "Atenção!",
        className: "dontHideEsc",
        message: "Deseja atualizar todos os registros no financeiro?",
        buttons: {
            confirm: {
                label: "Sim",
                className: "btn-nw-registro",
            },
            cancel: {
                label: "Não",
                className: "btn-nw-geral",
            },
        },
        backdrop: true,
        closeButton: false,
        callback: function (res) {
            if (res) {
                renderAtualizarLancamentos();
            }
        },
    });
});

function renderAtualizarLancamentos() {
    showLoaderAjax("Aguarde", "Processando lançamentos", false);
    setTimeout(() => {
        atualizarLancamentos();
    }, 500);
}

function atualizarLancamentos() {
    var formData = new FormData();
    formData.append("conta_id", conta_id);
    const rowsL = tblLancamentos.getRows("all");
    let dados = [];
    rowsL.forEach(function (row) {
        dados.push(row.getData());
    });
    formData.append("lancamentos", JSON.stringify(dados));
    const url = root + "/importExtrato.update";
    $.ajax({
        type: "POST",
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
        url: url,
        data: formData,
        async: false,
        success: function (data) {
            hideLoaderAjax();
            if (typeof data === "object")
                if (data.status == "OK") {
                    bootbox.alert("Registros atualizados com sucesso", function () {
                        renderEnviarExtrato();
                    });
                } else {
                    bootbox.alert(data.msg);
                }
            else if (typeof data === "string") bootbox.alert("Erro: " + data);
            else bootbox.alert("Erro desconhecido");
        },
        error: function (data) {
            hideLoaderAjax();
            bootbox.alert("Erro ao atualizar");
        },
        cache: false,
        contentType: false,
        processData: false,
    });
}
