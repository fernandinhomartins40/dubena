<div class="modal fade" id="modalBuscaConveniado" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="modalBuscaConveniadoLabel">Busca de Conveniado por CPF</h4>
            </div>
            <div class="modal-body col-md-12">
                <div class="row">
                    <div class="col-sm-10">
                        <select id="cpfConveniadoBusca" name="cpfConveniadoBusca" placeholder="Digite o CPF"  class="form-control input-sm" value="" data-selectize-value = '[]'></select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                {{-- <button type="button" class="btn btn-nw-geral" data-dismiss="modal">Cancelar</button> --}}
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        $("#cpfConveniadoBusca").selectize({
            valueField: "id",
            labelField: "nome",
            searchField: ["cpf"],
            maxOptions: 10,
            hideSelected: true,
            options: [],
            create: false,
            render: {
                option: function (item, escape) {
                    // var endereco = " - " + escape(item.rua.descricao) +
                    //     " nº " + escape(item.numero) + ", " + escape(item.bairro.descricao) + ", " +
                    //     escape(item.cidade.descricao);
                    return `<div><b>${escape(item.nome)}</b> ${escape(item.cpf)} </div>`;
                }
            },
            optgroups: [
                {value: "cliente", label: "Clientes"}
            ],
            optgroupField: "class",
            optgroupOrder: ["cliente"],
            load: function (query, callback) {
                refreshSelectize($("#cpfConveniadoBusca"));
                if (!query.length)
                    return callback();
                $.ajax({
                    url: root + "/api/searchClientePedidoConvenio",
                    type: "GET",
                    dataType: "json",
                    data: {
                        q: query
                    },
                    error: function (data) {
                        console.log(data);
                        callback();
                    },
                    success: function (res) {
                        buscaSelectize = true;
                        callback(res.data);
                    }
                });
            },
            onChange: function () {
                let option = this.options[this.getValue()];

                if (!option) return;

                let obj = {
                    nome: option.nome,
                    cliente_id: option.id,
                    numero: option.numero,
                    cidade_descricao: option.cidade.descricao,
                    bairro_descricao: option.bairro.descricao,
                    rua_descricao: option.rua.descricao,
                };
                let $nomeCliente = $("#nomecliente");
                let select = $nomeCliente.selectize()[0].selectize;
                clearSelectize($nomeCliente);
                addOptionNomeSelectize(select, obj);
                $("#modalBuscaConveniado").modal("hide");
            },
        });
    });

    $("#modalBuscaConveniado").on("shown.bs.modal", function () {
        let selectConveniado = $("#cpfConveniadoBusca").selectize()[0].selectize;
        setTimeout(() => {
            selectConveniado.focus();
        }, 100);
    });

    $("#labelConvenio").click(function () {
        $("#modalBuscaConveniado").modal("show");
    });
</script>