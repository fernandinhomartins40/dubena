var senhamestra = false;
var informouValegas = 0;
var notClearIdFieldOnHideModal = true;
var salva = false;
var empresa_id = 0;
var justificouAtraso = 0;
var telaMonitoramento = true;
var lastEdited = 0;
var atualiza = true;
var helpDialog = false;
var urlSenhaMestra = $("#rotaSenha").text();
var intervalAtualizacaoPedido = 0;
var tblCodValeGas;
var setores;
var colaboradores;
var submitted = false;
var htmlCondicaoPagamentoOriginal;

$(document).ready(function () {
    htmlCondicaoPagamentoOriginal = $("#modalcondicaopagamento_id").html();
    setores = JSON.parse($("#setores").val());
    colaboradores = JSON.parse($("#colaboradores").val());
    searchColaboradorSetor();
    loadDataPedidos(true);
    checkAutoSearch();
    shortcut.add("F1", showHideHelpDialog);

    tblCodValeGas = $("#tblCodValeGas").DataTable({
        "language": {"url": urlDataTable},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": true,
        "bInfo": false,
        "bAutoWidth": false
    });

    $("#modalSenha").on('hide.bs.modal', function () {
        $("#rotaSenha").text('');
        $("#rotaSenha").append(urlSenhaMestra);
    });

    $("form#fmModalMotivoAtrasoPedido").on('submit', function () {
        if (isEmpty($("#modalpedidomotivoatraso_id").val())) {
            bootbox.alert('Selecione o motivo do atraso do pedido!');
        } else {
            $("#pedidomotivoatraso_id").val($("#modalpedidomotivoatraso_id").val());
            $("#modalvariospedidomotivoatraso_id").val($("#modalpedidomotivoatraso_id").val());
            justificouAtraso = 1;
            if (!$("#modalEditaVariosPedidos").is(":visible"))
                $("form#fmModalEditaPedido").submit();
            else
                $("form#fmModalEditaVariosPedidos").submit();
            $("#modalMotivoAtrasoPedido").modal('hide');
        }
        return false;
    });

    $("#btnValidaGasBolso").on('click', function () {
        $("#modalValidaGasBolso").modal('hide');
        informouValegas = 1;
        if ($("#modalEditaPedido").is(':visible')) {
            $("#fmModalEditaPedido").submit();
        } else {
            $("#fmModalEditaVariosPedidos").submit();
        }
    });

    $("#modalValidaGasBolso").on('hide.bs.modal', function () {
        informouValegas = 0;
    });

    $("form#fmModalEditaVariosPedidos").on('submit', function (e) {
        e.preventDefault();
        var url = root + '/pedido/updateVariosStatus/' + justificouAtraso + '/' + informouValegas;
        var produtos = [];
        if (!checkValeGas()) {
            return false;
        } else {
            produtos = getProdutosValegas();
        }
        if (isEmpty($("#modalvariospedidosituacao_id").val())) {
            bootbox.alert('Selecione o status!');
        } else {
            if (submitted) {
                return false;
            }
            submitted = true;
            var formData = new FormData($(this)[0]);
            formData.append('produtosvalegas', JSON.stringify(produtos));
            formData.append('lastEdited', lastEdited);
            save(formData, url, true);
        }
        return false;
    });

    $("form#fmModalEditaPedido").on('submit', function (e) {
        e.preventDefault();
        $("#modalsetor_id").prop("disabled", false).trigger("chosen:updated");
        var formData = new FormData($(this)[0]);
        var pedido_id = $("#modalpedido_id").val();
        var url = root + '/pedido/editFromMonitoramento/' + pedido_id + '/' + justificouAtraso + '/' + informouValegas;
        var produtos = [];
        if (!checkValeGas()) {
            return false;
        } else {
            produtos = getProdutosValegas();
        }
        if (!isEmpty(pedido_id)) {
            if (submitted) {
                return false;
            }
            submitted = true;
            formData.append('produtosvalegas', JSON.stringify(produtos));
            save(formData, url, false);
        } else {
            bootbox.alert('Erro ao atualizar pedido!');
        }
        return false;
    });
});

$('body').on('click', function () {
    var dropdown = $("iframe").contents().find("#dropdown-cols-great-table");
    if (dropdown.hasClass('open'))
        dropdown.toggleClass('open');
});

$("#btnAddValeGas").on('click', function () {
    addValeGas(empresa_id);
});


//remove os valegas da lista
$("#tblCodValeGas").on('click', 'button', function () {
    removeValegasFromTbl(this);
});

