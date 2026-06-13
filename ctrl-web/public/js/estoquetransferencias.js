$(document).ready(function () {
    $('#tbodyProdutosList').append($('#inputListaProdutosTable').val());

    $("#addProdutos").addClass('disabled');

    $("#produto").val(function () {
        $("#setor_id").val($("#origemsetor_id").val());
        buscarProdutosAjax();
    });

    if ($("#setororigem_id").val() === '' || $("#setordestino_id").val() === '' || $("#produto").val() === '' || $("#quantidade").val() === '') {
        $("#addProdutos").addClass('disabled');
    } else {
        $("#addProdutos").removeClass('disabled');
    }

    $("#quantidade").on('keypress', function () {
        if ($("#quantidade").val().length > 0) {
            $("#addProdutos").removeClass('disabled');
        } else {
            $("#addProdutos").removeClass('disabled');
        }
    });
    $("#produto").change(function () {
        if ($("#produto").val() === '' || $("#quantidade").val() === '') {
            $("#addProdutos").addClass('disabled');
        } else {
            $("#addProdutos").removeClass('disabled');
        }
    });
    $("#quantidade").blur(function () {
        if ($("#setor").val() === '' || $("#quantidade").val() === '' || $("#quantidade").val() < 0) {
            $("#addProdutos").addClass('disabled');
            $("#quantidade").addClass('hasError');
        } else if ($("#produto").val() === '') {
            $("#addProdutos").addClass('disabled');
        } else {
            $("#addProdutos").removeClass('disabled');
            $("#quantidade").removeClass('hasError');
        }
    });
    if ($("#setor").val() === '' || $("#quantidade").val() === '' || $("#produto").val() === '' || $("#quantidade").val() < 0) {
        $("#addProdutos").addClass('disabled');
    } else {
        $("#addProdutos").removeClass('disabled');
    }

    tblProdutosTransferencia = $('#tblProdutosTransferencia').DataTable({
        "language": {"url": urlDataTable},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": true,
        "bInfo": false,
        "bAutoWidth": false
    });


    $("#tblProdutosTransferencia").on('click', 'button', function () {
        var trElem = $(this).closest("tr");// grabs the button's parent tr element
        var cod = $(trElem).children("td")[0]; //takes the first td which would have your Id
        if ($(cod).text() != "") {
            if ($(this).context.id === 'btnRemoverProduto') {
                tblProdutosTransferencia.row($(this).parents('tr'))
                .remove()
                .draw();
            }
        }
    });

    $("#fmCadastro").on('submit', function () {
        var produtos = [];
        tblProdutosTransferencia.rows().every(function () {
            var d = this.data();
            produtos.push(d);
        });
        $("#produtos").val(JSON.stringify(produtos));
    });
});

var produtos = null;

var button = '<button class="btn btn-nw-registro btn-xs" id="btnRemoverProduto" type="button">Remover</button>';

function addProdutosClick() {
    var prod = $("#produto option:selected");
    var qdeProdutos = parseInt($("#quantidade").val());
    var found = false;
    if ( produtos.length > 0 ) {
        var retornavel = produtos.where('id',prod.val()).where('retornavel_id','!=','null').first();
    } else {
        $("#origemsetor_id").trigger('change');
        var retornavel = produtos.where('id',prod.val()).where('retornavel_id','!=','null').first();
    }
    tblProdutosTransferencia.rows().every(function () {
        var d = this.data();
        if ( d[0] == prod.val() ) {
            d[2] += qdeProdutos;
            found = true;
        }
        if ( retornavel.hasOwnProperty("retornavel_id") && retornavel.retornavel_id != null ) {
            if ( found && d[0] == retornavel.retornavel_id ) {
                d[2] += qdeProdutos;
            }
        }
        this.invalidate();
    });
    if ( !found ) {
        tblProdutosTransferencia.row.add([
            prod.val(),
            prod.text(),
            qdeProdutos,
            button
        ]);
        if ( retornavel.hasOwnProperty("retornavel_id") && retornavel.retornavel_id != null ) {
            tblProdutosTransferencia.row.add([
                retornavel.retornavel_id,
                retornavel.retornavel,
                qdeProdutos,
                button
            ]);
        }
    }
    tblProdutosTransferencia.draw();
}

function carregarProdutosErro() {
    tblProdutosTransferencia.clear();
    var produtos = JSON.parse($('#produtos').val());
    for (var i = 0; i < produtos.length; i++) {
        tblProdutosTransferencia.row.add([
            produtos[i][0],
            produtos[i][1],
            produtos[i][2],
            produtos[i][3]
            ]).draw(false);
    }
}

function atualizarProdutos() {
    $("#setor_id").val($("#origemsetor_id").val());
    buscarProdutosAjax();
}

function buscarProdutosAjax() {
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
        var html = "<option value=''>Selecione</option>";
        for (var i = 0; i < data.length; i++) {
            html = html + "<option value='" + data[i].id + "'>" + data[i].descricao + "</option>";
        }
        $("#produto").append(html);
        $("#produto").trigger("chosen:updated");
        produtos = data;
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