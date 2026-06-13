$(document).ready(function () {
    tblPedidosFiltros = $("#tblPedidosFiltrado").DataTable({
        "language": {"url": urlDataTable},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": true,
        "bInfo": false,
        "bAutoWidth": false,
        "destroy": true,
        "sScrollY": "300",
        "sScrollX": "165%",
        "aoColumnDefs": [{
            "bVisible": false,
            "aTargets": [1, 2]
        }]
    });
    tblHistorico = $("#tblHistorico").DataTable({
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
        "sScrollCollapse": true,
        "sScrollX": true,
        "sScrollY": "300",
    });

    if ( getParametro('tab') ) {
        var tab = getParametro('tab');
        $(`.nav-tabs a[href="#tab_${tab}"]`).tab('show');
    }

    blockInputs();
    contarStatus(tblPedidosFiltros);
    marcarStatus(tblPedidosFiltros);
    if (errorsany) {
        vendaativaError(tblPedidosFiltros);
    }
});

//.cancelado, .emEntrega, .atrasado

$("#tblPedidosFiltrado").on('click', 'tr', function () {
    var row = $(this);
    var tblfiltrospedido = tblPedidosFiltros;
    marcarLinha(tblfiltrospedido, row);
});

$("#tblPedidosFiltrado").on('dblclick', 'tr', function () {
    var row = $(this);
    var tblfiltrospedido = tblPedidosFiltros;
    ligarNovamente(tblfiltrospedido, row);
    modificados(tblfiltrospedido, row);
});

$("#btnGerarOcorrencia").click(function () {
    var tblfiltrospedido = tblPedidosFiltros;
    var ativo = $("#atividade").val();
    if(ativo == "1"){
        gerarOcorrencia(tblfiltrospedido);
    }else{
        bootbox.alert('Não é possível gerar ocorrência em um filtro inativo.');
    }
});

$("#btnGerarPedido").click(function (e) {
    var tblfiltrospedido = tblPedidosFiltros;
    var ativo = $("#atividade").val();
    if (ativo == "1") {
        gerarPedido(tblfiltrospedido, e);
    } else {
        bootbox.alert('Não é possível gerar pedido em um filtro inativo.');
    }
});

$("#btnFiltrarEndereco").click(function (e) {
    e.preventDefault();
    var cidade = $("#cidade_id");
    if (! cidade.isEmpty() ) {
        filtrarEndereco();
    } else {
        bootbox.alert('Por favor, selecione uma cidade.');
    }
});

$("#btnFiltrarCompram").click(function(){
    var cidade = $("#cidadecompra");
    var naocompra = $("#naocompra");
    var temcompra = $("#temcompras");
    if (! cidade.isEmpty()  && ! naocompra.isEmpty() && ! temcompra.isEmpty() ) {
        filtrarCompra();
    }else{
        bootbox.alert('Por favor, selecione uma cidade e preencha os campos.');
    }
});

$("#btnFiltrarComMedia").click(function() {
    var cidade = $("#cidademedia");
    var meses = $("#mediamesantes");
    var dias = $("#compraentre");
    if(! cidade.isEmpty() && ! meses.isEmpty() && ! dias.isEmpty() ){
        filtrarMedia();
    } else {
        bootbox.alert('Por favor, selecione uma cidade e preencha os campos.');
    }
});

$("#temcompras").blur(function(){
    var naocompra = parseInt($("#naocompra").val());//end
    var temcompra = parseInt($("#temcompras").val());//begin
    if(naocompra > temcompra){
        bootbox.alert('Campo não compram não pode ser maior que o campo tem compras');
        $("#temcompras").val(parseInt(naocompra) + 1);
    }
});

$("#btnHistorico").click(function(){
    var tblfiltrospedido = tblPedidosFiltros;
    var tblhistorico = tblHistorico;
    historico(tblfiltrospedido,tblhistorico);
});

