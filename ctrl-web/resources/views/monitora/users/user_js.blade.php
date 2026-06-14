<script>

    var root = '{{url("/")}}';
    $(document).ready(function() {
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

        $menudata = [
                        @foreach($menus as  $menu)
                            { "id" : "{{$menu->id}}", "parent" : "{{$menu->parent_id==null?'#':$menu->parent_id}}", "text" : "{{$menu->titulo}}", "state" : { opened: true, disabled: true, selected: {{$menu->permitido=='T'?'true':'false'}} } },
                        @endforeach
                    ]
    @else
        $menudata = [
                        @foreach($menus as  $menu)
                            { "id" : "{{$menu->id}}", "parent" : "{{$menu->parent_id==null?'#':$menu->parent_id}}", "text" : "{{$menu->titulo}}", "state" : { opened: true, disabled: false, selected: {{$menu->permitido=='T'?'true':'false'}} } },
                        @endforeach
                    ]
    @endif
</script>