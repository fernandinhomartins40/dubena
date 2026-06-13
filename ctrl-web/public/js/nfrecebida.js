let changedChaveByCopy = false;
let arrayProductsXml = [];
let tblProdutosXML;
let tagCombXml;
let actualProductXml = null;
function onDocReady(callback) {
    let xml = $("#xml_import_json").val();
    if ((erros || editOrShow) && xml) {
        nfeImporting = JSON.parse(xml);
    }
    let $tipoLanc = $("#tipolancamento");
    if (! editOrShow && !erros) {
        $tipoLanc.val("1").trigger("chosen:updated");
    }
    changeTipoLancamento(true);
    $("#chaveacesso").on('input', function () {
        let len = $(this).val().length;
        if (len === 44) {
            changedChaveByCopy = true;
            setTimeout(function () {
                changedChaveByCopy = false;
            }, 5000);
            fillFieldsByChaveAcesso($(this));
        }
        if (len === 0) {
            changedChaveByCopy = false;
            clearFieldsByChaveAcesso($(this));
        }
    }).on('change', function () {
        if (!changedChaveByCopy) {
            fillFieldsByChaveAcesso($(this));
        }
    });

    $("#produtosxml_import").on("change", function () {
        loadItemImport($(this).val());
    });

    $("#nfmodelo").change(function () {
        let nfmodelo = $("#nfmodelo").val();
        let ambiente55 = $("#ambiente55").val();
        let ambiente65 = $("#ambiente65").val();
        if (nfmodelo === "55") {
            $("#nftipoambiente").val(ambiente55).trigger('chosen:updated');
        } else if (nfmodelo === "65") {
            $("#nftipoambiente").val(ambiente65).trigger('chosen:updated');
        }
        changeIndFinal();
        changeIdDest();
    });

    $tipoLanc.on('change', function () {
        changeTipoLancamento();
    });
    $("#btnImportXml").on("click", importXml);
    setImportSettings();
    if (typeof callback === "function")
        callback();
}

function clearFieldsByChaveAcesso($self) {
    let $nfnumero = $("#nfnumero");
    let $nfserie = $("#nfserie");
    let $nfmodelo = $("#nfmodelo");
    let $nftipoemissao = $("#nftipoemissao");
    $nfnumero.val("");
    $nfserie.val("");
    $nfmodelo.val("");
    $nftipoemissao.val("");
    $self.val("");
    clearNomeCliente();
}

function fillFieldsByChaveAcesso($self) {
    let selfChaveAcesso = $self.val();
    let $nfnumero = $("#nfnumero");
    let $nfserie = $("#nfserie");
    let $nfmodelo = $("#nfmodelo");
    let $nftipoemissao = $("#nftipoemissao");
    if (selfChaveAcesso !== "") {
        if (!validateLengthChaveAcesso($self)) {
            hasChaveAcesso = false;
            clearFieldsByChaveAcesso($self);
            return;
        }
        let dv = generateDVNF(selfChaveAcesso.substring(0, 43));
        if (dv === -1) {
            return;
        }
        if (dv !== parseInt(selfChaveAcesso.substring(43, 44))) {
            bootbox.alert("Atenção: o dígito verificador da chave de acesso é inválido.");
            hasChaveAcesso = false;
            clearFieldsByChaveAcesso($self);
            return;
        }
//            var chaveUF = chaveacesso.substring(0, 2);//2
//            var chaveAAMM = chaveacesso.substring(2, 6);//4
        let chaveCNPJ = selfChaveAcesso.substring(6, 20);//14
        let txtModeloNF = selfChaveAcesso.substring(20, 22);//2
        let txtSerieNF = selfChaveAcesso.substring(22, 25);//3
        let txtNrNFe = selfChaveAcesso.substring(25, 34);//9
        let txtTpEmiNF = selfChaveAcesso.substring(34, 35);//1
        hasChaveAcesso = true;
        getFornecedorByCNPJ(chaveCNPJ);
        $nfnumero.val(parseInt(txtNrNFe));
        $nfserie.val(parseInt(txtSerieNF));
        $nfmodelo.val(parseInt(txtModeloNF));
        $nftipoemissao.val(parseInt(txtTpEmiNF));
    } else {
        hasChaveAcesso = false;
        setTimeout(function () {
            $("#nfnumero").focus();
        }, 100);
    }
    enableDisableFieldsbyChaveAcesso();
}

