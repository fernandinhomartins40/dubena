$(document).ready(function(){
    $(".dataTableNoAll").DataTable({
        "language": {"url": urlDataTable},
		"processing": true,
		"bPaginate": true,
		"bLengthChange": false,
		"bFilter": false,
		"bSort": false,
		"bInfo": false,
		"bAutoWidth": false,
		"pageLength": 30
    });
    corrigirDadosFiltro();
    respondido();
});

$("#tblCadastro").on('click','#btnResponder',function(e){
    var row = $(this).closest('tr');
    redirect(e,row,true);
});

$("#tblCadastro").on('click','#btnEditar',function(e){
    var row = $(this).closest('tr');
    redirect(e,row,false);
});

$("#tblCadastro").on('click','#btnPdf',function(e){
    e.stopPropagation();
});

$("#goback").click(function(){
    window.history.back();
});

$("#fmCadastro").on('submit',function(e){
    submitRadios(e);
});

$('#btnFiltrarCheck').click(function () {
    filtrarBusca();
});

$("#pesquisaform").click(function(){
    respondido();
});

$("#btngravar").click(function(e){
    if(typeof array_check != "undefined"){
        $.each(array_check, function(i, val){
            if(typeof $("input[type=checkbox][name="+val+"]:checked").val() == "undefined"){
                e.preventDefault();
                bootbox.alert("Marque ao menos um dos checkbox!");
            }
        });
    }
});

function redirect(e,row,responder){
    e.preventDefault();
    var id = $(row.children('td')[0]).text();
    if(responder){
        var url = root + '/checklist/create?id=' + id;
    }else{
        var url = root + '/checklist/' + id + '/edit';
    }
    window.location.href = url;
}

function submitRadios(e){
    var inputs = fmCadastro.elements;
    var radios = [];
    var text = [];
    var checkbox = [];
    for(var i=0;i<inputs.length;i++){
        if(inputs[i].type == 'radio'){
            radios.push(inputs[i]);
        }else if(inputs[i].type == 'text'){
            text.push(inputs[i]);
        }else if(inputs[i].type == 'checkbox'){
            checkbox.push(inputs[i]);
        }
    }
    var radikoschecados = [];
    for(var i=0;i<radios.length;i++){
        if(radios[i].checked){
            radikoschecados.push({
                "idpergunta":radios[i].value,
                "idresposta":radios[i].id,
                "tipo": $("#"+radios[i].id).attr('tipo')
            });
        }
    }
    var textfull = [];
    for(var i=0;i<text.length;i++){
        if(text[i].value !== ""){
            textfull.push({
                "idpergunta":text[i].id,
                "idresposta":text[i].name,
                "resposta":text[i].value,
                "tipo": $("[name='"+text[i].name+"']").attr('tipo')
            });
        }
    }
    var checkedcheck = [];
    for(var i=0;i<checkbox.length;i++){
        if(checkbox[i].checked){
            checkedcheck.push({
                "idpergunta":checkbox[i].name,
                "idresposta":checkbox[i].id,
                "tipo": $("[name='"+checkbox[i].name+"']").attr('tipo')
            });
        }
    }
    $("#respostasradio").val(JSON.stringify(radikoschecados));
    $("#respostastext").val(JSON.stringify(textfull));
    $("#respostascheckbox").val(JSON.stringify(checkedcheck));
}

function showRespostas(){
    var respostas = JSON.parse($("#respostas").val());
    var inputs = fmCadastro.elements;
    var radios = [];
    var text = [];
    var checkbox = [];
    for(var i=0;i<inputs.length;i++){
        if(inputs[i].type == 'radio'){
            radios.push(inputs[i]);
        }else if(inputs[i].type == 'text'){
            text.push(inputs[i]);
        }else if(inputs[i].type == 'checkbox'){
            checkbox.push(inputs[i]);
        }
    }
    $.each(radios,function(i,val){
        for(var x=0;x<respostas.length;x++){
            if(val.value == respostas[x].checklistpergunta_id && val.id == respostas[x].checklistresposta_id){
                $("#"+val.id).prop('checked',true);
            }
        }
    });
    $.each(text,function(i,val){
        for(var x=0;x<respostas.length;x++){
            if(val.id == respostas[x].checklistpergunta_id && val.name == respostas[x].checklistresposta_id){
                $("[name='"+val.name+"']").val(respostas[x].resposta);
                var tipo = $("[name='"+val.name+"']").attr('tipo');
                if(tipo == "data"){
                    data = requestDataOracle(respostas[x].resposta,false,false,true);
                    $("[name='"+val.name+"']").val(data);
                }
            }
        }
    });
    $.each(checkbox,function(i,val){
        for(var x=0;x<respostas.length;x++){
            if(val.id == respostas[x].checklistresposta_id && val.name == respostas[x].checklistpergunta_id){
                $("#"+respostas[x].checklistresposta_id+"[name="+val.name+"]").prop('checked',true);
            }
        }
    });
}

function filtrarBusca(){
    var urlFiltro = 'checklist.filtro?datainicio=:datast&datafim=:datafim&tipo=:tipo&respondido=:respondido&empresa=:empresa';
    var datainicio = $("#datainicio").val();
    var datafim = $("#datafim").val();
    var tipo = $("#tipochecklist").val();
    var respondido = $("#pesquisaform").prop('checked') ? "1" : "0";
    var empresa = $("#empresa").val();
    var datainicio = insertDataOracle(datainicio, false);
    var datafim = insertDataOracle(datafim, false);
    if(respondido == "0" && empresa == ""){
        bootbox.alert('Por favor, selecione para qual empresa pretende responder um checklist.');
    }else{
        urlFiltro = urlFiltro.replace(':datast', datainicio);
        urlFiltro = urlFiltro.replace(':datafim', datafim);
        urlFiltro = urlFiltro.replace(':tipo', tipo);
        urlFiltro = urlFiltro.replace(':respondido', respondido);
        urlFiltro = urlFiltro.replace(':empresa', empresa);
        window.location.href = urlFiltro;
    }
}

function corrigirDadosFiltro(){
    var urlcontains = window.location.search;
    if(!isEmpty(urlcontains)){
        var datainicio = retornarData("datainicio");
        var datafim = retornarData("datafim");
        var tipo = getParametro("tipo") == 0 ? "" : getParametro("tipo");
        var empresa = getParametro("empresa") == 0 ? "" : getParametro("empresa");
        var respondido = getParametro("respondido") == 0 ? false : true;
        $("#datainicio").val(datainicio);
        $("#datafim").val(datafim);
        $("#empresa").val(empresa).trigger('chosen:updated');
        $("#tipochecklist").val(tipo).trigger('chosen:updated');
        $("#pesquisaform").prop('checked',respondido);
    }
}

function respondido(){
    if($("#pesquisaform").prop('checked') == true){
        $("#datainicio").removeAttr('readonly');
        $("#datafim").removeAttr('readonly');
    }else{
        $("#datainicio").attr('readonly',true);
        $("#datafim").attr('readonly',true);
    }
}