$("#modalMotivoAtrasoPedido").on('shown.bs.modal', function () {
    if ($("#modalEditaVariosPedidos").is(':visible')) {
        $("#divAvisoEditaVariosPedidos").show(0);
    } else {
        $("#divAvisoEditaVariosPedidos").hide(0);
    }
});

$("#modalEditaVariosPedidos").on('show.bs.modal', function () {
    $("#modalvariospedidosituacao_id").focus().trigger('chosen:activate');
});

$("#modalEditaVariosPedidos, #modalEditaPedido").on('hide.bs.modal', function () {
    tblCodValeGas.clear().draw();
});

$("#modalsetor_id").on('change', function () {
    searchColaboradorSetor(null);
});

$("#setor_id").on('change', function () {
    searchColaboradorSetor("all");
});


$(document).on("hidden.bs.modal", ".bootbox.modal", function (e) {
    if (!$('.modal').is(':visible') && atualiza) {
        loadDataPedidos();
        checkAutoSearch();
    }
});

$(document).on("shown.bs.modal", ".bootbox.modal", function (e) {
    clearInterval(intervalAtualizacaoPedido);
});

$("#atualizacaoAuto").on('change', function () {
    loadDataPedidos(true);
    checkAutoSearch();
});

function checkValeGas() {
    if (informouValegas) {
        if (validateProdutosValegas()) {
            return true;
        } else {
            return false;
        }
    } else {
        return true;
    }
}

function validateProdutosValegas() {
    var erroProdutos = false;
    $("#valegasproduto_id option").filter(function () {
        if (!$(this).prop('disabled')) {
            erroProdutos = true;
        }
    });
    if (erroProdutos) {
        bootbox.alert('Você precisa adicionar todos os produtos com vale gás antes de prosseguir!');
        informouValegas = 0;
        return false;
    }
    return true;
}

function getProdutosValegas() {
    var produtos = [];
    tblCodValeGas.rows().every(function () {
        produtos.push(this.data());
    });
    return produtos;
}

//verifica se o checkbox de atualização automática está ativo
function checkAutoSearch() {
    if (!$("#atualizacaoAuto").prop('checked')) {
        clearInterval(intervalAtualizacaoPedido);
    } else {
        setTimeout(function () {
            intervalAtualizacaoPedido = setInterval('loadDataPedidos()', 30000);
        }, 500);
    }
}

$("#btnLimpaCamposAcompanhamento").on('click', function () {
    setTimeout(function () {
        var data = dataAtual();
        $("#empresa_id, #status_id, #setor_id, #colaborador_id").val('').trigger('chosen:updated');
        $("#datafinal, #datainicial").val(data);
        sessionStorage.removeItem("sorting-by-tblAcompanhamentoPedidos");
        loadDataPedidos(true);
    }, 500);
});

$("#btnBuscaAcompanhamentos").on('click', function () {
    loadDataPedidos(true);
});

function showHideHelpDialog() {
    if ($("#modalHelp").is(":visible")) {
        $("#modalHelp").modal('hide');
    } else {
        $("#modalHelp").modal('show');
    }
}

//limpa a table e carrega novos pedidos
function loadDataPedidos(clear) {
    if (!$('#modalEditaPedido, #modalEditaVariosPedidos').is(':visible') || salva) {
        var prevPage = sessionStorage.getItem("prevPage-tblAcompanhamentoPedidos");
        var url = getUrlSearch() + (prevPage === null ? '' : 'page=' + prevPage);
        if (clear)
            url += '&clear=true';
        $("#iframeTable").attr('src', url);
    }
}

function getUrlSearch() {
    var dataInicio = $("#datainicial").val();
    var dataFim = $("#datafinal").val();
    var status_id = !parseInt($("#status_id").val()) ? '' : parseInt($("#status_id").val());
    var setor_id = !parseInt($("#setor_id").val()) ? '' : parseInt($("#setor_id").val());
    var colaborador_id = !parseInt($("#colaborador_id").val()) ? '' : parseInt($("#colaborador_id").val());
    var empresa_id = !parseInt($("#empresa_id").val()) ? '' : parseInt($("#empresa_id").val());
    dataFim = isEmpty(dataFim) ? '' : insertDataOracle(dataFim);
    dataInicio = isEmpty(dataInicio) ? '' : insertDataOracle(dataInicio);
    var baseUrl = root + '/pedidomonitoramento/';
    var parsUrl = '?datainicio=' + dataInicio + '&datafinal=' + dataFim + '&status_id=' + status_id + '&setor_id=' + setor_id;
    parsUrl += '&colaborador_id=' + colaborador_id + '&empresa_id=' + empresa_id + "&";
    var sorting = JSON.parse(sessionStorage.getItem("sorting-by-tblAcompanhamentoPedidos"));
    if (sorting !== null) {
        parsUrl = parsUrl + "sortBy=" + sorting.sort + "&order=" + sorting.order + "&";
        sort = sorting.sort;
        order = sorting.order;
    }
    return baseUrl + 'getPedidos' + parsUrl;
}

