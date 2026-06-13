$(document).ready(function () {
    if ( getParametro("tab") ) {
        var tab = getParametro("tab");
        $(`.nav-tabs a[href="#tab_${tab}"]`).tab('show');
    }
    bloquearSelectize(false);
    verificarMatriz();
    validaPresencaComprador();
    if (errorsany === true) {
        habilitarInputs(inputs);
        $("#btngravar").show();
        $("#habilitaredicao").hide();
        verificarMatriz();
    } else {
        bloquearInputs(inputs);
        $("#btngravar").hide();
        verificarMatriz();
    }
    checkCartao( $("input[name=pedidovalidacartao]") );
});

inputs = ['planoconta_id', 'centrocusto_id', 'setorprincipal_id', 'tempoidenchamada',
    'nfoperacoes_id', 'mensagemgasbolso', 'tempoentrega', 'tempourgente', 'validacordenadasentrega',
    'validagasbolso', 'validaatraso', 'androidenviatodos', 'mensagemduplicata', 'senhamestre',
    'percentualencargos', 'percentualprovisaodevedores', 'percentualremuneracaocapital',
    'percentualdistribuicaoresul', 'utilizafechamentoestoque', 'permiteestoquenegativo',
    'timezone', 'emailnomeremente', 'emailremetente', 'emailusuario', 'emailsenha',
    'emailservidorsmtp', 'emailportasmtp', 'emailrequerautenticacao', 'emailrequerconexaotls',
    'emailassunto', 'emailcorpo', 'impressaotipo', 'impressaomodelo', 'maximoparcelas',
    'impressaoporta', 'impressaoautomatica', 'impressaoqtdviaspedido', 'pedidovalidacartao',
    'pedidovalidacartaodias', 'pedidocontrolatempoligacoes', 'androidutiliza', 'telacontrolakm',
    'diastrabalhadosemana', 'keygooglemaps', 'operacaodisk',
    'contadevolucaocheque', 'qnddiasinativocompra', 'pedidoemitenfce', 'pedidostatuspadrao',
    'btnPconta', 'btnCcusto', 'btnEmailTeste', 'btnPcontaCartao', 'btnPcontaRecDes', 'contachecktroco',
    'btnPcontaRecJu', 'btnPcontaDesDes', 'btnPcontaDesJu', 'btnCcustoValGas', 'btnPcontaValGas',
    'presencacomprador', 'fretemodalidade', 'btnCcFrete', 'btnPcFrete', 'btnCcDesc', 'btnCcJuros',
    'btnCcRecJuros', 'btnCcRecDesc', 'btnCcCartao', 'setor_ressarcimento', 'operacao_ressarcimento',
    'pedidooperacao_id', 'transportadorpadrao_id', 'quant_padrao', 'emailkeygoogle', 'pedidooperacaoappnf_id',
    'presencacompradorappnf', 'fretemodalidadeappnf', 'transportadorappnf_id', 'contaappnf_id', 'client_id',
    'client_secret', 'chavepix', 'validapixentrega', 'maloteconta_id',
    'nfoperacaoconvenio_id', 'presencacompradorconvenionf', 'fretemodalidadeconvenionf', 'transportadorconvenionf_id', 
    'contaconvenionf_id', 'btnCcustoConvenio', 'btnPcontaConvenio', 'btnCcFreteConvenio', 'btnPcFreteConvenio' , 
    'setorconvenio_id', 'veiculoconvenio_id', 'condicaopagamentoconvenio_id', 'fatorpotencialvenda',
    'emaildiretoria', 'emailcomercial', 'condicaopagamentofretegp_id', 'ccfretegp_id', 'pcfretegp_id', 'produtogp_id',
    'btnCcustoFreteGp', 'btnPcontaFreteGp', 'condicaopagamentogp_id', 'valorfretegp'
];

email = [
    'emailservidorsmtp', 'emailportasmtp',
    'emailassunto', 'emailcorpo', 'emailnomeremente'
];

$("#habilitaredicao").click(function () {
    if ($("#qnddiasinativocompra").attr('disabled') === 'disabled') {
        bloquearSelectize(true);
        habilitarInputs(inputs);
        $("#btngravar").show();
        $("#habilitaredicao").hide();
    } else {
        bloquearSelectize(false);
        bloquearInputs(inputs);
        $("#btngravar").hide();
    }
});

$("#btngravar").click(function (e) {
    validarCampos( e );
});

$("#presencacomprador").on('change', function () {
    validaPresencaComprador();
});

$("input[name=pedidovalidacartao]").click( function () {
    checkCartao( $( this ) );
});

function validaPresencaComprador() {
    $("#fretemodalidade option").filter(function () {
        var $self = $(this);
        $self.prop('disabled', $self.val() === "9" && $("#presencacomprador").val() === "4");
        return true;
    }).trigger('chosen:updated');
}

