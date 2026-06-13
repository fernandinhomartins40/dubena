var editingUf = false;
var arrayFieldsUf = [
    "#id_imp_est", "#origem_uf", "#destino_uf", "#estadosnficms_id", "#estadosnficmsaliq", "#estadosnficmsbase",
    "#estadosmodicms", "#estadosmodicmsst", "#estadosaliqicmsst", "#estadopjmva", "#estadonficmsbasest",
    "#estadosnfmotdesonicms", "#estadopfnficms_id", "#estadopfnficmsaliq", "#pfaliqicmsdest", "#estadopfnficmsabase",
    "#estadopfmodicms", "#estadopfmodicmsst", "#estadopftxafecop", "#pfestadosnfmotdesonicms, #estadostaxafecop",
    "#estadosbeneficiario_id", "#estadospfbeneficiario_id"
];

var arrayFieldsPF = [
    '#pfnfcofins_id', '#pfnfcofinsaliq', '#pfnfcofinsbase', '#pfnfpis_id',
    '#pfnfpisaliq', '#pfnfpisbase', '#pfnficms_id', '#pfnficmsaliq',
    '#pfnficmsbase', '#modalidadebcicmspf', '#pforigemicms',
    '#modalidadebcicmsstpf', '#pftaxafecopgr', '#pfnfmotdesonicms'
];

var arrayFieldsPJ = [
    '#nfoperacao_id', '#grupofiscal_id', '#nfcofins_id', '#nfcofinsaliq',
    '#nfcofinsbase', '#nfpis_id', '#nfpisaliq', '#nfpisbase', '#nficms_id_pj',
    '#nficmsaliq', '#nficmsbase', '#modalidadebcicms', '#nfaliqdiferimento',
    '#nficmsaliq', '#taxafecop', '#modalidadebcicmsst', '#aliqicmsst',
    '#nficmsbasest', '#mva', '#mvareduzido', '#nfmotdesonicms'
];

$(document).ready(function () {
    validateAllFieldsICMS('pj', $("#nficms_id_pj").val(), $("label[for='origemicms']"));
    validateAllFieldsICMS('pf', $("#pfnficms_id").val());
    validateAllFieldsICMS('ufPj', $("#estadosnficms_id").val());
    validateAllFieldsICMS('ufPf', $("#estadopfnficms_id").val());
});

$("#nfcofins_id").on('change', function () {
    buscarNaturePis(urlNaturezaPis);
});

function onDocReady() {
    $(".percentagem, .percentagemAlowZero, .baseCalculoSuffix").trigger('mask.maskMoney');
    if (showing) {
        desativarInputs();
        desativarInputsEspecificos(['td button.hidden']);
        $("#btnAdicionarImpostos").prop('disabled', true);
    } else {
        buscarNaturePis(urlNaturezaPis);
    }

    tblImpostosEstado = new GreatTable($("#tblImpostosEstado"), {
        'cache': false,
        'contentHeight': 200,
        'sort': {
            'sort': 'origem_uf',
            'order': 'asc'
        }
    });

    tblImpostosEstado.render();
}

$(window).load(function () {
    var style = $("#tblImpostosEstado").attr('style');
    $("#tblImpostosEstado").attr('style', style + 'margin-top: 4px;');

    setTimeout(function () {
        var data = [];
        if (!errorsany) {
            data = estadosImp;
        } else {
            data = carregarImpostosErro();
        }

        if (tblImpostosEstado.addRow(data, true)) {
            setTimeout(function () {
                tblImpostosEstado.adjustWidth();
            }, 250);
        }

    }, 1);

    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        tblImpostosEstado.adjustWidth();
    });
});

function createButtonsGrid() {
    if (showing) {
        var title = "Visualizar";
        var disabled = " disabled='true' ";
        var classBtn = 'hidden';
    } else {
        var classBtn = '';
        var title = 'Editar';
        var disabled = "";
    }
    var button = "<button type='button' class='btn btn-nw-geral btn-xs' ";
    button += " onclick='editarTable($(this).parents(\"tr\"))' id='btnEditarImposto'>" + title + "</button> ";
    button += "<button type='button' class='btn btn-nw-registro " + classBtn + " btn-xs' onclick='removeRow($(this).parents(\"tr\"))'";
    button += disabled + " id='btnRemoverImpostos'>Remover</button>";
    return button;
}

function removeRow(row) {
    tblImpostosEstado.removeRow(row);
}

