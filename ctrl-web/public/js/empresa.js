jQuery(document).ready(function ($) {
    var options = {
        thumbBox: '.thumbBox',
        spinner: '.spinner',
        imgSrc: 'dist/img/upload.jpg'
    };
    verificarAmbiente();
    $("#nfeemitemodelos").trigger('change');
    var cropper = $('.imageBox').cropbox(options);

    $('#file').on('change', function () {
        var reader = new FileReader();
        reader.onload = function (e) {
            options.imgSrc = e.target.result;
            cropper = $('.imageBox').cropbox(options);
        };

        reader.readAsDataURL(this.files[0]);
        this.files = [];
    });

    $('#btnCrop').on('click', function () {
        var img = cropper.getDataURL();
        $('#cropped').prop('src', img);
        $('#logo').prop('value', img);
    });

    $('#btnZoomIn').on('click', function () {
        cropper.zoomIn();
    });

    $('#btnZoomOut').on('click', function () {
        cropper.zoomOut();
    });

    $('#btnRemoverImagem').on('click', function () {
        $('#cropped').prop('src', root + '/dist/img/upload.jpg');
        $('#logo').prop('value', '');
    });

    let $usasat = $('#usasat');
    $usasat.on('change', function () {
        $("#sattipoambiente").prop("disabled", ! $(this).is(":checked")).trigger("chosen:updated");
    });

    $("#sattipoambiente").prop("disabled", ! $usasat.is(":checked") || (typeof show !== "undefined" && show)).trigger("chosen:updated");

    document.getElementById('logo').onchange = function (evt) {
        var tgt = evt.target || window.event.srcElement,
                files = tgt.files;
        // FileReader support
        if (FileReader && files && files.length) {
            var fr = new FileReader();
            fr.onload = function () {
                document.getElementById('logoImg').src = fr.result;
            }
            fr.readAsDataURL(files[0]);
        }

    };
    $("#capacidadearmazenamento").keypress(function (e) {
        if (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) {
            return false;
        }
    });
    $("#codigoibgepais").keypress(function (e) {
        if (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) {
            return false;
        }
    });
    $("#nfenumerohomologacao").keypress(function (e) {
        if (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) {
            return false;
        }
    });
    $("#nfenumero").keypress(function (e) {
        if (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) {
            return false;
        }
    });
    $("#nfemodelo").keypress(function (e) {
        if (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) {
            return false;
        }
    });
    $("#nfeserie").keypress(function (e) {
        if (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) {
            return false;
        }
    });
    $("#nfcenumerohomologacao").keypress(function (e) {
        if (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) {
            return false;
        }
    });
    $("#nfcenumero").keypress(function (e) {
        if (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) {
            return false;
        }
    });
    $("#nfcemodelo").keypress(function (e) {
        if (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) {
            return false;
        }
    });
    $("#nfceserie").keypress(function (e) {
        if (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) {
            return false;
        }
    });
    $("#contcep").mask('99999-999', {placeholder: ""});


    // ## EXEMPLO 2
    // Aciona a validação ao sair do input
    $('.cnpj').blur(function () {

        // O CPF ou CNPJ
        var cpf_cnpj = $(this).val();

        // Testa a validação
        if (!valida_cpf_cnpj(cpf_cnpj)) {
            if (cpf_cnpj !== '') {
                alert('CNPJ inválido!');
                $(this).focus();
                $(this).val('');
            }
        }

    });
    $('.contcnpj').blur(function () {

        // O CPF ou CNPJ
        var cpf_cnpj = $(this).val();

        // Testa a validação
        if (!valida_cpf_cnpj(cpf_cnpj)) {
            if (cpf_cnpj !== '') {
                alert('CNPJ inválido!');
                $(this).focus();
                $(this).val('');
            }
        }

    });
    $('.cpf').blur(function () {

        // O CPF ou CNPJ
        var cpf_cnpj = $(this).val();

        // Testa a validação
        if (!valida_cpf_cnpj(cpf_cnpj)) {
            if (cpf_cnpj !== '') {
                alert('CPF inválido!');
                $(this).focus();
                $(this).val('');
            }
        }

    });

    $("#tornarMatriz").on('click', function () {
        var grupo_id = $("#grupo_id").val();
        if (isEmpty(grupo_id) || isNaN(parseInt(grupo_id))) {
            bootbox.alert("Informe o grupo");
            return;
        } else {
            ajaxGenerator(root + "/empresa/getMatrizGrupo/" + grupo_id, "GET", function (data) {
                if (!isNaN(parseInt(data))) {
                    if (parseInt(data) > 0) {
                        bootbox.confirm({
                            title: "Atenção!",
                            className: "warning",
                            message: "Este grupo já possui matriz, deseja remover a matriz antiga e continuar?",
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
                                    $("#matriz").val(1);
                                    $("#tornarMatriz").text("Já é matriz").prop('disabled', true);
                                }
                            }
                        });
                    } else {
                        $("#matriz").val(1);
                        $("#tornarMatriz").text("Já é matriz").prop('disabled', true);
                    }
                } else {
                    bootbox.alert("Erro ao buscar matriz do grupo");
                }
            });
        }
    });
    $("#grupo_id").on('change', function () {
        var matriz = $("#matriz").val();
        if (!isEmpty(matriz)) {
            bootbox.alert("Esta empresa é matriz do grupo anterior, para ela continuar sendo matriz, clique novamente em \"Tornar esta empresa matriz do grupo\"");
            $("#tornarMatriz").prop('disabled', false).text("Tornar esta empresa matriz do grupo");
            $("#matriz").val("");
            return;
        }
    });

    $(".slim-div").slimScroll({
        height: '390px',
    });
});

