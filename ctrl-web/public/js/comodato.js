$(document).ready(function () {
    selecionarTipo($("#tipo:checked").val());
    $('#btnAddProduto').prop('disabled', true);
    verificaClasseProduto();
    buscarProdutosAjax();
    $("#produtoclasse_id").change(function () {
        verificaClasseProduto();
        buscarProdutosAjax();
    });

    tblProdutos = $('#tblProdutos').DataTable({
        "language": {"url": urlDataTable},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": true,
        "bInfo": false,
        "bAutoWidth": false,
        "paging": false,
        "retrieve": true,
        "searching": false
    });

    $("#fmCadastro").on('submit', function () {
        var produtos = [];
        tblProdutos.rows().every(function () {
            var d = this.data();
            produtos.push(d);
        });
        $('#produtos').val(JSON.stringify(produtos));
    });

    $("#tblProdutos").on('click', 'button', function () {
        if ($(this).context.id === 'removerProduto') {
            tblProdutos.row($(this).parents('tr'))
            .remove()
            .draw();
        }
    });
    $("#tblCadastro").on('click', 'button', function () {
        var trElem = $(this).closest('tr');
        var id = $($(trElem).children('td')[0]).text();
        if ($(this).context.id === 'btnGerarContrato') {
            $(this).attr('btnClick', 'true');
            var url = $("#btnGerarContrato").attr('url') + '/' + id;
            window.open(url, '_blank');
        }
    });
});
function carregarProdutosErro() {
    tblProdutos.clear();
    var produtos = JSON.parse($("#produtos").val());
    for (var i = 0; i < produtos.length; i++) {
        tblProdutos.row.add([
            produtos[i][0],
            produtos[i][1],
            produtos[i][2],
            produtos[i][3]
            ]).draw(false);
    }
}
function addProdutos() {
    var produto_id = $("#produto_id").val();
    var qdeProdutos = parseInt($("#quantidade").val());
    tblProdutos.rows().every(function () {
        var d = this.data();
        if(d[0] == produto_id){
            qdeProdutos += d[2];
            this.remove();
        }
    });
    tblProdutos.row.add([
        $("#produto_id").val(),
        $("#produto_id option:selected").text(),
        qdeProdutos,
        '<button id="removerProduto" type="button" class="btn btn-nw-registro removerProduto btn-xs">Remover</button>'
        ]).draw(false);
}

function enableDisableBtnAddProduto() {
    if ($("#produto_id").val() !== '' && $("#quantidade").val() !== '' && $("#quantidade").val() !== '0') {
        $("#btnAddProduto").prop('disabled', false);
    } else {
        $("#btnAddProduto").prop('disabled', true);
    }
}
function buscarProdutosAjax() {
    if (typeof urlBuscaProdutosPorClasse !== 'undefined') {
        var urlProduto = urlBuscaProdutosPorClasse;
        var id = $("#produtoclasse_id").val();
        urlProduto = urlProduto.replace(':id', id);
        $("#produto_id").empty();
        if (id !== '') {
            $.ajax({headers: {
                'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
            },
            type: "GET",
            url: urlProduto,
            success: function (data) {
                var html = "<option value=''>Selecione</option>";
                for (var i = 0; i < data.length; i++) {
                    html = html + "<option value='" + data[i].id + "'>" + data[i].descricao + "</option>";
                }
                $("#produto_id").append(html);
                $("#produto_id").trigger("chosen:updated");
            },
            error: function (data) {
                bootbox.alert('Erro ao buscar os produtos');
            },
            cache: false,
            contentType: false,
            processData: false
        });
            return false;
        }
    }
}

function verificaClasseProduto() {
    if ($("#produtoclasse_id").val() === '') {
        $("#produto_id").empty();
        $("#produto_id").prop('disabled', true).trigger('chosen:updated');
    } else {
        $("#produto_id").prop('disabled', false).trigger('chosen:updated');
    }
}

