jQuery(document).ready(function ($) {
    tblExtratoconfig = $("#tblExtratoconfig").DataTable({
        language: { url: urlLanguage },
        processing: false,
        bPaginate: false,
        bLengthChange: false,
        bFilter: false,
        bSort: true,
        order: [[1, "desc"]],
        bInfo: false,
        bAutoWidth: false,
        columnDefs: [
            {
                targets: [0, 2, 4, 6, 8, 10, 12, 14],
                visible: false,
            },
        ],
    });
    searchboxCliente();
    setTimeout(function () {
        if (show) {
            desativarInputs();
            var ids = [".btn-danger", ".btn-nw-registro", ".btnEditarExtratoconfig", "#btnAddExtratoconfig"];
            desativarInputsEspecificos(ids);
        }
    }, $(document).ready());
});

$("#btnGravarExtratoconfig").on("click", () => {
    validarExtratoconfig();
});

function removerExtratoconfig(id, descricao) {
    bootbox.confirm({
        title: "Atenção!",
        className: "dontHideEsc",
        message: "Deseja remover essa configuração (" + descricao + ")?",
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
                operacaoExtratoconfig = "DEL";
                var formData = new FormData();
                formData.append("extratoconfig_id", id);
                formData.append("operacao", operacaoExtratoconfig);
                formData.append("conta_id", conta_id);
                gravarExtratoconfig(formData);
            }
        },
    });
}

function addExtratoconfig() {
    operacaoExtratoconfig = "ADD";
    $("#extratoconfig_id").val("");
    $("#descricaoextratoconfig").val("");
    $("#condicaopagamentoextratoconfig_id").val("").trigger("chosen:updated");
    $("#contamovimentotipoextratoconfig_id").val("").trigger("chosen:updated");
    $("#pcextratoconfig_id").val("");
    $("#ccextratoconfig_id").val("");
    $("#pcextratoconfig_descricao").val("");
    $("#ccextratoconfig_descricao").val("");
    $(".extratoconfiglancar").hide();
    $(".extratoconfigtransferir").hide();
    $("#acaoextratoconfig").val("").trigger("chosen:updated");
    $("#contaextratoconfig_id").val("");
    const selectizeInstance = $("#searchboxClienteExtratoconfig")[0].selectize;
    selectizeInstance.clear();
    selectizeInstance.clearOptions();
    $("#extratoconfig_modal").modal("show");
}

function validarExtratoconfig() {
    let acao = $("#acaoextratoconfig").val();
    if (acao.trim() == "") {
        bootbox.alert("Preencha o tipo de ação.");
        return;
    }
    if ($("#descricaoextratoconfig").val().trim() == "") {
        bootbox.alert("Preencha o texto do extrato.");
        return;
    }

    let cliente = $("#searchboxClienteExtratoconfig").selectize()[0].selectize.getValue();
    if (acao == extratoconfigLancar || acao == extratoacaolancarbaixar) {
        if ($("#condicaopagamentoextratoconfig_id").val().trim() == "") {
            bootbox.alert("Preencha a condição de pagamento.");
            return;
        }
        if ($("#contamovimentotipoextratoconfig_id").val().trim() == "") {
            bootbox.alert("Preencha o tipo de movimento.");
            return;
        }
        // if(!cliente){
        //     bootbox.alert('Preencha o cliente.');
        //     return;
        // }
        if ($("#pcextratoconfig_id").val().trim() == "") {
            bootbox.alert("Preencha o plano de contas.");
            return;
        }
        if ($("#ccextratoconfig_id").val().trim() == "") {
            bootbox.alert("Preencha o centro de custos.");
            return;
        }
    } else if (acao == extratoconfigTransferir) {
        if ($("#contaextratoconfig_id").val().trim() == "") {
            bootbox.alert("Preencha a conta origem/destino.");
            return;
        }
    }
    let erro = false;
    tblExtratoconfig.rows().every(function () {
        var d = this.data();
        if (
            d[1].toUpperCase() == $("#descricaoextratoconfig").val().trim().toUpperCase() &&
            (d[0] != $("#extratoconfig_id").val() || $("#extratoconfig_id").val() == "")
        ) {
            erro = true;
        }
    });
    if (erro) {
        bootbox.alert("Essa descrição já existe");
        return;
    }
    var formData = new FormData();
    formData.append("extratoconfig_id", $("#extratoconfig_id").val());
    formData.append("descricao", $("#descricaoextratoconfig").val());
    formData.append("condicaopagamento_id", $("#condicaopagamentoextratoconfig_id").val());
    formData.append("contamovimentotipo_id", $("#contamovimentotipoextratoconfig_id").val());
    formData.append("pc_id", $("#pcextratoconfig_id").val());
    formData.append("cc_id", $("#ccextratoconfig_id").val());
    formData.append("cliente_id", cliente);
    formData.append("operacao", operacaoExtratoconfig);
    formData.append("conta_id", conta_id);
    formData.append("acao_id", $("#acaoextratoconfig").val());
    formData.append("contaorigem_id", $("#contaextratoconfig_id").val());
    gravarExtratoconfig(formData);
}

