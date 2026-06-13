<!--suppress JSUnusedLocalSymbols, JSUnusedAssignment, JSDuplicatedDeclaration -->
<script>
    const tipoNf = "SAT";
    const urlTransmitir = '{{route("satcfe.transmitir", $cupomFiscal->id)}}';
    const urlCancelar = '{{route("satcfe.cancelar", $cupomFiscal->id)}}';
    @if ($errors->any())
        const hasErrors = true;
    @else
        const hasErrors = false;
    @endif

    @if (! $cupomFiscal->id)
        const editOrShow = false;
        const show = false;
    @else
        const editOrShow = true;
        @if(isset($show) && $show)
            const show = true;
        @else
            const show = false;
        @endif
    @endif

    showLoaderAjax();
    {{--var conn = new WebSocket('{{env("WEBSOCKET_ADDRESS", "")}}?empresa_id=' + "2");--}}
    {{--conn.onopen = function (e) {--}}
        {{--console.log("Connection established!");--}}
    {{--};--}}

    {{--conn.onmessage = function(e) {--}}
        {{--console.log(e.data);--}}
    {{--};--}}

    $(document).ready(function () {
        setPricesItems({!! $produtos !!});
        onDocReady();
    });
    setTimeout(function () {
        hideLoaderAjax();
    }, 1);
</script>