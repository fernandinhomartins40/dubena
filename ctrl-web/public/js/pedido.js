var conveniodisponivel = false;
var quantidadepedidos;
var quantidadepremios;
var qdelimitedisponivel = 0;
var comissao;
var ganhouprodutopremio;
var comprasprodutopromocao = 0;
var produtopremiodescricao = "";
var produtopromocao_id;
var premioproduto_id;
var buscaSelectize = false;
var pedidos = true;
var cidadeOriginal;
var callbackSenha;
var clearFormCliente = true;
var pedidoEmAndamento = false;
var notClearIdFieldOnHideModal = true;
var rejeitaLigacoesMotivo = false;
var chamadaAtendida = false;
var empresaConvenio = '';
var preencherCampos = false;
var formSubmetidoPedido = false;
var original_condicaopagamento_id = "0";
var original_situacao_id = 0;
var edit_pedido_id = 0;
var convenioPara = "0";
var qdeLimiteConvenioGeral = 0;
var condicaoPagamentoConfig = false;
var modalCliente = false;
var atendendo = false;
var alterouProdutos = false;
var tempoEntregaUrgente = 0;
var motivoatraso_id = "0";
var tempoEntregaConfig = 0;
var dialog;
var config = null;
var tblProdutosPedido;
var tblHistorico;
var tblModalClientes;
var tblFonesEspera;
// Variavel para determinar se foi adicionado ou removido produtos da grid - Criado por Lucas;
var alterou;
var alterouValor = false;
var alterouData = false;
var cepEmpresaPedido = false;
var editOrShow = false;
var show = false;
var verificouSenhaEdit = false;
var nfce_id = null;
var arrayStatusFechadoConcluido;
var arrayStatusFechadoCancelado;
var arrayStatusFinalizado;
var pedidoController;
var allConfigs = JSON.parse($("#allConfigs").val());
var encerrado = false;
var gasdopovo = false;
var condicaoPagamentoAnt = null;

var dialogProcessPedido = function () {
    dialog = bootbox.dialog({
        closeButton: false,
        class: 'dontHideEsc',
        title: 'Processando pedido! ',
        message: '<p><i class="fa fa-spin fa-spinner"></i> Por favor aguarde..</p>'
    });
};

$(window).on("beforeunload",function(e) {
    if (chamadaAtendida || pedidoEmAndamento) {
        e.preventDefault();

        $("#modalRejeitaLigacoesMotivo").modal('show');

        return null;
    }
});

