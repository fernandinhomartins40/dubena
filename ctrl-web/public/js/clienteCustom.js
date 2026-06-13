var btnRemoveProdConvenio = '<button type="button" class="btn btn-xs btn-nw-registro" id="btnRemoveProdConvenio">Remover</button>';
var tblClientePromocoes;
var tblCondPgto;
var tblProdutosPrecos;
var tblParentesco;
var tblFone;
var tblProdConvenio;
var tblCont;
var confirm;
var t;
var operacaocontato;
var linhacontato;
var dateDefault = true;
var oldContato;
var allTables = {
    tblCondPgto: {
        'added': false,
        'removed': []
    },//usa o cond_id como primary
    tblProdConvenio: {
        'added': false,
        'removed': []
    },//tblProdConvenio
    tblClientePromocoes: {
        'added': false,
        'removed': []
    },//tblClitenteProcmocoes
    tblCont: {
        'added': false,
        'removed': []
    },//tblContatos
    tblFone: {
        'added': false,
        'removed': []
    },//tblTelefones
    tblParentesco: {
        'added': false,
        'removed': []
    },//tblParentesco
    tblProdutosPrecos: {
        'added': false,
        'removed': []
    }//tblProdutosPrecos
};

$(document).ready(function () {
    mudarTipoPessoa();
    mostrarIE();
    ativarDesativarInputsConveniado();
    ativarDesativarInputsConvenio();
    enableDisableFieldsPromo();
    setEventsTelefone();
    changeClassValue();

    $("#parentescoAtivo").prop('checked', true);
    $(".modal-wide").on("show.bs.modal", function () {
        var height = $(window).height() - 200;
        $(this).find(".modal-body").css("max-height", height);
    });

    $("#fmCadastro").on("submit", function (e) {
        if ($("#telefone").is(":focus")) {
            e.preventDefault();
            return false;
        }

        contentTablesToJSON();
        $("#fornecedor, #cliente, #transportador").prop('disabled', false);
    });


    $("#btnGravarComSenha").on("click", function (e) {
        callbackSenha = function () {
            $('#fmCadastro').submit();
        }
        $("#modalSenha").modal('show');
    });

    initTable();
    var $condicaopagamento = $("#condicaopagamento_id");
    $condicaopagamento.on('change', function () {
        enableDisableBtnCondPgto();
    });

    var $promocao_id = $("#promocao_id");
    $promocao_id.change(function () {
        buscaPromocaoAjax(root + "/buscaPromocaoAjax/:promocao_id");
    });

    disableOptions(tblProdConvenio, $("#prodconvenio"), 1);
    disableOptions(tblClientePromocoes, $promocao_id, 1);
    disableOptions(tblCondPgto, $condicaopagamento, 0);
    disableOptions(tblProdutosPrecos, $("#selectProdutosPrecos"), 1);

    $('#responsaveltipo_id').val('');

    $("#tblClientePromocoes").on('click', 'button', function () {
        removeFromTable(tblClientePromocoes, $(this), "tblClientePromocoes");
        disableOptions(tblClientePromocoes, $promocao_id, 1);
    });

    $("#tblCondPgto").on('click', 'button', function () {
        removeFromTable(tblCondPgto, $(this), "tblCondPgto");
        disableOptions(tblCondPgto, $("#condicaopagamento_id"), 0);
        enableDisableBtnCondPgto();
    });

    $("#tblProdConvenio").on('click', 'button', function () {
        removeFromTable(tblProdConvenio, $(this), "tblProdConvenio");
        disableOptions(tblProdConvenio, $("#prodconvenio"), 1);
    });

    $("#tblProdutosPrecos").on('click', 'button', function () {
        removeFromTable(tblProdutosPrecos, $(this), "tblProdutosPrecos");
        disableOptions(tblProdutosPrecos, $("#selectProdutosPrecos"), 1);
    });

    $('#tblParentesco').on('click', 'button', function () {
        removeFromTable(tblParentesco, $(this), "tblParentesco");
    });

    $('#tblTelefones').on('click', 'button', function () {
        var trElem = $(this).closest("tr");// grabs the button's parent tr element
        var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id

        if ($(firstTd).text()) {
            if ($(this).context.id !== 'btnRemoverTelefone') {
                editarTelefone( trElem );
            }
            removeFromTable(tblFone, $(this), "tblFone");
        }
    });

    $('#tblContatos').on('click', 'button', function () {
        var row = $(this).closest('tr');
        var data = $('#tblContatos').dataTable().fnGetData(row);
        if (data[0]) {
            if ($(this).context.id === 'btnEditarContato') {
                operacaocontato = 'Edit';
                linhacontato = row;
                oldContato = data[0];
                $('#contatotipo_id').val(data[2]);
                $('#contatosituacao_id').val(data[4]);
                $('#descricaocontato').val(data[6]);
                $('#acaocontato').val(data[7]);
                $('#datacontato').val(data[1]);
                $('#popup_contato').modal('show');
            }
            removeFromTable(tblCont, $(this), "tblCont");
        }
    });
    $(".btnCloseContato").on('click', function () {
        $("#popup_contato").modal('hide');
    });

    $(".btnClosePromocao").on('click', function () {
        $("#modalClientePromocoes").modal('hide');
    });

    $("#telefone").on('keyup', function () {
        enableDisableBtnAddTel();
    });

    if (typeof errorsAny !== "undefined" && !errorsAny) {
        let $fornecedor = $("#fornecedor");
        let $cliente = $("#cliente");
        let $transportador = $("#transportador");
        $fornecedor.prop('disabled', $fornecedor.prop('checked'));
        $cliente.prop('disabled', $cliente.prop('checked'));
        $transportador.prop('disabled', $transportador.prop('checked'));
    }
    $(".modal").on('shown.bs.modal', function (e) {
        var id = $(this).context.id;
        setTimeout(function () {
            switch (id) {
                case "modalChangeProdConvenio":
                    $("#prodconvenio").focus().trigger("chosen:activate");
                    break;
                case "modalClientePromocoes":
                    $("#promocao_id").focus().trigger("chosen:activate");
                    break;
                case "popup_contato":
                    $("#contatotipo_id").focus().trigger("chosen:activate");
                    break;
            }
        }, 100);
    });
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        var $target = $(e.target);
        var href = $target.attr("href");
        if (href === "#subtab_1") {
            showConvenioEmpresa()
        } else if (href === "#subtab_2") {
            hideConvenioEmpresa();
        } else if (href === "#tab_6" ) {
            changeFocusSubtab();
        } else {
            var target = $target.attr('data-focus');
            var dis = $("#nome").prop('disabled');
            if ( target && ! dis ) {
                let $selector = $("#" + target);
                $selector.focus().trigger("chosen:activate");
            }
        }
    });

    $("#parentesco_id").change(function () {
        if ($("#parentesco_id").val() && $("#nomeClienteParentesco").val()) {
            $("#btnAddParentesco").prop('disabled', false);
        } else {
            $("#btnAddParentesco").prop('disabled', true);
        }
    });

    $("#nomeClienteParentesco").on('keyup', function () {
        if ($("#parentesco_id").val() !== '' && $(this).val() !== '') {
            $("#btnAddParentesco").prop('disabled', false);
        } else {
            $("#btnAddParentesco").prop('disabled', true);
        }
    });

    $(".delete").on("submit", function () {
        return confirm("Quer remover o registro atual?");
    });


    $('#datafim').on('dp.change', function () {
        let $dataFim = $(this);
        let $dataInicio = $("#datainicio");
        if ($dataFim.val() < $dataInicio.val()) {
            $dataFim.val($dataInicio.val());
        }
    });

    $('#datainicio').on('dp.change', function () {
        let $dataFim = $("#datafim");
        let $dataInicio = $(this);
        if ($dataInicio.val() > $dataFim.val()) {
            $dataInicio.val($dataFim.val());
        }
    });

    $(".datePickerCliente").on('dp.change', function () {
        let $dataFim = $("#datafim");
        if ($("#datainicio").val() > $dataFim.val()) {
            $dataFim.prop('data-toggle', "tooltip");
            $dataFim.prop('data-placement', "bottom");
            $dataFim.prop('title', "A data de início não pode ser maior que a final");
        }
    });

    $("#btnAddParentesco").on('click', function () {
        addParentesco();
    });

    $("#btnAddPromocao").on('click', function () {
        btnAddPromocao();
    });

    $("#btnAddCondicaoPagamento").on('click', function () {
        addCondPgto();
    });

    $("#btnAddProdConvenio").on('click', function () {
        addProdConvenio();
    });

    $("#btnAddFone").on('click', function () {
        buscarTelefonesClientes();
    });
});

