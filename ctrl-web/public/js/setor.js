$(document).ready(function () {
    if ($("#inputColaboradoresList").val().indexOf("||") !== -1) {
    } else {
        $("#tbodyColaboradoresList").append($("#inputColaboradoresList").val());
    }
    $(".pontoreferencia").hide();
    $("#addColaborador").on('click', function () {
        var colaborador = $('#colaboradores option:selected').text();
        var id = $('#colaboradores').val();
        var button = "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover" + id + "'>Remover</button>";

        if ($("#inputColaboradoresList").val().indexOf(id) !== -1) {
            bootbox.alert('Colaborador já adicionado a esse setor!');
        } else {
            listId = $("#inputColaboradoresListId").val($("#inputColaboradoresListId").val() + id + '||');
            colaboradoresListTable = "<tr id='tr" + id + "'><td>" + id + "</td><td>" + colaborador + "</td><td>" + button + "</td></tr>"
            $("#inputColaboradoresList").val($("#inputColaboradoresList").val() + colaboradoresListTable);
            $("#tbodyColaboradoresList").append(colaboradoresListTable);
        }
    });

    $("#tblListaColaboradores").on('click', 'button', function () {
        id = $(this).attr('id');
        id = id.replace('btnRemover', 'tr');
        $("#" + id).remove();
        $("#inputColaboradoresList").val('');
        $("#inputColaboradoresList").val($("#tbodyColaboradoresList").html());
        id = id.replace('tr', '');
        $("#inputColaboradoresListId").val($("#inputColaboradoresListId").val().replace(id + "||", ''));
    });
});