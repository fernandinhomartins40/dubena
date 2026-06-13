<script>
    $(document).ready(function (){$("select#planocontas_list").treeMultiselect();
    $("select#centrocustos_list").treeMultiselect();
    $menudatacc = [
            @foreach($centrocustos as  $menu)
            { "id" : "{{$menu->id}}", "parent" : "{{$menu->paicentrocusto_id==null?'#':$menu->paicentrocusto_id}}", "text" : "{{$menu->descricao}}", "state" : { opened: true, disabled: {{$menu -> disabled == 'T'?'true':'false'}}}},
            @endforeach
    ];

    $('#jstreecc').jstree({
    'core' : {
    'data' : $menudatacc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    }).on('loaded.jstree', function() {
    $('#jstreecc').jstree('open_all');
    });

    $('#jstreecc2').jstree({
    'core' : {
    'data' : $menudatacc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    }).on('loaded.jstree', function() {
    $('#jstreecc2').jstree('open_all');
    });
//Empresa config
//Centro Custo Empresa Config
    $('#jstreecc3').jstree({
    'core' : {
    'data' : $menudatacc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    }).on('loaded.jstree', function() {
    $('#jstreecc3').jstree('open_all');
    });
// Centro Custo Vale Gas Empresa Config
    $('#jstreecc4').jstree({
    'core' : {
    'data' : $menudatacc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    }).on('loaded.jstree', function() {
    $('#jstreecc4').jstree('open_all');
    });
// Centro Custo Frete Empresa Config
    $('#jstreecc5').jstree({
    'core' : {
    'data' : $menudatacc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    }).on('loaded.jstree', function() {
    $('#jstreecc5').jstree('open_all');
    });

// Centro Custo Despesas Juros Multa Empresa Config
    $('#jstreecc6').jstree({
    'core' : {
    'data' : $menudatacc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    }).on('loaded.jstree', function() {
    $('#jstreecc6').jstree('open_all');
    });

// Centro Custo Despesas Desconto Empresa Config
    $('#jstreecc7').jstree({
    'core' : {
    'data' : $menudatacc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    }).on('loaded.jstree', function() {
    $('#jstreecc7').jstree('open_all');
    });

// Centro Custo Receita Juros Multa Empresa Config
    $('#jstreecc8').jstree({
    'core' : {
    'data' : $menudatacc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    }).on('loaded.jstree', function() {
    $('#jstreecc8').jstree('open_all');
    });

// Centro Custo Receita Desconto Empresa Config
    $('#jstreecc9').jstree({
    'core' : {
    'data' : $menudatacc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    }).on('loaded.jstree', function() {
    $('#jstreecc9').jstree('open_all');
    });
// Centro Custo Cartões Empresa Config
    $('#jstreecc10').jstree({
    'core' : {
    'data' : $menudatacc,
            'multiple': false
    },
            "plugins" : ["checkbox"],
            "checkbox": {
            "three_state": false
            }
    }).on('loaded.jstree', function() {
    $('#jstreecc10').jstree('open_all');
    });

    });
</script>