setTimeout(function () {
    $("#nome").focus();
}, 1);

function enableDisableBtnAddTel() {
    $("#btnAddFone").prop('disabled', $("#telefone").val().length === 0);
}

function buscarTelefonesClientes() {
    var url = root + "/clientetelefone/buscaclientetelefone/:tel?cliente_id=:cliente_id&validateExists=1";
    if (!$.isNumeric($('#telefonetipo_id').val())) {
        bootbox.alert('Preencha o tipo de telefone.');
        return;
    }
    var $tel = $('#telefone');
    if (!$tel.val().trim()) {
        bootbox.alert('Preencha o telefone.');
        return;
    }
    if ($tel.val().length < 14) {
        bootbox.alert('O telefone está incompleto');
        $tel.focus();
        return;
    }
    var tel = $tel.val();
    var cliente_id = $("#cliente_id").val();
    url = url.replace(':tel', tel);
    if (typeof cliente_id === 'undefined' || isEmpty(cliente_id)) {
        url = url.replace(':cliente_id', '0');
    } else {
        url = url.replace(':cliente_id', cliente_id);
    }
    url = url.replace(' ', '--');

    var foneExists = false;
    tblFone.column(3)
            .data()
            .each(function (value) {
                if ($("#telefone").val() === value) {
                    foneExists = true;
                }
            });
    if (foneExists) {
        bootbox.alert('Telefone já consta na lista desse usuário.');
    } else {
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            url: url,
            type: 'GET',
            success: function (data) {
                if (data.substr(0, 3) === "OK|") {
                    addFone();
                } else if (data.substr(0, 3) === "OPS") {
                    if ($("#tblTelefones").text().indexOf(tel) !== -1) {
                        bootbox.alert("Este telefone já está cadastrado para este cliente!");
                    } else {
                        addFone();
                    }
                } else {
                    bootbox.alert("Este telefone já está cadastrado para outro cliente: " + data);
                }
            }, error: function () {
                bootbox.alert('Erro ao verificar telefones!');
            }
        });
    }
}

