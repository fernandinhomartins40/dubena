@extends('layouts.nomenu') 
@section('content')

<link href="{{asset('plugins/chosen/chosen.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('css/custom.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('plugins/datatables/dataTables.bootstrap.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('plugins/selectize/css/selectize.bootstrap3.css')}}" rel="stylesheet" type="text/css" />
<link href="{{asset('plugins/boostrap-table/css/bootstrap-table.min.css')}}" rel="stylesheet" type="text/css" />

<script src="{{asset('plugins/jQuery/jQuery-2.1.4.min.js')}}"></script>
<script src="{{asset('bootstrap/js/bootstrap.min.js')}}"></script>
<script src="{{asset('plugins/boostrap-table/js/bootstrap-table.min.js')}}" type="text/javascript"></script>
<script src="{{asset('plugins/boostrap-table/locale/bootstrap-table-pt-BR.min.js')}}" type="text/javascript"></script>
<script src="{{asset('plugins/bootbox.min.js')}}"></script>
<script src="{{asset('plugins/chosen/chosen.jquery.latin.js')}}"></script>
<script src="{{asset('js/jqueryMaskMoney.js')}}"></script>
<script src="{{asset('js/shortcut.js')}}"></script>
<script src="{{asset('plugins/datatables/jquery.dataTables.min.js')}}" type="text/javascript"></script>
<script src="{{asset('plugins/datatables/dataTables.bootstrap.min.js')}}" type="text/javascript"></script>
<script src="{{asset('plugins/datepicker1/moment/moment-with-locales.js')}}" type="text/javascript"></script>
<script src="{{asset('plugins/datepicker1/js/bootstrap-datetimepicker.min.js')}}" type="text/javascript"></script>
<script src="{{asset('js/jquery.mask.min.js')}}"></script>
<script src="{{asset('plugins/selectize/js/standalone/selectize.min.js')}}"></script>
<script src="{{asset('plugins/custom_utils.js')}}"></script> 
<script src="{{asset('js/lib/collection.js')}}"></script> 
<script src="{{asset('js/customJquery.js')}}"></script> 
<script src="{{asset('js/custom.js')}}"></script> 
<script src="{{asset('js/customvalidacoes.js')}}"></script> 
<script>
    root = '{{url("")}}';
    var urlDataTable = "{{asset('plugins/datatables/Portuguese-Brasil.json')}}";
    $('body').attr('style', 'background-color: #b1b0ab !important');
    
    @if(isset($dataFromPedido))
        $(window).load(function() {
            setInputsEnderecoPadrao('#cep', '#cidade_id', '#uf', '#bairro_id', '#rua_id');
            setTimeout(function () {
                @if (! $errors->any())
                    putEndereco();
                @endif
            });
        });
    @endif
        function putEndereco()
        {
            var cep_temp = "{{@$dataFromPedido['cep']}}";
            if (cep_temp) {
                $("#cep").val(cep_temp);
                buscarEnderecoPorCep('geral');
                return false;
            }
            dontLoadEnderecoEmpresa = true;
            var cidade_temp = '';
            var bairro_temp = '';
            var rua_temp = '';
            $("#uf").val("{{@$dataFromPedido['uf']}}").trigger('chosen:updated');
            cidade_temp = "{{@$dataFromPedido['cidade_id']}}";
            bairro_temp = "{{@$dataFromPedido['bairro_id']}}";
            rua_temp = "{{@$dataFromPedido['rua_id']}}";
            
            carregarEndereco(cidade_temp, bairro_temp, rua_temp, 'geral');
            $("#complemento").val("{{@$dataFromPedido['complemento']}}");
            $("#ponto_referencia").val("{{@$dataFromPedido['ponto_referencia']}}");
            
        }
    $(document).ready(function () {
        shortcut.add("ctrl+q", function () {
            window.parent.closeModal();
        });
        $("form#fmCadastro").on('submit', function (e) {
             $(this).append("<input type='hidden' name='fromPedidos'/>");
             $(this).append("<input type='hidden' name='clienteempresa_id' id='clienteempresa_id' value='{{$empresa_id}}'/>");
        });
        $("#ativo").prop('checked', true);
        @if(isset($telefone) && strlen($telefone) > 0)
            $("#btnAddFone").prop('disabled', false);
        @endif
        $("#btnVoltar").html('Fechar').attr('href', '').on('click', function () {
            window.parent.closeModal();
        });
    });
</script>
<div class="nav-tabs-custom margTop_25">
    @include('clientes.form_clientes')
</div>
@endsection