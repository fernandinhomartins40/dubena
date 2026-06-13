<script>
    function abrirCentroCusto() {
        $('#popup_centrocusto').modal('show');
    }
    function setCentroCusto() {
        $('#centrocusto_id').val($('#jstreecc').jstree(true).get_selected()[0]);
        var CurrentNode = $("#jstreecc").jstree("get_selected");
        //$('#centrocusto_descricao').val($('#'+CurrentNode).text());
        $('#centrocusto_descricao').val($('<textarea />').html($('#jstreecc').jstree().get_selected(true)[0].text).text());

        $('#popup_centrocusto').modal('hide');
    }
</script>