function enableDisableFieldsbyChaveAcesso() {
    let $nfnumero = $("#nfnumero");
    let $nfserie = $("#nfserie");
    let $nfmodelo = $("#nfmodelo");
    let $nftipoemissao = $("#nftipoemissao");
    if (hasChaveAcesso) {
        $nfnumero.prop("readonly", true).prop("tabindex", -1);
        $nfserie.prop("readonly", true).prop("tabindex", -1);
        $nfmodelo.prop("readonly", true).prop("tabindex", -1);
        $nftipoemissao.prop("readonly", true).prop("tabindex", -1);
    } else {
        $nfnumero.removeAttr("readonly").removeAttr("tabindex");
        $nfserie.removeAttr("readonly").removeAttr("tabindex");
        $nfmodelo.removeAttr("readonly").removeAttr("tabindex");
        $nftipoemissao.removeAttr("readonly").removeAttr("tabindex");
    }
}

function generateDVNF(chave) {
    let Digito = -1;
    let peso = "4329876543298765432987654329876543298765432";

    chave = chave.replace("NFe", "");
    if (chave.length !== 43) {
        bootbox.alert("Erro na composição da chave para obter o DV: (" + chave.length + ")");
        return Digito;
    }

    try {
        let j = 0;
        for (let i = 0; i < 43; ++i) {
            j += parseInt(chave.substring(i, i + 1)) * parseInt(peso.substring(i, i + 1));
        }
        Digito = 11 - (j % 11);
        if ((j % 11) < 2) {
            Digito = 0;
        }
    } catch (err) {
        Digito = -1;
        console.log(err);
    }
    if (Digito === -1) {
        bootbox.alert("Erro no cálculo do DV");
    }
    return Digito;
}

function getFornecedorByCNPJ(cnpj) {
    showLoaderAjax("Aguarde", "Carregando " + descriptionForClient, false);
    ajaxGenerator(root + "/api/getFornecedorByCNPJ/" + cnpj, "GET", function (data) {
        if (data.status === "OK") {
            let $clienteNomeErro = $("#cliente_nome_erro");
            let $clienteIdErro = $("#cliente_id_erro");
            let $clienteId = $("#cliente_id");
            $clienteNomeErro.val(data.msg);
            $clienteIdErro.val(data.id);
            $clienteId.val(data.id);
            let $nomeCliente = $("#nomecliente");
            let select = $nomeCliente.selectize()[0].selectize;
            select.clearOptions();
            select.refreshOptions(true);
            select.refreshItems();
            select.addOption([{
                nomecompleto: data.msg,
                nome: data.msg,
                id: data.id
            }]);
            select.addItem(data.id);
            //timeout necessário porque o plugin selectize acaba tendo um delay e lima os campos do destinatário ao carregar os items
            setTimeout(function () {
                $clienteNomeErro.val(data.msg);
                $clienteIdErro.val(data.id);
                $clienteId.val(data.id).trigger("change");
            }, 100);
            hideLoaderAjax();
        } else {
            hideLoaderAjax(function () {
                bootbox.alert("Nenhum fornecedor encontrado com o CNPJ: " + cnpj.mask("##.###.###/####-##"), function () {
                    clearFieldsByChaveAcesso($("#chaveacesso"));
                });
            });
        }
    }, function () {
        hideLoaderAjax(function () {
            bootbox.alert("Erro ao buscar " + descriptionForClient);
        });
    });
}

function changeTipoLancamento(onLoad) {
    let $chaveAcesso = $("#chaveacesso");
    if ( !onLoad) {
        clearFieldsByChaveAcesso($chaveAcesso);
        tblProdutos.clear().draw();
    }
    let $lanc = $("#tipolancamento");
    let html;
    //0 = emissão propria
    //1 = emissão terceiros
    if ($lanc.val() === "1") {
        $("#nfsituacao_id").val("100").prop("disabled", true).trigger("chosen:updated");
        $chaveAcesso.prop("readonly", false).removeAttr("tabindex");
        html = $("#operacaorecebida").html();
        operacoesDB = JSON.parse($("#objOperacoesRecebidas").val());
        // noinspection JSValidateTypes
        descriptionForEmpresa = "Destinatário";
        // noinspection JSValidateTypes
        descriptionForClient = "Emitente";
    } else {
        $("#nfsituacao_id").prop("disabled", false).trigger("chosen:updated");
        $chaveAcesso.prop("readonly", true).prop("tabindex", -1);
        $("#nfmodelo").removeAttr("readonly");
        html = $("#operacaoemitida").html();
        operacoesDB = JSON.parse($("#objOperacoesEmitidas").val());
        descriptionForClient = "Destinatário";
        descriptionForEmpresa = "Emitente";
    }
    $("#anchorTab3").text(descriptionForClient);
    $("#anchorTab2").text(descriptionForEmpresa);
    $("label[for='cliente_id']").text(descriptionForClient + ":");
    $("label[for='empresa_id']").text(descriptionForEmpresa + ":");
    $(".selectize-input").find("input").attr("placeholder", "Buscar " + descriptionForClient);
    $("#nfoperacao_id").empty().append(html);
    $("#nfoperacao_id_2").empty().append(html).trigger("chosen:updated");
    changePrevOperacao();
    enableDisableFieldsbyChaveAcesso();
    if (onLoad) {
        hasChaveAcesso = false;
    } else {
        clearFieldsByChaveAcesso($chaveAcesso);
    }
}

