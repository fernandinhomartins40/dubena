try {
    var breakFocusPrevInput = false;
    var loader = false;
    var inputWithFocus = null;
    var formSubmetido = false;
    var originalShortcuts;
    var ajaxLoaderDialog = undefined;
    $(window).load(function () {
        
        $('.chosen-results').css('cssText', 'max-height: 150px !important');
        
        var tableAjust = function () {
            $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
        };
        $("input.input-sm, textarea.input-sm").each(function () {
            if ($(this).prop('readonly'))
                $(this).attr("tabindex", "-1");
        });
        $(".modal").on('hidden.bs.modal', function (e) {
            tableAjust();
        });
        $(".modal").on('shown.bs.modal', function (e) {
            tableAjust();
        });
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            tableAjust();
        });
        $(document).on("hidden.bs.modal", ".bootbox.modal", function () {
            tableAjust();
        });
        $(document).on("shown.bs.modal", ".bootbox.modal", function () {
            tableAjust();
        });
        setTimeout(function () {
            $(document).on("hide.bs.modal", ".bootbox.modal, .modal", function () {
                reInsertShortcuts();
            });
            $(document).on("shown.bs.modal", ".bootbox.modal, .modal", function () {
                removeShortcuts();
            });
        }, 5000);
    });
    $(document).ready(function () {
        //limpa os options do plugin selectize após pressionar o backspace
        $('.selectize-input input').keyup(function (e) {
            if (e.keyCode == 8) {
                var select = $(this).parent().parent().parent().children('select:eq(0)').selectize()[0].selectize;
                select.clearOptions();
                select.refreshOptions(true);
                select.refreshItems();
            }
        });
        //Este cara
        $('.btnNovoCadastro').on('click', function () {
            $('#myModalLabelCadastro').text('Novo Registro');
            $('#fmCadastroAjax')[0].reset();
            $('#fmCadastroAjax :input').prop('disabled', false);
            $('#fmCadastroAjax :submit').show();
            $(".selectChosen").trigger('chosen:updated');
        });
        $(document).on("submit", 'form:not(.js-allow-double-submission)', function () {
            $(this).preventDoubleSubmission();
        });
        //Esse cara
        $("form").submit(function () {
            formSubmetido = true;
            var empresa_id = $("#empresa_documento").val();
            var filtro = $("#filtro_url").val();
            $(this).append("<input type='hidden' value='" + empresa_id + "' name='empresa_documento' />");
            if ( filtro ) {
                $( this ).append("<input type='hidden' value='" + filtro + "' name='filtro_url' />");
            }
        });
        $("#btnImprimirIframe").on('click', function () {
            window.frames["iFrameReport"].focus();
            window.frames["iFrameReport"].print();
        });
        $(".selectize-input").on('focusout', function () {
            var select = $(this).parent('div').parent('div').children('select');
            if (isEmpty(select.val())) {
                var selectize = select.selectize()[0].selectize;
                selectize.clearOptions();
                selectize.refreshOptions(true);
                selectize.refreshItems();
            }
        });
        $(".dontHideEsc").modal({
            show: false,
            keyboard: false,
            backdrop: "static"
        });
        $(document).on('focusin', '.chosen-container, input', function () {
            var input;
            if ($(this).hasClass("chosen-container"))
                input = $(this).attr('id').replace('_chosen', '');
            else {
                if ($(this).attr('id') !== 'undefined') {
                    input = $(this).attr('id');
                } else {
                    input = $(this).attr('name');
                }
            }
            if (typeof input !== "undefined")
                inputWithFocus = input;
        });
        $("input").on('keydown', function () {
            if ($(this).prop('readonly')) {
                return false;
            }
        });
        $(".btnNovoCadastro").on('click', function () {
            setTimeout(function () {
                $("#ativo").prop('checked', true);
            }, 500);
        });
        shortcut.add("F3", function () {
            if (typeof $("#fmCadastroAjax").val() !== 'undefined') {
                if (!formSubmetido && $('.modal').is(':visible')) {
                    $("input[type=submit][value=Gravar]").click();
                }
            } else if (!formSubmetido) {
                $("input[type=submit][value=Gravar]").click();
            }
        });
        $(".btnCloseCidade").on('click', function () {
            $("#popup_cidade").modal('hide');
        });
        $(".btnCloseBairro").on('click', function () {
            $("#popup_bairro").modal('hide');
        });
        $(".btnCloseRua").on('click', function () {
            $("#popup_rua").modal('hide');
        });
        $(".btnCloseCEP").on('click', function () {
            $("#popup_cep").modal('hide');
        });
        $("#popup_cidade, #popup_bairro, #popup_rua, #popup_cep").on("show.bs.modal", function () {
            shortcut.remove("escape");
            var that = this;
            shortcut.add("escape", function () {
                $(that).modal('hide');
            });
            var id = $(this).attr("id");
            setTimeout(function () {
                switch (id) {
                    case "popup_cidade":
                        $("#descricao_cidade").focus();
                        break;
                    case "popup_bairro":
                        $("#descricao_bairro").focus();
                        break;
                    case "popup_rua":
                        $("#descricao_rua").focus();
                        break;
                }
            }, 1000);
        });
        $("a[href='#']").on('focusout', function () {
            $(this).parent('span').attr('style', 'background-color: white');
        });
        $("a[href='#']").on('focusin', function () {
            $(this).parent('span').attr('style', 'background-color: lightblue');
        });
        shortcut.add('shift+alt+left', function () {
            shortcutTab('left');
        });
        shortcut.add('shift+alt+right', function () {
            shortcutTab();
        });
        function shortcutTab(type = 'right') {
            var ativou = false;
            $(".nav-tabs-custom > .nav-tabs").children('li').each(function () {
                if (ativou)
                    return true;
                if ($(this).hasClass('active')) {
                    var tab = $(this).children('a').attr('href');
                    if (typeof tab == 'undefined')
                        tab = "#tab_1";
                    tab = tab.split("_");
                    if (type === 'right') {
                        tab = parseInt(tab[1]) + 1;
                        tab = incrementsTable(tab);
                    } else {
                        tab = parseInt(tab[1]) - 1;
                        if (tab == 0 || !tabIsVisible(i)) {
                            var ok = false;
                            var count = tab == 0 ? $(".nav-tabs").find('a[data-toggle="tab"]').length : tab;
                            for (var i = count; i >= 0; i--) {
                                if (!tabIsUndefined(i) && tabIsVisible(i) && !ok) {
                                    ok = true;
                                    tab = i;
                                }
                            }
                        }
                    }

                    if (tabIsUndefined(tab) && type === 'right') {
                        tab = 1;
                    }
                    if (!isNaN(tab))
                        $('.nav-tabs a[href="#tab_' + tab + '"]').tab('show');
                    ativou = true;
                }
            });
        }

        function tabIsEmpty(num_tab) {
            return isEmpty($("#tab_" + num_tab).text());
        }
        function tabIsVisible(num_tab) {
            return $('.nav-tabs a[href="#tab_' + num_tab + '"]').is(":visible");
        }
        function tabIsUndefined(num_tab) {
            return $('.nav-tabs a[href="#tab_' + num_tab + '"]').length === 0;
        }
        function incrementsTable(num_tab) {
            if (tabIsUndefined(num_tab)) {
                return 1;
            } else if (!tabIsVisible(num_tab)) {
                num_tab++;
                return incrementsTable(num_tab);
            } else {
                return num_tab;
            }
        }
        shortcut.add('shift+alt+down', function () {
            var ativou = false;
            $(".subtabs").children('li').each(function () {
                if (ativou)
                    return true;
                if ($(this).hasClass('active')) {
                    var tab = $(this).children('a').attr('href');
                    if (typeof tab == 'undefined')
                        tab = "#subtab_1";
                    tab = tab.split("_");
                    tab = parseInt(tab[1]) - 1;
                    if (tab === 0) {
                        var i = 0;
                        while (i === 0) {
                            if (!isEmpty($("#subtab_" + tab).text())) {
                                tab++;
                                if (isEmpty($("#subtab_" + tab).text())) {
                                    tab--;
                                    i++;
                                }
                            } else {
                                tab++;
                            }
                        }
                    }
                    if (!isNaN(tab) && $("#subtab_" + tab).hasClass('hidden') == false) {
                        $('.nav-tabs a[href="#subtab_' + tab + '"]').tab('show');
                        ativou = true;
                    }
                }
            });
        });
        shortcut.add('shift+alt+up', function () {
            var ativou = false;
            $(".subtabs").children('li').each(function () {
                if (ativou)
                    return true;
                if ($(this).hasClass('active')) {
                    var tab = $(this).children('a').attr('href');
                    if (typeof tab == 'undefined')
                        tab = "#subtab_1";
                    tab = tab.split("_");
                    tab = parseInt(tab[1]) + 1;
                    if (isEmpty($("#subtab_" + tab).text())) {
                        tab = 1;
                    }
                    if (!isNaN(tab) && $("#subtab_" + tab).hasClass('hidden') == false) {
                        $('.nav-tabs a[href="#subtab_' + tab + '"]').tab('show');
                        ativou = true;
                    }
                }
            });
        });
        $(document).on("hidden.bs.modal", ".bootbox.modal", function (e) {
            inputFocus();
            if (formSubmetido)
                formSubmetido = false;
        });
        $(".modal").on('hide.bs.modal', function () {
            inputFocus();
            if (typeof telaPedidos === 'undefined')
                $("#id").val('');
            $('#tblCadastro').attr('btnClick', 'false');
        });
        $("#modalSenha").on('hide.bs.modal', function () {
            $("#fmVerificaSenha").each(function () {
                this.reset();
            });
        });
        $("#modalSenha").on('show.bs.modal', function () {
            setTimeout(function () {
                $("#pass").focus();
            }, 800);
        });
        $("#popup_relatorio").on('hidden.bs.modal', function () {
            $("#iFrameReport").contents().find("body").html('');
        });
        //este trexo de código impede o navegador de colocar um scroll no body da página quando há mais de uma modal aberta
        var $body = $('body'),
                curPos = 0,
                isOpened = false,
                isOpenedTwice = false;
        $body.off('show.bs.modal hidde.bs.modal', '.modal');
        $body.on('show.bs.modal', '.modal', function () {
            if (isOpened) {
                isOpenedTwice = true;
            } else {
                isOpened = true;
                curPos = $(window).scrollTop();
                $body.css('overflow', 'hidden');
            }
        });
        $body.on('hide.bs.modal', '.modal', function () {
            if (!isOpenedTwice) {
                $(window).scrollTop(curPos);
                $body.css('overflow', 'visible');
                isOpened = false;
            }
            isOpenedTwice = false;
        });
        // //resolve um bug que desconfigura o body da pagina ao abrir uma modal e dentro de outra
        // var sm = $('<div />').css({width: '100px', height: '100px', overflow: 'scroll', position: 'absolute', top: '-9999px'});
        // $('body').append(sm);
        // var sbm = sm.get()[0],
        // scrollbarWidth = sbm.offsetWidth - sbm.clientWidth;
        // sm.remove();
        // $('.modal').on('show.bs.modal', function () {
        //     if ($(document).height() > $(window).height()) {
        //         $('body').attr('style', 'margin-right: ' + scrollbarWidth + 'px;');
        //     } else {
        //         $('body').attr('style', 'margin-right: 0;');
        //     }
        // });
        // $('.modal').on('hidden.bs.modal', function () {
        //     $('body').attr('style', 'margin-right: 0;');
        // });
        $(".floatNumber").maskMoney({decimal: ',', symbolStay: true, allowNegative: false, precision: 2, precisionBefore: 4});
        $(".floatNumber").attr('maxlength', 6);
        $(".percentagem").maskMoney({suffix: ' %', decimal: ',', symbolStay: true, allowNegative: false, precision: 2, precisionBefore: 3, affixesStay: true});
        $(".percentagem").attr('maxlength', 8);
        $(".percentagemAlowZero").maskMoney({suffix: ' %', decimal: ',', symbolStay: true, allowNegative: false, allowZero: true, precision: 2, precisionBefore: 3, affixesStay: true});
        $(".percentagemAlowZero").attr('maxlength', 8);
        $(".percentagemQuatroDig").maskMoney({suffix: ' %', decimal: ',', symbolStay: true, allowNegative: false, precision: 3, precisionBefore: 3, affixesStay: true});
        $(".percentagemQuatroDig").attr('maxlength', 9);
        $(".percentagemSomenteNum").attr('maxlength', 6);
        $(".percentagemSomenteNum").maskMoney({decimal: ',', symbolStay: true, allowNegative: false, precision: 2, precisionBefore: 3, affixesStay: false});
        $(".percentagemSomenteNumAllowZero").attr('maxlength', 6);
        $(".percentagemSomenteNumAllowZero").maskMoney({decimal: ',', symbolStay: true, allowZero: true, allowNegative: false, precision: 2, precisionBefore: 3, affixesStay: false});
        $(".quantidadeLitros").attr('maxlength', 7);
        $(".quantidadeLitros").maskMoney({decimal: ',', symbolStay: true, allowNegative: false, precision: 3, precisionBefore: 3, affixesStay: false});
        $('.dataTable').dataTable({
            "language": {"url": urlDataTable},
            "processing": true,
            "bPaginate": true,
            "bLengthChange": false,
            "bFilter": true,
            "bSort": true,
            "bInfo": false,
            "bAutoWidth": false,
            "pageLength": 30
        });
        $('.dataTableSemFilter').dataTable({
            "language": {"url": urlDataTable},
            "processing": true,
            "bPaginate": true,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": true,
            "bInfo": false,
            "bAutoWidth": false,
            "pageLength": 30
        });
        $('.dataTableSemPaginate').dataTable({
            "language": {"url": urlDataTable},
            "processing": true,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": true,
            "bSort": true,
            "bInfo": false,
            "bAutoWidth": false,
            "pageLength": 30
        });
        $('.dataTableNoSort').dataTable({
            "language": {"url": urlDataTable},
            "processing": true,
            "bPaginate": true,
            "bLengthChange": false,
            "bFilter": true,
            "bSort": false,
            "bInfo": false,
            "bAutoWidth": false,
            "pageLength": 30
        });
        //store/update ajax
        $("form#fmCadastroAjax").submit(function () {
            var that = this;
            if (typeof planoconta != 'undefined') {
                if (pagarreceber != $("#pagarreceber").val() && nivel == 1 && !validaNivel) {
                    bootbox.confirm({
                        message: 'Este Plano de Contas pode possuir filhos vinculados e você está editando o tipo. As alterações do campo tipo serão aplicadas também aos dependentes. Deseja continuar?',
                        buttons: {
                            confirm: {
                                label: 'Sim',
                                className: 'btn-nw-registro'
                            },
                            cancel: {
                                label: 'Não',
                                className: 'btn-nw-geral'
                            }
                        },
                        callback: function (res) {
                            if (res == true) {
                                validaNivel = true;
                                $(that).submit();
                            }
                        }
                    });
                    return false;
                }
            }
            var id = $("#id").val();
            if (id !== '' && typeof id !== 'undefined') {
                var url = $("#rotaUpdate").text() + id;
                $('#metodo').val('PATCH');
            } else {
                var url = $("#rotaStore").text();
                $('#metodo').val('POST');
            }
            var formData = new FormData($(this)[0]);
            $("input").prop('disabled', true);
            $.ajax({
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                url: url,
                data: formData,
                async: false,
                success: function (data) {
                    if (data.substr(0, 3) == 'OK|') {
                        var url = $("#rotaIndex").text();
                        showDialogRedirect(url);
                    } else {
                        $("input").prop('disabled', false);
                        bootbox.alert('Houve um problema ao gravar: ' + data);
                    }
                },
                error: function (data) {
                    if (typeof (data) == 'object') {
                        $("input").prop('disabled', false);
                        var msg = '';
                        var responseText = '';
                        for (var key in data) {
                            if (key == 'responseJSON') {
                                for (var key1 in data['responseJSON']) {
                                    msg += '<br />' + data['responseJSON'][key1];
                                }
                            }
                            if (key == 'responseText') {
                                responseText = data['responseText'];
                            }
                        }
                        if (msg != '')
                            bootbox.alert('Erro ao gravar: <br />' + msg);
                        else
                            bootbox.alert('Erro ao gravar: ' + responseText);
                        //bootbox.alert('Erro ao gravar: ' + data.responseJSON.descricao);
                    } else if (typeof (data) == 'string') {
                        bootbox.alert('Erro ao gravar: ' + data);
                    } else {
                        bootbox.alert('Houve um erro desconhecido ao gravar!');
                    }
                },
                cache: false,
                contentType: false,
                processData: false
            });
            return false;
        });
        $("form#fmVerificaSenha").submit(function () {
            var url = $("#rotaSenha").text();
            $('#metodo').val('POST');
            var method = 'POST';
            var formData = new FormData($(this)[0]);
            if (typeof pedidos === 'undefined' && typeof callbackSenha === 'undefined') {
                $("input").prop('disabled', true);
            }
            $.ajax({
                type: method,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                url: url,
                data: formData,
                async: false,
                success: function (data) {
                    if (data.substr(0, 3) == 'OK|') {
                        var modal = $("#myModalDel").attr('modal');
                        if (typeof callbackSenha !== 'undefined') {
                            callbackSenha();
                        } else if (typeof pedidos !== 'undefined' && pedidos) {
                            gravarPedido();
                        } else {
                            if (modal === 'true') {
                                $("#myModalDel").modal('show');
                            } else {
                                var url = $("#urlRedirect").attr('url');
                                window.location.href = url;
                            }
                            $("input").prop('disabled', false);
                        }
                        $("#modalSenha").modal('hide');
                    } else if (data.substr(0, 3) == 'OPS') {
                        $("input").prop('disabled', false);
                        bootbox.dialog({
                            title: 'Ocorreu um erro ao verificar a senha!',
                            message: '<p>Sua senha ainda não foi cadastrada.</p>'
                        });
                    } else {
                        $("input").prop('disabled', false);
                        bootbox.alert('Houve um problema ao verificar a senha: ' + data);
                    }
                },
                error: function (data) {
                    if (typeof (data) == 'object') {
                        $("input").prop('disabled', false);
                        var msg = '';
                        var responseText = '';
                        for (var key in data) {
                            if (key == 'responseJSON') {
                                for (var key1 in data['responseJSON']) {
                                    msg += '<br />' + data['responseJSON'][key1];
                                }
                            }
                            if (key == 'responseText') {
                                responseText = data['responseText'];
                            }
                        }
                        if (msg != '')
                            bootbox.alert('Erro ao verificar a senha: <br />' + msg);
                        else
                            bootbox.alert('Erro ao verificar a senha: ' + responseText);
                        //bootbox.alert('Erro ao gravar: ' + data.responseJSON.descricao);
                    } else if (typeof (data) == 'string') {
                        bootbox.alert('Erro ao verificar a senha: ' + data);
                    } else {
                        bootbox.alert('Houve um erro desconhecido ao verificar a senha!');
                    }
                },
                cache: false,
                contentType: false,
                processData: false
            });
            return false;
        });
        $('#navbar-collapse .dropdown-menu').on({
            "click": function (e) {
                e.stopPropagation();
            }
        });
        $("form#fmCadastroDel").submit(function () {
            if (typeof submitDel !== 'undefined' && submitDel)
                return false;
            submitDel = true;
            var formData = new FormData($(this)[0]);
            var id = $('#id_del').val();
            var url = $('#rotaDel').text() + id;
            var token = $('#_token').val();
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: "delete",
                url: url,
                data: {
                    "_token": token,
                    "id": id
                },
                success: function (data) {
                    if (data == 'OK|') {
                        var url = $("#rotaIndex").text();
                        var dialog = bootbox.dialog({
                            title: 'Operação realizada com sucesso!',
                            message: '<p><i class="fa fa-spin fa-spinner"></i> Aguarde, você será redirecionado..</p>'
                        });
                        dialog.init(function () {
                            window.setTimeout("location.href='" + url + "'", 1500);
                        });
                    } else {
                        submitDel = false;
                        bootbox.alert('Erro:' + data);
                    }
                },
                complete: function (data) {
                    if (data.status !== 200) {
                        var msg = "";
                        if (typeof (data) == 'object') {
                            var msg = "<br />";
                            for (var key in data) {
                                if (key == 'responseJSON') {
                                    for (var key1 in data['responseJSON']) {
                                        msg += "<br />" + data['responseJSON'][key1];
                                    }
                                }
                                if (key == 'responseText')
                                    msg = data['responseText'];
                            }
                            if (msg != '')
                                msg = 'Erro ao remover: ' + msg;
                        } else if (typeof (data) == 'string') {
                            msg = 'Erro ao remover: ' + data;
                        } else {
                            msg = 'Erro desconhecido ao remover!';
                        }
                        alertDelete(msg);
                    }
                },
                cache: false,
                contentType: false,
                processData: false
            });
            return false;
        });
        $(".placa").keyup(function () {
            var ucase = $(this).val().toUpperCase();
            $(this).val(ucase);
        }).mask("AAAAAAA", {
            placeholder: "",
            onKeyPress: function (placa, e, field, options) {
                let mask = ["AAA-9999", "AAA9A99"];
                let fifth = placa.substr(placa.length - 3, 1);
                let isLetter = (/[a-zA-Z]/).test(fifth);

                if (placa.length > 6){
                    mask = isLetter ? mask[1] : mask[0];
                }
                else
                    mask = mask[1];

                $(".placa").mask(mask, options);
            }
        });
        $(".placa").trigger('keyup');
        $(".ctps").mask("999999-9", {placeholder: ""});
        $(".cnpj").mask("99.999.999/9999-99", {placeholder: ""});
        $(".cep").mask("99999-999", {placeholder: ""});
        $(".cpf").mask("999.999.999-99", {placeholder: " "});
        $(".rg").mask("99.999.999-9", {placeholder: " "});
        $(".cartaoCredito").mask("9999 9999 9999 9999", {placeholder: " "});
        $(".dataPadrao").mask("99/99/9999", {placeholder: " "});
        $(".telefone").on('focusin', function () {
            $(".telefone").select();
            $(".telefone").mask('(99) 99999-9999');
        });
        $(".telefone").mask("(99) 99999-9999");
        $(".telefone").blur(function (event) {
            var target, phone, element;
            target = (event.currentTarget) ? event.currentTarget : event.srcElement;
            phone = target.value.replace(/\D/g, '');
            element = $(target);
            element.unmask();
            if (phone.length > 10) {
                element.mask("(99) 99999-9999");
            } else {
                element.mask("(99) 9999-9999");
            }
        });
        $(".telefone2").on('focusin', function () {
            $(".telefone2").select();
            $(".telefone2").mask('(99) 99999-9999');
        });
        $(".telefone2").mask("(99) 99999-9999");
        $(".telefone2").blur(function (event) {
            var target, phone, element;
            target = (event.currentTarget) ? event.currentTarget : event.srcElement;
            phone = target.value.replace(/\D/g, '');
            element = $(target);
            element.unmask();
            if (phone.length > 10) {
                element.mask("(99) 99999-9999");
            } else {
                element.mask("(99) 9999-9999");
            }
        });
        $('.dinheiro').maskMoney({prefix: 'R$ ', thousands: '.', decimal: ',', allowNegative: true, allowZero: true});
        $('.dinheiroNoZero').maskMoney({prefix: 'R$ ', thousands: '.', decimal: ',', allowNegative: true, allowZero: false});
        $('.dinheiroPrefixNone').maskMoney({thousands: '.', decimal: ',', allowNegative: true, allowZero: true});
        $('.maskMoney').maskMoney({thousands: '.', decimal: ','});
        $('.maskNumber').maskMoney({thousands: '.', precision: 0});
        $('.maskDecimal').maskMoney({thousands: '.', decimal: ',', precision: 4});
        $('.baseCalculo').maskMoney({decimal: ',', precision: 3, allowZero: true, allowNegative: false}).attr('maxlength', 7);
        $('.baseCalculoSuffix').maskMoney({suffix: " %", decimal: ',', precision: 3, allowZero: true, allowNegative: false}).attr('maxlength', 9);
        // $('.mask3Decimal').maskMoney({thousands: '.', decimal: ',', precision: 3, allowZero:false});
        // $('.mask4Decimal').maskMoney({thousands: '.', decimal: ',', precision: 4, allowZero:false});
        $(".mask3Decimal").mask("99999999,999");
        $(".mask4Decimal").mask("99999999,9999");
        // $(".mask3Decimal").blur(function () {
        // 	if(','.indexOf($(this).val()) === -1);
        // 		$(this).val($(this).val() + ',000');
        // });
        // $(".mask4Decimal").blur(function () {
        // 	if(','.indexOf($(this).val()) === -1);
        // 		$(this).val($(this).val() + ',0000');
        // });
        $('.maskPeso').maskMoney({suffix: ' Kg', thousands: '.', decimal: ',', precision: 3});
        $('.maskPesoInteiro').maskMoney({suffix: ' Kg', thousands: '.', decimal: ',', precision: 0, allowZero: true});
        //dateTimePicker com campo de data e SEM HORA
        $('div.generalDatePicker').datetimepicker({
            defaultDate: moment(),
            locale: 'pt-br',
            viewMode: 'days',
            format: 'DD/MM/YYYY'
        });
        $('div.generalDatePickerDefaultDateFalse').datetimepicker({
            locale: 'pt-br',
            viewMode: 'days',
            format: 'DD/MM/YYYY'
        });
        $("input.generalDatePicker, input.generalDatePickerDefaultDateFalse").mask('99/99/9999');
        //dateTimePicker com campo de data e HORA
        $('div.generalDateTimePicker').datetimepicker({
            defaultDate: moment(),
            locale: 'pt-br',
            viewMode: 'days'
        });
        $('div.generalDateTimePickerDefaultDateFalse').datetimepicker({
            locale: 'pt-br',
            viewMode: 'days'
        });
        //dateTimePicker com campo de data, horas, min e seg
        $('div.generalDateTimePickerSeconds').datetimepicker({
            defaultDate: moment(),
            locale: 'pt-br',
            viewMode: 'days',
            format: 'DD/MM/YY HH:mm:ss'
        });
        //dateTimePicker com campo de data, horas, min e seg
        $('div.generalDateAll').datetimepicker({
            defaultDate: moment(),
            locale: 'pt-br',
            viewMode: 'days',
            format: 'DD/MM/YYYY HH:mm:ss'
        });
        $("input.generalDateTimePickerSeconds, input.generalDateTimePicker, input.generalDateTimePickerDefaultDateFalse").mask('99/99/9999 99:99:99');
        //dateTimePicker com campo de data, horas, min e seg
        $('div.generalDateMesAno').datetimepicker({
            locale: 'pt-br',
            viewMode: 'months',
            format: 'MM/YYYY'
        });
        $("input.generalDateMesAno").mask('99/9999');
        //select sem o campo de search, a classe é exclusiva para endereço por conta do tamanho do campo
        $(".selectDisableSearchEndereco").chosen({no_results_text: "nenhum registro encontrado",
            placeholder_text_single: "Selecione",
            width: "80%",
            disable_search: true
        });
        //select completo com search ativado, a classe é exclusiva para endereço por conta do tamanho do campo
        $(".selectChosenEndereco").chosen({no_results_text: "nenhum registro encontrado",
            placeholder_text_single: "Selecione",
            width: "80%",
            disable_search: false
        });
        //select sem o campo de search
        $(".selectDisableSearch").chosen({no_results_text: "nenhum registro encontrado",
            placeholder_text_single: "Selecione",
            width: "100%",
            disable_search: true
        });
        //select completo com search ativado
        $(".selectChosen").chosen({no_results_text: "nenhum registro encontrado",
            placeholder_text_single: "Selecione",
            search_contains: true,
            width: "100%",
            disable_search: false
        });
        $(".number").keyup(function () {
            $(this).val($(this).val().replace(/[^\d]+/g, ''));
        });
        $(".days").keyup(function () {
            if ($(this).val() > 31) {
                $(this).val(31);
            }
        });
        // $('#tblCadastro').on('click', 'tr', function () {
        // 	var trElem = $(this).closest("tr"); // grabs the button's parent tr element
        // 	var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
        // 	var url = $('#tblCadastro').attr("url");
        // 	var btnClick = $('#tblCadastro').attr("btnClick");
        // 	var id = parseInt($(firstTd).text());
        // 	if (btnClick === "false" && url !== "" && !isNaN(id)) {
        // 		url = url.replace(':id', id);
        // 		window.location.href = url;
        // 	}
        // });
        // $('#tblCadastro').on('click', 'button', function () {
        // 	var trElem = $(this).closest("tr"); // grabs the button's parent tr element
        // 	var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
        // 	var urlupdate = $('#tblCadastro').attr("urlupdate");
        // 	$('#tblCadastro').attr("btnClick", true);
        // 	var btnClick = $('#tblCadastro').attr("btnClick");
        // 	var id = parseInt($(firstTd).text());
        // 	if (btnClick === "true" && urlupdate !== "" && typeof urlupdate !== 'undefined' && !isNaN(id)) {
        // 		if ($(this).context.id == 'btnEditar') {
        // 			urlupdate = urlupdate.replace(':id', id);
        // 			window.location.href = urlupdate;
        // 		} else {
        // 			var descricao = $(trElem).children("td")[1];
        // 			$('#id_del').val(id);
        // 			$('#cod_del').val(id);
        // 			$('#descricao_del').val($(descricao).text());
        // 			$('#modalDel').modal('show');
        // 		}
        // 	}
        // });
        $(".modal").on('hide.bs.modal', function () {
            $('#tblCadastro').attr('btnClick', false);
            submitDel = false;
        });
        $(".modal").on('shown.bs.modal', function () {
            $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
        });
        $(document).ajaxComplete(function myErrorHandler(event, xhr, ajaxOptions, thrownError) {
            var response = xhr.responseText;
            if (typeof response !== "undefined") {
                if (response.includes('Empresa na qual a requisição foi feita é diferente da atual')) {
                    bootbox.alert(response.substr(12, response.length));
                }
            }
        });
        $(".btn-xs :not(.no-margin)").css('cssText', 'margin-top: 5px !important;');
    });
    $(".delete").on("submit", function () {
        return confirm("Quer remover o registro atual?");
    });
    function verificaNumero(e) {
        if (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) {
            return false;
        }
    }

    function insertDataOracle(data) {
        if (data.isEmpty()) {
            data = dataAtual();
        }
        var dataHora = data.split(' ');
        data = dataHora[0];
        data = data.split("/");
        data = data[2] + '-' + data[1] + '-' + data[0];
        if (typeof dataHora[1] !== 'undefined')
            data += ' ' + dataHora[1];
        return data;
    }

    function requestDataOracle(data, time = true, seconds = true, fullYear = true) {
        if (data !== null) {
            data = data.split("-");
            if (!time) {
                time = data[2].split(' ');
                data[2] = time[0];
                if (!fullYear) {
                    data[0] = data[0].substr(2, 4);
                }
                data = data[2] + '/' + data[1] + '/' + data[0];
            } else {
                time = data[2].split(' ');
                data[2] = time[0];
                if (!seconds) {
                    var horas = time[1].split(':');
                    time[1] = horas[0] + ':' + horas[1];
                }
                if (!fullYear) {
                    data[0] = data[0].substr(2, 4);
                }
                data = data[2] + '/' + data[1] + '/' + data[0] + ' ' + time[1];
            }
        }

        return data;
    }

    function mudarTipoPessoa(callback) {
        if (typeof $('#tipopessoa_id').val() !== 'undefined' && !isEmpty($('#tipopessoa_id').val()) && $('#tipopessoa_id').val().indexOf('F') !== -1) {
            $(".divTipoPessoa").removeClass('hidden');
            $('.divPessoaFisica').show();
            $('.cnpj').prop('disabled', true);
            $('#inscricao_estadual').prop('disabled', true);
            $('#fantasia').prop('disabled', true);
            $('.rg').prop('disabled', false);
            $('.cpf').prop('disabled', false);
            $('#datanascimento').prop('disabled', false);
            $('#btnAddPromocao').prop('disabled', false);
            $('#sexo').prop('disabled', false).trigger('chosen:updated');
            $('.divPessoaJuridica').hide();
            $(".subtab_1").addClass('hidden');
            $('#subtab_1').removeClass('active');
            $('.subtab_1').removeClass('active');
            $('#subtab_2').addClass('active');
            $('.subtab_2').addClass('active');
            $("label[for='nome']").text("Nome:");
        } else if (typeof $('#tipopessoa_id').val() !== 'undefined' && !isEmpty($('#tipopessoa_id').val()) && $('#tipopessoa_id').val().indexOf('J') !== -1) {
            $(".divTipoPessoa").removeClass('hidden');
            $('.divPessoaFisica').hide();
            $('.cnpj').prop('disabled', false);
            $('#inscricao_estadual').prop('disabled', false);
            $('#fantasia').prop('disabled', false);
            $('.rg').prop('disabled', true);
            $('.cpf').prop('disabled', true);
            $('#datanascimento').prop('disabled', true);
            $('#btnAddPromocao').prop('disabled', true);
            $('#sexo').prop('disabled', true).trigger('chosen:updated');
            $('.divPessoaJuridica').show();
            $('#subtab_1').addClass('active');
            $('#subtab_2').removeClass('active');
            $('.subtab_2').removeClass('active');
            $('.subtab_1').addClass('active');
            $(".subtab_1").removeClass('hidden');
            $("label[for='nome']").text("Razão Social:");
            $("label[for='fantasia']").text("Fantasia:");
        } else {
            $(".divTipoPessoa").addClass('hidden');
        }
        if (typeof callback === "function") {
            callback();
        }
    }


    function desativarInputs() {
        $("#fmCadastro :input").prop('disabled', true);
        $(".selectChosen").prop('disabled', true).trigger('chosen:updated');
        $(".selectDisableSearch").prop('disabled', true).trigger('chosen:updated');
        $(".selectChosenEndereco").prop('disabled', true).trigger('chosen:updated');
        $("#fmCadastro :button").prop('disabled', false);
        $("#fmCadastro :submit").hide();
    }
    function desativarInputsEspecificos(ids) {
        for (var i = 0; i < ids.length; i++)
            $(ids[i]).prop('disabled', true);
    }

    function isEmptyMultiple(array) {
        var empty = false;
        $.each(array, function (i, str) {
            if (!str || 0 === str.length) {
                empty = true;
                return false;
            }
        });
        if (empty)
            return true;
        return false;
    }


    function isEmpty(str) {
        return (!str || 0 === str.length);
    }

    function isBlank(str) {
        return (!str || /^\s*$/.test(str));
    }

    function buscaQuantidadeProduto(url) {
        var produto = $("#produto").val();
        var setor_id = $("#origemsetor_id").val();
        if (typeof setor_id === 'undefined') {
            var setor_id = $("#setor_id").val();
        }
        if (typeof setor_id !== 'undefined' || typeof produto !== 'undefined') {
            url = url.replace(':produto_id', produto);
            url = url.replace(':setor_id', setor_id);
            var quantidade = parseFloat($("#quantidade").val().replace(".", "").replace(",", "."));
            ajaxGenerator(url, 'GET',
                    function (data) {
                        if (typeof data !== 'undefined' && parseFloat(data) < quantidade) {
                            bootbox.confirm({
                                title: 'Atenção!',
                                className: 'warning',
                                message: 'Se você fizer uma movimentação com esse produto o estoque será negativado, deseja continuar?',
                                buttons: {
                                    confirm: {
                                        label: 'Sim',
                                        className: 'btn-nw-registro'
                                    },
                                    cancel: {
                                        label: 'Não',
                                        className: 'btn-nw-geral'
                                    }
                                },
                                callback: function (result) {
                                    if (result) {
                                        addProdutosClick();
                                    }
                                }
                            });
                        } else if (typeof data !== 'undefined' && parseFloat(data) >= parseFloat(quantidade)) {
                            addProdutosClick();
                        } else {
                            console.log(data);
                        }
                    },
                    function (data) {
                        console.log(data);
                    }
            );
        } else {
            bootbox.alert('Erro!');
        }
    }


    function ajaxGenerator(url, method, successFunction, errorFunction, data = null, async = false, completeFunction = null) {
        if (typeof async == 'undefined')
            async = false;
        method = method.toUpperCase();
        if (typeof errorFunction !== 'function') {
            errorFunction = getErrorFunctionAjaxGeneric();
        }
        if (typeof completeFunction !== 'function')
            completeFunction = function () {};
        if ("POST" === method || 'PATH' === method) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: url,
                type: method,
                data: data,
                cache: false,
                complete: completeFunction,
                success: successFunction,
                error: errorFunction,
                async: async,
                contentType: false,
                processData: false
            });
        } else if ("GET" === method) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: url,
                type: method,
                cache: false,
                complete: completeFunction,
                success: successFunction,
                error: errorFunction,
                async: async,
                contentType: false,
                processData: false
            });
        } else {
            console.log('Method invalid');
        }
        return false;
    }

    function getErrorFunctionAjaxGeneric(callback) {
        return function (data) {
            if (typeof dialog !== 'undefined')
                dialog.modal('hide');
            if (typeof (data) == 'object') {
                var msg = '';
                var responseText = '';
                for (var key in data) {
                    if (key == 'responseJSON') {
                        for (var key1 in data['responseJSON']) {
                            msg += '<br />' + data['responseJSON'][key1];
                        }
                    }
                    if (key == 'responseText') {
                        responseText = data['responseText'];
                    }
                }
                if (msg != '') {
                    bootbox.alert("Erro ao executar a ação: " + msg);
                } else {
                    bootbox.alert("Erro ao executar a ação: " + responseText);
                }
            } else if (typeof (data) == 'string') {
                bootbox.alert("Erro ao executar a ação: " + data);
            } else {
                bootbox.alert("Erro ao executar a ação!");
            }
            if (typeof callback === "function")
                callback();
        };
    }

