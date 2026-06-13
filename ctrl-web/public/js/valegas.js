$(document).ready(function () {
    tblSequencia = $("#tableprevenda").DataTable({
        "language": {
            "url": urlDataTable
        },
        "processing": false,
        "bPaginate": true,
        "bLengthChange": false,
        "bFilter": true,
        "bSort": true,
        "bInfo": false,
        "bAutoWidth": false,
        "order": [
            [1, 'asc']
        ],
        "aoColumnDefs": [
            {"bVisible": false, "aTargets": [0]}
        ]
    });

    $("#prevenda").on('click', function () {
        prevendadisable();
    });

    if ( errorsany ) {
        prevendadisable();
        checarPrevenda();
        if ( ! $("#valoresfechar").isEmpty() ) {
            prevendaMudar();
            $("#quantidade").prop('disabled', true);
            $("#produto_id").prop('disabled', true).trigger('chosen:updated');
        }
    }

    $("#fmCadastro").on('submit', function () {
        var val = $("#valortotal").val();
        var produto = $("#produto_id").val();
        var quantidade = $("#quantidade").val();
        var total = parseDinheiro(val, 2);
        $("#produto_id_hd").val(produto);
        $("#quantidade_hd").val(quantidade);
        if (!isNaN(total)) {
            $("#valortotal_hd").val(total);
        }
    });

    $("#fmImpressa").on('submit',function(){
        $("#imprimir_modal").modal('toggle');
    });

    $("#seqinicio").keyup(function () {
        tblSequencia.draw();
    });
    $("#seqfim").keyup(function () {
        tblSequencia.draw();
    });

    $("#fecharprevenda").click(function () {
        checarPrevenda();
    });
    $(".btnCancelar").on('click', function () {
        let id = $(this).attr('id');
        $("#urlRedirect").attr('url', root + '/vendavalegas/destroy/' + id);
    });

    checarPrevenda();
    parcelasShow();
    initSelectize();
});

var inputs = ['valorunitario', 'condicaopagamento_id', 'fecharprevenda', 'btnCalcularParcelas'];

$("#valorunitario").on('keyup', function () {
    var valunitario = $("#valorunitario").val();
    var valuni = parseDinheiro(valunitario, 2);
    $("#valortotal").val(valunitario);
    calcularValorTotal(valuni);
});

$("#quantidade").on('keyup', function () {
    var valunitario = $("#valorunitario").val();
    var valuni = parseDinheiro(valunitario, 2);
    calcularValorTotal(valuni);
});

$("#valorunitario").on('focusin', function () {
    var valunitario = $("#valorunitario").val();
    var valuni = parseDinheiro(valunitario, 2);
    $("#valortotal").val(valunitario);
    calcularValorTotal(valuni);
});


$("#btnCalcularParcelas").click(function () {
    tblparc.clear();
    var parc = $("#parcelas_financeiro").val();
    var cond = $("#condicaopagamento_id").val();
    if (cond !== "") {
        if (parc !== "") {
            calcularParcelas("valortotal", "datavenda", "parcelas_financeiro");
        } else {
            calcularParcelas("valortotal", "datavenda");
        }
    }
});

$("#condicaopagamento_id").change(function () {
    buscarParcelasAjax("datavenda", "datavenc");
    var datavenda = $("#datavenda").val();
    if ($(this).val() === '') {
        $("#datavenc").val(datavenda);
    }
});

$("#tableprevenda tbody").on('click','tr',function(){
    var row = $(this);
    var tblsequencia = tblSequencia;
    marcarLinha(tblsequencia,row);
});

$("#btnfecharprevenda").click(function(e){
    var tblsequencia = tblSequencia;
    fecharPrevendas(tblsequencia,e);
});

$("#prevenda_modal").on('hide.bs.modal',function(){
    tblSequencia.clear().draw();
    $("#cliente_id_md").val('').trigger('chosen:updated');
});

