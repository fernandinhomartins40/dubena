<script>

    var root = '{{url("/")}}';
    $(document).ready(function() {
        @if ($errors -> any())
        console.log($("#menus_permitidos").val());
        @endif
        var clipboard = new Clipboard('.copyClipboard');
        clipboard.on('success', function(e) {
            console.info('Action:', e.action);
            console.info('Text:', e.text);
            e.clearSelection();
        });

        clipboard.on('error', function(e) {
            console.error('Action:', e.action);
            console.error('Trigger:', e.trigger);
        });
    });

    @if (isset($show))
        desativarInputs();
        var ids = [".btn-danger", ".btn-info","btn-nw-buscas"];
        desativarInputsEspecificos(ids);
        $("#secret").prop('disabled', false);
        $("#secret").prop('readonly', true);
    @endif
</script>
