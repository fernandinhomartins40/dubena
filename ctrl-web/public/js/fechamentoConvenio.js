$(document).ready(function () {
    tblPedidosConvenios = $("#tblPedidosConvenios").DataTable({
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
        "sScrollY": "100%",
        "aoColumnDefs": [{
            "bVisible": false,
            "aTargets": [1]
        }]
    });
    if(errors){
        pedidosTableErro(tblPedidosConvenios);
    }
});

$("#cliente_id").change(function () {
    var cliente = $(this).val();
    var dataatual = moment().utc().format('DD/MM/YYYY');
    $("#datavencimento").val(dataatual);
    $("#datainicio").val(dataatual);
    $("#datafim").val(dataatual);
    if (cliente != "") {
        preecherDatasCliente();
    } else {
        $("#dataemissao").val(dataatual);
    }
});

$("#btnFiltroPedidos").click(function () {
    var tblpedidosconvenios = tblPedidosConvenios;
    var cliente = $('#cliente_id').val();
    if(!isEmpty(cliente)){
        filtroPedidos(tblpedidosconvenios);
    }else{
        bootbox.alert('Por favor selecione um conveniado.')
    }
});

$("#btnGravarFechamento").click(function (e) {
    var tblpedidosconvenios = tblPedidosConvenios;
    validarFechamento(tblpedidosconvenios, e);
});

$("#goback").click(function(){
    window.history.back();
});

function setarDatas() {
    var cliente = getParametro("cliente");
    var datainicio = retornarData("datainicio", false);
    var datafim = retornarData("datafim", false);
    var dataemissao = retornarData("dataemissao", false);
    var datavencimento = retornarData("datavencimento", false);
    $("#cliente_id").val(cliente).trigger('chosen:updated');
    $("#dataemissao").val(dataemissao);
    $("#datainicio").val(datainicio);
    $("#datafim").val(datafim);
    $("#datavencimento").val(datavencimento);
}

function validarFechamento(table, e) {
    var cond = $("#condicaopagamento_id").val();
    var validado = true;
    if (!table.rows().any()) {
        validado = false;
    }
    if (isEmpty(cond)) {
        validado = false;
    }
    if(validado){
        var conveniosfechar = [];
        table.rows().every(function () {
            var d = this.data();
            conveniosfechar.push(d);
        });
        $("#tblconveniopedidos_hd").val(JSON.stringify(conveniosfechar));
    }else{
        e.preventDefault();
        bootbox.alert('Por favor, selecione uma condicao de pagamento e filtre um conveniado.')
    }
}

function dataVencimento(dia) {
    let comp = parseInt(dia);
    let strd = String(dia).padStart(2, "0");
    let $ini = $("#dataemissao").val();
    let emissao = moment($ini, "DD/MM/YYYY");
    let isGreater = emissao.date() >= comp;
    let ad = emissao.clone().format("MM/YYYY");
    let ndate = moment(strd + "/" + ad, "DD/MM/YYYY");

    if (!ndate.isValid()) {
        ndate = moment("01/" + ad, "DD/MM/YYYY");
        isGreater = emissao.date() >= 1;
    }

    if (isGreater) {
        ndate = ndate.add(1, "month");
    }

    $("#datavencimento").val(ndate.format("DD/MM/YYYY"));
}

// function dataVencimento(data) {
//     let datavenc = data;
//     let datainicio = $("#dataemissao").val();
//     let date = moment(datainicio, 'DD/MM/YYYY');
//     let dataatual = moment(date).format('DD/MM/YYYY');
//     let mesFuturo = moment(date).add(1, 'M').format('DD/MM/YYYY');
//     let dataReplace = dataatual.split('/');
//     if (parseInt(dataReplace[0]) >= datavenc) {
//         let datafuturo = mesFuturo.split("/");
//         let diafuturo = validarData(datafuturo[1], datavenc);
//         let novadata = ("00" + diafuturo).substr(-2,2) + "/" + datafuturo[1] + "/" + datafuturo[2];
//         $("#datavencimento").val(novadata);
//     } else {
//         let dia = validarData(dataReplace[1], datavenc);
//         let novadata = ("00" + dia).substr(-2,2) + "/" + dataReplace[1] + "/" + dataReplace[2];
//         $("#datavencimento").val(novadata);
//     }
// }

