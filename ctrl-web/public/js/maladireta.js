var totalSelecionados = 0;
var lastSelectedRow;
var statusDelTempFile;
$("#tblClientes").on('click', 'td', function () {
    var trElem = $(this).parent('tr');
    if (window.event.ctrlKey) {
        marcarVariasLinhas(trElem);
    }

    if (window.event.button === 0) {
        if (!window.event.ctrlKey && !window.event.shiftKey) {
            clearAllRows();
            marcarVariasLinhas(trElem);
        }
        if (window.event.shiftKey) {
            selectRowsBetweenIndexes(lastSelectedRow, trElem)
        }
    }
    contTotalSelected();
});

$("#btnMail").on('click', function () {
    getClientesEmail(tblClientes);
});

$("#btnCsv").on('click', function () {
    getClientesCelular(tblClientes);
});

$("#btnLimpar").on('click', function () {
    var tab = getTabActive();
    if (tab == '_tab_1')
        tab = '';
    $("#setor_id" + tab + ", #bairro_id" + tab + ", #rua_id" + tab).val('').trigger('chosen:updated');
    if (isEmpty(tab))
        $("#datainicio, #datafim").val(dataAtual());
    else if (tab == '_tab_3')
        $("#compram, #naocompram").val('');
    tblClientes.clear().draw();
});

$("#btnFiltro").on('click', function () {
    var tab = getTabActive();
    if (tab == '_tab_1') {
        tab = '';
        if (isEmpty($("#datainicio").val())) {
            bootbox.alert('O campo Data Início é obrigatório');
            return;
        }

        if (isEmpty($("#datafim").val())) {
            bootbox.alert('O campo Data Fim é obrigatório');
            return;
        }

        if (isEmpty($("#cidade_id").val())) {
            bootbox.alert('O campo Cidade é obrigatório');
            return;
        }
    } else if (tab == '_tab_3') {
        if (isEmpty($("#compram").val())) {
            bootbox.alert('O campo "Compram a" é obrigatório');
            return;
        }

        if (isEmpty($("#naocompram").val())) {
            bootbox.alert('O campo "Não compram a" é obrigatório');
            return;
        }

        if (parseInt($("#naocompram").val()) >= parseInt($("#compram").val())) {
            bootbox.alert('O campo "Não compram a" não deve ser maior ou igual que o "Compram a".');
            return;
        }
    }

    var url = root + '/maladireta?tab=' + tab + '&cidade=:cidade_id&bairro=:bairro_id&rua=:rua_id';
    url += '&datainicio=:datainicio&datafim=:datafim&setor=:setor_id&naocompram=:naocompram&compram=:compram';
    var bairro_id = $("#bairro_id" + tab).val() == 'null' ? '' : $("#bairro_id" + tab).val();
    var cidade_id = $("#cidade_id" + tab).val() == 'null' ? '' : $("#cidade_id" + tab).val();
    url = url.replace(':cidade_id', cidade_id);
    url = url.replace(':bairro_id', bairro_id);
    url = url.replace(':rua_id', $("#rua_id" + tab).val());
    url = url.replace(':datainicio', isEmpty(tab) ? $("#datainicio").val() : '');
    url = url.replace(':datafim', isEmpty(tab) ? $("#datafim").val() : '');
    url = url.replace(':compram', tab == '_tab_3' ? $("#compram").val() : '');
    url = url.replace(':naocompram', tab == '_tab_3' ? $("#naocompram").val() : '');
    url = url.replace(':setor_id', $("#setor_id" + tab).val());
    window.location.href = url;
});

$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
    $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
});

function configInit() {
    var city = getParametro("cidade");
    var tab = getParametro("tab");
    if ( city && !tab ) {
        $("#cidade_id").val( city ).trigger('chosen:updated');
        getEnderecoAba1(true, city);
    } else {
        initByUrl( tab );
        enableButton("cidade_id", false);
    }

    tblClientes = $("#tblClientes").DataTable({
        "language": {"url": urlDataTable},
        "processing": true,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": false,
        "bInfo": false,
        "bAutoWidth": false,
        "pageLength": 30,
        'scrollX': '120%',
        'scrollY': '300'
    });
}

