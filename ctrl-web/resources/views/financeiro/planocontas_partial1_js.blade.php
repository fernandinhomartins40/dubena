<script>
    $(document).ready(function (){$("select#planocontas_list").treeMultiselect();
    $menudatapc = [
            @foreach($planocontas as  $menu)
    { "id" : "{{$menu->id}}", "parent" : "{{$menu->paiplanoconta_id==null?'#':$menu->paiplanoconta_id}}", "text" : "{{$menu->descricao}}", "state" : { opened: true, disabled: {{$menu -> disabled == 'T'?'true':'false'}}}},
            @endforeach
    ];

    $menudatapcR = [
        @if(isset($planocontasR))
            @foreach($planocontasR as  $menu)
                { "id" : "{{$menu->id}}", "parent" : "{{$menu->paiplanoconta_id==null?'#':$menu->paiplanoconta_id}}", "text" : "{{$menu->descricao}}", "state" : { opened: true, disabled: {{$menu -> disabled == 'T'?'true':'false'}}}},
            @endforeach
        @endif
    ];

    $menudatapcD = [
        @if(isset($planocontasD))
            @foreach($planocontasD as  $menu)
                { "id" : "{{$menu->id}}", "parent" : "{{$menu->paiplanoconta_id==null?'#':$menu->paiplanoconta_id}}", "text" : "{{$menu->descricao}}", "state" : { opened: true, disabled: {{$menu -> disabled == 'T'?'true':'false'}}}},
            @endforeach
        @endif
    ];

    $('#jstreepc').jstree({
    'core' : {
    'data' : $menudatapc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    });

    $('#jstreepc2').jstree({
    'core' : {
    'data' : $menudatapc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    });

//Empresa Config
//Plano de Contas Empresa Config
    $('#jstreepc3').jstree({
    'core' : {
    'data' : $menudatapc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    });
//Plano de Contas Cartão Empresa Config
    $('#jstreepc4').jstree({
    'core' : {
    'data' : $menudatapc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    });
//Plano de Conta Receita Desconto Empresa Config
    $('#jstreepc5').jstree({
    'core' : {
    'data' : $menudatapc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    });
//Plano de Conta Despesas Desconto Empresa Config
    $('#jstreepc6').jstree({
    'core' : {
    'data' : $menudatapc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    });
//Plano de Conta Receita Juros/Multas Empresa Config
    $('#jstreepc7').jstree({
    'core' : {
    'data' : $menudatapc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    });
//Plano de Conta Despesas Jurto/Multas Empresa Config
    $('#jstreepc8').jstree({
    'core' : {
    'data' : $menudatapc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    });
//Plano de Conta Vale Gas Empresa Config
    $('#jstreepc9').jstree({
    'core' : {
    'data' : $menudatapc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    });
//Plano de Conta Frete Empresa Config
    $('#jstreepc10').jstree({
    'core' : {
    'data' : $menudatapc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    });
//Plano de Conta Somente Receitas
    $('#jstreepcR').jstree({
    'core' : {
    'data' : $menudatapcR,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    });
//Plano de Conta Somente Despesas
    $('#jstreepcD').jstree({
    'core' : {
    'data' : $menudatapcD,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    });
//Plano de Conta Somente Receitas 2
    $('#jstreepcR2').jstree({
    'core' : {
    'data' : $menudatapcR,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    });
//Plano de Conta Somente Despesas 2
    $('#jstreepcD2').jstree({
    'core' : {
    'data' : $menudatapcD,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    });

    });
</script>
