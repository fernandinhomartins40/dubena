var creating = false;
$(document).ready(function () {

    $('.mesInicialMeta').datetimepicker({
        defaultDate: moment(),
        locale: 'pt-br',
        viewMode: 'days',
        format: 'MM/YYYY'
    });
    $('.mesInicialMeta').on('dp.change', function (e) {
        var data = e.date.format(e.date._f);
        selecionarMes(data);
    });
    var dataAtual = $("#mesInicialMeta").val();
    selecionarMes(dataAtual);
    $("#btnSubmit").on('click', function () {
        $("#fmCadastroMetas").submit();
    });
    enableDisableBtn();
    $("#alertSuccess").hide();
    $("#alertDanger").hide();
    $("#alertInfo").hide();
    $("#btnGravar").hide();
    buscarProdutosAjax();
});
function gravarMetas() {
    var method = '';
    var id = $("#id").val();
    var url;
    if (id !== '' && typeof id !== 'undefined') {
        url = $("#rotaUpdate").text() + id;
        $('#metodo').val('PATCH');
        method = 'POST';
    } else {
        url = $("#rotaStore").val();
        $('#metodo').val('POST');
        method = 'POST';
    }
    var formData = new FormData($("form#fmCadastroMetas")[0]);

    $.ajax({
        type: method,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        url: url,
        data: formData,
		async: false,        
        success: function (data) {
            if (data.substr(0, 3) == 'OK|') {
                $("#alertSuccess").show();
                $("#alertSuccess").fadeTo(4000, 500).slideUp(500, function () {
                    $("#alertSuccess").slideUp(1000);
                });
                if ($("#metavenda-list").find('td:eq(0)').hasClass('dataTables_empty')) {
                    $("#metavenda-list").text('');
                }
                $("#metavenda-list").append(data);

                $('#fmCadastroMetas')[0].reset();
                if (mesCadastro === 12) {
                    $("#btnBusca").click();
                }
                selecionarMes(parseInt(mesCadastro + 1) + '/' + ano);
            } else if (data.substr(0, 3) === 'OPS') {
                $("#alertInfo").show();
                $('#fmCadastroMetas')[0].reset();
                $("#alertInfo").fadeTo(4000, 500).slideUp(500, function () {
                    $("#alertInfo").slideUp(1000);
                });
                if (mesCadastro === 12) {
                    $("#btnBusca").click();
                }
                selecionarMes(parseInt(mesCadastro + 1) + '/' + ano);
            } else if (data.substr(0, 4) === 'EDIT') {
                var url = $("#rotaIndex").text();
                var dialog = bootbox.dialog({
                    title: 'Operação realizada com sucesso!',
                    message: '<p><i class="fa fa-spin fa-spinner"></i> Aguarde, você será redirecionado..</p>'
                });
                dialog.init(function () {
                    window.setTimeout("location.href='" + url + "'", 1500);
                });
            } else {
                $("#alertDanger").show();
                $("#alertDanger").html('Erro ao gravar: <br />' + data);
                $("#alertDanger").fadeTo(4000, 500).slideUp(500, function () {
                    $("#alertDanger").slideUp(5000);
                });
                selecionarMes(parseInt(mesCadastro - 1) + '/' + ano);
            }
            return false;
        },
        error: function (data) {
            $("#alertDanger").show();
            $("#alertDanger").html('Erro desconhecido ao gravar: <br />' + data);
            $("#alertDanger").fadeTo(3000, 500).slideUp(500, function () {
                $("#alertDanger").slideUp(500);
            });
            return false;
        },
        cache: false,
        contentType: false,
        processData: false
    });
    return false;
}

$("#produto_id").on('change', function () {
    enableDisableBtn();
});

$("#btnZeraFiltro").on('click', function () {
    urlBuscaIndex = urlBuscaIndex.replace(':setor_id', '&');
    urlBuscaIndex = urlBuscaIndex.replace(':produto_id', '&');
    urlBuscaIndex = urlBuscaIndex.replace(':data', '');
    window.location = urlBuscaIndex;
});

$("#btnGravar").on('click', function () {
    gravarMetas();
});

$("#btnBusca").on('click', function () {
    urlBuscaIndex = urlBuscaIndex.replace(':setor_id', $("#setor_id").val() + '&');
    urlBuscaIndex = urlBuscaIndex.replace(':produto_id', $("#produto_id").val() + '&');
    urlBuscaIndex = urlBuscaIndex.replace(':data', $("#data").val());
    window.location = urlBuscaIndex;
});
function buscarProdutosAjax() {
    // var urlProduto = root + "produto/buscaporsetor/:id";
    // var id = $("#setor_id").val();
    // urlProduto = urlProduto.replace(':id', id);
    // let $prod = $("#produto_id");
    // $prod.empty();
    // if (id !== '') {
    //     $.ajax({headers: {
    //             'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
    //         },
    //         type: "GET",
    //         url: urlProduto,
    //         success: function (data) {
    //             html = "<option value=''>Selecione</option>";
    //             for (var i = 0; i < data.length; i++) {
    //                 console.log(data[i].id);
    //                 html = html + "<option value='" + data[i].id + "'>" + data[i].descricao + "</option>";
    //             }
    //             $prod.append(html);
    //             let $prodH = $("#hiddenproduto_id");
    //             if ($prodH.val() !== '') {
    //                 $prod.val($('#produto_id option').filter(function () {
    //                     return $(this).val() === $prodH.val();
    //                 }).val());
    //                 $prodH.val('');
    //             }
    //             enableDisableBtn();
    //         },
    //         error: function (data) {
    //             bootbox.alert('Erro ao buscar os produtos');
    //             enableDisableBtn();
    //         },
    //         cache: false,
    //         contentType: false,
    //         processData: false
    //     });
    // }
    //
    // $('#fmCadastroMetas')[0].reset();
    // $prod.trigger("chosen:updated");
    return false;
}

