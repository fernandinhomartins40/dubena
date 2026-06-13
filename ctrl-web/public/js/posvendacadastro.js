$(document).ready(function () {
    tblPerguntas = $("#tblPerguntas").DataTable({
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
        "sScrollY": "200px",
        "aoColumnDefs": [{
                "bSortable": false,
                "aTargets": [1]
            },
            {
                "bVisible": false,
                "aTargets": [0]
            },
            {
                "width": "20%",
                "targets": 2
            }
        ]
    });

    tblRespostas = $("#tblRespostas").DataTable({
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
        "sScrollY": "200px",
        "aoColumnDefs": [{
                "bSortable": false,
                "aTargets": [3]
            },
            {
                "bVisible": false,
                "aTargets": [0, 1]
            },
            {
                "width": "20%",
                "targets": 3
            }
        ]
    });
});

$("#btnAddPergunta").click(function () {
    var tblperguntas = tblPerguntas;
    var pergunta = $("#descricaoperguntas").val();
    if (!isEmpty(pergunta)) {
        adicionarPergunta(tblperguntas, pergunta);
    }
});

$("#btnAddResposta").click(function () {
    var tblrespostas = tblRespostas;
    var tblperguntas = tblPerguntas;
    var resposta = $("#descricaoresposta").val();
    if (!isEmpty(resposta)) {
        adicionarResposta(tblrespostas, tblperguntas, resposta);
    }
});

$("#tblPerguntas").on('click','tr',function(){
    var row = $(this);
    var tblperguntas = tblPerguntas;
    var tblrespostas = tblRespostas;
    var row = $(this).closest('tr');
    marcarLinha(tblperguntas,row);
    mudarTabela(tblperguntas,tblrespostas,row);
});

$("#tblPerguntas").on('click','#btnRemover',function(e){
    var trelem = $(this).closest("tr");
    var parent = $(this).parents('tr');
    var tblperguntas = tblPerguntas;
    var tblrespostas = tblRespostas;
    if ($("#descricao").prop('disabled') !== true) {
        removerPergunta(tblperguntas,tblrespostas,trelem,parent,false);
    }
    e.stopPropagation();
});

$("#tblRespostas").on('click','#btnRemover',function(){
    var trelem = $(this).closest("tr");
    var parent = $(this).parents('tr');
    var tblperguntas = tblPerguntas;
    var tblrespostas = tblRespostas;
    if ($("#descricao").prop('disabled') !== true) {
        removerPergunta(tblperguntas,tblrespostas,trelem,parent,true);
    }
});

$("#btngravar").click(function(e){
    var tblperguntas = tblPerguntas;
    var tblrespostas = tblRespostas;
    verificacoesGerais(e,tblperguntas);
});

function callShow(){
    var tblperguntas = tblPerguntas;
    if(tblperguntas.rows().any()){
        criarInputs(tblperguntas);
        montarTabelas(tblperguntas);
    }
}

function adicionarPergunta(table, pergunta) {
    var generator = new IDGenerator(4);
    var id = generator.generate();
    var checar = checarExistencia(table, pergunta, 1);
    if (!checar) {
        table.row.add([
            id,
            pergunta,
            "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover'>Remover</button>"
        ]).draw();
        criarInputs(table);
    }else{
        bootbox.alert('Pergunta já existe na tabela.');
    }
    $("#descricaoperguntas").val('');
}

function adicionarResposta(table,table2,resposta){
    var generator = new IDGenerator(4);
    var id = generator.generate();
    var checar = checarExistencia(table, resposta, 2);
    var idpergunta = checarSelecaoLinha(table2);
    if(!checar){
        if(idpergunta != false){
            table.row.add([
                id,
                idpergunta,
                resposta,
                "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover'>Remover</button>"
            ]).draw();
            guardarInformacoes(table,idpergunta);
        }
    }else{
        bootbox.alert('Resposta já existe na tabela.');
    }
    $("#descricaoresposta").val('');
}

function marcarLinha(table, row) {
    if (row.hasClass('linhaselecionada')) {
        row.removeClass('linhaselecionada');
    } else {
        table.$('tr.linhaselecionada').removeClass('linhaselecionada');
        row.addClass('linhaselecionada');
    }
}

function checarExistencia(table, element, column) {
    var existe = false;
    if (table.rows().any()) {
        table.rows().eq(0).each(function (i) {
            var row = table.row(i);
            var data = row.data();
            if (typeof data[column] !== 'undefined') {
                if (replaceSpecialChars(data[column].toLowerCase()) === replaceSpecialChars(element.toLowerCase())) {
                    existe = true;
                }
            }
        });
    }
    return existe;
}

function criarInputs(table){
    table.rows().every(function(){
        var d = this.data();
        if(typeof $("#"+d[0]).val() === "undefined"){
            $("#perguntasconteudo").append("<input type='text' name='respostas[]' id='" + d[0] + "'>");
        }
    });
}

function checarSelecaoLinha(table){
    if(table.rows('.linhaselecionada').data().length > 0){
        var data = table.rows('.linhaselecionada').data();
        var id = data[0][0];
        return id;
    }else{
        bootbox.alert('Favor selecionar uma pergunta.');
        return false;
    }
}

