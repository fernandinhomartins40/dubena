<div class="form-group crud_space">
    {{ Form::label('emailremetente', 'E-Mail do Remetente:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-md-3">
        {{ Form::text('emailremetente',null,['id' => 'emailremetente','class'=>'form-control input-sm']) }}
    </div>
    {{ Form::label('emailsenha', 'Senha do E-Mail:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-md-2">
        <input type="password" name="emailsenha" id="emailsenha" class="form-control input-sm">
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('emailservidorsmtp', 'Servidor SMTP:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-md-3">
        {{ Form::text('emailservidorsmtp',null,['id' => 'emailservidorsmtp','class'=>'form-control input-sm']) }}
    </div>
    {{ Form::label('emailportasmtp', 'Porta SMTP:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-md-2">
        {{ Form::select('emailportasmtp',["" => "Selecione", "465" => "465", "587" => "587"], null,['id' => 'emailportasmtp','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
{{-- <div class="form-group crud_space">
    <div id="boxemailrequerautenticacao">
        {{ Form::label('emailrequerautenticacao', 'Requer Autenticação:', ['class'=>'col-md-2 control-label input-sm']) }}
        <div class="col-md-1 checkbox">
            {{ Form::checkbox('emailrequerautenticacao',1) }}
        </div>
    </div>
    <div id="boxemailrequerconexaotls">
        {{ Form::label('emailrequerconexaotls', 'Requer Conexão TLS:', ['class'=>'col-md-2 control-label input-sm']) }}
        <div class="col-md-1 checkbox">
            {{ Form::checkbox('emailrequerconexaotls',1) }}
        </div>
    </div>
</div> --}}
<div class="form-group crud_space">
    {{ Form::label('emailnomeremente', 'Nome do Remetente:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-md-3">
        {{ Form::text('emailnomeremente',null,['id' => 'emailnomeremente','class'=>'form-control input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('emailassunto', 'Assunto do E-Mail:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-md-4">
        {{ Form::text('emailassunto',null,['id' => 'emailassunto','class'=>'form-control input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('emailcorpo', 'Corpo do E-Mail:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-md-6">
        {{ Form::textarea('emailcorpo', null, ['id'=>'emailcorpo','size' => '30x3','class'=>'form-control']) }}
    </div>
</div>
<div class="form-group crud_space">
    <div class="col-sm-3 col-md-push-2">
        <button id="btnEmailTeste" type="button" class="btn btn-nw-buscas btn-sm">Enviar E-Mail teste</button>
    </div>
</div>
<hr>
<div class="form-group crud_space">
    {{ Form::label('emaildiretoria', 'E-Mail da Diretoria:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-md-4">
        {{ Form::text('emaildiretoria',null,['id' => 'emaildiretoria','class'=>'form-control input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('emailcomercial', 'E-Mail do Comercial:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-md-4">
        {{ Form::text('emailcomercial',null,['id' => 'emailcomercial','class'=>'form-control input-sm']) }}
    </div>
</div>