function changePrevOperacao() {
    $("#nfoperacao_id").val($("#prev_operacao_id").val()).trigger("chosen:updated");
}

function importXml() {
    $("#modal-upload-file").modal('show');
}

function setImportSettings() {
    initTableImport();

    $(".btnCloseImportProductsXml").on("click", cancelImportXML);
    $("#addProdutosXML").on("click", btnAddProdClickEv);
    $("#produtosxml_import").on("change", prodXmlChangeEv);
    $("#btnContinueImportProductsXml").on("click", afterImportProductsClickEv);
    $("#btnSaveImportXml").on("click", saveImportClickEv);

    validFormatUpload = ['xml'];
    $("#file-upload").attr('accept', '.xml');

    callbackUpload = function () {
        let url = root + '/nfrecebida.import.xml';
        let $fmUp = $("#fmUpload");
        let formData = new FormData($fmUp[0]);

        $fmUp.off().on('submit', function () {
            return false;
        });
        if (isEmpty($("#file-upload").val())) {
            bootbox.alert('Selecione um arquivo');
            return;
        }
        //emissão de terceiros
        $("#tipolancamento").val(1).trigger("change");
        importXML(url, formData);
    }
}

function saveImportClickEv() {
    let selected = $("#condicaopagamento_import").intVal();
    if (! selected) {
        bootbox.alert("Selecione a condição de pagamento");
        return;
    }
    $("#condicaopagamento_id").val(selected).trigger("change").trigger("chosen:updated");
    $("#modalFinanceiroXml").modal("hide");
    actualProductXml = null;

    bootbox.alert("Para finalizar, selecione as opções de Centro de Custo/Plano de Contas do Financeiro e Frete.", function () {
        $("a [href='#tab_6']").tab("show");
    });
}

function afterImportProductsClickEv() {
    if (countNotAddedProducts() > 0) {
        bootbox.alert("Antes de continuar, vincule todos os produtos!");
        return;
    }
    $("#modalImportProductsXml").modal("hide");
    $("#condicaopagamento_import").html($("#condicaopagamento_id").html()).trigger("chosen:updated");
    try {
        $("#vdesc_import").val("R$ " + formataDecimal(nfeImporting.cobr.fat.vDesc, 2));
        $("#vliq_import").val("R$ " + formataDecimal(nfeImporting.cobr.fat.vLiq, 2));
        $("#vorig_import").val("R$ " + formataDecimal(nfeImporting.cobr.fat.vOrig, 2));
    } catch (e) {
        console.log(e);
        $("#vdesc_import").val("R$ 0,00");
        $("#vliq_import").val("R$ 0,00");
        $("#vorig_import").val("R$ 0,00");
    }
    $("#modalFinanceiroXml").modal("show");
}

function prodXmlChangeEv() {
    let prod_id = $(this).val();
    if (! prod_id) {
        return;
    }
    let prod = arrayProductsXml.filter(function (el) {
        if (! el.attributes) {
            bootbox.alert("Ocorreu um erro ao tentar validar o item, atualize a página e tente novamente.");
            return false;
        }
        return prod_id === el.attributes.nItem;
    });
    if (! $.isArray(prod)) {
        bootbox.alert("Ocorreu um erro ao tentar validar o item, atualize a página e tente novamente.");
        return false;
    }
    actualProductXml = prod.first();
}

