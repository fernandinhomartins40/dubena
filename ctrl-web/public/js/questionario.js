$("#empresa_id").change(function(){
    $("#setor_id").val('').trigger('chosen:updated');
    $("#colaborador_id").val('').trigger('chosen:updated');
    $("#checklist_id").val('').trigger('chosen:updated');
    mudaEstadoSelectReport('empresa_id','setor_id');
    mudaEstadoSelectReport('empresa_id','colaborador_id');
    mudaEstadoSelectReport('empresa_id','checklist_id');
});

$("#setor_id").change(function(){
    $("#colaborador_id").val('').trigger('chosen:updated');
    $("#checklist_id").val('').trigger('chosen:updated');
    mudaEstadoSelectReport('empresa_id','colaborador_id','setor_id');
});

$("#btnFiltraPos").click(function(){
    var url = root + '/report.posvendafiltro?datainicio=:datainicio&datafim=:datafim&empresa=:empresa&setor=:setor' +
                        '&colaborador=:col&tipo=1';
    redirect(url,true);
});

$("#btnPdfPos").click(function(){
    var url = root + '/report.posvendafiltro?datainicio=:datainicio&datafim=:datafim&empresa=:empresa&setor=:setor' +
                        '&colaborador=:col&tipo=2';
    redirect(url,false);
});

function redirect(url,modal){
    var datainicio = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());
    var empresa = $("#empresa_id").val() == null || $("#empresa_id").val() == "null" ? 0 : $("#empresa_id").val();
    var setor = $("#setor_id").val() == null || $("#setor_id").val() == "null" ? 0 : $("#setor_id").val();
    var colaborador = $("#colaborador_id").val() == null || $("#colaborador_id").val() == "null" ? 0 : $("#colaborador_id").val();

    var url = url.replace(':datainicio',datainicio);
    var url = url.replace(':datafim',datafim);
    var url = url.replace(':setor',setor);
    var url = url.replace(':col',colaborador);
    var url = url.replace(':empresa',empresa);
    openModal(url,modal);
}

function openModal(url,modal){
    if(modal){
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    }else{
        window.open(url, '_blank');
    }
}

//Checklists
$("#tipochecklist_id").change(function(){
    var url = root + '/report.checklistajax?empresa=:empresa&tipocheck=:tipo';
    var empresa = $("#empresa_id").val() == null || $("#empresa_id").val() == "null" ? 0 : $("#empresa_id").val();
    var id = $(this).val();
    getChecklists(url,empresa,id);
});

$("#empresa_id").change(function(){
    $("#tipochecklist_id").val('').trigger('chosen:updated');
    $("#checklist_id").empty().trigger('chosen:updated');
});

$("#btnFiltraCheck").click(function(){
    var url = root + '/report.checklistfiltro?empresa=:empresa&tipocheck=:tipo&check=:check&tipo=1';
    var tipo = $("#tipochecklist_id").val();
    if(!isEmpty(tipo)){
        redirectCheck(url,true);
    }else{
        bootbox.alert('Por favor, selecione um tipo de checklist!');
    }
});

$("#btnPdfCheck").click(function(){
    var url = root + '/report.checklistfiltro?empresa=:empresa&tipocheck=:tipo&check=:check&tipo=2';
    var tipo = $("#tipochecklist_id").val();
    if(!isEmpty(tipo)){
        redirectCheck(url,false);
    }else{
        bootbox.alert('Por favor, selecione um tipo de checklist!');
    }
});

function redirectCheck(url,modal){
    var empresa = $("#empresa_id").val() == null || $("#empresa_id").val() == "null" ? 0 : $("#empresa_id").val();
    var tipocheck = $("#tipochecklist_id").val();
    var check = $("#checklist_id").val() == null || $("#checklist_id").val() == "null" ? 0 : $("#checklist_id").val();
    
    var url = url.replace(':empresa',empresa);
    var url = url.replace(':tipo',tipocheck);
    var url = url.replace(':check',check);
    openModal(url,modal);
}

//Ajax
function getChecklists(url,empresa,tipo){
    $("#checklist_id").empty().trigger('chosen:updated');
    var url = url.replace(':empresa',empresa);
    var url = url.replace(':tipo',tipo);
    var html = "";
    if(!isEmpty(tipo)){
        ajaxGenerator(url,'GET',function(data){
            $.each(data,function(empresa,check){
                if(Object.keys(check).length > 0){
                    html += "<optgroup label='" + empresa + "'>";
                    option = "";
                    $.each(check,function(i,val){
                        option += "<option value='"+i+"'>"+requestDataOracle(val,false)+"</option>";
                    });
                    html += option + "</optgroup>";
                }
            });
            $("#checklist_id").append(html).trigger('chosen:updated');
        });
    }
    // console.log(html);
}