$("#cidade_id").change( function () {
    if ( $(this).isEmpty() ) {
        $("#bairro_id").empty().trigger('chosen:updated');
        $("#rua_id").empty().trigger('chosen:updated');
    } else {
        enderecoInit( true, $(this).val() );
    }
});

$("#cidadecompra").change( function () {
    if ( $(this).isEmpty() ) {
        $("#bairrocompra").empty().trigger('chosen:updated');
        $("#ruacompra").empty().trigger('chosen:updated');
    } else {
        enderecoinitTab2( true, $(this).val() );
    }
});

$("#cidademedia").change( function () {
    if ( $(this).isEmpty() ) {
        $("#bairromedia").empty().trigger('chosen:updated');
        $("#ruamedia").empty().trigger('chosen:updated');
    } else {
        enderecoInitTab3( true, $(this).val() );
    }
});

$("#btngravar").click(function (e) {
    var tblfiltrospedido = tblPedidosFiltros;
    var vendaativa_id = $("#vendaativa_id").val();
    if(window.location.search == "" && vendaativa_id == "") {
        e.preventDefault();
        bootbox.alert('Venda Ativa não pode ser salva sem nenhum filtro.');
    }else if(!tblfiltrospedido.rows().any()){
        e.preventDefault();
        bootbox.alert('Filtro não pode ser salvo porque não resultou em cliente.');
    } else if(window.location.search != "" && vendaativa_id =="") {
        salvarClientes(tblfiltrospedido);
    }else{
        saidaPagina(tblfiltrospedido, "0");
    }
});

function filtrarEndereco() {
    var url = root + '/vendaativa.filtroendereco?cidade=:cidade&bairro=:bairro&rua=:rua&segmento=:seg&setor=:setor';
    var cidade = $("#cidade_id").val() == "" ? 0 : $("#cidade_id").val();
    var bairro = $("#bairro_id").val() == "" || $("#bairro_id").val() == "null" ? 0 : $("#bairro_id").val();
    var rua = $("#rua_id").val() == "" || $("#rua_id").val() == "null" ? 0 : $("#rua_id").val();
    var setor = $("#setor_id").val() == "" ? 0 : $("#setor_id").val();
    var segmento = $("#segmento_id").val() == "" ? 0 : $("#segmento_id").val();
    var url = url.replace(':cidade', cidade);
    var url = url.replace(':bairro', bairro);
    var url = url.replace(':rua', rua);
    var url = url.replace(':setor', setor);
    var url = url.replace(':seg', segmento);
    window.location.href = url;
}

function filtrarCompra() {
    var url = root + '/vendaativa.filtrocompra?cidade=:cidade&bairro=:bairro&rua=:rua&setor=:setor&segmento=:seg&temcompra=:temcompra&naocompra=:naocompra';
    var naocompra = isEmpty($("#naocompra").val()) ? "0" : $("#naocompra").val();
    var temcompra = isEmpty($("#temcompras").val()) ? "0" : $("#temcompras").val();;
    var cidade = $("#cidadecompra").val() == "" ? 0 : $("#cidadecompra").val();
    var bairro = $("#bairrocompra").val() == "" ? 0 : $("#bairrocompra").val();
    var rua = $("#ruacompra").val() == "" || $("#ruacompra").val() == "null" ? 0 : $("#ruacompra").val();
    var setor = $("#setorcompra").val() == "" || $("#setorcompra").val() == "" ? 0 : $("#setorcompra").val();
    var segmento = $("#segmentocompra").val() == "" ? 0 : $("#segmentocompra").val();
    var url = url.replace(':cidade', cidade);
    var url = url.replace(':bairro', bairro);
    var url = url.replace(':setor', setor);
    var url = url.replace(':rua', rua);
    var url = url.replace(':seg', segmento);
    var url = url.replace(':temcompra', temcompra);
    var url = url.replace(':naocompra', naocompra);
    window.location.href = url;
}

