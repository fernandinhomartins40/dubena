$(document).ready(function () {
    $("#nfepermite").on("click", function () {
        informarValNfe();
    });
    checarGlp();
    $("#nfecodgen").attr("maxLength", 2);
    $("#nfeextipi").attr("maxLength", 3);
    $("#nfecodlst").attr("maxLength", 5);

    tblOrigem = $("#tblOrigens").DataTable({
        language: {
            url: urlDataTable,
        },
        processing: false,
        bPaginate: false,
        bLengthChange: false,
        bFilter: false,
        bSort: false,
        bInfo: false,
        bAutoWidth: false,
        destroy: true,
        sScrollY: "170",
        columnDefs: [
            {
                targets: [0, 1, 3],
                visible: false,
            },
        ],
    });

    if (error) {
        tratarErros();
    }
});

$("#produtoclasse_id").change(function () {
    checarGlp();
});

$("#fmCadastro").on("submit", function () {
    let origens = [];
    let text = $("#produtoclasse_id option:selected").text();
    $("#produtoclasses_hd").val(text);
    $("#nfcest").val($("#nfcest_id option:selected").attr("cest"));

    tblOrigem.rows().every(function () {
        origens.push(this.data());
    });

    if (origens.length > 0) $("#origensList").val(JSON.stringify(origens));
});

$("#nfecodgen").blur(function () {
    digitosCod($(this));
});

$("#btngravar").click(function (e) {
    validarCampos(e);
});

$("#addOrigem").click(function () {
    addOrigem();
});

$("#tblOrigens tbody").on("click", "#btnRemover", function (e) {
    e.stopPropagation();
    let parent = $(this).parents("tr");

    tblOrigem.row(parent).remove().draw();
});

function informarValNfe() {
    var nfepermite = $("#nfepermite").prop("checked");
    $("select[name=nfipi_id]").prop("disabled", !nfepermite);
    $("select[name=nfipi_id]").trigger("chosen:updated");
    $("input[name=nfealiqipi]").prop("disabled", !nfepermite);
    $("input[name=nfebcipi]").prop("disabled", !nfepermite);
    $("input[name=nfedescricaofiscal]").prop("disabled", !nfepermite);
    $("input[name=ean]").prop("disabled", !nfepermite);
    $("input[name=ncm]").prop("disabled", !nfepermite);
    $("input[name=eantrib]").prop("disabled", !nfepermite);
    $("input[name=especie]").prop("disabled", !nfepermite);
    $("input[name=marca]").prop("disabled", !nfepermite);
    $("input[name=nfecodenquadramentoipi]").prop("disabled", !nfepermite);
    $("select[name=nfgrupofiscal_id]").prop("disabled", !nfepermite).trigger("chosen:updated");
    $("select[name=nfcest_id]").prop("disabled", !nfepermite).trigger("chosen:updated");
    $("input[name=nfecprodanp]").prop("disabled", !nfepermite);
    $("input[name=nfeqbcprod]").prop("disabled", !nfepermite);
    $("input[name=nfevaliqprod]").prop("disabled", !nfepermite);
    $("input[name=nfevcide]").prop("disabled", !nfepermite);
    $("input[name=nfealiqipip]").prop("disabled", !nfepermite);
    $("input[name=nfebcipi]").prop("disabled", !nfepermite);
    $("input[name=nfecodenquadramentoipi]").prop("disabled", !nfepermite);
    $("input[name=origcomb]").prop("disabled", !nfepermite);
    $("input[name=indimport]").prop("disabled", !nfepermite);
    $("input[name=cuforig]").prop("disabled", !nfepermite);
    $("input[name=pgni]").prop("disabled", !nfepermite);
    $("input[name=pgnn]").prop("disabled", !nfepermite);
    $("input[name=pglp]").prop("disabled", !nfepermite);
    $("input[name=porig]").prop("disabled", !nfepermite);
    $("input[name=nfedescanp]").prop("disabled", !nfepermite);
}

function checkVasilhameSimNao(value) {
    if (value === "1") {
        $("#produtoretornavel_id").prop("disabled", false).trigger("chosen:updated");
    } else {
        $("#produtoretornavel_id").prop("disabled", true).trigger("chosen:updated");
    }
}

