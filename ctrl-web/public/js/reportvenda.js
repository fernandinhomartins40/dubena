$("#btnFiltroVenSetor").click(function(){
    var url = root + '/report.vendasetorfiltro?datainicio=:datainicio&datafim=:datafim&setor=:setor&segmento=:segmento&produto=:produto&tipo=1';
    redirect(url,true);
});

$("#btngerarpdf").click(function(){
    var url = root + '/report.vendasetorfiltro?datainicio=:datainicio&datafim=:datafim&setor=:setor&segmento=:segmento&produto=:produto&tipo=2';
    redirect(url,false);
});

function redirect(url,modal){
    var datainicio = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());
    var setor = $("#setor_id").val() == "" ? 0 : $("#setor_id").val();
    var segmento = $("#segmento_id").val() == "" ? 0 : $("#segmento_id").val();
    var produtos = $("#produto_id").val() == null || $("#produto_id").val() == "null" ? 0 : $("#produto_id").val();

    var url = url.replace(':datainicio',datainicio);
    var url = url.replace(':datafim',datafim);
    var url = url.replace(':setor',setor);
    var url = url.replace(':segmento',segmento);
    var url = url.replace(':produto',produtos);

    openModal(url,modal);
}

//Vendas Diaria
$("#btnFiltroDiaria").click(function(){
    var url = root + '/report.vendadiariafiltro?data=:data&produto=:prod&setor=:setor&tipo=1';
    redirectDiaria(url,true);
});

$("#gerarPdfDiaria").click(function(){
    var url = root + '/report.vendadiariafiltro?data=:data&produto=:prod&setor=:setor&tipo=2';
    redirectDiaria(url,false);
});

function redirectDiaria(url,modal){
    var data = insertDataOracle($("#datainicio").val());
    var produtos = $("#produto_id").val() == "null" || $("#produto_id").val() == null? 0 : $("#produto_id").val();
    var setor = $("#setor_id").val() == "" ? 0 : $("#setor_id").val();

    var url = url.replace(':data',data);
    var url = url.replace(':prod',produtos);
    var url = url.replace(':setor',setor);
    openModal(url,modal);
}

///all
function openModal(url,modal){
    if(modal){
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    }else{
        window.open(url, '_blank');
    }
}

$("#btnFiltroVenApp").click(function(){
    var url = root + '/report.vendaappfiltro?datainicio=:datainicio&datafim=:datafim&setor=:setor&tipo=1';
    redirect(url,true);
});

$("#gerarPdfApp").click(function(){
    var url = root + '/report.vendaappfiltro?datainicio=:datainicio&datafim=:datafim&setor=:setor&tipo=2';
    redirect(url,false);
});

$("#btnFiltroVenProduto").click(function(){
    var url = root + '/report.vendaprodutofiltro?datainicio=:datainicio&datafim=:datafim&segmento=:segmento&produto=:produto&tipo=1';
    redirectProduto(url,true);
});

$("#btngerarpdfproduto").click(function(){
    var url = root + '/report.vendaprodutofiltro?datainicio=:datainicio&datafim=:datafim&segmento=:segmento&produto=:produto&tipo=2';
    redirectProduto(url,false);
});

function redirectProduto(url,modal){
    var datainicio = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());
    var segmento = $("#segmento_id").val() == "" ? 0 : $("#segmento_id").val();
    var produtos = $("#produto_id").val() == null || $("#produto_id").val() == "null" ? 0 : $("#produto_id").val();

    var url = url.replace(':datainicio',datainicio);
    var url = url.replace(':datafim',datafim);
    var url = url.replace(':segmento',segmento);
    var url = url.replace(':produto',produtos);

    openModal(url,modal);
}

// * Vendas gerais
$("#btnFiltroVenGeral").click(function () {
    let url = root + '/report.vendasgerais.pdf?datainicio=:datainicio&datafim=:datafim&tipo=1';
    redirectGeral(url, true);
});

$("#btngerarpdfgeral").click(function () {
    let url = root + '/report.vendasgerais.pdf?datainicio=:datainicio&datafim=:datafim&tipo=2';
    redirectGeral(url, false);
});

function redirectGeral(url, modal) {
    var datainicio = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());

    url = url.replace(':datainicio',datainicio);
    url = url.replace(':datafim',datafim);

    openModal(url, modal);
}