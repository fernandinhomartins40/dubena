$("#empresa_id").change( function () {
    var id = $(this).val();
    $("#user_id").empty().trigger('chosen:updated');
    if ( id !== '' )  preencherUsers( id );
});

$("#btnIframe").click( function () {
    var empresa = $("#empresa_id").isEmpty();
    if ( !empresa ) redirectModal();
    else bootbox.alert('Selecione a empresa!');
});

function preencherUsers ( empresa_id ) {
    var url  = root + `/getuserbyempresa?empresa=${empresa_id}&support=1`;
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
}

function redirectModal() {
    var inicio = insertDataOracle($("#datainicio").val());
    var fim = insertDataOracle($("#datafim").val());
    var user = $("#user_id").isEmpty() ? '0' : $("#user_id").val();
    var empresa = $("#empresa_id").val();
    var tela = $("#tela_id").val();

    var url = root + `/logs.filtrar?datainicio=${inicio}&datafim=${fim}`+
        `&empresa=${empresa}&user=${user}&tela=${tela}`;

    openModalReport(url, true);
}