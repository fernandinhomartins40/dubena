$(document).ready(function () {
    tblTopicos = $("#tblTopicos").DataTable({
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
        "sScrollY": "170",
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
        "sScrollY": "170",
        "aoColumnDefs": [
            {
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
        "sScrollY": "170",
        "aoColumnDefs": [
            {
                "bSortable": false,
                "aTargets": [6]
            },
            {
                "bVisible": false,
                "aTargets": [0, 1, 3]
            },
            {
                "width": "25%",
                "targets": 4
            },
            {
                "width": "10%",
                "targets": 5
            },
            {
                "width": "20%",
                "targets": 6
            }
        ]
    });
});

$("table").on('click',function(){
    tableclick = $(this).context.id;
});

$("#btnAddQuestionTopicos").click(function () {
    var tbltopicos = tblTopicos;
    var topico = $("#descricaotopicos").val();
    if (!isEmpty(topico)) {
        addTopicos(tbltopicos,topico);
    }
});

$("#btnAddPerguntas").click(function () {
    var tblperguntas = tblPerguntas;
    var tbltopicos = tblTopicos;
    var pergunta = $("#descricaoperguntas").val();
    if (!isEmpty(pergunta)) {
        addPerguntas(tblperguntas, tbltopicos,pergunta);
    }
});

$("#btnAddResposta").click(function () {
    var tblrespostas = tblRespostas;
    var tblperguntas = tblPerguntas;
    var resposta = $("#descricaoresposta").val();
    var alert = checarAlert(tblperguntas);
    var tipores = $("#tiporesposta").val();
    if (tipores !== '') {
        if (alert && resposta != "") {
            addRespostas(tblrespostas, tblperguntas, resposta);
        }
    } else {
        bootbox.alert('Por favor, selecione um tipo de resposta.');
    }
});

$("#tblTopicos").on('click', 'tr', function () {
    var row = $(this);
    var idtabela = row.closest('table').attr('id');
    var tbltopicos = tblTopicos;
    var tblperguntas = tblPerguntas;
    var tblrespostas = tblRespostas;
    marcarLinhas(tbltopicos, row, true);
    mudarTable(tblTopicos, tblperguntas, row, 'perguntas', tblrespostas, idtabela);
});

$("#tblPerguntas").on('click', 'tr', function () {
    var row = $(this);
    var tblperguntas = tblPerguntas;
    var tblrespostas = tblRespostas;
    marcarLinhas(tblperguntas, row, true);
    mudarTable(tblperguntas, tblrespostas, row,'respostas');
});

$("#tblTopicos").on('click', '#btnRemover', function (e) {
    var trelem = $(this).closest("tr");
    var parent = $(this).parents('tr');
    var tbltopicos = tblTopicos;
    var tblperguntas = tblPerguntas;
    var tblrespostas = tblRespostas;
    var tabela = "topicos";
    if ($("#descricao").prop('disabled') !== true) {
        removerLinha(tbltopicos, tabela, trelem, parent, tblperguntas, tblrespostas);
    }
    e.stopPropagation();
});

$("#tblPerguntas").on('click', 'button', function (e) {
    var trelem = $(this).closest("tr");
    var parent = $(this).parents('tr');
    var tbltopicos = tblTopicos;
    var tblperguntas = tblPerguntas;
    var tblrespostas = tblRespostas;
    var tabela = "perguntas";
    if ($("#descricao").prop('disabled') !== true) {
        removerLinha(tblperguntas, tabela, trelem, parent, tblrespostas, tbltopicos);
    }
    e.stopPropagation();
});

$("#tblRespostas").on('click', 'button', function (e) {
    var trelem = $(this).closest("tr");
    var parent = $(this).parents('tr');
    var tblperguntas = tblPerguntas;
    var tblrespostas = tblRespostas;
    var id = "tblRespostas";
    if ($("#descricao").prop('disabled') !== true) {
        removerLinha(tblrespostas, id, trelem, parent, tblperguntas);
    }
    e.stopPropagation();
});

$("#btngravar").click(function (e) {
    var descricao = $("#descricao").val();
    var tipo = $("#checklisttipo_id").val();
    var tbl1 = tblTopicos;
    var tbl2 = tblPerguntas;
    var tbl3 = tblRespostas;
    if (!isEmpty(tipo) && !isEmpty(descricao)) {
        validacoesGerais(e,tbl1,tbl2,tbl3);
    } else {
        e.preventDefault();
        bootbox.alert('Tipo de Checklist e Descrição são obrigatórios.');
    }
});

function showCall() {
    var tbltopicos = tblTopicos;
    var tblperguntas = tblPerguntas;
    var tblrespostas = tblRespostas;
    if(tbltopicos.rows().any()){
        criarInputs(tbltopicos,'topicosconteudo','perguntas');
        montarTabelas(tbltopicos,tblperguntas,tblrespostas);
    }
}

function checarAlert(table) {
    var tipo = $("#tiporesposta option:selected").html();
    if (!isEmpty(tipo)) {
        var tipores = tipo.toLowerCase();
        var alerta = $("#alerta").prop('checked');
        if (tipores !== "data" && alerta === true) {
            bootbox.alert('Alerta só é usado para o tipo data.');
            $("#alerta").prop('checked', false);
            return false;
        }
        return true;
    }
}

function addTopicos(tbltopicos,topico) {
    var existe = checarExistencia(tbltopicos, topico, 1);
    var generator = new IDGenerator(4);
    var id = generator.generate();
    if (!existe) {
        tbltopicos.row.add([
            id,
            topico,
            "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover' style='max-height:30px;font-size:12px;margin-top:-5px;'>Remover</button>"
        ]).draw();
        $("#descricaotopicos").val('');
        criarInputs(tbltopicos,'topicosconteudo','perguntas');
    } else {
        bootbox.alert('Tópico já existe na tabela.');
    }
}

function addPerguntas(tblperguntas, tbltopicos, pergunta) {
    var existe = checarExistencia(tblperguntas, pergunta, 2);
    var idtopico = checarSelecaoLinha(tbltopicos,"topicos");
    var generator = new IDGenerator(4);
    var id = generator.generate();
    if(!existe){
        if(idtopico != false){
            tblperguntas.row.add([
                id,
                idtopico,
                pergunta,
                "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover' style='max-height:30px;font-size:12px;margin-top:-5px;'>Remover</button>"
            ]).draw();
            $("#descricaoperguntas").val('');
            guardarInformacoes(tblperguntas,idtopico,"perguntas");
            criarInputs(tblperguntas,'perguntasconteudo','respostas');
        }
    }else{
        bootbox.alert('Pergunta já existe na tabela.');
    }
}

function addRespostas(tblrespostas, tblperguntas, resposta) {
    var generator = new IDGenerator(4);
    var id = generator.generate();
    var alerta = $("#alerta").prop('checked');
    var tipo = $("#tiporesposta").val();
    var tiporesposta = $("#tiporesposta option:selected").html();
    var existe = checarExistencia(tblrespostas, resposta, 2);
    var idpergunta = checarSelecaoLinha(tblperguntas, "perguntas");
    if(idpergunta != false){
        if(!existe){
            tblrespostas.row.add([
                id,
                idpergunta,
                resposta,
                tipo,
                tiporesposta,
                alerta == true ? "Sim" : "Não",
                "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover' style='max-height:30px;font-size:12px;margin-top:-5px;'>Remover</button>"
            ]).draw();
            $("#descricaoresposta").val('');
            $("#tiporesposta").val('').trigger('chosen:updated');
            $("#alerta").prop('checked',false);
            guardarInformacoes(tblrespostas,idpergunta,'respostas');
        }else{
            bootbox.alert('Resposta já existe na tabela.');
        }
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

function marcarLinhas(table, row, marcar) {
    if (marcar) {
        if (row.hasClass('linhaselecionada')) {
            row.removeClass('linhaselecionada');
        } else {
            table.$('tr.linhaselecionada').removeClass('linhaselecionada');
            row.addClass('linhaselecionada');
        }
    }
}

function checarSelecaoLinha(table, tabela) {
    if(table.rows('.linhaselecionada').data().length > 0){
        var data = table.rows('.linhaselecionada').data();
        var id = data[0][0];
    }else if(tabela == "perguntas"){
        bootbox.alert('Por favor, selecione uma pergunta.');
        return false;
    }else if(tabela == "topicos"){
        bootbox.alert('Por favor, selecione um tópico.');
        return false;
    }
    return id;
}

function guardarInformacoes(table, idstrange, tabela) {
    var informacoes = $("#"+idstrange);
    var checklist = [];
    informacoes.val('');
    if(tabela == "perguntas"){
        table.rows().every(function(){
            var data = this.data();
            checklist.push({
                "id":data[0],
                "idtopico":data[1],
                "pergunta":data[2]
            });
        });
    }else if(tabela == "respostas"){
        table.rows().every(function(){
            var data = this.data();
            checklist.push({
                "id":data[0],
                "idpergunta": data[1],
                "resposta":data[2],
                "tipo":data[3],
                "tiporesposta":data[4],
                "alerta": data[5] == "Sim" ? 1 : 0
            });
        });
    }
    informacoes.val(JSON.stringify(checklist));
}

function criarInputs(table, div, tabela, array = null) {
    if(array == null){
        table.rows().every(function(){
            var d = this.data();
            if($("#"+d[0]).length == 0){
                $("#"+div).append("<input type='text' name='"+tabela+"[]' id='" + d[0] + "'>");
            }
        });
    }else{
        $.each(table,function(i,val){
            var id = val.id;
            if($("#"+id).length == 0){
                $("#"+div).append("<input type='text' name='"+tabela+"[]' id='" + id + "'>");
            }
        });
    }
}

function mudarTable(table, table2, row, tabela, table3 = null,idtabela = null) {
    var id = table.rows('.linhaselecionada').data();
    if (row.hasClass('linhaselecionada') && id.length > 0) {
        popularTable(table2, id[0][0], tabela,idtabela);
        if(table3 != null){
            table3.clear().draw();
        }
    } else {
        table2.clear().draw();
        if(table3 != null){
            table3.clear().draw();
        }
    }
}

function popularTable(table, id, tabela,idtabela=null) {
    table.clear().draw();
    if(idtabela != null){
        var informacoes = $($("#topicosconteudo").find("#"+id)).val();
    }else{
        var informacoes = $($("#perguntasconteudo").find("#"+id)).val();
    }
    if (!isEmpty(informacoes)) {
        var parsed = JSON.parse(informacoes);
        if(tabela == "respostas"){
            $.each(parsed,function(i,val){
                table.row.add([
                    val.id,
                    val.idpergunta,
                    val.resposta,
                    val.tipo,
                    val.tiporesposta,
                    val.alerta == "1" ? "Sim" : "Não",
                    "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover' style='max-height:30px;font-size:12px;margin-top:-5px;'>Remover</button>"
                ]).draw();
            });
        }else{
            $.each(parsed,function(i,val){
                table.row.add([
                    val.id,
                    val.idtopico,
                    val.pergunta,
                    "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover' style='max-height:30px;font-size:12px;margin-top:-5px;'>Remover</button>"
                ]).draw();
            });
        }
    }
}

function removerLinha(table, tabela, row, parent, table2 = null, table3 = null) {
    var id = table.row(parent).data()[0];
    var ids = table.row(parent).data()[1];
    if (table2 !== null) {
        var data = table2.rows().data();
    }
    if (row.context.id === 'btnRemover') {
        table.row(parent).remove().draw();
        switch(tabela){
            case "topicos":
                $($("#topicosconteudo").find("#"+id)).remove();
                if(typeof data != 'undefined'){
                    $.each(data,function(i,val){
                        $($("#perguntasconteudo").find("#"+id)).remove();
                    });
                }
                table2.clear().draw();
                table3.clear().draw();
                break;
            case "perguntas":
                $($("#perguntasconteudo").find("#"+id)).remove();
                guardarInformacoes(table, ids, 'perguntas');
                table2.clear().draw();
                break;
            default:
                guardarInformacoes(table, ids, 'respostas');
                break;
        }
    }
}

function salvarTabelas(table) {
    var topicos = [];
    table.rows().every(function () {
        var d = this.data();
        topicos.push({
            "id":d[0],
            "topico":d[1]
        });
    });
    $("#topicos_hd").val(JSON.stringify(topicos));
}

function validacoesGerais(e,tbl1,tbl2,tbl3){
    var di = $("#datainicio").val();
    var de = $("#datafim").val();
    var validado = true;
    var urldata = root + "/checklist.verificarperiodo?datainicio=:datainicio&datafim=:datafim&tipo=:tipo&id=:id";
    var validacaoperiodo = verificarPeriodo(urldata);
    if(!validacaoperiodo){
        validado = false;
        bootbox.alert('Já existe um checklist ativo de ' + $("#checklisttipo_id option:selected").html() + ' no período selecionado.');
        e.preventDefault();
    }
    if(validado){
        var tblok = true;
        var topicosinputs = $("#topicosconteudo").find("input");
        topicosinputs.each(function(key,value){
            var input = $(value).val();
            if(input.length <= 2){
                tblok = false;
            }
        });
        var inputs = $("#perguntasconteudo").find("input");
        inputs.each(function(key,value){
            var input = $(value).val();
            if(input.length <= 2){
               tblok = false;
            }
        });
        if(!tblok){
            e.preventDefault();
            validado = false;
            bootbox.alert('Todos os tópicos adicionados devem ter perguntas e todas as perguntas respostas.')
        }
        salvarTabelas(tbl1);
    }
}

function montarTabelas(tbl,tbl1,tbl2){
    var perguntas = $("#perguntas_hd").val();
    var respostas = $("#respostas_hd").val();
    if(!isEmpty(perguntas) && !isEmpty(respostas)){
        var parsedperguntas = JSON.parse(perguntas);
        var parsedrespostas = JSON.parse(respostas);
        var idtopico = [];
        tbl.rows().every(function(){
            var id = this.data()[0];
            idtopico.push({
                "id":id
            });
        });
        $.each(idtopico,function(i,val){
            var input = $("#topicosconteudo").find("#"+val.id);
            var input = $(input);
            var perguntas = [];
            $.each(parsedperguntas,function(y,valor){
                if(val.id == valor.checklist_id){
                    perguntas.push({
                        "id":valor.id,
                        "idtopico":valor.checklist_id,
                        "pergunta":valor.descricao
                    });
                }
            });
            input.val(JSON.stringify(perguntas));
            criarInputs(perguntas,'perguntasconteudo','respostas',true);
        });
        $.each(parsedperguntas,function(i,val){
            var input = $("#perguntasconteudo").find("#"+val.id);
            var input = $(input);
            var respostas = [];
            $.each(parsedrespostas,function(y,valor){
                if(valor.checklistpergunta_id == val.id){
                    respostas.push({
                        "id":valor.id,
                        "idpergunta":val.id,
                        "resposta":valor.descricao,
                        "tipo":valor.tipopergunta,
                        "tiporesposta":valor.tiporesposta,
                        "alerta": valor.alerta
                    });
                }
            });
            input.val(JSON.stringify(respostas));
        });
    }
}

//ajax
function verificarPeriodo(url) {
    var validado = true;
    var di = $("#datainicio").val();
    var de = $("#datafim").val();
    var edit = $("#edit").val();
    var id = $("#id").val() == "" ? 0 : $("#id").val();
    var datainicio = insertDataOracle(di);
    var datafim = insertDataOracle(de);
    var dinicio = trazerData(di).getTime();
    var dfim = trazerData(de).getTime();
    var tipo = $("#checklisttipo_id").val();
    var url = url.replace(':datainicio', datainicio);
    var url = url.replace(':datafim', datafim)
    var url = url.replace(':tipo', tipo);
    var url = url.replace(':id', id);
    if(dfim > dinicio){
        ajaxGenerator(url, 'GET', function (data) {
            if (data == "true") {
                validado = false;
            }
        }, null);
    }
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

function inativarRegistro(){
    $("#descricao").prop('disabled',true);
    $("#checklisttipo_id").prop('disabled',true).trigger('chosen:updated');
    $("#tiporesposta").prop('disabled',true).trigger('chosen:updated');
    $("#descricaotopicos").prop('disabled',true);
    $("#descricaoperguntas").prop('disabled',true);
    $("#descricaoresposta").prop('disabled',true);
    $("#btnAddQuestionTopicos").prop('disabled',true);
    $("#btnAddPerguntas").prop('disabled',true);
    $("#btnAddResposta").prop('disabled',true);
    $("#alerta").prop('disabled',true);
    $("#datainicio").prop('disabled',true);
    $("#datafim").prop('disabled',true);
}
