$( document ).ready( function () {
    if ( typeof show !== "undefined" ) changeClassValue();
});

function changeClassValue () {
    var $acrescimo_0 = $("#acrescimo_0");
    var $acrescimo_1 = $("#acrescimo_1");
    var tipo   = $("#tipo:checked").val();
    var remove = '';

    if ( tipo == 1 || tipo == 2 ) {
        $acrescimo_0.removeClass('hidden').val('');
        $acrescimo_1.addClass('hidden').val('');
    } else {
        $acrescimo_0.addClass('hidden').val('');
        $acrescimo_1.removeClass('hidden').val('');
    }

    if ( show ) corrigirValor();
}

$("#btnGravar").click( function ( e ) {
    validarCampos( e );
});

function validarCampos( e ) {
    var produto_id = $("#produto_id").isEmpty();
    var valor = $("#acrescimo_0").hasClass('hidden') ? $("#acrescimo_1").isEmpty() : $("#acrescimo_0").isEmpty();
    
    if( produto_id ) {
        e.preventDefault();
        bootbox.alert( 'Por favor, selecione um produto!' );
        return false;
    }

    if( valor ) {
        e.preventDefault();
        bootbox.alert( 'Por favor, informe um valor!' );
        return false;
    }
}

function corrigirValor () {
    var valor = $("#valor_banco").val();
    var tipo  = $("#tipo:checked").val();

    switch ( tipo ) {
        case '1':
            var $campo = $("#acrescimo_0");
            break;
        case '2':
            var $campo = $("#acrescimo_0");
            break;
        default:
            var $campo = $("#acrescimo_1");
            break;
    }
    $campo.val(valor);
}

function esconderDivs () {
    $("#selects").addClass('hidden');
    $("#descricao").removeClass('hidden');
}