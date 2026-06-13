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
        {value: "cliente", label: "Emitentes"}
        ],
        optgroupField: "class",
        optgroupOrder: ["cliente"],
        load: function (query, callback) {
            var select = $("#cliente_id").selectize()[0].selectize;
            select.clearOptions();
            var url = root + "/api/searchClienteNF?q=" + query+'&clientefornecedor=F&mode=R';
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
    filtrar('emitidas');
});

$("#btnLimpar").click(function(){
    limparCampos('recebidas');
});

$("#btnIframePr").click(function(){
    filtrar('produtos');
});

$("#btnLimparPr").click(function(){
    limparCampos('produtos');
});

var filtrar = function(which){
    var url = root + "/report.nfentradas.pdf?datainicio=:inicio&datafim=:fim&operacao=:ope";

    if(which == 'emitidas'){
        var datainicio = insertDataOracle($("#datainicio").val());
        var datafim = insertDataOracle($("#datafim").val());
        var emitente = $("#cliente_id").isEmpty() ? '0' : $("#cliente_id").val();
        var operacao = $("#nfoperacao").isEmpty() ? '0' : $("#nfoperacao").val();
        var order = $("#ordem:checked").val();

        url += `&emitente=${emitente}&ordem=${order}&hub=1`;
    }else{
        var datainicio = insertDataOracle($("#datainicio_pr").val());
        var datafim = insertDataOracle($("#datafim_pr").val());
        var operacao = $("#nfoperacao_pr").isEmpty() ? '0' : $("#nfoperacao_pr").val();
        var produto = $("#produto_id").isEmpty() ? '0' : $("#produto_id").val();
        
        url += `&produto=${produto}&hub=2`;
    }

    var url = url.replace(':inicio',datainicio);
    var url = url.replace(':fim',datafim);
    var url = url.replace(':ope',operacao);
    openModalReport(url,true);
};
function limparCampos(which) {
    if(which == 'recebidas'){
        $("#datainicio").val(dataAtual());
        $("#datafim").val(dataAtual());
        var select = $("#cliente_id").selectize()[0].selectize;
        select.clearOptions();
        $("#ordem").val('D').prop('checked', true);
        $("#nfoperacao").val('').trigger('chosen:updated');
    }else{
        $("#datainicio_pr").val(dataAtual());
        $("#datafim_pr").val(dataAtual());
        $("#nfoperacao_pr").val('').trigger('chosen:updated');
        $("#produto_id").val('').trigger('chosen:updated');
    }
}