//adicionar dias
    Date.prototype.addDays = function (days) {
        this.setDate(this.getDate() + parseInt(days));
        return this;
    };
    function padronizacaoData(data) {
        return (data.getDate() < 10 ? '0' + data.getDate() : data.getDate()) + '/' +
                ((data.getMonth() + 1) < 10 ? '0' + (data.getMonth() + 1) : (data.getMonth() + 1)) + '/' + data.getFullYear();
    }

    function trazerData(data) {
        var data = data.split("/");
        var datasplit = data[01] + '/' + data[0] + '/' + data[02];
        var date = new Date(datasplit);
        return date;
    }

//Parse Valores Dinheiro
    function parseDinheiro(val, casas) {
        var valor = val.replace('R$ ', '');
        var valor = valor.replace(/\./g, '');
        var valor = valor.replace(',', '.');
        var strvalor = parseFloat(valor).toFixed(casas);
        var parsed = parseFloat(valor);
        return parsed;
    }

//Formata um valor para decimal com casas decimais
    function formataDecimal(valor, decimais, permiteNegativo = false) {
        valor = parseFloat(valor).toFixed(decimais).toString();
        if (parseFloat(valor) < 0)
            var negativo = true;
        else
            var negativo = false;
        valor = valor.replace(/\D/g, ""); //Remove tudo o que não é dígito
        if (decimais === 2) {
            valor = valor.replace(/(\d{2})$/, ",$1"); //Coloca a virgula
        } else if (decimais === 3) {
            valor = valor.replace(/(\d{3})$/, ",$1"); //Coloca a virgula
        } else {
            alert("Casas decimais não configurado.");
            return 0;
        }
        valor = valor.replace(/(\d+)(\d{3},\d{2})$/g, "$1.$2"); //Coloca o primeiro ponto

        var qtdLoop = (valor.length - 3) / 3;
        var count = 0;
        while (qtdLoop > count)
        {
            count++;
            valor = valor.replace(/(\d+)(\d{3}.*)/, "$1.$2"); //Coloca o resto dos pontos
        }

// valor=valor.replace(/^(0)(\d)/g,"$2"); //Coloca hífen entre o quarto e o quinto dígitos
        if (negativo && permiteNegativo)
            valor = '-' + valor;
        return valor;
    }

    function replaceSpecialChars(str) {
        str = str.replace(/[ÀÁÂÃÄÅ]/, "A");
        str = str.replace(/[àáâãäå]/, "a");
        str = str.replace(/[ÈÉÊË]/, "E");
        str = str.replace(/[éèêë]/, "e");
        str = str.replace(/[ÏÌÍÎ]/, "I");
        str = str.replace(/[ïìíî]/, "i");
        str = str.replace(/[ÓÒÔÖÕ]/, "O");
        str = str.replace(/[óòôöõ]/, "o");
        str = str.replace(/[ÚÙÛÜ]/, "U");
        str = str.replace(/[úùûü]/, "u");
        str = str.replace(/[ÝŸ]/, "Y");
        str = str.replace(/[ýÿ]/, "y");
        str = str.replace(/[Ç]/, "C");
        str = str.replace(/[ç]/, "c");
        str = str.replace(/[Ñ]/, "N");
        str = str.replace(/[ñ]/, "n");
        return str.replace(/[^a-z0-9]/gi, '');
    }

    function dataAtual(fullYear = true, horas = false, minutos = false, segundos = false) {
        var data = new Date();
        var dia = data.getDate();
        if (dia.toString().length === 1) {
            dia = "0" + dia;
        }
        var mes = data.getMonth() + 1;
        if (mes.toString().length === 1) {
            mes = "0" + mes;
        }
        var ano = data.getFullYear().toString();
        if (!fullYear)
            ano = ano.substr(2, 3);
        var hora = data.getHours();
        if (hora.toString().length === 1) {
            hora = '0' + hora;
        }
        var min = data.getMinutes();
        if (min.toString().length === 1) {
            min = '0' + min;
        }
        var seg = data.getSeconds();
        if (seg.toString().length === 1) {
            seg = '0' + seg;
        }
        if (!horas) {
            return dia + "/" + mes + "/" + ano;
        }
        if (!minutos) {
            return dia + "/" + mes + "/" + ano + ' ' + hora;
        }
        if (!segundos) {
            return dia + "/" + mes + "/" + ano + ' ' + hora + ':' + min;
        }
        return dia + "/" + mes + "/" + ano + ' ' + hora + ':' + min + ':' + seg;
    }

    function arredondarNumeros(num, scale) {
        if (!("" + num).includes("e")) {
            return +(Math.round(num + "e+" + scale) + "e-" + scale);
        } else {
            var arr = ("" + num).split("e");
            var sig = ""
            if (+arr[1] + scale > 0) {
                sig = "+";
            }
            return +(Math.round(+arr[0] + "e" + sig + (+arr[1] + scale)) + "e-" + scale);
        }
    }

    jQuery.ajaxPrefilter(function (options) {
        var empresa_id = $("#empresa_documento").val() == "" || typeof $("#empresa_documento").val() == "undefined" ? "0" : $("#empresa_documento").val();
        if (typeof send_empresa !== 'undefined'){
           if(!send_empresa){
             return;
           }
        }
        options.url += (options.url.indexOf("?") == -1 ? "?" : "&") + "empresa_documento=" + empresa_id;
    });
