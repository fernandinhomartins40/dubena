
<script>
    @if ($errors -> any())
            errorsAny = true;
    @else
            errorsAny = false;
    @endif
    @if (isset($show) && $show)
        onlyRead = true;
    @elseif (isset($edit))
        onlyRead = true;
    @else
        onlyRead = false;
    @endif
            var root = '{{URL("/")}}';
    var urlBuscaProdutosPorClasse = '{{ url("produto/buscaporclasse/:id") }}';
    $(document).ready(function(){
    @if ($errors -> any())
            carregarProdutosErro();
    @endif
    });
    
    setTimeout(function () {
    @if (isset($show) && $show)
        desativarInputs();
        var ids = ['.removerProduto'];
        desativarInputsEspecificos(ids);
    @elseif (isset($edit))
        var ids = ['.removerProduto', '#datacontrato', '#datavencimento', '#numeronota',
                '#observacao', 'input[type=radio]', ''];
        desativarInputsEspecificos(ids);
    @endif
    }, $(document).ready());
</script>