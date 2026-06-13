$(document).ready(function () {
    $('.modal-wide').on('show.bs.modal', function () {
        var height = $(window).height();
        $(this).find('.modal-body').css('max-height', height);
    });
    carregarMovimentoConta();
    $("#btnLimpar").on('click', function () {
        $("#datafinal").val(dataAtual(true, true, true));
        setTimeout(function () {
            $('#btnFiltro').trigger('click');
        }, 100);
    });
});

function carregarMovimentoConta(page = 1) {
    var $frame = $("#iframeTable");
    var date = $("#datafinal").val();
    if (typeof page === "undefined")
        page = 1;
    var url = root + '/api/searchMovimentoContaAtual';
    url += '?q=' + conta_id + '&c=' + contafechamento_id + '&page=' + page + '&datafinal=' + date;
    url += '&issetFechamento=' + issetContaFechamento;
    $frame.attr('src', url);
}

function confirmaFecharCaixa() {
    $('#data_fechamento').val($("#datafinal").val()).prop('readonly', true);
    $('#popup_fecharcaixa').modal('show');
}

function confirmaTransferirCaixa() {
    $('#data_transferencia').val(currentDateTimeComplete());
    $('#valorT').val('');
    $('#popup_transferircaixa').modal('show');
}

window.closeModal = function () {
    $('#popup_recebercaixa').modal('hide');
    $('#popup_financeiro').modal('hide');
};

function abrirTelaEstornar(id, tipo) {
    var texto = "Motivo do estorno da baixa: ";
    if (tipo == "TRA") {
        texto = "Motivo do estorno da transferência: ";
    }
    bootbox.prompt(texto,
            function (result) {
                if (result)
                    estornarLancamentoCaixa(id, result);
            });
}

function abrirTelaReceber() {
    $('#popup_lercodigobarras').modal('hide');
    $('#conta_id_receber').val(conta_id);
    $('#parcelas').val(JSON.stringify(duplicatas));
    duplicatas = [];
    $('#fmAbrirRecebimento').submit();
    $('#popup_recebercaixa').modal('show');
}

function estornarLancamentoCaixa(id, motivo) {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    $.ajax({
        url: root + '/api/estornarLancamentoCaixa',
        type: 'POST',
        dataType: 'json',
        data: {
            "_token": csrf_token,
            contamovimento_id: id,
            contafechamento_id: contafechamento_id,
            motivo: motivo
        },
        success: function (res) {
            if (res.substr(0, 3) == 'OK|') {
                bootbox.alert('Estorno efetivado com sucesso.');
                carregarMovimentoConta(getAtualPage());
            } else {
                bootbox.alert('erro: ' + res);
            }
        },
        error: function (data) {
            errorFunctionAjax(data);
        }
    });
}

function abrirTelaCaixas() {
    window.open(urlCaixaIndex, '_self');
}

function abrirFinanceiro(tipo) {
    $("#fmAbrirFinanceiro").attr('action', urlFmAbrirFinanceiro);
    $('#tipo_lancamento').val(tipo);
    $('#conta_id').val(conta_id);
    $('#fmAbrirFinanceiro').submit();
    $('#popup_financeiro').modal('show');
}

function checkCaixaIsFechado(contadestino_id, data, callback) {
    ajaxGenerator(root + '/api/checkCaixaIsFechado?conta_id=' + contadestino_id + '&data=' + data, 'GET', function (data) {
        if (data == 'NOK') {
            bootbox.alert('Não existe nenhum fechamento para o caixa de destino com a data informada');
        } else if (data == 'FEC') {
            bootbox.confirm({
                title: "Atenção!",
                className: "dontHideEsc",
                message: "O caixa de destino está fechado, deseja continuar?",
                buttons: {
                    confirm: {
                        label: "Sim",
                        className: "btn-nw-registro"
                    },
                    cancel: {
                        label: "Não",
                        className: "btn-nw-geral"
                    }
                },
                backdrop: true,
                closeButton: false,
                callback: function (res) {
                    if (res) {
                        callback();
                    }
                }
            });
        } else {
            callback();
        }
    });
}

