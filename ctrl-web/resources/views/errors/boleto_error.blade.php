@extends('layouts.mainmenu')
@section('content')
<script type="text/javascript">
    $(document).ready(function () {
        var errors = unescape('{{@$errorsBoleto}}');
        var strErrors = '';
        
        $.each(errors.split('+'), function (i, el) {
        	strErrors += el + ' ';
        });

        if(isEmpty(strErrors))
            window.location.href = root + '/home';
        else
            bootbox.alert({message: strErrors, callback: function () {window.close();}});

    });
</script>
@endsection
