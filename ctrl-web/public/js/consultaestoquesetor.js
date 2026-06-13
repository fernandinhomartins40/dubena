$(document).ready(function () {

    $("#btnZeraFiltro").on('click', function () {
        urlBuscaProdutosSetor = urlBuscaProdutosSetor.replace(':setor', '&');
        urlBuscaProdutosSetor = urlBuscaProdutosSetor.replace(':produto', '&');
        urlBuscaProdutosSetor = urlBuscaProdutosSetor.replace(':estoqueZerado', '');
        window.location = urlBuscaProdutosSetor;
    });
    $("#btnFecharEstoque").on('click', function () {
        $("#myModalLabelCadastro").text('Selecione a Data de Fechamento');
        urlOperacaoEstoque = root + '/consultaestoquesetor/fecharEstoque';
    });

    $("#btnReabrirEstoque").on('click', function () {
        $("#myModalAberturaLabelCadastro").text('Selecione a Data de Reabertura');
        urlOperacaoEstoque = root + '/consultaestoquesetor/abrirEstoque';
    });
    $("#btnBusca").on('click', function () {
        urlBuscaProdutosSetor = urlBuscaProdutosSetor.replace(':setor', $("#setor_id").val() + '&');
        urlBuscaProdutosSetor = urlBuscaProdutosSetor.replace(':produto', $("#produto").val() + '&');
        if ($('#estoqueZerado').is(':checked')) {
            urlBuscaProdutosSetor = urlBuscaProdutosSetor.replace(':estoqueZerado', 'true');
        } else {
            urlBuscaProdutosSetor = urlBuscaProdutosSetor.replace(':estoqueZerado', 'false');
        }
        window.location = urlBuscaProdutosSetor;
    });
    $("#fmFechamentoAjax").submit(function () {
        var formData = new FormData($(this)[0]);
        operacaoEstoqueAjax(formData);
        return false;
    });
    $("#fmAberturaAjax").submit(function () {
        $("#modalSenha").modal('show');
        $("#myModalAbertura").modal('hide');
        var formData = new FormData($(this)[0]);
        callbackSenha = function () {
            $("#myModalAbertura").modal('show');
            operacaoEstoqueAjax(formData);
        }
        return false;
    });
});
function operacaoEstoqueAjax(formData) {
    $.ajax({
        type: "POST",
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            url: urlOperacaoEstoque,
            data: formData,
            async: false,
            success: function (data) {
                if (data.substr(0, 3) == 'OK|') {
                    var url = urlIndex;
                    var dialog = bootbox.dialog({
                        title: 'Operação realizada com sucesso!',
                        message: '<p><i class="fa fa-spin fa-spinner"></i> Aguarde, você será redirecionado..</p>'
                    });
                    dialog.init(function () {
                        window.setTimeout("location.href='" + url + "'", 1500);
                    });
                } else {
                    bootbox.alert('Houve um problema ao efetuar operação: ' + data);
                }
            },
            error: function (data) {
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
                    if (msg != '')
                        bootbox.alert('Erro ao efetuar operação: <br />' + msg);
                    else
                        bootbox.alert('Erro ao efetuar operação: ' + responseText);
                    //bootbox.alert('Erro ao gravar: ' + data.responseJSON.descricao);
                } else if (typeof (data) == 'string') {
                    bootbox.alert('Erro ao efetuar operação: ' + data);
                } else {
                    bootbox.alert('Houve um erro desconhecido ao efetuar operação!');
                }
            },
            cache: false,
            contentType: false,
            processData: false
        });
    return false;
}
function buscarProdutosAjax() {
//    alert('d');
var urlProduto = urlBuscaProdutosAjax;
var id = $("#setor_id").val();
urlProduto = urlProduto.replace(':id', id);
$("#produto").empty();
$.ajax({headers: {
    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
},
type: "GET",
url: urlProduto,
success: function (data) {
    html = "<option value=''>Selecione</option>";
    for (var i = 0; i < data.length; i++) {
        html = html + "<option value='" + data[i].id + "'>" + data[i].descricao + "</option>";
    }
    $("#produto").append(html);
    $("#produto").trigger("chosen:updated");
},
error: function (data) {
    bootbox.alert('Erro ao buscar os produtos');
},
cache: false,
contentType: false,
processData: false
});
return false;
}
