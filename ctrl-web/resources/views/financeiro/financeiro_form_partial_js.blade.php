@include('financeiro.centrocustos_partial1_js')
@include('financeiro.planocontas_partial1_js')
<script src="{{URL::to('js/lib/collection.js')}}"></script>

<!-- page script -->
<script type="text/javascript">
    var urlDataTable = "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}";
    var root = '{{url("/")}}';
    var urlret = '{{ route("financeiro.fecharModalIframe") }}';
    var errorsAny = "{{$errors->any() ? 1 : 0}}" === "1";
    var origemAgrupar = "{{isset($origemAgrupar) ? 1 : 0}}" === "1";
    var optCliente = origemAgrupar ? [{"id": "{{@$cliente_id}}", "nome": "{!!@$nome!!}"}] : [];
    var urlClientes = root + "/api/{{$tipo_lancamento=='P'?'searchFornecedores':'searchClientes'}}";
    var tipoTela = "{{$tipo_tela}}";
    var voltar = "{{$voltar}}" === "1";
    var urlStore = '{{ route("financeiro.store") }}';
    shortcut.add('F3', function(){
       gravar(); 
    });
</script>
<script type="text/javascript" src="{{asset('js/financeiroForm.js')}}"></script>


@include('financeiro.centrocustos_partial2_js')
@include('financeiro.planocontas_partial2_js')
@include('financeiro.centrocustos_partial1')
@include('financeiro.planocontas_partial1')
