$(document).ready(function() {
    $("#cliente_id").selectize({
        valueField: "id",
        labelField: "nome",
        searchField: ["nome"],
        maxOptions: 10,
        hideSelected: true,
        options: [],
        create: false,
        render: {
            option: function (item, escape) {
                return "<div>" + escape(item.nome)+"</div>";
            }
        },
        optgroups: [
            {value: "cliente", label: "Emitente/Destinatário"}
        ],
        optgroupField: "class",
        optgroupOrder: ["cliente"],
        load: function (query, callback) {
            var select = $("#cliente_id").selectize()[0].selectize;
            select.clearOptions();
            var url = root + "/api/searchgeralreportnf?query=" + query;
            ajaxGenerator(url, 'GET', function (res) {
                callback(res.data);
            }, function (data) {
                console.error(data);
                callback();
            })
        },
        onChange: function (data) {
            var select = $("#cliente_id").selectize()[0].selectize;
            if (typeof select.getItem(this.items[0]).context === "object") {
                cliente_id = select.getValue();
            } else {
                cliente_id = '';
            }
        }
    });
});

$("#btnIframe").click(function(){
    getInputs('emitida');
});

$("#btnIframePr").click(function(){
    getInputs('produtos');
});

$("#btnLimpar").click(function(){
    limparCampos('emitidas');
});

$("#btnLimparPr").click(function(){
    limparCampos('produtos');
});

$("#situacao").change(function (){
    if ($(this).intVal() === 102) {
        $("[name='ordem']").prop("disabled", true).val('D').prop('checked', true);
        $("#nfoperacao").prop("disabled", true).trigger("chosen:updated");
    } else {
        $("[name='ordem']").prop("disabled", false);
        $("#nfoperacao").prop("disabled", false).trigger("chosen:updated");
    }
});

var getInputs = function(which){
    var alertS = "Por favor, selecione a situação da NF.";

    var url = root + '/report.nfemitidas.pdf?datainicio=:inicio&datafim=:fim&operacao=:ope';
    if(which == 'emitida'){
        //emitida
        var datainicio = $("#datainicio").val();
        var datafim = $("#datafim").val();
        var operacao = $("#nfoperacao").isEmpty() ? '0' : $("#nfoperacao").val();
        var cliente = $("#cliente_id").isEmpty() ? '0' : $("#cliente_id").val();
        if($("#situacao").isEmpty()){
            bootbox.alert(alertS);
            return;
        }
        var situacao = $("#situacao").val();
        var ordem = $("#ordem:checked").val();
        var modelosA = $("#nfmodelos").val();
        let modelos;
        if (modelosA !== null) {
            modelos = modelosA.join(", ");
        } else {
            modelos = "";
        }
        url += `&cliente=${cliente}&situacao=${situacao}&ordem=${ordem}&modelos=${modelos}&hub=1`;
    }else{
        // produto
        var datainicio = $("#datainicio_pr").val();
        var datafim = $("#datafim_pr").val();
        var operacao = $("#nfoperacao_pr").isEmpty() ? "0" : $("#nfoperacao_pr").val();
        var produto = $("#produto_id").isEmpty() ? "0" : $("#produto_id").val();
        url += `&produto=${produto}&hub=2`;
    }
    var url = url.replace(':inicio',insertDataOracle(datainicio));
    var url = url.replace(':fim',insertDataOracle(datafim));
    var url = url.replace(':ope',operacao);

    openModalReport(url,true);
};

function limparCampos(which) {
    if(which == 'emitidas'){
        $("#datainicio").val(dataAtual());
        $("#datafim").val(dataAtual());
        var select = $("#cliente_id").selectize()[0].selectize;
        select.clearOptions();
        $("#nfmodelos").val('').trigger("chosen:updated");
        $("#ordem").val('D').prop('checked', true);
        $("#situacao").val('').trigger('chosen:updated');
        $("#nfoperacao").val('').trigger('chosen:updated');
    }else{
        $("#datainicio_pr").val(dataAtual());
        $("#datafim_pr").val(dataAtual());
        $("#nfoperacao_pr").val('').trigger('chosen:updated');
        $("#produto_id").val('').trigger('chosen:updated');
    }
}