function ajaxNcmCest(url) {
    var urlajax = url;
    var ncm = $("#ncm").val();
    var urlbuscarcest = url.replace(":id", ncm);
    if (ncm === "") {
        $("#nfcest_id").empty().trigger("chosen:updated");
        return false;
    } else {
        $("#nfcest_id").empty().trigger("chosen:updated");
        $.ajax({
            headers: {
                "X-CSRF-TOKEN": $('meta[name="_token"]').attr("content"),
            },
            url: urlbuscarcest,
            type: "GET",
            success: function (data) {
                var cestselect = $("#nfcest_id");
                if (data.length === 0) {
                    $("#nfcest_id").empty().trigger("chosen:updated");
                }
                for (var i = 0; i < data.length; i++) {
                    var cest = data[i].cest;
                    var descricao = data[i].descricao;
                    var id = data[i].id;
                    cestselect.append(
                        "<option value='" + id + "' cest='" + cest + "'>" + cest + " | " + descricao + "</option>"
                    );
                }
                cestselect.trigger("chosen:updated");
            },
            error: function (data) {
                $("#nfcest_id").empty().trigger("chosen:updated");
            },
        });
    }
}

function validarCampos(e) {
    var validado = true;
    var glp = false;
    var tipoglp = $("#tipo_glp").val();
    var precominimo = $("#precovendaminimo").val();
    var customedio = $("#customedio").val();
    var precovenda = $("#precovenda").val();
    var classeglp = $("#classeglp").val();
    var classe = $("#produtoclasse_id").val();

    if (!isEmpty(classeglp) && !isEmpty(classe)) {
        glp = classeglp.includes(classe);
    }

    if (!isEmpty(precominimo) && !isEmpty(precovenda)) {
        var min = parseDinheiro(precominimo);
        var venda = parseDinheiro(precovenda);
        if (min > 0 && venda > 0) {
            if (min > venda) {
                validado = false;
            }
        }
    }
    if (!isEmpty(customedio) && !isEmpty(precovenda)) {
        var medio = parseDinheiro(customedio);
        var venda = parseDinheiro(precovenda);
        if (medio > 0 && venda > 0) {
            if (medio > venda) {
                validado = false;
            }
        }
    }
    if (isEmpty(tipoglp) && glp) {
        e.preventDefault();
        bootbox.alert("Por favor, selecione o tipo de GLP!");
    }
    if (!validado) {
        e.preventDefault();
        bootbox.alert("O custo médio e o preço mínimo não pode ser maior que o preço de venda.");
    }
}

function checarGlp() {
    var classeglp = $("#classeglp").val();
    var classe = $("#produtoclasse_id").val();
    var glp = false;
    if (!isEmpty(classeglp) && !isEmpty(classe)) {
        glp = classeglp.includes(classe);
    }

    if (glp) {
        $("#tipoglp").removeClass("hidden");
    } else {
        $("#tipoglp").addClass("hidden");
        $("#tipo_glp").val("").trigger("chosen:updated");
    }
}

function digitosCod(myself) {
    var cod = myself.val();
    if (cod != "0" && cod.length < 2) {
        var myVal = "0" + cod;
        myself.val(myVal);
    }
}

function addOrigem() {
    let $indimport = $("input[name='indimport']:checked");
    let $cuf = $("#cuforig option:selected");
    let $porig = $("#porig");
    let existe = checkExistencia($cuf.val());

    if (existe) return;

    tblOrigem.row
        .add([
            null,
            $indimport.val(),
            $indimport.val() == 0 ? "Nacional" : "Importado",
            $cuf.val(),
            $cuf.text(),
            $porig.val(),
            "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover' style='max-height:30px;font-size:12px;margin-top:-5px;'>Remover</button>",
        ])
        .draw();

    $("input[name='indimport'][value=0]").prop("checked", true);
    $porig.val("");
    $("#cuforig").val("");
    $("#cuforig").trigger("chosen:updated");
}

function checkExistencia(cuf) {
    if (!tblOrigem.rows().any()) return false;

    let existe = false;
    tblOrigem
        .rows()
        .eq(0)
        .each(function (i) {
            let row = tblOrigem.row(i);
            let data = row.data();

            if (data[2] == cuf) {
                existe = true;
                return;
            }
        });

    return existe;
}

function tratarErros() {
    setTimeout(() => {
        if ($.fn.dataTable.isDataTable("#tblOrigens") && $.fn.dataTable.isDataTable("#tblOrigens")) {
            carregarOrigemErro();
        }
    }, 100);
}

function carregarOrigemErro() {
    let origens = $("#origensList").val();
    if (!isEmpty(origens)) {
        tblOrigem.clear().rows.add(JSON.parse(origens)).draw();
    }
}
