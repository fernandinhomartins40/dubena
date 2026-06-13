////////// Colaborador Malote
$("#btnFiltraMalote").click(function () {
    var url = root + '/report.colaboradormalotepdf?datainicio=:datainicio&datafim=:datafim&colaborador=:colaborador&iframe=0';
    redirect(url,false);
});

$("#btnIframe").click(function(){
    var url = root + '/report.colaboradormalotepdf?datainicio=:datainicio&datafim=:datafim&colaborador=:colaborador&iframe=1';
    redirect(url,true);
});

/////////// Vendas Direta
$("#btnFiltroDireta").click(function () {
    var url = root + '/report.vendadiretapdf?datainicio=:datainicio&datafim=:datafim&setor=:setor&tipo=1';
    redirectDireta(url, true);
});

$("#gerarPdfDireta").click(function () {
    var url = root + '/report.vendadiretapdf?datainicio=:datainicio&datafim=:datafim&setor=:setor&tipo=2';
    redirectDireta(url, false);
});

////////// Vendas por Operações
$("#btnFiltroOperacoes").click(function(){
    var url = root + '/report.vendaoperacoespdf?datainicio=:datainicio&datafim=:datafim&operacao=:op&segmento=:segmento&produto=:produto&tipo=1';
    var operacao = $("#operacoes").val();
    var segmento = $("#segmento_id").val();
    if(!isEmpty(operacao) && !isEmpty(segmento)){
        redirectOperacoes(url,true);
    }else{
        bootbox.alert('Por favor, selecione uma operação e um segmento!');
    }
});

$("#gerarPdfOperacoes").click(function(){
    var url = root + '/report.vendaoperacoespdf?datainicio=:datainicio&datafim=:datafim&operacao=:op&segmento=:segmento&produto=:produto&tipo=2';
    var operacao = $("#operacoes").val();
    if(!isEmpty(operacao)){
        redirectOperacoes(url,false);
    }else{
        bootbox.alert('Por favor, selecione uma operação!');
    }
});
///

function redirect(url,modal) {
    var datainicio = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());
    var colaborador = $("#colaborador_id").val() == "" ? 0 : $("#colaborador_id").val();
    var url = url.replace(':datainicio', datainicio);
    var url = url.replace(':datafim', datafim);
    var url = url.replace(':colaborador', colaborador);
    if(modal){
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    }else{
        window.open(url, '_blank');
    }
}

function redirectDireta(url, modal) {
    var datainicio = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());
    var setor = $("#setor_id").val() == "" ? 0 : $("#setor_id").val();
    var url = url.replace(':datainicio', datainicio);
    var url = url.replace(':datafim', datafim);
    var url = url.replace(':setor', setor);
    if (modal) {
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    } else {
        window.open(url, '_blank');
    }
}

function redirectOperacoes(url,modal){
    var datainicio = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());
    var operacao = $("#operacoes").val();
    var segmento = $("#segmento_id").val() == "" ? 0 : $("#segmento_id").val();
    var produtos = $("#produto_id").val() == null || $("#produto_id").val() == "null" ? 0 : $("#produto_id").val();

    var url = url.replace(':datainicio',datainicio);
    var url = url.replace(':datafim',datafim);
    var url = url.replace(':op',operacao);
    var url = url.replace(':segmento',segmento);
    var url = url.replace(':produto',produtos);
    if(modal){
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    }else{
        window.open(url, '_blank');
    }
}