function btnAddProdClickEv() {
    let nfoperacao_empty = $("#nfoperacao_import").intVal() === 0;
    let setor_empty = $("#setor_import").intVal() === 0;
    let produtos_empty = $("#produtos_import").intVal() === 0;
    let produtosxml_empty = $("#produtosxml_import").intVal() === 0;
    let msg = "O campo :field é obrigatório";
    let hasError = nfoperacao_empty || setor_empty || produtos_empty || produtosxml_empty;
    if (nfoperacao_empty) {
        msg = msg.replace(":field", "Operação");
    }
    if (setor_empty) {
        msg = msg.replace(":field", "Setor");
    }
    if (produtos_empty) {
        msg = msg.replace(":field", "Produto Correspondente");
    }
    if (produtosxml_empty) {
        msg = msg.replace(":field", "Produto XML");
    }
    if (hasError) {
        bootbox.alert(msg);
        return;
    }
    calcularTotais(true, function (res) {
        if (! res) {
            bootbox.alert("Erro desconhecido.");
            return;
        }
        var totStr = res[0];
        var totF = res[1];
        if (compareImpostoXML(totF, totStr)) {
            addProdutoImport();
        }
    }, true);
}

function importXML(url, formData) {
    showLoaderAjax("Aguarde", "Carregando XML", false);
    ajaxGenerator(url, "POST", function (result) {
        let msg;
        if (typeof result.msg !== "undefined") {
            msg = result.msg;
        }
        if (typeof result.status === "string" && result.status === "OK|") {
            $("#modal-upload-file").modal("hide");
            confirmOperacaoImport(result);
        } else {
            bootbox.alert(msg ? msg : "Erro desconhecido ao tentar a importação do arquivo");
        }
    }, function (result) {
        let msg;
        if (typeof result.msg !== "undefined") {
            msg = result.msg;
        }
        bootbox.alert(msg ? msg : "Erro desconhecido ao tentar a importação do arquivo");
    }, formData, true, function () {
        hideLoaderAjax();
    });
}

function confirmOperacaoImport(result) {
    let htmlOp = generateHtml("select", "nfoperacaop_import", $("#nfoperacao_id").html());
    bootboxConfirm("Informe a operação principal", htmlOp, function (confirm) {
        if (confirm) {
            let op = $("#nfoperacaop_import").intVal();
            if (! op) {
                bootbox.alert("Selecione a operação!", function () {
                    confirmOperacaoImport(result);
                });
                return;
            }
            $("#nfoperacao_id").val(op);
            fillXmlImport(result.data);
        } else {
            cancelImportXML(function () {
                confirmOperacaoImport(result);
            });
        }
    }, "Continuar", "Cancelar");
}

function fillXmlImport(data) {
    let nfe = data.nfe;
    $("#xml_import_json").val(JSON.stringify(data.nfe));
    $("#xml").val(data.xml);
    nfeImporting = nfe;

    let id = nfe.attributes.Id;

    $("#chaveacesso").val(id.substr(3, id.length)).trigger("change");
    let callback = function () {
        fillIdeXmlImport(nfe);
        fillTotalXmlImport(nfe.total.ICMSTot);

        loadItemsImport(nfe);
    };
    if (nfe.transp.modFrete !== "9") {
        fillFreteXmlImport(nfe, data.fretecliente_id, callback);
    } else {
        callback();
    }
}

function fillIdeXmlImport(nfe) {
    let ide = nfe.ide;
    $("#datahoraemissao").val(ide.dhEmi).trigger("change");
    $("#datahoraentradasaida").val(ide.dhSaiEnt).trigger("change");
    $("#informacaocomplementar").val(nfe.infAdic.infCpl).trigger("change");
    $("#nfefinalidade").val(ide.finNFe).trigger("change");
}

function loadItemsImport(nfe) {
    let htmlItems = "";
    if ($.isArray(nfe.det)) {
        $.each(nfe.det, function (i, el) {
            htmlItems += addItemImport(el);
        });
    } else {
        htmlItems += addItemImport(nfe.det);
    }
    $("#produtos_import").html($("#produto_id").html()).trigger("chosen:updated").trigger("change");
    $("#produtosxml_import").html(htmlItems).trigger("chosen:updated").trigger("change");
    $("#nfoperacao_import").html($("#nfoperacao_id_2").html()).val($("#nfoperacao_id").val()).trigger("chosen:updated");
    $("#setor_import").html($("#setor_id").html()).trigger("chosen:updated");
    $("#modalImportProductsXml").modal("show");
}