$("#pfnficms_id").bind('chosen:updated', function () {
    var val = $(this).val();
    validateAllFieldsICMS('pf', val);
}).change(function () {
    var val = $(this).val();
    validateAllFieldsICMS('pf', val);
});

$("#nficms_id_pj").bind('chosen:updated', function () {
    var val = $(this).val();
    validateAllFieldsICMS('pj', val, $("label[for='origemicms']"));
}).change(function () {
    var val = $(this).val();
    validateAllFieldsICMS('pj', val, $("label[for='origemicms']"));
});

$("#estadopfnficms_id").bind('chosen:updated', function () {
    var val = $(this).val();
    validateAllFieldsICMS('ufPf', val);
}).change(function () {
    var val = $(this).val();
    validateAllFieldsICMS('ufPf', val);
});

$("#estadosnficms_id").bind('chosen:updated', function () {
    var val = $(this).val();
    validateAllFieldsICMS('ufPj', val);
}).change(function () {
    var val = $(this).val();
    validateAllFieldsICMS('ufPj', val);
});

function validateAllFieldsICMS(prefix, val, $labelOrigICMS) {
    var cst = allCodICMS.where('id', val).first().cst;
    var nowIsSN = isSN.where('cst', cst).first(true);

    validateFieldsICMS(prefix, "ICMSDeson", val);
    validateFieldsICMS(prefix, "ICMSST", val);
    validateFieldsICMS(prefix, "ICMSREDBC", val);
    validateFieldsICMS(prefix, "ICMSREDBCST", val);
    validateFieldsICMS(prefix, "ICMSFCPNormal", val);
    validateFieldsICMS(prefix, "Diferimento", val);
    validateFieldsICMS(prefix, "MODBC", val);
    validateFieldsICMS(prefix, "MODBCST", val);

    if ($labelOrigICMS) {
        $labelOrigICMS.removeClass('col-sm-2 col-sm-1');
        if (cst != '51') {
            $labelOrigICMS.addClass('col-sm-2');
        } else {
            $labelOrigICMS.addClass('col-sm-1');
        }
    }

    var $lBase = $("label[for='estadonficmsbasest']");
    if ((cst == '60' || cst == '500') && prefix === "ufPj") {
        $lBase.removeClass('col-sm-2 col-sm-1');
        $lBase.addClass('col-sm-2');
    } else if (prefix === "ufPj") {
        $lBase.removeClass('col-sm-2 col-sm-1');
        $lBase.addClass('col-sm-1');
    }

    $(".pjGroupICMSDeson").find('.chosen-results').css('cssText', 'max-height: 150px !important');

    var $div = $("." + prefix + "GroupICMSNormal");
    $div.removeClass('hidden');

    if (nowIsSN && !isEmpty(val) && !allowedICMSST.where('cst', cst).first().cst) {
        $div.addClass('hidden');
        $div.find('input').val('').trigger('chosen:updated');
    }

    var $crud_space = $('.crud_space');
    $crud_space.removeClass('hidden');
    $crud_space.each(function () {
        if ($(this).children('div:not(.hidden)').length === 0)
            $(this).addClass('hidden');
    });

    if (typeof cst !== "undefined")
        fillMotDeson(cst, prefix);

    prefix = prefix == "pj" ? "" : prefix;
    let $labelMon = $("#" + prefix + "nficmsalimono_lb");
    let $inputMon = $("#" + prefix + "nficmsalimono");
    let $labelAli = $("#" + prefix + "nficmsaliq_lb");
    let $inputAli = $("#" + prefix + "nficmsaliq");

    if (cst == "61") {
        if ($labelMon.hasClass("hidden")) $labelMon.removeClass("hidden");
        if ($inputMon.hasClass("hidden")) $inputMon.removeClass("hidden");
        if (!$labelAli.hasClass("hidden")) $labelAli.addClass("hidden");
        if (!$inputAli.hasClass("hidden")) $inputAli.addClass("hidden");
    }

    if (cst != "61") {
        if (!$labelMon.hasClass("hidden")) $labelMon.addClass("hidden");
        if (!$inputMon.hasClass("hidden")) $inputMon.addClass("hidden");
        if ($labelAli.hasClass("hidden")) $labelAli.removeClass("hidden");
        if ($inputAli.hasClass("hidden")) $inputAli.removeClass("hidden");
    }
}

function fillMotDeson(cst, prefix)
{
    var alloweds = motDesICMS.where('cst', 'in', cst).first(true);

    if (alloweds) {
        var $select = getSelectMotDeson(prefix);
        $select.empty();
        appendChosen($select, '', "Selecione");
        $.each(alloweds.elements, function (i, el) {
            appendChosen($select, i, el);
        });

        $select.val(getValueMotDeson(prefix)).trigger('chosen:updated');
    }
}