// function validarData(data, dia) {
//     switch (data) {
//         case "06":
//             if (dia > "30") {
//                 var dia = "30";
//             }
//             break;
//         case "09":
//             if (dia > "30") {
//                 var dia = "30";
//             }
//             break;
//         case "11":
//             if (dia > "30") {
//                 var dia = "30";
//             }
//             break;
//         case "02":
//             if (dia > "28") {
//                 var dia = "28";
//             }
//             break;
//         default:
//             var dia = dia;
//             break;
//     }
//     return dia;
// }

function pedidosTableErro(table){
    table.clear().draw();
    var pedidos = JSON.parse($("#tblconveniopedidos_hd").val());
    for(var i=0;i<pedidos.length;i++){
        table.row.add([
            pedidos[i][0],
            pedidos[i][1],
            pedidos[i][2],
            pedidos[i][3],
            pedidos[i][4]
        ]).draw();
    }
}

function filtroPedidosPreencher(data,table){
    table.clear().draw();
    var pedidos = data.pedidos;
    var valortotal = data.totalpedidos;
    $("#valor").val(valortotal);
    for(var i=0;i<pedidos.length;i++){
        table.row.add([
            pedidos[i].id,
            pedidos[i].cliente_id,
            pedidos[i].nome,
            requestDataOracle(pedidos[i].datahoraprevisaoentrega,true,false),
            "R$ " + formataDecimal(pedidos[i].valorvenda,2)
        ]).draw();
    }
}

//ajax
function preecherDatasCliente() {
    var cliente_id = $('#cliente_id').val();
    var urlcliente = root + '/clienteconveniado/:id';
    var url = urlcliente.replace(':id', cliente_id);
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
        },
        url: url,
        type: 'GET',
        success: function (data) {
            dataVencimento(data);
        }
    });
}

function filtroPedidos(table){
    var urlFiltro = root + "/fechamentoconvenio/filtro/:cliente/:datast/:dataen/:edit/:id";
    var cliente = $('#cliente_id').val();
    var datainicio = insertDataOracle($('#datainicio').val());
    var datafim = insertDataOracle($('#datafim').val());
    var url = urlFiltro.replace(':cliente',cliente);
    var url = url.replace(':datast',datainicio);
    var url = url.replace(':dataen',datafim);
    var url = url.replace(':edit',edit);
    var url = url.replace(':id',conveniofechamento_id);
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
        },
        url: url,
        type: 'GET',
        success: function (data) {
            filtroPedidosPreencher(data,table);
        }
    });
}

$("#fmCadastro").on('submit', function (e) {
      showLoaderAjax("Aguarde", "Gravando dados", false);
      var formData = new FormData($(this)[0]);
      const method = this.method;
      const url = this.action;
      $.ajax({
         type: method,
         url: url,
         data: formData,
         async: false,
         success: function (res) {
            hideLoaderAjax();
            if(res.status == 'OK'){
                if(edit=='1'){
                    window.open(root + '/fechamentoconvenio?cod=' + res.data, '_self');
                } else {
                    confirmaEmitirNFBoleto(res.data);
                }
            } else {
                bootbox.alert('erro: ' + res.msg);
            }
         },
         error: function (data) {
            hideLoaderAjax();
            errorFunctionAjax(data);
         },
         cache: false,
         contentType: false,
         processData: false
      });
      return false;

   });

   function confirmaEmitirNFBoleto(conveniofechamento_id){
        bootbox.prompt({
            title: 'Deseja emitir a NF/Boleto para esse fechamento?',
            message: '<p>Deseja emitir a NF/Boleto para esse fechamento?</p>',
            inputType: 'checkbox',
            inputOptions: [{
                text: 'Nota Fiscal',
                value: '1'
            },
            {
                text: 'Boleto',
                value: '2'
            }],
            callback: function (result) {
               if(!result || result.length==0){
                    window.open(root + '/fechamentoconvenio?cod=' + conveniofechamento_id, '_self');
               } else {
                    console.log(result);
                    if(result.includes('1')){
                        console.log('vai emitir nf', result.includes('2'))
                        emitirNF(conveniofechamento_id, result.includes('2'));
                    } else {
                        console.log('vai emitir boleto')
                        emitirBoleto(conveniofechamento_id);
                    }
               }
            }
        });
    }

