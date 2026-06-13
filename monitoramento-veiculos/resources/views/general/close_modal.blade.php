
@extends('layouts.nomenu')

@section('content')

<div id="mainContent" class="content">
<div id="divCadastro">
	<meta name="csrf-token" content="{{ csrf_token() }}" />
  <script src="{{URL::to('plugins/jQuery/jQuery-2.1.4.min.js')}}"></script>
  <script src="{{URL::to('bootstrap/js/bootstrap.min.js')}}" type="text/javascript"></script>
  <script src="{{URL::to('dist/js/app.min.js')}}" type="text/javascript"></script>
	<script type="text/javascript">
  $(document).ready(function() {
		window.parent.closeModal();
  });

	</script>
</div><!-- /.content-wrapper -->
</div>
@endsection