function getValueMotDeson(prefix) {
    return getMotDeson(prefix, "value");
}

function getSelectMotDeson(prefix) {
    return getMotDeson(prefix);
}

function getMotDeson(prefix, type = "select") {
    if (prefix === "pj") {
        if (type === "select")
            return $('#nfmotdesonicms');
        else
            return motDesonPj;
    } else if (prefix === "pf") {
        if (type === "select")
            return $('#pfnfmotdesonicms');
        else
            return motDesonPf;
    } else if (prefix === 'ufPj') {
        if (type === "select")
            return $('#estadosnfmotdesonicms');
        else
            return motDesonPjUf;
    } else {
        if (type === "select")
            return $('#pfestadosnfmotdesonicms');
        else
            return motDesonPfUf;
}
}

function appendChosen($select, value, html) {
    $select.append("<option value='" + value + "'>" + html + "</option>");
}

function validateFieldsICMS(prefix, arrayName, cst_id) {
    var alloweds = eval('allowed' + arrayName);
    var cst = allCodICMS.where('id', cst_id).first().cst;
    var allowed = alloweds.where('cst', cst).first(true);

    var $div = $("." + prefix + "Group" + arrayName);
    $div.removeClass('hidden');
    var $input = $div.find('input :not(select)');
    var $select = $div.find('select');
    $select.prop('disabled', showing);
    $input.prop('disabled', showing);
    //MODBCST é obrigatório mesmo não mandando os valores do ST e os csts 202 e 203 NÃO POSSÚEM ST PARA PF
    if ((prefix == 'pf' || prefix == 'ufPf') && $.inArray(cst, ['203', '202']) >= 0 && arrayName !== "MODBCST") {
        allowed = false;
    }

    if (!allowed) {
        $div.addClass('hidden');
        $select.val('0,00').prop('disabled', true);
        $input.val('').prop('disabled', true);
    }
    $select.trigger('chosen:updated');
}

$("#fmCadastro").on("submit", function (e) {
    if (editingUf) {
        e.preventDefault();
        e.stopPropagation();
        var msg = "Você está no meio de uma edição de impostos por estado, deseja continuar sem adiciona-lo na tabela? O registro será removido.";
        confirm(msg, function (res) {
            if (res) {
                editingUf = false;
                $("#fmCadastro").submit();
            }
        });
    }
    var imp = tblImpostosEstado.getData();
    for (let i = 0; i < imp.length; i++) {
        imp[i].buttons = "";
    }
    $("#impostosestados").val(JSON.stringify(imp));
    if (!checarVazios($("#tab_1"), '.input-pj') || !checarVazios($("#tab_2"), '.input-pf')) {
        e.preventDefault();
    }
});

$("#btnAdicionarImpostos").on('click', function () {
    if (checarVazios() && !checarIgualdade()) {
        adicionarDados();
    }
});

$("#goback").click(function () {
    var url = getParametro('index');

    if (!url) {
        url = root + "/nfimposto";
    }

    window.location.href = url.replace('extPar', "&");
});

$("#modalidadebcicms").change(function () {
    modalidade();
});

$("#modalidadebcicmsst").change(function () {
    modalidadeSt();
});

function checarVazios($selector = $("#tab_3"), inputClass = '.input-uf') {
    let vazios = true;
    let motDeson = [
        "estadosnfmotdesonicms", "pfestadosnfmotdesonicms",
        "nfmotdesonicms", "pfnfmotdesonicms"
    ];
    let emptyAllowed = [
        "estadosbeneficiario_id", "estadospfbeneficiario_id"
    ];

    $selector.find('.crud_space:not(.hidden) input' + inputClass + ', .crud_space:not(.hidden) select' + inputClass).each(function (i, el) {
        let $field = $(el);
        let isHidden = $field.is(':hidden');
        let isSelect = $field.is('select');

        let isMotDeson = $.inArray($field.attr('id'), motDeson) >= 0;
        let isAllowed = $.inArray($field.attr('id'), emptyAllowed) >= 0;

        if (isSelect) {
            isHidden = $field.parent('div').children('div.chosen-container').is(':hidden');
        }

        let isValid = isFieldUF($field) || isFieldPF($field) || isFieldPJ($field);

        if (isValid && isEmpty($field.val()) && !isHidden && !isMotDeson && !isAllowed) {
            vazios = false;
        } else if (isValid && isEmpty($field.val()) && isHidden) {
            $field.val('').trigger('chosen:updated');
        }
    });

    $selector.find('.crud_space.hidden input' + inputClass + ', .crud_space.hidden select' + inputClass).each(function (i, el) {
        let $field = $(el);
        let isValid = isFieldUF($field) || isFieldPF($field) || isFieldPJ($field);
        if (isValid && $field.is(':hidden')) {
            $field.val('').trigger('chosen:updated');
        }
    });

    if (!vazios) {
        let msg = "Não é possível gravar pois alguns valores não ocultos estão vazios";
        if (inputClass === '.input-uf')
            msg = 'Não é permitido inserir dados vazios na tabela.';

        bootbox.alert(msg);
    }

    return vazios;
}