//busca os colaboradores por setor
function searchColaboradorSetor(colaborador = "all", callBackColaborador) {
    $("#modalcolaborador_id").empty();
    $("#colaborador_id").empty();
    var setor_id;
    if (colaborador !== "all") {
        setor_id = parseInt($("#modalsetor_id").val());
    } else {
        setor_id = parseInt($("#setor_id").val());
    }
    if (setor_id) {
        var colaboradoresBySetor = colaboradores.where('setor_id', setor_id);
        var html = '';
        if (colaboradoresBySetor.length > 1) {
            html = appendOption("");
        }
        $.each(colaboradoresBySetor, function (i, element) {
            html += appendOption(element.id, element.nome);
        });
        if (colaborador === "all") {
            $("#colaborador_id").append(html).trigger('chosen:updated');
        }
        $("#modalcolaborador_id").append(html).trigger('chosen:updated');
        if (typeof callBackColaborador === 'function') {
            callBackColaborador();
        }
    }
    $("#colaborador_id").trigger('chosen:updated');
    $("#modalcolaborador_id").trigger('chosen:updated');
}

function buscaInfoEmpresaPedidos(setor = "all", empresa_id = null, callback = null) {
    empresa_id = !parseInt(empresa_id) ? parseInt($("#empresa_id").val()) : parseInt(empresa_id);
    if (empresa_id) {
        preencherSelectSetor(setores.where('empresa_id', empresa_id), setor === "all", function () {
            if (typeof callback === "function") {
                callback();
            }
        });
}
}
$("#empresa_id").on('change', function () {
    $("#setor_id").empty();
    $("#colaborador_id").empty();
    $(".selectChosen").trigger('chosen:updated');
    if (!isEmpty($(this).val())) {
        $("#divEditaVariosStatus").removeClass('hidden').show();
    } else {
        $("#divEditaVariosStatus").hide();
    }
    setTimeout(function () {
        loadDataPedidos(true);
    }, 100);
    buscaInfoEmpresaPedidos("all");
});

function preencherCamposPedidoEdit(data) {
    buscaInfoEmpresaPedidos(null, data.empresa_id, function () {
        let isFechado = checkFechado(data);

        $("#titleModalEditOne").text('Editando pedido número \"' + data.id + '\"');
        $("#modalpedido_id").val(data.id);
        $("#modalsetor_id").val(data.entregasetor_id)
            .prop("disabled", isFechado)
            .trigger('chosen:updated')
            .change();
        $("#modalpedidosituacao_id").val(data.pedidosituacao_id).trigger('chosen:updated');
        let $condPgto = $("#modalcondicaopagamento_id");
        if (data.condicaoPagamento) {
            $condPgto.html(data.condicaoPagamento);
        } else {
            $condPgto.html(htmlCondicaoPagamentoOriginal);
        }
        $condPgto.val(data.condicaopagamento_id).trigger('chosen:updated');
        $("#modalcolaborador_id").val(data.colaborador_id).trigger('chosen:updated');
        $("#modalEditaPedido").modal('show');
    });
}

//preenche os selects após realizar as buscas
function preencherSelectSetor(setoresByEmpresa, getAll, callback) {
    var html = '';
    if (setoresByEmpresa.length > 1) {
        html = appendOption('', 'Selecione');
    }
    $.each(setoresByEmpresa, function (i, element) {
        html += appendOption(element.id, element.descricao);
    });
    if (getAll) {
        $("#setor_id").empty().append(html).trigger('chosen:updated');
    }
    $("#modalsetor_id").empty().append(html).trigger('chosen:updated');
    $(".selectChosen").trigger('chosen:updated');

    if (typeof callback === 'function') {
        callback();
    }
}

function appendOption(value, text) {
    if (!text) {
        text = "Selecione";
    }
    return "<option value='" + value + "'>" + text + '</option>';
}

function geraEmite(id, gerou, nfce_id) {
    if (!gerou) {
        var title = "Gerar Nota fiscal?";
        var message = "Deseja gerar NFCe?";
        bootbox.confirm({
            title: title,
            message: message,
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
            callback: function (result) {
                if (result) {
                    $("#pedido_id_nf").val(id);
                    $('#btn-close-tiponf').html('<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>');
                    $("#modal-tiponf").modal('show');
                }
            }
        });
    } else {
        consultarNf(nfce_id);
    }
}

