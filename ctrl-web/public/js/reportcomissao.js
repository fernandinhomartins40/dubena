$("#btnFiltroComissao").click(function(){
    var url = root + '/report.comissoespdf?ton=0&datainicio=:datainicio&datafim=:datafim&colaborador=:col&tipo=1&resumo=:resumo';
    redirect(url,true);
});

$("#gerarPdfComissao").click(function(){
    var url = root + '/report.comissoespdf?ton=0&datainicio=:datainicio&datafim=:datafim&colaborador=:col&tipo=2&resumo=:resumo';
    redirect(url,false);
});

function redirect(url,modal){
    var datainicio = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());
    var colaborador = $("#colaborador_id").val() == "" ? 0 : $("#colaborador_id").val();
    var resumo = $("#resumo").is(':checked') ? "1" : "0";

    var url = url.replace(':datainicio',datainicio);
    var url = url.replace(':datafim',datafim);
    var url = url.replace(':col',colaborador);
    var url = url.replace(':resumo',resumo);
    if(modal){
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    }else{
        window.open(url, '_blank');
    }
}

$("#btnFiltroComissaoTon").click(function(){
    var url = root + '/report.comissoespdf?ton=1&datainicio=:datainicio&datafim=:datafim&colaborador=:col&tipo=1&resumo=:resumo';
    redirectTon(url,true);
});

$("#gerarPdfComissaoTon").click(function(){
    var url = root + '/report.comissoespdf?ton=1&datainicio=:datainicio&datafim=:datafim&colaborador=:col&tipo=2&resumo=:resumo';
    redirectTon(url,false);
});

function redirectTon(url,modal){
    var datainicio = insertDataOracle($("#datainicioton").val());
    var datafim = insertDataOracle($("#datafimton").val());
    var colaborador = $("#colaborador_idton").val() == "" ? 0 : $("#colaborador_idton").val();
    var resumo = $("#resumoton").is(':checked') ? "1" : "0";

    var url = url.replace(':datainicio',datainicio);
    var url = url.replace(':datafim',datafim);
    var url = url.replace(':col',colaborador);
    var url = url.replace(':resumo',resumo);
    if(modal){
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    }else{
        window.open(url, '_blank');
    }
}