$.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
        if (settings.sTableId === "tableprevenda") {
            var seqinicio = $("#seqinicio").val();
            var seqfim = $("#seqfim").val();
            var inicio = parseInt(seqinicio);
            var fim = parseInt(seqfim);
            var data = parseInt(data[1]) || 0;
            if ((isNaN(inicio) && isNaN(fim)) ||
                (isNaN(inicio) && data <= fim) ||
                (inicio <= data && isNaN(fim)) ||
                (inicio <= data && data <= fim)) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }
);

function marcarLinha(table,row){
    if (row.hasClass('linhaselecionada')) {
        row.removeClass('linhaselecionada');
    } else {
        row.addClass('linhaselecionada');
    }
}

function prevendadisable() {
    if ($("#prevenda").prop('checked') === true) {
        for (var i = 0; i < inputs.length; i++) {
            $("#" + inputs[i]).prop('disabled', true).trigger('chosen:updated');
        }
    } else {
        for (var i = 0; i < inputs.length; i++) {
            $("#" + inputs[i]).prop('disabled', false).trigger('chosen:updated');
        }
    }
}


function checarPrevenda() {
    if ($("#fecharprevenda").prop('checked') === false) {
        $("#btnprevenda").hide();
        $("#prevenda").prop('disabled', false);
    } else {
        $("#btnprevenda").show();
        $("#prevenda").prop('disabled', true);
    }
}

function calcularValorTotal(valorunitario) {
    var qnta = $("#quantidade").val();
    var quantidade = isNaN(parseInt(qnta)) ? 1 : parseInt(qnta);
    if (!isNaN(valorunitario)) {
        var valtotal = (quantidade * valorunitario).toFixed(2);
        var showtotal = ("R$ " + valtotal.replace('.', ','));
        $("#valortotal").val(showtotal);
        $("#valortotal_hd").val(valtotal);
    }
}


function checarCheckbox(tbl) {
    var table = tbl.table().node();
    var check = $('tbody input[type="checkbox"]', table);
    var checked = $("tbody input[type='checkbox']:checked", table);
    var check_all = $("thead input[name='select_all']", table).get(0);
    if (checked.length === 0) {
        if ('indeterminate' in check_all) {
            check_all.indeterminate = false;
        }
    } else if (checked.length === check.length) {
        check_all.checked = true;
        if ('indeterminate' in check_all) {
            check_all.indeterminate = false;
        }
    }
}

function fecharPrevendas(table,e){
    var informacoes = grabData(table,e);
    var checagem = checarProdutos(informacoes,table);
    if ( checagem ) {
        table.rows('.linhaselecionada').nodes().to$().removeClass('linhaselecionada');
        $("#valoresfechar").val(JSON.stringify(informacoes));
        $('#prevenda_modal').modal('toggle');
        prevendaMudar();
    }
}

function grabData(table,e) {
    var info = [];
    var selecionados = true;
    if(table.rows('.linhaselecionada').any()){
        table.rows('.linhaselecionada').every(function(){
            var data = this.data();
            info.push({
                "id": data[0],
                "prevendasequencia": data[1],
                "cliente_id": data[2],
                "codigo": data[3],
                "descricao": data[4]
            });
        });
    }else{
        selecionados = false;
    }
    console.log(info.length);
    if(selecionados){
        return info;
    }else{
        e.preventDefault();
        bootbox.alert('Por favor, selecione as prevendas que deseja fecha.');
    }
}

function prevendaMudar() {
    var $info = $("#valoresfechar");
    if ( $info.isEmpty() ) {
        return false;
    }
    var data = JSON.parse( $info.val() );
    var quantidade = data.length;
    for (var i = 0; i < data.length; i++) {
        var produto = data[i].descricao;
    }
    blockAndSet(quantidade, produto);
}

function blockAndSet(quantidade, produto) {
    if (!isEmpty(quantidade) && !isEmpty(produto)) {
        $("#produto_id").val($('#produto_id option').filter(function () {
            return $(this).html().toUpperCase() == produto.toUpperCase();
        }).val()).trigger('chosen:updated');
        $("#quantidade").val(quantidade);
        $("#quantidade").prop('disabled', true);
        $("#produto_id").prop('disabled', true).trigger('chosen:updated');
    }
}

