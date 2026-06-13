$(document).ready(function(){
    if(window.location.search != ""){
        correcoesParametros();
    }
});
//////////Abastecimento
$("#btnFiltroAbastecimento").click(function(){
    var url = root + '/report.abastecimentopdf?datainicio=:datainicio&datafim=:datafim&veiculo=:veiculo&tipo=1';
    redirect(url,true);
});

$("#gerarPdfAbastecimento").click(function(){
    var url = root + '/report.abastecimentopdf?datainicio=:datainicio&datafim=:datafim&veiculo=:veiculo&tipo=2';
    redirect(url,false);
});

////////Gestão Frota
$("#btnFiltroGestaoFrota").click(function(){
    var url = root + '/report.gestaofrotapdf?datainicio=:datainicio&datafim=:datafim&veiculo=:veiculo&tipo=1';
    redirect(url,true);
});

$("#gerarPdfGestaoFrota").click(function(){
    var url = root + '/report.gestaofrotapdf?datainicio=:datainicio&datafim=:datafim&veiculo=:veiculo&tipo=2';
    redirect(url,false);
});

///////Troca de Oleo
$("#btnFiltroTrocaOleo").click(function(){
    var url = root + '/report.trocaoleopdf?datainicio=:datainicio&datafim=:datafim&veiculo=:veiculo&tipo=1';
    redirectOleo(url,true);
});

$("#gerarPdfTrocaOleo").click(function(){
    var url = root + '/report.trocaoleopdf?datainicio=:datainicio&datafim=:datafim&veiculo=:veiculo&tipo=2';
    redirectOleo(url,false);
});

$("#btnFiltroVencidos").click(function(){
    var url = root + '/report.trocaoleovencidopdf?&tipoveiculo=:tipoveiculo&tab=2&tipo=1';
    redirectOleo(url,true,"2");
});

$("#gerarPdfVencido").click(function(){
    var url = root + '/report.trocaoleovencidopdf?&tipoveiculo=:tipoveiculo&tab=2&tipo=2';
    redirectOleo(url,false,"2");
});

function redirect(url,modal){
    var datainicio = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());
    var veiculo = $("#veiculo_id").val() == "" ? 0 : $("#veiculo_id").val();
    
    var url = url.replace(':datainicio',datainicio);
    var url = url.replace(':datafim',datafim);
    var url = url.replace(':veiculo',veiculo);
    if(modal){
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    }else{
        window.open(url, '_blank');
    }
}

function redirectOleo(url,modal,tab){
    if(tab != "2"){
        var datainicio = insertDataOracle($("#datainicio").val());
        var datafim = insertDataOracle($("#datafim").val());
        var veiculo = $("#veiculo_id").val() == "" ? 0 : $("#veiculo_id").val();
        
        var url = url.replace(':datainicio',datainicio);
        var url = url.replace(':datafim',datafim);
        var url = url.replace(':veiculo',veiculo);
    }else{
        var tipo = $("#tipoveiculo_id").val() == "" ? 0 : $("#tipoveiculo_id").val();
        var url = url.replace(":tipoveiculo",tipo);
    }
    if(modal){
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    }else{
        window.open(url, '_blank');
    }
}

function correcoesParametros(){
    if(window.location.search.includes('tab')){
        $('.nav-tabs a[href="#tab_2"]').tab('show');
    }
}