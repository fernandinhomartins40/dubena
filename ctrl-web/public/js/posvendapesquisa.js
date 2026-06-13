$(document).ready(function(){
    tblPosvenda = $('#tblCadastroPosVenda').DataTable({
        "language": {"url": urlDataTable},
        "processing": true,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": true,
        "bSort": true,
        "bInfo": false,
        "bAutoWidth": false,
        "sScrollY": "300",
        "sScrollX": "205%",
        "order": [[ 2,"asc" ]]
    });
    corrigirDadosFiltro();
});

$("#btnFiltrar").click(function(){
    filtrarPedidos();
});

$("#tblCadastroPosVenda").on('dblclick','tr',function(){
    var trelem = $(this).closest("tr");
    var parent = $(this).parents('tr');
    if(trelem.hasClass('linhaselecionada')){
        bootbox.alert('Este cliente ja respondeu a pesquisa.');
    }else{
        redirect(trelem,parent);
    }
});

$("#btngravar").click(function(e){
    submitFormulario();
});

$("#goback").click(function(){
    window.history.back();
});

function filtrarPedidos(){
    var url = root + '/posvenda.filtro?datainicio=:datainicio&datafim=:datafim&setor=:setor&colaborador=:colaborador';
    var datainicio = insertDataHoraOracle($("#datainicio").val());
    var datafim = insertDataHoraOracle($("#datafim").val());
    var setor = $("#setor").val() == "" ? 0 : $("#setor").val();
    var colaborador = $("#colaborador").val() == "" ? 0 : $("#colaborador").val();
    var url = url.replace(':datainicio',datainicio);
    var url = url.replace(':datafim',datafim);
    var url = url.replace(':setor',setor);
    var url = url.replace(':colaborador',colaborador);
    window.location.href = url;
}

function corrigirDadosFiltro(){
    var urlcontains = window.location.search;
    if(!isEmpty(urlcontains)){
        var datainicio = retornarData("datainicio",true);
        var datafim = retornarData("datafim",true);
        var setor = getParametro("setor") == 0 ? "" : getParametro("setor");
        var colaborador = getParametro("colaborador") == 0 ? "" : getParametro("colaborador");
        $("#datainicio").val(datainicio);
        $("#datafim").val(datafim);
        $("#setor").val(setor);
        $("#colaborador").val(colaborador);
    }
    if($('#datahoraatual').val() == '' || $('#datahoraatual').val() == undefined){
        $('#datahoraatual').val(dataAtual(true, true, true, false));
    }

}

function redirect(trelem,parent){
    var urlTable = $("#tblCadastroPosVenda").attr('url');
    if($('#datahoraatual').val() == '' || $('#datahoraatual').val() == undefined){
        $('#datahoraatual').val(dataAtual(true, true, true, false));
    }

    var dataatual = insertDataHoraOracle($("#datahoraatual").val());
    var colunaum = $(trelem).children("td")[0];
    var id = $(colunaum).text();
    var url = urlTable.replace(':id',id);
    var url = url.replace(':dataatual',dataatual);
    window.location.href = url;
}

function submitFormulario(){
    var inputs = fmCadastro.elements;
    var radios = [];
    $.each(inputs,function(i,val){
        if(val.type == 'radio' && val.checked){
            radios.push({
                "respostaid":val.id,
                "perguntaid":val.name
            });
        }
    });
    $("#respostas").val(JSON.stringify(radios));
}