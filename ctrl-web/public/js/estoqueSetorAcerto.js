$(document).ready(function () {
    $("#quantidadeatual").val($("#quantidadeantiga").val());
    $("#fmCadastro").on('submit', function () {
        $("#quantidadeantiga").val($("#quantidadeatual").val());
    });
});

$("#btn_gravar").on('click', function (e) {
    e.preventDefault();
});

function buscarInfoProduto(url) {
    var produto_id = $("#produto_id").val();
    var setor_id = $("#setor_id").val();
    url = url.replace(':produto_id', produto_id);
    url = url.replace(':setor_id', setor_id);
    if (setor_id === '' || produto_id === '') {
        $("#quantidadeatual").val(0);
        return false;
    } else {
        if (typeof setor_id !== 'undefined' && typeof produto_id !== 'undefined') {
            $.ajax({headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                },
                type: "GET",
                url: url,
                success: function (data) {
                    if (data.length == 0) {
                        $("#quantidadeatual").val(0);
                    }
                    for (var i = 0; i < data.length; i++) {
                        $("#quantidadeatual").val(data[i].quantidade);
                    }
                },
                error: function (data) {
                    bootbox.alert('Erro ao buscar informações sobre o produto');
                },
                cache: false,
                contentType: false,
                processData: false
            });
            return false;
        }
    }
}

const callbackSenha = function() {
    $("#fmCadastro").submit();
};