$("#cidade_id").on('change', function () {
    if ($(this).isEmpty()) {
        $("#bairro_id").empty().trigger('chosen:updated');
        $("#rua_id").empty().trigger('chosen:updated');
    } else {
        getEnderecoAba1(true, $(this).val());
    }
});

$("#cidade_id_tab_2").on('change', function () {
    if ($(this).isEmpty()) {
        $("#bairro_id_tab_2").empty().trigger('chosen:updated');
        $("#rua_id_tab_2").empty().trigger('chosen:updated');
    } else {
        getEnderecoAba2(true, $(this).val());
    }
});

$("#cidade_id_tab_3").on('change', function () {
    if ($(this).isEmpty()) {
        $("#bairro_id_tab_3").empty().trigger('chosen:updated');
        $("#rua_id_tab_3").empty().trigger('chosen:updated');
    } else {
        getEnderecoAba3(true, $(this).val());
    }
});

function getEnderecoAba1(fromJs = false, city = null) {
    if (!fromJs) {
        idCidade = cidade_id;
    } else {
        idCidade = city;
    }
    enableButton( "cidade_id", fromJs );
    setInputsEnderecoPadrao('#cep', '#cidade_id', '#uf', '#bairro_id', '#rua_id');
    $(getIdInputCidade('geral')).val(idCidade).trigger('chosen:updated');
    changeCidade(function () {
        $(getIdInputBairro('geral')).val(bairro_id).trigger('chosen:updated');
        $(getIdInputRua('geral')).val(rua_id).trigger('chosen:updated');
        if (!fromJs) getEnderecoAba2();
    }, 'geral');
}

function getEnderecoAba2(fromJs = false, city = null) {
    enableButton( "cidade_id_tab_2", fromJs );
    if (!fromJs) {
        idCidade = cidade_id_tab_2;
    } else {
        idCidade = city;
    }
    setInputsEnderecoOutros('#cep', '#cidade_id_tab_2', '#uf', '#bairro_id_tab_2', '#rua_id_tab_2');
    $(getIdInputCidade('outros')).val(idCidade).trigger('chosen:updated');
    changeCidade(function () {
        $(getIdInputBairro('outros')).val(bairro_id_tab_2).trigger('chosen:updated');
        $(getIdInputRua('outros')).val(rua_id_tab_2).trigger('chosen:updated');
        console.log('banana');
        if (!fromJs) getEnderecoAba3();
    }, 'outros');
}

function getEnderecoAba3(fromJs = false, city = null) {
    enableButton( "cidade_id_tab_3", fromJs );
    if (!fromJs) {
        idCidade = cidade_id_tab_3;
    } else {
        idCidade = city;
    }
    setInputsEnderecoContabilista('#cep', '#cidade_id_tab_3', '#uf', '#bairro_id_tab_3', '#rua_id_tab_3');
    $(getIdInputCidade('contabilista')).val(idCidade).trigger('chosen:updated');
    changeCidade( function () {
        $(getIdInputBairro('contabilista')).val(bairro_id_tab_3).trigger('chosen:updated');
        $(getIdInputRua('contabilista')).val(rua_id_tab_3).trigger('chosen:updated');
        $('.btn').prop('disabled', false);
    }, 'contabilista');
}

function enableButton( id_city, fromJs) {
    var $city = $("#" + id_city);
    if ( $city.isEmpty() || fromJs ) {
        $('.btn').prop('disabled', false);
        return false;
    }
}

function getTabActive () {
    var tabAtiva = false;
    var tab = "#tab_1";
    $(".nav-tabs-custom > .nav-tabs").children('li').each(function () {
        if (tabAtiva)
            return true;
        if ($(this).hasClass('active')) {
            tab = $(this).children('a').attr('href');
            tabAtiva = true;
        }
    });
    tab = tab.replace("#", '_');
    return tab;
}