function guardarInformacoes(table,idpergunta){
    var informacoes = $("#"+idpergunta);
    var perguntas = [];
    table.rows().every(function(){
        var data = this.data();
        perguntas.push({
            "id": data[0],
            "idpergunta": data[1],
            "resposta": data[2],
        });
    });
    informacoes.val(JSON.stringify(perguntas));
}

function mudarTabela(table, table2, row){
    var id = table.rows('.linhaselecionada').data();
    if(row.hasClass('linhaselecionada') && id.length > 0){
        popularTable(table2,id[0][0]);
    }else{
        table2.clear().draw();
    }
}

function popularTable(table,id){
    table.clear().draw();
    var informacoes = $("#"+id).val();
    if(!isEmpty(informacoes)){
        var parsed = JSON.parse(informacoes);
        for(var i=0;i<parsed.length;i++){
            table.row.add([
                parsed[i].id,
                parsed[i].idpergunta,
                parsed[i].resposta,
                "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover'>Remover</button>"
            ]).draw();
        }
    }
}

function removerPergunta(table,table2,trelem,parent,$resposta){
    if(!$resposta){
        var idpergunta = table.row(parent).data()[0];
        if(!isEmpty(idpergunta)){
            table.row(parent).remove().draw();
            $("#"+idpergunta).remove();
            table2.clear().draw();
        }
    }else{
        var id = table2.row(parent).data()[1];
        table2.row(parent).remove().draw();
        guardarInformacoes(table2,id);
    }
}

function verificacoesGerais(e,table){
    var descricao = $("#descricao").val();
    var dinicio = $("#datahorainicio").val();
    var dfim = $("#datahorafim").val();
    var datainicio = trazerData(dinicio).getTime();
    var datafim = trazerData(dfim).getTime();
    var validartabelas = validarTabelas(table);
    if(!isEmpty(descricao)){
        if(validartabelas){
            if(datafim >= datainicio){
                var periodo = verificarPeriodo();
                if(!periodo){
                    e.preventDefault();
                    bootbox.alert('Somente um cadastro de pós-venda pode existir no periodo colocado.');
                }
            }else{
                e.preventDefault();
                bootbox.alert('Data fim não pode ser menor que a data inicio.');
            }
        }else{
            e.preventDefault();
            bootbox.alert('Tabela de perguntas ou respostas está vazia ou alguma pergunta não tem resposta');
        }
    }else{
        e.preventDefault();
        bootbox.alert('Campo descrição é obrigatorio.');
    }
    salvarPerguntas(table);
}

function salvarPerguntas(table){
    $("#perguntas_hd").val('');
    var perguntas = [];
    table.rows().every(function(){
        var d = this.data();
        perguntas.push({
            "id":d[0],
            "pergunta":d[1]
        });
    });
    $("#perguntas_hd").val(JSON.stringify(perguntas));
}

function validarTabelas(table){
    var validar = true;
    if(table.rows().any()){
        table.rows().every(function(){
            var id = this.data()[0];
            var input = $("#"+id).val();
            if(input == ""){
                validar = false;
            }
        });
    }else{
        validar = false;
    }
    return validar;
}

function montarTabelas(table){
    var respostas = $("#respostas_hd").val();
    if(!isEmpty(respostas)){
        var parsed = JSON.parse(respostas);
        var ids = [];
        table.rows().every(function(){
            var id = this.data()[0];
            ids.push({
                "id":id
            });
        });
        $.each(ids,function(i,val){
            var input = $("#"+val.id);
            var respostas = [];
            $.each(parsed,function(x,valor){
                if(valor.posvendapergunta_id == val.id){
                    respostas.push({
                        "id":valor.id,
                        "idpergunta":valor.posvendapergunta_id,
                        "resposta":valor.descricao
                    });
                }
            });
            input.val(JSON.stringify(respostas));
        });
    }
}

//Ajax
function verificarPeriodo(){
    var urlData = root + '/posvendacadastro.verificarperiodo?datainicio=:datainicio&datafim=:datafim';
    var validado = true;
    var edit = $("#edit").val();
    var dinicio = $("#datahorainicio").val();
    var dfim = $("#datahorafim").val();
    var datainicio = insertDataHoraOracle(dinicio);
    var datafim = insertDataHoraOracle(dfim);
    var url = urlData.replace(':datainicio',datainicio);
    var url = url.replace(':datafim',datafim);
    ajaxGenerator(url, 'GET', function (data) {
        if (data === "true" && edit === '') {
            validado =  false;
        }
    },null);
    return validado;
}

function replaceSpecialChars(str) {
    str = str.replace(/[àáâãäå]/, "a");
    str = str.replace(/[éèêë]/, "e");
    str = str.replace(/[ç]/, "c");
    str = str.replace(/[ñ]/, "n");
    str = str.replace(/[iíìïî]/, "i");
    str = str.replace(/[oóòõöô]/, "o");
    str = str.replace(/[uúùüû]/, "u");
    return str;
}

function inativarCadastro(){
    $("#descricao").prop('disabled',true);
    $("#datahorainicio").prop('disabled',true);
    $("#datahorafim").prop('disabled',true);
    $("#descricaoperguntas").prop('disabled',true);
    $("#btnAddPergunta").prop('disabled',true);
    $("#descricaoresposta").prop('disabled',true);
    $("#btnAddResposta").prop('disabled',true);
    $("#descricao").prop('disabled',true);
}