<script>
    var urlFormRua = root + '/rua';
    var urlFormBairro = root + '/bairro';
    var urlBuscaRuas = root + '/rua/dropdown/:id';
    var urlChangeUf = root + '/cidade/dropdown/:id';
    var urlBuscaEstado = root + '/cidade/buscaPorNomeEEstado/:cidade/:estado';
    var urlChangeCidade = root + '/bairro/dropdown/:id/1';
    $(document).ready(function () {
        setCidadeOriginal("{{@$cidade_id}}");
        @if (isset($pedidoController))
            setPedidoController({!!$pedidoController!!});
            setNfceId("{!!$pedido->nfce_id!!}");
            setTempoEntregas("{{$tempoEntregaUrgente}}", "{{$tempoEntregaConfig}}");
            setArrayStatus();
        @endif
        @if(isset($show) && $show)
            setShowParameters();
        @elseif(!isset($show) && !str_contains(Request::url(), '/edit'))
            showShortcutsEdit();
        @else
            $("#teclasAtalho").removeClass('hidden');
        @endif
    });

    $(window).load(function () {
        @if(isset($pedeSenha) && $pedeSenha && !(isset($show) && $show))
            requirePassword();
        @else
            verificouSenhaEdit = true;
        @endif
    });
</script>
<script src="{{URL::to('js/enderecoPedido.js')}}"></script>
