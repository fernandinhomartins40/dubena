$(document).ready(function () {
    tblPedidosSetor = $("#tblPedidosSetor").DataTable({
        "language": {
            "url": urlDataTable
        },
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": true,
        "bInfo": false,
        "bAutoWidth": false,
        "destroy": true,
        "sScrollY":"200px",
        "columnDefs": [
            {"className": "dt-center", "targets": "_all"}
        ]
    });

    tblPedidosSelecionados = $("#tblPedidosVinculados").DataTable({
        "language": {
            "url": urlDataTable
        },
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": true,
        "bInfo": false,
        "bAutoWidth": false,
        "destroy": true,
        "sScrollY":"200px"
    });
});

inputs = ["veiculo_id","datahora","km","temporodado","kmrodado"];

$('a[data-toggle="tab"]').on('shown.bs.tab', function(e){
   $($.fn.dataTable.tables(true)).DataTable()
      .columns.adjust();
});

$("#veiculo_id").change(function () {
    var datainicial = $("#datahora").val();
    $("#ultimadatahora").val(datainicial);
    $("#ultimokm").val('');
    $("#kmrodado").val('');
    $("#temporodado").val('');
    var id = $(this).val();
    dadosVeiculo(id);
});

$("#km").blur(function () {
    var kmatual = $(this).val();
    calcularKm(kmatual);
    tempoRodado()
});

$("#btnFiltroPedidos").click(function(){
    var tblpedidossetor = tblPedidosSetor;
    filtroPedidos(tblpedidossetor);
});

$("#btnVincularPedidos").click(function(){
    var tblpedidossetor = tblPedidosSetor;
    var tblpedidosslecionados = tblPedidosSelecionados;
    vincularPedidos(tblpedidossetor,tblpedidosslecionados);
});

$("#tblPedidosSetor").on('click','tr',function(){
    var row = $(this);
    var tblpedidossetor = tblPedidosSetor;
    marcarLinha(tblpedidossetor,row);
});

$("#tblPedidosVinculados").on('click','tr',function(){
    var row = $(this);
    var tblpedidosslecionados = tblPedidosSelecionados;
    marcarLinha(tblpedidosslecionados,row);
});

$("#btnRemoverSelecionados").click(function(){
    var tblpedidosslecionados = tblPedidosSelecionados;
    removerLinha(tblpedidosslecionados);
});

$("#gravar").click(function(e){
    var tblpedidosslecionados = tblPedidosSelecionados;
    checagemGravacao(e,tblpedidosslecionados);
});

function preencherCampos(data) {
    var kmatual = data.kmatual;
    $("#ultimokm").val(kmatual);
    if(!isEmpty(data.ultimadatahora)){
        $("#ultimadatahora").val(requestDataOracle(data.ultimadatahora,true,false,true));
        $("#entrada_hd").val(data.entrada);
        $("#saida_hd").val(data.saida);
    }
}

function calcularKm(kmatual) {
    var kmantigo = parseInt($("#ultimokm").val());
    if (kmantigo <= kmatual) {
        var calculo = kmatual - kmantigo;
        $("#kmrodado").val(calculo);
    }else{
        $("#kmrodado").val('');
    }
}

function tempoRodado() {
    var dataatual = moment($("#ultimadatahora").val(),'DD/MM/YYYY HH:mm:ss');
    var dataanterior = moment($("#datahora").val(),'DD/MM/YYYY HH:mm:ss');
    var diferenca = dataatual.diff(dataanterior);
    var tempototal = "00 00:00:00";
    if(dataatual.isBefore(dataanterior)){
        var duracao = Math.abs(diferenca / 1000);
        var seconds = twoDigit(Math.floor(duracao%60));
        var min = twoDigit(Math.floor(duracao%3600/60));
        var hours = twoDigit(Math.floor(duracao%86400/3600));
        var days = twoDigit(Math.floor(duracao/86400));
        tempototal = days + " " + hours + ":" + min + ":" + seconds;
    }
    $("#temporodado").val(tempototal);
}

