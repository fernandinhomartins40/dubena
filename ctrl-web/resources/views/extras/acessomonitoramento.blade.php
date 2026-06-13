
@extends('layouts.mainmenu')

@section('content')
<script type="text/javascript">
    var newWindow = window.open('{{$linkmonitoramento}}', '_blank');
    if(newWindow)
	    window.location.href = root + '/home';
	else
		bootbox.alert("Não foi possível abrir a tela de monitoramento. Verifique se seu navegador permite pop-ups na barra de url");
</script>
@endsection
