var tblparc = null;
var tblfreteparc = null;

$(document).ready(function () {
    tblparc = $("#tblparcelas").DataTable({
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
        "columnDefs": [{
                "className": "dt-center",
                "targets": "_all"
            }]
    });

    tblfreteparc = $("#tblfreteparcelas").DataTable({
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
        "columnDefs": [{
                "className": "dt-center",
                "targets": "_all"
            }]
    });
});

function calcularParcelas(idValorTotal, idDataOperacao, idShowEditParcelas, idVista, idBoleto, idCartao, idTblParcelas, idCondicaoparcelas, idCondicao, showModal = true) {
    return new Promise((resolve, reject) => {
        var tblParcelas = null;
        if (typeof idTblParcelas === 'undefined')
            tblParcelas = tblparc;
        else
            tblParcelas = tblfreteparc;
        tblParcelas.clear();

        if (typeof idVista === 'undefined')
            idVista = "vista";
        if (typeof idBoleto === 'undefined')
            idBoleto = "boleto";
        if (typeof idCartao === 'undefined')
            idCartao = "cartao";

        if (typeof idCondicaoparcelas === 'undefined')
            idCondicaoparcelas = "condicaoparcelas";
        if (typeof idCondicao === 'undefined')
            idCondicao = "condicao";

        if (typeof idShowEditParcelas === "undefined") {
            var vista;
            try {
                vista = JSON.parse($("#" + idVista).val());
            } catch (err) {
                vista = [];
            }
            var boleto;
            try {
                boleto = JSON.parse($("#" + idBoleto).val());
            } catch (err) {
                boleto = [];
            }
            var cartao;
            try {
                cartao = JSON.parse($("#" + idCartao).val());
            } catch (err) {
                cartao = [];
            }

            var condicaoparcelas = null;
            var condicao = null;
            try {
                condicaoparcelas = JSON.parse($("#" + idCondicaoparcelas).val());
                condicao = JSON.parse($("#" + idCondicao).val());
            } catch (err) {
                condicaoparcelas = null;
                condicao = null;
            }

            var total = isNaN(parseDinheiro($("#" + idValorTotal).val(), 2)) ? 0 : parseDinheiro($("#" + idValorTotal).val(), 2);
            var date = trazerData($("#" + idDataOperacao).val());

            if (condicaoparcelas != null || condicao != null) {
                if (condicao[0].tipo == 1) {
                    for (var i = 0; i < condicaoparcelas.length; i++) {
                        var dia = condicaoparcelas[i].dias;
                        var dias = parseInt(dia);
                        var percentual = condicaoparcelas[i].percentualvalor;
                        var parpercentual = parseFloat(percentual).toFixed(2);
                        var parper = parseFloat(percentual);
                        var parcelasdate = date.addDays(dias);
                        var data = padronizacaoData(parcelasdate);
                        var valors = ((parper / 100) * total);
                        var valult = (total - valors);
                        if (i === condicaoparcelas.length - 1) {
                            valors = total - valult;
                        }
                        tblParcelas.row.add([
                            i + 1,
                            data,
                            ("R$ " + formataDecimal(valors, 2))
                        ]);
                    }
                } else if (condicao != null) {
                    if (condicao[0].intervalo !== null) {
                        for (var i = 0; i < condicao.length; i++) {
                            num = condicao[i].num_parcelas;
                            var dia = condicao[i].intervalo;
                            var primeirapar = condicao[i].dias_primeira;
                            var dias = parseInt(dia);
                            var primeira = parseInt(primeirapar);
                            var parcelasdate = date.addDays(primeira);
                            for (var j = 0; j < num; j++) {
                                var per = parseFloat(parpercentual);
                                var valors = (total / num);
                                var valult = (total - valors);
                                if (j === num - 1) {
                                    valors = total - valult;
                                }
                                var data = padronizacaoData(parcelasdate);
                                tblParcelas.row.add([
                                    j + 1,
                                    data,
                                    ("R$ " + formataDecimal(valors, 2))
                                ]);
                                var parcelasdate = parcelasdate.addDays(dias);
                            }
                        }
                        tblParcelas
                    } else if (condicao[0].dias_primeira !== null) {
                        for (var i = 0; i < condicao.length; i++) {
                            var num = 1;
                            if (condicao[0].num_parcelas !== null) {
                                num = condicao[i].num_parcelas;
                            }
                            var dia = 0;
                            if (condicao[0].intervalo !== null) {
                                dia = condicao[i].intervalo;
                            }
                            var primeirapar = condicao[i].dias_primeira;
                            // var dias = parseInt(dia);
                            var primeira = parseInt(primeirapar);
                            for (var j = 0; j < num; j++) {
                                var parcelasdate = date.addDays((j + 1) * primeira);
                                var per = parseFloat(parpercentual);
                                var valors = (total / num);
                                var valult = (total - valors);
                                if (j === num - 1) {
                                    valors = total - valult;
                                }
                                var data = padronizacaoData(parcelasdate);
                                tblParcelas.row.add([
                                    j + 1,
                                    data,
                                    ("R$ " + formataDecimal(valors, 2))
                                ]);
                                // var parcelasdate = parcelasdate.addDays(dias);
                            }
                            tblParcelas
                        }
                    } else {
                        for (var i = 0; i < condicao.length; i++) {
                            var dia = condicao[i].dias_primeira;
                            var dias = parseInt(dia);
                            var novadata = date.addDays(dias);
                            var data = padronizacaoData(novadata);
                            tblParcelas.row.add([
                                i + 1,
                                data,
                                ("R$ " + formataDecimal(total, 2))
                            ]);
                        }
                        tblParcelas
                    }
                }
            } else {
                if (cartao.length > 0 && cartao[0].intervalo !== null) {
                    for (var i = 0; i < cartao.length; i++) {
                        num = cartao[i].num_parcelas;
                        var dia = cartao[i].intervalo;
                        var primeirapar = cartao[i].dias_primeira;
                        var dias = parseInt(dia);
                        var primeira = parseInt(primeirapar);
                        var parcelasdate = date.addDays(primeira);
                        for (var j = 0; j < num; j++) {
                            var per = parseFloat(parpercentual);
                            var valors = (total / num);
                            var valult = (total - valors);
                            if (j === num - 1) {
                                valors = total - valult;
                            }
                            var data = padronizacaoData(parcelasdate);
                            tblParcelas.row.add([
                                j + 1,
                                data,
                                ("R$ " + formataDecimal(valors, 2))
                            ]);
                            var parcelasdate = parcelasdate.addDays(dias);
                        }
                    }
                } else if (boleto.length !== 0) {
                    for (var i = 0; i < boleto.length; i++) {
                        var dia = boleto[i].dias;
                        var dias = parseInt(dia);
                        var percentual = boleto[i].percentualvalor;
                        var parpercentual = parseFloat(percentual).toFixed(2);
                        var parper = parseFloat(percentual);
                        var parcelasdate = date.addDays(dias);
                        var data = padronizacaoData(parcelasdate);
                        var valors = ((parper / 100) * total);
                        var valult = (total - valors);
                        if (i === boleto.length - 1) {
                            valors = total - valult;
                        }
                        tblParcelas.row.add([
                            i + 1,
                            data,
                            ("R$ " + formataDecimal(valors, 2))
                        ]);
                    }
                } else {
                    for (var i = 0; i < vista.length; i++) {
                        var dia = vista[i].dias_primeira;
                        var dias = parseInt(dia);
                        var novadata = date.addDays(dias);
                        var data = padronizacaoData(novadata);
                        tblParcelas.row.add([
                            i + 1,
                            data,
                            ("R$ " + formataDecimal(total, 2))
                        ]);
                    }
                }
            }
        } else {
            var parcelas = JSON.parse($("#" + idShowEditParcelas).val());
            for (var i = 0; i < parcelas.length; i++) {
                if (parcelas[i].datavencimento.indexOf('-') !== -1)
                    parcelas[i].datavencimento = requestDataOracle(parcelas[i].datavencimento, false);
                tblParcelas.row.add([
                    i + 1,
                    parcelas[i].datavencimento,
                    ("R$ " + formataDecimal(parcelas[i].valorefetivado, 2))
                ]);
            }
        }
        tblParcelas.draw(false);
        if (showModal)
            showModalParcelas(idTblParcelas);
        resolve();
    });
}

function showModalParcelas(idTblParcelas) {
    let $modal;
    if (typeof idTblParcelas === 'undefined')
        $modal = $('#modalparcelas');
    else
        $modal = $('#modalfreteparcelas');
    if (! $modal.is(":visible")) {
        $modal.modal('show');
    }
}
