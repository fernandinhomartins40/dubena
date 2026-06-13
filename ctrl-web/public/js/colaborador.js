function addFone() {
    if (!isInt($('#telefonetipo_id').val())) {
        bootbox.alert('Preencha o tipo de telefone.');
        return;
    }
    if ($('#telefone').val().trim() == '') {
        bootbox.alert('Preencha o telefone.');
        return;
    }
    tblFone.row.add([
        $('#telefonetipo_id').val(),
        $('#telefonetipo_id option:selected').text(),
        $('#telefone').val(),
        "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverTelefone' >Remover</button>"
    ]).draw(false);
    $('#telefone').val('');
}
function addFamilia() {
    if (!isInt($('#parentesco_id').val())) {
        bootbox.alert('Preencha o parentesco do colaborador.');
        return;
    }
    if ($('#familianome').val().trim() == '') {
        bootbox.alert('Preencha o nome do parente.');
        return;
    }
    if ($('#familiadatanascimento').val().trim() == '') {
        bootbox.alert('Preencha a data de nascimento do parente.');
        return;
    }
    tblFamilia.row.add([
        $('#parentesco_id').val(),
        $('#parentesco_id option:selected').text(),
        $('#familianome').val(),
        $('#familiadatanascimento').val(),
        "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverFamilia' >Remover</button>"
    ]).draw(false);
    $('#familianome').val('');
}

function addFerias() {
    if ($('#feriasdatainicio').val().trim() == '') {
        bootbox.alert('Preencha a data de início das férias.');
        return;
    }
    if (!isInt($('#feriasdias').val())) {
        bootbox.alert('Preencha os dias das férias.');
        return;
    }
    tblFerias.row.add([
        $('#feriasdatainicio').val(),
        $('#feriasdias').val(),
        $('#feriasgozada').prop('checked') == 1 ? 'Sim' : 'Não',
        "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverFerias' >Remover</button>"
    ]).draw(false);
    $('#feriasdias').val('');
    $('#feriasdatainicio').val('');
    $('#feriasgozada').prop("checked", false);
}

function addExame() {
    if (!isInt($('#tipoexame_id').val())) {
        bootbox.alert('Preencha o tipo de exame.');
        return;
    }
    if ($('#dataexame').val().trim() == '') {
        bootbox.alert('Preencha a data do exame.');
        return;
    }
    if ($('#vencimentoexame').val().trim() == '') {
        bootbox.alert('Preencha o vencimento do exame.');
        return;
    }

    tblExames.row.add([
        $('#tipoexame_id').val(),
        $('#tipoexame_id option:selected').text(),
        $('#dataexame').val(),
        $('#vencimentoexame').val(),
        $('#alerta').prop('checked') == 1 ? 'Sim' : 'Não',
        "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverExame' >Remover</button>"
    ]).draw(false);

    $('#dataexame').val('');
    $('#vencimentoexame').val('');
    $('#alerta').prop("checked", false);
}
var confirm;
var t;
var root = '{{url("/")}}';
window.onload = function () {
    document.getElementById('foto').onchange = function (evt) {
        var tgt = evt.target || window.event.srcElement,
                files = tgt.files;
        // FileReader support
        if (FileReader && files && files.length) {
            var fr = new FileReader();
            fr.onload = function () {
                document.getElementById('fotoImg').src = fr.result;
            };
            fr.readAsDataURL(files[0]);
        }
    };
};//]]>;
$('.modal-wide').on('show.bs.modal', function () {
    var height = $(window).height() - 200;
    $(this).find('.modal-body').css('max-height', height);
});
$(".delete").on("submit", function () {
    return confirm("Quer remover o registro atual?");
});
jQuery(document).ready(function ($) {
    mudarTipoPessoa();
    $('#popup_capture').on('hidden.bs.modal', function () {
        stopCapture();
    })
    $(".modal-wide").on("show.bs.modal", function () {
        var height = $(window).height() - 200;
        $(this).find(".modal-body").css("max-height", height);
    });
    tblFone = $('#tblTelefones').DataTable({
        "language": {"url": "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}"},
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
    tblFamilia = $('#tblColaboradorfamilias').DataTable({
        "language": {"url": "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}"},
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
    tblFerias = $('#tblColaboradorferias').DataTable({
        "language": {"url": "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}"},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": true,
        "bInfo": false,
        "bAutoWidth": false,
        "columnDefs": [{
                "targets": [0],
                "visible": true
            }]
    });
    tblExames = $('#tblColaboradorexames').DataTable({
        "language": {"url": "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}"},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": true,
        "bInfo": false,
        "bAutoWidth": false,
        "columnDefs": [{
                "targets": [0],
                "visible": false
            }]
    });
    $("#fmCadastro").on("submit", function () {
        var fones = [];
        tblFone.rows().every(function () {
            var d = this.data();
            fones.push(d);
        });
        $('#telefones').val(JSON.stringify(fones));
        var familias = [];
        tblFamilia.rows().every(function () {
            var d = this.data();
            familias.push(d);
        });
        $('#colaboradorfamilias').val(JSON.stringify(familias));
        var ferias = [];
        tblFerias.rows().every(function () {
            var d = this.data();
            ferias.push(d);
        });
        $('#colaboradorferias').val(JSON.stringify(ferias));
        var exames = [];
        tblExames.rows().every(function () {
            var d = this.data();
            exames.push(d);
        });
        $('#colaboradorexames').val(JSON.stringify(exames));
    });


    $('#tblTelefones').on('click', 'button', function () {
        var trElem = $(this).closest("tr");// grabs the button's parent tr element
        var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
        if ($(firstTd).text() != "") {
            if ($(this).context.id == 'btnRemoverTelefone') {
                tblFone
                        .row($(this).parents('tr'))
                        .remove()
                        .draw();
            }
        }
        ;
    });

    $('#tblColaboradorfamilias').on('click', 'button', function () {
        var trElem = $(this).closest("tr");// grabs the button's parent tr element
        var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
        if ($(firstTd).text() != "") {
            if ($(this).context.id == 'btnRemoverFamilia') {
                tblFamilia
                        .row($(this).parents('tr'))
                        .remove()
                        .draw();
            }
        }
        ;
    });
    $('#tblColaboradorferias').on('click', 'button', function () {
        var trElem = $(this).closest("tr");// grabs the button's parent tr element
        var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
        alert();
        if ($(firstTd).text() != "") {
            if ($(this).context.id == 'btnRemoverFerias') {
                tblFerias
                        .row($(this).parents('tr'))
                        .remove()
                        .draw();
            }
        }
        ;
    });
    $('#tblColaboradorexames td').on('click', 'button', function () {
        var trElem = $(this).closest("tr");// grabs the button's parent tr element
        var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
        if ($(firstTd).text() != "") {
            if ($(this).context.id == 'btnRemoverExame') {
                tblExames
                        .row($(this).parents('tr'))
                        .remove()
                        .draw();
            }
        }
        ;
    });
});