function enableDisableBtnCondPgto() {
    if ($("#condicaopagamento_id").val() === '')
        $("#btnAddCondicaoPagamento").prop('disabled', true);
    else
        $("#btnAddCondicaoPagamento").prop('disabled', false);
}

function addParentesco() {
    let $nomeParentesco = $('#nomeClienteParentesco');
    let $parentesco_id = $('#parentesco_id');
    if (($nomeParentesco.val() === '')) {
        bootbox.alert('Preencha o nome.');
        return;
    }
    if ($parentesco_id.val() === '') {
        bootbox.alert('Preencha o parentesco.');
        return;
    }
    var ativo = 'Não';

    if ($("#parentescoAtivo").prop('checked') === true) {
        ativo = 'Sim';
    }
    addedTable("tblParentesco");
    tblParentesco.row.add([
        '',
        $nomeParentesco.val(),
        $parentesco_id.val(),
        $parentesco_id.find("option:selected").text(),
        ativo,
        "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverParentesco'>Remover</button>"
    ]).draw(false);
    $nomeParentesco.val('').focus();
}

function addFone() {
    let wpp = 'Não';
    let $tel = $('#telefone');
    let $wpp = $("#whatsapp");
    let $btnAdd = $("#btnAddFone");
    let $telTipo = $('#telefonetipo_id');
    if ($wpp.prop('checked')) {
        wpp = 'Sim';
    }
    if (!$btnAdd.prop('disabled')) {
        addedTable("tblFone");
        tblFone.row.add([
            '',
            $telTipo.val(),
            $telTipo.find("option:selected").text(),
            $tel.val(),
            wpp,
            "<button type='button' class='btn btn-nw-geral btn-xs' id='btnEditarTelefone'>Editar</button>&nbsp;&nbsp;" +
            "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverTelefone'>Remover</button>"
        ]).draw(false);
    }
    $tel.val('');
    $wpp.prop('checked', false);
    $btnAdd.prop('disabled', true);
    $telTipo.focus().trigger("chosen:activate");
}

