var nomeCidade, nomeBairro, idBairro, nomeRua, uf, confirm, codIBGE, change = true;
var urlFormCidade = root + '/cidade';
var urlFormRua = root + '/rua';
var urlFormBairro = root + '/bairro';
var urlChangeUf = root + '/cidade/dropdown/:id';
var urlBuscaRuas = root + '/rua/dropdown/:id';
var urlChangeCidade = root + '/bairro/dropdown/:id';
var urlBuscaBairro = root + '/bairro/buscaPorNomeECidade/:bairro/:cidade';
var urlBuscaEstado = root + '/cidade/buscaPorNomeEEstado/:cidade/:estado';
var retornoErros = false;
var callbackSubmitCidade;
var callbackSubmitBairro;
var callbackSubmitRua;
var hasPicker = $.isFunction($.fn.selectpicker);
var alertBairroOpened = false;

$(document).ready(function () {
    if ( hasPicker ) {
        $(".selectpicker").selectpicker('setStyle', 'btn-sm select-picker-style btn-default');
    }

    createShortCuts();

    $("form").on('submit', function(){
        $("#rua_erro_cont").val($("#contrua_id").val());
        $("#bairro_erro_cont").val($("#contbairro_id").val());
        $("#cidade_erro_cont").val($("#contcidade_id").val());
        $("#rua_erro").val($("#rua_id").val());
        $("#bairro_erro").val($("#bairro_id").val());
        $("#cidade_erro").val($("#cidade_id").val());
    });

    $("#popup_rua").on('show.bs.modal', function () {
        $("#descricao_rua").val('');
        setTimeout(function () {
            $("#descricao_rua").focus();
        }, 500);
    });

    $("#popup_bairro").on('show.bs.modal', function () {
        $("#descricao_bairro").val('');
        setTimeout(function () {
            $("#descricao_bairro").focus();
        }, 500);
    });

    $("#popup_cidade").on('show.bs.modal', function () {
        $("#descricao_cidade").val('');
        setTimeout(function () {
            $("#descricao_cidade").focus();
        }, 500);
    });

    var rua_id = null;

    $("#cep").mask('99999-999', {placeholder: " "});
    $("#cep").click(function () {
        $('#cep').select();
    });
    $("#uf").change(function () {
        setInputsEnderecoPadrao('#cep', '#cidade_id', '#uf', '#bairro_id', '#rua_id');
        if (change) {
            buscarCidades(null, 'geral');
        }
    });

    $(".btnNovoCadEnderecoPadrao").on('click', function () {
        setInputsEnderecoPadrao('#cep', '#cidade_id', '#uf', '#bairro_id', '#rua_id');
    });
    $(".btnNovoCadEnderecoCont").on('click', function () {
        setInputsEnderecoContabilista('#contcep', '#contcidade_id', '#contuf', '#contbairro_id', '#contrua_id');
    });

    $("#cidade_id").change(function () {
        setInputsEnderecoPadrao('#cep', '#cidade_id', '#uf', '#bairro_id', '#rua_id');
        if (change) {
            nomeBairro = '';
            changeCidade(null, 'geral');
        }
        $("#cidade_erro").val($(this).val());
    });

    $("#bairro_id").change(function () {
        setInputsEnderecoPadrao('#cep', '#cidade_id', '#uf', '#bairro_id', '#rua_id');
        $("#bairro_erro").val($(this).val());
    });

    $("#rua_id").change(function () {
        setInputsEnderecoPadrao('#cep', '#cidade_id', '#uf', '#bairro_id', '#rua_id');
        $("#rua_erro").val($(this).val());
    });

    $("#contcep").mask('99999-999', {placeholder: " "});
    $("#contcep").click(function () {
        $('#contcep').select();
    });
    $("#contuf").change(function () {
        setInputsEnderecoContabilista('#contcep', '#contcidade_id', '#contuf', '#contbairro_id', '#contrua_id');
        if (change) {
            buscarCidades(null, 'contabilista');
        }
    });

    $('#buscarEndereco').on('focusout', function () {
        shortcut.remove("Space");
    });
    $('#buscarEndereco').on('focusin', function () {
        shortcut.remove("Space");
        shortcut.add("Space", function () {
            $("#buscarEndereco").click();
        });
    });
    $("#contcidade_id").change(function () {
        setInputsEnderecoContabilista('#contcep', '#contcidade_id', '#contuf', '#contbairro_id', '#contrua_id');
        if (change) {
            changeCidade(null, 'contabilista');
        }
        $("#cidade_erro_cont").val($(this).val());
    });

    $("#contbairro_id").change(function () {
        setInputsEnderecoContabilista('#contcep', '#contcidade_id', '#contuf', '#contbairro_id', '#contrua_id');
        $("#bairro_erro_cont").val($(this).val());
    });

    $("#contrua_id").change(function () {
        setInputsEnderecoContabilista('#contcep', '#contcidade_id', '#contuf', '#contbairro_id', '#contrua_id');
        $("#rua_erro_cont").val($(this).val());
    });


    $('#btnContBuscarEndereco').on('focusout', function () {
        shortcut.remove("Space");
    });
    $('#btnContBuscarEndereco').on('focusin', function () {
        shortcut.add("Space", function () {
            $("#btnContBuscarEndereco").click();
        });
    });


    $("form#fmCidade").submit(function () {
        $('#grupo_id_cidade').val($('#grupo_id').val());
        $('#uf_cidade').val($(getIdInputUf(padraoBusca)).val());
        var formData = new FormData($(this)[0]);
        filtrarSelect(getIdInputCidade(padraoBusca), $("#descricao_cidade").val(), function () {
            if (!isEmpty($(getIdInputCidade(padraoBusca)).val())) {
                $('#btnCloseCidade').click();
                return false;
            }
            $.ajax({
                type: "POST",
                url: urlFormCidade,
                data: formData,
                //async: false,
                success: function (data) {
                    if (!isNaN(parseInt(data))) {
                        $("#cidade_erro").val(data);
                        $("#cidade_erro_cont").val(data);
                        buscarCidades(function () {
                            $(getIdInputCidade(padraoBusca)).val(data).trigger('chosen:updated');
                            if (typeof callbackSubmitCidade === "function") {
                                callbackSubmitCidade();
                                callbackSubmitCidade = undefined;
                            }
                        }, padraoBusca);
                        $('#btnCloseCidade').click();
                    } else {
                        bootbox.alert(data);
                    }
                },
                error: function (data) {
                    bootbox.alert(data.responseText);
                },
                cache: false,
                contentType: false,
                processData: false
            });
        });
        return false;
    });
    $("form#fmRua").submit(function () {
        var url = urlFormRua;
        $('#bairro_id_rua').val($(getIdInputBairro(padraoBusca)).val());
        $('#cidade_id_rua').val($(getIdInputCidade(padraoBusca)).val());
        $('#cep_rua').val($(getIdInputCep(padraoBusca)).val());
        $('#importacaocep_id_rua').val('0');

        let formData = new FormData($(this)[0]);

        const callbackPost = function () {
            if (!isEmpty($(getIdInputRua(padraoBusca)).val())) {
                $('#btnCloseRua').click();
                return false;
            }
            $('#btnCloseRua').click();
            let desc = formData.get("descricao");
            insertRua(desc, padraoBusca);
            // $.ajax({
            //     type: "POST",
            //     url: url,
            //     data: formData,
            //     success: function (data) {
            //         if (!isNaN(parseInt(data))) {
            //             rua_id = data;
            //             $("#rua_erro").val(rua_id);
            //             $("#rua_erro_cont").val(rua_id);
            //             $('#btnCloseRua').click();
            //             buscaRuas(function () {
            //                 setTimeout(function () {
            //                     $(getIdInputRua(padraoBusca)).val(rua_id);
            //                     $(getIdInputRua(padraoBusca)).refresh( hasPicker );
            //                 });
            //             }, padraoBusca);
            //         } else {
            //             bootbox.alert(data);
            //         }
            //     },
            //     error: function () {
            //         if ($(getIdInputBairro(padraoBusca)).val() === null) {
            //             bootbox.alert('Por favor selecione o estado e cidade para incluir a rua');
            //         } else {
            //             bootbox.alert('Erro ao incluir rua. Verifique todos os campos e tente novamente.');
            //         }
            //     },
            //     cache: false,
            //     contentType: false,
            //     processData: false
            // });
            nomeRua = '';
        };

        filtrarSelect(getIdInputRua(padraoBusca), $("#descricao_rua").val(), callbackPost);

        return false;
    });
    $('form#fmBairro').submit(function (e) {
        e.preventDefault();
        $('#uf_bairro').val($(getIdInputUf(padraoBusca)).val());
        $('#cidade_id_bairro').val($(getIdInputCidade(padraoBusca)).val());
        var formData = new FormData($(this)[0]);
        filtrarSelect(getIdInputBairro(padraoBusca), $("#descricao_bairro").val(), function () {
            if (!isEmpty($(getIdInputBairro(padraoBusca)).val())) {
                $('#btnCloseBairro').click();
                return false;
            }
            $.ajax({
                type: "POST",
                url: urlFormBairro,
                data: formData,
                //async: false,
                success: function (data) {
                    if (!isNaN(parseInt(data))) {
                        $("#bairro_erro").val(data);
                        $("#bairro_erro_cont").val(data);
                        $('#btnCloseBairro').click();
                        changeCidade(function () {
                            $(getIdInputBairro(padraoBusca)).val(data).refresh( hasPicker );
                            setTimeout(function () {
                                $("#numero").focus();
                            }, 1000);
                        }, padraoBusca, true);
                    } else {
                        bootbox.alert(data);
                    }
                },
                error: function (data) {
                    console.log(data);
                    bootbox.alert('Erro ao incluir bairro: ' + data.responseText);
                },
                cache: false,
                contentType: false,
                processData: false
            });
            nomeBairro = '';
        });
        return false;
    });
});