function contTotalSelected() {
    totalSelecionados = tblClientes.rows('.linhaselecionada').data().length;
    $("#totalSelecionados").html(totalSelecionados + ' de 90 clientes selecionados.');
}

//
function selectRowsBetweenIndexes(lastSelected, init) {
    var indexLastSelected = tblClientes.row(lastSelected).index();
    var indexInit = tblClientes.row(init).index();

    if (indexLastSelected > indexInit) {
        for (var i = indexInit; i <= indexLastSelected; i++) {
            $(tblClientes.row(i).node()).addClass('linhaselecionada');
        }
    } else if (indexLastSelected < indexInit) {
        for (var i = indexLastSelected; i <= indexInit; i++) {
            $(tblClientes.row(i).node()).addClass('linhaselecionada');
        }
    }
}

//seleciona várias linhas e muda o contador de clientes selecionados
function marcarVariasLinhas(row) {
    if (row.hasClass('linhaselecionada')) {
        row.removeClass('linhaselecionada');
    } else {
        row.addClass('linhaselecionada');
    }
    lastSelectedRow = row;
}

function clearAllRows() {
    tblClientes.rows('.linhaselecionada').nodes().to$().removeClass('linhaselecionada');
}

//
function getContentEmail(clientes, assunto = '', conteudo = '') {
    var message = 'Assunto:';
    message += '<br /> <input id="assunto" value="' + assunto + '" type="text" class="bootbox-input bootbox-input-select form-control" />';
    message += '<br /> Conteúdo:';
    message += '<br /> <textarea id="conteudo" class="bootbox-input bootbox-input-select form-control" rows="4" cols="50">' + conteudo + '</textarea>';
    bootbox.dialog({
        title: "Enviar E-mail",
        message: message,
        buttons: {
            confirm: {
                label: 'Enviar',
                className: "btn-nw-registro",
                callback: function () {
                    var conteudo = $("#conteudo").val();
                    var assunto = $("#assunto").val();
                    if (isEmpty(conteudo)) {
                        bootbox.alert({message: 'O conteúdo do email não pode ficar vazio.', callback: function () {
                            getContentEmail(clientes, assunto, conteudo);
                        }});
                        return;
                    }
                    enviarEmails(clientes, assunto, conteudo);
                }
            },
            cancel: {
                label: 'Cancelar',
                className: "btn-nw-geral"
            }
        }
    });

}

//enviar email
function enviarEmails(clientes, assunto, conteudo) {
    url = root + '/maladireta.mail';
    var formData = new FormData();
    var conteudo = unescape(escape(conteudo).replace(/%0A/g, '<br>'));
    formData.append('conteudo', conteudo);
    formData.append('assunto', assunto);
    formData.append('clientes', JSON.stringify(clientes));
    ajaxGenerator(url, 'POST', function (data) {
        if (data.substr(0, 3) == 'OK|')
            bootbox.alert("E-mail(s) enviado(s) com sucesso!");
        else
            bootbox.alert({message: 'Erro:' + data, callback: function () {
                getContentEmail(clientes, assunto, conteudo);
            }});
    }, function (data) {
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
                bootbox.alert({message: 'Erro:' + msg, callback: function () {
                    getContentEmail(clientes, assunto, conteudo);
                }});
            } else {
                bootbox.alert({message: 'Erro:' + responseText, callback: function () {
                    getContentEmail(clientes, assunto, conteudo);
                }});
            }
        } else if (typeof (data) == 'string') {
            bootbox.alert({message: 'Erro:' + data, callback: function () {
                getContentEmail(clientes, assunto, conteudo);
            }});
        } else {
            bootbox.alert({message: 'Erro ao executar a ação', callback: function () {
                getContentEmail(clientes, assunto, conteudo);
            }});
        }
    }, formData);
}

//gerar .csv
function gerarCsv(clientes) {
    var url = root + '/maladireta.csv';
    $("#clientes").val(JSON.stringify(clientes));
    $("#fmCsv").attr('action', url).submit();
}

$("#btnEtiquetas").on('click', function () {
    checaSelecionados(tblClientes);
});