function carregarTelefonesErro() {
    tblFone.clear();
    var telefones = JSON.parse($('#telefones').val());
    tblFone.rows.add(telefones).draw();
}

function carregarAllTablesErro() {
    allTables = JSON.parse($("#alltables").val());
}

function addContato() {
    let $contatoTipo = $('#contatotipo_id');
    let $contatoSit = $('#contatosituacao_id');
    let $descContato = $('#descricaocontato');
    let $acaoContato = $('#acaocontato');
    let $data = $('#datacontato');
    if (!$contatoTipo.intVal()) {
        bootbox.alert('Preencha o tipo de contato.');
        return;
    }
    if (!$contatoSit.intVal()) {
        bootbox.alert('Preencha a situação do contato.');
        return;
    }
    if (!$data.val().trim()) {
        bootbox.alert('Preencha a data do contato.');
        return;
    }
    if (!$descContato.val().trim()) {
        bootbox.alert('Preencha a descrição do contato.');
        return;
    }
    if (operacaocontato === "Add") {
        addedTable("tblCont");
        tblCont.row.add([
            '',
            $data.val(),
            $contatoTipo.val(),
            $contatoTipo.find("option:selected").text(),
            $contatoSit.val(),
            $contatoSit.find("option:selected").text(),
            $descContato.val(),
            $acaoContato.val(),

            "<button type='button' class='btn btn-nw-geral btn-xs' id='btnEditarContato'>Editar</button>\n\
            <button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverContato'>Remover</button>"
        ]).draw(false);
    } else {
        addedTable("tblCont");
        let $tblContatos = $('#tblContatos');
        $tblContatos.dataTable().fnUpdate('', linhacontato, 0);
        $tblContatos.dataTable().fnUpdate($data.val(), linhacontato, 1);
        $tblContatos.dataTable().fnUpdate($contatoTipo.val(), linhacontato, 2);
        $tblContatos.dataTable().fnUpdate($contatoTipo.find("option:selected").text(), linhacontato, 3);
        $tblContatos.dataTable().fnUpdate($contatoSit.val(), linhacontato, 4);
        $tblContatos.dataTable().fnUpdate($contatoSit.find("option:selected").text(), linhacontato, 5);
        $tblContatos.dataTable().fnUpdate($descContato.val(), linhacontato, 6);
        $tblContatos.dataTable().fnUpdate($acaoContato.val(), linhacontato, 7);
    }
    $acaoContato.val('');
    $descContato.val('');
    $('#popup_contato').modal('hide');
}

function createContato() {
    operacaocontato = 'Add';
    $('#acaocontato').val('');
    $('#descricaocontato').val('');
    $('#popup_contato').modal('show');
}

function carregarProdutosErro() {
    tblProdutosPrecos.clear();
    var produtos = JSON.parse($('#produtos').val());
    tblProdutosPrecos.rows.add(produtos).draw();
}
function carregarContatosErro() {
    tblCont.clear();
    var contatos = JSON.parse($('#contatos').val());
    tblCont.rows.add(contatos).draw();
}

function carregarParentescoErro() {
    tblParentesco.clear();
    var parentesco = JSON.parse($("#parentesco").val());
    tblParentesco.rows.add(parentesco).draw();
}

