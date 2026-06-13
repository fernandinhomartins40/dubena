$(document).ready(function () {
    $("#apartir").blur(function () {
        checarQuantidade();
    });
});

function checarQuantidade() {
    let quantidade = parseInt($("#apartir").val());
    if (!isNaN(quantidade)) {
        if (quantidade > 30) {
            if (typeof entrou === 'undefined') {
                bootbox.alert('O sistema realiza impresão de no maximo 30 etiquetas por vez');
                entrou = true;
                $("#apartir").val('');
            }
            $("#apartir").val('');
        }
    }
}


