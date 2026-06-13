var nomeCidadePedido;
var nomeRuaPedido;
var nomeBairroPedido;
var ufPedido;
var codIBGEPedido;
var changePedido = true;
var oldUf = $("#ufentrega").val();
var oldCidade = $("#entregacidade_id").val();
$(document).ready(function () {

    $('#ufentrega').change(function () {
        if (changePedido) {
            changeUfPedido(null);
        }
    });
    var $cep = $("#entregacep");
    $cep.mask('99999-999', {placeholder: " "});
    $cep.click(function () {
        $cep.select();
    });

    $('#entregacidade_id').change(function () {
        if (changePedido) {
            changeCidadePedido(null);
        }
    });
});
function buscarEnderecoPedidoPorCep() {
    if ($("#entregacep").val() != "") {
        cep = $("#entregacep").val();
        var url = "//viacep.com.br/ws/" + cep + "/json/?";
        $.ajax({
            method: "GET",
            url: url,
            timeout: 2500,
            error: function (xhr) {
                buscarEnderecoPedidoPorCepAltern1(cep);
            },
            success: function (dados, textStatus, xhr) {
                if (!("erro" in dados)) {
                    preencherDadosEnderecoPedido(dados);
                } else if(xhr.status == 118 || xhr.status == 502) {
                    buscarEnderecoPedidoPorCepAltern1(cep);
                } else {
                    bootbox.alert('Endereço não encontrado! Verifique o CEP e tente novamente!');
                }
            }
        });
    }
}

function buscarEnderecoPedidoPorCepAltern1(cep) {
    var url = "http://api.postmon.com.br/v1/cep/" + cep.replace('-', '');
    $.ajax({
        method: "GET",
        url: url,
        timeout: 2500,
        error: function () {
            bootbox.alert('Endereço não encontrado! Verifique o CEP e tente novamente!');
        },
        success: function (result, textStatus, xhr) {
            if(xhr.status != 200) {
                bootbox.alert('Erro ao buscar endereço:' + textStatus + '. code: ' + xhr.status);
            } else if (result.status != 0) {
                var dados  = {
                            localidade: result.cidade,
                            bairro: result.bairro,
                            logradouro: result.logradouro,
                            ibge: result.cidade_info.codigo_ibge,
                            uf: result.estado
                        };
                preencherDadosEnderecoPedido(dados);
            } else {
                bootbox.alert('Endereço não encontrado! Verifique o CEP e tente novamente!');
            }
        }
    });
}

function preencherDadosEnderecoPedido(dados) {
    $("#ufentrega").val(dados.uf).trigger('chosen:updated');
    nomeCidadePedido = dados.localidade;
    nomeBairroPedido = dados.bairro;
    nomeRuaPedido = dados.logradouro;
    ufPedido = dados.localidade;
    codIBGEPedido = dados.ibge;
    changeUfPedido(undefined);
}

function changeUfPedido(callbackC) {
    var url = urlChangeUf;
    var uf = $("#ufentrega").val();
    if (oldUf !== uf || oldCidade !== $("#entregacidade_id").val()) {
        $('#entregabairro_id').empty().trigger('chosen:updated');
    }
    if (uf) {
        url = url.replace(':id', uf);
    } else {
        url = url.replace(':id', ufPedido);
    }
    if (uf && uf !== oldUf) {
        $.ajax({
            type: "GET",
            url: url,
            async: false,
            success: function (data) {
                var $cidade = $('#entregacidade_id');
                $cidade.empty();
                var html = data.replace('<select name="cidade_id">', "").replace('</select>', "");
                $cidade.html(html).trigger('chosen:updated');
                callCallbackCidade(callbackC);
            },
            error: function (data) {
                console.log(data);
                bootbox.alert("Erro ao buscar cidades");
            },
            cache: false,
            contentType: false,
            processData: false
        });
    } else if (oldUf !== uf || typeof callbackC === "function") {
        callCallbackCidade(callbackC);
    }
    oldUf = uf;
}

function callCallbackCidade(callbackC) {
    if (typeof callbackC === "function") {
        callbackC();
    } else if (callbackC === undefined) {
        atualizarCidadePedido();
    } else {
        changeCidadePedido(callbackB);
    }
}

