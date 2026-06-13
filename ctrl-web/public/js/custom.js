try {
    var breakFocusPrevInput = false;
    var loader = false;
    var submitDel = false;
    var inputWithFocus = null;
    var ignoreLastFocus = false;
    var formSubmetido = false;
    var ajaxLoaderDialog = undefined;

    const email = ['emailservidorsmtp', 'emailportasmtp',
        'emailassunto', 'emailcorpo', 'emailnomeremente'
    ];

    $(window).load(function () {
        $('.chosen-results').css('cssText', 'max-height: 150px !important');

        var tableAjust = function () {
            // noinspection JSUnresolvedFunction
            $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
        };
        $("input.input-sm, textarea.input-sm").each(function () {
            if ($(this).prop('readonly'))
                $(this).attr("tabindex", "-1");
        });
        let $_modal = $(".modal");
        $_modal.on('hidden.bs.modal', function () {
            tableAjust();
        });
        $_modal.on('shown.bs.modal', function () {
            tableAjust();
        });
        $('a[data-toggle="tab"]').on('shown.bs.tab', function () {
            tableAjust();
        });
        $(document).on("hidden.bs.modal", ".bootbox.modal", function () {
            tableAjust();
        });
        $(document).on("shown.bs.modal", ".bootbox.modal", function () {
            tableAjust();
        });
    });
    $(document).ready(function () {
        //limpa os options do plugin selectize após pressionar o backspace
        $('.selectize-input input').keyup(function (e) {
            if (e.keyCode === 8) {
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
            setTimeout(function () {
                $("#ativo").prop('checked', true);
            }, 500);
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
            if (filtro) {
                $(this).append("<input type='hidden' value='" + filtro + "' name='filtro_url' />");
            }
        });

        $("#btnEmailTeste").click( function () {
            var validado = validarSendMail();
            if ( validado ) {
                promptEmail();
            } else {
                bootbox.alert('Por favor, informe todos os campos.');
                return false;
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
                input = $(this).attr('id') ? $(this).attr('id').replace('_chosen', '') : null;
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
        }).on('focusin', function () {
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
                    if (typeof tab === 'undefined')
                        tab = "#tab_1";
                    tab = tab.split("_");
                    if (type === 'right') {
                        tab = parseInt(tab[1]) + 1;
                        tab = incrementsTable(tab);
                    } else {
                        tab = parseInt(tab[1]) - 1;
                        if (tab === 0 || !tabIsVisible(tab)) {
                            var ok = false;
                            var count = tab === 0 ? $(".nav-tabs").find('a[data-toggle="tab"]').length : tab;
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

        function validarSendMail() {
            if ( $("#emailremetente").isEmpty() ) return false;

            for (let i = 0; i < email.length; i++) {
                if ( $(`#${email[i]}`).isEmpty() ) {
                    return false;
                }
            }

            return true;
        }

        function promptEmail() {
            bootbox.prompt({
                title: "Para quem deseja enviar este e-mail?",
                buttons: {
                    confirm: {
                        label: 'Enviar',
                    },
                    cancel: {
                        label: 'Cancelar',
                    }
                },
                callback: function ( result ) {
                    if ( result ) {
                        if ( result.validateEmail() ) {
                            sendEmail( result );
                            return true;
                        } else if ( ! result.validateEmail() ) {
                            bootbox.alert("Por favor, informe um e-mail válido.");
                            return false;
                        }
                    }
                }
            });
        }

        function sendEmail( to ) {
            showLoaderAjax();
            var remetente = $("#emailremetente").val();
            var name = $("#emailnomeremente").val();
            var senha = $("#emailsenha").val();
            var host = $("#emailservidorsmtp").val();
            var port = $("#emailportasmtp").val();
            var subject = $("#emailassunto").val();
            var content = $("#emailcorpo").val();

            var url = root + "/empresaconfig.email?config=true";
            var data = new FormData();
            data.append("username", remetente);
            data.append("name", name);
            data.append("password", senha);
            data.append("host", host);
            data.append("port", port);
            data.append("subject", subject);
            data.append("content", content);
            data.append("to", to);

            ajaxGenerator( url, 'POST',
                function ( result ) {
                    if ( result.substr(0, 3) === 'OK|' )  bootbox.alert("E-mail enviado com sucesso!");
                    else bootbox.alert("Error: " + result);

                }, null, data, false,
                function () {
                    hideLoaderAjax();
                });
        }
        shortcut.add('shift+alt+down', function () {
            var ativou = false;
            $(".subtabs").children('li').each(function () {
                if (ativou)
                    return true;
                if ($(this).hasClass('active')) {
                    var tab = $(this).children('a').attr('href');
                    if (typeof tab === 'undefined')
                        tab = "#subtab_1";
                    tab = tab.split("_");
                    tab = parseInt(tab[1]) - 1;
                    if (tab === 0) {
                        var i = 0;
                        while (i === 0) {
                            if (! tabIsEmpty(tab, true)) {
                                tab++;
                                if (tabIsEmpty(tab, true)) {
                                    tab--;
                                    i++;
                                }
                            } else {
                                tab++;
                            }
                        }
                    }
                    if (!isNaN(tab) && ! $("#subtab_" + tab).hasClass('hidden')) {
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
                    if (typeof tab === 'undefined')
                        tab = "#subtab_1";
                    tab = tab.split("_");
                    tab = parseInt(tab[1]) + 1;
                    if (tabIsEmpty(tab, true)) {
                        tab = 1;
                    }
                    if (!isNaN(tab) && ! $("#subtab_" + tab).hasClass('hidden')) {
                        $('.nav-tabs a[href="#subtab_' + tab + '"]').tab('show');
                        ativou = true;
                    }
                }
            });
        });
        $(document).on("hidden.bs.modal", ".bootbox.modal", function () {
            inputFocus();
            if (formSubmetido)
                formSubmetido = false;
        });
        $("#modalSenha").on('hide.bs.modal', function () {
            $("#fmVerificaSenha").each(function () {
                this.reset();
            });
            $('body').css('overflow', 'auto');
        }).on('show.bs.modal', function () {
            setTimeout(function () {
                $("#motivo").focus();
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
        $(".floatNumber")
            .maskMoney({decimal: ',', symbolStay: true, allowNegative: false, precision: 2, precisionBefore: 4})
            .attr('maxlength', 6);
        $(".percentagem")
            .maskMoney({suffix: ' %', decimal: ',', symbolStay: true, allowNegative: false, precision: 2, precisionBefore: 3, affixesStay: true})
            .attr('maxlength', 8);
        $(".percentagemAlowZero")
            .maskMoney({suffix: ' %', decimal: ',', symbolStay: true, allowNegative: false, allowZero: true, precision: 2, precisionBefore: 3, affixesStay: true})
            .attr('maxlength', 8);
        $(".percentagemQuatroDig")
            .maskMoney({suffix: ' %', decimal: ',', symbolStay: true, allowNegative: false, precision: 3, precisionBefore: 3, affixesStay: true})
            .attr('maxlength', 9);
        $(".percentagemSomenteNum")
            .attr('maxlength', 6)
            .maskMoney({decimal: ',', symbolStay: true, allowNegative: false, precision: 2, precisionBefore: 3, affixesStay: false});
        $(".percentagemSomenteNumAllowZero")
            .attr('maxlength', 6)
            .maskMoney({decimal: ',', symbolStay: true, allowZero: true, allowNegative: false, precision: 2, precisionBefore: 3, affixesStay: false});
        $(".quantidadeLitros")
            .attr('maxlength', 7)
            .maskMoney({decimal: ',', symbolStay: true, allowNegative: false, precision: 3, precisionBefore: 3, affixesStay: false});
        $(".pGLP").attr('maxlength', 8).maskMoney({
            decimal: ',',
            allowZero: true,
            symbolStay: true,
            allowNegative: false,
            precision: 4,
            precisionBefore: 3,
            affixesStay: false
        });

        let obj = {
            "language": { "url": urlDataTable },
            "processing": true,
            "bPaginate": true,
            "bLengthChange": false,
            "bFilter": true,
            "bSort": true,
            "bInfo": false,
            "bAutoWidth": false,
            "pageLength": 30,
        };

        if ( window.innerWidth < 768 ) {
            obj = {
                "language": { "url": urlDataTable },
                "processing": true,
                "bPaginate": true,
                "bLengthChange": false,
                "bFilter": true,
                "bSort": true,
                "bInfo": false,
                "bAutoWidth": false,
                "sScrollX": true,
                "responsive": true,
                "pageLength": 30,
                "scrollY": '50vh',
                "scrollCollapse": true,
                // "pagingType": "simple",
            }
        }

        standardTable = $('.dataTable').DataTable(obj);

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
        $('.dataTableEndereco').dataTable({
            "language": {"url": urlDataTable},
            "processing": true,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
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
            // noinspection JSUnresolvedVariable
            if (typeof planoconta !== "undefined") {
                // noinspection JSUnresolvedVariable
                if (pagarreceber !== $("#pagarreceber").val() && nivel === 1 && !validaNivel) {
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
                            if (res) {
                                // noinspection JSUndeclaredVariable
                                validaNivel = true;
                                $(that).submit();
                            }
                        }
                    });
                    return false;
                }
            }
            var id = $("#id").val();
            var url;
            if (id !== '' && typeof id !== 'undefined') {
                url = $("#rotaUpdate").text() + id;
                $('#metodo').val('PATCH');
            } else {
                url = $("#rotaStore").text();
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
                    if (data.substr(0, 3) === 'OK|') {
                        var url = $("#rotaIndex").text();
                        showDialogRedirect(url);
                    } else {
                        $("input").prop('disabled', false);
                        bootbox.alert('Houve um problema ao gravar: ' + data);
                    }
                },
                error: function (data) {
                    $("input").prop('disabled', false);
                    let _fun = getErrorFunctionAjaxGeneric();
                    _fun(data);
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
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: url,
                data: formData,
                async: false,
                success: function (data) {
                    if (data.substr(0, 3) === 'OK|') {
                        let $_modal_del = $("#myModalDel");
                        var modal = $_modal_del.attr('modal');
                        if (typeof callbackSenha !== 'undefined') {
                            callbackSenha();
                        } else if (typeof pedidos !== 'undefined' && pedidos) {
                            gravarPedido();
                        } else {
                            if (modal === 'true') {
                                $_modal_del.modal('show');
                            } else {
                                window.location.href = $("#urlRedirect").attr('url');
                            }
                            $("input").prop('disabled', false);
                        }
                        $("#modalSenha").modal('hide');
                    } else if (data.substr(0, 3) === 'OPS') {
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
                    $("input").prop('disabled', false);
                    let _fun = getErrorFunctionAjaxGeneric();
                    _fun(data);
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
                    if (data === 'OK|') {
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
                        if (typeof (data) === 'object') {
                            msg = "<br />";
                            for (var key in data) {
                                if (key === 'responseJSON') {
                                    for (var key1 in data['responseJSON']) {
                                        if (data['responseJSON'].hasOwnProperty(key1)) {
                                            msg += "<br />" + data['responseJSON'][key1];
                                        }
                                    }
                                }
                                if (key === 'responseText')
                                    msg = data['responseText'];
                            }
                            if (msg !== '')
                                msg = 'Erro ao remover: ' + msg;
                        } else if (typeof (data) === 'string') {
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
            console.log('a');
            console.log($(this).val());

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
            $(this).select();
            $(this).mask('(99) 99999-9999');
        }).blur(function (event) {
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
        }).mask("(99) 99999-9999");
        $(".telefone2").on('focusin', function () {
            $(this).select();
            $(this).mask('(99) 99999-9999');
        }).blur(function (event) {
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
        }).mask("(99) 99999-9999");
        $('.dinheiro').maskMoney({prefix: 'R$ ', thousands: '.', decimal: ',', allowNegative: true, allowZero: true});
        $('.dinheiroNoZero').maskMoney({prefix: 'R$ ', thousands: '.', decimal: ',', allowNegative: true, allowZero: false});
        $('.dinheiroPrefixNone').maskMoney({thousands: '.', decimal: ',', allowNegative: true, allowZero: true});
        $('.maskMoney').maskMoney({thousands: '.', decimal: ','});
        $('.maskNumber').maskMoney({thousands: '.', precision: 0});
        $(".moneyFourCases").maskMoney({prefix: 'R$ ', decimal: ',', precision: 4, precisionBefore: 0}).attr('maxlength', 10);
        $('.maskDecimal').maskMoney({thousands: '.', decimal: ',', precision: 4});
        $('.baseCalculo').maskMoney({decimal: ',', precision: 3, allowZero: true, allowNegative: false}).attr('maxlength', 7);
        $('.baseCalculoSuffix').maskMoney({suffix: " %", decimal: ',', precision: 3, allowZero: true, allowNegative: false}).attr('maxlength', 9);
        $(".mask3Decimal").mask("99999999,999");
        $(".mask4Decimal").mask("99999999,9999");
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
        }).css('width', '80%');
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
        $(".modal").on('hide.bs.modal', function () {
            $('#tblCadastro').attr('btnClick', false);
            submitDel = false;
        }).on('shown.bs.modal', function () {
            // noinspection JSUnresolvedFunction
            $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
        }).on('hide.bs.modal', function () {
            inputFocus();
            if (typeof notClearIdFieldOnHideModal === 'undefined')
                $("#id").val('');
            $('#tblCadastro').attr('btnClick', 'false');
        });
        $(document).ajaxComplete(function myErrorHandler(event, xhr) {
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
        let _tipopessoa_id = $('#tipopessoa_id').val();
        if (typeof _tipopessoa_id !== 'undefined' && !isEmpty(_tipopessoa_id) && _tipopessoa_id.indexOf('F') !== -1) {
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
            $(".subtab_1").addClass('hidden').removeClass('active');
            $('#subtab_1').removeClass('active');
            $('#subtab_2').addClass('active');
            $('.subtab_2').addClass('active');
            $("label[for='nome']").text("Nome:");
        } else if (typeof _tipopessoa_id !== 'undefined' && !isEmpty(_tipopessoa_id) && _tipopessoa_id.indexOf('J') !== -1) {
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
            $('.subtab_1').addClass('active').removeClass('hidden');
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
        return empty;
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
            setor_id = $("#setor_id").val();
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
                        } else if (typeof data !== 'undefined' && parseFloat(data) >= quantidade) {
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
        if (typeof async === 'undefined')
            async = false;
        method = method.toUpperCase();
        if (typeof errorFunction !== 'function') {
            errorFunction = getErrorFunctionAjaxGeneric();
        }
        if (typeof completeFunction !== 'function')
            completeFunction = function () {};
        if ("POST" === method || 'PATCH' === method) {
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
            if (typeof (data) === 'object') {
                var msg = '';
                var responseText = '';
                for (var key in data) {
                    if (key === 'responseJSON') {
                        for (var key1 in data['responseJSON']) {
                            if (data['responseJSON'].hasOwnProperty(key1)) {
                                msg += '<br />' + data['responseJSON'][key1];
                            }
                        }
                    }
                    if (key === 'responseText') {
                        responseText = data['responseText'];
                    }
                }
                if (msg !== '') {
                    bootbox.alert("Erro ao executar a ação: " + msg);
                } else {
                    bootbox.alert("Erro ao executar a ação: " + responseText);
                }
            } else if (typeof (data) === 'string') {
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
        var dataN = data.split("/");
        var datasplit = dataN[1] + '/' + dataN[0] + '/' + dataN[2];
        return new Date(datasplit);
    }

//Parse Valores Dinheiro
    function parseDinheiro(val, casas) {
        var valor = val.replace('R$ ', '');
        valor = valor.replace(/\./g, '');
        valor = valor.replace(',', '.');
        var strvalor = parseFloat(valor).toFixed(casas);
        return parseFloat(strvalor);
    }

//Formata um valor para decimal com casas decimais
    function formataDecimal(valor, decimais) {
        var n = valor;
        var c = isNaN(decimais = Math.abs(decimais)) ? 2 : decimais;
        var d = ",";
        var t = ".";
        var s = n < 0 ? "-" : "";
        var i = parseInt(n = Math.abs(+n || 0).toFixed(c)) + "";
        var j = (j = i.length) > 3 ? j % 3 : 0;
        return s + (j ? i.substr(0, j) + t : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + t) + (c ? d + Math.abs(n - i).toFixed(c).slice(2) : "");
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

    jQuery.ajaxPrefilter(function (options) {
        let $empresa_documento = $("#empresa_documento");
        var empresa_id = $empresa_documento.val() === "" || typeof $empresa_documento.val() === "undefined" ? "0" : $empresa_documento.val();
        if ( ! options.url.includes('empresa_documento') )
            options.url += (options.url.indexOf("?") === -1 ? "?" : "&") + "empresa_documento=" + empresa_id;
    });

//coloca o foco no ultimo input antes de abrir uma modal
    function inputFocus() {
        if (breakFocusPrevInput)
            return;
        let $inputFocus = $("#" + inputWithFocus);
        var input;
        if (typeof $inputFocus !== 'undefined') {
            input = $inputFocus;
        } else {
            input = $("input[name='" + inputWithFocus + "']");
        }
        let _hasClass = typeof input.attr('class') !== 'undefined' && input.attr('class').toUpperCase().indexOf('PICKER') === -1;
        if (! ignoreLastFocus && (typeof input !== "undefined" || _hasClass)) {
            setTimeout(function () {
                input.focus();
                input.trigger('chosen:updated').trigger('chosen:activate');
            }, 250);
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
            data = data.split("-");
            var datatempo = data[2] + '/' + data[1] + '/' + data[0];
            if (tempo) {
                datatempo = datatempo + " " + hora;
            }
            return datatempo;
        } else {
            return "";
        }
    }

//Passar data hora por parametro
    function insertDataHoraOracle(dataParam, separator) {
        if (!separator) {
            separator = "_";
        }
        var data = dataParam;
        if (data.isEmpty()) {
            data = dataAtual();
        }
        var datasplit = data.split(" ");
        var hora = datasplit[1];
        data = datasplit[0];
        data = data.split("/");
        data = data[2] + '-' + data[1] + '-' + data[0];
        return data + separator + hora;
    }

//Função criar numero aleatorio para id
    function IDGenerator(length) {
        this.length = length;
        this.timestamp = +new Date;
        var _getRandomInt = function (min, max) {
            return Math.floor(Math.random() * (max - min + 1)) + min;
        };

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
        if (typeof callback === 'function')
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
        var empresa_id;
        if (typeof id === "undefined")
            empresa_id = $("#empresa_id").val();
        else
            empresa_id = $("#" + id).val();
        let empresas_id = isEmpty(empresa_id) ? '' : empresa_id.toString();
        empresas_id = empresas_id.split(',');
        var empresas = [];
        if (typeof id === "undefined") {
            $(`#${attrEmpresa_id} option`).filter(function () {
                for (var i = 0; i < empresas_id.length; i++) {
                    if (empresas_id[i] === $(this).val())
                        empresas.push(replaceSpecialChars($(this).text().toUpperCase()));
                }
            }).trigger('chosen:updated');
        } else {
            $(`#${id} option`).filter(function () {
                for (var i = 0; i < empresas_id.length; i++) {
                    if (empresas_id[i] === $(this).val())
                        empresas.push(replaceSpecialChars($(this).parent('optgroup').attr('label').toUpperCase()));
                }
            }).trigger('chosen:updated');
        }

        $('#' + attrSetor_id).val($(`#${attrSetor_id} option`).filter(function () {
            $(this).prop('disabled', false);
            if (empresas.indexOf(replaceSpecialChars($(this).parent('optgroup').attr('label').toUpperCase())) === -1)
                $(this).prop('disabled', true);
            if (isEmpty(empresa_id))
                $(this).prop('disabled', false);
        })).trigger('chosen:updated');
    }

    function openModalReport(url, modal) {
        if (modal) {
            $("#popup_relatorio").modal('show');
            $("#iFrameReport").prop('src', url);
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
                if (val !== 'null' && val != null && !isEmpty(val)) {
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
            if (typeof type !== 'undefined') {
                if (type === 'checkbox') {
                    var checked = parseInt(val) === 1;
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
            let $_modalDel = $('#myModalDel');
            if (typeof $_modalDel.attr('id') !== "undefined")
                $_modalDel.modal('show');
            else
                $('#modalDel').modal('show');
        } else {
            $('#fmCadastroAjax :input').prop('disabled', view);
            $('#fmCadastroAjax :button').prop('disabled', false);
            let $_formSub = $('#fmCadastroAjax :submit');
            $_formSub.prop('disabled', view);
            if (view)
                $_formSub.hide();
            else
                $_formSub.show();
            var label = view ? 'Visualizar' : 'Editar';
            $('#myModalLabelCadastro').text(label + ' Registro');
            $('#myModal').modal('show');
        }
        $(".selectChosen").trigger('chosen:updated');
    }

    function removeRegister(objValues) {
        populateFieldsEditView(objValues, false, true);
    }

    function alertDelete(error) {
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

    function showLoaderAjax(title, message, closeButton = true, callback) {
        if (typeof title === "function") {
            callback = title;
            title = undefined;
        }
        if (typeof title === "undefined")
            title = "Aguarde";
        if (typeof message === "undefined")
            message = "Carregando..";
        if (typeof ajaxLoaderDialog === "undefined") {
            ajaxLoaderDialog = bootbox.dialog({
                closeButton: closeButton,
                title: title,
                className: 'teste',
                message: '<p><i class="fa fa-spin fa-spinner"></i> <span id="content-loader-ajax">' + message + '</span></p>'
            });
        }
        if (typeof callback === "function") {
            callback();
        }
    }

    // noinspection JSUnusedGlobalSymbols
    function updateContentLoaderAjax(msg) {
        $("#content-loader-ajax").text(msg);
    }

    function hideLoaderAjax(callback) {
        if (typeof ajaxLoaderDialog !== "undefined") {
            ajaxLoaderDialog.modal('hide');
            ajaxLoaderDialog = undefined;
        }
        if (typeof callback === "function") {
            callback();
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
        var firstChar = url.indexOf("?") === -1 ? "?" : "&";
        $lastEl.attr('href', url + firstChar + 'page=' + lastPage);
        $links.first().children('a').attr('href', url + firstChar + 'page=1');
    }

    function getLastPageNumber($el) {
        return decodeURIComponent((new RegExp('[?|&]page=' + '([^&;]+?)(&|#|;|$)').exec($el.attr('href'))
                || [null, ''])[1].replace(/\+/g, '%20')) || null;
    }

    function changeTabIndexAttr() {
        $("input.input-sm, textarea.input-sm").each(function () {
            if (! $(this).prop('readonly'))
                $(this).removeAttr("tabindex");
            else
                $(this).attr("tabindex", '-1');
        });
    }

    function fetchGET(url) {
        return fetch(url, {
            method: "GET", // *GET, POST, PUT, DELETE, etc.
            headers: {
                "Content-Type": "application/json",
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                "X-Requested-With": "XMLHttpRequest",
            },
        }).then((response) => {
            if (response.ok) {
                return response.json();
                } else {
                    return Promise.reject({
                        status: response.status,
                        message: response.statusText
                    });
            }
        });
    }

    function fetchPOST(url, data) {
        return fetch(url, {
            method: "POST", // *GET, POST, PUT, DELETE, etc.
            headers: {
                "Content-Type": "application/json",
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(data),
        }).then((response) => {
            if (response.ok) {
                return response.json();
            } else {
                return Promise.reject({
                    status: response.status,
                    message: response.statusText
                });
            }
        });
    }

    function fetchPOSTString(url, data) {
        return fetch(url, {
            method: "POST", // *GET, POST, PUT, DELETE, etc.
            headers: {
                "Content-Type": "application/json",
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(data),
        }).then((response) => {
            if (response.ok) {
                return response.text();
            } else {
                return Promise.reject({
                    status: response.status,
                    message: response.statusText
                });
            }
        });
    }

    function readonly(fields) {
        $(fields).prop('readonly', true).prop('tabindex', -1);
    }

    function getPrecoProdutoCliente($default, $byClient, produto_id, allowedClient, callback) {
        if (typeof allowedClient === "undefined") {
            allowedClient = true;
        }
        let precoF = 0;
        let produtos = [];
        let prices = $default.val();
        let precovenda;
        if (prices) {
            produtos = JSON.parse(prices);
            let p = produtos.where('produto_id', produto_id).first();
            if (typeof callback === "function")
                callback(p);
            precovenda = p.precovenda;
        }

        prices = $byClient.val();
        let prodClient;
        if (prices && allowedClient) {
            produtos = collect(JSON.parse(prices));
            prodClient = produtos.where('produto_id', produto_id).first();
        }

        if (prodClient && prodClient.tipo) {
            let preco = prodClient.preco ? prodClient.preco : 0;
            if (!preco && precovenda) {
                preco = precovenda;
            }
            let newPreco = applyDesc(prodClient.tipo, prodClient.desconto, preco);
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
        if (parseInt(tipo) === 1) {
            return origVal - valDesc;
        } else if (parseInt(tipo) === 2) {
            return origVal - (origVal * valDesc);
        }
    }

    function validatePrecoMinimo($prices, produto_id, userValue, _ignoreAlert) {
        _ignoreAlert = typeof _ignoreAlert === "undefined" ? false : _ignoreAlert;
        var produtos = [];
        var prices = $prices.val();
        var precovendaminimo;
        var finalValue = userValue;
        userValue = parseDinheiro(userValue, 2);

        if (prices) {
            produtos = JSON.parse(prices);
            var p = produtos.where('produto_id', produto_id).first(true);
            precovendaminimo = p ? p.precovendaminimo : userValue;
        }
        if (precovendaminimo && userValue < precovendaminimo && ! _ignoreAlert) {
            finalValue = formataDecimal(precovendaminimo, 2);
            var msg = "O valor informado não pode ser menor que o preço mínimo do ";
            msg += "produto: R$ " + finalValue;
            bootbox.alert(msg);
        }
        return finalValue;
    }

    function onlyNumbers(value) {
        return value.replace(/\D/g, '');
    }

    function tabIsEmpty(num_tab, subtab = false) {
        if (subtab) {
            return isEmpty($("#tab_" + num_tab).text());
        } else {
            return isEmpty($("#subtab_" + num_tab).text());
        }
    }

    function numberFormat(number, casas, format) {
        let n;
        try {
            if (typeof casas === "undefined")
                casas = 2;
            if (typeof format === "undefined")
                format = "s";
            if (typeof number === "string") {
                if (number.indexOf(',') === -1) {
                    number = number.replace(new RegExp(".", "g"), '').replace(',', '.');
                }
                number = floatVal(number, casas)
            }
            n = number.toFixed(casas).replace('.', ',');
            if (n.indexOf(',') === -1) {
                n += ',';
            }

            let splited = n.split(',')[1];
            let count = splited.length;
            while (count < casas)
            {
                count++;
                n += '0';
            }
        } catch (e) {
            n = '0';
            console.error("Impossível converter a variável. Causa: " + e.message);
        }
        return format.toUpperCase() === "N" ? parseFloat(n.replace(",", '.')) : n;
    }

    function floatVal(str, casas) {
        if (!casas) {
            casas = 2;
        }
        if (typeof str !== "string")
            str += "";
        var value;
        if (str.indexOf("R$") !== -1 || str.indexOf(",") !== -1)
            value = parseDinheiro(str, casas);
        else
            value = parseFloat(parseFloat(str).toFixed(casas));
        return isNaN(value) ? 0 : value;
    }
} catch (e) {
    storageLogError(e, 'log-customjs');
}

function storageLogError(e, logName, sendAjax = true) {
    try {
        if (typeof logName === "undefined")
            logName = 'log-general';
        if (logName === "log-pedido-js" || logName === "log-pedido-general-js" || logName === "log-pedido-frete-js" || logName === "log-pedido-nf-js") {
            alert("Um possível erro ocorreu ao executar o script da página, contate o suporte");
        }
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
        // localStorage.removeItem(logName);
        console.log(storageObj);
        if (typeof sendAjax !== "undefined" && sendAjax) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: root + '/log.js',
                type: 'POST',
                data: storageObj,
                success: function () {
                    console.log('log enviado com sucesso');
                },
                error: function () {
                    console.error('Erro ao armazenar log');
                }
            });
        }
    } catch (e) {

    }
}
