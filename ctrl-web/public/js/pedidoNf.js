var transportadoras = [];
var condicoesPgtoTransp = [];
var config;
var isDup = false;
var strCondPgto = "";
var $condPgtoFrete = $("#fretecondicaopagamento_id");
var $formaPgto = $("#formapagamento");
var presencaCompradorPedido;

desabilitaCamposFrete();
$("#fisicajuridica").on("change", function () {
    try {
        validaFisicaJuridica($(this).val());
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
});

$("#transportador_id").on('change', function () {
    try {
        var transp = transportadoras.where('id', $(this).val());
        transp = typeof transp[0] === "undefined" ? [] : transp[0];
        fillFieldsFrete(transp);
        updateCondicaoPagamentoTransp();
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
});

$("#nfcpfcnpj").on('focusin', function () {
    try {
        shortcut.add('Enter', function () {
            $("#btnGravarNF").click();
        });
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}).on('focusout', function () {
    try {
        shortcut.remove('Enter');
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
});

$("#modal-tiponf").on('shown.bs.modal', function () {
    try {
        $("#nftipo").val(0).trigger('chosen:updated');
        $("#nfcpfcnpj").val('');
        $("#freteplacauf").val('');
        $("#freteplaca").val('');
        getInfoClienteByPedido();
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
});

$("#nftipo").on('change', function () {
    getInfoClienteByPedido();
});

$("#btnCancelarNf").on('click', function () {
    try {
        bootboxConfirm("Atenção", "Tem certeza de que deseja cancelar?", function () {
            if (!(typeof telaMonitoramento !== 'undefined' && telaMonitoramento)) {
                $("#modal-tiponf").modal('hide');
                finalizaPedido();
            } else {
                finalizaNfCreate("Operação Cancelada");
            }
        });
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
});

$("#btnGravarNF").on("click", function () {
    try {
        if (typeof config !== "undefined" && typeof config.presencacomprador !== "undefined") {
            var presencaComprador = config.presencacomprador
        } else {
            bootbox.alert('Não foi possivel verificar o tipo de presença do comprador das configurações da empresa!');
            return;
        }
        presencaCompradorPedido = presencaComprador;
        var nftipo = presencaComprador === '4' ? 2 : $("#nftipo").intVal();
        var $nfcpfcnpj = $("#nfcpfcnpj");
        if (nftipo === 1 && isEmpty($nfcpfcnpj.val())) {
            bootbox.alert("Informe o CPF/CNPJ do cliente.");
            return false;
        }
        if ($("#fisicajuridica").val() === 'J' && isEmpty($("#indicador_ie").val())) {
            bootbox.alert("Informe o Indic. I.E do cliente.");
            return false;
        }
        if (nftipo === 2 && $("#freteplacauf").val().length !== 2) {
            bootbox.alert('Informe um UF Placa válido.');
            return false;
        }
        if (nftipo === 2 && $("#freteplaca").val().length !== 8) {
            bootbox.alert('Informe uma placa válida.');
            return false;
        }
        var transportador_id = parseInt($("#transportador_id").val());
        if (nftipo === 2 && !transportador_id) {
            bootbox.alert('O transportador é obrigatório.');
            return false;
        }
        if (presencaComprador === '4' && $nfcpfcnpj.isEmpty()) {
            bootbox.alert('CPF/CNPJ é obrigatório para a Presença de Comprador 4.');
            return false;
        }
        if (presencaComprador === '4' && !$("#fretecondicaopagamento_id").intVal()) {
            bootbox.alert('Selecione a condição de pagamento.');
            return false;
        }
        updateDadosNf(presencaComprador);
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
});

function updateDadosNf(presencaComprador) {
    try {
        var id = $("#pedido_id_nf").val();
        var url = root + '/pedido/updateDadosNf/' + id;
        var nftipo = presencaComprador === '4' ? 2 : $("#nftipo").val();
        var formData = new FormData();

        formData.append('nfcpfcnpj', $("#nfcpfcnpj").val());
        formData.append('nftipo', nftipo);
        formData.append('indicador_ie', $("#indicador_ie").val());
        formData.append('transportador_id', $("#transportador_id").val());
        let callback = (result) => {
            if (result) {
                ajaxGenerator(url, "POST", function (data) {
                    if (data.substr(0, 3) === 'OK|') {
                        gerarNf(id);
                    } else {
                        finalizaNfCreate(data);
                    }
                }, function () {
                    finalizaNfCreate('Não foi possível gravar os dados da NF para este pedido');
                }, formData);
            }
        };
        if (parseInt(nftipo) && $("#fisicajuridica").val() === "J") {
            let msg = "Na transmissão da NFC-e é somente aceito destinatário que é Consumidor Final. " +
                "Portanto, o destinatário informado será considerado consumidor final, deseja continuar?";
            bootboxConfirm("Atenção", msg, callback)
        } else {
            callback(true);
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function getInfoClienteByPedido() {
    try {
        var pedido_id = $("#pedido_id_nf").val();
        ajaxGenerator(root + '/api/getInfoClienteByPedido/' + pedido_id, "GET", function (data) {
            if (typeof data === 'object') {
                updateFieldsNF(data);
            } else {
                bootbox.alert(' ' + data);
            }
        }, null);
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function updateFieldsNF(data) {
    try {
        var $transportador_id = $("#transportador_id");
        var $nftipo = $("#nftipo");
        $transportador_id.html('').trigger('chosen:updated');
        config = data.config;
        transportadoras = [];
        condicoesPgtoTransp = data.condicoesPgto;
        setStringCondPgto(data.condicoesPgtoPadrao);
        var $inputToFocus;
        if (data.config.presencacomprador === "4") {
            $(".divTransportadora").show();
            $(".divTiponf").hide();
            $("label[for='fisicajuridica']").removeClass('col-sm-1 col-sm-2').addClass('col-sm-2');
            $nftipo.val('1').prop('disabled', true).trigger('chosen:updated');
            var options = '';
            for (let i = 0; i < data.transportadoras.length; i++) {
                var el = data.transportadoras[i];
                transportadoras.push(el);
                var selected = parseInt(el.id) === parseInt(data.config.transportadorpadrao_id) ? 'selected' : '';
                fillFieldsFrete(el);
                options += "<option " + selected + " value=\"" + el.id + "\">" + el.nome + "</option>";
            }
            $transportador_id.html(options).trigger('chosen:updated');
            $("#vfrete").attr('readonly', "readonly").attr('tabindex', '-1').val(data.vfrete);
            updateCondicaoPagamentoTransp();
            $inputToFocus = $transportador_id;
        } else {
            $(".divTiponf").show();
            $("label[for='fisicajuridica']").removeClass('col-sm-1 col-sm-2').addClass('col-sm-1');
            $(".divTransportadora").hide();
            $inputToFocus = $nftipo;
        }

        isDup = data.isDup;
        if (data.isDup) {
            $("#div_tpag").show();
        } else {
            $("#div_tpag").hide();
        }

        changeVFrete(data.pedidogerafinanceiro, data.config.fretemodalidade);
        changeFreteFinanceiro(data.pedidogerafinanceiro);

        $("select").trigger("chosen:updated");
        $("#fisicajuridica").val(data.tipopessoa).prop('disabled', true).attr('tabindex', -1).trigger('chosen:updated');
        validaFisicaJuridica(data, function (data) {
            if ($nftipo.intVal() === 1) {
                var $id = $("#nfcpfcnpj");
                $id.val(data.cpfcnpj);
                if (!isEmpty(data.cpfcnpj))
                    $id.attr('readonly', 'readonly').attr('tabindex', -1);
                else
                    $id.val('').removeAttr('readonly').removeAttr('tabindex');
            } else {
                $("#nfcpfcnpj").val('').prop('readonly', false).removeAttr('tabindex');
            }
            setTimeout(function () {
                $inputToFocus.focus().trigger("chosen:activate");
            }, 650);
        });
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function updateCondicaoPagamentoTransp() {
    try {
        var condTransp = condicoesPgtoTransp.where('cliente_id', $("#transportador_id").val());
        var opt;
        if (condTransp.length === 0) {
            opt = strCondPgto;
        } else {
            opt = "";
            for (let i = 0; i < condTransp.length; i++) {
                opt += "<option value=\"" + condTransp[i].id + "\">" + condTransp[i].descricao + "</option>";
            }
        }
        $condPgtoFrete.html(opt).trigger('chosen:updated');
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function gerarNf(id) {
    try {
        var url = root + "/nfemitida/processorPedidoNf/" + id;
        var formData = new FormData();
        formData.append('indicador_ie', $("#indicador_ie").val());
        formData.append('transportador_id', $("#transportador_id").val());
        formData.append('freteplaca', $("#freteplaca").val());
        formData.append('freteplacauf', $("#freteplacauf").val());
        if (isDup) {
            formData.append('nfc_tpag', $("#nfc_tpag").val());
        }
        formData.append('fretecondicaopagamento_id', $condPgtoFrete.val());
        formData.append('formapagamento', $formaPgto.val());
        ajaxGenerator(url, "POST", function (data) {
            if (data.status === "OK") {
                transmitirNf(id, data);
                updatePedidoGerouNf(id, data);
            } else {
                finalizaNfCreate(data.msg);
            }
        }, function (data) {
            finalizaNfCreate(data);
        }, formData);
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function transmitirNf(id, data) {
    try {
        var url = root + '/nfemitida/evento/transmitir?id=' + data.id;
        ajaxGenerator(url, 'GET', function (dados) {
            if (dados.indexOf('Sucesso') === -1)
                finalizaNfCreate('A NFCe foi gravada mas ocorreu um problema ao transmitir:' + dados);
            else
                consultarNf(data.id);
        }, function () {
            finalizaNfCreate('Problemas ao transmitir NFCe, acesse a tela de Emissões de Notas Fiscais para solucionar.');
        });
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function updatePedidoGerouNf(id, data) {
    try {
        var url = root + "/pedido/nfcegerou/" + id + '/' + data.id;
        ajaxGenerator(url, 'GET', function (d) {
            if (d !== 'OK|')
                finalizaNfCreate('A NFCe foi gerada mas o pedido não foi vinculado com ela, contate o suporte com urgência!');
        }, function (data) {
            finalizaNfCreate(data);
        });
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function setStringCondPgto(array) {
    try {
        strCondPgto = '';
        for (let i = 0; i < array.length; i++) {
            strCondPgto += "<option value=\"" + array[i].id + "\">" + array[i].descricao + "</option>";
        }
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function finalizaNfCreate(msg) {
    try {
        $("#modal-tiponf").modal('hide');
        if (typeof telaMonitoramento !== 'undefined' && telaMonitoramento)
            bootbox.alert(' ' + msg);
        else
            bootbox.alert(' ' + msg, finalizaPedido());
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function validaFisicaJuridica(data, callback) {
    try {
        var $cpfCnpj = $("#nfcpfcnpj");
        $cpfCnpj.val('').unmask();
        if (data.tipopessoa === 'F') {
            var indicador_ie = 9;
            if (!isEmpty(data.indicador_ie)) {
                indicador_ie = data.indicador_ie;
            }
            $("#indicador_ie").val(indicador_ie).trigger('chosen:updated');
            $cpfCnpj.off().mask("999.999.999-99", {placeholder: " "});
            $("#divIndicadorIe").hide();
        } else {
            $cpfCnpj.off().mask("99.999.999/9999-99", {placeholder: ""});
            $("#divIndicadorIe").show();
            $("#indicador_ie").val(data.indicador_ie).trigger('chosen:updated');
        }
        $cpfCnpj.on('change', function () {
            var $self = $(this);
            var $btn = $("#btnGravarNF");
            if ($self.val() && !valida_cpf_cnpj($self.val())) {
                $btn.prop('disabled', true);
                bootbox.alert("CPF/CNPJ inválido!", function () {
                    $self.val("").focus();
                });
            } else {
                $btn.prop('disabled', false);
            }
        });
        if (typeof callback === "function")
            callback(data);
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}

function consultarNf(id) {
    try {
        window.open(root + "/nfemitida/evento/consultar?id=" + id + "&getFile=0&fromPedidos=1", '_blank');
        finalizaNfCreate('Nota fiscal consultada');
    } catch (e) {
        storageLogError(e, 'log-pedido-js');
    }
}