function editaVarios(ids, empresa_id, verificouSenha = false) {
    if (ids.length == 0) {
        bootbox.alert('Selecione ao menos um pedido para editar!');
        return;
    }
    empresa_id = $("#empresa_id").val();
    if (typeof empresa_id === 'undefined' || (typeof empresa_id !== 'undefined' && empresa_id == 0)) {
        bootbox.alert("Eroo eo verificar empresa do pedido");
        return;
    }
    $("#arraypedidos_id").val(JSON.stringify(ids));
    if (verificouSenha) {
        $("#modalEditaVariosPedidos").modal('show');
        senhamestra = false;
    } else {
        ajaxGenerator(root + '/pedido.isFechado?ids=' + $("#arraypedidos_id").val(), "GET", function (data) {
            var callbackPosSenha = function () {
                editaVarios(ids, empresa_id, true);
            };
            if (data === 'senha') {
                pedeSenha(empresa_id, callbackPosSenha);
            } else {
                senhamestra = false;
                $("#modalEditaVariosPedidos").modal('show');
            }
        });
}
}

function editaUm(id, td, nome_informal) {
    if (typeof nome_informal === 'undefined') {
        bootbox.alert("Erro eo verificar empresa do pedido");
        return;
    }
    empresa_id = getEmpresaByNomeFantasia(nome_informal);
    if (parseInt(td) === parseInt(id) && parseInt(id)) {
        var url = root + '/pedido/buscaPorId/' + id + '/' + senhamestra;
        ajaxGenerator(url, "GET", function (data) {
            if (typeof data === 'object' || typeof data === 'array') {
                preencherCamposPedidoEdit(data);
                senhamestra = false;
            } else if (data.substr(0, 5) === 'senha') {
                var callbackPosSenha = function () {
                    editaUm(id, td, nome_informal);
                };
                pedeSenha(empresa_id, callbackPosSenha);
            } else {
                bootbox.alert('Erro ao buscar pedido:' + data);
                senhamestra = false;
            }
        });
    }
}

function pedeSenha(empresa_id, callback) {
    $("#rotaSenha").append('/' + empresa_id);
    callbackSenha = function () {
        senhamestra = true;
        salva = true;
        callback();
        $("#rotaSenha").text('');
        $("#rotaSenha").append(urlSenhaMestra);
    };
    $("#modalSenha").modal('show');
}

function getEmpresaByNomeFantasia(nome_informal) {
    return $("#empresa_id option").filter(function () {
        return $(this).html().equalsStrictIgnoreCase(nome_informal);
    }).val();
}

function save(formData, url, isMany) {
    ajaxGenerator(url, 'POST', function (data) {
        lastEdited = getLastEdited(data);
        if (data.substr(0, 3) === 'OK|') {
            informouValegas = 0;
            justificouAtraso = 0;
            salva = true;
            bootbox.alert('Pedido(s) alterado(s) com sucesso!', function () {
                if (isMany) {
                    $("#modalEditaVariosPedidos").modal('hide');
                } else {
                    $("#modalEditaPedido").modal('hide');
                }
            });
        } else if (data.substr(0, 12) === 'motivoatraso') {
            $("#modalMotivoAtrasoPedido").modal('show');
        } else if (data.substr(0, 7) === 'valegas') {
            if (isMany) {
                data = data.split('<|>')[0];
            } else {
                $("#divPedido_id").hide();
            }

            tblCodValeGas.clear().draw();
            $("#valegasproduto_id").empty();
            var pedido = JSON.parse(data.substr(7, data.length));
            var pedido_id = null;
            informouValegas = 1;
            var html = '';
            $.each(pedido, function (i, el) {
                html += appendOption(el.produto_id, el.produto_descricao);
                pedido_id = el.pedido_id;
            });
            if (isMany) {
                $("#divPedido_id").html('Pedido nº: ' + pedido_id).show();
            }
            $("#valegaspedido_id").val(pedido_id);
            $("#valegasproduto_id").append(html).trigger('chosen:updated');
            setTimeout(function () {
                $("#modalValidaGasBolso").modal('show');
            }, 500);
        } else {
            bootbox.alert('Erro ao editar:' + data);
        }
    }, null, formData, true, function () {
        submitted = false;
    });
}

function getLastEdited(data) {
    if (data === 'OK|') {
        return 0;
    }
    return typeof data.split('<|>')[1] !== 'undefined' && parseInt(data.split('<|>')[1]) ? data.split('<|>')[1] : 0;
}

function checkFechado(pedido) {
    return parseInt(pedido.pedidosituacao.fechadoconcluido) === 1;
}

$("#cod_gasbolso").keyup(function (e) {
    if (e.keyCode === 13)
    addValeGas(empresa_id);        
});