function buscaCidadePorNomeEEstadoPedido() {
    var url = urlBuscaEstado;
    url = url.replace(':cidade', nomeCidadePedido);
    url = url.replace(':estado', $("#ufentrega").val());
    if (typeof nomeCidadePedido !== 'undefined' && typeof $("#ufentrega").val() !== 'undefined' && nomeCidadePedido !== '' && $("#ufentrega").val() !== '') {
        $.ajax({
            type: "GET",
            url: url,
            async: false,
            success: function (data) {
                idCidadePedido = data;
            },
            error: function (data) {
                console.log(data);
            },
            cache: false,
            contentType: false,
            processData: false
        });
    }
}
function changeCidadePedido(callbackB) {
    var url = urlChangeCidade;
    var cidade = $("#entregacidade_id").val();
    if (cidade) {
        url = url.replace(':id', cidade);
    } else {
        buscaCidadePorNomeEEstadoPedido();
        if (typeof idCidadePedido !== 'undefined') {
            url = url.replace(':id', idCidadePedido);
        }
    }
    if (cidade && cidade !== oldCidade) {
        $.ajax({
            type: "GET",
            url: url,
            async: false,
            success: function (data) {
                var $bairro = $("#entregabairro_id");
                var html = data.replace('<select name="bairro_id">', "").replace('</select>', "");
                $bairro.html(html).trigger('chosen:updated');
                callCallbackBairro(callbackB);
            },
            error: function (data) {
                console.log(data);
                bootbox.alert('Erro ao selecionar o bairro');
            },
            cache: false,
            contentType: false,
            processData: false
        });
    } else if (cidade !== oldCidade || typeof callbackB === "function") {
        callCallbackBairro(callbackB);
    }
    oldCidade = cidade;
}

function callCallbackBairro(callbackB) {
    if (typeof callbackB === "function") {
        callbackB();
    } else if (callbackB === undefined) {
        if (!cepEmpresaPedido) {
            atualizarBairroPedido();
        } else {
            cepEmpresaPedido = false;
            $("#entregacep").val('');
            nomeBairroPedido = '';
            nomeCidadePedido = '';
        }
    }
}

function atualizarCidadePedido() {
    changePedido = false;
    $('#entregacidade_id').val($('#entregacidade_id option').filter(function () {
        return replaceSpecialChars($(this).html().toUpperCase()) == replaceSpecialChars(nomeCidadePedido.toUpperCase());
    }).val());
    if ($("#entregacidade_id").val !== '') {
        $('#entregacidade_id').trigger('chosen:updated').change();
    }
    if ($('#entregacidade_id').val() == null && nomeCidadePedido != '' && nomeCidadePedido !== undefined) {
        bootbox.confirm({
            title: "Confirmação",
            message: nomeCidadePedido + " não encontrada. Deseja cadastrar?",
            buttons: {
                cancel: {
                    label: "Não",
                    className: "btn-default pull-center"
                },
                confirm: {
                    label: "Sim",
                    className: "btn-nw-registro pull-center"
                }
            },
            callback: function (result) {
                if (result) {
                    $('#descricao_cidade').val(nomeCidadePedido);
                    $('form#fmCidade').submit();
                    changeCidadePedido(undefined);
                }
            }
        });
    }
    if ($("#entregacidade_id").val() !== '') {
        changeCidadePedido(undefined);
    }
    changePedido = true;
}
function atualizarBairroPedido() {
    changePedido = false;
    $('#entregabairro_id').val($('#entregabairro_id option').filter(function () {
        return replaceSpecialChars($(this).html().toUpperCase()) == replaceSpecialChars(nomeBairroPedido.toUpperCase());
    }).val());
    $('#entregabairro_id').trigger('chosen:updated');
    if ($('#entregabairro_id').val() !== null) {
        $('#entregabairro_id').change();
    }
    if ($('#entregabairro_id').val() == null && $('#entregacidade_id').val() != null && nomeBairroPedido != '') {
        bootbox.confirm({
            title: "Confirmação",
            message: "Bairro " + nomeBairroPedido + " não encontrado para esta cidade. Deseja cadastrar?",
            buttons: {
                cancel: {
                    label: "Não",
                    className: "btn-default pull-center"
                },
                confirm: {
                    label: "Sim",
                    className: "btn-nw-registro pull-center"
                }
            },
            callback: function (result) {
                if (result) {
                    $('#descricao_bairro').val(nomeBairroPedido);
                    $('form#fmBairro').submit();
                    $('#entregabairro_id').val($('#entregabairro_id option').filter(function () {
                        return replaceSpecialChars($(this).html().toUpperCase()) == replaceSpecialChars(nomeBairroPedido.toUpperCase());
                    }).val());
                    $('#entregabairro_id').trigger('chosen:updated');
                }
            }
        });
    }
    changePedido = true;
}