// $.ajaxSetup({
//     beforeSend: function () {
//         if ($('#loader').is(':hidden') && typeof telaPedidos === 'undefined') {
//             $('#loader').modal('show');
//         }
// //        setTimeout(function () {
// //            if ($('#loader').is(':visible'))
// //                $('#loader').modal('hide');
// //        }, 10000);
// },
// complete: function () {
//     setTimeout(function () {
//         $('#loader').modal('hide');
//     }, 100);
// },
// success: function () {
//     setTimeout(function () {
//         $('#loader').modal('hide');
//     }, 100);
// },
// error: function () {
//     setTimeout(function () {
//         $('#loader').modal('hide');
//     }, 100);
// }
// });

// function removeLoader() {
//     $("#modalLoader").html('');
//     loaderAdd = false;
// }

// function adicionaLoader() {
//     if (!loaderAdd) {
//         $("#modalLoader").html('<div id="loader" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true"> \n\
//             <div class="modal-dialog">\n\
//             <div class="modal-content"> \n\
//             <div class="modal-header" style="text-align:center">  \n\
//             <button type="button" class="close" data-dismiss="modal">\n\
//             <span aria-hidden="true">&times;</span><span class="sr-only">Close</span>\n\
//             </button> \n\
//             <img src="' + root + '/img/ajax-loader-barra.gif" alt="Aguarde..."/> \n\
//             </div>\n\
//             </div>\n\
//             </div>\n\
//             </div>');
//         loaderAdd = true;
//     }
// }