function selecionarTipo(value) {
    if (value === '0') {
        searchbox();
        $('#searchboxPF')[0].selectize.disable();
        $('#searchboxPJ')[0].selectize.enable();
        $('#searchboxPJFornecedor')[0].selectize.disable();
        $(".searchboxPJ").show();
        $(".searchboxPF").hide();
        $(".searchboxPJFornecedor").hide();
        $(".divPJ").show();
    } else if (value === "1") {
        searchbox();
        $('#searchboxPJ')[0].selectize.disable();
        $('#searchboxPF')[0].selectize.enable();
        $('#searchboxPJFornecedor')[0].selectize.disable();
        $(".searchboxPF").show();
        $(".searchboxPJ").hide();
        $(".searchboxPJFornecedor").hide();
        $(".divPJ").hide();
    } else {
        searchbox();
        if (typeof $('#searchboxPF')[0] !== 'undefined') {
            $('#searchboxPF')[0].selectize.disable();
            $('#searchboxPJ')[0].selectize.disable();
            $('#searchboxPJFornecedor')[0].selectize.enable();
            $(".searchboxPJ").hide();
            $(".searchboxPF").hide();
            $(".searchboxPJFornecedor").show();
            $(".divPJ").show();
        }
    }
    if (typeof onlyRead !== 'undefined') {
        if (onlyRead) {
            $('.onlyCreate').hide();
            $('.onlyRead').removeClass('hidden');
        }
    }
}
function searchbox() {
    $('#searchboxPF').selectize({
        valueField: 'id',
        labelField: 'nome',
        searchField: ['nome'],
        maxOptions: 10,
        options: [],
        create: false,
        render: {
            option: function (item, escape) {
                if (escape(item.cpf) == 'null') {
                    var cpf = '';
                } else {
                    var cpf = ' - ' + escape(item.cpf);
                }
                return '<div>' + escape(item.nome) + cpf + '</div>';
            }
        },
        optgroups: [
        {value: 'cliente', label: 'Clientes'}
        ],
        optgroupField: 'class',
        optgroupOrder: ['cliente'],
        load: function (query, callback) {
            if (!query.length)
                return callback();
            $.ajax({
                url: root + '/api/clientespf',
                type: 'GET',
                dataType: 'json',
                data: {
                    q: query
                },
                error: function () {
                    callback();
                },
                success: function (res) {
                    callback(res.data);
                }
            });
        },
        onChange: function (data) {
            buscaClientePorId(data);
            $('#cliente_id_erro').val($('#searchboxPF').selectize()[0].selectize.getValue());
            $('#cliente_nome_erro').val($('#searchboxPF').selectize()[0].selectize.getItem(this.items[0]).context.innerText);

        }, onInitialize: function () {
            var existingOptions = JSON.parse(this.$input.attr('data-selectize-value'));
            var self = this;
            if (errorsAny) {
                if ($("#tipo:checked").val() === '1') {
                    var opt = [{"id": $('#cliente_id_erro').val(), "nome": $('#cliente_nome_erro').val(), 'cpf': $('#cliente_cpf_cnpj_erro').val()}];
                    opt.forEach(function (existingOption) {
                        self.addOption(existingOption);
                        self.addItem(existingOption[self.settings.valueField]);
                    });
                }
            } else {
                if (Object.prototype.toString.call(existingOptions) === "[object Array]") {
                    existingOptions.forEach(function (existingOption) {
                        self.addOption(existingOption);
                        self.addItem(existingOption[self.settings.valueField]);
                    });
                } else if (typeof existingOptions === 'object') {
                    self.addOption(existingOptions);
                    self.addItem(existingOptions[self.settings.valueField]);
                }
            }
        }
    });

    $('#searchboxPJ').selectize({
        valueField: 'id',
        labelField: 'nome',
        searchField: ['nome', 'cnpj'],
        maxOptions: 10,
        options: [],
        create: false,
        render: {
            option: function (item, escape) {
                if (escape(item.cnpj) == 'null') {
                    var cnpj = '';
                } else {
                    var cnpj = ' - ' + escape(item.cnpj);
                }
                return '<div>' + escape(item.nome) + cnpj + '</div>';
            }
        },
        optgroups: [
        {value: 'cliente', label: 'Clientes'}
        ],
        optgroupField: 'class',
        optgroupOrder: ['cliente'],
        load: function (query, callback) {
            if (!query.length)
                return callback();
            $.ajax({
                url: root + '/api/clientespj',
                type: 'GET',
                dataType: 'json',
                data: {
                    q: query
                },
                error: function () {
                    callback();
                },
                success: function (res) {
                    callback(res.data);
                }
            });
        },
        onChange: function (data) {
            buscaClientePorId(data);
            $('#cliente_id_erro').val($('#searchboxPJ').selectize()[0].selectize.getValue())
            if(typeof $('#searchboxPJ').selectize()[0].selectize.getItem(this.items[0]).context !== 'undefined')
                $('#cliente_nome_erro').val($('#searchboxPJ').selectize()[0].selectize.getItem(this.items[0]).context.innerText);

        }, onInitialize: function () {
            var existingOptions = JSON.parse(this.$input.attr('data-selectize-value'));
            var self = this;
            if (errorsAny) {
                if ($("#tipo:checked").val() !== '1') {
                    var opt = [{"id": $('#cliente_id_erro').val(), "nome": $('#cliente_nome_erro').val(), 'cnpj': $('#cliente_cpf_cnpj_erro').val()}];
                    opt.forEach(function (existingOption) {
                        self.addOption(existingOption);
                        self.addItem(existingOption[self.settings.valueField]);
                    });
                }
            } else {
                if (Object.prototype.toString.call(existingOptions) === "[object Array]") {
                    existingOptions.forEach(function (existingOption) {
                        self.addOption(existingOption);
                        self.addItem(existingOption[self.settings.valueField]);
                    });
                } else if (typeof existingOptions === 'object') {
                    self.addOption(existingOptions);
                    self.addItem(existingOptions[self.settings.valueField]);
                }
            }
        }
    });
    
    $('#searchboxPJFornecedor').selectize({
        valueField: 'id',
        labelField: 'nome',
        searchField: ['nome', 'cnpj'],
        maxOptions: 10,
        options: [],
        create: false,
        render: {
            option: function (item, escape) {
                if (escape(item.cnpj) == 'null') {
                    var cnpj = '';
                } else {
                    var cnpj = ' - ' + escape(item.cnpj);
                }
                return '<div>' + escape(item.nome) + cnpj + '</div>';
            }
        },
        optgroups: [
        {value: 'cliente', label: 'Clientes'}
        ],
        optgroupField: 'class',
        optgroupOrder: ['cliente'],
        load: function (query, callback) {
            if (!query.length)
                return callback();
            $.ajax({
                url: root + '/api/fornecedorespj',
                type: 'GET',
                dataType: 'json',
                data: {
                    q: query
                },
                error: function () {
                    callback();
                },
                success: function (res) {
                    callback(res.data);
                }
            });
        },
        onChange: function (data) {
            buscaClientePorId(data);
            $('#cliente_id_erro').val($('#searchboxPJ').selectize()[0].selectize.getValue());
            if(typeof $('#searchboxPJ').selectize()[0].selectize.getItem(this.items[0]).context !== 'undefined')
                $('#cliente_nome_erro').val($('#searchboxPJ').selectize()[0].selectize.getItem(this.items[0]).context.innerText);

        }, onInitialize: function () {
            var existingOptions = JSON.parse(this.$input.attr('data-selectize-value'));
            var self = this;
            if (errorsAny) {
                if ($("#tipo:checked").val() !== '1') {
                    var opt = [{"id": $('#cliente_id_erro').val(), "nome": $('#cliente_nome_erro').val(), 'cnpj': $('#cliente_cpf_cnpj_erro').val()}];
                    opt.forEach(function (existingOption) {
                        self.addOption(existingOption);
                        self.addItem(existingOption[self.settings.valueField]);
                    });
                }
            } else {
                if (Object.prototype.toString.call(existingOptions) === "[object Array]") {
                    existingOptions.forEach(function (existingOption) {
                        self.addOption(existingOption);
                        self.addItem(existingOption[self.settings.valueField]);
                    });
                } else if (typeof existingOptions === 'object') {
                    self.addOption(existingOptions);
                    self.addItem(existingOptions[self.settings.valueField]);
                }
            }
        }
    });
}
function buscaClientePorId(id) {
    var url = root + '/cliente/buscaporid/:id';
    url = url.replace(':id', id);
    $.ajax({
        type: "GET",
        url: url,
        async: false,
        success: function (data) {
            if (data.cnpj !== null) {
                $("#cnpj_cpf").val(data.cnpj);
                $("#cliente_cpf_cnpj_erro").val(data.cnpj);
                $("#inscricaoest_rg").val(data.inscricao_estadual);
            } else {
                $("#cnpj_cpf").val(data.cpf);
                $("#cliente_cpf_cnpj_erro").val(data.cnpj);
                $("#inscricaoest_rg").val(data.rg);
            }
        },
        error: function (data) {
            getErrorFunctionAjaxGeneric()
        },
        cache: false,
        contentType: false,
        processData: false
    });
}

$('.cpf').blur(function () {

    // O CPF ou CNPJ
    var cpf_cnpj = $(this).val();

    // Testa a validação
    if (valida_cpf_cnpj(cpf_cnpj)) {
//                    alert('OK');
} else {
    if (cpf_cnpj !== '') {
        alert('CPF inválido!');
        $(this).focus();
        $(this).val('');
    }
}
});