function fillTotalXmlImport(icms) {
    $("#vfrete").val("R$ " + formataDecimal(icms.vFrete, 2)).trigger("blur");
    $("#vdesc").val("R$ " + formataDecimal(icms.vDesc, 2));
    $("#voutro").val("R$ " + formataDecimal(icms.vOutro, 2));
    $("#vseg").val("R$ " + formataDecimal(icms.vSeg, 2));
}

function fillFreteXmlImport(nfe, fretecliente_id, callback) {
    $("#fretecliente_id").val(fretecliente_id).trigger("chosen:updated").trigger("change");
    $("#fretemodalidade").val(nfe.transp.modFrete).trigger("chosen:updated").trigger("change");

    if (typeof nfe.transp.veicTransp !== "undefined") {
        $("#freteplaca").val(nfe.transp.veicTransp.placa);
        $("#freteplacauf").val(nfe.transp.veicTransp.UF);
    }
    if (parseFloat(nfe.total.ICMSTot.vFrete)) {
        confirmFinanceiroFrete(callback);
    } else {
        callback();
    }
}

function confirmFinanceiroFrete(callback) {
    let selectOptions = $("#formapagamento").html();
    let html = generateHtml("select", "formapagamento_import", selectOptions);
    bootboxConfirm("Gerar financeiro do frete?", html, function (confirm) {
        let $formaPgto = $("#formapagamento");
        let selected = $("#formapagamento_import").val();
        $formaPgto.val(selected).trigger("change");
        if (confirm) {
            if ($formaPgto.intVal() > 0) {
                confirmCondPgtoFreteImport(callback);
            } else {
                callback();
            }
        } else {
            cancelImportXML(function () {
                confirmFinanceiroFrete(callback);
            });
        }
    }, "Continuar", "Cancelar");
}

function confirmCondPgtoFreteImport(callback) {
    let $condPgtoFrete = $("#fretecondicaopagamento_id");
    let selectOptions = $condPgtoFrete.html();
    let html = generateHtml("select", "fretecondicaopagamento_import", selectOptions);
    bootboxConfirm("Informe a condição de pagamento do frete.", html, function (confirm) {
        let selected = $("#fretecondicaopagamento_import").val();
        if (confirm) {
            if (! selected) {
                bootbox.alert("Informe a condição de pagamento do frete", function () {
                    confirmCondPgtoFreteImport(callback);
                });
                return;
            }
            $condPgtoFrete.val(selected).trigger("change");
            callback();
        } else {
            cancelImportXML(function () {
                confirmCondPgtoFreteImport(callback);
            });
        }
    }, "Continuar", "Cancelar");
}

function addItemImport(item) {
    arrayProductsXml.push(item);
    return appendOption(item.attributes.nItem, "nItem " + item.attributes.nItem + " - " + item.prod.xProd);
}

function appendOption(value, html) {
    return "<option value='" + value + "'>" + html + "</option>";
}

function generateHtml(type, id, content) {
    if (type === "select") {
        let select = $("<select>", {id: id, class: "selectChosen"}).html(content)[0];
        let $div1 = $("<div>", {class: "col-sm-offset-1"}).html(select)[0];
        let $div2 = $("<div>").html($div1);
        let html = $div2.html();
        html += '<script>let $selectCreated = $("#' + id + '");' +
            '$selectCreated.chosen({no_results_text: "nenhum registro encontrado", placeholder_text_single: "Selecione", width: "90%"});' +
            'setTimeout(function (){$selectCreated.focus().trigger("chosen:activate")}, 500)</script>';
        return html;
    } else {
        return "<div></div>";
    }
}

function cancelImportXML(cancel) {
    bootboxConfirm("Atenção", "Tem certeza que deseja cancelar a importação?", function (result) {
        if (result) {
            // nfeImporting = null;
            // arrayProductsXml = [];
            let $modalProd = $("#modalImportProductsXml").modal("hide");
            let $modalFin = $("#modalFinanceiroXml").modal("hide");
            if ($modalProd.is(":visible")) {
                $modalProd.modal("hide")
            } else if ($modalFin.is(":visible")) {
                $modalProd.modal("hide");
            }

            // reload();
        } else {
            if (typeof cancel === "function") {
                cancel();
            }
        }
    });
}

function loadItemImport(item) {
    $.each(arrayProductsXml, function (i, el) {
        if (el.attributes.nItem === item) {
            $("#produto_valor_import").val(formataDecimal(el.prod.vUnCom, 2));
            $("#produto_quantidade_import").val(formataDecimal(el.prod.qCom, 2));
            $("#produto_total_trib").val(formataDecimal(el.imposto.vTotTrib, 2));
            return false;
        }
    });
}

