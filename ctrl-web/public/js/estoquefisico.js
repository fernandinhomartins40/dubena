var selected_id = 0;
var selected_setor = 0;
var selected_produto = 0;
var selected_unidademedida = 0;
var selected_quantidadeatualsistema = 0;
var selected_quantidadeatualfisico = 0;
var setorSelectedOld = '';
var dataAnterior = null;
var confirm = false;
$(document).ready(function () {
    $("#fmCadastro").on('submit', function (e) {
        if (!confirm) {
            var estoque = [];
            $('#tblEstoqueFisico tbody tr').each(function () {
                var row = {};
                var modified = false;
                var zerou = false;
                var removeu = false;
                $(this).find('td').each(function (index) {
                    if (index == 0)
                        row.id = $(this).text();
                    else if (index == 1)
                        row.setor = $(this).text();
                    else if (index == 2)
                        row.produto = $(this).text();
                    else if (index == 3)
                        row.unidademedida = $(this).text();
                    else if (index == 4)
                        row.quantidadeatualsistema = $(this).text();
                    else if (index == 5)
                        row.quantidadeatualfisico = $(this).text();
                    else if (index == 6)
                        row.diferenca = $(this).text();
                    else if (index == 7) {
                        var zerar = $(this).find('input[type="checkbox"]');
                        if (zerar.prop('checked'))
                            zerou = true;
                    } else if (index == 8) {
                        var remover = $(this).find('input[type="checkbox"]');
                        if (remover.prop('checked'))
                            removeu = true;
                    }
                    if (index == 8) {
                        row.zerar = zerou;
                        row.remover = removeu;
                        estoque.push(row);
                    }
                });
            });
            if (estoque.length == 0) {
                bootbox.alert('Não é possível gravar pois nenhum estoque foi editado!');
                return false;
            }
            $("#estoqueAlterado").val(JSON.stringify(estoque));
            bootbox.confirm({
                title: 'Atenção!',
                className: 'warning',
                message: 'Deseja efetivar o estoque?',
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
                    if (result)
                        $("#efetivado").val(1);
                    else
                        $("#efetivado").val(0);
                    confirm = true;
                    $("#fmCadastro").submit();
                }
            });
            return false;
        }
    });
    $('#tblEstoqueFisico').bootstrapTable({
        columns: [{
                field: 'id',
                title: '#'
            }, {
                field: 'setor',
                title: 'Setor'
            }, {
                field: 'produto',
                title: 'Produto'
            }, {
                field: 'unidademedida',
                title: 'Unidade Medida'
            }, {
                field: 'quantidadeatualsistema',
                title: 'Qde Atual Sistema'
            }, {
                field: 'quantidadeatualfisico',
                title: 'Qde Atual Físico'
            }, {
                field: 'diferenca',
                title: 'Diferença'
            }, {
                field: 'zerar',
                title: 'Zerar'
            }, {
                field: 'remover',
                title: 'Remover'
            }],
        onPostBody: function () {
            disableInputCells('tblEstoqueFisico', ['quantidadeatualfisico']);
            $('#tblEstoqueFisico').editableTableWidget({editor: $('<input number="true" data-empty="0" type="text">')});
            callbackAfterEdit = function () {
                var insertIndex = 0;
                $("#tblEstoqueFisico tbody tr").each(function (i) {
                    var id = $($(this).children('td')[0]).text();
                    if (id == selected_id)
                        insertIndex = i;
                });
                getSelecteds(function () {
                    $('#tblEstoqueFisico tbody tr').each(function () {
                        var sistema = 0;
                        var fisico = 0;
                        $(this).find('td').each(function (index) {
                            if (index == 4)
                                sistema = parseInt($(this).text());
                            else if (index == 5)
                                fisico = parseInt($(this).text());
                            else if (index == 6 && fisico > 0) {
                                $(this).text('');
                                $(this).append(sistema - fisico);
                            }
                        });
                    });
                });

            }
        }
    });
});

$("#tblEstoqueFisico").on('focusin', 'tr', function () {
    selected_id = $($(this).children('td')[0]).text();
    trSelected = this;
});

$("#setor_id").on('change', function () {
    confirmChangeSetorData();
});

$("#datacompetencia").on('focusout', function () {
    if ($(this).val() != dataAnterior && dataAnterior !== null)
        confirmChangeSetorData();
    dataAnterior = $(this).val();
});

function confirmChangeSetorData() {
    bootbox.confirm({
        title: 'Atenção!',
        className: 'warning',
        message: 'Se você mudar o setor ou a data, todas as alterações serão perdidas, deseja continuar?',
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
                preencherTblEstoque();
                dataAnterior = $("#datacompetencia").val();
                setorSelectedOld = $("#setor_id").val();
            } else {
                $("#setor_id").val(setorSelectedOld).trigger('chosen:updated');
                $("#datacompetencia").val(dataAnterior);
            }
        }
    });
}

function getSelecteds(callBack) {
    if (typeof trSelected !== 'undefined') {
        selected_id = $($(trSelected).children('td')[0]).text();
        selected_setor = $($(trSelected).children('td')[1]).text();
        selected_produto = $($(trSelected).children('td')[2]).text();
        selected_unidademedida = $($(trSelected).children('td')[3]).text();
        selected_quantidadeatualsistema = $($(trSelected).children('td')[4]).text();
        selected_quantidadeatualfisico = $($(trSelected).children('td')[5]).text();
        if (typeof callBack == 'function')
            callBack();
    }
}

function defineFisicoIgualSistema() {
    $('#tblEstoqueFisico tbody tr').each(function () {
        var sistema = 0;
        $(this).find('td').each(function (index) {
            if (index == 4)
                sistema = parseInt($(this).text());
            else if (index == 5 && sistema > 0) {
                $(this).text('');
                $(this).append(sistema);
            }
        });
    });
}

function preencherTblEstoque(estoquefisicosetor = null) {
    if (estoquefisicosetor == null) {
        var setor_id = !isEmpty($("#setor_id").val()) ? $("#setor_id").val() : null;
        var datacompetencia = !isEmpty($("#datacompetencia").val()) ? insertDataOracle($("#datacompetencia").val()) : null;
        var urlDados = root + '/estoquefisico/buscaEstoqueSetor/' + setor_id + '/' + datacompetencia;
        $("#tblEstoqueFisico").bootstrapTable('refresh', {
            url: urlDados
        });
    } else {
        var checkboxZerar = '<input type="checkbox" id="zerar" :checked>';
        var checkboxRemover = '<input type="checkbox" id="remover" :checked>';
        $.each(estoquefisicosetor, function (i, el) {
            if (typeof el.remover === 'boolean')
                el.remover = !el.remover ? checkboxRemover.replace(':checked') : checkboxRemover.replace(':checked', 'checked');
            if (typeof el.zerar === 'boolean')
                el.zerar = !el.zerar ? checkboxZerar.replace(':checked') : checkboxZerar.replace(':checked', 'checked');
            $('#tblEstoqueFisico').bootstrapTable('insertRow', {index: i, row: el});
        });
}
}