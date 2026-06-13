$(document).ready(function(){
    correcoesParametros();
});

$("#btnFiltroPendente").click(function(){
    var url = root + '/report.valegasfiltro?cliente=:cliente&prevenda=:prevenda&rub=1&tipo=1';
    redirectPendente(url,true);
});

$("#gerarPdfPendente").click(function(){
    var url = root + '/report.valegasfiltro?cliente=:cliente&prevenda=:prevenda&rub=1&tipo=2';
    redirectPendente(url,false);
});

function redirectPendente(url,modal,pendente=true){
    if(pendente){
        var cliente = $("#cliente_pendente").val() == "" ? 0 : $("#cliente_pendente").val();
        var prevenda = $("#prevenda").prop('checked');
        var url = url.replace(':cliente',cliente);
        var url = url.replace(':prevenda',prevenda);
    }
    openModal(url,modal);
}

//Baixado
$("#btnFiltroBaixado").click(function(){
    var url = root + '/report.valegasfiltro?datainicio=:datainicio&datafim=:datafim&cliente=:cliente&situacao=:situacao&rub=2&tipo=1';
    redirectBaixado(url,true);
});

$("#gerarPdfBaixado").click(function(){
    var url = root + '/report.valegasfiltro?datainicio=:datainicio&datafim=:datafim&cliente=:cliente&situacao=:situacao&rub=2&tipo=2';
    redirectBaixado(url,false);
});

function redirectBaixado(url,modal){
    var datainicio = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());
    var cliente = $("#cliente_baixado").val() == "" ? 0 : $("#cliente_baixado").val();
    var situacao = $("#situacao").val() == "" ? 0 : $("#situacao").val();

    var url = url.replace(':datainicio',datainicio);
    var url = url.replace(':datafim',datafim);
    var url = url.replace(':cliente',cliente);
    var url = url.replace(':situacao',situacao);
    openModal(url,modal);
}


//Pedido
$("#btnFiltroPedido").click(function(){
    var url = root + '/report.valegasfiltro?datainicio=:datainicio&datafim=:datafim&cliente=:cliente&rub=4&tipo=1';
    redirectPendente(url,true);
});

$("#gerarPdfPedido").click(function(){
    var url = root + '/report.valegasfiltro?datainicio=:datainicio&datafim=:datafim&cliente=:cliente&rub=4&tipo=2';
    redirectPendente(url,false);
});

function redirectPendente(url,modal){
    var datainicio = insertDataOracle($("#datainicio_pedido").val());
    var datafim = insertDataOracle($("#datafim_pedido").val());
    var cliente = $("#cliente_pedido").val() == "" ? 0 : $("#cliente_pedido").val();

    var url = url.replace(':datainicio',datainicio);
    var url = url.replace(':datafim',datafim);
    var url = url.replace(':cliente',cliente);

    openModal(url,modal);
}



//Vendas
$("#btnFiltroVenda").click(function(){
    var url = root + '/report.valegasfiltro?datainicio=:datainicio&datafim=:datafim&cliente=:cliente&compram=:compram&situacao=:sit&rub=3&tipo=1';
    var sit = $("#situacao_val").val();
    var check = $("#compram:checked").val() == "1";
    if(check){
        if(!isEmpty(sit)){
            redirectVenda(url,true);
        }else{
            bootbox.alert('Por favor, selecione uma situação');
        }
    }else{
        redirectVenda(url,true,check);
    }
});

$("#gerarPdfVenda").click(function(){
    var url = root + '/report.valegasfiltro?datainicio=:datainicio&datafim=:datafim&cliente=:cliente&compram=:compram&situacao=:sit&rub=3&tipo=2';
    var sit = $("#situacao_val").val();
    var check = $("#compram:checked").val() == "1";
    if(!isEmpty(sit)){
        redirectVenda(url,false);
    }else{
        bootbox.alert('Por favor, selecione uma situação');
    }
});

function redirectVenda(url,modal,situacao = true){
    var datainicio = insertDataOracle($("#datainicio_venda").val());
    var datafim = insertDataOracle($("#datafim_venda").val());
    var cliente = $("#cliente_venda").val() == "" ? "0" : $("#cliente_venda").val();
    var compram = $("#compram:checked").val();

    if(situacao){
        var sit = $("#situacao_val").val();
        var url = url.replace(':sit',sit);
    }

    var url = url.replace(':datainicio',datainicio);
    var url = url.replace(':datafim',datafim);
    var url = url.replace(':cliente',cliente);
    var url = url.replace(':compram',compram);
    openModal(url,modal);
}

function checarCompram(){
    var compram = $("#compram:checked").val() == "1";
    if(compram){
        $("#cliente_venda").prop('disabled',false).trigger('chosen:updated');
        $("#situacao_val").prop('disabled',false).trigger('chosen:updated');
        $("#gerarPdfVenda").removeClass('hidden');
    }else{
        $("#cliente_venda").prop('disabled',true).trigger('chosen:updated');
        $("#situacao_val").prop('disabled',true).trigger('chosen:updated');
        $("#gerarPdfVenda").addClass('hidden');
    }
}

//Estoque
$("#btnFiltroEstoque").click(function(){
    var url = root + '/report.valegasfiltro?rub=5&tipo=1';
    redirectPendente(url,true,false);
});

$("#gerarPdfEstoque").click(function(){
    var url = root + '/report.valegasfiltro?rub=5&tipo=2';
    redirectPendente(url,false,false);
});

function correcoesParametros(){
    if(window.location.search.includes('tab')){
        var tab = getParametro("tab");
        $('.nav-tabs a[href="#'+tab+'"]').tab('show');
    }
}

function openModal(url,modal){
    if(modal){
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    }else{
        window.open(url, '_blank');
    }
}
