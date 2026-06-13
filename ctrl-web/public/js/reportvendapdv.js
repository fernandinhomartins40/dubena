//Filtro compram nao compram
$("#btnFiltroCompram").click(function(){
    var url = root + '/report.vendasegmentopdf?datainicio=:datainicio&datafim=:datafim&segmento=:segmento&setor=:setor&compram=:compram&tipo=1';
    redirect(url,true);
});

$("#gerarPdfCompram").click(function(){
    var url = root + '/report.vendasegmentopdf?datainicio=:datainicio&datafim=:datafim&segmento=:segmento&setor=:setor&compram=:compram&tipo=2';
    redirect(url,false);
});

//Filtro Entregador
$("#btnFiltroEntregador").click(function(){
    var url = root + '/report.vendaentregadorpdf?datainicio=:datainicio&datafim=:datafim&colaborador=:col&tipo=1';
    redirectEntregador(url,true);
});

$("#gerarPdfEntregador").click(function(){
    var url = root + '/report.vendaentregadorpdf?datainicio=:datainicio&datafim=:datafim&colaborador=:col&tipo=2';
    redirectEntregador(url,false);
});

//Filtro Convenio
$("#btnFiltroConvenio").click(function(){
    var url = root + '/report.vendaconveniopdf?datainicio=:datainicio&datafim=:datafim&conveniado=:con&compram=:cpr&tipo=1';
    redirectConveniado(url,true);
});

function redirect(url,modal){
    var datainicio = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());
    var segmento = $("#segmento_id").val() == "" ? 0 : $("#segmento_id").val();
    var setor = $("#setor_id").val() == "" ? 0 : $("#setor_id").val();
    var radio = $("#compram:checked").val();
    var url = url.replace(':datainicio',datainicio);
    var url = url.replace(':datafim',datafim);
    var url = url.replace(':segmento',segmento);
    var url = url.replace(':setor',setor);
    var url = url.replace(':compram',radio);
    if(modal){
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    }else{
        window.open(url, '_blank');
    }
}

function naoCompram(){
    var compram = $("#compram:checked").val();
    if(compram == 1){
        // $("#gerarPdfCompram").removeClass('hidden');
    }else{
        $("#gerarPdfCompram").addClass('hidden');
    }
}

function redirectEntregador(url,modal){
    var datainicio = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());
    var colaborador = $("#colaborador_id").val() == "" ? 0 : $("#colaborador_id").val();

    var url = url.replace(':datainicio',datainicio);
    var url = url.replace(':datafim',datafim);
    var url = url.replace(':col',colaborador);
    if(modal){
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    }else{
        window.open(url, '_blank');
    }
}

function redirectConveniado(url,modal){
    var datainicio = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());
    var conveniado = $("#convenio_id").val() == "" ? 0 : $("#convenio_id").val();
    var radio = $("#compram:checked").val();
    var url = url.replace(':datainicio',datainicio);
    var url = url.replace(':datafim',datafim);
    var url = url.replace(':con',conveniado);
    var url = url.replace(':cpr',radio);
    if(modal){
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    }else{
        window.open(url, '_blank');
    }
}