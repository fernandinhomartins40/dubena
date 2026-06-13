$(document).ready(function () {
//    $("#novaSenha").on('submit', function () {
//        var id = $("#config_id").val();
//        var senhanova = $("#senhanova").val();
//        var confirmarsenha = $("#confirmarsenha").val();
//        var igualdade = igualdadeSenha(senhanova, confirmarsenha, id);
//        if (igualdade === true) {
//            var urlchange = 'empresaconfig/changepassword';
//            var formData = new FormData($(this)[0]);
//            $.ajax({
//                type: 'POST',
//                url: urlchange,
//                data: formData,
//                
//                error: function (data) {
//                    bootbox.alert("Ops! Ocorreu um erro ao cadastrar a senha." + data);
//                },
//                cache: false,
//                contentType: false,
//                processData: false
//            });
//        } else if (isEmpty(senhanova)) {
//            bootbox.alert('Favor inserir uma nova senha.');
//        } else {
//            bootbox.alert('Senha nova e confirmar senha devem ser iguais.');
//            $("#confirmarsenha").val('');
//            $("#senhanova").val('');
//        }
//        return false;
//    });
    
    $("#confirmarsenha").on('keyup', function () {
        var senhanova = $("#senhanova").val();
        var confirmarsenha = $("#confirmarsenha").val();
        igualdadeSenha(senhanova,confirmarsenha);
    });
});

function igualdadeSenha(senhanova, confirmarsenha, id) {
    if (isEmpty(id)) {
        return false;
    } else if (senhanova !== confirmarsenha || confirmarsenha === '') {
        return false;
    } else {
        return true;
    }
}