$('#btnProximo').on('click', function () {
    mesCadastro = mesCadastro;
    selecionarMes((mesCadastro) + '/' + ano);
    gravarMetas();
    $("#formproduto_id").val($("#produto_id").val());
    $("#formsetor_id").val($("#setor_id").val());

});
$('#btnFechar').on('click', function () {
    gravarMetas();
    $("#btnBusca").click();
});
$("#btnXCloseModal").on('click', function () {
    $("#btnBusca").click();
});
$('#btnSelecionarData').on('click', function () {
    $("#myModal").modal('show');
    $("#myModalData").modal('hide');
    $("#dataMeta").val($("#mesInicialMeta").val());
    $("#mesInicial").val($("#mesInicialMeta").val());
    var dataAtual = $("#mesInicialMeta").val();
    creating = true;
    selecionarMes(dataAtual);
    $("#formproduto_id").val($("#produto_id").val());
    $("#formsetor_id").val($("#setor_id").val());
});
function selecionarMes(data) {
    data = data.split('/');
    mesCadastro = parseInt(data[0]);
    ano = data[1];
    mesAtual = verificarMes(mesCadastro);
    $('#myModalLabel').text('Metas ' + mesAtual + '/' + ano);
    if (mesCadastro !== 12) {
        $('#mesAtual').val('01/' + parseInt(mesCadastro) + '/' + ano);
        $('#btnProximo').text("Próximo");
    } else {
        $('#mesAtual').val('01/' + parseInt(mesCadastro) + '/' + ano);
        $('#btnProximo').text('Finalizar');
    }
    $("#quantidade").focus();
    $("#dataMeta").val($("#mesInicialMeta").val());
    $("#mesInicial").val($("#mesInicialMeta").val());
}

function verificarMes(mes) {
    switch (mes) {
        case 1:
            mes = 'Jan';
            break;
        case 2:
            mes = 'Fev';
            break;
        case 3:
            mes = 'Mar';
            break;
        case 4:
            mes = 'Abr';
            break;
        case 5:
            mes = 'Maio';
            break;
        case 6:
            mes = 'Jun';
            break;
        case 7:
            mes = 'Jul';
            break;
        case 8:
            mes = 'Ago';
            break;
        case 9:
            mes = 'Set';
            break;
        case 10:
            mes = 'Out';
            break;
        case 11:
            mes = 'Nov';
            break;
        default:
            mes = 'Dez';
    }
    return mes;
}


$('#tblCadastro').on('click', 'button', function () {
    var trElem = $(this).closest("tr"); // grabs the button's parent tr element
    var data = $(trElem).children("td")[1];
    $("#alertSuccess").hide();
    $("#alertDanger").hide();
    $("#alertInfo").hide();
    $('#myModalLabel').text($(data).text());
    $("#btnProximo").hide();
    if ($(this).context.id === 'btnEditar') {
        $("#btnFechar").hide();
        $("#btnCancelar, #btnGravar").show();
        $('#setor').attr('disabled', 'disabled');
    }else if($(this).context.id === 'btnVisualizar'){
        $("#btnFechar").hide();
        $('#fmCadastroMetas :input, #fmCadastroMetas :submit').prop('disabled', true);
        $("#btnCancelar").show().prop('disabled',false);
    }
});

$('.btnNovoCadastro').on('click', function () {
    if (typeof $('#fmCadastroMetas')[0] !== "undefined")
        $('#fmCadastroMetas')[0].reset();
    $('#fmCadastroMetas :input').prop('disabled', false);
    $("#btnProximo").show();
    $("#btnFechar").show();
    $("#produtoSetor").addClass('hidden');
});
$(".modal").on('hide.bs.modal', function () {
    $("#btnGravar").hide();
    $("#btnCancelar").hide();
    $('#tblCadastro').attr('btnClick', 'false');
    $('#btnProximo').prop('disabled', false);
    $('#fmCadastroMetas :input').prop('disabled', false);
    $('#fmCadastroMetas :submit').prop('disabled', true);
});

function enableDisableBtn() {
    if (isEmpty($("#produto_id").val())) {
        $(".btnNovoCadastro").attr('disabled', 'disabled');
    } else {
        $(".btnNovoCadastro").removeAttr('disabled');
    }
}