$("#btnFiltroMovimentacao").click(function(){
    var url = root + '/report.movimentacoes.filtro?datainicio=:datainicio&datafim=:datafim&setor=:setor&produto=:prod&filtro=:filtro&tipo=1';
    redirect(url,true);
});

$("#gerarPdfMovimentacao").click(function(){
    var url = root + '/report.movimentacoes.filtro?datainicio=:datainicio&datafim=:datafim&setor=:setor&produto=:prod&filtro=:filtro&tipo=2';
    redirect(url,false);
});

function redirect(url,modal){
    var res_produtos = $("#produto_id").val() != "";
    var res_setor = $("#setor_id").val() != "";
    if(res_produtos && res_setor){
        var datainicio = insertDataOracle($("#datainicio").val());
        var datafim = insertDataOracle($("#datafim").val());
        var setor = $("#setor_id").val();
        var produto = $("#produto_id").val();
        var filtro = $("#filtro:checked").val();

        var url = url.replace(':datainicio',datainicio);
        var url = url.replace(':datafim',datafim);
        var url = url.replace(':setor',setor);
        var url = url.replace(':prod',produto);
        var url = url.replace(':filtro',filtro);
        openModalReport(url,modal);
    }else{
        bootbox.alert('Setor e produto são obrigatórios.');
    }
}