function carregarClientePromocoesErro() {
    tblClientePromocoes.clear();
    var clientepromocoes = JSON.parse($("#promocoes").val());
    tblClientePromocoes.rows.add(clientepromocoes);
    tblClientePromocoes.draw(false);
}

function carregarClienteCondicoesPagamentoErro() {
    tblCondPgto.clear();
    let clienteCondPto = JSON.parse($("#condicoespagamento").val());
    tblCondPgto.rows.add(clienteCondPto);
    tblCondPgto.draw(false);
}

function carregarProdConvenioErro() {
    var value = $("#clienteprodutosconvenios").val();
    if ( ! value.isEmpty() ) {
        tblProdConvenio.clear();
        var prodConvenio = JSON.parse(value);
        tblProdConvenio.rows.add(prodConvenio).draw(false);
    }
}

//conveniado
$("#convenio").change(function () {
    ativarDesativarInputsConveniado();
});

function ativarDesativarInputsConveniado() {
    if ($("#convenio").prop('checked') !== true) {
        $("#conveniolimite").prop('disabled', true);
        $("#convenio_id").prop('disabled', true).trigger('chosen:updated');
        $("#parentescoAtivo").prop('disabled', true);
        $("#nomeClienteParentesco").prop('disabled', true);
        $("#btnAddParentesco").prop('disabled', true);
        $("#parentesco_id").prop('disabled', true).trigger('chosen:updated');
        $("#codigo_convenio").prop('disabled', true);
    } else {
        let $nomeParentesco = $("#nomeClienteParentesco");
        let $parentesco_id = $("#parentesco_id");
        $("#conveniolimite").prop('disabled', false);
        $("#convenio_id").prop('disabled', false).trigger('chosen:updated');
        $("#parentescoAtivo").prop('disabled', false);
        $("#codigo_convenio").prop('disabled', false);
        $nomeParentesco.prop('disabled', false);
        if ($parentesco_id.val() !== '' && $nomeParentesco.val() !== '') {
            $("#btnAddParentesco").prop('disabled', false);
        }
        $parentesco_id.prop('disabled', false).trigger('chosen:updated');
    }
}
//convenio ativo
$("#convenioativo").change(function () {
    ativarDesativarInputsConvenio();
});

$('.cpf').blur(function () {
    var cpf_cnpj = $(this).val();
    if (valida_cpf_cnpj(cpf_cnpj)) {
    } else {
        if (cpf_cnpj !== '') {
            alert('CPF inválido!');
            $(this).focus();
            $(this).val('');
        }
    }
});

$('.cnpj').blur(function () {
    var cpf_cnpj = $(this).val();
    if (valida_cpf_cnpj(cpf_cnpj)) {
    } else {
        if (cpf_cnpj !== '') {
            alert('CNPJ inválido!');
            $(this).focus();
            $(this).val('');
        }
    }
});

function ativarDesativarInputsConvenio() {
    let disabled = $("#convenioativo").prop('checked') !== true;
    $("#datacontrato").prop('disabled', disabled);
    $("#nomerepresentante").prop('disabled', disabled);
    $("#cpfrepresentante").prop('disabled', disabled);
    $("#rgrepresentante").prop('disabled', disabled);
    $("#limitecompra").prop('disabled', disabled);
    $("#diafechamento").prop('disabled', disabled);
    $("#diavencimento").prop('disabled', disabled);
    $("#comissao").prop('disabled', disabled);
    $("#comissaodestino").prop('disabled', disabled).trigger('chosen:updated');
    $("#btnChangeProdConvenio").prop('disabled', disabled);
}

$("#btnChangeProdConvenio").on('click', function () {
    $("#modalChangeProdConvenio").modal('show');
});

//zera o input do limite convenio
$("#convenio_id").change(function () {
    var $limite = $("#conveniolimite");
    if ($limite.val() === '') {
        $limite.val(0);
    }
});

