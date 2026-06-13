function addValeGas(empresa_id, pedido_id) {
    try {
        var url = root + '/validaGasBolso';
        var formData = new FormData();
        formData.append("codigo", $("#cod_gasbolso").val().trim());
        formData.append("produto_id", $("#valegasproduto_id").val());
        if ($("#modalEditaVariosPedidos").is(':visible'))
            empresa_id = $("#empresa_id").val();
        formData.append("empresa_id", empresa_id);
        if (typeof pedido_id !== "undefined" && pedido_id > 0)
            formData.append("pedido_id", pedido_id);
        var produtoExistente = false;
        tblCodValeGas.rows().every(function () {
            var d = this.data();
            if (d[0] == $("#cod_gasbolso").val()) {
                produtoExistente = true;
            }
        });
        if (!isEmpty($("#cod_gasbolso").val()) && !isEmpty($("#valegasproduto_id").val()) && !produtoExistente) {
            ajaxGenerator(url, 'POST', function (data) {
                if (typeof data === 'object') {
                    tblCodValeGas.row.add([
                        data.codigo,
                        data.valor,
                        data.produto_id,
                        data.produto,
                        "<button id='btnRemoverValeGas' type='button' class='btn btn-nw-registro btn-xs'>Remover</button>"
                    ]).draw();
                    $("#valegasproduto_id option:selected").attr('disabled', 'true').trigger('chosen:updated');
                } else {
                    bootbox.alert('' + data);
                }
                $("#cod_gasbolso").val('');
                $('#valegasproduto_id').children('option:enabled').eq(0).prop('selected',true).trigger('chosen:updated');
            }, null, formData);
        } else if (isEmpty($("#valegasproduto_id").val()) && !produtoExistente) {
            bootbox.alert('Selecione outro produto!');
        } else if (!produtoExistente) {
            bootbox.alert('Digite o código do Vale Gás!');
        } else {
            bootbox.alert('Este Vale Gás já foi validado para outro produto nessa compra!');
        }
        $("#cod_gasbolso").focus();
    } catch (e) {
        storageLogError(e, 'log-pedido-general-js');
    }
}

function removeValegasFromTbl(that) {
    try {
        var row = $(that).closest('tr');
        var data = $('#tblCodValeGas').dataTable().fnGetData(row);
        var produto = data[2];
        var disabled = false;
        if (data[0] !== '')
            tblCodValeGas.row($(that).parents('tr')).remove().draw();
        $("#valegasproduto_id").find("option").filter(function () {
            if (disabled)
                return;
            if ($(this).val() == produto && $(this).prop('disabled')) {
                $(this).prop('disabled', false).trigger('chosen:updated');
                disabled = true;
            }
        });
    } catch (e) {
        storageLogError(e, 'log-pedido-general-js');
    }
}

function gerarComanda(pedido_id) {
    try {
        ajaxGenerator(root + '/pedido.comanda/' + pedido_id, "GET", function (result) {
            if (typeof result !== "object") {
                bootbox.alert("Erro: " + result);
                return;
            }
            var empresa = result.empresa;
            var pedido = result.pedido;
            var qdePrint = parseInt(result.qde);
            var printer = result.printer;
            // var printer = "EPSON TM-T20 Receipt";
            var data = [];
            //command is instanced in thermalPrint.js
            data.push(command.center);
            data.push(command.boldOn);
            data.push(command.small);
            data.push(empresa.razao_social.toUpperCase());
            data.push("CNPJ: " + empresa.cnpj);
            data.push(empresa.rua.descricao + "," + empresa.numero + " - " + empresa.bairro.descricao);
            var tel = empresa.telefone1 !== null && empresa.telefone1.length > 0 ? empresa.telefone1 : '';
            data.push(empresa.cidade.descricao + "/" + empresa.uf + " Tel" + tel);

            data.push(command.left);
            data.push("Pedido: " + pedido.id + "\n");
            data.push("CLIENTE");
            data.push(command.boldOff);

            data.push("Nome: " + pedido.cliente.nome);
            var complemento = pedido.entregacomplemento !== null && pedido.entregacomplemento.length > 0 ? ' - ' + pedido.entregacomplemento : '';
            data.push("Endereço: " + pedido.entregarua.descricao + ", " + pedido.entreganumero + complemento);
            data.push("Bairro: " + pedido.entregabairro.descricao);
            var ref = pedido.entregapontoreferencia !== null && pedido.entregapontoreferencia.length > 0 ? pedido.entregapontoreferencia : "";
            data.push("Ponto Referência: " + ref);
            data.push("Cidade/UF: " + pedido.entregacidade.descricao + '/' + pedido.entregacidade.uf.toUpperCase());

            data.push(command.boldOn);
            data.push("DADOS DO PEDIDO");
            data.push(command.boldOff);

            data.push("Operação: " + pedido.pedido_operacao.descricao);
            data.push("Data: " + requestDataOracle(pedido.datahoraprevisaoentrega));
            data.push("Setor: " + pedido.entrega_setor.descricao);
            data.push("Entregador: " + pedido.colaborador.nome);
            data.push("Condição de Pagamento: " + pedido.condicaopagamento.descricao);

            data.push(command.boldOn);
            data.push("PRODUTOS");
            data.push(command.boldOff);

            data.push('Descricao                   Qde       Vl. Unt.       Total');
            for (var i = 0; i < pedido.pedidoitem.length; i++) {
                var item = pedido.pedidoitem[i];
                var descricao = putWhiteSpaces(item.produto.descricao, 28);
                var quantidade = putWhiteSpaces(item.quantidade, 8);
                var precovendaunitario = putWhiteSpaces(formataDecimal(item.precovendaunitario, 2), 14);
                var precovendatotal = formataDecimal(item.precovendatotal, 2);
                data.push(descricao + quantidade + precovendaunitario + precovendatotal);
            }

            data.push("\nTaxa de Entrega: " + formataDecimal(pedido.entregataxa, 2));
            data.push("Valor Desconto: " + formataDecimal(pedido.valordesconto, 2));
            data.push("Valor Total: " + formataDecimal(pedido.valorvenda, 2) + "\n");
            data.push("Usuário: " + pedido.atendente_user.name + "\n");
            var obs = pedido.observacao !== null && pedido.observacao.length > 0 ? pedido.observacao : "";
            data.push("Observações: " + obs);
            printEscpos(data, qdePrint, printer);
        });
    } catch (e) {
        storageLogError(e, 'log-pedido-general-js');
    }
}

function bootboxConfirm(title, message, callback, btnConfirm = "Sim", btnCancel = "Não") {
    try {
        bootbox.confirm({
            title: title,
            message: message,
            buttons: {
                confirm: {
                    label: btnConfirm,
                    className: "btn-nw-registro"
                },
                cancel: {
                    label: btnCancel,
                    className: "btn-nw-geral"
                }
            },
            callback: function (result) {
                callback(result);
            }
        });
    } catch (e) {
        storageLogError(e, 'log-pedido-general-js');
    }
}


