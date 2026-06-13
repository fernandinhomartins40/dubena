var num_parcelas = '';
$("#tipo").change(function () {
    verificaTipo();
    $("#intervalo").blur(function () {
        if ($("#num_parcelas").val() === '1' && $("#intervalo").val() !== '0') {
            $("#intervalo").addClass('hasError');
        } else {
            $("#intervalo").removeClass('hasError');
        }
    });
});
function verificaTipo() {
    var tipo = $("#tipo").val();
    $(".avista").hide();
    $(".aprazo").hide();
    $(".aprazooutros").hide();
    $(".cartao").hide();
    $(".aprazocartao").hide();

    if (tipo === '0') {
        $(".avista").show();
        $("#parcelas").empty();
    } else if (tipo === '1') {
        $(".aprazooutros").show();
    } else if (tipo === '2') {
        $(".avista").show();
        $(".cartao").show();
        $("#parcelas").empty();
        $("#dias_primeira").show();
    } else if (tipo === '3') {
        $(".cartao").show();
        $(".aprazocartao").show();
        $("#parcelas").empty();
        $("#dias_primeira").show();
    } else if (tipo === '4') {
        $("#descricao").show();
        $("#dias_primeira").hide();
        $("#parcelas").empty();
    } else {
        $("#descricao").show();
        $("#parcelas").empty();
        $("#dias_primeira").hide();
    }
}
$(document).ready(function () {
    verificaTipo();
    carregarParcelas();
    initSearchbox();

    $("#btnNumParcelasModal").on('click', function () {
        if ($("#num_parcelas_modal").val() !== '') {
            $("#parcelas").show();
            $("#parcelas").html('');
            $("#num_parcelas").val($("#num_parcelas_modal").val());
            $("#inputParcelasRetorno").val('');
            $('#myModal').modal('toggle');
            $("#erro").addClass('hidden');
            var divGroup = '<div class="form-group crud_space">';
            var divColSm2 = '<div class="col-sm-2">';
            var label = '<label class="col-sm-3 control-label input-sm required">Dias para Parcela :num:</label>';
            var label2 = '<label class="col-sm-2 control-label input-sm required">Percentual para Parcela :num:</label>';
            var input = '<input name=":name" id=":id" onkeyup="changeDias(this.id)" type="text" class="input-sm form-control number" value="">';
            var input2 = '<input name=":name" id=":id" onkeyup="changePercentual(this.id)" type="text" class="input-sm form-control " value=":valor">';
            var fechaDiv = "</div>";
            var ultimoId;
            num_parcelas = parseInt($("#num_parcelas_modal").val());
            var divisao_parcelas = 100 / num_parcelas;
            divisao_parcelas = divisao_parcelas.toFixed(2);
            var resto_divisao = (100 - (divisao_parcelas * num_parcelas)).toFixed(2);
            resto_divisao = parseFloat(resto_divisao);
            divisao_parcelas = divisao_parcelas.toString();
            divisao_parcelas = divisao_parcelas.replace('.', ',');
            for (var i = 1; i <= num_parcelas; i++) {
                var inputTemp = input.replace(':name', 'dias' + i);
                var inputTemp = inputTemp.replace(':id', 'id' + i);
                if (i == num_parcelas) {
                    var valorParcial = divisao_parcelas.replace(',', '.');
                    valorParcial = parseFloat(valorParcial);
                    valorParcial = valorParcial + resto_divisao;
                    valorParcial = valorParcial.toFixed(2);
                    valorParcial = valorParcial.toString();
                    var input2Temp = input2.replace(':valor', valorParcial.replace('.', ','));
                } else {
                    var input2Temp = input2.replace(':valor', divisao_parcelas);
                }
                var input2Temp = input2Temp.replace(':name', 'percentual' + i);
                var input2Temp = input2Temp.replace(':id', 'idPercentual' + i);
                var labelTemp = label.replace(':num', i);
                var label2Temp = label2.replace(':num', i);
                var conteudoParcelas = divGroup + labelTemp + divColSm2 + inputTemp + fechaDiv + label2Temp + divColSm2 + input2Temp + fechaDiv + fechaDiv;
                $("#parcelas").append(conteudoParcelas);
                $("#inputParcelasRetorno").val($("#inputParcelasRetorno").val() + conteudoParcelas);
                $("#inputPercentualParcelasRetorno").val($("#inputPercentualParcelasRetorno").val() + divisao_parcelas + '||');
                ultimoId = i;
            }
            $("#idPercentual" + ultimoId).attr('disabled', 'disabled');
        } else {
            $("#num_parcelas_modal").addClass('hasError');
            $("#erro").removeClass('hidden');
        }
    });
});
function changeDias(id) {
    var letras = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZÇç/*-~[]´;+,?=_)`,' + "'&{}" + '.><:¹²³£¢¬ºª°(¨%$#@!" áàâãéèêíïóôõöúçñÁÀÂÃÉÈÍÏÓÔÕÖÚÇÑ';
    input = $("#" + id).val();
    for (i = 0; i < $("#" + id).val().length; i++) {
        if (letras.indexOf($("#" + id).val().charAt(i), 0) != -1) {
            input = input.replace($("#" + id).val().charAt(i), '');
        }
    }
    $("#" + id).val(input);
    $("#inputDiasParcelasRetorno").val('');
    id = id.replace('id', '');
    id = parseInt(id);
    for (var i = 1; i <= id; i++) {
        $("#inputDiasParcelasRetorno").val($("#inputDiasParcelasRetorno").val() + $("#id" + i).val() + '||');
    }
    for (var i = id + 1; i <= num_parcelas; i++) {
        $("#inputDiasParcelasRetorno").val($("#inputDiasParcelasRetorno").val() + $("#id" + i).val() + '||');
    }
}
function changePercentual(id) {
    var total_parcial = 0;
    var parcelas_restantes = 0;
    var divisao_parcelas;
    var ultimoId;
    num_parcelas = $("#num_parcelas").val();
    $("#inputPercentualParcelasRetorno").val('');
    id = id.replace('idPercentual', '');
    id = parseInt(id);
    for (var i = 1; i <= id; i++) {
        var campo = parseFloat($("#idPercentual" + i).val().replace(',', '.'));
        total_parcial = parseFloat(campo) + parseFloat(total_parcial);
        $("#inputPercentualParcelasRetorno").val($("#inputPercentualParcelasRetorno").val() + $("#idPercentual" + i).val() + '||');
    }
    if (total_parcial <= 100) {
        for (var i = id + 1; i <= num_parcelas; i++) {
            ultimoId = i;
        }
        if (id !== (ultimoId - 1)) {
            divisao_parcelas = (100 - total_parcial) / (num_parcelas - id);
        } else {
            divisao_parcelas = 100 - total_parcial;
        }
        if (divisao_parcelas > 0) {
            divisao_parcelas = divisao_parcelas.toFixed(2);
            divisao_parcelas = divisao_parcelas.toString();
            divisao_parcelas = divisao_parcelas.replace('.', ',');
            for (var i = id + 1; i <= num_parcelas; i++) {
                // $("#idPercentual" + i).unmask();
                $("#idPercentual" + i).val(divisao_parcelas + ' %');
                $("#idPercentual" + id).maskMoney({suffix: ' %', decimal: ',', symbolStay: true, allowNegative: false, precision: 2, precisionBefore: 3, affixesStay: true}).trigger('mask.maskMoney');;
                $("#idPercentual" + id).attr('maxlength', 8);
                $("#inputPercentualParcelasRetorno").val($("#inputPercentualParcelasRetorno").val() + divisao_parcelas + '||');
            }
        } else {
            $("#info-alert").hide();
            $("#info-alert").show();
            $("#info-alert").fadeTo(3000, 500).slideUp(500, function () {
                $("#info-alert").slideUp(500);
            });
            $("#idPercentual" + id).val('');
        }
    } else {
        $("#info-alert").hide();
        $("#info-alert").show();
        $("#info-alert").fadeTo(3000, 500).slideUp(500, function () {
            $("#info-alert").slideUp(500);
        });
        $("#idPercentual" + id).val('');
    }
    $("#idPercentual" + id).maskMoney({suffix: ' %', decimal: ',', symbolStay: true, allowNegative: false, precision: 2, precisionBefore: 3, affixesStay: true}).trigger('mask.maskMoney');;
    $("#idPercentual" + id).attr('maxlength', 8);
}

function carregarParcelas(){
    if (typeof retornoPtoPrazo === 'undefined' || retornoPtoPrazo === false) {
        $("#parcelas").append($("#inputParcelasRetorno").val());
    }
    if (typeof $("#inputDiasParcelasRetorno").val() !== 'undefined') {
        var diasRetorno = $("#inputDiasParcelasRetorno").val().split('||');
        var percentualRetorno = $("#inputPercentualParcelasRetorno").val().split('||');
    }
    var ultimoId = 0;
    for (var i = 1; i <= $("#num_parcelas").val(); i++) {
        $("#id" + i).val(diasRetorno[i - 1]);
        $("#idPercentual" + i).val((percentualRetorno[i - 1]).replace('.', ','));
        $("#idPercentual" + i).maskMoney({suffix: ' %', decimal: ',', symbolStay: true, allowNegative: false, precision: 2, precisionBefore: 3, affixesStay: true}).trigger('mask.maskMoney');;
        $("#idPercentual" + i).attr('maxlength', 8);
        ultimoId = i;
    }
    $("#idPercentual" + ultimoId).attr('disabled', 'disabled');
    $("#info-alert").hide();

}
function initSearchbox () {
    $('#searchboxPJFornecedor').selectize({
        valueField: 'id',
        labelField: 'nome',
        searchField: ['nome', 'cnpj'],
        maxOptions: 10,
        options: [],
        create: false,
        render: {
            option: function (item, escape) {
                return '<div>' + escape(item.nome) + '</div>';
            }
        },
        optgroups: [
        {value: 'cliente', label: 'Clientes'}
        ],
        optgroupField: 'class',
        optgroupOrder: ['cliente'],
        load: function (query, callback) {
            if (!query.length)
                return callback();
            $.ajax({
                url: root + '/api/searchFornecedores',
                type: 'GET',
                dataType: 'json',
                data: {
                    q: query
                },
                error: function () {
                    callback();
                },
                success: function (res) {
                    callback(res.data);
                }
            });
        },
        onChange: function (data) {
            $('#cliente_id_erro').val($('#searchboxPJFornecedor').selectize()[0].selectize.getValue());
            if(typeof $('#searchboxPJFornecedor').selectize()[0].selectize.getItem(this.items[0]).context !== 'undefined')
                $('#cliente_nome_erro').val($('#searchboxPJFornecedor').selectize()[0].selectize.getItem(this.items[0]).context.innerText);

        }, onInitialize: function () {
            var existingOptions = JSON.parse(this.$input.attr('data-selectize-value'));
            var self = this;
            if ($("#tipo:checked").val() !== '1') {
                var opt = [{"id": $('#cliente_id_erro').val(), "nome": $('#cliente_nome_erro').val(), 'cnpj': $('#cliente_cpf_cnpj_erro').val()}];
                opt.forEach(function (existingOption) {
                    self.addOption(existingOption);
                    self.addItem(existingOption[self.settings.valueField]);
                });
            }
        }
    });
}