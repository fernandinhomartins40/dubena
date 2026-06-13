//Obrigatoriedade Campo 10 caso codigo do Campo 05 seja: 201, 202, 203, 204, 208, 301, 302, 303, 304, 307 ou 308
var codCampo10 = ["201", "202", "203", "204", "208", "301", "302", "303", "304", "307", "308"];
//Obrigatoriedade Campo 11 caso codigo do Campo 05 seja: 301, 302, 303, 304 ou 308
var codCampo11 = ["301", "302", "303", "304", "308"];

$( document ).ready( function() {
    var min = new Date( new Date().getFullYear(), new Date().getMonth() - 1, 1);
    var max = new Date( new Date().getFullYear(), new Date().getMonth(), 1);
    $('.mesAno').datetimepicker({
        locale: 'pt-br',
        viewMode: 'months',
        format: 'MM/YYYY',
        minDate: min,
        maxDate: max
    });
    $("input.mesAno").mask('99/9999');
    $("#cod_cred").trigger('change');
    $("#orig_cred").trigger('change');
});

$("#orig_cred").change( function () {
    if ( $(this).val() == "02" ) {
        $("#cnpj_suc").prop('readonly', false);
    } else {
        $("#cnpj_suc").prop('readonly', true);
    }
});

//Campo 06 e 07
$(".camp08").blur( function() {
    camposSoma();
    subtracaoCampos08();
    subtracaoCampos12();
});

//Substração campos 08, 09, 10 e 11
$(".camp12").blur( function() {
    subtracaoCampos08();
    camposSoma();
    subtracaoCampos12();
});

//Substração campos 12, 13, 14, 15, 16 e 17
$(".camp18").blur( function() {
    subtracaoCampos12();
    camposSoma();
    subtracaoCampos08();
});

$("#cod_cred").change( function() {
    if ( $(this).val() ) unlockCamps( $(this).val() );
    else clearcamps();
});

$("#btnGravar").click( function( e ) {
    validacoes( e );
});

//Soma dos campos 06 e 07
function camposSoma() {
    var campo06 = $("#vl_cred_apu").moneyToFloat();
    var campo07 = $("#vl_cred_ext_apu").isEmpty() ? 0 : $("#vl_cred_ext_apu").val().moneyToFloat();
    var calc = campo06 + campo07;
    $("#vl_tot_cred_apu").val(calc.floatToMoney()).trigger('mask.maskMoney');
}

//Substração do Campo 08 pelos campos 09, 10 e 11
function subtracaoCampos08() {
    var campo08 = $("#vl_tot_cred_apu").isEmpty() ? 0 : $("#vl_tot_cred_apu").val().moneyToFloat();
    var campo09 = $("#vl_cred_desc_pa_ant").isEmpty() ? 0 : $("#vl_cred_desc_pa_ant").val().moneyToFloat();
    var campo10 = $("#vl_cred_per_pa_ant").isEmpty() ? 0 : $("#vl_cred_per_pa_ant").val().moneyToFloat();
    var campo11 = $("#vl_cred_dcomp_pa_ant").isEmpty() ? 0 : $("#vl_cred_dcomp_pa_ant").val().moneyToFloat();

    var calc = (campo08 - campo09 - campo10 - campo11);
    var val  = calc < 0 ? 0 : calc.floatToMoney();
    $("#sd_cred_disp_efd").val(val).trigger('mask.maskMoney');
}

//Substração do Campo 12 pelos campos 13, 14, 15, 16 e 17
function subtracaoCampos12() {
    var campo12 = $("#sd_cred_disp_efd").isEmpty() ? 0 : $("#sd_cred_disp_efd").val().moneyToFloat();
    var campo13 = $("#vl_cred_desc_efd").isEmpty() ? 0 : $("#vl_cred_desc_efd").val().moneyToFloat();
    var campo14 = $("#vl_cred_per_efd").isEmpty() ? 0 : $("#vl_cred_per_efd").val().moneyToFloat();
    var campo15 = $("#vl_cred_dcomp_efd").isEmpty() ? 0 : $("#vl_cred_dcomp_efd").val().moneyToFloat();
    var campo16 = $("#vl_cred_trans").isEmpty() ? 0 : $("#vl_cred_trans").val().moneyToFloat();
    var campo17 = $("#vl_cred_out").isEmpty() ? 0 : $("#vl_cred_out").val().moneyToFloat();

    var calc = (campo12 - campo13 - campo14 - campo15 - campo16 - campo17);
    var val  = calc < 0 ? 0 : calc.floatToMoney();
    $("#sld_cred_fim").val(val).trigger('mask.maskMoney');
}

