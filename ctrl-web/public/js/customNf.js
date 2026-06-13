
$(document).ready(function () {
    $('.btnNovoCadastro').on('click', function () {
        $('#myModalLabelCadastro').text('Novo Registro');
        $('#fmCadastroAjax')[0].reset();
        $('#fmCadastroAjax :input').prop('disabled', false);
        $('#fmCadastroAjax :submit').show();
    });
    $('#tblCadastro').on('click', 'button', function () {
        $('#tblCadastro').attr('btnClick', 'true');
        $('#fmCadastroAjax :submit').show();
        var trElem = $(this).closest("tr"); // grabs the button's parent tr element
        var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
        var id = $(trElem).children("td")[0]; //takes the first td which would have your Id
        var codigo = $(trElem).children("td")[1];
        var descricao = $(trElem).children("td")[2];
        var tag = $(trElem).children("td")[3];
        if ($(firstTd).text() !== "") {
            if ($(this).context.id === 'btnEditar') {
                $('#fmCadastroAjax :input').prop('disabled', false);
                $('#myModalLabelCadastro').text('Editar Registro');
                $('#id').val($(id).text());
                $('#codigo').val($(codigo).text());
                $('#descricao').val($(descricao).text());
                $('#tag').val($(tag).text());
                $('#myModal').modal('show');
            } else {
                $('#fmCadastroDel :input').prop('disabled', true);
                $('#myModalLabel').text('Remover Registro');
                $('#id_del').val($(id).text());
                $('#codigo_del').val($(codigo).text());
                $('#descricao_del').val($(descricao).text());
                $('#tag_del').val($(tag).text());
                $('#myModalDel').modal('show');
            }
            $('#fmCadastroDel :button').prop('disabled', false);
            $('#fmCadastroDel :submit').prop('disabled', false);
        }
    });
    $('#tblCadastro').on('click', 'tr', function () {
        var trElem = $(this).closest("tr"); // grabs the button's parent tr element
        var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
        var codigo = $(trElem).children("td")[1];
        var descricao = $(trElem).children("td")[2];
        var tag = $(trElem).children("td")[3];
        var url = $('#tblCadastro').attr("url");
        var btnClick = $('#tblCadastro').attr("btnClick");
        var id = parseInt($(firstTd).text());
        if (btnClick === "false" && url === "" && !isNaN(id)) {
            $('#fmCadastroAjax :input').prop('disabled', true);
            $('#fmCadastroAjax :button').prop('disabled', false);
            $('#fmCadastroAjax :submit').prop('disabled', true);
            $('#fmCadastroAjax :submit').hide();
            $('#myModalLabelCadastro').text('Visualizar Registro');
            $('#id').val($(id).text());
            $('#codigo').val($(codigo).text());
            $('#descricao').val($(descricao).text());
            $('#tag').val($(tag).text());
            $('#myModal').modal('show');
        }
    });
    $(".modal").on('hide.bs.modal', function () {
        $('#tblCadastro').attr('btnClick', 'false');
    });
});