function criarTable(data) {
    tblSequencia.clear();
    var data = JSON.parse(data);
    for (var i = 0; i < data.length; i++) {
        tblSequencia.row.add([
            data[i].id,
            data[i].prevendasequencia,
            data[i].cliente_nome,
            data[i].codigo,
            data[i].produto_nome
        ]);
    }
    tblSequencia.draw();
}

function checarProdutos(info,table) {
    var diferente = 0;
    for (var i = 0; i < info.length; i++) {
        for (var x = 0; x < info.length; x++) {
            if (info[i].descricao !== info[x].descricao) {
                diferente++;
            }
        }
    }
    if (diferente !== 0) {
        bootbox.alert('Por favor, selecione selecione produtos diferentes para fechar a prevenda.');
        table.rows('.linhaselecionada').nodes().to$().removeClass('linhaselecionada');
        return false;
    } else {
        return true;
    }
}

function parcelasShow(){
    var parcelas = $("#parcelas_financeiro").val();
    var showpre = $("#condicaopagamento_id").val();
    if(!isEmpty(parcelas) && showpre !== ""){
        var parc = JSON.parse(parcelas);
        if(typeof parc[0] != 'undefined')
            $("#datavenc").val(parc[0].datavencimento);
    }
}

//Ajax
function ajaxProdutosPrevenda(url) {
    var id = $("#cliente_id_md").val();
    var urlcliente = url.replace(':id', id);
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
        },
        url: urlcliente,
        type: 'GET',
        success: function (data) {
            var data = JSON.stringify(data);
            criarTable(data);
        }
    });
}

//configura o plugin selectize para a busca de cliente pelo nome
function initSelectize() {
    const onChangeFn = function (data) {
        if (typeof buscaSelectize !== "undefined" && buscaSelectize !== false) {
            var $selectize = $("#nomecliente").selectize()[0].selectize;
            if (typeof $selectize.getItem(this.items[0]).context === "object") {
                $("#cliente_nome_erro").val($selectize.getItem(this.items[0]).context.innerText);
                $("#cliente_id_erro").val($selectize.getValue());
                $("#cliente_id").val($selectize.getValue());
            }
            buscaSelectize = false;
        }
        if (isEmpty(data) && !errorsany) {
            $("#cliente_nome_erro").val("");
            $("#cliente_id_erro").val("");
            $("#cliente_id").val("");
        }
    };

    let option = {
        valueField: "id",
        labelField: "nome",
        searchField: ["nome", "nomecompleto"],
        maxOptions: 10,
        hideSelected: true,
        options: [],
        create: false,
        render: {
            option: function (item, escape) {
                if (typeof item.nome === "undefined") {
                    return;
                }
                return "<div><b>" + escape(item.nome) + "</b></div>";
            }
        },
        optgroups: [{
            value: "cliente",
            label: "Clientes"
        }],
        optgroupField: "class",
        optgroupOrder: ["cliente"],
        load: function (query, callback) {
            let $select = $("#nomecliente").selectize()[0].selectize;
            $select.clearOptions();
            if (!query.length)
                return callback();
            $.ajax({
                url: root + "/api/searchClientes",
                type: "GET",
                dataType: "json",
                data: {
                    q: query
                },
                error: getErrorFunctionAjaxGeneric(callback),
                success: function (res) {
                    buscaSelectize = true;
                    callback(res.data);
                }
            });
        },
        onDropdownOpen: function ($dropdown) {
            $dropdown.css('visibility', this.lastQuery !== null && this.lastQuery.length ? 'visible' : 'hidden');
        }
    };

    $("#nomecliente").selectize({ ...option, onChange: onChangeFn });

    $("#nomeclientemd").selectize(option);

    if (errorsany) refreshSelectize();
}

function refreshSelectize() {
    let cliente_id = $("#cliente_id").val();
    let nome = $("#cliente_nome_erro").val();
    selectizeAddItem(cliente_id, nome);
}

function selectizeAddItem(cliente_id, nome) {
    let $select = $("#nomecliente").selectize()[0].selectize;
    $select.clearOptions();
    $select.addOption([{
        nome: nome,
        id: cliente_id
    }]);
    $select.addItem(cliente_id);
    $select.refreshOptions(true);
    $select.refreshItems();

}