// function addLoaderSelectize() {
//     if (typeof loaderAdd === 'undefined') {
//         loaderAdd = false;
//     }
//     $(".selectize-input input").on('focusout', function () {
//         adicionaLoader();
//     });
//     $(".selectize-input input").on('focusin', function () {
//         removeLoader();
//     });
// }

//coloca o foco no ultimo input antes de abrir uma modal
    function inputFocus() {
        if (breakFocusPrevInput)
            return;
        if (typeof $("#" + inputWithFocus) !== 'undefined') {
            var input = $("#" + inputWithFocus);
        } else {
            var input = $("input[name='" + inputWithFocus + "'");
        }
        if (typeof input !== "undefined" || (typeof input.attr('class') != 'undefined' && input.attr('class').toUpperCase().indexOf('PICKER') === -1)) {
            setTimeout(function () {
                input.focus();
                input.trigger('chosen:updated').trigger('chosen:activate');
            }, 500);
        }
    }

//Pega parametros da URL
    function getParametro(parametro) {
        return decodeURIComponent((new RegExp('[?|&]' + parametro + '=' + '([^&;]+?)(&|#|;|$)').exec(location.search)
                || [null, ''])[1].replace(/\+/g, '%20')) || null;
    }
//Retorna data e hora de parametros da url
    function retornarData(parametro, tempo) {
        var datainicial = getParametro(parametro);
        if (datainicial != null) {
            var datain = datainicial.split('_');
            var hora = datain[1];
            var data = datain[0];
            var data = data.split("-");
            var datatempo = data[2] + '/' + data[1] + '/' + data[0];
            if (tempo == true) {
                var datatempo = datatempo + " " + hora;
            }
            return datatempo;
        } else {
            return "";
        }
    }