function buscaPromocaoAjax(url) {
    var promocao_id = $("#promocao_id").intVal();
    if (promocao_id) {
        url = url.replace(':promocao_id', promocao_id);
        ajaxGenerator(url, 'GET', function (data) {
            if (typeof data === "string") {
                bootbox.alert(data);
            } else {
                datePickerCliente(data.datahorainicio, data.datahorafim);
                $("#datainicio").val(data.datahorainiciof);
                $("#datafim").val(data.datahorafimf);
                enableDisableFieldsPromo();
            }
        }, function () {
            bootbox.alert('Erro ao buscar a promoção!');
        });
    } else {
        enableDisableFieldsPromo();
    }
}

function enableDisableFieldsPromo() {
    let $promo_id = $("#promocao_id");
    let $datainicio = $("#datainicio");
    let $datafim = $("#datafim");
    let disabled = !($datainicio.val() !== "" && $datafim.val() !== "" && $promo_id.intVal() > 0);
    $("#btnAddPromocao").prop('disabled', disabled);
    $datainicio.prop('disabled', disabled);
    $datafim.prop('disabled', disabled);
    if (disabled) {
        $datafim.val('');
        $datainicio.val('');
        $("#mediadias").val('');
    }
}

function datePickerCliente(min, max) {
    let $datePickerI = $("#datainicio").data('DateTimePicker');
    if ($datePickerI) {
        $datePickerI.destroy();
        $("#datafim").data('DateTimePicker').destroy();
        $(".datePickerCliente").datetimepicker({
            defaultDate: false,
            locale: 'pt-br',
            format: 'DD/MM/YYYY',
            viewMode: 'days',
            minDate: min,
            maxDate: max
        });
    } else {
        $(".datePickerCliente").datetimepicker({
            defaultDate: false,
            locale: 'pt-br',
            format: 'DD/MM/YYYY',
            viewMode: 'days',
            minDate: min,
            maxDate: max
        });
    }
    dateDefault = false;
}

function addProdConvenio() {
    let $prod = $("#prodconvenio");
    let precoProduto = $("#precoprodconvenio").val();
    let $selected = $prod.find("option:selected");
    if (!$prod.val() || !precoProduto) {
        bootbox.alert("Selecione um produto e valor.");
        return;
    }
    addedTable("tblProdConvenio");
    tblProdConvenio.row.add([
        '',
        $prod.val(),
        $selected.text(),
        precoProduto,
        btnRemoveProdConvenio
    ]).draw();
    $selected.prop('disabled', true).trigger('chosen:updated');
    $prod.focus().trigger("chosen:activate");
}

function btnAddPromocao() {
    let $promo = $("#promocao_id");
    let $selected = $promo.find("option:selected");
    if ($selected.prop('disabled')) {
        bootbox.alert("Selecione uma promoção ainda não informada na grid");
        return;
    }
    if (!$promo.intVal()) {
        bootbox.alert("Selecione uma promoção");
        return;
    }
    addedTable("tblClientePromocoes");
    tblClientePromocoes.row.add([
        '',
        $promo.val(),
        $selected.text(),
        $("#datainicio").val(),
        $("#datafim").val(),
        $("#mediadias").val(),
        '<button id="removerPromocao" type="button" class="btn btn-nw-registro btn-xs">Remover</button>'
    ]).draw(false);
    $selected.prop('disabled', true).trigger('chosen:updated');
    $promo.val("");
    $promo.trigger('chosen:updated').focus().trigger("chosen:activate");;
    enableDisableFieldsPromo();
}

function addCondPgto() {
    let $cond = $("#condicaopagamento_id");
    let $optSelected = $cond.find("option:selected");
    let condicaopagamento_id = $cond.val().replace('-0', '');
    condicaopagamento_id = condicaopagamento_id.replace('-1', '');
    condicaopagamento_id = condicaopagamento_id.replace('-2', '');
    condicaopagamento_id = condicaopagamento_id.replace('-3', '');
    condicaopagamento_id = condicaopagamento_id.replace('-4', '');
    condicaopagamento_id = condicaopagamento_id.replace('-5', '');
    addedTable("tblCondPgto");
    tblCondPgto.row.add([
        condicaopagamento_id,
        $optSelected.text(),
        '<button id="btnRemoverCondicaoPagamento" type="button" class="btn btn-nw-registro btn-xs">Remover</button>'
    ]).draw(false);
    $optSelected.attr('disabled', true);
    $cond.trigger('chosen:updated').focus().trigger("chosen:activate");
    $("#btnAddCondicaoPagamento").prop('disabled', true);
}

