<div class="form-group crud_space">
    {{ Form::label('client_id', 'Client ID:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-4">
        {{ Form::text('client_id',null,['id' => 'client_id','class'=>'form-control input-sm']) }}
    </div>
    {{ Form::label('client_secret', 'Client Secret:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-4">
        {{ Form::text('client_secret',null,['id' => 'client_secret','class'=>'form-control input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('chavepix', 'Chave PIX:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-4">
        {{ Form::text('chavepix',null,['id' => 'chavepix','class'=>'form-control input-sm']) }}
    </div>
</div>
