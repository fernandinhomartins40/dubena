<script>
    function abrirPlanoConta(idarvore, idinputid, idinputdescricao) {
        idarvore = isEmpty(idarvore) ? 'jstreepc' : idarvore;
        idinputid = isEmpty(idinputid) ? 'planoconta_id' : idinputid;
        idinputdescricao = isEmpty(idinputdescricao) ? 'planoconta_descricao' : idinputdescricao;

        $('#' + idarvore).jstree('select_node', $("#" + idinputid).val());

        $('#popup_planoconta').modal('show');
        $('.jstree-generica').hide();
        $('#' + idarvore).show();
        $('#btnGravarPlanoConta').removeAttr('onclick');
        $('#btnGravarPlanoConta').attr('onclick', "setPlanoConta('" + idarvore + "', '" + idinputid + "', '" + idinputdescricao + "')");
    }
    function setPlanoConta(idarvore, idinputid, idinputdescricao) {
        var $id = $('#' + idinputid);
        var $desc = $('#' + idinputdescricao);
        var valueId = "";
        var valueDesc = "";
        var $tree = $('#' + idarvore);
        var $selected = $tree.jstree(true).get_selected()[0];
        if (typeof $selected !== 'undefined') {
            valueId = $selected;
            valueDesc = $('<textarea />').html($tree.jstree().get_selected(true)[0].text).text();
        }
        $id.val(valueId).trigger('change');
        $desc.val(valueDesc).trigger('change');
        $('#popup_planoconta').modal('hide');
    }
</script>