function isFieldPF($el) {
    return $.inArray("#" + $el.attr('id'), arrayFieldsPF) > -1;
}

function isFieldPJ($el) {
    return $.inArray("#" + $el.attr('id'), arrayFieldsPJ) > -1;
}

function isFieldUF($el) {
    return $.inArray("#" + $el.attr('id'), arrayFieldsUf) > -1;
}

function modalidade() {
    var mod = $("#modalidadebcicms").val();
    $("#modalidadebcicmspf").val(mod).trigger('chosen:updated');
    $("#estadosmodicms").val(mod).trigger('chosen:updated');
    $("#estadopfmodicms").val(mod).trigger('chosen:updated');
}

function modalidadeSt() {
    var modst = $("#modalidadebcicmsst").val();
    $("#modalidadebcicmsstpf").val(modst).trigger('chosen:updated');
    $("#estadosmodicmsst").val(modst).trigger('chosen:updated');
    $("#estadopfmodicmsst").val(modst).trigger('chosen:updated');

}

function adicionarDados() {
    //tem 33 na table
    var data = {
        id: $("#id_imp_est").val(),
        origem_uf: $("#origem_uf").val(),
        destino_uf: $("#destino_uf").val(),
        nficms_id: $("#estadosnficms_id").val(),
        nficms_id_desc: getText('estadosnficms_id'),
        beneficiario_id: $("#estadosbeneficiario_id").val(),
        beneficiario_id_desc: getText('estadosbeneficiario_id'),
        nficmsaliq: $("#estadosnficmsaliq").val(),
        nficmsbase: $("#estadosnficmsbase").val(),
        nficmsmodalidadebc: $("#estadosmodicms").val(),
        nficmsmodalidadebc_desc: getText('estadosmodicms'),
        nficmsstmodalidadebc: $("#estadosmodicmsst").val(),
        nficmsstmodalidadebc_desc: getText('estadosmodicmsst'),
        nficmsbasest: $("#estadonficmsbasest").val(),
        nficmsstaliq: $("#estadosaliqicmsst").val(),
        mva: $("#estadopjmva").val(),
        pfnficms_id: $("#estadopfnficms_id").val(),
        pfnficms_id_desc: getText('estadopfnficms_id'),
        pfbeneficiario_id: $("#estadospfbeneficiario_id").val(),
        pfbeneficiario_id_desc: getText('estadospfbeneficiario_id'),
        pfnficmsaliq: $("#estadopfnficmsaliq").val(),
        pfaliqicmsdest: $("#pfaliqicmsdest").val(),
        pfnficmsbase: $("#estadopfnficmsabase").val(),
        pfnficmsmodalidadebc: $("#estadopfmodicms").val(),
        pfnficmsmodalidadebc_desc: getText('estadopfmodicms'),
        pftaxafecop: $("#estadopftxafecop").val(),
        taxafecop: $("#estadostaxafecop").val(),
        pfnficmsstmodalidadebc: $("#estadopfmodicmsst").val(),
        pfnficmsstmodalidadebc_desc: getText('estadopfmodicmsst'),
        nfmotdesonicms: $("#estadosnfmotdesonicms").val(),
        nfmotdesonicms_desc: getText('estadosnfmotdesonicms'),
        pfnfmotdesonicms: $("#pfestadosnfmotdesonicms").val(),
        pfnfmotdesonicms_desc: getText('pfestadosnfmotdesonicms'),
        buttons: button
    };

    tblImpostosEstado.addRow(data, true, function () {
        limparCampos();
        return true;
    });
    editingUf = false;
}

function getText(selector)
{
    var text = $("#" + selector + " option:selected").text();
    return text.toLowerCase().trim() !== 'selecione' ? text : '';
}