function initTableImport() {
    let $tbl = $("#tblProdutosXML");
        tblProdutosXML = $tbl.DataTable({
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
            "sScrollY": "200px",
            "columnDefs": [{
                "targets": [0],
                "visible": false
            }]
        });
    $tbl.on('click', 'button', btnRemoveProdXmlClickEv);
}

function btnRemoveProdXmlClickEv() {
    let $self = $(this);
    let trElem = $self.closest("tr");
    let nItem = parseInt($($(trElem).children("td")[0]).text());
    nItem = nItem ? nItem : 0;
    if (nItem && $self.hasClass("btnRemoverProdutoXml")) {
        $("#produtosxml_import").find("option").map(function () {
            let $self = $(this);
            if ($self.intVal() === nItem) {
                $self.prop("disabled", false).trigger("chosen:updated");
            }
        }).trigger("chosen:updated");
        tblProdutosXML.row($self.parents('tr')).remove().draw();
        let $button = $("[nitem='" + nItem + "']");
        tblProdutos.row($button.parents("tr")).remove().draw();
    }
}

function compareImpostoXML(tot, totStr) {

    let prod = actualProductXml.prod;
    let imp = actualProductXml.imposto;
    let icms = imp.ICMS ? imp.ICMS[Object.keys(imp.ICMS)[0]] : {};
    let ipi = imp.IPI ? imp.IPI[Object.keys(imp.IPI)[0]] : {};
    let totalXml = formataDecimal(prod.vProd, 5).moneyToFloat() -
        formataDecimal(prod.vDesc, 5).moneyToFloat() -
        formataDecimal(icms.vICMSDeson, 5).moneyToFloat() +
        formataDecimal(icms.vICMSST, 5).moneyToFloat() +
        formataDecimal(icms.vFCPST, 5).moneyToFloat() +
        formataDecimal(prod.vFrete, 5).moneyToFloat() +
        formataDecimal(prod.vSeg, 5).moneyToFloat() +
        formataDecimal(prod.vOutro, 5).moneyToFloat() +
        formataDecimal(ipi.vIPI, 5).moneyToFloat();

    if (formataDecimal(totalXml, 4) !== formataDecimal(tot.vnf, 4)) {
        bootbox.alert("Valores calculados pelo sistema não conferem com valores importados do xml. " +
            "Verifique no cadastro de imposto e tente adicionar o produto novamente. " +
            "Calculado: \"" + totStr.vnf + "\" Informado no XML: \" R$ " + formataDecimal(totalXml, 2) + "\"");
        return false;
    } else {
        return true;
    }
}

function addProdutoImport() {
    let $op = $("#nfoperacao_import");
    let $setor = $("#setor_import");
    let $prod = $("#produtos_import");
    let $prodXml = $("#produtosxml_import");
    let $valor = $("#produto_valor_import");
    let $qtde = $("#produto_quantidade_import");
    tblProdutosXML.row.add([
        $prod.val(),
        $prodXml.val(),
        $prod.find("option:selected").text(),
        $valor.val(),
        $qtde.val(),
        $op.find("option:selected").text(),
        $setor.find("option:selected").text(),
        '<button class="btn btn-nw-registro btn-xs btnRemoverProdutoXml" type="button">Remover</button>'
    ]).draw(false);

    $("#nfoperacao_id_2").val($op.val()).trigger("change");
    $("#setor_id").val($setor.val()).trigger("change");
    $("#produto_id").val($prod.val()).trigger("change");
    $("#produto_quantidade").val($qtde.val()).trigger("change");
    $("#produto_valor").val($valor.val()).trigger("change");

    $("#pGNi").val(formataDecimal(tagCombXml.pGNi, 4)).trigger("change");
    $("#pGNn").val(formataDecimal(tagCombXml.pGNn, 4)).trigger("change");
    $("#pGLP").val(formataDecimal(tagCombXml.pGLP, 4)).trigger("change");
    try {
        $("#qVol").val(nfeImporting.transp.vol.qVol).trigger("change");
        $("#pesoL").val(formataDecimal(nfeImporting.transp.vol.pesoL, 3)).trigger("change");
        $("#pesoB").val(formataDecimal(nfeImporting.transp.vol.pesoB, 3)).trigger("change");
    } catch (e) {
        console.log(e);
        $("#qVol").val(0).trigger("change");
        $("#pesoL").val("0,0000").trigger("change");
        $("#pesoB").val("0,0000").trigger("change");
    }

    let oldVal = $prodXml.intVal();
    addProdutosClick(oldVal);
    $prod.val("").trigger("change");
    $setor.val("").trigger("change");
    $prodXml.find("option:selected").prop("disabled", true);
    $prodXml.val(oldVal + 1).trigger("change");
    $setor.focus().trigger("chosen:activate");

    $("select").trigger("chosen:updated");

    notifyUser(countNotAddedProducts() === 0);
}

