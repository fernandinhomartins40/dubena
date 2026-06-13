<script type="text/javascript" src="{{URL::to('js/endereco.js')}}"></script>
<script type="text/javascript" src="{{URL::to('js/maladireta.js')}}"></script>
<script type="text/javascript">
    $(document).ready(function () {
        cepEmpresa = false;
        var pars = '{{Request::getQueryString()}}';
        var tab = "{{@$tab}}";
        empresa_id = "{{Session::get('empresa_padrao')->id}}";
        if(!isEmpty(tab))
            $('.nav-tabs a[href="#' + tab.replace('_t', 't') + '"]').tab('show');
        cidade_id = "{{@$cidade_id}}";
        bairro_id = "{{@$bairro_id}}";
        rua_id = "{{@$rua_id}}";
        cidade_id_tab_2 = "{{@$cidade_id_tab_2}}";
        bairro_id_tab_2 = "{{@$bairro_id_tab_2}}";
        setor_id = "{{@$setor_id}}";
        rua_id_tab_2 = "{{@$rua_id_tab_2}}";
        cidade_id_tab_3 = "{{@$cidade_id_tab_3}}";
        bairro_id_tab_3 = "{{@$bairro_id_tab_3}}";
        rua_id_tab_3 = "{{@$rua_id_tab_3}}";
        tabFiltros = "{{@$tab}}";

        configInit();
    });
</script>