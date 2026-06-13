
@extends('layouts.mainmenu')

@section('content')
<div id="mainContent" class="content">
	<div id="divCadastro">
		<div class="row">
			<div class="col-xs-12">
				<div class="box-header">
					<div class="row">
						<div class="col-md-12">
						</div> <!--col-md-12-->
					</div><!--row-->
					<div class="panel panel-default">
						<div class="panel-heading">
							<h3 class="box-title">Estoque Físico</h3>
						</div><!-- /.box-header -->
						@if(isset($estoquefisico))
						{{ Form::model($estoquefisico, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'route' => array('estoquefisico.update', $estoquefisico->id))) }}
						@else
						{{ Form::open(['id'=>'fmCadastro', 'route' => 'estoquefisico.store', 'class' => 'form-horizontal']) }}
						@endif
						<div class="panel-body">
							<div class="col-md-3">
								{!! Form::label('dataFechamento', 'Fechamento:', ['class'=>'col-sm-4 control-label input-sm','style'=>'text-align:right;']) !!}
								<div class="col-sm-7 input-group generalDateTimePicker">
									@if(isset($dataFechamento))
										{!! Form::text('dataFechamento',requestDataOracle($dataFechamento),['class'=>'form-control input-sm', 'id' => 'dataFechamento', 'disabled']) !!}
									@else
										{!! Form::text('dataFechamento',null,['class'=>'form-control input-sm generalDateTimePicker', 'id' => 'dataFechamento', 'disabled']) !!}
									@endif
									<span class="input-group-addon">
										<span class="glyphicon glyphicon-calendar"></span>
									</span>
								</div>
							</div>
							<div class="col-md-3">
								{!! Form::label('datacompetencia', 'Competência: ', ['class'=>'col-sm-4 control-label input-sm','style'=>'text-align:right;']) !!}
								<div class="col-sm-7 input-group generalDateTimePicker">
									@if(isset($estoquefisico->datacompetencia))
										{!! Form::text('datacompetencia',requestDataOracle($estoquefisico->datacompetencia),['class'=>'form-control input-sm', 'id' => 'datacompetencia']) !!}
									@else
										{!! Form::text('datacompetencia',null,['class'=>'form-control input-sm generalDateTimePicker', 'id' => 'datacompetencia']) !!}
									@endif
									<span class="input-group-addon">
										<span class="glyphicon glyphicon-calendar"></span>
									</span>
								</div>
							</div>
							<div class="col-md-3">
								{!! Form::label('setor_id', 'Setor: ', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;']) !!}
								<div class="col-sm-9">
									{!! Form::select('setor_id',$setores,@setor_id,['class'=>'form-control input-sm selectChosen', 'id' => 'setor_id']) !!}
								</div>
							</div>
							<br />
							<br />
							<br />
							<div class="col-md-12">
								<table class="table table-hover table-condensed table-responsive table-bordered" data-height="400" data-id-field="id" id="tblEstoqueFisico">
								</table>
							</div>
						</div><!-- /.box-body -->
						<div class="box-footer">
							<div class="col-md-4">
								{!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
								<a type="button" href="{{url('estoquefisico')}}" class="btn btn-nw-geral">Voltar</a>
							</div>
							<div class="col-md-4">
							</div>
						</div>
						{{Form::hidden('estoqueAlterado', null, ['id' => 'estoqueAlterado'])}}
						{{Form::hidden('efetivado', null, ['id' => 'efetivado'])}}
						{{ Form::close()}}
					</div><!-- /.box -->
				</div><!-- /.col -->
			</div><!-- /.row -->
			@include('general.modal_del')
			<!--Rota para deletar via ajax-->
			<div id='rotaDel' class="hidden">{{url('estoquefisico')}}/</div>
			<!--Rota para redirecionar via ajax-->
			<div id='rotaIndex' class="hidden">{{route('estoquefisico.index')}}</div>
		</div><!-- /.content-wrapper -->
	</div>
</div>
<script src="{{URL::to('plugins/boostrap-table/extensions/js/mindmup-editabletable.js')}}" type="text/javascript"></script>
<script type="text/javascript" src="{{URL::to('js/estoquefisico.js')}}"></script> 
<script type="text/javascript">
	$(document).ready(function($) {
		
		@if(isset($show) || str_contains(Request::url(), '/edit'))
			desativarInputsEspecificos(['.selectChosen']);
			$(".selectChosen").trigger('chosen:updated');
			@if(isset($show))
				var estoquefisicosetor = {!!$estoquefisicosetor!!};
				preencherTblEstoque(estoquefisicosetor);
				disableInputCells('tblEstoqueFisico', []);
				desativarInputsEspecificos(["#datacompetencia", 'input[type="checkbox"]']);
			@elseif($errors -> any())
				preencherTblEstoque(JSON.parse($("#estoqueAlterado").val()));
			@else
				var estoquefisicosetor = {!!$estoquefisicosetor!!};
				preencherTblEstoque(estoquefisicosetor);
			@endif
		@else 
            @if($errors->any())
				preencherTblEstoque(JSON.parse($("#estoqueAlterado").val()));
			@else
				preencherTblEstoque();
			@endif
		@endif
	});
</script>
@endsection
