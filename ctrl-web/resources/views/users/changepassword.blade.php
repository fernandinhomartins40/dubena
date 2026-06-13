
@extends('layouts.mainmenu')

@section('content')
<link href="{{URL::to('plugins/tree-multiselect.min.css')}}" rel="stylesheet" type="text/css" />
<link href="{{URL::to('plugins/jstree/dist/themes/default/style.min.css')}}" rel="stylesheet" type="text/css" />
<div id="mainContent" class="content">
	<div id="divCadastro" class="row">
		<div class="col-md-6 col-md-offset-3">
			<div class="nav-tabs-custom">
                                <div class="header panel-default">
                                    <div class="panel-heading">
                                        <h3 class="panel-title">
                                            Alteração de Senha
                                        </h3>
                                    </div>
                                </div>
				<ul class="nav nav-tabs">
					
				</ul>
				{{ Form::model($User, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'route' => array('user.updatepassword', $User->id))) }}
				<div class="tab-content">

					<div class="tab-pane active" id="tab_1">
						<!-- form start -->

						<div class="row">
							<div id="tabCadastro" class="col-md-10">
								<div class="box-body">
									<div class="form-group crud_space">
										{!! Form::label('name', 'Nome:', ['class'=>'col-sm-3 control-label input-sm']) !!}
										<div class="col-sm-4">
											{!! Form::text('name',null,['class'=>'form-control input-sm', 'disabled']) !!}
										</div>
									</div>
									<div class="form-group crud_space">
										{!! Form::label('password_old', 'Senha Atual:', ['class'=>'col-sm-3 control-label input-sm']) !!}
										<div class="col-sm-9">
											{!! Form::password('password_old',null,['class'=>'form-control input-sm']) !!}
										</div>
									</div>
									<div class="form-group crud_space">
										{!! Form::label('password', 'Nova Senha:', ['class'=>'col-sm-3 control-label input-sm']) !!}
										<div class="col-sm-9">
											{!! Form::password('password',null,['class'=>'form-control input-sm']) !!}
										</div>
									</div>
									<div class="form-group crud_space">
										{!! Form::label('password_confirmation', 'Confirme a senha:', ['class'=>'col-sm-3 control-label input-sm']) !!}
										<div class="col-sm-9">
											{!! Form::password('password_confirmation',null,['class'=>'form-control input-sm']) !!}
										</div>
									</div>
								</div>
							</div><!-- /.tab-pane -->
						</div><!-- /.tab-pane -->
					</div><!-- /.tab-content -->
				</div><!-- /.col -->
                                <div class="box-footer">
                                    <div class="col-md-4">
                                        {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                                        <a type="button" href="{!!url('home')!!}" class="btn btn-nw-geral">Voltar</a>
                                    </div>
                                </div>
			</div>
			{!! Form::close() !!}
		</div>
	</div>
</div>
		<!-- page script -->
		<script type="text/javascript">



			jQuery(document).ready(function($){
			});

			$("#fmCadastro").on("submit", function(){
			});

			/*
				$('form').on('submit',function(){
				if($('#password').val()!=$('#password1').val()){
				alert('Senhas não conferem');
				return false;
				}
				return true;
				});
			*/
		</script>
		@endsection