function emitirNF(conveniofechamento_id, emiteBoleto){
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    showLoaderAjax("Aguarde", "Gerando Nota Fiscal", false);
    $.ajax({
        url: root + '/fechamentoconvenio.emitirNF',
        type: 'POST',
        dataType: 'json',
        data: {
            conveniofechamento_id: conveniofechamento_id,
        },
        success: function (res) {
            hideLoaderAjax();
            if(res.status == 'OK'){
                bootbox.alert(res.data.mensagem, function() {
                    var url = root + "/nfemitida/evento/consultar?id=:id";
                    url = url.replace(":id", res.data.id);
                    window.open(url, "_blank");
                    if(emiteBoleto){
                        emitirBoleto(conveniofechamento_id);
                    } else {
                        window.open(root + '/fechamentoconvenio?cod=' + conveniofechamento_id, '_self');
                    }
                });
            } else {
                bootbox.alert('erro ao emitir NF: ' + res.msg, function (){ 
                    if(emiteBoleto){
                        emitirBoleto(conveniofechamento_id);
                    } else {
                        window.open(root + '/fechamentoconvenio?cod=' + conveniofechamento_id, '_self');
                    }
                });
            }
        },
        error: function (data) {
            hideLoaderAjax();
            console.log(data);
             bootbox.alert('Houve um erro ao emitir a NF', function (){ 
                if(emiteBoleto){
                    emitirBoleto(conveniofechamento_id);
                } else {
                    window.open(root + '/fechamentoconvenio?cod=' + conveniofechamento_id, '_self');
                }
            });
        }
    });
}

function emitirBoleto(conveniofechamento_id){
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    showLoaderAjax("Aguarde", "Gerando Boleto", false);
    $.ajax({
        url: root + '/fechamentoconvenio.emitirBoleto',
        type: 'POST',
        dataType: 'json',
        data: {
            conveniofechamento_id: conveniofechamento_id,
        },
        success: function (res) {
            hideLoaderAjax();
            if(res.status == 'OK'){
                bootbox.alert(res.data.mensagem, function() {
                    const binaryString = atob(res.data.boleto);
                    const len = binaryString.length;
                    const bytes = new Uint8Array(len);
                    for (let i = 0; i < len; i++) {
                        bytes[i] = binaryString.charCodeAt(i);
                    }
                    const blob = new Blob([bytes], { type: 'application/pdf' });
                    const blobUrl = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = blobUrl;
                    a.download = 'boleto_' + res.data.id + '.pdf'; 
                    a.target = '_blank'; 
                    a.click();
                    URL.revokeObjectURL(blobUrl);
                    window.open(root + '/fechamentoconvenio?cod=' + conveniofechamento_id, '_self');
                });
            } else {
                bootbox.alert('erro ao emitir Boleto: ' + res.msg, function (){ 
                    window.open(root + '/fechamentoconvenio?cod=' + conveniofechamento_id, '_self');
                });
            }
        },
        error: function (data) {
            hideLoaderAjax();
            console.log(data);
             bootbox.alert('Houve um erro ao emitir o Boleto', function (){ 
                window.open(root + '/fechamentoconvenio?cod=' + conveniofechamento_id, '_self');
            });

        }
    });
}


   function errorFunctionAjax(data) {
    if (typeof (data) == 'object') {
        var msg = '';
        var responseText = '';
        for (var key in data) {
            if (key == 'responseJSON') {
                for (var key1 in data['responseJSON']) {
                    msg += data['responseJSON'][key1];
                }
            }
            if (key == 'responseText') {
                responseText = data['responseText'];
            }
        }
        if (msg != '')
            bootbox.alert('Erro ao executar a operação: ' + msg);
        else
            bootbox.alert('Erro ao executar a operação: ' + responseText);
    } else if (typeof (data) == 'string') {
        bootbox.alert('Erro ao executar a operação: ' + data);
    } else {
        bootbox.alert('Houve um erro desconhecido ao executar a operação!');
    }
}