function filtrarMedia(){
    var url = root + "/vendaativa.filtromedia?setor=:setor&cidade=:cidade&bairro=:bairro&segmento=:seg&giroate=:giroate&mesantes=:mesantes&entre=:entre";
    var meses = $("#mediamesantes").val();
    var dias = $("#compraentre").val();
    var setor = $("#setormedia").val() == "" ? 0 : $("#setormedia").val();
    var bairro = $("#bairromedia").val() == "" || $("#bairromedia").val() == "" ? 0 : $("#bairromedia").val();
    var cidade = $("#cidademedia").val() == "" ? 0 : $("#cidademedia").val();
    var segmento = $("#segmentomedia").val() == "" ? 0 : $("#segmentomedia").val();
    var datagiro = insertDataOracle($("#mediagiro").val());
    var url = url.replace(':mesantes', meses);
    var url = url.replace(':entre', dias);
    var url = url.replace(':setor', setor);
    var url = url.replace(':cidade', cidade);
    var url = url.replace(':bairro', bairro);
    var url = url.replace(':seg', segmento);
    var url = url.replace(':giroate', datagiro);
    window.location.href = url;
}

function gerarOcorrencia(table) {
    var vendaativa_id = $("#vendaativa_id").val();
    if (vendaativa_id != "") {
        if (table.rows('.linhaselecionada').data().any()) {
            var data = table.row('.linhaselecionada').data();
            var verificar = verificarMarcacoes(table, data[0]);
            if (verificar) {
                $("#user_id").val(data[1]);
                $("#colaborador_nome").val(data[2]);
                $("#cliente_id").val(data[0]);
                $("#cliente_nome").val(data[3]);
                $("#ocorrencia_modal").modal('show');
            } else {
                bootbox.alert('Cliente já realizou um pedido ou uma ocorrência.');
            }
        } else {
            bootbox.alert('Selecione um cliente para gerar ocorrência.');
        }
    } else {
        bootbox.alert('Para gerar uma ocorrência os filtros deve ser salvos.');
    }
}

function gerarPedido(table, e) {
    var vendaativa_id = $("#vendaativa_id").val();
    if (vendaativa_id != "") {
        if (table.rows('.linhaselecionada').data().any()) {
            var data = table.row('.linhaselecionada').data();
            var verificar = verificarMarcacoes(table, data[0]);
            if (verificar) {
                var clientes = saidaPagina(table, "3");
                var url = root + "/pedido/create?cliente_id=:id&vendaativa_id=:id";
                var url = url.replace(':id', data[0]);
                var url = url.replace(':id', vendaativa_id);
                var salvo = ajaxSave(clientes, e);
                if(salvo){
                    window.location.href = url;
                }
            }else{
                bootbox.alert('Cliente já realizou um pedido ou uma ocorrência.');
            }
        }else{
            bootbox.alert('Selecione um cliente para gerar um pedido.');
        }
    }else{
        bootbox.alert('Para gerar uma pedido os filtros deve ser salvos.');
    }
}

function corrigirInformacoes(filtro) {
    var setor = getParametro("setor") == "0" ? "" : getParametro("setor");
    var segmento = getParametro("segmento") == "0" ? "" : getParametro("segmento");
    var cidade = getParametro('cidade');
    var bairro = getParametro('bairro');
    var rua    = getParametro('rua');
    switch ( filtro ) {
        case "1":
            $("#cidade_id").val(cidade).trigger('chosen:updated');
            $("#cidade_id").trigger('change');
            $("#setor_id").val(setor).trigger('chosen:updated');
            $("#segmento_id").val(segmento).trigger('chosen:updated');
            break;
        case "2":
            $('.nav-tabs a[href="#tab_2"]').tab('show');
            $("#cidadecompra").val(cidade).trigger('chosen:updated');
            $("#cidadecompra").trigger('change');
            var temcompra = getParametro("temcompra") == "0" ? "" : getParametro("temcompra");
            var naocompra = getParametro("naocompra") == "0" ? "" : getParametro("naocompra");
            $("#setorcompra").val(setor).trigger('chosen:updated');
            $("#segmentocompra").val(segmento).trigger('chosen:updated');
            $("#naocompra").val(naocompra);
            $("#temcompras").val(temcompra);
            break;
        default:
            $('.nav-tabs a[href="#tab_3"]').tab('show');
            var entre = getParametro("entre") == "0" ? "" : getParametro("entre");
            var mesantes = getParametro("mesantes") == "0" ? "" : getParametro("mesantes");
            var giroate = retornarData("giroate");
            $("#cidademedia").val(cidade).trigger('chosen:updated');
            $("#cidademedia").trigger('change');
            $("#mediagiro").val(giroate);
            $("#compraentre").val(entre);
            $("#mediamesantes").val(mesantes);
            $("#setormedia").val(setor).trigger('chosen:updated');
            $("#segmentomedia").val(segmento).trigger('chosen:updated');
            break;
    }
}

