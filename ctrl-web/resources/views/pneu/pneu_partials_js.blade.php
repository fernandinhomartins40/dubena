<script type="text/javascript">
buscarUrl = '{{URL::to("veiculo/buscarveiculosajax/:id")}}';

setTimeout( function () {
    @if ( isset($show) )
      desativarInputs();
      $("#kmatualpneu").val({{ $veiculopneu->km }});
      $("#quantidadepneu").val({{ $veiculopneu->quantidade }});
      @if ($veiculopneu->alertaantes == 1)
          $("#alertaantespneu").prop("checked", true);
          $("#kmalertaantespneu").val({{ $veiculopneu->kmalertaantes}});
      @endif
    @endif
}, $(document).ready() );

$(document).ready(function () {
    checkAlertaBoxPneu();
});

$( document ).ajaxComplete(function( event, xhr, settings ) {
    kmbanco = $("#kmatualpneu_hd").val();
} );

$('#boxcheckalertapneu').on('click', function () {
    checkAlertaBoxPneu();
});

$("#placa").change( function () {
    if ( !$(this).isEmpty() ) carregarDadosCondVei( buscarUrl );
    else cleanInputs();
} );

$("#fmCadastro").submit(function ( e ) {
    if( checarKmMenor() ) {
        e.preventDefault();
        bootbox.alert("A soma do km na troca com a vida útil não pode ser menor que o km anterior ("+kmbanco+"km)");
        return false;
    }
    
    if( calcularAlertVida() ) {
        e.preventDefault();
        bootbox.alert("Alerta Km antes não pode ser maior que a soma da vida útil com km na troca.");
        return false;
    }
});

function calcularAlertVida() {
    var kmmomento = parseInt( $("#kmatualpneu").val() );
    var alerta = parseInt( $("#kmalertaantespneu").val() );
    var util = parseInt( $("#vidautilkm").val() );

    if ( !isNaN(kmmomento) && !isNaN(alerta) && !isNaN(util) ) {
        var calc = kmmomento + util;
        if ( alerta > calc ) return true;
        else return false;
    }

    return false;
}

function checarKmMenor() {
    var kmmomento = parseInt( $("#kmatualpneu").val() );
    var kmatualbanco = parseInt( $("#kmatualpneu_hd").val() );
    var vidautil = parseInt( $("#vidautilkm").val() );

    if ( !isNaN(kmmomento) && !isNaN(kmatualbanco) && !isNaN(vidautil) ) {
        var calc = kmmomento + vidautil;
        if ( calc > kmatualbanco ) return false;
        else return true;
    }

    return true;
}

function checkAlertaBoxPneu() {
    if ($('#alertaantespneu').prop('checked') === true) {
        $('#kmalertaantespneu').prop('disabled', false);
    } else {
        $('#kmalertaantespneu').prop('disabled', true);
        $('#kmalertaantespneu').val('');
    }
}

function cleanInputs() { 
    $("#kmatualpneu").val('');
    $("#kmatualpneu_hd").val('');
    $("#valor").val('');
    $("#medidapneus").val('');
    $("#vidautilkm").val('');
    $("#quantidadepneu").val('');
    $("#kmalertaantespneu").val('');
    $("#alertaantespneu").prop('checked',false);
    checkAlertaBoxPneu();
}

</script>