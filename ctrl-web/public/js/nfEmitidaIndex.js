try {
    var urlExportarXmls = root + '/nfemitida/exportarXmls/:ids';
    var tblCadastroCustom;

    $(document).ready(function () {
        var urlAtual = $(location).attr('href');
        $("#rotaIndex").text(urlAtual);
    });

    $('.btnInutilizar').on('click', function () {
        var $empresa = $("#empresa_id");
        var empresa_id = $empresa.intVal();
        if (!empresa_id) {
            bootbox.alert("Para inutlizar é preciso ter uma empresa selecionada no filtro.");
        } else {
            $("#empresainutilizar").val($empresa.find("option:selected").text());
            $("#xJust").val("");
            $("#nIni").val("");
            $("#nFin").val("");
            $("#nfmodeloinutilizar").val($("#nfmodelo").val()).trigger("chosen:updated");
            $("#myModalInutilizar").modal('show');
        }
    });

    $('#tblCadastroCustom').on('click', '#btnImprimir', function (e) {
        e.stopPropagation();
        var trElem = $(this).closest("tr"); // grabs the button's parent tr element
        var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
        var id = parseInt($(firstTd).text());
        atualizarStatusNF(id);
    }).on('click', '#btnVisualizar, #btnEditar', function (e) {
        e.stopPropagation();
    });

    $("#empresa_id").on('change', function () {
        var empresa = empresas.where('id', $(this).val()).first();
        var $modelo = $("#nfmodelo");
        $modelo.find("option").filter(function () {
            var $self = $(this);
            if ($self.val() === '55') {
                $self.prop('disabled', empresa.nfeemite === '0').trigger('chosen:updated');
            } else if ($self.val() === '65') {
                $self.prop('disabled', empresa.nfceemite === '0').trigger('chosen:updated');
            }
        });
        $modelo.val(empresa.nfceemite === "1" ? "65" : "55").trigger('chosen:updated');
    });

    $('#btnFiltros').on('click', function () {
        var urlFiltro = root + '/nfemitida';
        urlFiltro += '?dataI=:dataInicial';
        urlFiltro += '&dataF=:dataFinal';
        urlFiltro += '&nfmodelo=:nfmodelo';
        urlFiltro += '&numnf=:numnf';
        urlFiltro += '&empresa_id=:empresa_id';
        var dataInicial = $("#dataInicial").val();
        var dataFinal = $("#dataFinal").val();
        var nfmodelo = $("#nfmodelo").val();
        var numnf = $("#numnf").val();
        var empresa_id = $("#empresa_id").val();
        if (isEmpty(empresa_id)) {
            bootbox.alert('A empresa é obrigatória');
            return;
        }

        dataInicial = insertDataOracle(dataInicial);
        dataFinal = insertDataOracle(dataFinal);
        urlFiltro = urlFiltro.replace(':dataInicial', dataInicial);
        urlFiltro = urlFiltro.replace(':dataFinal', dataFinal);
        urlFiltro = urlFiltro.replace(':nfmodelo', nfmodelo);
        urlFiltro = urlFiltro.replace(':numnf', numnf);
        urlFiltro = urlFiltro.replace(':empresa_id', empresa_id);
        window.location.href = urlFiltro;
    });

    $('.btnExportarXMLs').on('click', function () {
        var ids = [];
        var data;
        var msg;
        var selected = tblCadastroCustom.getDataSelected();
        if (selected.length > 0) {
            data = selected;
            msg = "Deseja realmente exportar somente as linhas selecionadas? (exporta somente autorizadas)";
            msg += "<br />";
            msg += "Processamento pode demorar dependendo da quantidade de notas."
        } else {
            data = tblCadastroCustom.getData();
            msg = "Deseja realmente exportar todas as notas da pesquisa? (exporta somente autorizadas)";
        }
        bootboxConfirm("Atenção", msg, function () {
            if (confirm) {
                for (let i = 0; i < data.length; i++) {
                    var el = data[i];
                    if (el.nfsituacao_id === 100 || el.nfsituacao_id === "100") {
                        ids.push(el.id);
                    }
                }
                if (ids.length > 0) {
                    exportarXmls(ids);
                } else {
                    bootbox.alert("Precisa ter ao menos uma nota autorizada para ser exportada!");
                }
            }
        });
    });

    function confirmarInutilizacao() {
        var modelo = $("#nfmodeloinutilizar").find("option:selected").text();
        var de = parseInt($("#nIni").val());
        var ate = parseInt($("#nFin").val());
        bootbox.confirm({
            title: "Atenção!",
            className: "dontHideEsc",
            message: `Deseja inutilizar os números de: ${de} até: ${ate} do modelo: ${modelo}`,
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
                if (res) {
                    processInutilizacao();
                }
            }
        });
    }

    function processInutilizacao() {
        var nfmodelo = parseInt($("#nfmodeloinutilizar").val());
        var xJust = $("#xJust").val();
        var nIni = parseInt($("#nIni").val());
        var nFin = parseInt($("#nFin").val());
        var empresa_id = parseInt($("#empresa_id").val());
        var msg = "";
        var callback = function () {
            $('#myModalInutilizar').modal('show');
        };

        if (!xJust || !nIni || !nFin || !empresa_id || !nfmodelo) {
            msg = "Para inutlizar é preciso informar os campos de ";
            msg += "Justificativa, Número Inícial, Número Final, ";
            msg += "Empresa e Modelo";
        } else if (xJust.length > 15 && xJust.length <= 255) {
            showLoaderAjax();
            var url = root + '/nfemitida.inutilizarFaixaNFs?';
            url += 'nfmodelo=' + nfmodelo;
            url += '&xJust=' + xJust;
            url += '&nIni=' + nIni;
            url += '&nFin=' + nFin;
            url += '&empresa_id=' + empresa_id;
            ajaxGenerator(url, "GET", function (data) {
                if (typeof data === "string" && data.indexOf('realizada, protocolo') !== -1) {
                    callback = function () {
                        $('#myModalInutilizar').modal('hide');
                        window.location.reload();
                    };
                }
                msg = data;
            }, null, null, false, function () {
                hideLoaderAjax();
            });
        } else {
            msg = "Justificativa deve ter entre 15 a 255 caracteres.";
        }
        bootbox.alert(msg, callback);
        return false;
    }

    function exportarXmls(ids) {
        if (ids.length > 0) {
            var url = urlExportarXmls;
            url = url.replace(':ids', JSON.stringify(ids));
            window.open(url, '_blank');
        }
    }

    function atualizarStatusNF(id) {
        var url = root + '/nfemitida/evento/consultar?id=:id&getFile=1';
        url = url.replace(':id', id);
        window.open(url, '_blank');
        return false;
    }

    function bootboxConfirm(title, message, callback, btnConfirm = "Sim", btnCancel = "Não") {
        bootbox.confirm({
            title: title,
            message: message,
            buttons: {
                confirm: {
                    label: btnConfirm,
                    className: "btn-nw-registro"
                },
                cancel: {
                    label: btnCancel,
                    className: "btn-nw-geral"
                }
            },
            callback: function (result) {
                callback(result);
            }
        });
    }

} catch (e) {
    storageLogError(e, 'log-tela-nfe');
}
