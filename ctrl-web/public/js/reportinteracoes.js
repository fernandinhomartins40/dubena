$("#empresa_id").change(function(){
    mudaEstadoSelectReport('empresa_id','setor_id');
});

$("#btnFiltroInteracoes").click(function(){
    var url = root + "/report.interacoesfiltro?empresa=:empresa&setor=:setor&datainicio=:datainicio&"+
                        "datafim=:datafim&segmento=:seg&pessoa=:pessoa&situacao=:sit&contato=:contato&tipo=1";
    redirect(url,true);
});

$("#gerarPdfInteracoes").click(function(){
    var url = root + "/report.interacoesfiltro?empresa=:empresa&setor=:setor&datainicio=:datainicio&"+
                        "datafim=:datafim&segmento=:seg&pessoa=:pessoa&situacao=:sit&contato=:contato&tipo=2";
    redirect(url,false);
});

function redirect(url,modal){
    var empresa = $("#empresa_id").val() == "null" || $("#empresa_id").val() == null ? 0 : $("#empresa_id").val();
    var setor = $("#setor_id").val() == "null" || $("#setor_id").val() == null ? 0 : $("#setor_id").val();
    var datainicio = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());
    var segmento = $("#segmento_id").val() == "" ? 0 : $("#segmento_id").val();
    var tipopessoa = $("#tipopessoa_id").val() == "" ? 0 : $("#tipopessoa_id").val();
    var situacao = $("#situacao_id").val() == "" ? 0 : $("#situacao_id").val();
    var tipo = $("#tipo_id").val() == "" ? 0 : $("#tipo_id").val();
    
    var url = url.replace(':empresa',empresa);
    var url = url.replace(':setor',setor);
    var url = url.replace(':datainicio',datainicio);
    var url = url.replace(':datafim',datafim);
    var url = url.replace(':seg',segmento);
    var url = url.replace(':pessoa',tipopessoa);
    var url = url.replace(':sit',situacao);
    var url = url.replace(':contato',tipo);
    if(modal){
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    }else{
        window.open(url, '_blank');
    }
}