function contentTablesToJSON() {
    putTableInSelector($('#telefones'), tblFone);
    putTableInSelector($('#contatos'), tblCont);
    putTableInSelector($('#produtos'), tblProdutosPrecos);
    putTableInSelector($('#parentesco'), tblParentesco);
    putTableInSelector($('#promocoes'), tblClientePromocoes);
    putTableInSelector($('#condicoespagamento'), tblCondPgto);
    putTableInSelector($('#clienteprodutosconvenios'), tblProdConvenio);

    $("#alltables").val(JSON.stringify(allTables));
}

function putTableInSelector($selector, tbl) {
    let data = [];
    tbl.rows().every(function () {
        var d = this.data();
        data.push(d);
    });
    data = JSON.stringify(data);
    if (data) {
        $selector.val(data);
    } else {
        $selector.val('');
    }
}

function hideConvenioEmpresa() {
    $("#subtab_1").hide();
    changeFocusSubtab();
}

function showConvenioEmpresa() {
    $("#subtab_1").show();
    changeFocusSubtab();
}

$("#modalChangeProdutosConvenio").on('show.bs.modal', function () {
    $("#btnModalImprimir").prop('disabled', false);
});

$("form#fmImprimirEtiquetas").on('submit', function () {
    let apartir = $("#apartir").val();
    apartir = !isEmpty(apartir) ? apartir : 0;
    var id = $("#cliente_id").val();
    var url = root + '/cliente/etiquetas/' + apartir + '/' + id;
    window.open(url, '_blank');
    return false;
});

$("#nomeClienteParentesco").attr('maxlength', 25);

function changeClassValue() {
    var tipo = $("#tipo:checked").val();
    var $val0 = $("#valor0");
    var $val1 = $("#valor1");

    if (parseInt(tipo) === 1) {
        $val0.removeClass('hidden').val('');
        $val1.addClass('hidden').val('');
    } else {
        $val0.addClass('hidden').val('');
        $val1.removeClass('hidden').val('');
    }
}

$("#btnAddPreco").click(function () {
    let prod = !$("#selectProdutosPrecos").find("option:selected").isEmpty();
    let price = !$("#produtoValor").isEmpty();
    let $valor0 = $("#valor0");
    let desc = $valor0.isEmpty() ? !$("#valor1").isEmpty() : !$valor0.isEmpty();
    if (prod && (price || desc)) {
        addPrecoProdutoCliente();
    }
});

function addPrecoProdutoCliente() {
    let button = '<button id="btnRemoverProduto" type="button" class="btn btn-nw-registro btn-xs">Remover</button>';
    let $select = $("#selectProdutosPrecos");
    let $selected = $select.find("option:selected");
    let $descPara = $("#selectDescontoPara");
    let $descParaSelected = $descPara.find("option:selected");
    let $preco = $("#produtoValor");
    let $valor0 = $("#valor0");
    let $desc = $valor0.isEmpty() ? $("#valor1") : $valor0;
    let tipo = $("#tipo:checked").val();

    addedTable("tblProdutosPrecos");
    tblProdutosPrecos.row.add([
        '',
        $selected.val(),
        $selected.text(),
        $preco.val(),
        $desc.val(),
        tipo,
        $descParaSelected.val(),
        $descParaSelected.text(),
        button
    ]).draw();

    $selected.prop('disabled', true).trigger('chosen:updated');
    $select.val('').trigger('chosen:updated').focus().trigger("chosen:activate");
    $descPara.val(1).trigger('chosen:updated').focus().trigger("chosen:activate");
    $preco.val('');
    $desc.val('');
    $("#tipo[value=1]").prop('checked', true).trigger('click');
}

