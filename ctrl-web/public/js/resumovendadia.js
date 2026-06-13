$("#btnFiltroResSetor").click(function(){
    var url = root + '/report.resumovendadiafiltro?datareferencia=:datareferencia&produto=:produto&tipo=1&setor=:setor';
    redirect(url,true);
});

$("#btngerarpdfresumodia").click(function(){
    var url = root + '/report.resumovendadiafiltro?datareferencia=:datareferencia&produto=:produto&tipo=0&setor=:setor';
    redirect(url,false);
});

function redirect(url,modal){
    if($("#produto_id").val()==''){
        bootbox.alert('Informe o produto');
        return;
    }
    if(!$("#setor_id").val()){
        bootbox.alert('Informe pelo menos um setor');
        return;
    }
    var datareferencia = insertDataOracle($("#datareferencia").val());
    var produtos = $("#produto_id").val() == null || $("#produto_id").val() == "null" ? 0 : $("#produto_id").val();

    var url = url.replace(':datareferencia',datareferencia);
    var url = url.replace(':produto',produtos);
    var url = url.replace(':setor',$('#setor_id').val().join(','));

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
