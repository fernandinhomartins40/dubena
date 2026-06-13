$("#btnFiltroPromo").click(function(){
    var url = root + '/report.acompanhamentopromopdf?promocao=:promo&operador=:op&compras=:qtd&tipo=1';
    redirect(url,true);
});

function redirect(url,modal){
    var promo = $("#promocoes").val();
    var op = $("#maiormenor").val();
    var compras = $("#compras").val() == "" ? 0 : $("#compras").val();
    if(compras == 0){
        $("#compras").val(compras);
    }
    if(promo != ""){
        var url = url.replace(':promo',promo);
        var url = url.replace(':op',op);
        var url = url.replace(':qtd',compras);
        if(modal){
            $("#popup_relatorio").modal('show');
            $("#iFrameReport").attr('src', url);
        }
    }else{
        bootbox.alert('Por favor, selecione uma promoção!');
    }
}