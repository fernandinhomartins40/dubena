$(document).ready(function(){
    if(window.location.search != ""){
        corrgiriTab();
    }
});
//report transferencia
$("#gerarPdfTransferencia").click(function (e) {
    var url = root + '/report.transferenciapdf?datainicio=:datainicio&datafinal=:datafim&setororigem=:setororigem&produto=:produto&tab=1&tipo=1&destino=0';
    redirecTransferencia("datainicio", "datafim", "produto_id", url, false);
});

$("#btnFiltroTransferencia").click(function () {
    var url = root + '/report.transferenciapdf?datainicio=:datainicio&datafinal=:datafim&setororigem=:setororigem&produto=:produto&tab=1&tipo=2&destino=0';
    redirecTransferencia("datainicio", "datafim", "produto_id", url, true);
});

//Destino
$("#gerarPdfTransferenciaDestino").click(function () {
    var url = root + '/report.transferenciapdf?datainicio=:datainicio&datafinal=:datafim&setordestino=:destino&produto=:produto&tab=1&tipo=1&destino=1';
    redirecTransferencia("datast", "dataen", "produtoid", url, false);
});

$("#btnFiltroTransferenciaDestino").click(function () {
    var url = root + '/report.transferenciapdf?datainicio=:datainicio&datafinal=:datafim&setordestino=:destino&produto=:produto&tab=1&tipo=2&destino=1';
    redirecTransferencia("datast", "dataen", "produtoid", url, true);
});

//report requisicao
$("#btnFiltroRequisicao").click(function () {
    var url = root + '/report.estoquerequisicaopdf?datainicio=:datainicio&datafinal=:datafim&setor=:setor&produto=:produto&cancelado=:cancelado&tipo=1';
    redirectRequisicao(url, true);
});

$("#gerarPdfRequisicao").click(function () {
    var url = root + '/report.estoquerequisicaopdf?datainicio=:datainicio&datafinal=:datafim&setor=:setor&produto=:produto&cancelado=:cancelado&tipo=2';
    redirectRequisicao(url, false);
});

//Report estoque GLP
$("#btnFiltroGlp").click(function () {
    var url = root + '/report.estoqueglppdf?setor=:setor&produto=:produto&zerado=:zerado&tipo=1';
    redirectGLPGeral(url, true, false);
});

$("#gerarPdfGlp").click(function () {
    var url = root + '/report.estoqueglppdf?setor=:setor&produto=:produto&zerado=:zerado&tipo=2';
    redirectGLPGeral(url, false, false);
});

//Report Estoque Geral
$("#btnFiltroGeral").click(function () {
    var url = root + '/report.estoquegeralpdf?setor=:setor&produto=:produto&classe=:classe&zerado=:zerado&tipo=1';
    redirectGLPGeral(url, true, true);
});

$("#gerarPdfGeral").click(function () {
    var url = root + '/report.estoquegeralpdf?setor=:setor&produto=:produto&classe=:classe&zerado=:zerado&tipo=2';
    redirectGLPGeral(url, false, true);
});

function redirecTransferencia(datast, dataen, prod, url, modal) {
    var datainicio = insertDataOracle($("#" + datast).val());
    var datafim = insertDataOracle($("#" + dataen).val());
    var setororigem = $("#setores_id").val() == "" ? 0 : $("#setores_id").val();
    var setordestino = $("#setordestino_id").val() == "null" ? 0 : $("#setordestino_id").val();
    var produto = $("#" + prod).val() == "" ? 0 : $("#" + prod).val();
    var url = url.replace(':datainicio', datainicio);
    var url = url.replace(':datafim', datafim);
    var url = url.replace(':setororigem', setororigem);
    var url = url.replace(':destino', setordestino);
    var url = url.replace(':produto', produto);
    if (modal) {
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    } else {
        window.open(url, '_blank');
    }
}

function redirectRequisicao(url, modal) {
    var datainicio = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());
    var setor = $("#setor_id").val() == "" ? 0 : $("#setor_id").val();
    var produto = $("#produto_id").val() == "" ? 0 : $("#produto_id").val();
    var cancelado = $("#cancelado").prop('checked') == false ? 0 : 1;
    var url = url.replace(':datainicio', datainicio);
    var url = url.replace(':datafim', datafim);
    var url = url.replace(':setor', setor);
    var url = url.replace(':produto', produto);
    var url = url.replace(':cancelado', cancelado);
    if (modal) {
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    } else {
        window.open(url, '_blank');
    }
}

function redirectGLPGeral(url, modal, geral) {
    var setor = $("#setor_id").val() == "" ? 0 : $("#setor_id").val();
    var produto = $("#produto_id").val() == "" ? 0 : $("#produto_id").val();
    var zerado = $("#zerado").prop('checked') == false ? 0 : 1;
    var url = url.replace(':setor', setor);
    var url = url.replace(':produto', produto);
    var url = url.replace(':zerado', zerado);
    if (geral) {
        var classe = $("#classe_id").val() == "" ? 0 : $("#classe_id").val();
        var url = url.replace(':classe', classe);
    }
    if (modal) {
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src',url);
    } else {
        window.open(url, '_blank');
    }
}

function corrgiriTab(){
    $('.nav-tabs a[href="#tab_2"]').tab('show');
}