$(document).ready(function () {
    try {
        $.fn.modal.Constructor.prototype.enforceFocus = function () {};
        addShortcuts();
        putMaskTel();
        initTables();
        viewValidate();
        fieldsEvents();
        shortcutsEventsFields();
        initSelectize();

        let callback;
        if (typeof pedidoController !== 'undefined') {
            callback = function () {
                edit_pedido_id = pedidoController.pedido.id;
                changeHistoricoCliente(pedidoController.historico);
                original_condicaopagamento_id = pedidoController.pedido.condicaopagamento_id + '-' + pedidoController.pedido.condicaopagamento.tipo;
                encerrado = pedidoController.pedido.encerrado;
                original_situacao_id = pedidoController.pedido.pedidosituacao_id;
                motivoatraso_id = pedidoController.pedido.pedidomotivoatraso_id;
                gasdopovo = pedidoController.pedido.gasdopovo=="1";
                $("#produtosconvenio").val(pedidoController.produtosConvenio);

                //atualiza o setor conforme pedido
                $("#entregasetor_id").val(pedidoController.pedido.entregasetor_id).trigger('chosen:updated').trigger('change');
                buscaColaboradorProduto(function () {
                    let $colab = $("#colaborador_id");
                    if (isEmpty($colab.val()))
                        $colab.val(pedidoController.pedido.colaborador_id).trigger('chosen:updated');
                });

                let configT = config;
                //se o cliente possui condições específicas, atualiza as options e o select, senão, somente atualiza o select
                if (pedidoController.condicao_pagamento_cli) {
                    configT = {condicao_pagamento: pedidoController.condicao_pagamento_cli};
                }

                updateCondicaoPagamento(configT, true);
                enableDisableGasdoPovo();
                $("#entregacidade_id").val(pedidoController.pedido.entregacidade_id).trigger('chosen:updated').trigger("change");
                verificaConvenioPromocao(pedidoController.historico[pedidoController.historico.length - 1]);
                updateConvenio();
                setPrecoProduto(pedidoController.preco);

                if (pedidoController.pedido.entregacidade_id != cidadeOriginal) {
                    $("#entregabairro_id").val(pedidoController.pedido.entregabairro_id).trigger("chosen:updated");
                }
            };
        } else {
            callback = function () {
                $("#entregacidade_id").val(config.cidade_id).trigger('chosen:updated');
            };
        }
        changeDataFromConfigEmpresa(callback);
        //Função venda ativa ~Lucas
        if (window.location.search.includes('vendaativa'))
            criarPedidoVendaAtiva();

        $('#condicaopagamento_id').data('previousValue', $('#condicaopagamento_id').val());
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
});

$(window).load(function () {
    try {
        if (!$("#modalSenha").is(':visible')) {
            $("#entregatelefone").focus();
        }
        checkIfEdit();
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
});

$(document).on('shown.bs.modal', ".bootbox.modal", function () {
    onOpenModal($(this));
}).on('hide.bs.modal', changeTooltipCEP);

$(".modal").on('shown.bs.modal', function () {
    onOpenModal($(this));
}).on('hide.bs.modal', changeTooltipCEP);

function onOpenModal($that) {
    try {
        let $btn = $that.find('.btn-nw-geral:last');
        let id = $that.context.id;
        setTimeout(function () {
            if (id === "popup_relatorio") {
                shortcut.remove("F2");
            } else if (id === "modalValidaGasBolso") {
                tblCodValeGas.clear().draw();
                $("#cod_gasbolso").val('').focus();
            } else if (id === "modalCartao") {
                $("#numerocartao_modal").focus();
            } else if (id === "popup_relatorio") {
                //autofocus no nome
            } else {
                let $empresa = $that.find('#empresa_id_modal');
                if (typeof $empresa.val() !== "undefined") {
                    if ($empresa.intVal() > 0) {
                        $btn.focus();
                    } else {
                        $empresa.focus().trigger("chosen:activate");
                    }
                } else {
                    $btn.focus();
                }
            }
        }, 600);
        destroyTooltipCEP();
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//verifica o tipo de pagamento para valida-las
function verificaPagamentos() {
    try {
        var qdeProdutos = 0;
        tblProdutosPedido.rows().eq(0).each(function (i) {
            var row = tblProdutosPedido.row(i);
            var data = row.data();
            if (typeof data !== "undefined" && data[0].indexOf('P') === -1) {
                qdeProdutos += data[3];
            }
        });
        if (condPgtoIsConvenio() && qdeLimiteConvenioGeral < (qdeProdutos)) {
            bootbox.alert("Não há limite disponível para convênio! Remova produdos ou selecione outra forma de pagamento!");
            formSubmetidoPedido = false;
            return false;
        }
        if (config && parseInt(config.pedidovalidacartao) === 1) {
            var hasCartao = (pedidoController
                && pedidoController.pedido
                && !pedidoController.pedido.numerocartao)
                || !pedidoController;
            if (isCondicaoPagamentoCartao() && hasCartao) {
                $("#modalCartao").modal("show");
                formSubmetidoPedido = false;
                return false;
            }
        }
        checkConfigGasBolso();
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function checkConfigGasBolso() {
    try {
        let fechCon = JSON.parse($("#arrayStatusFechadoConcluido").val());
        let condicaoPgto = $("#condicaopagamento_id").val();
        let pedidosituacao_id = $("#pedidosituacao_id").intVal();
        let fechadoconcluido = !!collect(fechCon).get(pedidosituacao_id);;
        if (parseInt(config.validagasbolso) === 1 && !encerrado) {
            if (!isEmpty(condicaoPgto) && condicaoPgto.indexOf('-5') !== -1) {
                checaProdutosValeGas();
            } else {
                gravarPedido();
            }
        } else if (fechadoconcluido && !isEmpty(condicaoPgto) && condicaoPgto.indexOf('-5') !== -1) {
            checaProdutosValeGas();
        } else {
            gravarPedido();
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function addShortcuts() {
    try {
        if (!shortcut.all_shortcuts['f1']) {
            shortcut.add("F1", function () {
                let $modal = $("#modalAtalhos");
                if ($modal.is(":visible")) {
                    $modal.modal('hide');
                } else {
                    $modal.modal('show');
                }
            });
        }

        declareShortcutOpenClient();

        shortcut.add("F3", function () {
            $("#btnGravar").click();
        });

        if (!shortcut.all_shortcuts['f4']) {
            shortcut.add("F4", function () {
                $("#btnNovoPedido").click();
            });
        }
        if (!shortcut.all_shortcuts['ctrl+enter']) {
            shortcut.add("Ctrl+Enter", function () {
                let $extra = $(".extra");
                if ($extra.is(":visible")) {
                    $extra.hide();
                } else {
                    $extra.show();
                }
            });
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//faz uma busca no banco para validar o cartã0
function validaCartaoAjax(num) {
    try {
        var hideModal = function () {
            $("#modalCartao").modal("hide");
        };
        var formData = new FormData();
        formData.append("numero", num);
        formData.append('empresa_id', $("#empresa_id").val());
        if (pedidoController) {
            formData.append('id', pedidoController.pedido.id);
        }
        ajaxGenerator(root + "/validacartao", "POST", function (data) {
            if (data.substr(0, 3) === "OK|") {
                hideModal();
                gravarPedido();
            } else if (data.substr(0, 3) === "NOT") {
                confirmausaCartao(hideModal);
            } else {
                bootbox.alert("Cartão inválido!", verificaPagamentos());
            }
        }, null, formData);
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function confirmausaCartao(hideModal) {
    try {
        var title = "Atenção!";
        var message = "Já foi realizada uma compra com esse cartão, deseja prosseguir com a venda?";
        bootboxConfirm(title, message, function (result) {
            if (result) {
                hideModal();
                $("#modalSenha").modal("show");
                let $inputRoute = $("#rotaSenha");
                var urlSenha = $inputRoute.text();
                $inputRoute.append('/' + $("#empresa_id").val());
                callbackSenha = function () {
                    gravarPedido();
                    $inputRoute.text('');
                    $inputRoute.append(urlSenha);
                };
            } else {
                verificaPagamentos();
            }
        });
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}
//abre modal de pedido de emissão de nf
function emiteNFConfirm(id) {
    try {
        var title = "Pedido gravado com sucesso!";
        var message = "Deseja gerar NFC-e?";
        var callbackConfirm = function (result) {
            if (result) {
                if (nfce_id) {
                    consultarNf(nfce_id);
                } else {
                    openModalNf(id);
                }
            } else {
                finalizaPedido();
            }
        };
        bootboxConfirm(title, message, callbackConfirm);
        chamadaAtendida = false;
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function openModalNf(id) {
    try {
        $("#pedido_id_nf").val(id);
        $("#nfcpfcnpj").val('');
        $("#modal-tiponf").modal('show');
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function finalizaPedido() {
    try {
        if (typeof editOrShow !== 'undefined' && editOrShow)
            fecharJanelaConfirm();
        else
            novoPedido();
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function gravarPedido() {
    try {
        if (tblProdutosPedido.rows().data().length === 0) {
            bootbox.alert('Ao menos um produto deve ser adicionado');
            formSubmetidoPedido = false;
            return false;
        }
        if (isEmpty($("#colaborador_id").val())) {
            bootbox.alert('Selecione o Colaborador');
            formSubmetidoPedido = false;
            return false;
        }
        enbDsbCriticalFields(false);
        var formData = new FormData($("#fmCadastro")[0]);
        if (alterou) {
            formData.append("alterou", alterou);
        }
        let novoFinanceiro = false;
        if (pedidoController) {
            alterouValor = numberFormat($("#valortotalpedidodisabled").val(), 2, "n") !== parseFloat(pedidoController.pedido.valorvenda);
            let data = insertDataHoraOracle($("#datahoraprevisaoentrega").val()).split("_")[0];
            data = "20" + data.split("-")[0] + "-" + data.split("-")[1] + "-" + data.split("-")[2];
            alterouData = pedidoController.pedido.datahoraprevisaoentrega.split(" ")[0] !== data;
            novoFinanceiro = $("#condicaopagamento_id").intVal() !== parseInt(original_condicaopagamento_id) || alterouValor || alterouData;
        }
        if (alterou || novoFinanceiro) {
            formData.append("novoFinanceiro", "1");
        } else {
            formData.append("novoFinanceiro", "0");
        }

        var url = root + "/pedido";
        if (typeof pedidoController !== 'undefined')
            url += "/" + pedidoController.pedido.id;

        var pedidosituacao_id = $("#pedidosituacao_id").intVal();

        let fechadoconcluido = !!collect(arrayStatusFechadoConcluido).get(pedidosituacao_id);
        let finalizado = !!collect(arrayStatusFinalizado).get(pedidosituacao_id);

        var creating = typeof editOrShow === "undefined" || (typeof editOrShow !== "undefined" && !editOrShow);
        var d = new Date();
        var d2 = new Date(typeof $("#entregaurgente:checked").val() === "undefined" ? tempoEntregaConfig : tempoEntregaUrgente);

        if (config.validaatraso == "1" && (finalizado || fechadoconcluido) && !parseInt(motivoatraso_id) && !creating && d.getTime() > d2.getTime()) {
            formSubmetidoPedido = false;
            justificarAtraso();
            return;
        }

        formData.append('pedidomotivoatraso_id', parseInt(motivoatraso_id) ? motivoatraso_id : "");

        if ((editOrShow && fechadoconcluido) || (typeof editOrShow === "undefined" || !editOrShow)) {
            var produtos = [];
            tblCodValeGas.rows().every(function () {
                var d = this.data();
                produtos.push(d);
            });
            formData.append('produtosvalegas', JSON.stringify(produtos));
        }
        if (verificouSenhaEdit || !editOrShow)
            gravarPedidoAjax(url, formData);
        else
            bootbox.alert("Você não digitou a senha mestra para poder gravar este pedido!");
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function justificarAtraso() {
    try {
        $("#modalMotivoAtrasoPedido").modal('show');
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function gravarPedidoAjax(url, formData) {
    try {
        dialogProcessPedido();
        ajaxGenerator(url, "POST",
            function (data) {
                if (typeof data === "string" && data.substr(0, 3) === "OK|") {
                    pedidoEmAndamento = false;
                    var pedido_id = data.substr(3, data.lenght);
                    if (parseInt(config.impressaoautomatica) === 1)
                        gerarComanda(pedido_id);

                    if (parseInt(config.pedidoemitenfce) === 1 && autorizadoCriarNf) {
                        emiteNFConfirm(pedido_id);
                    } else {
                        bootbox.alert("Pedido gravado com sucesso!", function () {
                            chamadaAtendida = false;
                            novoPedido();
                        });
                    }
                    sendPedidoPendente(pedido_id);
                } else if (data.substr(0, 10) === "vendaativa") {
                    //Venda Ativa gerar pedido ~Lucas
                    window.location.href = root + "/vendaativa/" + $("#vendaativa_id").val();
                } else {
                    bootbox.alert("Erro: " + data);
                }
                if (typeof dialog !== "undefined") {
                    dialog.modal('hide');
                }
            }, null, formData, true, function () {
                formSubmetidoPedido = false;
                if (typeof dialog !== "undefined") {
                    dialog.modal('hide');
                }
                if (!editOrShow) {
                    refreshSelectize($("#nomecliente"));
                    refreshSelectize($("#entregarua_id"));
                }
            }
        );
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//calcula o valor total do pedido
function calculaValorTotalPedido() {
    try {
        var partialValue = 0;
        var desconto = $("#valordesconto").val().toString().replace("R$ ", "");
        desconto = desconto.replace(".", "");
        desconto = desconto.replace(",", ".");
        desconto = !parseFloat(desconto) ? 0 : desconto;
        var taxaEntrega = $("#entregataxa").val().toString().replace("R$ ", "");
        taxaEntrega = taxaEntrega.replace(".", "");
        taxaEntrega = taxaEntrega.replace(",", ".");
        taxaEntrega = isEmpty(taxaEntrega) ? 0 : parseFloat(taxaEntrega);
        var total;
        tblProdutosPedido.column(2).data().each(function (value, i) {
            tblProdutosPedido.column(3).data().each(function (qde, j) {
                if (i === j) {
                    value = value.replace("R$ ", "");
                    value = value.replace(".", "");
                    value = value.replace(",", ".");
                    partialValue += qde * value;
                }
            });
        });
        total = partialValue - desconto + taxaEntrega;
        total = total.toFixed(2);
        total = total.replace(".", ",");
        $("#valorvenda").val(total).trigger('mask.maskMoney');
        $("#valortotalpedidodisabled").val(total).trigger('mask.maskMoney');
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//busca os clientes pelo endereço e carrega seus dados em uma modal
function buscaClienteEndereco(url) {
    try {
        var rua_id = $("#rua_id_erro").val();
        var numero = $("#entreganumero").val();
        var complemento = $("#entregacomplemento").val();
        if (rua_id !== "") {
            url = url.replace(":rua", rua_id);
            if (numero !== "") {
                url = url.replace(":num", numero);
            } else {
                url = url.replace(":num", "");
            }
            if (complemento !== "") {
                url = url.replace(":complemento", complemento);
            } else {
                url = url.replace(":complemento", "");
            }
            buscaClientesEnderecoAjax(url);
        } else {
            bootbox.alert("Você deve selecionar uma rua para buscar os clientes");
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function buscaClientesEnderecoAjax(url) {
    try {
        ajaxGenerator(url, 'GET', function (data) {
            tblModalClientes.clear();
            if (data === "NOT") {
                bootbox.alert("Nenhum cliente encontrado para esse endereço!", function () {
                    $("#entregarua_id").selectize()[0].selectize.focus();
                });
            } else if (data !== "ERR") {
                for (var i = 0; i < data.length; i++) {
                    var complemento = data[i].complemento ? data[i].complemento : "";
                    tblModalClientes.row.add([
                        data[i].id, //0
                        data[i].nome, //1
                        data[i].numero, //2
                        complemento, //3
                        parseInt(data[i].cliente) //4
                    ]);
                }
                tblModalClientes.draw(false);
                $("#modalClientes").modal("show");
            } else {
                bootbox.alert("Ocorreu um erro ao buscar clientes!", function () {
                    $("#entregarua_id").selectize()[0].selectize.focus();
                });
            }
        }, function () {
            bootbox.alert("Erro ao buscar clientes", function () {
                $("#entregarua_id").selectize()[0].selectize.focus();
            });
        });
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//busca clientes pelo telefone
function buscaClienteTelefone() {
    try {
        var url = root + "/clientetelefone/buscaclientetelefone/:tel";
        var $tel = $("#entregatelefone");
        if ($tel.val().length > 0) {
            url = url.replace(":tel", $tel.val());
            url = url.replace(" ", "--");
            getCliente(url);
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//busca clientes por id
function buscaClientePorId(id) {
    try {
        var url = root + "/api/buscaClientePorId/:id";
        url = url.replace(":id", id);
        getCliente(url);
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function getCliente(url) {
    try {
        if ($("#modal-tiponf").is(":visible")) {
            return;
        }
        ajaxGenerator(url, "GET", function (data) {
            pedidoEmAndamento = true;
            if (typeof data === 'string' && data.indexOf("As configurações da empresa não foram encontradas!") !== -1) {
                bootbox.alert(data);
            } else if (typeof data['cliente'] !== "undefined") {
                $("#cliente_id").val(data['cliente'].id);
                $("#rejcliente_id").val(data['cliente'].id);
                $("#empresa_id_telefonerejeitado").val(data['cliente'].empresa_id);
                $("#telefonerejeitado").val(data['cliente'].telefone);
                if (parseInt(data['cliente'].cliente) === 0)
                    alteraCadastroFornecedor(data);
                else if (parseInt(data['cliente'].ativo) === 0)
                    ativaClienteConfirm(data);
                else
                    preencherCamposForm(data);
            } else {
                novoCliente();
            }
        });
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//busca o preço dos produtos para os clientes
function setPrecoProduto(data) {
    try {
        var $preco = $("#precoprodutos");
        $preco.val("");
        var produtos = [];
        for (var i = 0; i < data.length; i++) {
            var d = data[i];

            if (d.descontopara == 2) continue;

            d.preco = parseFloat(d.preco) ? parseFloat(d.preco) : 0;
            d.desconto = parseFloat(d.desconto) ? parseFloat(d.desconto) : 0;
            produtos.push(d);
        }
        $preco.val(JSON.stringify(produtos));
        getPrecoProduto();
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//verifica se existe convenio e promoção ativa para o cliente
function verificaConvenioPromocao(data) {
    try {
        if (parseInt(data.participaPromocao) === 1)
            $("#divPromocao").append(data.promo_descricao);
        if (parseInt(data.convenio) === 1)
            empresaConvenio = data.conv_descricao;
        else
            empresaConvenio = '';
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function openIframeCliente(url, stylePopup, styleIframe) {
    try {
        if (!stylePopup) {
            stylePopup = "border: 0; width:100%; height: 100%;margin-top:0px;";
        }
        if (!styleIframe) {
            styleIframe = "border: 0; width:100%; height:550px;margin-top:0px;";
        }
        var $relatorio = $("#popup_relatorio");
        $relatorio.modal('show');
        if (stylePopup !== null) {
            $relatorio.attr('style', stylePopup);
        }
        $("#iFrameReport").attr('style', styleIframe).attr('src', url);
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//função chamada após buscar um cliente pelo telefone e não retornar nada
function novoCliente() {
    try {
        $("#cliente").prop("checked", true);
        $("#id").val("");
        let title = "Atenção!";
        let message = "O telefone não foi encontrado para nenhum cliente, deseja cadastrar um novo?";
        var callbackConfirm = function (result) {
            if (result) {
                resetFormPedido(function () {
                    $("#btnEditCliente").trigger('click');
                });
            } else {
                $("#entregatelefone").focus();
            }
        };
        bootboxConfirm(title, message, callbackConfirm);
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}
//abre uma modal para selecionar a empresa ao cadastrar novo usuário
function selecionarEmpresa() {
    try {
        $("#modalSelecionarEmpresa").modal("show");
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//Novo pedido
function novoPedido() {
    try {
        if (pedidoEmAndamento || (chamadaAtendida && config.pedidocontrolatempoligacoes !== 0))
            $("#modalRejeitaLigacoesMotivo").modal('show');
        else if (editOrShow)
            fecharJanelaConfirm();
        else {
            resetFormPedido();
        }
        return false;
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function resetFormPedido(callback) {
    try {
        alterou = undefined;
        var data = dataAtual(false, true, true, true);
        var selectCliente = $("#nomecliente").selectize()[0].selectize;
        var selectConveniado = $("#cpfConveniadoBusca").selectize()[0].selectize;
        var selectRua = $("#entregarua_id").selectize()[0].selectize;
        $("#cliente_id").val("");
        setConfig(true);
        var $tel = $("#entregatelefone");
        var oldTel = $tel.val();
        $("#fmCadastro").each(function () {
            this.reset();
        });
        alterouProdutos = false;
        $("#divConvenio").text("");
        $("#divPromocao").text("");
        $("#buscarEnderecoEntrega").click();
        $("#datahoraprevisaoentrega").val(data);
        $("#datahoraacaoshow").val(data);
        $("#datahoraacao").val(data);
        $("#entregaurgente").prop('checked', false);
        $(".extra").show();
        $(".selectChosen").trigger("chosen:updated");
        $(".selectChosenClear").empty().trigger("chosen:updated");
        selectConveniado.clearOptions();
        selectConveniado.clear();
        selectCliente.clearOptions();
        selectCliente.clear();
        selectRua.clearOptions();
        selectRua.clear();
        $("#entregacep").val("");
        // var html = $("#condicaopagamento_hidden").html();
        // $("#condicaopagamento_id").empty().append(html).trigger("chosen:updated");
        tblProdutosPedido.clear().draw();
        tblHistorico.clear().draw();
        $("#entregacidade_id").val(cidadeOriginal).trigger('chosen:updated');
        //quando passado um callback, é um novo registro do cliente
        if (typeof callback === "function") {
            $tel.val(oldTel).focus();
            callback();
        } else {
            $tel.focus();
            buscaTelefone(true);
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function confirmEncerraPedido() {
    try {
        var title = "Atenção!";
        var message = "Deseja mesmo encerrar este pedido?";
        var callbackConfirm = function (result) {
            if (result) {
                pedidoEmAndamento = false;
                novoPedido();
            }
        };
        bootboxConfirm(title, message, callbackConfirm);
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//busca o histórico do cliente
function changeHistoricoCliente(data) {
    try {
        tblHistorico.clear();
        if (typeof data === "string" && data.substr(0, 3) !== "ERR") {
            bootbox.alert("Erro:" + data);
        } else if (typeof data === 'object') {
            $.each(data, function (i, element) {
                var style = "color: gray;";
                if (element.id === false) {
                    conveniodisponivel = element.conveniodisponivel;
                    qdelimitedisponivel = element.qdelimitedisponivel;
                    premioproduto_id = element.premioproduto_id + "P";
                    comprasprodutopromocao = parseInt(element.comprasprodutopromocao);
                    quantidadepedidos = parseInt(element.quantidadepedidos);
                    quantidadepremios = parseInt(element.quantidadepremios);
                    produtopromocao_id = parseInt(element.produtopromocao_id);
                    produtopremiodescricao = element.produtopremiodescricao;
                    ganhouprodutopremio = element.ganhouprodutopremio;
                    convenioPara = element.conveniopara;
                    comissao = element.comissao;
                } else {
                    switch (element.status) {
                        case 0:
                            style = "color: blue;";
                            break;
                        case 1:
                            style = "color: red;";
                            break;
                        case 2:
                            style = "color: green;";
                            break;
                        default:
                            break;
                    }
                    var div = "<div style='" + style + "'>";
                    var vencimento = element.vencimento.split(' ');
                    tblHistorico.row.add([
                        div + element.data + "</div>",
                        div + element.descricao_produto + "</div>",
                        div + element.pedidosituacao + "</div>",
                        div + element.quantidade + "</div>",
                        div + element.precovendatotal + "</div>",
                        div + vencimento[0] + "</div>",
                        div + element.condicaopagamento_descricao + "</div>",
                        div + element.id + "</div>"
                    ]);
                }
            });
            conveniodisponivel = true;
            qdeLimiteConvenioGeral = qdelimitedisponivel;
            if (qdelimitedisponivel < 1)
                conveniodisponivel = false;
            tblProdutosPedido.rows().eq(0).each(function (i) {
                var row = tblProdutosPedido.row(i);
                var data = row.data();
                if (typeof data !== "undefined") {
                    qdelimitedisponivel -= parseInt(data[3]);
                }
            });
            if (qdelimitedisponivel < 0)
                qdelimitedisponivel = 0;
            updateConvenio();
        } else {
            bootbox.alert("Erro: " + data);
        }
        tblHistorico.draw(false);
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function updateCondicaoPagamento(data, updateToOriginal = false) {
    try {
        var html = "";
        var $condPgto = $("#condicaopagamento_id");
        if (data.condicao_pagamento.length > 0) {
            html = "<option value> Selecione</option>";
            $.each(data.condicao_pagamento, function (i) {
                var element = data.condicao_pagamento[i];
                if (typeof element !== "undefined") {
                    html += appendOption(element.id + "-" + element.tipo, element.descricao);
                }
            });
        } else {
            if (!condicaoPagamentoConfig) {
                html = $("#condicaopagamento_hidden").html();
            }
            condicaoPagamentoConfig = false;
        }
        if (html) {
            $condPgto.empty();
        }
        $condPgto.append(html).trigger("chosen:updated");
        if (updateToOriginal && parseInt(original_condicaopagamento_id)) {
            $condPgto.val(original_condicaopagamento_id).trigger("chosen:updated");
        }
        enableDisableDesconto();
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function updateConvenio() {
    try {
        var $optionsCond = $("#condicaopagamento_id").find("option");
        $optionsCond.filter(function () {
            $(this).prop("disabled", false).trigger('chosen:updated');
        }).trigger("chosen:updated");
        if (condPgtoIsConvenio()) {
            $("#preco").prop("disabled", true);
            tblProdutosPedido.rows().eq(0).each(function (i) {
                var row = tblProdutosPedido.row(i);
                var data = row.data();
                if (typeof data !== "undefined") {
                    qdelimitedisponivel -= parseInt(data[3]);
                }
            });
        } else {
            $("#preco").prop("disabled", show);
            qdelimitedisponivel = qdeLimiteConvenioGeral;
        }
        var $convenio = $("#divConvenio");
        if (!conveniodisponivel) {
            $convenio.removeAttr("style");
            $convenio.attr("style", "color: red");
            $optionsCond.filter(function () {
                if ($(this).val().indexOf("-4") !== -1) {
                    $(this).prop("disabled", true).trigger('chosen:updated');
                }
            }).trigger("chosen:updated");
        } else {
            $convenio.removeAttr("style");
            $convenio.attr("style", "color: green");
        }
        if (typeof qdelimitedisponivel !== "undefined") {
            $convenio.html('');
            $convenio.append(empresaConvenio + ' - ' + "Disponível: " + qdelimitedisponivel);
            if(gasdopovo){
                $convenio.html('Programa Gás do Povo');
            }
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//atualiza os setores
function updateSetores(data) {
    try {
        var $setor = $("#entregasetor_id");
        $setor.empty();
        var len = data.setores.length;
        if (len > 0) {
            var html = "";
            if (len > 1) {
                html = "<option value>Selecione</option>";
            }
            $.each(data.setores, function (i, element) {
                html += "<option value='" + element.id + "'>" + element.descricao + "</option>";
            });
            $setor.append(html);

        }
        $setor.trigger("chosen:updated");
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function updateProdutosCliente(data) {
    try {
        var $preco = $("#selectProdutosPrecos");
        $preco.empty();
        if (data.cliente_produto.length > 0) {
            var html = "<option value>Selecione</option>";
            $.each(data.cliente_produto, function (i, element) {
                html += "<option value='" + element.id + "'>" + element.descricao + "</option>";
            });
            $preco.append(html);
        }

        $preco.trigger('chosen:updated');
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//atualiza os status
function updateStatus(data) {
    try {
        $("#pedidosituacao_id").val(data.pedidostatuspadrao).trigger('chosen:updated');
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//atualiza as Operacoes
function updateOperacoes(data) {
    try {
        $("#pedidooperacao_id").val(data.operacaodisk).trigger('chosen:updated');
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//busca o telefone na taabela de monitoramentos
function buscaTelefone(btnClick = false) {
    try {
        if (!editOrShow) {
            tblFonesEspera.clear().draw(false);
            var url = root + "/api/searchTelefonesMonitoramento";
            ajaxGenerator(url, "GET", function (data) {
                if (typeof data === "object") {
                    if (data.length > 0) {
                        populateTableCalls(data);
                        var $modal =$("#modalChamadasEspera");
                        if (!$modal.is(':visible'))
                            $modal.modal("show");
                    } else if (btnClick) {

                    }
                } else if (btnClick && typeof data === "string") {
                    bootbox.alert("Erro ao buscar telefones: " + data);
                } else if (btnClick) {
                    bootbox.alert("Erro ao buscar telefones!");
                }
            });
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function populateTableCalls(data) {
    try {
        $.each(data, function (index, element) {
            tblFonesEspera.row.add([
                element.id,
                element.empresa_id,
                element.nome_informal,
                element.telefone,
                "<button id='btnAtender' class='btn btn-xs btn-nw-registro' type='button'>Atender <i class='glyphicon glyphicon-earphone'></i></button> <button id='btnRejeitar' class='btn btn-xs btn-nw-geral' type='button'>Rejeitar <i class='glyphicon glyphicon-remove-circle'></i></button>"
            ]);
        });
        tblFonesEspera.draw();
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//exclui os telefones da tabela de monitoramentos
function excluirTelefoneChamada(id, rejeitaLigacao) {
    try {
        if (typeof id === 'undefined')
            id = $("#telefonechamada_id").val();
        if (parseInt(config.pedidocontrolatempoligacoes) === 0 || rejeitaLigacoesMotivo || !rejeitaLigacao) {
            var url = root + "/excluirTelefoneChamada/" + id;
            var completeFunction = function () {
                setTimeout(function () {
                    atendendo = false;
                }, 500);
            };
            ajaxGenerator(url, "GET", function () {
                tblFonesEspera.clear();
                if (rejeitaLigacao) {
                    buscaTelefone();
                }
            }, null, null, false, completeFunction);
            rejeitaLigacoesMotivo = false;
        } else {
            atendendo = false;
            $("#modalRejeitaLigacoesMotivo").modal('show');
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//coloca a máscara no campo de telefone
function putMaskTel() {
    try {
        var $tel = $("#entregatelefone");
        if ($tel.val().length === 15) {
            $tel.mask("(##) #####-####");
        } else {
            $tel.mask("(##) ####-####");
        }
        $tel.blur(function (event) {
            var target, phone, element;
            target = (event.currentTarget) ? event.currentTarget : event.srcElement;
            phone = target.value.replace(/\D/g, "");
            element = $(target);
            element.unmask();
            if (phone.length > 10)
                element.mask("(99) 99999-9999");
            else
                element.mask("(99) 9999-9999");
        });
        $tel.on("focusin", function () {
            $tel.select();
            $tel.mask("(99) 99999-9999");
        });
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//insere o telefone após a chamada ser atendida
function setTelefoneChamada(tel, id) {
    try {
        chamadaAtendida = true;
        setTelefone(tel);
        $("#modalChamadasEspera").modal("hide");
        $("#btnBuscaClienteTelefone").click();
        excluirTelefoneChamada(id);
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//busca as informações da config da empresa
function changeDataFromConfigEmpresa(callback, data, changeCondPgto) {
    try {
        setConfig();
        if (typeof changeCondPgto === "undefined") {
            changeCondPgto = true;
        }
        if (changeCondPgto) {
            updateCondicaoPagamento(config);
            enableDisableGasdoPovo();
        }
        updateSetores(config);
        if (data) {
            updateProdutosCliente(data);
        }
        //seta o cep da empresa selecionada para preencher nos campos de cliente
        $("#cepEmpresa").val(config.cep);
        cepEmpresa = true;
        $("#cep").val(config.cep);
        $("#buscarEndereco").click();
        if (typeof callback === 'function') {
            callback();
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function addOptionNomeSelectize(select, data) {
    try {
        select.addOption(getOptionsNomeClienteSelectize(data));
        select.addItem(data.cliente_id);
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function getOptionsNomeClienteSelectize(data) {
    try {
        return  [{nome: data.nome,
            rua: {descricao: data.rua_descricao},
            numero: data.numero,
            bairro: {descricao: data.bairro_descricao},
            cidade: {descricao: data.cidade_descricao},
            id: data.cliente_id
        }];
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function preencherCamposForm(d) {
    try {
        let historico = d['historico'];
        let preco = d['cliente_produto'];
        let data = d['cliente'];
        $("#produtosconvenio").val(d['produtosconvenio']);
        let $entregaNum = $("#entreganumero");

        gasdopovo = (parseInt(data.gasdopovo) === 1)
        $("#divConvenio").html("");
        $("#divPromocao").html("");
        tblProdutosPedido.clear().draw();
        tblHistorico.clear().draw();

        setTelefone(data.telefone);

        preencherCampos = true;
        $("#empresa_id").val(data.empresa_id);
        changeDataFromConfigEmpresa(null, data, false);
        $("#cliente_id").val(data.cliente_id);
        changeHistoricoCliente(historico);

        $("#ufentrega").val(data.uf).trigger("chosen:updated");
        changeUfPedido(function () {
            $("#entregacidade_id").val(data.cidade_id).trigger("chosen:updated")
        });
        changeCidadePedido(function () {
            $("#entregabairro_id").val(data.bairro_id).trigger("chosen:updated");
        });
        $entregaNum.val(data.numero);
        $("#entregacomplemento").val(data.complemento);
        $("#entregapontoreferencia").val(data.ponto_referencia);
        $("#entregacep").val(data.cep).trigger("change");
        $("#observacao").val(data.observacoes);
        $("#valordesconto").val("R$ 0,00");
        $("#entregataxa").val("R$ 0,00");
        let $setor = $("#entregasetor_id");
        let $colab = $("#colaborador_id");
        $setor.val(data.setor_id).trigger("chosen:updated").trigger("change");
        if (!isEmpty($setor.val())) {
            let setColaborador_id = function () {
                if (isEmpty($colab.val()))
                    $colab.val(data.colaborador_id).trigger('chosen:updated');
            };
            buscaColaboradorProduto(setColaborador_id);
        }
        
        updateCondicaoPagamento(data);
        verificaConvenioPromocao(historico[historico.length - 1]);
        updateConvenio();
        setPrecoProduto(preco);
        setQuantidadePadrao();
        calculaValorTotalPedido();
        enableDisableGasdoPovo();
        let $nomeCliente = $("#nomecliente");
        let select = $nomeCliente.selectize()[0].selectize;
        clearSelectize($nomeCliente);
        addOptionNomeSelectize(select, data);

        let $rua = $("#entregarua_id");
        let selectRua = $rua.selectize()[0].selectize;
        clearSelectize($rua);
        selectRua.addOption([{descricao: data.rua_descricao, id: data.rua_id}]);
        selectRua.addItem(data.rua_id);
        $("#rua_id_erro").val(data.rua_id);

        if (!$setor.val()) {
            $setor.focus().trigger("chosen:activate");
        } else if (!$colab.val()) {
            $colab.focus().trigger("chosen:activate");
        } else {
            $("#condicaopagamento_id").focus().trigger("chosen:activate");
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//checa se o status do pedido é pendente, se sim, o envia ao colaborador
function sendPedidoPendente(id) {
    try {
        var url = root + '/checaStatusPedido/' + id;
        if (parseInt(config.androidutiliza) === 1) {
            ajaxGenerator(url, "GET", function (data) {
                if (data.substr(0, 3) !== 'OK|') {
                    bootbox.alert('Não foi possível enviar o pedido ao colaborador!');
                }
            }, function () {
                bootbox.alert('Não foi possível enviar o pedido ao colaborador!');
            });
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//valida gas d ebolso
function validaGasBolso() {
    try {
        var erroProdutos = false;
        $("#valegasproduto_id").find("option").filter(function () {
            if (!$(this).prop("disabled"))
                erroProdutos = true;
        });
        if (erroProdutos) {
            bootbox.alert('Você precisa adicionar todos os produtos com vale gás antes de prosseguir!');
            formSubmetidoPedido = false;
            return false;
        }
        gravarPedido();
        $("#modalValidaGasBolso").modal('hide');
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//adiciona protudos na tabela de produtos
function addProdutoToTable(id_produto, produto, valorUnitario, quantidade) {
    try {
        tblProdutosPedido.row.add([
            id_produto,
            produto,
            valorUnitario,
            quantidade,
            "<button class='btn btn-nw-registro btn-xs btn-remocao-produtos' id='removerProduto'>Remover</button>"
        ]).draw();
        alterouProdutos = true;
        calculaValorTotalPedido();
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//adiciona os produtos da tabela no select da modal para validar o valegas por produtos
function checaProdutosValeGas() {
    try {
        var alterouPag = parseInt($("#condicaopagamento_id").val()) !== parseInt(original_condicaopagamento_id);

        var cancelado = false;
        var situacao_id = parseInt($("#pedidosituacao_id").val());
        if (typeof arrayStatusFechadoCancelado !== 'undefined') {
            $.each(arrayStatusFechadoCancelado, function (i) {
                if (parseInt(i) === situacao_id)
                    cancelado = true;
            });
        }
        var concluido = false;
        if (typeof arrayStatusFechadoConcluido !== 'undefined') {
            $.each(arrayStatusFechadoConcluido, function (i) {
                if (parseInt(i) === situacao_id)
                    concluido = true;
            });
        }

        if (((!alterouProdutos && !alterouPag) || cancelado) && !concluido) {
            gravarPedido();
        } else {
            var html = '';
            tblProdutosPedido.rows().every(function () {
                var d = this.data();
                if (replaceSpecialChars(d[2]) !== '000') {
                    for (var i = 0; i < d[3]; i++) {
                        html += '<option value="' + d[0] + '">' + d[1] + '</option>';
                    }
                }
            });
            $("#valegasproduto_id").empty().append(html).trigger('chosen:updated');
            $("#modalValidaGasBolso").modal('show');
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function fecharJanelaConfirm() {
    try {
        var title = "Atenção!";
        var message = "Deseja voltar a tela de monitoramento ou fechar a aba?";
        var callbackConfirm = function (result) {
            if (result) {
                window.open('', '_parent', '');
                window.close();
            } else {
                window.location.href = root + '/pedido';
            }
        };
        bootboxConfirm(title, message, callbackConfirm, "Fechar", "Monitoramento");
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//quando o campo "cliente" não está marcado, pergunta se o usuário deseja alterar o tipo do cadastro
function alteraCadastroFornecedor(data) {
    try {
        var title = "Atenção!";
        var message = "O cadastro está como fornecedor/transportador, deseja alterar o cadastro para o tipo cliente?";
        var callbackConfirm = function (result) {
            if (result) {
                var url = root + "/updatecampocliente/:id";
                url = url.replace(":id", data['cliente'].cliente_id);
                var method = "GET";
                ajaxGenerator(url, method,
                    function (dataConfirm) {
                        if (dataConfirm.substr(0, 3) !== "OK|") {
                            bootbox.alert("Ocorreu um erro ao alterar o cadastro do cliente!");
                        } else if (parseInt(data['cliente'].ativo) === 0) {
                            ativaClienteConfirm(data);
                        } else {
                            preencherCamposForm(data);
                        }
                    }, null);
            } else {
                $("#modalClientes").modal("hide");
            }
            $("#entregatelefone").focus();
        };
        bootboxConfirm(title, message, callbackConfirm);
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function ativaClienteConfirm(data) {
    try {
        var title = "Atenção!";
        var message = "O cadastro deste cliente está inativo, deseja ativar?";
        var callbackConfirm = function (result) {
            if (result) {
                var url = root + "/cliente/ativacliente/:id";
                url = url.replace(":id", data['cliente'].cliente_id);
                var method = "GET";
                ajaxGenerator(url, method,
                    function (dataConfirm) {
                        if (dataConfirm.substr(0, 3) !== "OK|") {
                            bootbox.alert("Ocorreu um erro ao alterar o cadastro do cliente!");
                        } else {
                            preencherCamposForm(data);
                        }
                    }, null);
            }
            $("#entregatelefone").focus();
        };
        bootboxConfirm(title, message, callbackConfirm);
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//Venda Ativa Gerar Pedido
function criarPedidoVendaAtiva() {
    try {
        var cliente_id = getParametro("cliente_id");
        var vendaativa_id = getParametro("vendaativa_id");
        var url = root + '/vendaativa/' + vendaativa_id;
        buscaClientePorId(cliente_id);
        $("#vendaativa_id").val(vendaativa_id);
        $("#btnVoltarForm").attr('href', url);
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//pega o preço do produto pelo id, e seta no campo "preco"
function getPrecoProduto() {
    try {
        var produto_id = $("#produto_id").val();
        var precoF = 0;
        var $condPgto = $("#condicaopagamento_id");
        var allowedCond = $condPgto.val() !== null && !condPgtoIsConvenio();

        if (!isEmpty(produto_id)) {
            if (condPgtoIsConvenio()) {
                precoF = getPrecoProdutoConvenio();
            } else {
                precoF = getPrecoProdutoCliente($("#precosProdutosPadrao"), $("#precoprodutos"), produto_id, allowedCond);
                if(gasdopovo && config){
                    if(config.produtogp_id == produto_id && $('#condicaopagamento_id').val().startsWith(config.condicaopagamentogp_id)){
                        if($("#precosProdutosPadrao").val()){
                            const prod = JSON.parse($("#precosProdutosPadrao").val()).where('produto_id', produto_id).first();
                            if(prod && prod.precogasdopovo){
                                precoF = prod.precogasdopovo;
                            }
                        }
                    }
                }
            }
        }

        $("#preco").val(formataDecimal(precoF, 2));
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

// noinspection JSUnusedGlobalSymbols
function closeModal(cliente_id) {
    try {
        if (typeof cliente_id !== "undefined" && !isEmpty(cliente_id))
            buscaClientePorId(cliente_id);
        $('#popup_relatorio').modal('hide');
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function initTables() {
    try {
        tblProdutosPedido = $("#tblProdutosPedido").DataTable({
            "language": {"url": urlDataTable},
            "processing": false,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": false,
            "bInfo": false,
            "bAutoWidth": true,
            "destroy": true,
            "sScrollY": "100"
        });
        tblHistorico = $("#tblHistorico").DataTable({
            "language": {"url": urlDataTable},
            "processing": false,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": false,
            "bInfo": false,
            "bAutoWidth": false,
            "destroy": true,
            "sScrollY": "100",
            "sScrollX": "165%"
        });
        tblCodValeGas = $("#tblCodValeGas").DataTable({
            "language": {"url": urlDataTable},
            "processing": false,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": false,
            "bInfo": false,
            "bAutoWidth": false,
            "destroy": true,
        });
        tblFonesEspera = $("#tblFonesEspera").DataTable({
            "language": {"url": urlDataTable},
            "processing": false,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": false,
            "bInfo": false,
            "bAutoWidth": false,
            "destroy": true,
        });
        tblModalClientes = $("#tblClientes").DataTable({
            "language": {"url": urlDataTable},
            "processing": false,
            "bPaginate": true,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": true,
            "bInfo": false,
            "bAutoWidth": false,
            "destroy": true,
            "paging": false,
            "order": [[ 1, "asc" ]],
            "aoColumnDefs": [
                {"bSortable": false, "aTargets": [4]},
                {"bVisible": false, "aTargets": [4]}
            ],
            "createdRow": function (row) {
                $(row).addClass('cursorPointer');
            }
        });
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function initSelectize() {
    try {
        //configura o plugin selectize para a busca de cliente pelo nome
        $("#nomecliente").selectize({
            valueField: "id",
            labelField: "nome",
            searchField: ["nome"],
            maxOptions: 10,
            hideSelected: true,
            options: [],
            create: false,
            render: {
                option: function (item, escape) {
                    var endereco = " - " + escape(item.rua.descricao) +
                        " nº " + escape(item.numero) + ", " + escape(item.bairro.descricao) + ", " +
                        escape(item.cidade.descricao);
                    return "<div><b>" + escape(item.nome) + "</b>" + endereco + "</div>";
                }
            },
            optgroups: [
                {value: "cliente", label: "Clientes"}
            ],
            optgroupField: "class",
            optgroupOrder: ["cliente"],
            load: function (query, callback) {
                refreshSelectize($("#nomecliente"));
                if (!query.length)
                    return callback();
                $.ajax({
                    url: root + "/api/searchClientePedido",
                    type: "GET",
                    dataType: "json",
                    data: {
                        q: query
                    },
                    error: function (data) {
                        console.log(data);
                        callback();
                    },
                    success: function (res) {
                        buscaSelectize = true;
                        callback(res.data);
                    }
                });
            },
            onChange: function () {
                if (typeof buscaSelectize !== "undefined" && buscaSelectize !== false) {
                    var selectize = $("#nomecliente").selectize()[0].selectize;
                    if (typeof selectize.getItem(this.items[0]).context === "object") {
                        var $cliente_id = $("#cliente_id");
                        $cliente_id.val(selectize.getValue());
                        buscaClientePorId($cliente_id.val());
                    }
                    buscaSelectize = false;
                }
            }, onInitialize: function () {
                var existingOptions = JSON.parse(this.$input.attr("data-selectize-value"));
                var self = this;
                if (Object.prototype.toString.call(existingOptions) === "[object Array]") {
                    existingOptions.forEach(function (existingOption) {
                        self.addOption(existingOption);
                        self.addItem(existingOption[self.settings.valueField]);
                    });
                } else if (typeof existingOptions === "object") {
                    self.addOption(existingOptions);
                    self.addItem(existingOptions[self.settings.valueField]);
                }
            }, onDropdownOpen: function ($dropdown) {
                $dropdown.css('visibility', this.lastQuery !== null && this.lastQuery.length ? 'visible' : 'hidden');
            }
        });
        //configura o plugin selectize para a busca de ruas
        $("#entregarua_id").selectize({
            valueField: "id",
            labelField: "descricao",
            searchField: ["descricao"],
            hideSelected: true,
            maxOptions: 10,
            options: [],
            create: false,
            render: {
                option: function (item, escape) {
                    return "<div>" + escape(item.descricao) + "</div>";
                }
            },
            optgroups: [
                {value: "rua", label: "Ruas"},
            ],
            optgroupField: "class",
            optgroupOrder: ["rua"],
            load: function (query, callback) {
                refreshSelectize($("#entregarua_id"));
                if (!query.length)
                    return callback();
                $.ajax({
                    url: root + "/api/searchRua/" + $("#entregacidade_id").val(),
                    type: "GET",
                    dataType: "json",
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
            onChange: function () {
                var selectize = $("#entregarua_id").selectize()[0].selectize;
                if (typeof selectize.getItem(this.items[0]).context === "object") {
                    $("#rua_id_erro").val(selectize.getValue());
                }
            }, onInitialize: function () {
                var existingOptions = JSON.parse(this.$input.attr("data-selectize-value"));
                var self = this;
                if (Object.prototype.toString.call(existingOptions) === "[object Array]") {
                    existingOptions.forEach(function (existingOption) {
                        self.addOption(existingOption);
                        self.addItem(existingOption[self.settings.valueField]);
                    });
                } else if (typeof existingOptions === "object") {
                    self.addOption(existingOptions);
                    self.addItem(existingOptions[self.settings.valueField]);
                }
            }, onDropdownOpen: function ($dropdown) {
                $dropdown.css('visibility', this.lastQuery.length ? 'visible' : 'hidden');
            }, onItemAdd: function () {
                if (show) {
                    $("#nomecliente").selectize()[0].selectize.disable();
                    $("#entregarua_id").selectize()[0].selectize.disable();
                }
            }, onDropdownClose: function () {
                setTimeout(function () {
                    var $input = $(".selectize-input");
                    $input.removeClass('dropdown-active');
                    $input.removeClass('focus');
                    $input.removeClass('input-active');
                }, 1000);
            }
        });
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function btnGravarClick() {
    try {
        if (!formSubmetidoPedido) {
            formSubmetidoPedido = true;
            var produtos = [];
            tblProdutosPedido.rows().every(function () {
                var d = this.data();
                produtos.push(d);
            });
            $("#produtospedido").val(JSON.stringify(produtos));
            verificaPagamentos();
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//busca os colaboradores e produtos do setor
function buscaColaboradorProduto(callback) {
    try {
        //PASSAR A BUSCAR NA HORA QUE GRAVA O PEDIDO E INICIA UM NOVO - achei melhor não fazer isso porque traz muitos dados;
        var url = root + "/consultaestoquesetor/consultaEstoqueSetor/:setor_id?filterColumns=1&empresa_id=" + config.empresa_id;
        var $colab_id = $("#colaborador_id");
        var $produto_id = $("#produto_id");
        $colab_id.empty().trigger('chosen:updated');
        $produto_id.empty().trigger('chosen:updated');
        $("#preco").val('0,00');
        var setor_id = $("#entregasetor_id").val();
        if (setor_id) {
            url = url.replace(":setor_id", setor_id);
            setor_id = parseInt(setor_id);
            ajaxGenerator(url, 'GET', function (data) {
                fillSelectColaborador(setor_id);
                var html = "<option value=''>Selecione</option>";
                var precosProdutosPadrao = [];
                $.each(data, function (i, element) {
                    html += "<option value='" + element.id + "'> " + element.descricao + "</option>";
                    precosProdutosPadrao.push({
                        "produto_id": element.id,
                        "precovenda": parseFloat(element.precovenda),
                        "precovendaminimo": parseFloat(element.precovendaminimo),
                        "precogasdopovo": parseFloat(element.precogasdopovo)
                    });
                });
                if (typeof callback === 'function')
                    callback();
                $("#precosProdutosPadrao").val(JSON.stringify(precosProdutosPadrao));
                $("#produto_id").append(html);
            }, function (data) {
                console.log(data);
            });
        }
        $colab_id.trigger("chosen:updated");
        $produto_id.val($produto_id.find("option").filter(function () {
            return replaceSpecialChars($(this).html().toUpperCase()).indexOf('P13') !== -1;
        }).val()).trigger('chosen:updated');
        getPrecoProduto();
        enableDisableDesconto();
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function validaCamposAddProduto() {
    try {
        var msg = "";
        if (isEmpty($("#condicaopagamento_id").val())) {
            msg = "O campo forma de Pagamento é necessário antes de adicionar o produto ";
        } else if (isEmpty($("#preco").val()) || $("#preco").val() === '0,00') {
            msg = "o campo preço é necessário e não pode ser zero ";
        } else if (isEmpty($("#produto_id").val())) {
            msg = "Selecione um produto ";
        } else if (!($("#quantidade").val() > 0)) {
            msg = "A quantidade deve ser maior que zero ";
        }
        if (!isEmpty(msg)) {
            bootbox.alert(msg + 'para adicionar.');
            return false;
        } else {
            validateProdutos();
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

//funções que são chamadas em eventos do HTML
function fieldsEvents() {
    try {
        $("#btnUpdateCalls").on('click', function () {
            tblFonesEspera.clear().draw();
            buscaTelefone();
        });

        $("#entregacep").on("change", changeTooltipCEP);

        $('[data-toggle="tooltip"]').tooltip();

        //ao clicar na linha da tabela de clientes encontrados pelo endereço chama o metodo buscaClientePorId
        $("#tblClientes").on("click", "tr", function () {
            var trElem = $(this).closest("tr");
            var id = $(trElem).children("td")[0];
            id = $(id).text();
            if (id !== "") {
                buscaClientePorId(id);
                $("#modalClientes").modal("hide");
            }
        });
        $('#buscarEnderecoEntrega').on('click', function () {
            buscarEnderecoPedidoPorCep();
        });
        $("#condicaopagamento_id").change(function () {
            updateCondGasdoPovo();
            updateConvenio();
        });
        $("#fmModalMotivoAtrasoPedido").on('submit', function (e) {
            e.preventDefault();
            $("#modalMotivoAtrasoPedido").modal('hide');
            motivoatraso_id = $("#modalpedidomotivoatraso_id").val();
            gravarPedido();
        });
        $("#btnSubmitRejeitaLigacoesMotivo").on('click', function () {
            $("#fmModalRejeitaLigacoesMotivo").submit();
        });
        $("form#fmModalRejeitaLigacoesMotivo").on('submit', function (e) {
            e.preventDefault();
            rejectCallSetMotivo(this);
        });
        $("#tblFonesEspera").on("dblclick", "td", function () {
            var tr = $(this).parent('tr');
            if (!$(this).hasClass('dataTables_empty'))
                meetOrRejectCall(tr, true);
        }).on("click", "button", function () {
            meetOrRejectCall(this);
        });
        $("#btnAddProduto").on("click", function () {
            alterou = true;
            validaCamposAddProduto();
        });
        $("#btnBuscaClienteTelefone").on('click', function () {
            buscaClienteTelefone();
        });
        $("#tblProdutosPedido").on("click", "button", function () {
            removeProdutosFromTbl(this);
        });
        $("#btnAddValeGas").on('click', function () {
            addValeGas($("#empresa_id").val(), edit_pedido_id);
        });
        $("#tblCodValeGas").on('click', 'button', function () {
            removeValegasFromTbl(this);
        });
        $("#btnValidaCartao").on("click", function () {
            validaCartao();
        });
        $("#btnGravar").on("click", function () {
            btnGravarClick();
        });
        $("#entregasetor_id").change(function () {
            buscaColaboradorProduto();
        });
        $("#condicaopagamento_id, #produto_id").change(function () {
            getPrecoProduto();
            enableDisableDesconto();
        });
        $("a[href='#']").on('click', function (e) {
            e.preventDefault();
        });
        $("#entregataxa, #valordesconto").on("keyup", function () {
            calculaValorTotalPedido();
        });
        $("#btnSelecionaEmpresa").on("click", function () {
            checkEmpresaSelectedOnModal();
        });
        $("#btnAtender, #btnRejeitar").on('click', function (e) {
            e.preventDefault();
        });
        //chama a função novo pedido
        $("#btnNovoPedido").on("click", function () {
            novoPedido();
        });
        //verifica se todos os produtos foram validados quando a forma de pagamento é valegas
        $("#btnValidaGasBolso").on('click', function () {
            validaGasBolso();
        });
        //formSubmetido pra false pra que o sistema possa passar pela validação do submit do formulario
        $("#modalValidaGasBolso").on('hide.bs.modal', function () {
            formSubmetidoPedido = false;
        });
        $("#btnEditCliente").on("click", function () {
            $("#btnImprimirIframe").hide();
            editClienteOnClick();
            modalCliente = true;
        });
        $("#popup_relatorio").on('hidden.bs.modal', function () {
            if (!modalCliente)
                finalizaPedido();
            modalCliente = false;
            declareShortcutOpenClient();
            if ($("#cliente_id").val()) {
                changeTooltipCEP();
            } else {
                setTimeout(function () {
                    $("#entregatelefone").focus();
                }, 700);
            }
        });
        $("#preco").on('change', function () {
            var $self = $(this);
            $self.val(validatePrecoMinimo($("#precosProdutosPadrao"), $("#produto_id").val(), $self.val()));
        });
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function rejectCallSetMotivo(that) {
    try {
        var url = root + '/rejeitaligacao';
        var formData = new FormData($(that)[0]);

        if (chamadaAtendida) {
            formData.append("atendida", true);
        }

        if (!isEmpty($("#motivonaovenda_id").val())) {
            ajaxGenerator(url, 'POST', function (data) {
                if (data.substr(0, 3) === "OK|") {
                    if (chamadaAtendida || pedidoEmAndamento) {
                        chamadaAtendida = false;
                        pedidoEmAndamento = false;
                        novoPedido();
                    } else {
                        rejeitaLigacoesMotivo = true;
                        excluirTelefoneChamada($("#telefonechamada_id").val(), true);
                    }
                    $("#modalRejeitaLigacoesMotivo").modal('hide');
                } else {
                    bootbox.alert("Erro ao informar motivo: " + data);
                }
            }, null, formData);
        } else {
            bootbox.alert("Selecione o motivo!");
        }
        return false;
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function meetOrRejectCall(that, meet = false) {
    try {
        if (!atendendo) {
            atendendo = true;
            var trElem = $(that).closest("tr");
            var id = $(trElem).children("td")[0];
            var empresa_id = $(trElem).children("td")[1];
            empresa_id = $(empresa_id).text();
            var $telefone = $($(trElem).children("td")[3]);
            var telefone = $telefone.text();
            id = $(id).text();
            $("#telefonechamada_id").val(id);
            $("#telefonerejeitado").val(telefone);
            $("#empresa_id_telefonerejeitado").val(empresa_id);
            if (!meet && $(that).context.id === "btnRejeitar")
                excluirTelefoneChamada(id, true);
            else
                setTelefoneChamada(telefone, id);
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function validaCartao() {
    try {
        var $numeroSelector = $("#numerocartao_modal");
        var cardNumber = $numeroSelector.val();
        if (isEmpty(cardNumber) || cardNumber.length !== 19) {
            $numeroSelector.addClass("hasError");
            if (isEmpty(cardNumber)) {
                bootbox.alert("Digite o número do cartão!");
            } else {
                bootbox.alert("O campo está incompleto!");
            }
        } else {
            $("#numerocartao").val(cardNumber);
            validaCartaoAjax(cardNumber);
            $numeroSelector.val("");
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function editClienteOnClick() {
    try {
        var cliente_id =  $("#cliente_id").val();
        if (typeof cliente_id !== "undefined" && !isEmpty(cliente_id)) {
            var url = root + "/cliente.editFromPedidos/" + cliente_id;
            openIframeCliente(url);
        } else {
            selecionarEmpresa();
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function checkEmpresaSelectedOnModal() {
    try {
        var empresaModal = $("#empresa_id_modal").val();
        if (!isEmpty(empresaModal)) {
            $("#modalSelecionarEmpresa").modal("hide");
            changeDataFromConfigEmpresa(function () {
                var url = root + '/cliente.createFromPedidos/';
                url += empresaModal + '?telefone=' + $("#entregatelefone").val();
                url += "&bairro_id=" + $("#entregabairro_id").val();
                url += "&rua_id=" + $("#entregarua_id").val();
                url += "&uf=" + $("#ufentrega").val();
                url += "&cidade_id=" + $("#entregacidade_id").val();
                url += "&numero=" + $("#entreganumero").val();
                url += "&complemento=" + $("#entregacomplemento").val();
                url += "&ponto_referencia=" + $("#entregapontoreferencia").val();
                url += "&cep=" + $("#entregacep").val();
                openIframeCliente(url);
            });
        } else {
            bootbox.alert("Selecione a empresa!");
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function removeProdutosFromTbl(that) {
    try {
        var row = $(that).closest("tr");
        var data = $("#tblProdutosPedido").dataTable().fnGetData(row);
        var produto_id = data[0];
        if (produto_id !== "") {
            tblProdutosPedido.row(row).remove();
            alterou = true;
            if (parseInt(produtopromocao_id) === parseInt(produto_id)) {
                tblProdutosPedido.rows().eq(0).each(function (i) {
                    var row = tblProdutosPedido.row(i);
                    var data = row.data();
                    if (typeof data !== "undefined") {
                        if (parseInt(data[0]) === parseInt(premioproduto_id)) {
                            tblProdutosPedido.row(i).remove();
                        }
                    }
                });
            }
        }
        tblProdutosPedido.draw();

        var contemProdutos = false;
        tblProdutosPedido.rows().eq(0).each(function (i) {
            var row = tblProdutosPedido.row(i);
            var data = row.data();
            if (typeof data !== "undefined") {
                qdelimitedisponivel = qdeLimiteConvenioGeral - parseInt(data[3]);
                contemProdutos = true;
            }
        });
        if (!contemProdutos) {
            qdelimitedisponivel = qdeLimiteConvenioGeral;
        }
        updateConvenio();
        calculaValorTotalPedido();
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function shortcutsEventsFields() {
    try {
        //atalhos
        $("#entregatelefone").on("focusin", function () {
            if (!shortcut.all_shortcuts['ctrl+space']) {
                let fun = function () {
                    putMaskTel();
                    $("#btnBuscaClienteTelefone").click();
                };
                shortcut.add("Ctrl+Space", fun);
                shortcut.add("Enter", fun);
            }
        }).on("focusout", function () {
            shortcut.remove("Ctrl+Space");
            shortcut.remove("Enter");
        });
        $("#entreganumero, #entregacomplemento").on("focus", function () {
            if (!shortcut.all_shortcuts['ctrl+space']) {
                setTimeout(function () {
                    let fun = function () {
                        $("#btnBuscaClienteEndereco").click();
                    };
                    shortcut.add("Ctrl+Space", fun);
                    shortcut.add("Enter", fun);
                }, 100);
            }
        }).on("focusout", function () {
            shortcut.remove("Ctrl+Space");
            shortcut.remove("Enter");
        });
        $("#btnBuscaClienteEndereco").on("focusout", function () {
            shortcut.remove("Space");
        }).on("focusin", function () {
            if (!shortcut.all_shortcuts['space']) {
                shortcut.add("Space", function () {
                    $("#btnBuscaClienteEndereco").click();
                });
            }
        }).on("click", function () {
            buscaClienteEndereco($(this).attr('urlclick'));
        });
        $("#btnBuscaClienteTelefone").on("focusout", function () {
            shortcut.remove("Space");
        }).on("focusin", function () {
            if (!shortcut.all_shortcuts['space']) {
                shortcut.add("Space", function () {
                    $("#btnBuscaClienteTelefone").click();
                });
            }
        });
        $("#entregacep").on("focusout", function () {
            shortcut.remove("Ctrl+Space");
            shortcut.remove("Enter");
        }).on("focusin", function () {
            if (!shortcut.all_shortcuts['ctrl+space']) {
                let fun = function () {
                    $("#buscarEnderecoEntrega").click();
                };
                shortcut.add("Ctrl+Space", fun);
                shortcut.add("Enter", fun);
            }
        });
        $("#buscarEnderecoEntrega").on("focusout", function () {
            shortcut.remove("Space");
        }).on("focusin", function () {
            if (!shortcut.all_shortcuts['space']) {
                shortcut.add("Space", function () {
                    $("#buscarEnderecoEntrega").click();
                });
            }
        });
        $("#btnEditCliente").on("focusout", function () {
            shortcut.remove("Space");
        }).on("focusin", function () {
            if (!shortcut.all_shortcuts['space']) {
                shortcut.add("Space", function () {
                    $("#btnEditCliente").click();
                });
            }
        });
        $("#quantidade").on("focusout", function () {
            shortcut.remove("Enter");
        }).on("focusin", function () {
            if (!shortcut.all_shortcuts['enter']) {
                shortcut.add("Enter", function () {
                    $("#btnAddProduto").click();
                });
            }
        });
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function validateProdutos() {
    try {
        var $qtde = $("#quantidade");
        var $produto = $("#produto_id");
        var id_produto = $produto.val();
        var produto = $produto.find("option:selected").text();
        var preco = $("#preco").val();
        var precoNovo = parseFloat(preco.replace(".", "").replace(',', '.'));
        var quantidade = parseInt($qtde.val());
        var id_produtoAntigo = 0;
        var produtoAntigo = 0;
        var precoAntigo = 0.0;
        var qdeAntiga = 0;
        var produtoAntigoExists = false;

        tblProdutosPedido.rows().eq(0).each(function (i) {
            var row = tblProdutosPedido.row(i);
            var data = row.data();
            if (typeof data !== "undefined") {
                if (parseInt(data[0]) === parseInt(id_produto)) {
                    produtoAntigoExists = true;
                    id_produtoAntigo = data[0];
                    produtoAntigo = data[1];
                    qdeAntiga = parseInt(data[3]);
                    precoAntigo = data[2].replace(".", "");
                    precoAntigo = parseFloat(precoAntigo.replace(',', '.'));
                    tblProdutosPedido.row(i).remove();
                    quantidade += parseInt(data[3]);
                }
            }
        });

        var convenioDisponivel = qdeLimiteConvenioGeral >= quantidade;

        if (parseInt(convenioPara) === 1 && convenioDisponivel && condPgtoIsConvenio())
            precoNovo -= precoNovo * comissao / 100;
        precoNovo = formataDecimal(precoNovo, 2);

        if(gasdopovo && config){
            if(id_produto != config.produtogp_id && parseInt($("#condicaopagamento_id").val()) == config.condicaopagamentogp_id){
                bootbox.alert("Produto não permitido para Programa Gás do Povo!");
                return false;    
            }
        }

        if (convenioDisponivel || !condPgtoIsConvenio()) {
            addProdutoToTable(id_produto, produto, precoNovo, quantidade);
        } else {
            precoAntigo = formataDecimal(precoAntigo, 2);
            if (produtoAntigoExists)
                addProdutoToTable(id_produtoAntigo, produtoAntigo, precoAntigo, qdeAntiga);
            bootbox.alert("Não há convenio disponível para adicionar produtos!");
            return false;
        }

        if (parseInt(produtopromocao_id) === parseInt(id_produto))
            addProdutoPromocao(quantidade);
        updateConvenio();
        $qtde.val("");
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function addProdutoPromocao(quantidade) {
    try {
        let totalCompras = comprasprodutopromocao + quantidade;
        let qdeAntiga = 0;
        tblProdutosPedido.rows().eq(0).each(function (i) {
            let data = tblProdutosPedido.row(i).data();
            if (typeof data !== "undefined") {
                if (parseInt(data[0]) === parseInt(premioproduto_id)) {
                    qdeAntiga = parseInt(data[3]);
                    tblProdutosPedido.row(i).remove();
                }
            }
        });
        let qdeSemPremio;
        if (ganhouprodutopremio > 0) {
            qdeSemPremio = totalCompras - (ganhouprodutopremio * quantidadepedidos);
        } else {
            qdeSemPremio = totalCompras;
        }
        if (qdeSemPremio >= quantidadepedidos && parseInt(quantidadepedidos) !== 0) {
            let qtde;
            if (parseInt(quantidadepremios) !== 0) {
                qtde = (qdeSemPremio / quantidadepedidos) * quantidadepremios;
            } else {
                qtde = (qdeSemPremio / quantidadepedidos);
            }
            qtde = Math.floor(qtde);
            addProdutoToTable(premioproduto_id, produtopremiodescricao, "0,00", qtde);
        }
        tblProdutosPedido.draw();
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function isObject(variable) {
    return typeof variable === 'object';
}

function isArray(variable) {
    return isObject(variable);
}

function clearSelectize($selector) {
    try {
        let select = $selector.selectize()[0].selectize;
        select.clearOptions();
        select.refreshOptions(true);
        // select.refreshItems();
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function refreshSelectize($selector) {
    try {
        let select = $selector.selectize()[0].selectize;
        select.refreshItems();
        select.clear();
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function condPgtoIsConvenio() {
    try {
        var cond = $("#condicaopagamento_id").val();
        return !isEmpty(cond) && cond.indexOf("-4") !== -1;
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function isCondicaoPagamentoCartao() {
    try {
        var tipo2 = "-2";
        var tipo3 = "-3";
        var $cond = $("#condicaopagamento_id").val();
        return !isEmpty($cond) && ($cond.indexOf(tipo2) !== -1 || $cond.indexOf(tipo3) !== -1);
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function viewValidate() {
    try {
        $("#popup_int").attr("style", "background-color: #b1b0ab");
        $(".extra").show();
        $(".selectChosenClear").chosen({no_results_text: "nenhum geral encontrado",
            placeholder_text_single: "Selecione",
            search_contains: true,
            width: "100%",
            disable_search: false
        });
        $("#quantidade").attr('maxlength', 2);
        $("#entregatelefone").mask("(##) #####-####");
        $("#datahoraacao").val($("#datahoraacaoshow").val());
        var $inputs = $('.selectize-input').find("input");
        $inputs.keyup(function (e) {
            if (e.keyCode === 8)
                clearSelectize($("#nomecliente"));
        });
        $inputs.keyup(function (e) {
            if (e.keyCode === 8)
                clearSelectize($("#entregarua_id"));
        });
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function setPedidoController(pedController) {
    try {
        pedidoController = pedController;
        editOrShow = true;
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function setNfceId(id) {
    try {
        nfce_id = parseInt(id) ? null : id;
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function setTempoEntregas(urgente, normal) {
    try {
        tempoEntregaUrgente = urgente;
        tempoEntregaConfig = normal;
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function setArrayStatus() {
    try {
        arrayStatusFechadoConcluido = JSON.parse($("#arrayStatusFechadoConcluido").val());
        arrayStatusFechadoCancelado = JSON.parse($("#arrayStatusFechadoCancelado").val());
        arrayStatusFinalizado = JSON.parse($("#arrayStatusFinalizado").val());
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function setCidadeOriginal(id) {
    try {
        cidadeOriginal = id;
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function requirePassword() {
    try {
        $("#divFooterModalSenha").prepend('<button type="button" onclick="fecharJanelaConfirm()" class="btn btn-nw-geral" data-dismiss="modal">Cancelar</button>');
        let $modal = $("#modalSenha");
        $modal.modal({
            show: true,
            keyboard: false,
            backdrop: "static"
        });
        $("#btnTopCloseModalSenha, #btnCloseModalSenhaMestra").prop("disabled", true).hide();

        $modal.modal("show");
        var urlSenha = $modal.text();
        $("#rotaSenha").append('/' + $("#empresa_id").val());
        callbackSenha = function () {
            verificouSenhaEdit = true;
            $modal.modal({
                keyboard: true,
                backdrop: true
            });
            $("#rotaSenha").text(urlSenha);
            $("#btnTopCloseModalSenha, #btnCloseModalSenhaMestra").prop("disabled", false).show();
            addShortcuts();
        };
        shortcut.remove("F1");
        removeCreateEditShortcuts();
        shortcut.remove("Ctrl+Enter");
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function setShowParameters() {
    try {
        $(window).load(function () {
            removeCreateEditShortcuts();
        });
        let $btnEdit = $("#btnEditaPedido");
        $btnEdit.removeClass('hidden');
        $("#teclasAtalhoShow").removeClass('hidden');
        desativarInputsEspecificos(['.selectChosen', '.selectChosenClear', 'input', '#btnAddProduto', '.btn-nw-registro']);
        $btnEdit.removeAttr("disabled");
        $("#btnGravar").hide();
        // $("#divHeaderAddProduto").hide();
        show = true;
        $('a[href="#"]').on('click', function () {
            let isLinkMenu = $(this).hasClass('dropdown')
                || $(this).hasClass('dropdown-toggle')
                || $(this).parent('li').hasClass('dropdown-submenu');

            if (!isLinkMenu)
                $(this).off();

        }).each(function () {
            let isLinkMenu = $(this).hasClass('dropdown') || $(this).hasClass('dropdown-toggle') || $(this).parent('li').hasClass('dropdown-submenu');
            if (!isLinkMenu)
                $(this).attr("disabled", "disabled").attr('tabindex', -1);
        });
        $(".selectChosen, .selectChosenClear").trigger("chosen:updated");
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function showShortcutsEdit() {
    try {
        $("#teclasAtalho").removeClass('hidden');
        $("#btnNovoPedido").removeClass('hidden');
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function removeCreateEditShortcuts() {
    try {
        shortcut.remove("F2");
        shortcut.remove("F3");
        shortcut.remove("F4");
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

/**
 * return a option string
 * @param value
 * @param text
 * @returns {string}
 */
function appendOption(value, text) {
    try {
        if (!text) {
            text = "Selecione";
        }
        if (!value) {
            value = '';
        }
        return "<option value='" + value + "'>" + text + '</option>';
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function setQuantidadePadrao() {
    try {
        $("#quantidade").val(config.quant_padrao);
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function setConfig(novoPedido) {
    try {
        var $empresa = $("#empresa_id");

        config = allConfigs.where('empresa_id', parseInt($empresa.val())).first();
        if (!config) {
            $empresa.val("");
            $("#pedidosituacao_id").val("");
            $("#pedidooperacao_id").val("");
            condicaoPagamentoConfig = false;
            if (!novoPedido && editOrShow)
                bootbox.alert("Não será possível registrar nenhum pedido para esta empresa pois não as configurações ainda não foram escolhidas!");
        } else {
            if (typeof pedidoController === "undefined") {
                $empresa.val(parseInt(config.empresa_id) ? config.empresa_id : "");
                updateStatus(config);
                updateOperacoes(config);
                condicaoPagamentoConfig = true;
            }
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function fillSelectColaborador(setor_id) {
    try {
        var html = '';
        var setor = config.setores.where('id', setor_id).first();
        var colaboradores = setor ? setor.colaboradores : null;
        if (colaboradores) {
            if (colaboradores.length > 1) {
                html += appendOption();
            }
            var colab = 0;
            $.each(colaboradores, function (i, el) {
                html += appendOption(el.id, el.nome);
                colab = el.id;
            });
            $("#colaborador_id").append(html).val(colaboradores.length === 1 ? colab : 0);
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function enableDisableDesconto() {
    try {
        let cond = $("#condicaopagamento_id").val();
        let $desc = $("#valordesconto");
        if (show) {
            $desc.prop("disabled", true);
            return;
        }
        if (cond && cond.indexOf('-5') !== -1) {
            $desc.prop("disabled", true).val("R$ 0,00");
        } else {
            $desc.prop("disabled", false);
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function setTelefone(tel) {
    try {
        $("#entregatelefone").unmask().val(tel);
        putMaskTel();
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function declareShortcutOpenClient() {
    try {
        if (!shortcut.all_shortcuts['f2']) {
            shortcut.add("F2", function () {
                $("#btnEditCliente").click();
            });
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function changeTooltipCEP() {
    try {
        let $cep = $("#entregacep");
        if ($cep.isEmpty() && !show) {
            $cep.attr('data-toggle', 'tooltip')
                .attr('data-placement', 'top')
                .attr('title', 'Se for gerar NFC-e com identificação do destinatário, informar o cep')
                .tooltip("show");

            setTimeout(function () {
                destroyTooltipCEP();
            }, 2500);
        } else {
            destroyTooltipCEP();
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function destroyTooltipCEP() {
    try {
        $("#entregacep").removeAttr('data-toggle')
            .removeAttr('data-placement')
            .removeAttr('title').tooltip("destroy");
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

/**
 * checa e retorna os preços do produto para o convênio
 * @returns {number}
 */
function getPrecoProdutoConvenio() {
    try {
        let prices = $("#produtosconvenio").val();
        let precovenda = 0;
        let $produto_id = $("#produto_id");
        let produto_id = $produto_id.intVal();
        let callback = function () {
            $produto_id.val("").trigger("chosen:updated");
        };
        if (prices) {
            produtos = JSON.parse(prices);
            let p = produtos.where('produto_id', produto_id).first(true);
            if (p) {
                precovenda = p.preco;
            } else {
                bootbox.alert("Produto não cadastrado para o convênio", callback);
            }
        } else {
            bootbox.alert("Produto não cadastrado para o convênio", callback);
        }
        return precovenda;
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
        return 0;
    }
}

function checkIfEdit() {
    if (!window.location.pathname.includes('edit')) return;

    let concluido = parseInt(pedidoController.pedido.pedidosituacao.fechadoconcluido);
    let cancelado = parseInt(pedidoController.pedido.pedidosituacao.fechadocancelado);

    if (concluido == 1 || cancelado == 1) {
        enbDsbCriticalFields();
    }
}

function enbDsbCriticalFields(disabled = true) {
    $(".btn-remocao-produtos").prop("disabled", disabled);
    $("#btnAddProduto").prop("disabled", disabled);
    $("#condicaopagamento_id").prop("disabled", disabled).trigger("chosen:updated");
    $("#entregasetor_id").prop("disabled", disabled).trigger("chosen:updated");
}

$("#cod_gasbolso").keyup(function (e) {
    if (e.keyCode === 13)
    addValeGas($("#empresa_id").val(), edit_pedido_id);
});

function updateCondGasdoPovo(){

    if(gasdopovo && config){
        const previousValue = $('#condicaopagamento_id').data('previousValue');
        if(parseInt(previousValue) == config.condicaopagamentogp_id || parseInt($("#condicaopagamento_id").val()) == config.condicaopagamentogp_id){
            //se era gas do povo e mudou para outra condição, remove os itens devido ao preço
            const alterouPag = parseInt($("#condicaopagamento_id").val()) !== parseInt(previousValue);
            if(alterouPag){
                tblProdutosPedido.clear().draw();               
                updateConvenio();
                calculaValorTotalPedido();
            }
        }
        $('#condicaopagamento_id').data('previousValue', $("#condicaopagamento_id").val());
    }
}

function enableDisableGasdoPovo(){
    if(!gasdopovo && config && config.condicaopagamentogp_id){
        //se não for gás do povo, remove condição de pagamento
        $(`#condicaopagamento_id option[value*="${config.condicaopagamentogp_id}-"]`).each(function() {
            $(this).prop("disabled", true)
        });
    } else {
        $(`#condicaopagamento_id option[value*="${config.condicaopagamentogp_id}-"]`).each(function() {
            $(this).prop("disabled", false)
        });

    }
    $("#condicaopagamento_id").trigger('chosen:updated')
}