///////////////////////////////////////////// Abastecimento ////////////////////////////////
$('#kmatual').blur(function () {
    calculoRendimento();
    var totallitros = $("#totallitros").val();
    if (isNaN(totallitros)) {
        calcularMedia();
    }
});

function calculoRendimento() {
    var kmanterior = parseInt($("#kmanterior").val());
    var kmatual = parseInt($("#kmatual").val());
    if (isNaN(kmatual)) {
        $("#kmrodado").val('');
    } else {
        var calculo = (kmatual - kmanterior);
        if (kmatual > kmanterior) {
            $("#kmrodado").val(calculo);
        } else {
            $("#alertaKm").show();
            $("#alertaKm").fadeTo(4000, 500).slideUp(500, function () {
                $("#alertaKm").slideUp(1000);
            });
            $("#kmrodado").focus();
        }
    }
}
$("#totallitros").blur(function () {
    var totallitros = $("#totallitros").val();
    if (isNaN(totallitros)) {
        calcularMedia();
    } else {
        $("#mediaconsumo").val('');
    }
});
function calcularMedia() {
    var totallitros = $("#totallitros").val().replace(',', '.');
    var kmrodado = parseInt($("#kmrodado").val());
    var kmatual = $("#kmatual").val();
    if (kmatual !== '') {
        if (!isNaN(totallitros) && !isNaN(kmrodado)) {
            var calculo = (kmrodado / totallitros);
            $("#mediaconsumo").val(calculo.toFixed(2).replace('.', ','));
        }
    }
}


////////////////// Ajax ALL /////////////////
function carregarDadosCondVei(buscar) {
    var idveiculo = $("#placa").val();
    var urlveiculo = buscar;
    if (!isEmpty(idveiculo)) {
        urlveiculo = urlveiculo.replace(':id', idveiculo);
        ajaxGenerator( urlveiculo, 'GET', function(data){
            for (var i = 0; i < data.length; i++) {
                //abastecimento
                $("#kmanterior").val(data[i].kmatual);
                $("#totallitros").val('');
                $("#kmrodado").val('');
                $("#mediaconsumo").val('');
                $("#colaborador_id").val(data[i].colaborador_id).trigger('chosen:updated');

                //troca de pneu
                $("#kmatualpneu").val(data[i].kmatual);
                $("#kmatualpneu_hd").val(data[i].kmatual);
            }
            if(!isEmpty($("#colaborador_id_hd").val())){
                $("#colaborador_id").val($("#colaborador_id_hd").val()).trigger('chosen:updated');
                $("#colaborador_id_hd").val('');
            }
        }, null, null, true );
    }
}
////////////////////////// Continua Abastecimento //////////
$("#fmCadastro").submit(function () {
    var mediaconsumo = $("#mediaconsumo").val();
    var kmanterior = $("#kmanterior").val();
    var kmrodado = $("#kmrodado").val();

    $("#mediaconsumo_hd").val(mediaconsumo);
    $("#kmanterior_hd").val(kmanterior);
    $("#kmrodado_hd").val(kmrodado);
});