//Passar data hora por parametro
    function insertDataHoraOracle(data) {
        if (data.isEmpty()) {
            data = dataAtual();
        }
        var datasplit = data.split(" ");
        var hora = datasplit[1];
        var data = datasplit[0];
        var data = data.split("/");
        var data = data[2] + '-' + data[1] + '-' + data[0];
        var datatempo = data + "_" + hora;
        return datatempo;
    }

//Função criar numero aleatorio para id
    function IDGenerator(length) {
        this.length = length;
        this.timestamp = +new Date;
        var _getRandomInt = function (min, max) {
            return Math.floor(Math.random() * (max - min + 1)) + min;
        }

        this.generate = function () {
            var ts = this.timestamp.toString();
            var parts = ts.split("").reverse();
            var id = "";
            for (var i = 0; i < this.length; ++i) {
                var index = _getRandomInt(0, parts.length - 1);
                id += parts[index];
            }

            return id;
        }
    }

//selecionar linha datatable
    function marcarLinha(table, row, callback) {
        if (row.hasClass('linhaselecionada')) {
            row.removeClass('linhaselecionada');
        } else {
            table.$('tr.linhaselecionada').removeClass('linhaselecionada');
            row.addClass('linhaselecionada');
        }
        if (typeof callback == 'function')
            callback();
    }

