$(document).ready(function () {
    toggleValueClass(true);
    $("#codigo").on('keyup', function () {
        $(this).val(replaceSpecialChars($(this).val()));
    });
    $("#fmCadastro").on('submit', function (e) {

        const tipo = $("#tipo:checked").val();
        const total = parseDinheiro($("#valor_string").val(), tipo === '0'? 2 : 0);
        $("#valor").val(total);
    });
});

function toggleValueClass(initializing) {
    const tipo = $("#tipo:checked").val();
    const $valor = $("#valor_string");
    const $label = $('label[for="valor_string"]');
    if (!initializing) {
        $valor.val('0');
    }
    // Desconto em valor fixo
    if (tipo === '0') {
        $valor.maskMoney({prefix: 'R$ ', thousands: '.', decimal: ',', allowNegative: false, allowZero: false})
            .attr('maxlength', 9);
        $label.html('' +
            'Valor (R$):');
    }
    // Desconto em percentual
    else {
        $valor.maskMoney({
            suffix: ' %',
            decimal: ',',
            symbolStay: true,
            allowNegative: false,
            precision: 0,
            precisionBefore: 2,
            affixesStay: true
        })
            .attr('maxlength', 4);
        $label.html('Valor (%):');
    }
    $valor.trigger('mask.maskMoney');
}

function createRandomCode() {
    if (typeof gerarCodigoUrl !== 'undefined') {
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
            },
            type: "GET",
            url: gerarCodigoUrl,
            success: function (data) {
                $("#codigo").val(data.codigo);
            },
            error: function (data) {
                console.log(data);
                bootbox.alert('Erro ao gerar código');
            },
            cache: false,
            contentType: false,
            processData: false
        });
        return false;
    }
}
