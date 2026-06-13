
<script>
    function abrirPlanoConta() {
        $('#popup_planoconta').modal('show');
    }
    function setPlanoConta() {
        $('#planoconta_id').val($('#jstreepc').jstree(true).get_selected()[0]);
        var CurrentNode = $("#jstreepc").jstree("get_selected");
        //$('#planoconta_descricao').val($('#'+CurrentNode).text());
        $('#planoconta_descricao').val($('<textarea />').html($('#jstreepc').jstree().get_selected(true)[0].text).text());

        $('#popup_planoconta').modal('hide');
    }
</script>