$("#contingenciaemissao").change(function () {
    ativarDesativarInputsContingencia();
});

$("#btnResponder").click( function () {
    if ( $("#contnome").prop('disabled') ) {
        $("#registro_modal input[type=radio]").prop('disabled', true);
    }
    if (! $("#spedregistro1010").isEmpty() )
        remarcarRegistros();
    else
        $("#registro_modal").modal('show');
});

$("#nfcetipoambiente").change( function () {
    verificarAmbiente();
});

$("#registro_modal").on( 'hidden.bs.modal', function () {
    $("#registro_modal input[type=radio]").removeAttr('checked');
});

$("#btnConfirmar").click( function () {
    registro1010();
});

$("#btnGravar").click( function ( e ) {
    validarCampos( e );
});

$("#fmCadastro").on( 'submit', function () {
    ativarDesativarInputsEmiteSped( false );
});

$("#nfeemite").on('change', function () {
    ativarDesativarInputsEmiteNFe(function () {
        blockAmbiente("nfetipoambiente", "nfenumerohomologacao", "nfenumero");
    });
});

$("#nfceemite").on('change', function () {
    ativarDesativarInputsEmiteNFCe(function () {
        blockAmbiente("nfcetipoambiente", "nfcenumerohomologacao", "nfcenumero");
    });
});

$("#spedemite").change(function () {
    ativarDesativarInputsEmiteSped( ! $(this).prop('checked') );
});

$("#nfetipoambiente").change(function () {
    blockAmbiente("nfetipoambiente", "nfenumerohomologacao", "nfenumero");
});

$("#nfcetipoambiente").change(function () {
    blockAmbiente("nfcetipoambiente", "nfcenumerohomologacao", "nfcenumero");
});

$("#nfeemitemodelos").change(function () {
    setTimeout(() => {
        var $self = $(this);
        var value = $self.val();
        var disabled = isEmpty(value) || show;
        $("#nfecrt").attr('disabled', disabled).trigger('chosen:updated');
        $("#nfecreditosimplesnacional").prop('disabled', disabled);
        if (value === '3') {
            $("#nfceemite, #nfeemite").val(1).trigger('change');
        } else if (value === '1') {
            $("#nfeemite").val(1).trigger('change');
            $("#nfceemite").val(0).trigger('change');
        } else if (value === '2') {
            $("#nfeemite").val(0).trigger('change');
            $("#nfceemite").val(1).trigger('change');
        } else {
            $("#nfceemite, #nfeemite").val(0).trigger('change');
        }
    }, 100);
});

function ativarDesativarInputsEmiteNFe(callback) {
    setTimeout(() => {
        if ($("#nfeemite").val() !== "1" || show) {
            $("#nfenumerohomologacao").prop('disabled', true);
            $("#nfenumero").prop('disabled', true);
            $("#nfemodelo").attr('disabled', true).trigger('chosen:updated');
            $("#nfeserie").prop('disabled', true);
            $("#nfetipoambiente").attr('disabled', true).trigger('chosen:updated');
            $("#nfetipoemissao").attr('disabled', true).trigger('chosen:updated');
        } else {
            $("#nfenumerohomologacao").prop('disabled', false);
            $("#nfenumero").prop('disabled', false);
            $("#nfemodelo").attr('disabled', false).trigger('chosen:updated');
            $("#nfeserie").prop('disabled', false);
            $("#nfetipoambiente").attr('disabled', false).trigger('chosen:updated');
            $("#nfetipoemissao").attr('disabled', false).trigger('chosen:updated');
            if (typeof callback !== "undefined")
                callback();
        }
    });
}

function ativarDesativarInputsContingencia() {
    setTimeout(() => {
        if ($("#contingenciaemissao").prop('checked') !== true || show) {
            $("#contingenciadatahora").prop('disabled', true);
            $("#contingenciadatahora").val('');
            $("#contingenciajustificativa").prop('disabled', true);
        } else {
            $("#contingenciadatahora").prop('disabled', false);
            $("#contingenciajustificativa").prop('disabled', false);
        }
    });
}