function transferirCaixa() {
    checkCaixaIsFechado($('#conta_idT').val(), $('#data_transferencia').val(), function () {
        $('#popup_transferircaixa').modal('hide');
        if ($('#data_transferencia').val() == '') {
            bootbox.alert('Informe a data/hora de transferência desejada.');
            return false;
        }
        if ($('#valorT').val() == '') {
            bootbox.alert('Informe o valor de transferência.');
            return false;
        }
        if ($('#conta_idT').val() == '') {
            bootbox.alert('Informe a conta de destino.');
            return false;
        }
        var url = root + '/api/transferirCaixa';

        var d = new FormData();
        
        d.append('_token', csrf_token);
        d.append('conta_id', conta_id);
        d.append('conta_destino_id', $('#conta_idT').val());
        d.append('data_transferencia', $('#data_transferencia').val());
        d.append('contafechamento_id', contafechamento_id);
        d.append('valor', $('#valorT').val());
        
        ajaxGenerator(url, 'POST', function (res) {
            if (res.substr(0, 3) == 'OK|') {
                var id = res.split('|')[1];
                carregarMovimentoConta(getAtualPage());
            } else {
                bootbox.alert('erro: ' + res);
            }
        }, function (data) {
            errorFunctionAjax(data);
        }, d, false, function () {
            $('#popup_fecharcaixa').modal('hide');
            hideLoaderAjax();
        });
    });
}

function errorFunctionAjax(data) {
    if (typeof (data) == 'object') {
        var msg = '';
        var responseText = '';
        for (var key in data) {
            if (key == 'responseJSON') {
                for (var key1 in data['responseJSON']) {
                    msg += data['responseJSON'][key1];
                }
            }
            if (key == 'responseText') {
                responseText = data['responseText'];
            }
        }
        if (msg != '')
            bootbox.alert('Erro ao executar a operação: ' + msg);
        else
            bootbox.alert('Erro ao executar a operação: ' + responseText);
    } else if (typeof (data) == 'string') {
        bootbox.alert('Erro ao fechar o caixa: ' + data);
    } else {
        bootbox.alert('Houve um erro desconhecido ao executar a operação!');
    }
}

function fecharCaixa() {
    if ($('#data_fechamento').val() == '') {
        bootbox.alert('Informe a data de fechamento desejada.');
        return false;
    }
    showLoaderAjax("Aguarde", "Fechando Caixa..", false);
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    $.ajax({
        url: root + '/api/fecharCaixa',
        type: 'POST',
        dataType: 'json',
        data: {
            "_token": csrf_token,
            conta_id: conta_id,
            data_fechamento: $('#data_fechamento').val(),
        },
        success: function (res) {
            console.log(res);
            if (res.substr(0, 3) == 'OK|') {
                id = res.split('|')[1];
                fechouCaixa = true;
                abrirTelaCaixas();
            } else if (res.substr(0, 4) == 'OPEN') {
                window.location.href = root + '/financeiro.abrirTelaCaixa/' + res.substr(4, res.length) + '|';
            } else {
                bootbox.alert('erro: ' + res);
            }
        },
        error: function (data) {
            errorFunctionAjax(data);
        },
        complete: function () {
            $('#popup_fecharcaixa').modal('hide');
            hideLoaderAjax();
        }
    });
}

function updateValueCheques(valorcheques) {
    if (valorcheques !== "R$ 0,00")
        $(".divCheques").html('Cheques Depositados: ' + valorcheques);
    else
        $(".divCheques").html('');
}

$("#popup_fecharcaixa, #popup_transferircaixa, #popup_recebercaixa, #popup_financeiro, #popup_lercodigobarras").on("hide.bs.modal", function () {
    carregarMovimentoConta(getAtualPage());
    $("#iframeTable").get(0).contentWindow.adjustWidth(100);
});

$(document).on("hide.bs.modal", ".bootbox.modal", function () {
    $("#iframeTable").get(0).contentWindow.adjustWidth(100);
});

function getAtualPage() {
    $("#iframeTable").get(0).contentWindow.getAtualPage();
}