//seleciona várias linhas
    function marcarVariasLinhas(row) {
        if (row.hasClass('linhaselecionada')) {
            row.removeClass('linhaselecionada');
        } else {
            row.addClass('linhaselecionada');
        }
    }

    function mudaEstadoSelectReport(attrEmpresa_id, attrSetor_id, id) {
        if (typeof id == "undefined")
            var empresa_id = $("#empresa_id").val();
        else
            var empresa_id = $("#" + id).val();
        empresas_id = isEmpty(empresa_id) ? '' : empresa_id.toString();
        empresas_id = empresas_id.split(',');
        var empresas = [];
        if (typeof id == "undefined") {
            $('#' + attrEmpresa_id + ' option').filter(function () {
                for (var i = 0; i < empresas_id.length; i++) {
                    if (empresas_id[i] == $(this).val())
                        empresas.push(replaceSpecialChars($(this).text().toUpperCase()));
                }
                ;
            }).trigger('chosen:updated');
        } else {
            $('#' + id + ' option').filter(function () {
                for (var i = 0; i < empresas_id.length; i++) {
                    if (empresas_id[i] == $(this).val())
                        empresas.push(replaceSpecialChars($(this).parent('optgroup').attr('label').toUpperCase()));
                }
                ;
            }).trigger('chosen:updated');
        }

        $('#' + attrSetor_id).val($('#' + attrSetor_id + ' option').filter(function () {
            $(this).prop('disabled', false);
            if (empresas.indexOf(replaceSpecialChars($(this).parent('optgroup').attr('label').toUpperCase())) == -1)
                $(this).prop('disabled', true);
            if (isEmpty(empresa_id))
                $(this).prop('disabled', false);
        })).trigger('chosen:updated');
    }

    function openModalReport(url, modal) {
        if (modal) {
            $("#popup_relatorio").modal('show');
            $("#iFrameReport").attr('src', url);
        } else {
            window.open(url, '_blank');
        }
    }

