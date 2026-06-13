<script>
    function abrirCentroCusto(idarvore, idinputid, idinputdescricao) {
        idarvore = isEmpty(idarvore) ? 'jstreecc' : idarvore;
        idinputid = isEmpty(idinputid) ? 'centrocusto_id' : idinputid;
        idinputdescricao = isEmpty(idinputdescricao) ? 'centrocusto_descricao' : idinputdescricao;

        $('#' + idarvore).jstree('select_node', $("#" + idinputid).val());

        $('#popup_centrocusto').modal('show');
        $('.jstree-generica').hide();
        $('#' + idarvore).show();
        $('#btnGravarCentroCusto').removeAttr('onclick');
        $('#btnGravarCentroCusto').attr('onclick', "setCentroCusto('" + idarvore + "', '" + idinputid + "', '" + idinputdescricao + "')");
    }
    function setCentroCusto(idarvore, idinputid, idinputdescricao) {
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
        $('#popup_centrocusto').modal('hide');
    }
</script>
