$(document).ready(function () {
    $("#colaborador_id").val(function () {
        if ($("#setor_id").val() !== '') {
            buscaColaboradoresPorSetor();
        }
    });
    $("#colaborador_id").change(function () {
        $("#hiddencolaborador_id").val($(this).val());
    });
    $("#setor_id").change(function () {
        $("#colaborador_id").empty().trigger('chosen:updated');
        if ( ! $(this).isEmpty() ) buscaColaboradoresPorSetor();
    });

    $("#btnZeraFiltro").on('click', function () {
        urlBuscaIndex = urlBuscaIndex.replace(':setor_id', '&');
        urlBuscaIndex = urlBuscaIndex.replace(':produto', '&');
        window.location = urlBuscaIndex;
    });

    $("#btnBusca").on('click', function () {
        urlBuscaIndex = urlBuscaIndex.replace(':setor_id', $("#setor_id").val() + '&');
        urlBuscaIndex = urlBuscaIndex.replace(':colaborador_id', $("#colaborador_id").val());
        window.location = urlBuscaIndex;
    });

    $("#btnReplicar").click( function () {
        var checked = $("#replicar").prop('checked');
        confirmarReplicar();
    });

    tblExcecoesComissao = $("#tblExcecoesComissao").DataTable({
        'language':{'url': urlDataTable},
        'paginate': false,
        'info': false,
        'filter': false
    });

    tblExcecoesComissao.rows().every(function () {
        console.error();
        var d = this.data();
        $("#segmento_id option").filter(function () {
            if ($(this).val() == d[0]) {
                $(this).attr("disabled", true);
            }
        });
    });
    $("#segmento_id").trigger('chosen:updated');

    $("#fmCadastro").on('submit', function() {
        var data = $("#tblExcecoesComissao").dataTable().fnGetData();
        $("#setor_id").prop('disabled', false);
        if (data.length > 0) {
            $("#excecoes").val(JSON.stringify(data));
        }
    });
    $('#tonelagem').change(function() {
        if(this.checked) {
            //Comissão por tonelagem
            if($('input[name="tipocomissao"]:checked').val()==2 || existsExceptionValor()){
                bootbox.confirm({
                    title: "Atenção!",
                    className: "dontHideEsc",
                    message: "Deseja alterar para comissão por Tonelagem? O tipo será alterado para percentual e valores de repasse serão removidos da tela",
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
                        if(res){
                            setComissaoTonelagem();
                        } else {
                            $('#tonelagem').prop('checked', false);
                        }
                    }
                });
            } else {
                setComissaoTonelagem();
            }
        } else {
            //Comissão normal
            let $radios = $('input:radio[name=tipocomissao]');
            $radios.filter('[value=2]').attr('disabled', false);
            $radios = $('input:radio[name=tipoexcecao]');
            $radios.filter('[value=2]').attr('disabled', false);

            $('#setor_id option').attr('disabled', false);
            $('#setor_id').val(-1).trigger('chosen:updated');
            $('#colaborador_id').val('').trigger('chosen:updated');
            $('#condicaopagamento_id option').attr('disabled', false)
            $('#condicaopagamento_id').trigger('chosen:updated');
            buscaColaboradoresPorSetor();

            $('#btnReplicar').attr('disabled', false);
            $('#btnReplicar').show();
        
        }
        
    });
    if ( typeof errors !== "undefined" && errors) carregarExcecoesErro();
});

$('#tblCadastro').on('click', 'button', function () {
    var trElem = $(this).closest("tr");// grabs the button's parent tr element
    var firstTd = $(trElem).children("td")[0];//takes the first td which would have your Id
    var id = $(firstTd).text();
    if ($(firstTd).text() != "") {
        if ($(this).context.id == 'btnEditar') {
            var url = urlEdit;
            url = url.replace(':id', id);
            window.location.href = url;
        }
    }
});

$("#btnAddExcecao").on('click', function() {
    var segmento_id = $("#segmento_id").val();
    var valorAdd =  $("#tipoexcecao:checked").val() == 2 ? $("#valorexcecao").val() : $("#percentualexcecao").val();
    var valorAddApp =  $("#tipoexcecao:checked").val() == 2 ? $("#valorexcecaoapp").val() : $("#percentualexcecaoapp").val();
    if(isEmpty(segmento_id)) {
        bootbox.alert('Selecione o segmento!');
        return;
    }  
    console.log(4, valorAdd);
    if(isEmpty(valorAdd)) {
        bootbox.alert('Informe o valor/percentual!');
        return;
    }
    if(isEmpty(valorAddApp)) {
        bootbox.alert('Informe o valor/percentual para o App!');
        return;
    }
    tblExcecoesComissao.row.add([
        segmento_id,
        $("#segmento_id option:selected").text(),
        $("#tipoexcecao:checked").val() == 2 ? 'Repasse' : 'Percentual',
        valorAdd,
        valorAddApp,
        '<button type="button" class="btn-xs btn-nw-registro btn" id="btnRemoverExcecao">Remover</button>'
    ]).draw();

    $("#segmento_id option:selected").attr('disabled', 'true').trigger('chosen:updated');
    $("#valorexcecao, #percentualexcecao, #valorexcecaoapp, #percentualexcecaoapp").val('');
});     
$("#tblExcecoesComissao").on('click', 'button', function() {
    var row = $(this).closest('tr');
    var data = $('#tblExcecoesComissao').dataTable().fnGetData(row);
    var produto = data[1];
    if (data[0] !== '') {
        tblExcecoesComissao.row($(this).parents('tr')).remove().draw();
    }
    $("#segmento_id option").filter(function () {
        if ($(this).val() == data[0]) {
            $(this).attr("disabled", false);
        }
    });
    $("#segmento_id").trigger('chosen:updated');
});
function buscaColaboradoresPorSetor() {
    var url = urlBuscaColaboradoresPorSetor;
    var id = $("#setor_id").val();
    url = url.replace(':setor_id', id);
    $("#colaborador_id").empty();
    if ($("#setor_id").val() !== '') {
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
            },
            type: "GET",
            url: url,
            success: function (data) {
                var html = "<option value=''>Selecione</option>";
                $.each(data, function (i) {
                    html = html + "<option value='" + data[i].colaborador_id + "'>" + data[i].nome + "</option>";
                });
                $("#colaborador_id").append(html);
                $('#colaborador_id').val($('#colaborador_id option').filter(function () {
                    return $(this).val() === $("#hiddencolaborador_id").val();
                }).val());
                $("#colaborador_id").trigger("chosen:updated");
            },
            error: function (data) {
                bootbox.alert('Erro ao buscar colaboradores');
            },
            cache: false,
            contentType: false,
            processData: false
        });
        return false;
    }
}

