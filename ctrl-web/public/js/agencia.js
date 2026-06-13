$('.modal-wide').on('show.bs.modal', function () {
    var height = $(window).height() - 200;
    $(this).find('.modal-body').css('max-height', height);
});
jQuery(document).ready(function ($) {

    tblFone = $('#tblTelefones').DataTable({
        "language": {"url": urlDataTable},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": true,
        "bInfo": false,
        "bAutoWidth": false,
        "columnDefs": [
            {
                "targets": [0],
                "visible": false
            }
        ]
    });
    $("#fmCadastro").on("submit", function () {
        var fones = [];
        tblFone.rows().every(function () {
            var d = this.data();
            fones.push(d);
        });
        $('#telefones').val(JSON.stringify(fones));
        var contatos = [];
        tblCont.rows().every(function () {
            var d = this.data();
            contatos.push(d);
        });
        $('#contatos').val(JSON.stringify(contatos));
    });

    tblFone = $('#tblTelefones').DataTable().on('click', 'button', function () {
        $('#tblTelefones').DataTable()
                .row($(this).parents('tr'))
                .remove()
                .draw();
    });
    $('#responsaveltipo_id').val('');



});

function addFone() {
    if (!isInt($('#telefonetipo_id').val())) {
        bootbox.alert('Preencha o tipo de telefone.');
        return;
    }
    if ($('#telefone').val().trim() == '') {
        bootbox.alert('Preencha o telefone.');
        return;
    }
    var wpp = 'Não';

    if ($("#whatsapp").prop('checked') === true) {
        wpp = 'Sim';
    }
    tblFone.row.add([
        $('#telefonetipo_id').val(),
        $('#telefonetipo_id option:selected').text(),
        $('#telefone').val(),
        "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverTelefone'>Remover</button>"
    ]).draw(false);
    $('#telefone').val('');
}
function carregarTelefonesErro() {
    tblFone.clear();
    contatos = JSON.parse($('#telefones').val());
    for (i = 0; i < contatos.length; i++) {
        tblFone.row.add([
            contatos[i][0],
            contatos[i][1],
            contatos[i][2],
            contatos[i][3],
        ]).draw(false);
    }
    //console.log(contatos);
}
function errorMsg(msg, error) {
    errorElement.innerHTML += '<p>' + msg + '</p>';
    if (typeof error !== 'undefined') {
        console.error(error);
    }
}