function ativarDesativarInputsEmiteNFCe(callback) {
    setTimeout(() => {
        if ($("#nfceemite").val() !== "1" || show) {
            $("#nfcenumerohomologacao").prop('disabled', true);
            $("#nfcenumero").prop('disabled', true);
            $("#nfcevalorlimite").prop('disabled', true);
            $("#nfcemodelo").attr('disabled', true).trigger('chosen:updated');
            $("#nfceserie").prop('disabled', true);
            $("#nfcetipoambiente").attr('disabled', true).trigger('chosen:updated');
            $("#nfcetipoemissao").attr('disabled', true).trigger('chosen:updated');
            $("#nfcetokenid").attr('disabled', true).trigger('chosen:updated');
            $("#nfcetoken").attr('disabled', true).trigger('chosen:updated');
        } else {
            $("#nfcenumerohomologacao").prop('disabled', false);
            $("#nfcenumero").prop('disabled', false);
            $("#nfcevalorlimite").prop('disabled', false);
            $("#nfcemodelo").attr('disabled', false).trigger('chosen:updated');
            $("#nfceserie").prop('disabled', false);
            $("#nfcetipoambiente").attr('disabled', false).trigger('chosen:updated');
            $("#nfcetipoemissao").attr('disabled', false).trigger('chosen:updated');
            $("#nfcetokenid").attr('disabled', false).trigger('chosen:updated');
            $("#nfcetoken").attr('disabled', false).trigger('chosen:updated');
            if (typeof callback !== "undefined")
                callback();
        }
    });
}

function ativarDesativarInputsEmiteSped( block ) {
    $("#spedincidenciatributaria").attr('disabled', block).trigger('chosen:updated');
    $("#spedperfil").attr('disabled', block).trigger('chosen:updated');
    $("#spedapropriacaocredito").attr('disabled', block).trigger('chosen:updated');
    $("#spedatividade").attr('disabled', block).trigger('chosen:updated');
    $("#spedtipocontribuicao").attr('disabled', block).trigger('chosen:updated');
    $("#spedregimecumulativo").attr('disabled', block).trigger('chosen:updated');
    $("#contnome").prop('disabled', block);
    $("#contcpf").prop('disabled', block);
    $("#contcnpj").prop('disabled', block);
    $("#contcrc").prop('disabled', block);
    $("#conttelefone").prop('disabled', block);
    $("#contfax").prop('disabled', block);
    $("#contemail").prop('disabled', block);
    $("#contcep").prop('disabled', block);
    $("#btnContBuscarEndereco").prop('disabled', block);
    $("#contuf").attr('disabled', block).trigger('chosen:updated');
    $("#contcidade_id").attr('disabled', block).trigger('chosen:updated');
    $("#btnContNovoCadCidade").prop('disabled', block);
    $("#contbairro_id").attr('disabled', block).trigger('chosen:updated');
    $("#btnContNovoCadBairro").prop('disabled', block);
    $("#contrua_id").attr('disabled', block).trigger('chosen:updated');
    $("#btnContNovoCadEndereco").prop('disabled', block);
    $("#btnBuscarcontcep").prop('disabled', block);
    $("#contnumero").prop('disabled', block);
    $("#ponto_referencia").prop('disabled', block);
    $("#contcomplemento").prop('disabled', block);
}

function blockAmbiente(ambiente, homologacao, producao) {
    var value = $(`#${ambiente}`).val();
    if (value == '1') {
        $(`#${homologacao}`).prop('disabled', true);
        $(`#${producao}`).prop('disabled', false);
    } else {
        $(`#${homologacao}`).prop('disabled', false);
        $(`#${producao}`).prop('disabled', true);
    }
}

$('.modal-wide').on('show.bs.modal', function () {
    var height = $(window).height() - 200;
    $(this).find('.modal-body').css('max-height', height);
});

function showHideCrt() {
    if ($("#nfecrt").val() == 3)
        $(".nfecrtcred").hide();
    else
        $(".nfecrtcred").show();
}

$(document).on('change', '#certificadodigital', function () {
    let input = $(this);
    let numFiles = input.get(0).files ? input.get(0).files.length : 1;
    let label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
    $.each(['.pfx'], function (i, el) {
        if (label.substring(label.length - el.length, label.length) != el) {
            bootbox.alert("Tipo de arquivo inválido");
            input.val('');
            input.parents('label').children('#upload-filename').text("Formato de arquivo inválido.");
            return;
        } else {
            input.parents('label').children('#upload-filename').text(label);
        }
    });
});

function setIsMatrizButton() {
    if ($("#matriz").val() == 1)
        $("#tornarMatriz").text("Já é matriz").prop('disabled', true);
    else
        $("#tornarMatriz").prop('disabled', false).text("Tornar esta empresa matriz do grupo");
}

