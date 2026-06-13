////
$("#empresa_id").change( function () {
    var id = $(this).val();
    $("#user_id").empty().trigger('chosen:updated');
    $("#conta_id").empty().trigger('chosen:updated');
    if ( id !== '' ) {
        preencherUsers( id, function () {
            getContas( id );
        });
    }
});

$("#btnFiltrar").click( function () {
    redirect();
});

function preencherUsers ( empresa_id, callback ) {
    var url  = root + `/getuserbyempresa?empresa=${empresa_id}`;
    var html = "<option value=''>Selecione</option>";
    ajaxGenerator( url, 'GET', function ( data ) {
        if ( !data.includes('Empresa na qual') ){ 
            if ( data.length > 0 ) {
                for (var i = 0; i < data.length; i++) {
                    html += "<option value='" + data[i].id + "'>" + data[i].name + "</option>";
                }
                $("#user_id").append(html).trigger('chosen:updated');
            }
        } else {
            $("#user_id").empty().trigger('chosen:updated');
        }
    }, null, null, true );

    if ( typeof callback === "function" ) callback( empresa_id );
}

function getContas( empresa_id ) {
    var url = root + "/api/searchContasByEmpresa/" + empresa_id;
    $("#conta_id").empty();
    var html = '';
    ajaxGenerator(url, 'GET', function (data) {
        if (data.length > 0) {
            $.each(data, function (i, el) {
                html += "<option value=" + el.id + ">" + el.descricao + "</option>";
            });
        }
        $("#conta_id").append(html).trigger('chosen:updated');
    }, null, null, true );
}

function redirect () {
    let conta_id = $("#conta_id").val();
    let url = root + '/report.retroativofiltrar?datainicio=:inicio&datafim=:fim&empresa=:emp&user=:user&conta=:conta';
    let inicio = insertDataOracle($("#datainicio").val());
    let fim = insertDataOracle($("#datafim").val());
    let empresa_id = $("#empresa_id").isEmpty() ? '0' : $("#empresa_id").val();
    let user_id = $("#user_id").isEmpty() ? '0' : $("#user_id").val();
    conta_id = conta_id == 'null' || conta_id == null ? '0' : conta_id;

    url = url.replace(':inicio',inicio);
    url = url.replace(':fim',fim);
    url = url.replace(':emp',empresa_id);
    url = url.replace(':user',user_id);
    url = url.replace(':conta',conta_id);

    openModalReport(url, true);
}