function editarExtratoconfig(id) {
    let ver = null;
    tblExtratoconfig.rows().every(function () {
        var d = this.data();
        if (d[0] == id) {
            ver = d;
        }
    });
    if (!ver) {
        bootbox.alert("Registro de configuração não encontrado");
        return;
    }
    operacaoExtratoconfig = "UPD";

    $("#extratoconfig_id").val(ver[0]);
    $("#descricaoextratoconfig").val(ver[1]);
    $("#acaoextratoconfig").val(ver[2]).trigger("chosen:updated").trigger("change");
    $("#condicaopagamentoextratoconfig_id").val(ver[6]).trigger("chosen:updated");
    $("#contamovimentotipoextratoconfig_id").val(ver[12]).trigger("chosen:updated");
    $("#pcextratoconfig_id").val(ver[8]);
    $("#ccextratoconfig_id").val(ver[10]);
    $("#pcextratoconfig_descricao").val(ver[9]);
    $("#ccextratoconfig_descricao").val(ver[11]);

    const selectizeInstance = $("#searchboxClienteExtratoconfig")[0].selectize;
    selectizeInstance.clear();
    selectizeInstance.clearOptions();
    selectizeInstance.addOption([{ id: ver[4], nome: ver[5], class: "cliente" }]);
    selectizeInstance.refreshOptions(true);
    selectizeInstance.refreshItems();
    selectizeInstance.addItem(ver[4]);
    $("#extratoconfig_modal").modal("show");
}

function gravarExtratoconfig(formData) {
    var url = root + "/conta.extratoconfig";
    $.ajax({
        type: "POST",
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
        url: url,
        data: formData,
        async: false,
        success: function (data) {
            if (typeof data === "object")
                if (data.status == "OK") {
                    const configs = data.data;
                    tblExtratoconfig.clear().draw(false);
                    configs.map((v) => {
                        tblExtratoconfig.row
                            .add([
                                v.id,
                                v.descricao,
                                v.acao,
                                acoes[v.acao],
                                v.cliente_id,
                                v.cliente ? v.cliente.nome : null,
                                v.condicaopagamento_id,
                                v.condicao_pagamento ? v.condicao_pagamento.descricao : null,
                                v.planoconta_id,
                                v.plano_conta ? v.plano_conta.descricao : null,
                                v.centrocusto_id,
                                v.centro_custo ? v.centro_custo.descricao : null,
                                v.contamovimentotipo_id,
                                v.conta_movimento_tipo ? v.conta_movimento_tipo.descricao : null,
                                v.contaorigem_id,
                                v.conta_origem ? v.conta_origem.descricao : null,
                                `
                                <button type='button' onclick="editarExtratoconfig(${v.id})" class='btnEditarExtratoconfig btn btn-nw-geral btn-xs' id='btnEditarExtratoconfig'><span class="fa fa-pencil-square-o fa-lg" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar Configuração"></span></button>
                                <button type='button' onclick="removerExtratoconfig(${v.id}, '${v.descricao}')" class='btn btn-nw-registro btn-xs' id='btnRemoverExtratoconfig' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover Configuração"><span class="fa fa-trash fa-lg"></span></button>
                                `,
                            ])
                            .draw(false);
                    });
                    $("#extratoconfig_id").val("");
                    $("#descricaoextratoconfig").val("");
                    $("#condicaopagamentoextratoconfig_id").val("").trigger("chosen:updated");
                    $("#contamovimentotipoextratoconfig_id").val("").trigger("chosen:updated");
                    $("#pcextratoconfig_id").val("");
                    $("#ccextratoconfig_id").val("");
                    $("#pcextratoconfig_descricao").val("");
                    $("#ccextratoconfig_descricao").val("");
                    const selectizeInstance = $("#searchboxClienteExtratoconfig")[0].selectize;
                    selectizeInstance.clear();
                    selectizeInstance.clearOptions();
                    $("#extratoconfig_modal").modal("hide");
                } else {
                    bootbox.alert(data.msg);
                }
            else if (typeof data === "string") bootbox.alert("Erro: " + data);
            else bootbox.alert("Erro desconhecido");
        },
        error: function (data) {
            hideLoaderAjax();
            bootbox.alert("Erro ao gravar a configuração");
        },
        cache: false,
        contentType: false,
        processData: false,
    });
}

function searchboxCliente() {
    $("#searchboxClienteExtratoconfig").selectize({
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
                url: root + "/api/searchClientes",
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

$("#acaoextratoconfig").on("change", () => {
    let acao = $("#acaoextratoconfig").val();
    if (acao == extratoconfigLancar || acao == extratoacaolancarbaixar) {
        $(".extratoconfiglancar").show();
        $(".extratoconfigtransferir").hide();
    } else {
        $(".extratoconfiglancar").hide();
        if (acao == extratoconfigTransferir) {
            $(".extratoconfigtransferir").show();
        } else {
            $(".extratoconfigtransferir").hide();
        }
    }
});
