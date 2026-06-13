
$(document).ready(function () {
    $(".floatQtde").maskMoney({decimal: ',', thousands:'.', symbolStay: true, allowNegative: false, precision: 2, precisionBefore: 4});
	$(".floatQtde").attr('maxlength', 8);
    $('#tbodyProdutosList').append($('#inputListaProdutosTable').val());

    $("#produto").val(function () {
        buscarProdutosAjax();
    });

    $("#customedio").maskMoney({prefix: 'R$ ', thousands: '.', decimal: ',', symbolStay: true});

    $("#addProdutos").addClass('disabled');

    if ($("#setor_id").val() === '' || $("#produto").val() === '' || $("#quantidade").val() === '') {
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
        if ($("#produto").val() === '') {
            $("#addProdutos").addClass('disabled');
        } else {
            $("#addProdutos").removeClass('disabled');
        }
    });
    $("#quantidade").blur(function () {
        if ($("#setor_id").val() === '' || $("#quantidade").val() === '' || $("#quantidade").val() < 0) {
            $("#addProdutos").addClass('disabled');
            $("#quantidade").addClass('hasError');
        } else if ($("#produto").val() === '') {
            $("#addProdutos").addClass('disabled');
        } else {
            $("#addProdutos").removeClass('disabled');
            $("#quantidade").removeClass('hasError');
        }
    });

    tblProdutosRequisicao = $('#tblProdutosRequisicao').DataTable({
        "language": {"url": urlDataTable},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": true,
        "bInfo": false,
        "bAutoWidth": false
    });


    $("#tblProdutosRequisicao").on('click', 'button', function () {
        var trElem = $(this).closest("tr");// grabs the button's parent tr element
        var cod = $(trElem).children("td")[0]; //takes the first td which would have your Id
        if ($(cod).text() != "") {
            if ($(this).context.id === 'btnRemoverProduto') {
                tblProdutosRequisicao.row($(this).parents('tr'))
                .remove()
                .draw();
            }
        }
    });

    $("#fmCadastro").on('submit', function () {
        var produtos = [];
        tblProdutosRequisicao.rows().every(function () {
            var d = this.data();
            produtos.push(d);
        });
        $("#produtos").val(JSON.stringify(produtos));
    });
});

$("#buttonBack").click( function ( e ) {
    var href = $(this).attr('href');
    var url  = $("#filtro_url").val();
    if (! isEmpty(url) && ! href.includes('filter') ) {
        href += url.substr(0, 1) == "/" ? url : `/${url}`;
    }
    $(this).attr('href', href);
});

function addProdutosClick() {
    var produto_id = $("#produto").val();
    var qdeProdutos = parseFloat($("#quantidade").val().replace(".", "").replace(",", "."));

    tblProdutosRequisicao.rows().every(function () {
        var d = this.data();
        if(d[0] == produto_id){
            qdeProdutos += parseFloat(String(d[4]).replace(".", "").replace(",", "."));
            this.remove();
        }
    });
    // qdeProdutos = qdeProdutos.toFixed(2).replace('.',',');
    tblProdutosRequisicao.row.add([
        $("#produto").val(),
        $("#produto option:selected").text(),
        $("#setor_id option:selected").text(),
        $("#setor_id").val(),
        qdeProdutos.toLocaleString('pt-BR'),
        $("#customedio").val(),
        '<button class="btn btn-nw-registro btn-xs" id="btnRemoverProduto" type="button">Remover</button>'
    ]).draw(false);
}

function carregarProdutosErro() {
//    tblProdutosTransferencia = $('#tblProdutosTransferencia').DataTable({
//        "language": {"url": urlDataTable},
//        "processing": false,
//        "bPaginate": false,
//        "bLengthChange": false,
//        "bFilter": false,
//        "bSort": true,
//        "bInfo": false,
//        "bAutoWidth": false
//    });
tblProdutosRequisicao.clear();
var produtos = JSON.parse($('#produtos').val());
for (var i = 0; i < produtos.length; i++) {
    tblProdutosRequisicao.row.add([
        produtos[i][0],
        produtos[i][1],
        produtos[i][2],
        produtos[i][3],
        produtos[i][4],
        produtos[i][5],
        produtos[i][6]
        ]).draw(false);
}
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

function atualizarProdutos() {
    buscarProdutosAjax();
}