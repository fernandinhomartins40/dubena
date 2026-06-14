
@extends('monitora.layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
<div id="divCadastro">
	<div class="row">
		<div class="col-xs-12">
			<div class="box-header">
				<h3 class="box-title">Erro</h3>
			</div><!-- /.box-header -->
			<div class="box">
				<div class="box-body">
					<div class="col-md-12">
						<div id="saveError" class="alert alert-danger alert-dismissable" style="display:block;">
							<span class="glyphicon glyphicon-remove"></span>
							<div id="save_result">{{$msg}}</div>
						</div>
					</div>
				</div><!-- /.box-body -->
			</div><!-- /.box -->
		</div><!-- /.col -->
	</div><!-- /.row -->



</div><!-- /.content-wrapper -->
</div>
@endsection