function ligarNovamente(table, row) {
    var vendaativa_id = $("#vendaativa_id").val();
    if (vendaativa_id != "") {
        if (!row.hasClass('cancelado') && !row.hasClass('emEntrega')) {
            if (row.hasClass('atrasado')) {
                row.removeClass('atrasado');
            } else {
                row.addClass('atrasado');
            }
        }
        contarStatus(table);
    } else {
        bootbox.alert('Por favor, salve os filtros antes de começar a trabalhar nessa venda ativa.')
    }
}

function contarStatus(table) {
    var novamente = table.rows('.atrasado').data().length;
    $("#divQdeClientesNovamente").text('');
    $("#divQdeClientesNovamente").append(novamente + ' cliente(s)');

    var ocorrencia = table.rows('.cancelado').data().length;
    $("#divQdeClientesOcorrencia").text('');
    $("#divQdeClientesOcorrencia").append(ocorrencia + ' cliente(s)');

    var pedido = table.rows('.emEntrega').data().length;
    $("#divQdeClientesPedido").text('');
    $("#divQdeClientesPedido").append(pedido + ' cliente(s)');

    var total = table.rows().data().length;
    $("#divQdeClientes").text('');
    $("#divQdeClientes").append((total - novamente - ocorrencia - pedido) + ' cliente(s)');
    ocorrencia = 0;
    pedido = 0;
    novamente = 0;
    total = 0;
}

function saidaPagina(table, form) {
    var novamente = [];
    if (table.rows('.atrasado').data().length > 0) {
        table.rows('.atrasado').every(function () {
            var data = this.data();
            if (data != "") {
                novamente.push({
                    "cliente_id": data[0]
                });
            }
        });
    }
    var clientesmodificar = filtrarArray(table, novamente);
    if (form == "0") {
        $("#novamente").val(JSON.stringify(clientesmodificar));
    } else if (form == "1") {
        $("#novamente_ocorrencia").val(JSON.stringify(clientesmodificar));
    } else {
        return clientesmodificar;
    }
}

function blockInputs() {
    var vendaativa_id = $("#vendaativa_id").val();
    var inputs = ["uf", "cidade_id", "bairro_id", "rua_id", "setor_id", "ufcompra", "cidadecompra",
        "cidadecompra", "bairrocompra", "ruacompra", "naocompra", "bairromedia", "cidademedia", "mediagiro",
        "mediamesantes", "compraentre", "btnFiltrarEndereco", "btnFiltrarCompram", "btnFiltrarComMedia",
        "setorcompra", "temcompras", "setormedia"
    ];
    if (vendaativa_id != "") {
        for (var i = 0; i < inputs.length; i++) {
            $("#" + inputs[i]).prop('disabled', true).trigger('chosen:updated');
        }
    }
}