//chequa a quantidade de linhas selecionadas 
function checaSelecionados(tbl) {
    var clientes = '';
    if (tbl.rows('.linhaselecionada').any()) {
        tbl.rows('.linhaselecionada').every(function () {
            var data = this.data();
            clientes += data[0] + ';';
        });

        selectNumInicialEtiquetas(clientes);
    } else {
        bootbox.alert('Selecione ao menos um cliente para gerar as etiquetas');
    }
}

function gerarEtiquetas(clientes, startIn) {
    var url = root + '/maladireta.etiquetas?clientes=' + clientes + '&apartir=' + startIn;
    $("#popup_relatorio > #fundo_popup").attr('style', 'width: 74%; margin-left: 12%');
    $("#popup_relatorio").modal('show');
    $("#iFrameReport").attr('src', url);
}

//abre a modal para selecionar o número inicial de da etiqueta
function selectNumInicialEtiquetas(clientes) {
    if (totalSelecionados > 90) {
        bootbox.alert('O máximo de clientes para gerar etiquetas é de 90');
        return;
    }
    var message = 'Imprimir a partir de:';
    message += '<br /> <input id="apartir" type="number" class="bootbox-input bootbox-input-select form-control" />';
    bootbox.dialog({
        title: "Selecione!",
        message: message,
        buttons: {
            confirm: {
                label: 'Gerar',
                className: "btn-nw-registro",
                callback: function () {
                    var startIn = $("#apartir").val();
                    var cli = clientes.split(';');
                    var qdeClientes = cli.length - 1;
                    if (startIn > 29 || isEmpty(startIn)) {
                        bootbox.alert({message: 'O número inicial de etiquetas não pode ser vazio nem maior que 29.', callback: function () {
                            selectNumInicialEtiquetas(clientes);
                        }});
                        return;
                    }
                    if ((parseInt(startIn) + qdeClientes) > 91) {
                        bootbox.alert({message: 'A quantidade de clientes somado ao número inicial das etiquetas não pode exceder 90.', callback: function () {
                            selectNumInicialEtiquetas(clientes);
                        }});
                        return;
                    }
                    gerarEtiquetas(clientes, startIn)
                }
            },
            cancel: {
                label: 'Cancelar',
                className: "btn-nw-geral"
            }
        }
    });
}

//pega os clientes que possuem email
function getClientesEmail(tbl) {
    var clientes = [];
    var clientesSemEmail = false;
    tbl.rows().every(function () {
        var data = this.data();
        if (!isEmpty(data[5])) {
            clientes.push({
                "id": data[0],
                "nome": data[1],
                "email": data[5]
            });
        } else {
            clientesSemEmail = true;
        }
    });
    if (clientes.length == 0) {
        bootbox.alert('Nenhum dos clientes da busca possui e-mail.');
        return;
    }
    if (clientesSemEmail)
        bootbox.alert({message: "Alguns clientes não possuem email cadastrado.", callback: function () {
                getContentEmail(clientes);
            }});
    else
        getContentEmail(clientes);
}

//pega os clientes que possuem celular
function getClientesCelular(tbl) {
    var clientes = [];
    var clientesSemCelular = false;
    tbl.rows().every(function () {
        var data = this.data();
        if (!isEmpty(data[4])) {
            clientes.push([
                data[1],
                data[4]
            ]);
        } else {
            clientesSemCelular = true;
        }
    });
    if (clientes.length == 0) {
        bootbox.alert('Nenhum dos clientes da busca possui telefone celular.');
        return;
    }
    if (clientesSemCelular)
        bootbox.alert({message: "Alguns clientes não possuem celular cadastrado.", callback: function () {
                gerarCsv(clientes);
            }});
    else
        gerarCsv(clientes);
}

function initByUrl( tab ) {
    var city = getParametro("cidade");
    var seto = getParametro("setor");
    console.log( $("#setor_id"  + tab), seto, tab );
    $("#cidade_id" + tab).val( city ).trigger('chosen:updated').trigger('change');
    $("#setor_id"  + tab).val( seto ).trigger('chosen:updated');

}