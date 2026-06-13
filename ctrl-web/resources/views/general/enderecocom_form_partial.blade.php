<div class="form-group crud_space">
	{!! Form::label('empresa', 'Empresa:', ['class'=>'col-sm-3 control-label input-sm']) !!}
	<div class="col-sm-9">
		{!! Form::text('empresa',null,['class'=>'form-control input-sm']) !!}
	</div>
</div>
<div class="form-group crud_space">
	{!! Form::label('profissao', 'Profissão:', ['class'=>'col-sm-3 control-label input-sm']) !!}
	<div class="col-sm-9">
		{!! Form::text('profissao',null,['class'=>'form-control input-sm']) !!}
	</div>
</div>
<div class="form-group crud_space">
	{!! Form::label('email_com', 'e-mail:', ['class'=>'col-sm-3 control-label input-sm']) !!}
	<div class="col-sm-9">
		{!! Form::text('email_com',null,['class'=>'form-control input-sm']) !!}
	</div>
</div>
<div class="form-group crud_space">
	{!! Form::label('cep_com', 'CEP:', ['class'=>'col-sm-3 control-label input-sm']) !!}
	<div class="col-sm-3">
		{!! Form::text('cep_com',null,['class'=>'form-control input-sm']) !!}
	</div>
	{!! Form::label('uf_com', 'Estado:', ['class'=>'col-sm-3 control-label input-sm']) !!}
	<div class="col-sm-3">
		{!! Form::select('uf_com', $estados, null, ['class' => 'form-control', 'style' => 'width:100%;']) !!}
	</div>
</div>
<div class="form-group crud_space">
	{!! Form::label('cidade_com_id', 'Cidade:', ['class'=>'col-sm-3 control-label input-sm']) !!}
	<div class="col-sm-4">
		{!! Form::select('cidade_com_id', $cidadesC, null, ['class' => 'form-control', 'style' => 'width:88%;float:left;']) !!}
		<a href="#" data-toggle="modal" data-target="#popup_cidade" onclick="origemCidade='cidade_com_id';origemUF='uf_com';nomeBairro='';"><i class="icon fa fa-plus form-control" style="width:10%;border:0px;padding-top:5px;float:right;"></i></a>
	</div>
	{!! Form::label('bairro_com_id', 'Bairro:', ['class'=>'col-sm-2 control-label input-sm']) !!}
	<div class="col-sm-3">
		{!! Form::select('bairro_com_id', $bairrosC, null, ['class' => 'form-control', 'style' => 'width:85%;float:left;']) !!}
		<a href="#" data-toggle="modal" data-target="#popup_bairro" onclick="origemCidade='cidade_com_id';origemUF='uf_com';"><i class="icon fa fa-plus form-control" style="width:10%;border:0px;padding-top:5px;"></i></a>
	</div>
</div>
<div class="form-group crud_space">
	{!! Form::label('endereco_com', 'Endereço:', ['class'=>'col-sm-3 control-label input-sm']) !!}
	<div class="col-sm-7">
		{!! Form::text('endereco_com',null,['class'=>'form-control input-sm']) !!}
	</div>
	<div class="col-sm-2" style="text-align:right;">
		<button type="button" class="btn btn-nw-busca" style="text-align:right;" onclick="buscarCEP('#cidade_com_id', '#uf_com', '#endereco_com', '#cep_com');">buscar CEP</button>
	</div>

</div>
<div class="form-group crud_space">
	{!! Form::label('numero_com', 'Número:', ['class'=>'col-sm-3 control-label input-sm']) !!}
	<div class="col-sm-1">
		{!! Form::text('numero_com',null,['class'=>'form-control input-sm']) !!}
	</div>
	{!! Form::label('complemento_com', 'Complemento:', ['class'=>'col-sm-2 control-label input-sm']) !!}
	<div class="col-sm-6">
		{!! Form::text('complemento_com',null,['class'=>'form-control input-sm']) !!}
	</div>
</div>
