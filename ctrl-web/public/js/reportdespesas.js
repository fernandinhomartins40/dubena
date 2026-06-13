$(document).ready(function(){
    $("#btnVoltar").removeClass('hidden');
    $("#btnAvancar").removeClass('hidden');
    regionaisFunction();
});

$("#btnFiltroDespesas").click(function(){
    var url = root + '/report.despesas.filtro?datainicio=:in&datafim=:fn&consolidado=:consolidado&empresa=:emp';
    redirect(url,true,'plano');
});

$("#btnFiltroReceita").click(function(){
    var url = root + '/report.receitas.filtro?datainicio=:in&datafim=:fn&consolidado=:consolidado&empresa=:emp';
    redirect(url,true,'plano');
});

$("#btnVoltar").click(function(){
    window.history.back();
});

$("#consolidado_id").change(function(){
    var con = $(this).val();
    if(!isEmpty(con))
        buscaEmpresas(con);
    else
        $("#empresa_id").empty().trigger('chosen:updated');
});

$("#btnAvancar").click(function(){
    window.history.forward();
});

function redirect(url, modal, which){
    var datainico = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());
    if(which == 'plano'){
        var consolidado = $("#consolidado_id").val() == "" ? "0" : $("#consolidado_id").val();
        var empresa = $("#empresa_id").val() == "" || $("#empresa_id").val() == "null" || $("#empresa_id").val() == null ? "0" : $("#empresa_id").val();
        var url = url.replace(':consolidado',consolidado);
        var url = url.replace(':emp',empresa);
    }else{
        var pai = $("#centropai_id").val() == "" ? "0" : $("#centropai_id").val();
        var filho = $("#centrofilho_id").val() == "" || $("#centrofilho_id").val() == "null" || $("#centrofilho_id").val() == null ? "0" : $("#centrofilho_id").val();
        var url = url.replace(':pai',pai);
        var url = url.replace(':filho',filho);
    }

    var url = url.replace(':in',datainico);
    var url = url.replace(':fn',datafim);
    openModalReport(url,modal);
}

function regionaisFunction(){
    if($('#consolidado_id option').length > 1){
        $("#empresa_id").empty().trigger('chosen:updated');
    }else{
        $('#consolidado_id').prop('disabled',true);
    }
}

function buscaEmpresas(con){
    var url = root + "/api/empresasbyregional?regional="+con;
    $("#empresa_id").empty().trigger('chosen:updated');
    var html = "<option value=''>Selecione</option>";
    ajaxGenerator(url,'GET',function(data){
        $.each(data,function(key,val){
            html += "<option value='"+key+"'>"+val+"</option>";
        });
        $("#empresa_id").append(html).trigger('chosen:updated');
    },null,null,true);
}

// Centro Custo
$("#btnFiltroCC").click(function(){
    var url = root + "/report.despesas_cc.filtro?datainicio=:in&datafim=:fn&centropai_id=:pai&centrofilho_id=:filho";
    redirect(url,true,'centro');
});

$("#btnFiltroCCReceitas").click(function(){
    var url = root + "/report.receitas_cc.filtro?datainicio=:in&datafim=:fn&centropai_id=:pai&centrofilho_id=:filho";
    redirect(url,true,'centro');
});

$("#centropai_id").change(function(){
    var url = root + '/api/centrocustobypai?centro_id=:centro';
    var id = $(this).val();
    if(id == "")
        $("#centrofilho_id").empty().trigger('chosen:updated');
    else
        ajaxCentroCustos(url,id);
});

function ajaxCentroCustos(url, id){
    $("#centrofilho_id").empty().trigger('chosen:updated');
    var url = url.replace(':centro',id);
    var html = "<option value=''>Selecione</option>";
    ajaxGenerator(url,'GET',function(data){
        $.each(data, function(id,descricao){
            html += "<option value='"+id+"' >"+descricao+"</option>";
        });
        $("#centrofilho_id").append(html).trigger('chosen:updated');
    },null,null,true);
}


$("#teee").on('focusout', function () {
    var val = $("#teee").val();
    var val = unescape(decodeURIComponent(val)).replace(/\+/g, " ");
    $("#teee").val(val);
    var copyText = document.getElementById("teee");
    copyText.select();
    document.execCommand("Copy");
});