function buscarCidades(callbackC, padraoPesquisa) {
    let $rua = $(getIdInputRua(padraoPesquisa));
    let $bairro = $(getIdInputBairro(padraoPesquisa));
    let $cid = $(getIdInputCidade(padraoPesquisa));
    $rua.html('').refresh( hasPicker );
    $bairro.html('').refresh( hasPicker );

    var url = urlChangeUf;
    let $uf = $(getIdInputUf(padraoPesquisa));
    if ($uf.val()) {
        url = url.replace(':id', $uf.val());
    } else {
        url = url.replace(':id', uf);
    }
    if ($uf.val()) {
        $.ajax({
            type: "GET",
            url: url,
            //async: false,
            success: function (data) {
                data = data.replace('<select name="cidade_id">', "").replace('</select>', "");

                $cid.html(data).refresh( hasPicker );

                if (typeof callbackC === "function") {
                    callbackC();
                }
            },
            error: function (data) {
                console.log(data);
                bootbox.alert('Erro ao selecionar cidade', function () {
                    $cid.focus().trigger("chosen:activate");
                });
            },
            cache: false,
            contentType: false,
            processData: false
        });
    }
}

function buscaCidadePorNomeEEstado(padraoPesquisa) {
    var url = urlBuscaEstado;
    url = url.replace(':cidade', nomeCidade);
    url = url.replace(':estado', $(getIdInputUf(padraoPesquisa)).val());
    if (typeof nomeCidade !== 'undefined' && typeof $(getIdInputUf(padraoPesquisa)).val() !== 'undefined' && nomeCidade !== '' && $(getIdInputUf(padraoPesquisa)).val() !== '') {
        $.ajax({
            type: "GET",
            url: url,
            //async: false,
            success: function (data) {
                idCidade = data;
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
function preencherBairros(data, padraoPesquisa, callbackB) {
    var html = "<option value=''>Selecione</option>";
    let $bairro = $(getIdInputBairro(padraoPesquisa));
    $bairro.html('');
    for (var i = 0; i < data.length; i++) {
        html = html + "<option value='" + data[i].id + "'>" + data[i].descricao + "</option>";
    }
    $bairro.append(html).refresh( hasPicker );

    if (typeof callbackB === "function") {
        callbackB();
    } else if (typeof callbackB === 'undefined') {
        if ( ! cepEmpresa ) {
            atualizarBairro(padraoPesquisa);
        } else {
            cepEmpresa = false;
            $("#cep").val('');
            nomeBairro = '';
            nomeCidade = '';
            nomeRua = '';
        }
    }
}

function preencherRuas(data, padraoPesquisa, callbackB) {
    var html = "<option value=''>Selecione</option>";
    let $rua = $(getIdInputRua(padraoPesquisa));
    $rua.html('');
    for (var i = 0; i < data.length; i++) {
        html = html + "<option value='" + data[i].id + "'>" + data[i].descricao + "</option>";
    }

    $rua.append(html).refresh( hasPicker );

    if (typeof callbackB === 'undefined') {
        if (!cepEmpresa) {
            atualizarRua(padraoPesquisa);
        }
    } else if (typeof callbackB === "function") {
        callbackB();
    }
}

function changeCidade(callbackB, padraoPesquisa, ignoreRua) {
    if (! ignoreRua) {
        ignoreRua = false;
    }

    if (! ignoreRua) {
        $(getIdInputRua(padraoPesquisa)).html('').refresh( hasPicker );
    }
    var url = urlChangeCidade;
    if ($(getIdInputCidade(padraoPesquisa)).val() !== undefined && $(getIdInputCidade(padraoPesquisa)).val() !== '' && $(getIdInputCidade(padraoPesquisa)).val() !== null) {
        url = url.replace(':id', $(getIdInputCidade(padraoPesquisa)).val());
    } else {
        buscaCidadePorNomeEEstado(padraoPesquisa);
        if (typeof idCidade !== 'undefined') {
            url = url.replace(':id', idCidade);
        }
    }
    var cidade = $(getIdInputCidade(padraoPesquisa)).val();
    if (!isEmpty(cidade)) {
        ajaxGenerator(root + '/getBairrosRuasByCidade/' + cidade, 'GET', function (data) {
            if ( typeof data === "object") {
                preencherBairros(data.bairros, padraoPesquisa, ignoreRua ? callbackB : undefined);
                if (! ignoreRua) {
                    preencherRuas(data.ruas, padraoPesquisa, callbackB);
                }
            }
        });
    }
}
function buscaBairroPorNomeECidade(padraoPesquisa) {
    url = urlBuscaBairro;
    url = url.replace(':bairro', nomeBairro);
    url = url.replace(':cidade', $(getIdInputCidade(padraoPesquisa)).val());
    if (nomeBairro !== 'undefined' && typeof nomeBairro != 'undefined') {
        $.ajax({
            type: "GET",
            url: url,
            //async: false,
            success: function (data) {
                idBairro = data;
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

function buscaRuas(callbackB, padraoPesquisa) {
    var url = urlBuscaRuas;
    let $rua = $(getIdInputRua(padraoPesquisa));
    let $cidad = $(getIdInputCidade(padraoPesquisa));
    if ($cidad.val()) {
        url = url.replace(':id', $cidad.val());
    } else {
        buscaBairroPorNomeECidade(padraoPesquisa);
        url = url.replace(':id', idCidade);
    }
    if ($cidad.val()) {
        $.ajax({
            type: "GET",
            url: url,
            //async: false,
            success: function (data) {
                $rua.html('');
                var html = "<option value=''>Selecione</option>";
                for (var i = 0; i < data.length; i++) {
                    html = html + "<option value='" + data[i].id + "'>" + data[i].descricao + "</option>";
                }
                $rua.append(html);
                if (typeof callbackB === 'undefined') {
                    if (!cepEmpresa) {
                        atualizarRua(padraoPesquisa);
                    }
                } else {
                    callbackB();
                }
            },
            error: function (data) {
                console.log(data);
                bootbox.alert('Erro ao selecionar rua', function () {
                    $rua.focus().trigger("chosen:activate");
                });
            },
            cache: false,
            contentType: false,
            processData: false
        });
    } else {
        $rua.html('');
    }
    $rua.refresh( hasPicker );
}
function buscarCEP(campoCidade, campoUF, campoRua, campoCEP, padraoPesquisa) {
    var callbackClose = function () {
        setTimeout(function () {
            $("#numero").focus();
        }, 600);
    };
    var $cidade = $(campoCidade);
    var $uf = $(campoUF);
    var $rua = $(campoRua);

    if ($uf.val() && $cidade.intVal() && $rua.intVal()) {
        var enderecoCompleto = $uf.val() + "/" + $cidade.find("option:selected").text() + "/" + $rua.find("option:selected").text();
        var url = "//viacep.com.br/ws/";
        url += encodeURI(enderecoCompleto).split('.')[0] + "/json";
        $.ajax({
            type: "GET",
            url: url,
            timeout: 3000,
            success: function (data) {
                if ($.isArray(data) && data.length > 0) {
                    for (let i = 0; i < data.length; i++) {
                        data[i]["selecionar"] = "<button class='btn btn-xs btn-nw-geral'><i class='fa fa-check'></i></button>";
                    }
                    preencherTableEndereco(data, campoCEP, padraoPesquisa);
                } else {
                    bootbox.alert('CEP não encontrado. Verifique se a cidade e o endereço foram preenchidos e tente novamente.', callbackClose);
                }
            },
            error: function () {
                bootbox.alert('CEP não encontrado. O serviço pode estar temporariamente indisponível.', callbackClose);
            },
            cache: false,
        });
    } else {
        bootbox.alert('Favor preencher cidade e endereço para buscar o CEP.', callbackClose);
    }
}

function preencherTableEndereco(data, campoCEP, padraoPesquisa) {
    var $tblCEP = $('#tblCEP');
    $tblCEP.dataTable({
        "language": {"url": urlDataTable},
        "processing": false,
        "bPaginate": true,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": false,
        "bInfo": false,
        "bAutoWidth": false,
        "data": data,
        "destroy": true,
        "sScrollY": "200px",
        "columns": [
            {"data": "bairro"},
            {"data": "cep"},
            {"data": "complemento"},
            {"data": "logradouro"},
            {"data": "localidade"},
            {"data": "selecionar"}
        ]
    });
    $tblCEP.off().on('click', 'tr', function () {
        var trElem = $(this).closest("tr");
        selectCEP(trElem, campoCEP, padraoPesquisa);
    }).on("click", 'button', function () {
        var trElem = $(this).closest("tr");
        selectCEP(trElem, campoCEP, padraoPesquisa);
    });

    $('#popup_cep').modal('show');
    setTimeout(function () {
        $tblCEP.find("button:first").focus();
    }, 1100);
}

function selectCEP(trElem, campoCEP, padraoPesquisa) {
    var cep = $(trElem).children("td")[1];
    var bairro = $(trElem).children("td")[0];
    var rua = $(trElem).children("td")[3];
    if ($(cep).text()) {
        $('#btnCloseCEP').click();
        $(campoCEP).val($(cep).text());
        nomeBairro = $(bairro).text();
        nomeRua = $(rua).text();
        atualizarBairro(padraoPesquisa);
        atualizarRua(padraoPesquisa);
    }
}

function buscarEnderecoPorCepAltern1(cep, padraoPesquisa, callbackClose) {
    var url = "https://api.postmon.com.br/v1/cep/" + cep.replace('-', '');
    $.ajax({
        method: "GET",
        url: url,
        timeout: 2500,
        error: function () {
            bootbox.alert('Endereço não encontrado! Verifique o CEP e tente novamente!', callbackClose);
        },
        success: function (result, textStatus, xhr) {
            if (xhr.status !== 200) {
                bootbox.alert('Erro ao buscar endereço:' + textStatus + '. code: ' + xhr.status);
            } else if (result.status !== 0) {
                var dados = {
                    localidade: result.cidade,
                    bairro: result.bairro,
                    logradouro: result.logradouro,
                    ibge: result.cidade_info.codigo_ibge,
                    uf: result.estado
                };
                confirmaPreencheEndereco(padraoPesquisa, dados)
            } else {
                bootbox.alert('Endereço não encontrado! Verifique o CEP e tente novamente!', callbackClose);
            }
        }
    });
}

$('#popup_cidade').on('shown.bs.modal', function () {
    $('#descricao_cidade').focus();
});

$('#popup_bairro').on('shown.bs.modal', function () {
    $('#descricao_bairro').focus();
});

function buscarEnderecoPorCep(padraoPesquisa) {
    var callbackClose = function () {
        setTimeout(function () {
            $("#cep").focus();
        }, 600);
    };
    var $cep = $(getIdInputCep(padraoPesquisa));
    var url = "https://viacep.com.br/ws/" + $cep.val() + "/json";
    if ($cep.val()) {
        $.ajax({
            method: "GET",
            url: url,
            timeout: 2500,
            error: function () {
                buscarEnderecoPorCepAltern1($cep.val(), padraoPesquisa, callbackClose);
            },
            success: function (dados, textStatus, xhr) {
                if (!("erro" in dados)) {
                    confirmaPreencheEndereco(padraoPesquisa, dados);
                } else if (xhr.status === 118 || xhr.status === 502) {
                    buscarEnderecoPorCepAltern1($cep.val(), padraoPesquisa, callbackClose);
                } else {
                    bootbox.alert('Endereço não encontrado! Verifique o CEP e tente novamente!', callbackClose);
                }
            }
        });
    }
}

function confirmaPreencheEndereco(padraoPesquisa, dados) {
    preencherDadosEndereco(dados);
    let callbackA = function () {
        $(getIdInputUf(padraoPesquisa)).val(uf).refresh( hasPicker );
        buscarCidades(function () {
            atualizarCidade(padraoPesquisa, function () {
                atualizarRua(padraoPesquisa);
            });
            $("#numero").focus();
        }, padraoPesquisa);
    };
    let $rua = $(getIdInputRua(padraoPesquisa));
    let text = $rua.find("option:selected").text();

    if (text !== dados.logradouro && text && text !== "Selecione") {
        let callBackConfirm = function () {
            callbackA();
        };
        bootboxConfirmEnd("Confirmação de Atualização de Endereço", "Deseja atualizar o endereço pelo CEP?", callBackConfirm);
    } else {
        callbackA();
    }
}
function filtrarSelect(input, valorCheck, callback) {
    $(input).val($(input + ' option').filter(function () {
        return replaceSpecialChars($(this).html().toUpperCase()) === replaceSpecialChars(valorCheck.toUpperCase());
    }).val()).trigger('chosen:updated');
    if (typeof callback === 'function')
        callback();
}
function atualizarCidade(padraoPesquisa, callback) {
    change = false;
    let idInput = getIdInputCidade(padraoPesquisa);
    let $city = $(idInput);

    filtrarSelect(idInput, nomeCidade, function () {
        if (! $city.isEmpty()) {
            $city.trigger('chosen:updated').trigger("change");
        }
        if ($city.isEmpty() && nomeCidade) {
            let callbackConfirm = function () {
                $('#descricao_cidade').val(nomeCidade);
                $('#cod_ibge').val(codIBGE);
                $('form#fmCidade').submit();
                callbackSubmitCidade = function () {
                    changeCidade(callback, padraoPesquisa);
                }
            };
            bootboxConfirmEnd("Confirmação de Cadastro de Cidade", nomeCidade + " não encontrada. Deseja cadastrar?", callbackConfirm);
        } else {
            changeCidade(callback, padraoPesquisa);
        }
        change = true;
    });
}
function atualizarBairro(padraoPesquisa) {
    change = false;
    let idInput = getIdInputBairro(padraoPesquisa);
    if (typeof nomeBairro !== "undefined") {
        filtrarSelect(idInput, nomeBairro, function () {
            let $bairro = $(idInput);
            $bairro.refresh( hasPicker );
            if ($bairro.val()) {
                $bairro.trigger("change");
            }
            if ($bairro.isEmpty() && ! $(getIdInputCidade(padraoPesquisa)).isEmpty() && nomeBairro) {
                alertBairroOpened = true;
                ignoreLastFocus = true;
                let callbackConfirm = function () {
                    $('#descricao_bairro').val(nomeBairro);
                    $('form#fmBairro').submit();
                    $bairro.val($(idInput + ' option').filter(function () {
                        return replaceSpecialChars($(this).html().toUpperCase()) === replaceSpecialChars(nomeBairro.toUpperCase());
                    }).val()).trigger('chosen:updated');
                    alertBairroOpened = false;
                    ignoreLastFocus = false;
                };
                let callbackNot = function () {
                    alertBairroOpened = false;
                    ignoreLastFocus = false;
                };
                bootboxConfirmEnd("Confirmação de Cadastro de Bairro", nomeBairro + " não encontrado para esta cidade. Deseja cadastrar?", callbackConfirm, callbackNot, "btnCancelNewBairro")
            }
            change = true;
        });
    }
}
function atualizarRua(padraoPesquisa) {
    change = false;
    let idInput = getIdInputRua(padraoPesquisa);
    let $rua = $(idInput);
    if (typeof nomeRua !== 'undefined') {
        $rua.val($(idInput + ' option').filter(function () {
            if (replaceSpecialChars($(this).html().toUpperCase()) === replaceSpecialChars(nomeRua.toUpperCase())) {
                nomeRua = '';
                return true;
            }
        }).val()).trigger('chosen:updated');
    }
    if ($rua.isEmpty() && nomeRua) {
        let callbackConfirm = function () {
            $('#descricao_rua').val(nomeRua);
            $('form#fmRua').submit();
            if (alertBairroOpened) {
                setTimeout(function () {
                    $(".btnCancelNewBairro").focus();
                }, 750);
            }
        };
        let callbackNot = function () {
            if (alertBairroOpened) {
                setTimeout(function () {
                    $(".btnCancelNewBairro").focus();
                }, 750);
            }
        };
        bootboxConfirmEnd("Confirmação de Cadastro de Rua", nomeRua + " não encontrada. Deseja cadastrar?", callbackConfirm, callbackNot)
    }
    $rua.trigger("change").refresh( hasPicker );
    change = true;
}

function setInputsEnderecoPadrao(cep, cidade, uf, bairro, rua) {
    padraoBusca = 'geral';
    setIdInputCep(cep);
    setIdInputCidade(cidade);
    setIdInputUf(uf);
    setIdInputBairro(bairro);
    setIdInputRua(rua);
}
function setInputsEnderecoContabilista(cep, cidade, uf, bairro, rua) {
    padraoBusca = 'contabilista';
    setIdInputCep(cep);
    setIdInputCidade(cidade);
    setIdInputUf(uf);
    setIdInputBairro(bairro);
    setIdInputRua(rua);
}

function setInputsEnderecoOutros(cep, cidade, uf, bairro, rua) {
    padraoBusca = 'outros';
    setIdInputCep(cep);
    setIdInputCidade(cidade);
    setIdInputUf(uf);
    setIdInputBairro(bairro);
    setIdInputRua(rua);
}

function setIdInputCep(cep) {
    if (padraoBusca === 'geral') {
        idInputCepPadrao = cep;
    } else if (padraoBusca === 'contabilista') {
        idInputCepContabilista = cep;
    } else if (padraoBusca === 'outros') {
        idInputCepOutros = cep;
    } else {
        console.error('padrao não definido');
    }
}
function setIdInputCidade(cidade) {
    if (padraoBusca === 'geral') {
        idInputCidadePadrao = cidade;
    } else if (padraoBusca === 'contabilista') {
        idInputCidadeContabilista = cidade;
    } else if (padraoBusca === 'outros') {
        idInputCidadeOutros = cidade;
    } else {
        console.error('padrao não definido');
    }
}
function setIdInputUf(uf) {
    if (padraoBusca === 'geral') {
        idInputUfPadrao = uf;
    } else if (padraoBusca === 'contabilista') {
        idInputUfContabilista = uf;
    } else if (padraoBusca === 'outros') {
        idInputUfOutros = uf;
    } else {
        console.error('padrao não definido');
    }
}
function setIdInputBairro(bairro) {
    if (padraoBusca === 'geral') {
        idInputBairroPadrao = bairro;
    } else if (padraoBusca === 'contabilista') {
        idInputBairroContabilista = bairro;
    } else if (padraoBusca === 'outros') {
        idInputBairroOutros = bairro;
    } else {
        console.error('padrao não definido');
    }
}
function setIdInputRua(rua) {
    if (padraoBusca === 'geral') {
        idInputRuaPadrao = rua;
    } else if (padraoBusca === 'contabilista') {
        idInputRuaContabilista = rua;
    } else if (padraoBusca === 'outros') {
        idInputRuaOutros = rua;
    } else {
        console.error('padrao não definido');
    }
}
function getIdInputCep(padrao) {
    if (padrao === 'geral') {
        return idInputCepPadrao;
    } else if (padrao === 'contabilista') {
        return idInputCepContabilista;
    } else if (padrao === 'outros') {
        return idInputCepOutros;
    } else {
        console.error('padrao não definido');
    }
}
function getIdInputCidade(padrao) {
    if (padrao === 'geral') {
        return idInputCidadePadrao;
    } else if (padrao === 'contabilista') {
        return idInputCidadeContabilista;
    } else if (padrao === 'outros') {
        return idInputCidadeOutros;
    } else {
        console.error('padrao não definido');
        return erro;
    }
}
function getIdInputUf(padrao) {
    if (padrao === 'geral') {
        return idInputUfPadrao;
    } else if (padrao === 'contabilista') {
        return idInputUfContabilista;
    } else if (padrao === 'outros') {
        return idInputUfOutros;
    } else {
        console.error('padrao não definido');
        return erro;
    }
}
function getIdInputBairro(padrao) {
    if (padrao === 'geral') {
        return idInputBairroPadrao;
    } else if (padrao === 'contabilista') {
        return idInputBairroContabilista;
    } else if (padrao === 'outros') {
        return idInputBairroOutros;
    } else {
        console.error('padrao não definido');
        return erro;
    }
}
function getIdInputRua(padrao) {
    if (padrao === 'geral') {
        return idInputRuaPadrao;
    } else if (padrao === 'contabilista') {
        return idInputRuaContabilista;
    } else if (padrao === 'outros') {
        return idInputRuaOutros;
    } else {
        console.error('padrao não definido');
        return erro;
    }
}

function preencherDadosEndereco(dados) {
    nomeCidade = dados.localidade;
    nomeBairro = dados.bairro;
    nomeRua = dados.logradouro;
    codIBGE = dados.ibge;
    uf = dados.uf;
}

//carrega o endereço básico novamente em caso de erro
function carregarEnderecoErro() {
    setInputsEnderecoPadrao('#cep', '#cidade_id', '#uf', '#bairro_id', '#rua_id');
    var cidade_id = $("#cidade_erro").val();
    idCidade = cidade_id;
    var bairro_id = $("#bairro_erro").val();
    var rua_id = $("#rua_erro").val();
    buscarCidades(function () {
        $(getIdInputCidade('geral')).val(cidade_id);
        $(getIdInputCidade('geral')).refresh( hasPicker );
        changeCidade(function () {
            $(getIdInputBairro('geral')).val(bairro_id);
            $(getIdInputRua('geral')).val(rua_id);
            $(getIdInputRua('geral')).refresh( hasPicker );
            $(getIdInputBairro('geral')).refresh( hasPicker );

            carregarRuaErro('geral');
        }, 'geral');
    }, 'geral');
}

//carrega o endereço de contabilista novamente em caso de erro
function carregarEnderecoErroContabilista() {
    setInputsEnderecoContabilista('#contcep', '#contcidade_id', '#contuf', '#contbairro_id', '#contrua_id');
    var cidade_id = $("#cidade_erro_cont").val();
    idCidade = cidade_id;
    var bairro_id = $("#bairro_erro_cont").val();
    var rua_id = $("#rua_erro_cont").val();
    buscarCidades(function () {
        $(getIdInputCidade('contabilista')).val(cidade_id);
        $(getIdInputCidade('contabilista')).refresh( hasPicker );

        changeCidade(function () {
            $(getIdInputBairro('contabilista')).val(bairro_id);
            $(getIdInputBairro('contabilista')).refresh( hasPicker );

            carregarRuaErro('contabilista');

        }, 'contabilista');
        $(getIdInputRua('contabilista')).val(rua_id);
        $(getIdInputRua('contabilista')).refresh( hasPicker );
    }, 'contabilista');
}

function carregarEndereco(cidade_id, bairro_id, rua_id, inputsPadrao) {
    idCidade = cidade_id;
    if ((cidade_id === null || cidade_id.length === 0) && cepEmpresa === true)
        return;
    buscarCidades(function () {
        $(getIdInputCidade(inputsPadrao)).val(cidade_id);
        $(getIdInputCidade(inputsPadrao)).refresh( hasPicker );

        changeCidade(function () {
            $(getIdInputBairro(inputsPadrao)).val(bairro_id);
            $(getIdInputBairro(inputsPadrao)).refresh( hasPicker );
            setTimeout(function () {
                $(getIdInputRua(inputsPadrao)).val(rua_id);
                $(getIdInputRua(inputsPadrao)).refresh( hasPicker );

                carregarRuaErro(inputsPadrao);
            }, 100);
        }, inputsPadrao);
    }, inputsPadrao);
}

function createShortCuts() {
    shortcutByTarget("#popup_cidade");
    shortcutByTarget("#popup_bairro");
    shortcutByTarget("#popup_rua");
}

function shortcutByTarget(target) {
    $("[data-target='" + target + "']").on("focusin", function () {
        var $that = $(this);
        $that.children("i").addClass("activated");
        shortcut.add("space", function () {
            $that.trigger("click");
        });
    }).on("focusout", function () {
        $(this).children("i").removeClass("activated");
        shortcut.remove("space");
    });
}

$(document).on("hide.bs.modal", "#popup_contato, #popup_cidade, #popup_bairro, #popup_rua, #popup_cep", function () {
    adjustFocusOnModal(this);
});

function adjustFocusOnModal(that) {
    var id = $(that).attr("id");
    setTimeout(function () {
        switch (id) {
            case "popup_contato":
                $("#btnAddFollowUp").focus();
                break;
            case "popup_cidade":
                $("#bairro_id").focus().trigger("chosen:activate");
                break;
            case "popup_bairro":
                $("#rua_id").focus().trigger("chosen:activate");
                break;
            default:
                if (! alertBairroOpened) {
                    $("#numero").focus();
                }
                break;
        }
    }, 1000);
    setTimeout(function () {
        shortcut.add("escape", function () {
            $(that).modal("hide");
        });
    }, 500);
    clearFormCliente = false;
}

$(document).on('shown.bs.modal', ".bootbox.modal", function () {
    onOpenModal($(this));
});

function onOpenModal($that) {
    let $btn = $that.find('.btn-nw-geral:last');
    setTimeout(function () {
        $btn.focus();
    }, 600);
}

function bootboxConfirmEnd(title, message, callback, callbackNot, classCancel) {
    if (! classCancel) {
        classCancel = "";
    }
    bootbox.confirm({
        title: title,
        message: message,
        buttons: {
            cancel: {
                label: "Não",
                className: "btn-nw-geral pull-center " + classCancel
            },
            confirm: {
                label: "Sim",
                className: "btn-nw-registro pull-center"
            }
        },
        callback: function (result) {
            if (result) {
                callback();
            } else if (typeof callbackNot === "function") {
                callbackNot();
            }
        }
    });
}

function generateRuaId() {
    let min = 1000;
    let max = 9999;
    let id = Math.floor(Math.random() * (max - min + 1)) + min;
    let input = getIdInputRua(padraoBusca);

    if ($(`${input} option[value='${id}']`).length > 0) {
        generateRuaId();
    }

    return id;
}

function carregarRuaErro(padrao) {
    if (!padrao) padrao = padraoBusca;

    let rua_err = $("#rua_erro").val();

    if (rua_err.startsWith("N"))
        insertRua($("#rua_descricao").val(), padrao);

}

function insertRua(desc, padrao) {
    let $rua = $(getIdInputRua(padrao));
    let id = "N" + generateRuaId();
    let option = new Option(desc, id, false, true);
    $rua.append(option);
    $rua.refresh(hasPicker);
    $rua.trigger("chosen:updated");

    $("#rua_descricao").val(desc);
}
