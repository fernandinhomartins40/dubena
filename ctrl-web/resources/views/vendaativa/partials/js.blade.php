<script type="text/javascript" src="{{URL::to('js/vendaativa.js')}}"></script>
<script type="text/javascript" src="{{URL::to('js/endereco.js')}}"></script>
<script type="text/javascript">
@if(isset($trampo))
    $("#tab_1, #li-tab_1").addClass('hidden');
    $("#tab_2, #li-tab_2").addClass('hidden');
    $("#tab_3, #li-tab_3").addClass('hidden');
@else
    $("#filtrosusados").addClass("hidden");
    $(document).ready(function(){
        cepEmpresa = false;
        cidade_id = "{{@$cidade_id}}";
        bairro_id = "{{@$bairro_id}}";
        rua_id = "{{@$rua_id}}";
        cidadecompra = "{{@$cidadecompra}}";
        bairrocompra = "{{@$bairrocompra}}";
        ruacompra = "{{@$ruacompra}}";
        cidademedia = "{{@$cidademedia}}";
        bairromedia = "{{@$bairromedia}}";

        enderecoInit();
        if (window.location.search != "" && ! getParametro('tab')) {
            var filtro = $("#filtro").val();
            corrigirInformacoes(filtro);
        }
    });
@endif

@if($errors->any())
    errorsany = true;
@else
    errorsany = false;
@endif

</script>