function mudarTipoExcecao(val) {
    if(val == 1){
        $("#percentualexcecao").prop('disabled', false).show();
        $("#valorexcecao").prop('disabled', true).hide();
        $("#percentualexcecaoapp").prop('disabled', false).show();
        $("#valorexcecaoapp").prop('disabled', true).hide();
    } else {
        $("#percentualexcecao").prop('disabled', true).hide();
        $("#valorexcecao").prop('disabled', false).show();
        $("#percentualexcecaoapp").prop('disabled', true).hide();
        $("#valorexcecaoapp").prop('disabled', false).show();
    }
}

function mudarTipo(val) {
    if(val == 1){
        $("#percentual").prop('disabled', false);
        $("#empresavalor").prop('disabled', true);
        $("#empresavalor").val("");
        $("#percentualapp").prop('disabled', false);
        $("#empresavalorapp").prop('disabled', true);
        $("#empresavalorapp").val("");
    } else {
        $("#percentual").prop('disabled', true);
        $("#empresavalor").prop('disabled', false);
        $("#percentual").val("");
        $("#percentualapp").prop('disabled', true);
        $("#empresavalorapp").prop('disabled', false);
        $("#percentualapp").val("");
    }
}
function carregarExcecoesErro() {
    tblExcecoesComissao.rows().clear();
    console.log('erro');
    if ( ! $("#excecoes").isEmpty() ) {
        console.log($("#excecoes").val());
        var excecoes = JSON.parse($("#excecoes").val());
        for (var i = 0; i < excecoes.length; i++) {
            tblExcecoesComissao.row.add([
                excecoes[i][0],
                excecoes[i][1],
                excecoes[i][2],
                excecoes[i][3],
                excecoes[i][4],
                excecoes[i][5],
            ]);
        }
    }

    tblExcecoesComissao.draw();
    if ( tblExcecoesComissao.data().any() ) {
        tblExcecoesComissao.rows().every(function () {
            var d = this.data();
            $("#segmento_id option").filter(function () {
                if ($(this).val() == d[0]) {
                    $(this).attr("disabled", true);
                }
            });
        });
        $("#segmento_id").trigger('chosen:updated');
    }
}

function confirmarReplicar() {
    bootbox.confirm({
        title: "Replicar cadastro?",
        message: "Deseja replicar essas informações para os demais setores e colaboradores?",
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
                // $("#replicar").prop('checked', true);
                let rep = '<input name="replicar" class="hidden" type="text" value="1" id="replicar">';
                $("form#fmCadastro").append(rep);
                $("form#fmCadastro").submit();
            }
        }
    });
}

function existsExceptionValor(){
    let achou = false;
    tblExcecoesComissao.rows().every(function () {
        var d = this.data();
        if(d[2]=='Repasse'){
            achou = true;
        }
    });
    return achou;
}

function setComissaoTonelagem(){
    let $radios = $('input:radio[name=tipocomissao]');
    $radios.filter('[value=1]').prop('checked', true);
    $radios.filter('[value=2]').attr('disabled', true);
    $radios = $('input:radio[name=tipoexcecao]');
    $radios.filter('[value=1]').prop('checked', true);
    $radios.filter('[value=2]').attr('disabled', true);
    $('#btnReplicar').attr('disabled', true);
    $('#btnReplicar').hide();
    mudarTipo(1);
    mudarTipoExcecao(1);
    $('#setor_id').val(-1);
    $('#setor_id option:not(:selected)').attr('disabled', true);
    $('#setor_id').trigger('chosen:updated');
    $('#condicaopagamento_id option').attr('disabled', true);
    $("#condicaopagamento_id").val('').trigger('chosen:updated');
    buscaColaboradoresPorSetor();
    tblExcecoesComissao.rows( function ( idx, data, node ) {
        return data[2] == 'Repasse';
    } )
    .remove()
    .draw();

}