function marcarStatus(table) {
    var clientesvenda = $("#vendaativaclientes").val() == "" ? [] : JSON.parse($("#vendaativaclientes").val());
    $("#tblPedidosFiltrado tbody tr").each(function (i, cliente) {
        var data = $(this).find('td:first').text();
        var row = $(this);
        $.each(clientesvenda, function (i, cliente) {
            if (data == cliente.id) {
                if (cliente.ligarnovamente == 1 && isEmpty(cliente.vendaativaocorrencia_id) &&
                         isEmpty(cliente.pedido_id)) {
                    row.addClass('atrasado');
                } else if (!isEmpty(cliente.vendaativaocorrencia_id)) {
                    row.addClass('cancelado');
                } else if (!isEmpty(cliente.pedido_id)) {
                    row.addClass('emEntrega');
                }
            }
        });
    });
    contarStatus(table);
}

function verificarMarcacoes(table, id) {
    var pedido = table.rows('.emEntrega').data();
    var ocorrencia = table.rows('.cancelado').data();
    var verificado = true;
    for (var i = 0; i < pedido.length; i++) {
        if (pedido[i][0] == id) {
            verificado = false;
        }
    }

    for (var i = 0; i < ocorrencia.length; i++) {
        if (ocorrencia[i][0] == id) {
            verificado = false;
        }
    }

    $("#tblPedidosFiltrado tbody tr").each(function (i, cliente) {
        var data = $(this).find('td:first').text();
        var row = $(this);
        if(id == data){
            if(row.hasClass('atrasado')){
                row.removeClass('atrasado');
            }
        }
    });
    return verificado;
}

function modificados(table, row) {
    var modificados = $("#modificados").val() == "" ? [] : JSON.parse($("#modificados").val());
    var idselecionado = row.find('td:first').text();
    if (isEmpty(modificados)) {
        modificados.push({
            "cliente_id": idselecionado
        });
    } else {
        var existe = false
        $.each(modificados, function (i, mod) {
            if (mod.cliente_id == idselecionado) {
                existe = true;
            }
        });
        if (!existe) {
            modificados.unshift({
                "cliente_id": idselecionado
            });
        }
    }
    $("#modificados").val(JSON.stringify(modificados));
}

function filtrarArray(table, novamente) {
    var id = $("#vendaativa_id").val();
    var clientes = JSON.parse($("#vendaativaclientes").val());
    var modificados = $("#modificados").val() == "" ? [] : JSON.parse($("#modificados").val());
    var clientesmodificar = [];
    $.each(modificados, function (i, mod) {
        if (novamente.length > 0) {
            var novamenteexiste = existeArray(novamente, mod.cliente_id);
            if (novamenteexiste) {
                clientesmodificar.push({
                    "cliente_id": mod.cliente_id,
                    "ligarnovamente": 1
                });
            } else {
                clientesmodificar.push({
                    "cliente_id": mod.cliente_id,
                    "ligarnovamente": 0
                });
            }
        } else {
            clientesmodificar.push({
                "cliente_id": mod.cliente_id,
                "ligarnovamente": 0
            });
        }
    });
    return clientesmodificar;
}

function existeArray(array, elemento) {
    var existe = false;
    $.each(array, function (i, valor) {
        if (valor.cliente_id == elemento) {
            existe = true;
        }
    });
    return existe;
}

function ajaxSave(clientes, e) {
    var id = $("#vendaativa_id").val();
    var data = new FormData();
    data.append('vendaativa_id',id);
    data.append("clientes",JSON.stringify(clientes));
    var url = root + "/vendaativa/salvarfiltros";
    var salvo = false;
    ajaxGenerator(url,'POST',function(data){
        if (data == "true") {
            salvo = true;
        }else{
            e.preventDefault();
            bootbox.alert("Ops, algo deu errado.");
        }
    },null,data);
    return salvo;
}

function salvarClientes(table){
    var clientes = [];
    table.rows().every(function(){
        var data = this.data();
        clientes.push({
            "cliente_id":data[0],
            "ligarnovamente":0,
            "mediagiro":data[10]
        });
    });
    $("#clientes").val(JSON.stringify(clientes));
}

