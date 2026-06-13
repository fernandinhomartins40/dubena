$("#empresa_id").change(function(){
    var empresa = $(this).val();
    getSetores(empresa); 
});

$("#btnFiltroCli").click(function(e){
    var url = root + '/report.semcomprasfiltro?empresa=:empresa&setor=:setor&tipopessoa=:tipopessoa&segmento=:seg&bairro=:bairro&'+
                        'naocompra=:nao&temcompra=:tem&tipo=1&exportar=0';
    var nao = parseInt($("#naocompram").val());
    var compram = parseInt($("#temcompras").val());
    var ok = true;
    if(nao > compram){
        bootbox.alert('Campo não compram não pode ser maior que o campo tem compras');
        $("#naocompram").val('');
        ok = false;
    }
    if (!isEmpty(nao) && !isEmpty(compram) && ok)
        redirect(url,true,false);
    else if (ok)
        bootbox.alert('Campo não compram e o campo tem compras são obrigatórios');
});

$("#btnFiltroCliXls").click(function(e){
    var url = root + '/report.semcomprasfiltro?empresa=:empresa&setor=:setor&tipopessoa=:tipopessoa&segmento=:seg&bairro=:bairro&'+
                        'naocompra=:nao&temcompra=:tem&tipo=1&exportar=1';
    var nao = parseInt($("#naocompram").val());
    var compram = parseInt($("#temcompras").val());
    var ok = true;
    if(nao > compram){
        bootbox.alert('Campo não compram não pode ser maior que o campo tem compras');
        $("#naocompram").val('');
        ok = false;
    }
    if (!isEmpty(nao) && !isEmpty(compram) && ok)
        redirect(url,true,true);
    else if (ok)
        bootbox.alert('Campo não compram e o campo tem compras são obrigatórios');
});

function getSetores(empresa){
    $("#setor_id").empty().trigger('chosen:updated');
    var url = root + '/ajax.getsetor?empresa='+empresa+'&tipo=3';
    var setores = "";
    var bairros = "";
    if(!isEmpty(empresa)){
        ajaxGenerator(url,'GET',function(data){
            var setor =  data;
            setores += "<option value=''>Selecione</option>";
            if(typeof setor != "undefined" && setor.length > 0){
                $.each(setor,function(key,setor){
                    setores += "<option value='"+setor.id+"'>"+setor.descricao+"</option>";
                });
            }
            $("#setor_id").append(setores).trigger('chosen:updated');
        },null);
    }
}

function redirect(url, modal, exportar=false){
    var empresa = $("#empresa_id").val() == "" ? 0 : $("#empresa_id").val();
    var setor = $("#setor_id").val() == "" || $("#setor_id").val() == "null" || $("#setor_id").val() == null ? 0 : $("#setor_id").val();
    var tipopessoa = $("#tipopessoa_id").val() == "" ? 0 : $("#tipopessoa_id").val();
    var segmento = $("#segmento_id").val() == "" ? 0 : $("#segmento_id").val();
    var bairro = $("#bairro_id").val() == "" || $("#bairro_id").val() == "null" || $("#bairro_id").val() == null ? 0 : $("#bairro_id").val();
    var nao = $("#naocompram").val();
    var compram = $("#temcompras").val();

    var url = url.replace(':empresa',empresa);
    var url = url.replace(':setor',setor);
    var url = url.replace(':tipopessoa',tipopessoa);
    var url = url.replace(':seg',segmento);
    var url = url.replace(':bairro',bairro);
    var url = url.replace(':nao',nao);
    var url = url.replace(':tem',compram);
    
    if(!exportar){
        openModalReport(url,modal);
    } else {
        window.open(url, '_target');
    }
}