function twoDigit(seconds){
    if(seconds < 10){
        seconds = "0" + seconds;
    }
    return seconds;
}

function popularTablePedidos(data,table){
    table.clear().draw();
    if(data.length > 0){
        for(var i=0;i<data.length;i++){
            table.row.add([
                data[i].id,
                data[i].status,
                data[i].cliente,
                data[i].endereco,
                data[i].valorvenda
            ]).draw();
        }
    }
}

function marcarLinha(table,row){
    if (row.hasClass('linhaselecionada')) {
        row.removeClass('linhaselecionada');
    } else {
        row.addClass('linhaselecionada');
    }
}

function vincularPedidos(table,table2){
    var inserir = true;
    if(table2.rows().any()){
        table2.rows().every(function(){
            var data = this.data();
            var datatable1 = table.rows('.linhaselecionada').data();
            for(var i=0;i<datatable1.length;i++){
                if(data[0] == datatable1[i][0]){
                    inserir = false;
                    table.rows('.linhaselecionada').nodes().to$().removeClass('linhaselecionada');
                }
            }
        });
    }
    if(inserir){
        table.rows('.linhaselecionada').every(function(e){
            var data = this.data();
            table2.row.add([
                data[0],
                data[1],
                data[2],
                data[3],
                data[4]
            ]).draw();
        });
        table.rows('.linhaselecionada').nodes().to$().removeClass('linhaselecionada');
    }
}

function removerLinha(table){
    table.rows('.linhaselecionada').remove().draw();
}

function checagemGravacao(e,table){
    var entrada = $("#entrada").prop('checked');
    var saida = $("#saida").prop('checked');
    var dataatual = moment($("#ultimadatahora").val(),'DD/MM/YYYY HH:mm:ss');
    var dataanterior = moment($("#datahora").val(),'DD/MM/YYYY HH:mm:ss');
    var inputok = false;
    var dataok = true;

    for(var i=0;i<inputs.length;i++){
        var input = $("#"+inputs[i]).val();
        if(isEmpty(input)){
            inputok = true;
        }
    }
    if(dataatual.isAfter(dataanterior)){
        e.preventDefault();
        bootbox.alert('Data atual deve ser posterior que a última data!');
        return;
    }

    if(table.rows().any() && !inputok){
        var pedidosvinculados = [];
        table.rows().every(function(){
            var d = this.data();
            pedidosvinculados.push(d)
        });
        $("#tblvincpedidos_hd").val(JSON.stringify(pedidosvinculados));
    }else{
        e.preventDefault();
        bootbox.alert('Favor vincular os pedidos a está movimentação ou preencher todos o campos, lembrando que Km Atual pode apenas ser igual ou maior que o antigo.');
    }
}

//Ajax
function filtroPedidos(table){
    var datainicio = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());
    var setor = $("#setor_id").val() == "" ? 0 : $("#setor_id").val();
    var urlfiltro = root + "/ajaxpedidosetor?datainicio=:datainicio&datafim=:datafim&setor=:setor";
    var url = urlfiltro.replace(':datainicio',datainicio);
    var url = url.replace(':datafim',datafim);
    var url = url.replace(':setor',setor);
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
        },
        url: url,
        type: 'GET',
        success: function (data) {
            popularTablePedidos(data,table);
        }
    });
}

function dadosVeiculo(id) {
    var url = root + "/ajaxbuscardados/:id";
    var url = url.replace(':id', id);
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
        },
        url: url,
        type: 'GET',
        success: function (data) {
            preencherCampos(data);
        }
    });
}

function ocultarElementos(){
    $("#div-filtros").addClass('hidden');
    $("#div-table").addClass('hidden');
    $("#div-botoes").addClass('hidden');
    $("#hr-botoes-after").addClass('hidden');
    $("#hr-botoes").addClass('hidden');
}