function carregarImpostosErro() {
    var impostos = JSON.parse($("#impostosestados").val());
    for (let i = 0; i < impostos.length; i++) {
        impostos[i].buttons = button;
    }
    return impostos;
}

function checarIgualdade() {
    var origem = $("#origem_uf").val();
    var destino = $("#destino_uf").val();
    var igualdade = false;
    $.each(tblImpostosEstado.getData(), function (i, el) {
        if (origem === el.origem_uf && destino === el.destino_uf) {
            igualdade = true;
            bootbox.alert('Impostos com está Origem e Destino já foram inseridos, não podem ser repetidos.');
            return igualdade;
        }
    });

    return igualdade;
}

function limparCampos() {
    for (var i = 0; i < arrayFieldsUf.length; i++) {
        $(arrayFieldsUf[i]).val('').trigger('chosen:updated');
    }
}

function buscarNaturePis(url) {
    var id = $("#nfcofins_id").val();
    var urlbuscar = url.replace(':id', id);
    var natureza = $("#piscofinsnatreceita");
    var naturezaval = $("#piscofinsnatreceita_hd").val();
    if (!isEmpty(id)) {
        ajaxGenerator(urlbuscar, 'GET', function (data) {
            natureza.empty().trigger('chosen:updated');
            natureza.append("<option value=''>Selecione</option>");
            for (i = 0; i < data.length; i++) {
                var codigo = data[i].codigo;
                var descricao = data[i].descricao;
                var id = data[i].id;
                natureza.append("<option value='" + id + "' >" + codigo + " " + descricao + "</option>");
            }
            if (!isEmpty(naturezaval)) {
                natureza.val(naturezaval);
            }
            natureza.trigger('chosen:updated');
        }, function () {
            $("#piscofinsnareceita").empty().trigger('chosen:updated');
        });
    }
}

function confirm(message, callback) {
    bootbox.confirm({
        title: "Atenção!",
        className: "dontHideEsc",
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
        backdrop: true,
        closeButton: false,
        callback: function (res) {
            callback(res);
        }
    });
}

function editarTable(row) {
    if (editingUf && !showing) {
        var msg = "Você já está no meio de uma edição de impostos por estado, deseja cancelar e editar outra? A anterior será perdida.";
        callback = function (res) {
            if (res) {
                editingUf = false;
                editarTable(row);
            }
        };
        confirm(msg, callback);
        return false;
    }
    var data = tblImpostosEstado.getRow(row).first();

    $("#id_imp_est").val(data.id);
    $("#origem_uf").val(data.origem_uf).trigger('chosen:updated');
    $("#destino_uf").val(data.destino_uf).trigger('chosen:updated');
    $("#estadosnficms_id").val(data.nficms_id).trigger('chosen:updated');
    $("#estadosnficmsaliq").val(data.nficmsaliq);
    $("#estadosnficmsbase").val(data.nficmsbase);
    $("#estadosmodicms").val(data.nficmsmodalidadebc).trigger('chosen:updated');
    $("#estadosmodicmsst").val(data.nficmsstmodalidadebc).trigger('chosen:updated');
    $("#estadosaliqicmsst").val(data.nficmsstaliq);
    $("#estadopjmva").val(data.mva);
    $("#estadonficmsbasest").val(data.nficmsbasest);
    $("#estadopfnficms_id").val(data.pfnficms_id).trigger('chosen:updated');
    $("#estadopfnficmsaliq").val(data.pfnficmsaliq);
    $("#pfaliqicmsdest").val(data.pfaliqicmsdest);
    $("#estadopfnficmsabase").val(data.pfnficmsbase);
    $("#estadopfmodicms").val(data.pfnficmsmodalidadebc).trigger('chosen:updated');
    $("#estadopftxafecop").val(data.pftaxafecop);
    $("#estadopfmodicmsst").val(data.pfnficmsstmodalidadebc).trigger('chosen:updated');
    $("#pfestadosnfmotdesonicms").val(data.pfnfmotdesonicms).trigger('chosen:updated');
    $("#estadosnfmotdesonicms").val(data.nfmotdesonicms).trigger('chosen:updated');
    $("#estadostaxafecop").val(data.taxafecop);
    $("#estadosbeneficiario_id").val(data.beneficiario_id).trigger('chosen:updated');
    $("#estadospfbeneficiario_id").val(data.pfbeneficiario_id).trigger('chosen:updated');

    if (!showing)
        tblImpostosEstado.removeRow(row);

    editingUf = true;
}