function notifyUser(show) {
    let $alert = $("#notify-user");
    if (! show) {
        $alert.hide();
    } else {
        $alert.show().fadeTo(3000, 500).slideUp(500, function () {
            $alert.slideUp(500);
        });
    }
}

function countNotAddedProducts() {
    let length = 0;

    $("#produtosxml_import").find("option").each(function () {
        if (! $(this).prop("disabled")) {
            length++;
        }
    });
    return length;
}

function setValuesComb(pGNi, pGNn, pGLP) {
    tagCombXml = {"pGNi": pGNi, "pGNn": pGNn, "pGLP": pGLP};
}

let confirmNewImposto = function (res) {
    if (res.includes("Não foi encontrado nenhum imposto")) {
        let msg = "Não foi encontrado nenhum imposto para a operação e produto, deseja cadastrar um novo?";
        bootboxConfirm("Atenção!", msg, function (confirm) {
            if (confirm) {
                newImposto();
            }
        });
    } else {
        bootbox.alert(res);
    }
};

function newImposto() {
    let imp = actualProductXml.imposto;
    let url = root + "/nfimposto/createByNf?";
    url += mountUrlImposto(imp, "COFINS");
    url += mountUrlImposto(imp, "PIS");
    url += mountUrlImposto(imp, "ICMS");
    url += appendUrl("origem_uf", nfeImporting.emit.enderEmit.UF);
    url += appendUrl("destino_uf", nfeImporting.dest.enderDest.UF);
    url += appendUrl("nfoperacao_id", $("#nfoperacao_import").val());
    url += appendUrl("produto_id", $("#produtos_import").val());
    window.open(url, "_blank");
    bootbox.alert("Após realizar o cadastro, clique no botão de adicionar novamente!");
}

function mountUrlImposto(imp, tag) {
    switch (tag) {
        case "COFINS":
            return mountUrlPisCofins(imp.COFINS, "cofins");
        case "PIS":
            return mountUrlPisCofins(imp.PIS, "pis");
        case "ICMS":
            return mountUrlICMS(imp.ICMS);
        default:
            console.error("imposto não selecionado");
            return "";
    }
}

