
@extends('layouts.mainmenu')

@section('content')
<style>

</style>
<div id="mainContent" class="content">
<div id="divCadastro">
	<div class="row">
		<div class="col-md-6 col-md-offset-3">
			<div class="box-header">
				<h3 class="box-title">Relatório de Follow Up (Todos os Colégios)</h3>
			</div><!-- /.box-header -->
			<div class="box">
				<div class="box-body">
					<div class="col-md-12">
						<div class="form-group crud_space col-sm-12">
							{!! Form::label('datainicial', 'Data Início:', ['class'=>'col-sm-3 control-label input-sm']) !!}
							<div class="col-sm-4">
								<div class="input-group date" id="datetimepicker1">
									{!! Form::text('datainicial',null,['class'=>'form-control input-sm']) !!}
									<span class="input-group-addon">
										<span class="glyphicon glyphicon-calendar"></span>
									</span>
								</div>
							</div>
						</div>
						<div class="form-group crud_space col-sm-12">
							{!! Form::label('datafinal', 'Data Término:', ['class'=>'col-sm-3 control-label input-sm']) !!}
							<div class="col-sm-4">
								<div class="input-group date" id="datetimepicker2">
									{!! Form::text('datafinal',null,['class'=>'form-control input-sm']) !!}
									<span class="input-group-addon">
										<span class="glyphicon glyphicon-calendar"></span>
									</span>
								</div>
							</div>
						</div>
						<div class="form-group crud_space col-sm-12">
							{!! Form::label('empresas_list', 'Empresas:', ['class'=>'col-sm-3 control-label input-sm']) !!}
							<div class="col-sm-9">
								{!! Form::select('empresas_list[]',$empresas, [],['id'=>'empresas_list','class'=>'form-control input-sm', 'multiple', 'style' => 'width:100%;']) !!}
							</div>
						</div>

					</div>
				</div><!-- /.box-body -->
			</div><!-- /.box -->
			<div class="row">
				<div class="col-md-12">
					<div class="col-md-5">
						<button type="button" id="btnPrint" class="btn btn-nw-busca" onclick="imprimir();">Visualizar</button>
					</div>
				</div>
			</div>
		</div><!-- /.col -->
	</div><!-- /.row -->

	</div>
	<meta name="csrf-token" content="{{ csrf_token() }}" />

	<!-- DATA TABES SCRIPT -->
  <script src="{{URL::to('plugins/jQuery/jQuery-2.1.4.min.js')}}"></script>
  <!-- Bootstrap 3.3.2 JS -->
  <script src="{{URL::to('bootstrap/js/bootstrap.min.js')}}" type="text/javascript"></script>
  <!-- AdminLTE App -->
  <script src="{{URL::to('dist/js/app.min.js')}}" type="text/javascript"></script>
	<script src="{{URL::to('plugins/datepicker1/moment/moment-with-locales.js')}}" type="text/javascript"></script>
	<script src="{{URL::to('plugins/datepicker1/js/bootstrap-datetimepicker.min.js')}}"
  <script src="{{URL::to('plugins/slimScroll/jquery.slimscroll.min.js')}}" type="text/javascript"></script>
  <script src="{{URL::to('plugins/fastclick/fastclick.min.js')}}"></script>
	<script src="{{URL::to('plugins/bootbox.min.js')}}"></script>
	<script src="{{URL::to('plugins/bootstrap-multiselect/bootstrap-multiselect.js')}}"></script>
		<!-- page script -->
	<script type="text/javascript">

	var operacao = "";
  $(document).ready(function() {
		$('#datetimepicker1').datetimepicker({
			locale: 'pt-br',
			viewMode: 'days',
			format: 'DD/MM/YYYY'
		});
		$('#datetimepicker2').datetimepicker({
			locale: 'pt-br',
			viewMode: 'days',
			format: 'DD/MM/YYYY'
		});
		$('#empresas_list').multiselect({
				includeSelectAllOption: false,
				enableFiltering: false,
				allSelectedText: "Todos selecionados",
				nonSelectedText: 'Selecione os colégios'
		});
	});
	function imprimir(){
		var url = '{{ route("report.FollowUpAll", ":par") }}';
		url = url.replace(':par', $('#datainicial').val().replace(/\//g, '-') + '|' + $('#datafinal').val().replace(/\//g, '-') + '|' + $('#empresas_list').val());
		window.open(url, '_blank');
	}

	</script>
</div><!-- /.content-wrapper -->

@endsection