function treatErrors() {
    setIsMatrizButton();
    carregarEnderecoErro();
}

function treatInputsShow() {
    desativarInputs();
    var ids = [".btnBuscarEndereco", '#btnBuscarCEP',
        '.novoCadEndereco', '.btnContBuscarEndereco', '#btnBuscarcontcep', '#btnCrop',
        '#btnZoomIn', '#btnZoomOut', '#btnCarregarImagem', '#btnRemoverImagem', '#tornarMatriz'
    ];
    desativarInputsEspecificos(ids);
    $('#btnContBuscarEndereco').attr('disabled', 'disabled');
}

function treatInputsNotShow() {
    ativarDesativarInputsContingencia();
    ativarDesativarInputsEmiteNFe(function () {
        blockAmbiente("nfetipoambiente", "nfenumerohomologacao", "nfenumero");
    });
    ativarDesativarInputsEmiteNFCe(function () {
        blockAmbiente("nfcetipoambiente", "nfcenumerohomologacao", "nfcenumero");
    });
    ativarDesativarInputsEmiteSped( !$("#spedemite").prop('checked') );
}

function validarCampos( e ) {
    var file = $("#certificadodigital");
    var pass = $("#nfesenhapfx");

    if ( !file.isEmpty() && pass.isEmpty() ) {
        e.preventDefault();
        bootbox.alert('Por favor, informe uma senha para o certificado digital.');
        return false;
    }
    return true;
}

function registro1010() {
    var $reg1010 = $("#spedregistro1010");
    var $reg1100 = $("input[name=ind_exp]:checked");
    var $reg1200 = $("input[name=ind_ccrf]:checked");
    var $reg1300 = $("input[name=ind_comb]:checked");
    var $reg1390 = $("input[name=ind_usina]:checked");
    var $reg1400 = $("input[name=ind_va]:checked");
    var $reg1500 = $("input[name=ind_ee]:checked");
    var $reg1600 = $("input[name=ind_cart]:checked");
    var $reg1700 = $("input[name=ind_form]:checked");
    var $reg1800 = $("input[name=ind_aer]:checked");

    var value = validateRegs( true, $reg1100, $reg1200, $reg1300, $reg1390,
        $reg1400, $reg1500, $reg1600, $reg1700, $reg1800
    );

    if ( typeof value === "boolean" ) {
        bootbox.alert('Por favor, informe todos os campos!');
        return false;
    }

    $reg1010.val( value );
    $("#registro_modal").modal('hide');
    $("#registro_modal input[type=radio]").removeAttr('checked');
    return true;
}

function validateRegs( selector, ...regs ) {
    for ( let i = 0; i < regs.length; i++ ) {
        if ( selector ) {
            if ( typeof regs[i].val() === "undefined" )
                return false;

            if ( regs[i].isEmpty() )
                return false;
        } else {
            if ( typeof $("#" + regs[i]).val() === "undefined" )
                return false;

            if ( $("#" + regs[i]).isEmpty() )
                return false;
        }
    }
    var value = "";
    for (let i = 0; i < regs.length; i++) {
        value += regs[i].val();
    }
    return value;
}

function remarcarRegistros() {
    var reg1010 = $("#spedregistro1010").val();
    var spl = reg1010.split('');

    $("input[name=ind_exp][value="+ spl[0] +"]").prop('checked', true);
    $("input[name=ind_ccrf][value="+ spl[1] +"]").prop('checked', true);
    $("input[name=ind_comb][value="+ spl[2] +"]").prop('checked', true);
    $("input[name=ind_usina][value="+ spl[3] +"]").prop('checked', true);
    $("input[name=ind_va][value="+ spl[4] +"]").prop('checked', true);
    $("input[name=ind_ee][value="+ spl[5] +"]").prop('checked', true);
    $("input[name=ind_cart][value="+ spl[6] +"]").prop('checked', true);
    $("input[name=ind_form][value="+ spl[7] +"]").prop('checked', true);
    $("input[name=ind_aer][value="+ spl[8] +"]").prop('checked', true);

    $("#registro_modal").modal('show');
    return true;
}

function verificarAmbiente() {
    var amb = $("#nfcetipoambiente").val();
    if ( amb == "1") {
        $("#nfcetokenid").addClass('hidden');
        $("#nfcetokenid_prod").removeClass('hidden');
        $("#nfcetoken").addClass('hidden');
        $("#nfcetoken_prod").removeClass('hidden');
    } else {
        $("#nfcetokenid").removeClass('hidden');
        $("#nfcetokenid_prod").addClass('hidden');
        $("#nfcetoken").removeClass('hidden');
        $("#nfcetoken_prod").addClass('hidden');
    }
}