function mountUrlICMS(tagIcms) {
    let childTag = Object.keys(tagIcms)[0];
    let tag = tagIcms[childTag];
    let url = "";
    let fcp = formataDecimal(tag.pFCP ? tag.pFCP : (tag.pFCPST ? tag.pFCPST : "0"), 2);
    let modalidadebcicmsst = (tag.modBCST ? tag.modBCST : "");
    let modalidadebcicms = (tag.modBC ? tag.modBC : "");
    let aliqicmsst = formataDecimal(tag.pICMSST ? tag.pICMSST : "0", 2);
    let nficmsaliq = formataDecimal(tag.pICMS ? tag.pICMS : "0", 2);
    let nficmsbase = formataDecimal(100 - (tag.pRedBC ? tag.pRedBC : 0), 3);
    let nficmsbasest = formataDecimal(100 - (tag.pRedBCST ? tag.pRedBCST : 0), 3);
    let nfmotdesonicms = (tag.motDesICMS ? tag.motDesICMS : "");
    let mva = formataDecimal(tag.pMVAST ? tag.pMVAST : "", 2);
    let ufDest = actualProductXml.imposto.ICMSUFDest;

    url += appendUrl("cst_icms", (tag.CST ? tag.CST : tag.CSOSN));
    //PF
    url += appendUrl("pftaxafecopgr", fcp);
    url += appendUrl("modalidadebcicmsstpf", modalidadebcicmsst);
    url += appendUrl("modalidadebcicmspf", modalidadebcicms);
    url += appendUrl("pfnficmsaliq", nficmsaliq);
    url += appendUrl("pfnficmsbase", nficmsbase);
    url += appendUrl("pfnfmotdesonicms", nfmotdesonicms);
    //PF - UF
    if (nfeImporting.emit.enderEmit.UF !== nfeImporting.dest.enderDest.UF) {
        url += appendUrl("estadopfnficmsaliq", nficmsaliq);
        if (ufDest) {
            url += appendUrl("pfaliqicmsdest", formataDecimal(ufDest.pICMSUFDest, 2));
        }
        url += appendUrl("estadopfnficmsabase", nficmsbase);
        url += appendUrl("estadopfmodicms", modalidadebcicms);
        url += appendUrl("estadopfmodicmsst", modalidadebcicmsst);
        url += appendUrl("estadopftxafecop", fcp);
        url += appendUrl("pfestadosnfmotdesonicms", nfmotdesonicms);
    }
    //PJ
    url += appendUrl("nficmsaliq", nficmsaliq);
    url += appendUrl("nficmsbase", nficmsbase);
    url += appendUrl("modalidadebcicms", modalidadebcicms);
    url += appendUrl("taxafecop", fcp);
    url += appendUrl("modalidadebcicmsst", modalidadebcicmsst);
    url += appendUrl("aliqicmsst", aliqicmsst);
    url += appendUrl("nficmsbasest", nficmsbasest);
    url += appendUrl("mva", ! isSN ? mva : "0,00");
    url += appendUrl("mvareduzido", isSN ? mva : "0,00");
    url += appendUrl("nfmotdesonicms", nfmotdesonicms);
    //PJ - UF
    if (nfeImporting.emit.enderEmit.UF !== nfeImporting.dest.enderDest.UF) {
        url += appendUrl("estadosnficmsaliq", nficmsaliq);
        url += appendUrl("estadosnficmsbase", nficmsbase);
        url += appendUrl("estadosmodicms", modalidadebcicms);
        url += appendUrl("estadostaxafecop", fcp);
        url += appendUrl("estadosmodicmsst", modalidadebcicmsst);
        url += appendUrl("estadosaliqicmsst", aliqicmsst);
        url += appendUrl("estadopjmva", mva);
        url += appendUrl("estadonficmsbasest", nficmsbasest);
        url += appendUrl("estadosnfmotdesonicms", nfmotdesonicms);
    }
    return url;
}

function mountUrlPisCofins(tagPisCofins, type) {
    let obj = getTagPisCofins(tagPisCofins, type);
    if (obj === false) {
        return "";
    }
    let tag = obj.tag;
    let aliq = tag["p" + type.toUpperCase()];
    let vBCXml = tag["vBC"];
    let vBC = getVBCActualItem();
    let base = (vBCXml * 100) / (vBC);
    let url = "";

    base = formataDecimal(base, 2);
    aliq = formataDecimal(typeof aliq !== "undefined" ? aliq : "0", 2);
    url += appendUrl("cst_" + type, obj.cst);
    url += appendUrl("nf" + type + "aliq", aliq);
    url += appendUrl("nf" + type + "base", base);
    return url;
}

function getVBCActualItem() {
    let prod = actualProductXml.prod;
    prod = collect(prod);
    return (prod.getFloat("vProd")
        + prod.getFloat("vFrete")
        + prod.getFloat("vOutro")
        + prod.getFloat("vSeg")
        - prod.getFloat("vDesc")).toFixed(2);
}

function getTagPisCofins(tagPisCofins, type) {
    type = type.toUpperCase();
    let childTag = Object.keys(tagPisCofins)[0];
    let tag = tagPisCofins[childTag];
    //tag COFINSST/PISST nao possui cst no xml mas a cst usada é 05
    let cst = typeof tag.CST !== "undefined" ? tag.CST : (childTag === type + "ST" ? "05" : "");
    if (cst === "03") {
        bootbox.alert("Não foi possível criar a tag de " + type + " para gerar o imposto para o cst 03, contate o suporte");
        return false;
    }
    if (! tag) {
        console.log("Child tag " + childTag + " not found");
        bootbox.alert("Não foi possível criar a tag de pis ou cofins para gerar o imposto, contate o suporte");
        return false;
    }
    return {"tag": tag, "cst": cst};
}

function appendUrl(key, value) {
    return key + "=" + value + "&";
}

function printRegister(nf_id){
    var url = root + '/report.nfrecebidacomprovantepdf?id=' + nf_id;
    redirect(url,false);
}

function redirect(url,modal){
    if(modal){
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src', url);
    }else{
        window.open(url, '_blank');
    }
}