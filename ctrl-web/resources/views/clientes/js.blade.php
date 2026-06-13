<script>
    var root = '{{url("/")}}';
    $(document).ready(function() {
        @if ($errors->any())
            carregarTelefonesErro();
            carregarAllTablesErro();
            carregarContatosErro();
            carregarProdutosErro();
            carregarParentescoErro();
            carregarClientePromocoesErro();
            carregarClienteCondicoesPagamentoErro();
            carregarProdConvenioErro();
        @endif
        @if (isset($show) && $show)
            desativarInputs();
            var ids = ["#bttAddCliPromoc", ".btn-danger", ".btn-info", ".btnBuscarEndereco", '#btnBuscarCEP',
                    '.novoCadEndereco', '#btnAddFollowUp', '#btnAddFone', '#btnAddConvenioDependentes', 'btn-nw-buscas',
                    '#cliBttAddPreco', '#btnAddPreco', '#btnAddPromocao', '#btnAddParentesco', '#cpf', '#rg',
                    '#btnAddProdConvenio',
                    '#datanascimento', '#btnAddCondicaoPagamento', ".btnEditarContato"];
            desativarInputsEspecificos(ids);
        @endif
        @if (!isset($show) && !str_contains(Request::url(), '/edit') && !$errors -> any())
            $("#cliente").prop('checked', true);
            //$('#segmento_id').val($('#segmento_id option').filter(function () {
            //    return replaceSpecialChars($(this).html().toUpperCase()) == replaceSpecialChars('RESIDENCIAL');
            //}).val()).trigger('chosen:updated');
        @endif
    });
</script>
