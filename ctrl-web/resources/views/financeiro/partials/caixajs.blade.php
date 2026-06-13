
<script type="text/javascript">
    var operacao = "";
    var root = '{{url("/")}}';
    var tblMovto;
    var fechouCaixa = false;
    var conta_id = "{{$Conta->id}}";
    var issetContaFechamento = "{{isset($contafechamento) ? 1 : 0}}" === "1" ? true : false;
    var contafechamento_id = "{{(isset($contafechamento) ? $contafechamento->id : -1 )}}";
    var csrf_token = "{{ csrf_token() }}";
    var urlFmAbrirFinanceiro = "{{URL::route('financeiro.createbycaixa')}}";
    var urlCaixaIndex = '{{ route("caixa.index") }}';
    var estornar = "{{$estornar ? 1 : 0}}" === "1" ? true : false;
</script>