function bloquearSelectize(value) {
    if ($("#nfcecliente_id").selectize()[0]?.selectize) {
        $("#nfcecliente_id").selectize()[0].selectize.destroy();
    }

    if (value === true) {
        searchCliente();
        $("#nfcecliente_id")[0].selectize.enable();
    } else {
        searchCliente();
        $("#nfcecliente_id")[0].selectize.disable();
    }
}

function bloquearInputs(inputs) {
    for (var i = 0; i < inputs.length; i++) {
        $("#" + inputs[i]).prop('disabled', true).trigger('chosen:updated');
    }
}

function habilitarInputs(inputs) {
    for (var i = 0; i < inputs.length; i++) {
        $("#" + inputs[i]).prop('disabled', false).trigger('chosen:updated');
    }
    verificarMatriz();
}

function searchCliente() {
    $('#nfcecliente_id').selectize({
        valueField: 'id',
        labelField: 'nome',
        searchField: ['nome', 'cpf'],
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
                url: root + '/api/clientespf',
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
        }, onInitialize: function () {
            var existingOptions = JSON.parse(this.$input.attr('data-selectize-value'));
            var id = $("#cliente_id").val();
            var nome = $("#cliente_nome").val();
            var self = this;
            if (errorsany) {
                var opt = [{"id": $('#cliente_id').val(), "nome": $('#cliente_nome').val()}];
                opt.forEach(function (existingOption) {
                    self.addOption(existingOption);
                    self.addItem(existingOption[self.settings.valueField]);
                });
            } else if (id != "") {
                var opt = [{"id": id, "nome": nome}];
                if (Object.prototype.toString.call(opt) === "[object Array]") {
                    opt.forEach(function (existingOption) {
                        self.addOption(existingOption);
                        self.addItem(existingOption[self.settings.valueField]);
                    });
                } else if (typeof existingOptions === 'object') {
                    self.addOption(existingOptions);
                    self.addItem(existingOptions[self.settings.valueField]);
                } else if (name !== '' && id !== '') {
                    opt.forEach(function (existingOption) {
                        self.addOption(existingOption);
                        self.addItem(existingOption[self.settings.valueField]);
                    });
                }
            }
        }
    });
}

function validarCampos(e) {
    let validado = true;
    let $remetente = $("#emailremetente");
    let frete = $("#fretemodalidade").val();
    let ccfrete_id = $("#ccfrete_id").val();
    let pcfrete_id = $("#pcfrete_id").val();
    let cartao = $("#pedidovalidacartao").prop('checked');
    let dias = $("#pedidovalidacartaodias").val();
    let chave = $("#chavepix").val()

    if (frete != 9 && (isEmpty(ccfrete_id) || isEmpty(pcfrete_id)) && (frete != '' || frete != null)) {
        validado = false;
        e.preventDefault();
        bootbox.alert('O centro de custos e plano de contas para o frete é obrigatório caso a opção modalidade de freta seja diferente de Sem Frete!');
        return false;
    }

    if ($("#presencacomprador").val() === "4" && (frete == 9 || frete == "" || frete == null)) {
        validado = false;
        e.preventDefault();
        bootbox.alert('A Modalidade Frete é obrigarória quando a presença do comprador é 4!');
        return false;
    }

    if ( !$remetente.isEmpty() ) {
        let pass = true;
        for (let i = 0; i < email.length; i++) {
            if ( $(`#${email[i]}`).isEmpty() ) {
                pass = false;
                break;
            }
        }
        if ( !pass ) {
            e.preventDefault();
            bootbox.alert('Por favor, informe todos os campos obrigatórios do e-mail (senha, servidor, porta, assunto e corpo)!');
            return false;
        }
    }

    if (cartao) {
        if (!isEmpty(dias) && dias > 0) {
            return validado;
        } else {
            e.preventDefault();
            bootbox.alert('Caso o pedido valida cartão esteja marcado o campo de dias é obrigatorio e maior que 0.');
        }
    }

    // if (!isEmpty(chave)) {
    //     e.preventDefault();
    //     $("#modalSenha").modal("show");
    // }
}

function verificarMatriz() {
    var matriz = $("#matriz").val() == 1;
    var btns = ['btnPcFrete', 'btnPcontaValGas', 'btnPcontaDesJu', 'btnPcontaDesDes',
        'btnPcontaRecJu', 'btnPcontaRecDes', 'btnPcontaCartao', 'btnPconta'];

    if (!matriz) {
        for (var i = 0; i < btns.length; i++) {
            $("#" + btns[i]).prop('disabled', true);
        }
    }
}

function checkCartao( $valida ) {
    if ( $valida.prop('checked') ) {
        $("#pedidovalidacartaodias").prop('readonly', false);
    } else {
        $("#pedidovalidacartaodias").prop('readonly', true);
    }
}

const callbackSenha = function() {
    $("#fmCadastro").submit();
};
