
var botao = '<button class="btn btn-xs btn-nw-registro" id="btnRemover" data-toggle="tooltip" data-trigger="hover" data-placement="bottom" title="Remover Empresa">Remover</button>';

$( document ).ready( function () {
    tblItems = $("#tblInventarioItems").DataTable({
        "language": {
            "url": urlDataTable
        },
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": false,
        "bInfo": false,
        "bAutoWidth": false,
        "destroy": true,
        "sScrollY": "270",
        "aoColumnDefs": [{
            "bVisible": false,
            "aTargets": [0]
        },{
            "width": "10%",
            "aTargets":[5]
        }]
    });
});

$("#addProduto").click( function () {
    var validado = true;
    var produto_id = $("#produto_id").val();
    var produto = $("#produto_id option:selected").text();
    var valor = $("#valorunitario").moneyToFloat();
    var quantidade = parseInt($("#quantidade").val());
    if( $("#produto_id").isEmpty() ) validado = false;
    if( $("#valorunitario").isEmpty() || valor == '0' ) validado = false;
    if( $("#quantidade").isEmpty() ) validado = false;

    if( validado ) addProduto( produto_id, produto, valor, quantidade );
});

$("#tblInventarioItems tbody").on( 'click','#btnRemover', function ( e ) {
    e.stopPropagation();
    var parent = $(this).parents('tr');
    tblItems.row( parent ).remove().draw();
});

$("#btnGravar").click( function ( e ) {
    validarCampos( e );
});

function addProduto( produto_id, produto, valor, quantidade ) {
    var total = (valor * quantidade);
    var achou = false;

    tblItems.rows().every( function () {
        var id = this.data()[0];
        if( id === produto_id ) {
            achou = true;
            this.data()[2] = "R$ " + formataDecimal(valor,2);
            this.data()[3] = quantidade;
            this.data()[4] = "R$ " + formataDecimal(total,2);
            this.invalidate();
            return;
        }
    });
    if( !achou ) {
        tblItems.row.add([
            produto_id,
            produto,
            "R$ " + formataDecimal(valor,2),
            quantidade,
            "R$ " + formataDecimal(total,2),
            botao
        ]).draw();
    }
    clearCampos();
}

function clearCampos() {
    $("#produto_id").val('').trigger('chosen:updated');
    $("#valorunitario").val('');
    $("#quantidade").val('');
}

function validarCampos( e ) {
    var validado = true;

    if ( $("#datainventario").isEmpty() ) validado = false;
    if ( $("#mesentrega").isEmpty() ) validado = false;
    if ( !tblItems.rows().any() ) validado = false;

    if ( !validado ) {
        e.preventDefault();
        bootbox.alert("Por favor, preencha as datas e a tabela.");
        return false;
    }
    salvarTable();
}

function salvarTable () {
    $("#produtos").val('');
    var informacoes = [];
    var produtos = [];
    var total = 0;

    tblItems.rows().every( function () {
        var d = this.data();
        produtos.push({
            "produto_id":d[0],
            "produto":d[1],
            "valorunitario":d[2].moneyToFloat(),
            "quantidade":d[3]
        });
        total += d[4].moneyToFloat();
    });
    informacoes = {
        "total":total,
        "produtos":produtos
    };
    $("#produtos").val(JSON.stringify(informacoes));
}

function errorsFix() {
    var produtos = $("#produtos").val();
    if( !produtos.isEmpty() ) {
        produtos = JSON.parse(produtos);
        delete produtos.total;
        tblItems.clear();
        prod = produtos.produtos;
        for( var i = 0; i < prod.length; i++ ) {
            var total = prod[i].valorunitario * prod[i].quantidade;
            tblItems.row.add([
                prod[i].produto_id,
                prod[i].produto,
                "R$ " + formataDecimal(prod[i].valorunitario,2),
                prod[i].quantidade,
                "R$ " + formataDecimal(total,2),
                botao
            ]);
        }
        tblItems.draw();
    }
}