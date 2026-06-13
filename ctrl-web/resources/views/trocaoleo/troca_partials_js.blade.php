<script type="text/javascript">
$(document).ready(function () {
    $("#kmtrocaoleo").on('keyup', function () {
        calculoKmRendimento();
    });
    $('#oleorendimento').on('keyup', function () {
        calculoKmRendimento();
    });

    $('#boxalertaoleo').on('click', function () {
        checkAlertaBoxOleo();
    });
    checkAlertaBoxOleo();
});

$("#fmCadastro").submit( function ( e ) {
    if( calcularAlertaKm() ) {
        e.preventDefault();
        bootbox.alert("KM de alerta antes não pode ser maior que o KM da proxima troca.");
        return false;
    }

    if( verificacaokmUltimaTroca() ) {
        e.preventDefault();
        bootbox.alert('O KM da próxima troca não poder ser menor que o da ultima troca.');
        return false;
    }
});

$('#checkboxnfe').on('click', function () {
    checkAlertaBoxOleo();
});

$("#veiculo_id").on('change', function () {
    if( !$(this).isEmpty() ) getVeiculos();
    else resetInputs();
});

function checkAlertaBoxOleo() {
    if ( $("#veiculo_id").prop('disabled') === false ){
        if ( $('#alertaantesoleo').prop('checked') === true ) {
            $('#kmalertaantesoleo').prop('disabled', false);
        } else {
            $('#kmalertaantesoleo').prop('disabled', true);
            $('#kmalertaantesoleo').val('');
        }
    }
}

function calculoKmRendimento() {
    var trocaoleo = parseInt($("#kmtrocaoleo").val());
    var oleorendimento = parseInt($("#oleorendimento").val());

    if (isNaN(trocaoleo) || isNaN(oleorendimento)) {
        if (!isNaN(trocaoleo) && isNaN(oleorendimento)) {
            $("#oleoproximotroca").val(trocaoleo);
        } else if (isNaN(trocaoleo) && !isNaN(oleorendimento)) {
            $("#oleoproximotroca").val(oleorendimento);
        } else {
            $("#oleoproximotroca").val('');
        }
    } else if (isNaN(trocaoleo) && isNaN(oleorendimento)) {
        $("#oleoproximotroca").val('');
    } else {
        var calculo = (oleorendimento + trocaoleo);
        $("#oleoproximotroca").val(calculo);
        $("#oleoproximotroca_hd").val(calculo);
    }
}

function calcularAlertaKm() {
    var kmalerta = parseInt( $("#kmalertaantesoleo").val() );
    var oleoproximatroca = parseInt( $("#oleoproximotroca").val() );

    if ( !isNaN(kmalerta) && !isNaN(oleoproximatroca) ) {
        if ( kmalerta >= oleoproximatroca ) return true;
        else return false;
    }
    return false;
}

function verificacaokmUltimaTroca() {
    var kmultimatroca = parseInt($("#kmultimatrocaoleo").val());
    var kmtrocaoleo = parseInt($("#oleoproximotroca").val());

    if ( kmultimatroca >= kmtrocaoleo ) return true;

    return false;
}

function getVeiculos(){
    var veiculo = $("#veiculo_id").val();
    var url = root + `/gettrocasdeoleo?veiculo=${veiculo}`;
    ajaxGenerator( url,'GET', function( data ) {
        for( var i = 0; i < data.length; i++ ) {
            $("#colaborador_id").val(data[i].colaborador_id).trigger('chosen:updated');
            if( data[i].oleo_ultima !== null ) $("#kmultimatrocaoleo").val( data[i].oleo_ultima );
            else $("#kmultimatrocaoleo").val( data[i].veiculo_ultima );
        }
    }, null, null, true );
}

function resetInputs(){
    $("#kmtrocaoleo").val('');
    $("#kmultimatrocaoleo").val('');
    $("#kmtrocaoleo").val('');
    $("#oleorendimento").val('');
    $("#oleoproximotroca").val('');
    $("#colaborador_id").val('').trigger('chosen:updated');
    $("#oleoproximatroca_hd").val('');
    $("#colaborador_id_hd").val('');
    $("#alertaantesoleo").prop('checked',false);
    checkAlertaBoxOleo();
}
</script>