//Aquele Cara
    function viewRegister(objValues) {
        populateFieldsEditView(objValues, true, false);
    }

    function editRegister(objValues) {
        populateFieldsEditView(objValues, false, false);
    }

    function populateFieldsEditView(objValues, view, remove) {
        $.each(objValues, function (key, val) {
            if (remove) {
                if (val != 'null' && val != null && !isEmpty(val)) {
                    if (key.includes('data') && !val.includes('/')) {
                        var data = requestDataOracle(val);
                        val = data.includes('undefined') ? requestDataOracle(val, false) : data;
                    }
                }
                if (key.includes('descricaofiltro'))
                    key = 'descricao';
                key += '_del';
            }

            var input = $("#" + key);
            var type = input.attr('type') || input.hasClass('selectChosen');
            if (typeof type != 'undefined') {
                if (type == 'checkbox') {
                    var checked = val == 1;
                    input.prop('checked', checked);
                } else {
                    input.val(val);
                }
            }
        });
        if (remove) {
            $('#fmCadastroDel :input').prop('disabled', true);
            $('#fmCadastroDel :button').prop('disabled', false);
            $('#fmCadastroDel :submit').prop('disabled', false);
            if (typeof $('#myModalDel').attr('id') != "undefined")
                $('#myModalDel').modal('show');
            else
                $('#modalDel').modal('show');
        } else {
            $('#fmCadastroAjax :input').prop('disabled', view);
            $('#fmCadastroAjax :button').prop('disabled', false);
            $('#fmCadastroAjax :submit').prop('disabled', view);
            if (view)
                $('#fmCadastroAjax :submit').hide();
            else
                $('#fmCadastroAjax :submit').show();
            var label = view ? 'Visualizar' : 'Editar';
            $('#myModalLabelCadastro').text(label + ' Registro');
            $('#myModal').modal('show');
        }
        $(".selectChosen").trigger('chosen:updated');
    }

    function removeRegister(objValues) {
        populateFieldsEditView(objValues, false, true);
    }

    function alertDelete(error, callback) {
        bootbox.alert({
            message: error,
            callback: function () {
                submitDel = false;
            }
        });
    }

    function putWhiteSpaces(str, maxLength) {
        var spaces = '';
        for (var i = str.length; i <= maxLength; i++)
            spaces += " ";
        return str + spaces;
    }

    function showDialogRedirect(url) {
        var dialog = bootbox.dialog({
            title: 'Operação realizada com sucesso!',
            message: '<p><i class="fa fa-spin fa-spinner"></i> Aguarde, você será redirecionado..</p>'
        });
        dialog.init(function () {
            window.setTimeout("location.href='" + url + "'", 1500);
        });
    }

    function showLoaderAjax(title, message, closeButton = true) {
        if (typeof title === "undefined")
            title = "Aguarde";
        if (typeof message === "undefined")
            message = "Carregando..";
        if (typeof ajaxLoaderDialog === "undefined") {
            ajaxLoaderDialog = bootbox.dialog({
                closeButton: closeButton,
                title: title,
                message: '<p><i class="fa fa-spin fa-spinner"></i> ' + message + '</p>'
            });
    }
    }

    function hideLoaderAjax() {
        if (typeof ajaxLoaderDialog !== "undefined") {
            ajaxLoaderDialog.modal('hide');
            ajaxLoaderDialog = undefined;
        }
    }

    function isEmptyNullOrUndefined(str) {
        if (typeof str !== "string")
            str += "";
        return typeof str === "undefined" || (str !== "undefined" && str.isEmpty());
    }

    function dd(...d) {
        for (let i = 0; i < d.length; i++)
            console.log(d[i]);
        throw new Error("Debugger");
    }

    function dump(...d) {
        for (let i = 0; i < d.length; i++)
            console.log(d[i]);
    }

    function removeURLParameter(url, parameter) {
        //prefer to use l.search if you have a location/link object
        var urlparts = url.split('?');
        if (urlparts.length >= 2) {

            var prefix = encodeURIComponent(parameter) + '=';
            var pars = urlparts[1].split(/[&;]/g);

            //reverse iteration as may be destructive
            for (var i = pars.length; i-- > 0; ) {
                //idiom for string.startsWith
                if (pars[i].lastIndexOf(prefix, 0) !== -1) {
                    pars.splice(i, 1);
                }
            }

            url = urlparts[0] + '?' + pars.join('&');
            return url;
        } else {
            return url;
        }
    }

    //Ajusta o primeiro e ultimo link para que redirecione a primeira e ultima pagina respectivamente
    //laravel faz a paginação voltar/seguir para a anterior/proxima página, o que foge do padrão do sistema
    function adjustPaginate() {
        var url = removeURLParameter(window.location.href, 'page');
        var $links = $(".pagination li");
        var $lastEl = $($links.get($links.length - 1)).children('a');
        var lastPage = getLastPageNumber($($links.get($links.length - 2)).children('a'));
        var firstChar = url.indexOf("?") == -1 ? "?" : "&";
        $lastEl.attr('href', url + firstChar + 'page=' + lastPage);
        $links.first().children('a').attr('href', url + firstChar + 'page=1');
    }

    function getLastPageNumber($el) {
        return decodeURIComponent((new RegExp('[?|&]page=' + '([^&;]+?)(&|#|;|$)').exec($el.attr('href'))
                || [null, ''])[1].replace(/\+/g, '%20')) || null;
    }

    function changeTabIndexAttr() {
        $("input.input-sm, textarea.input-sm").each(function () {
            if (!$(this).prop('readonly'))
                $(this).removeAttr("tabindex");
            else
                $(this).attr("tabindex", '-1');
        });
    }

    function reInsertShortcuts()
    {
//        setTimeout(function () {
//            if (originalShortcuts) {
//                $.each(Object.keys(originalShortcuts), function (i, el) {
//                    shortcut.remove(el);
//                    shortcut.add(el, originalShortcuts[el].callback);
//                });
//            }
//        }, 100);
    }

    function removeShortcuts()
    {
//        setTimeout(function () {
//            originalShortcuts = {};
//            var keys = Object.keys(shortcut.all_shortcuts);
//            $.each(keys, function (i, el) {
//                originalShortcuts[el] = shortcut.all_shortcuts[el];
//                shortcut.remove(el);
//            });
//        }, 100);
    }

    function getPrecoProdutoCliente($default, $byClient, produto_id, allowedClient = true, callback) {
        var precoF = 0;
        var produtos = [];
        var prices = $default.val();
        var precovenda;
        if (prices) {
            produtos = JSON.parse(prices);
            var p = produtos.where('produto_id', produto_id).first();
            if (typeof callback === "function")
                callback(p);
            precovenda = p.precovenda;
        }

        prices = $byClient.val();
        var prodClient;
        if (prices && allowedClient) {
            produtos = collect(JSON.parse(prices));
            prodClient = produtos.where('produto_id', produto_id).first();
        }

        if (prodClient && prodClient.tipo) {
            var preco = prodClient.preco ? prodClient.preco : 0;
            if (!preco && precovenda) {
                preco = precovenda;
            }
            var newPreco = applyDesc(prodClient.tipo, prodClient.desconto, preco);
            if (!newPreco) {
                newPreco = 0;
            }
            precoF = newPreco;
        } else {
            precoF = precovenda ? precovenda : 0;
        }
        return precoF;
    }
    
    function applyDesc(tipo, valDesc, origVal) {
        //tipo 1 = Valor, tipo 2 = Percentual
        if (tipo == 1) {
            return origVal - valDesc;
        } else if (tipo == 2) {
            return origVal - (origVal * valDesc);
        }
    }

} catch (e) {
    storageLogError(e, 'log-customjs');
}

function storageLogError(e, logName, sendAjax = true) {
    try {
        if (typeof logName === "undefined")
            logName = 'log-general';
        var date = new Date();
        var storageObj = {
            message: e.message,
            stack: e.stack,
            exception: e,
            url: window.location.href,
            datetime: date,
            logName: logName
        };
        localStorage.removeItem(logName);
        var atualStorage = localStorage.getItem(logName);
        if (atualStorage === null)
            atualStorage = [];
        else
            atualStorage = JSON.parse(atualStorage);
        atualStorage.push(storageObj);
        atualStorage = JSON.stringify(atualStorage);
        localStorage.setItem(logName, atualStorage);
        localStorage.removeItem(logName);
        console.log(storageObj);
        if (typeof sendAjax !== "undefined" && sendAjax) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: root + '/log.js',
                type: 'POST',
                data: storageObj,
                success: function (res) {
                    console.log('log enviado com sucesso');
                },
                error: function (res) {
                    console.error('Erro ao armazenar log');
                }
            });
        }
    } catch (e) {
        storageLogError(e, 'log-customjs');
}
}