function historico(table,table2){
    var vendaativa_id = $("#vendaativa_id").val();
    if (table.rows('.linhaselecionada').data().any()) {
        var data = table.rows('.linhaselecionada').data();
        var id = data[0][0];
        var nome = data[0][3];
        var endereco = data[0][4];
        var telefones = data[0][5];
        $("#endereco").val(endereco);
        $("#cliente").val(nome);
        $("#telefones").val(telefones);
        table2.clear().draw();
        ajaxHistorico(id,table2);
    }else{
        bootbox.alert('Por favor, selecione um cliente para visualizar o histórico.');
    }
}

function ajaxHistorico(cliente_id,table2){
    var url = root + '/ajaxhistorico/:cliente_id/:limit';
    var url = url.replace(':cliente_id',cliente_id);
    var url = url.replace(':limit','30');
    ajaxGenerator(url,'GET',function(data){
        if (!isEmpty(data)) {
            montarTabela(data,table2);
        } else {
            bootbox.alert('O cliente em questão não tem nenhuma compra.')
        }
    }, null);
}

function montarTabela(data,table2){
    for (var i=0;i<data.length;i++) {
        table2.row.add([
            data[i].pedido_id,
            data[i].data,
            data[i].produto,
            data[i].quantidade,
            data[i].condicao,
            data[i].valor,
            data[i].status
        ]);
    }
    table2.draw();
    $("#modal_historico").modal('show');
}

function vendaativaError(table){
    var novamente = $("#novamente").val();
    var arraynov = JSON.parse(novamente);
    $("#tblPedidosFiltrado tbody tr").each(function (i, cliente) {
        var data = $(this).find('td:first').text();
        var row = $(this);
        $.each( arraynov, function ( i, cliente ) {
            if ( data == cliente.cliente_id ) {
                if ( cliente.ligarnovamente == 1 ) {
                    row.addClass('atrasado');
                } else {
                    row.removeClass('atrasado');
                }
            }
        });
    });
    contarStatus(table);
}

function enderecoInit( fromJs = false, city = null ) {
    var $city = $("#cidade_id");
    if (! fromJs ) {
        idCidade = cidade_id;
    } else {
        idCidade = city;
    }

    if ( $city.isEmpty() || ! fromJs ) {
        $(".btn").removeAttr('disabled');
        return false;
    }
    setInputsEnderecoPadrao('#cep', '#cidade_id', '#uf', '#bairro_id', '#rua_id');
    $( getIdInputCidade('geral') ).val(idCidade).trigger('chosen:updated');
    changeCidade( function () {
        $(getIdInputBairro('geral')).val(bairro_id).trigger('chosen:updated');
        $(getIdInputRua('geral')).val(rua_id).trigger('chosen:updated');
        if (! fromJs ) enderecoinitTab2();
    },'geral');
}

function enderecoinitTab2( fromJs = false, city = null ) {
    if (! fromJs ) {
        idCidade = cidadecompra;
    } else {
        idCidade = city;
    }
    setInputsEnderecoOutros('#cep', '#cidadecompra', '#uf', '#bairrocompra', '#ruacompra');
    $(getIdInputCidade('outros')).val(idCidade).trigger('chosen:updated');
    changeCidade(function(){
        $(getIdInputBairro('outros')).val(bairrocompra).trigger('chosen:updated');
        $(getIdInputRua('outros')).val(ruacompra).trigger('chosen:updated');
        if (! fromJs ) 
            enderecoInitTab3();
    }, 'outros');
}

function enderecoInitTab3( fromJs = false, city = null ) {
    if (! fromJs ) {
        idCidade = cidademedia;
    } else {
        idCidade = city;
    }
    setInputsEnderecoContabilista('#cep', '#cidademedia', '#uf', '#bairromedia', '#ruamedia');
    $(getIdInputCidade('contabilista')).val(idCidade).trigger('chosen:updated');
    changeCidade(function(){
        $(getIdInputBairro('contabilista')).val(bairromedia).trigger('chosen:updated');
        $(".btn").removeAttr('disabled');
    }, 'contabilista');
}