function validacoes( e ) {
    var campo01 = $("#registro");
    var campo02 = $("#per_apu_cred");
    var campo03 = $("#orig_cred");
    var campo04 = $("#cnpj_suc");
    var campo05 = $("#cod_cred");
    var campo06 = $("#vl_cred_apu");
    var campo10 = $("#vl_cred_per_pa_ant");
    var campo11 = $("#vl_cred_dcomp_pa_ant");
    var campo14 = $("#vl_cred_per_efd");
    var campo15 = $("#vl_cred_dcomp_efd");

    if ( campo01.isEmpty() ) {
        e.preventDefault();
        bootbox.alert("Por favor, informe o Registro!");
        return false;
    }

    if ( campo03.isEmpty() ) {
        e.preventDefault();
        bootbox.alert("Por favor, informe uma origem para o crédito!");
        return false;
    }

    if ( campo03.val() === "02" && campo04.isEmpty() ) {
        e.preventDefault();
        bootbox.alert("Caso a Origem do Crédito seja 02, o CNPJ do Cendente do Crédito deve ser informado!");
        return false;
    } else if ( campo03 === "02" && !campo04.isEmpty() ) {
        if ( !valida_cnpj( campo04 ) ) {
            e.preventDefault();
            bootbox.alert('Por favor, informe um CNPJ valido!');
            return false;
        }
    }

    if ( campo02.isEmpty() ) {
        e.preventDefault();
        bootbox.alert('Por favor, informe o mês de apuração!');
        return false
    }

    if ( campo05.isEmpty() ) {
        e.preventDefault();
        bootbox.alert('Por favor, informe o código do tipo de crédito!');
        return false
    }

    if ( campo06.isEmpty() ||  parseDinheiro(campo06.val()) == "0" ) {
        e.preventDefault();
        bootbox.alert('Por favor, informe o valor total do crédito!');
        return false
    }

    if ( $.inArray( campo05, codCampo10 ) !== -1 && campo10.isEmpty() ) campo10.val(0).trigger('mask.maskMoney');

    if ( $.inArray( campo05, codCampo11 ) !== -1 && campo11.isEmpty() ) campo11.val(0).trigger('mask.maskMoney');

    if ( $.inArray( campo05, codCampo10 ) !== -1 && campo14.isEmpty() ) campo14.val(0).trigger('mask.maskMoney');

    if ( $.inArray( campo05, codCampo11 ) !== -1 && campo15.isEmpty() ) campo15.val(0).trigger('mask.maskMoney');
}

function unlockCamps( camp05 ) {
    var camp10 = $("#vl_cred_per_pa_ant");
    var camp11 = $("#vl_cred_dcomp_pa_ant");
    var camp14 = $("#vl_cred_per_efd");
    var camp15 = $("#vl_cred_dcomp_efd");

    if ( $.inArray( camp05, codCampo10 ) !== -1 ) {
        camp10.removeAttr('readonly tabindex');
        camp14.removeAttr('readonly tabindex');
    } else {
        camp10.prop('readonly',true);
        camp14.prop('readonly',true);
        camp10.prop('tabindex',-1);
        camp14.prop('tabindex',-1);
    }

    if ( $.inArray( camp05, codCampo11 ) !== -1 ) {
        camp11.removeAttr('readonly tabindex');
        camp15.removeAttr('readonly tabindex');
    } else {
        camp11.prop('readonly',true);
        camp15.prop('readonly',true);
        camp11.prop('tabindex',-1);
        camp15.prop('tabindex',-1);
    }
}