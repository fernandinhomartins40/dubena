<script>
    $(document).ready(function () {
        $("#codigo").val($("#codigoDisabled").val());
        $("select#planocontas_list").treeMultiselect();
        $menudatapc = [
            { "id" : "0", "parent" : "#", "text" : "Novo", "state" : { opened: true, disabled: false, selected: false}},
            @foreach($planocontas as  $menu)
                { "id" : "{{$menu->id}}", "parent" : "{{$menu->paiplanoconta_id==null?'#':$menu->paiplanoconta_id}}", "text" : "{{$menu->descricao}}", "state" : { opened: true, disabled: false, selected: {{$menu -> id == $planoconta_id?'true':'false'}}}},
            @endforeach
        ];
        $('#jstreepc').jstree({
            'core': {
                'data': $menudatapc,
                'multiple': false,
                'check_callback': true
            },
            "checkbox": {
                "three_state": false
            }
        }).on("changed.jstree", function (e, data) {
            var id = data.selected;
            var planocontaeditando_id = $("#id").val();
            if(id == planocontaeditando_id){
                bootbox.alert('O plano de contas selecionado não pode ser o mesmo que você está editando!');
                return;
            }
            planocontaeditando_id = isEmpty(planocontaeditando_id) ? 0 : planocontaeditando_id;
            ajaxGenerator(root + '/planocontaajax/' + id + '/' + planocontaeditando_id, 'GET',
                    function (dados) {
                        if (typeof dados == 'array' || typeof dados == 'object') {
                            $("#codigo").val(dados.codigo);
                            $("#codigoDisabled").val(dados.codigo);
                            if(id != 0)
                                $("#pagarreceber").val(dados.pagarreceber).prop('disabled', true).trigger('chosen:updated')
                            else 
                                $("#pagarreceber").val(dados.pagarreceber).prop('disabled', false).trigger('chosen:updated')
                            $("#popup_planoconta").modal('hide');
                        } else {
                            bootbox.alert('' + dados);
                            console.error(dados);
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