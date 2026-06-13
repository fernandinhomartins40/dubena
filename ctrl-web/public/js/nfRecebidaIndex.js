$('#btnFiltros').on('click', function () {
    var urlFiltro = root + '/nfrecebida?dataI=:dataInicial&dataF=:dataFinal&cliente_id=:cliente_id&tipolancamento=:tipolancamento';
    var dataInicial = $("#dataInicial").val();
    var dataFinal = $("#dataFinal").val();
    var cliente_id = $("#cliente_id").intVal();
    var nomecliente = $("#nomecliente").val();
    var tipolancamento = $("#tipolancamento").val();

    if (isEmpty(nomecliente)) {
        cliente_id = 0;
    }

    dataInicial = insertDataOracle(dataInicial);
    dataFinal = insertDataOracle(dataFinal);
    urlFiltro = urlFiltro.replace(':dataInicial', dataInicial);
    urlFiltro = urlFiltro.replace(':dataFinal', dataFinal);
    urlFiltro = urlFiltro.replace(':cliente_id', cliente_id);
    urlFiltro = urlFiltro.replace(':tipolancamento', tipolancamento);
    window.location.href = urlFiltro;
});

$("#modalInutilizar").on("shown.bs.modal", function () {
    setTimeout(function () {
        $("#nfmodelo").focus();
    }, 500);
});

$("#btnInutilizar").on("click", function () {
    $("#modalInutilizar").modal("show");
});

$("#fmInut").on("submit", function (e) {
    e.preventDefault();
    let msg = "";
    let baseMsg = "O campo :replace é obrigatório.";
    if ($("#nfmodelo").isEmpty()) {
        msg = baseMsg.replace(":replace", "Modelo");
    } else if ($("#nfserie").isEmpty()) {
        msg = baseMsg.replace(":replace", "Série");
    } else if ($("#nini").isEmpty()) {
        msg = baseMsg.replace(":replace", "Num Inicial");
    } else if ($("#nfim").isEmpty()) {
        msg = baseMsg.replace(":replace", "Num Final");
    } else if ($("#xjust").isEmpty()) {
        msg = baseMsg.replace(":replace", "Motivo");
    }
    if (msg) {
        bootbox.alert(msg);
        return;
    }
    let formData = new FormData($(this)[0]);
    ajaxGenerator(root + "/nfrecebida.inutilizar", "POST", function (result) {
        if (result === "OK|") {
            bootbox.alert("Inutilização realizada com sucesso!", () => location.reload());
        } else {
            bootbox.alert(result);
        }
    }, null, formData);
});

$(document).ready(function () {
    var urlAtual = $(location).attr('href');
    $("#rotaIndex").text(urlAtual);

    var cliente_id = $("#cliente_id").val();
    var clientenome = $("#clientenome").val();
    if (cliente_id > 0) {
        var select = $("#nomecliente").selectize()[0].selectize;
        select.clearOptions();
        select.addOption([{
            nome: clientenome,
            rua: {descricao: ''},
            numero: '',
            bairro: {descricao: ''},
            cidade: {descricao: ''},
            id: cliente_id}]);
        select.refreshItems();
        select.addItem(cliente_id);
        $("#cliente_nome_erro").val(clientenome);
        $("#cliente_id_erro").val(cliente_id);
        $("#dataInicial").focus();
    }
    $("#tipolancamento").on('change', changeTipoLancamento);
    changeTipoLancamento();
});

function changeTipoLancamento() {
    let tipoLancamento = $("#tipolancamento").intVal();
    let description = "";
    switch (tipoLancamento) {
        case 0:
            description = "Destinatário";
            break;
        case 1:
            description = "Emitente";
            break;
        default:
            description = "Emitente/Destinatário";
    }
    $("label[for='cliente_id']").text(description + ":");
    $(".selectize-input input").attr("placeholder", "Buscar " + description);
}

$("#nomecliente").selectize({
    valueField: "id",
    labelField: "nome",
    searchField: ["nome"],
    maxOptions: 10,
    hideSelected: true,
    options: [],
    create: false,
    render: {
        option: function (item, escape) {
            if (typeof item.nomecompleto === "undefined")
                return;
            var dataSplited = item.nomecompleto.split('||');
            var name = typeof dataSplited[0] === "undefined" ? "" : dataSplited[0];
            var fantasia = typeof dataSplited[1] === "undefined" ? "" : dataSplited[1];
            fantasia = isEmpty(fantasia) ? "" : " - " + fantasia;
            return "<div><b>" + escape(name) + "</b>" + escape(fantasia) + "</div>";
        }
    },
    optgroups: [{
        value: "cliente",
        label: "Clientes"
    }],
    optgroupField: "class",
    optgroupOrder: ["cliente"],
    load: function (query, callback) {
        var clientefornecedor;
        switch ($("#tipolancamento").intVal()) {
            case 0:
                clientefornecedor = "C";
                break;
            case 1:
                clientefornecedor = "F";
                break;
            default:
                clientefornecedor = "A";
        }
        var select = $("#nomecliente").selectize()[0].selectize;
        select.clearOptions();
        if (!query.length)
            return callback();
        $.ajax({
            url: root + "/api/searchClienteNF",
            type: "GET",
            dataType: "json",
            data: {
                q: query,
                mode: "R",
                empresa_id: $("#empresa_documento").intVal(),
                clientefornecedor: clientefornecedor
            },
            error: function (data) {
                console.log(data);
                callback();
            },
            success: function (res) {
                buscaSelectize = true;
                callback(res.data);
            }
        });
    },
    onChange: function () {
        if (typeof buscaSelectize !== "undefined" && buscaSelectize !== false) {
            let selectC = $("#nomecliente").selectize()[0].selectize;
            if (typeof selectC.getItem(this.items[0]).context === "object") {
                $("#cliente_nome_erro").val(selectC.getItem(this.items[0]).context.innerText);
                $("#cliente_id_erro").val(selectC.getValue());
                $("#cliente_id").val(selectC.getValue());
            }
            buscaSelectize = false;
        }
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
    onDropdownOpen: function ($dropdown) {
        $dropdown.css('visibility', this.lastQuery != null && this.lastQuery.length ? 'visible' : 'hidden');
    }
});

function printRegister(nf_id){
    var url = root + '/report.nfrecebidacomprovantepdf?id=' + nf_id;
    redirect(url,false);
}

function redirect(url,modal){
    if(modal){
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    }else{
        window.open(url, '_blank');
    }
}