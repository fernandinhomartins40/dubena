$(document).ready(function(){
    if(window.location.search != ""){
        correcoes();
    }
});

//Fornecedores
$("#btnFiltroFornecedores").click(function(){
    var url = root + '/report.fornecedorespdf?uf=:uf&cidade=:cidade&segmento=:segmento&tipo=:tipo&ativo=:ativo&empresa=:empresa&modal=1';
    redirect(url);
});

//Clientes
$("#btnFiltroClientes").click(function(){
    var url = root + '/report.clientespdf?uf=:uf&cidade=:cidade&segmento=:segmento&tipo=:tipo&ativo=:ativo';
    var estado = $("#uf").val();
    if(!isEmpty(estado)){
        redirect(url);
    }else{
        bootbox.alert('Por favor, selecione um estado!');
    }
});

//Clientes por Bairro
$("#btnFiltro").click(function () {
    var url = root + '/report.clientesbairrospdf?setor=:setor&segmento=:segmento&tipopessoa=:tipopessoa&bairro=:bairro';
    redirectClientesBairro(url);
});

//Clientes ativos/inativos
$("#btnFiltroClientesAtivos").click(function(){
    var url = root + '/report.clientesativospdf?segmento=:segmento&setor=:setor&ativo=:ativo&tipopessoa=:tipopessoa&exportar=:exportar';
    redirectClientesAtivos(url, false);
});

$("#btnFiltroClientesAtivosXls").click(function(){
    var url = root + '/report.clientesativospdf?segmento=:segmento&setor=:setor&ativo=:ativo&tipopessoa=:tipopessoa&exportar=:exportar';
    redirectClientesAtivos(url, true);
});

function redirect(url){
    var uf = $("#uf").val() == "" ? 0 : $("#uf").val();
    var cidade = $("#cidade_id").val() == null || $("#cidade_id").val() == "Selecione"  ? 0 : $("#cidade_id").val();
    var segmento = $("#segmento_id").val() == "" ? 0 : $("#segmento_id").val();
    var tipo = $("#tipopessoa_id").val() == "" ? 0 : $("#tipopessoa_id").val();
    var ativo = $("#ativo").prop("checked") == false ? 0 : 1;
    var empresa = $("#empresa_id").val() == null || $("#empresa_id").val() == 'null' ? 0 : $("#empresa_id").val();
    
    var url = url.replace(':uf',uf);
    var url = url.replace(':cidade',cidade);
    var url = url.replace(':segmento',segmento);
    var url = url.replace(':tipo',tipo);
    var url = url.replace(':ativo',ativo);
    var url = url.replace(':empresa',empresa);

    $("#popup_relatorio").modal('show');
    $("#iFrameReport").attr('src', url);
}

function correcoes(){
    var estado = getParametro("uf");
    var cidade = getParametro("cidade");
    var segmento = getParametro("segmento");
    var tipo = getParametro("tipo");
    var ativo = getParametro("ativo") == 1 ? true : false;

    $("#uf").val(estado).trigger("chosen:updated").trigger('change');
    $("#cidade_id").val(cidade).trigger("chosen:updated");
    $("#segmento_id").val(segmento).trigger("chosen:updated");
    $("#tipopessoa_id").val(tipo).trigger("chosen:updated");
    $("#ativo").prop("checked",ativo);
}

function redirectClientesBairro(url){
    var setor = $("#setor").val() == "" ? 0 : $("#setor").val();
    var segmento = $("#segmento").val() == "" ? 0 : $("#segmento").val();
    var tipo = $("#tipopessoa").val() == "" ? 0 : $("#tipopessoa").val();
    var bairro = $("#bairro").val() == "" ? 0 : $("#bairro").val();

    url = url.replace(':setor', setor);
    url = url.replace(':segmento', segmento);
    url = url.replace(':tipopessoa',tipo);
    url = url.replace(':bairro',bairro);
    $("#popup_relatorio").modal('show');
    $("#iFrameReport").attr('src', url);
}

function redirectClientesAtivos(url, exportar){
    let setor = $("#at_setor_id").val() == "" ? 0 : $("#at_setor_id").val();
    let segmento = $("#at_segmento_id").val() == "" ? 0 : $("#at_segmento_id").val();
    let ativo = $("#at_ativo").prop("checked") == false ? 0 : 1;
    let tipopessoa = $("#at_tipopessoa_id").val() == "" ? 0 : $("#at_tipopessoa_id").val();
    let exportar1 = exportar ? 1 : 0;

    url = url.replace(':setor', setor);
    url = url.replace(':segmento', segmento);
    url = url.replace(':tipopessoa',tipopessoa);
    url = url.replace(':ativo',ativo);
    url = url.replace(':exportar',exportar1);
    if(!exportar){
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    } else {
        window.open(url, '_target');
    }
}


//Clientes incompletos
$("#btnFiltroIncompletos").click(function(){
    var url = root + '/report.incompletosfiltro?dados=:dados';
    var incompletos = $("#incompleto").val();
    if(!isEmpty(incompletos)){
        redirectIncompletos(url,true);
    }else{
        bootbox.alert('Campo dados incompletos é obrigatório!');
    }
});

function redirectIncompletos(url,modal) {
    var incompletos = $("#incompleto").val();

    var url = url.replace(':dados',incompletos);
    openModalReport(url,modal);
}