function mostrarIE() {
    $("#inscricao_estadual").prop('disabled', false);
    var $label = $("label[for='inscricao_estadual']");
    $label.removeClass('col-sm-1 col-sm-2');
    var tipopessoa = $("#tipopessoa_id").val();
    if (typeof tipopessoa !== "undefined" && tipopessoa.indexOf('F') !== -1)
        $label.addClass('col-sm-2');
    else
        $label.addClass('col-sm-1');
}

function editarTelefone( row ) {
    var data = tblFone.row( row ).data();
    var tipo = data[1];
    var telefone = data[3];
    var whats = data[4] === "Sim";

    $("#telefonetipo_id").val( tipo ).trigger('chosen:updated');
    $("#whatsapp").prop('checked', whats);

    $("#btnAddFone").prop('disabled', false);
    $("#telefone").val( telefone ).focus();
}

function disableOptions(table, $selector, index) {
    $selector.find("option").filter(function () {
        $(this).prop('disabled', false);
    });
    $selector.find("option").filter(function () {
        var $that = $(this);
        table.rows().every(function () {
            var i = this.data();
            if (parseInt($that.val()) === parseInt(i[index])) {
                $that.prop('disabled', true);
            }
        });
    });
    $selector.trigger("chosen:updated");
}

function setEventsTelefone() {
    $("#telefone").on('focusin', function () {
        let fun = function () {
            buscarTelefonesClientes();
        };
        shortcut.remove("Enter");
        shortcut.add("Ctrl+Space", fun);
        shortcut.add("Enter", fun);
    }).on('focusout', function () {
        shortcut.remove("Ctrl+Space");
        shortcut.remove("Enter");
    });
}

function initTable() {
    tblFone = $('#tblTelefones').DataTable({
        "language": {"url": urlDataTable},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": false,
        "bInfo": false,
        "bAutoWidth": false,
        "columnDefs": [
            {
                "targets": [0, 1],
                "visible": false
            }
        ]
    });
    tblProdConvenio = $('#tblProdConvenio').DataTable({
        "language": {"url": urlDataTable},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": false,
        "bInfo": false,
        "bAutoWidth": false,
        "columnDefs": [{
            "visible": false,
            "targets": [0]
        }]
    });
    tblCont = $('#tblContatos').DataTable({
        "language": {"url": urlDataTable},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": false,
        "bInfo": false,
        "bAutoWidth": false,
        "columnDefs": [{
            "visible": false,
            "targets": [0, 2, 4]
        }]
    });
    tblParentesco = $('#tblParentesco').DataTable({
        "language": {"url": urlDataTable},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": false,
        "bInfo": false,
        "bAutoWidth": false,
        "columnDefs": [{
            "targets": [0, 2],
            "visible": false
        }]
    });
    tblProdutosPrecos = $("#tblProdutosPrecos").DataTable({
        "language": {"url": urlDataTable},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": false,
        "bInfo": false,
        "bAutoWidth": false,
        "columnDefs": [{
            "visible": false,
            "targets": [0, 5, 6]
        }]
    });
    tblCondPgto = $("#tblCondPgto").DataTable({
        "language": {"url": urlDataTable},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": false,
        "bInfo": false,
        "bAutoWidth": false
    });
    tblClientePromocoes = $("#tblClientePromocoes").DataTable({
        "language": {"url": urlDataTable},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": false,
        "bInfo": false,
        "bAutoWidth": false,
        "columnDefs": [{
            "visible": false,
            "targets": [0]
        }]
    });
}

function removeFromTable(table, $button, strTable) {
    let row = $button.parents('tr');
    let tableRow = table.row(row);
    let data = tableRow.data();
    if (data[0]) {
        allTables[strTable].removed.push(data[0]);
    }
    tableRow.remove().draw();
}

function addedTable(strTable) {
    allTables[strTable].added = true;
}

function changeFocusSubtab() {
    setTimeout(function () {
        var $convenioativo = $("#convenioativo");
        if ($convenioativo.is(":visible")) {
            $convenioativo.focus();
        } else {
            $("#convenio").focus();
        }
    }, 100);
}
