<script>
    $(document).ready(function () {
        $("#codigo").val($("#codigoDisabled").val());
        $("select#centrocustos_list").treeMultiselect();
        $menudatacc = [
            { "id" : "0", "parent" : "#", "text" : "Novo", "state" : { opened: true, disabled: false, selected: false}},
            @foreach($centrocustos as  $menu)
            { "id" : "{{$menu->id}}", "parent" : "{{$menu->paicentrocusto_id==null?'#':$menu->paicentrocusto_id}}", "text" : "{{$menu->descricao}}", "state" : { opened: true, disabled: {{$menu -> disabled == 'T'?'true':'false'}}, selected: {{$menu -> id == $centrocusto_id?'true':'false'}}}},
            @endforeach
        ];
        $('#jstreecc').jstree({
            'core': {
                'data': $menudatacc,
                'multiple': false,
                'check_callback': true
            },
            "checkbox": {
                "three_state": false
            }
        }).on("changed.jstree", function (e, data) {
            var id = data.selected;
            var centrocustoeditando_id = $("#id").val();
            if(id == centrocustoeditando_id){
                bootbox.alert('O centro de custos selecionado não pode ser o mesmo que você está editando!');
                return;
            }
            centrocustoeditando_id = isEmpty(centrocustoeditando_id) ? 0 : centrocustoeditando_id;
            ajaxGenerator(root + '/centrocustoajax/' + id + '/' + centrocustoeditando_id, 'GET',
                    function (dados) {
                        if (!isNaN(parseInt(dados))) {
                            $("#codigo").val(dados);
                            $("#codigoDisabled").val(dados);
                            $("#popup_centrocusto").modal('hide');
                        } else {
                            bootbox.alert('' + dados);
                        }
                    }, function (dados) {
                if (typeof (dados) == 'object') {
                    var msg = '';
                    var responseText = '';
                    for (var key in dados) {
                        if (key == 'responseJSON') {
                            for (var key1 in dados['responseJSON']) {
                                msg += '<br />' + dados['responseJSON'][key1];
                            }
                        }
                        if (key == 'responseText') {
                            responseText = dados['responseText'];
                        }
                    }
                    if (msg != '')
                        bootbox.alert('Erro ao selecionar: <br />' + msg);
                    else
                        bootbox.alert('Erro ao selecionar: ' + responseText);
                } else {
                    bootbox.alert('Erro ao selecionar!');
                    console.log(dados)
                }
            });
        });
        $(document).on("hidden.bs.modal", function () {
            $("#codigoDisabled").prop('disabled', true);
        });
    });
</script>