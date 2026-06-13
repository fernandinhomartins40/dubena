<div class="form-group crud_space">
    {{ Form::label('mensagemduplicata', 'Mensagem Duplicata:', ['class'=>'col-md-3 control-label input-sm']) }}
    <div class="col-md-8">
        {{ Form::textarea('mensagemduplicata', null, ['id'=>'mensagemduplicata','size' => '30x3','class'=>'form-control']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('mensagemgasbolso', 'Mensagem Vale Gás:', ['class'=>'col-md-3 control-label input-sm']) }}
    <div class="col-md-8">
        {{ Form::textarea('mensagemgasbolso', null, ['id'=>'mensagemgasbolso','size' => '30x3','class'=>'form-control']) }}
        {{Form::hidden('empresaemitenfe',@$empresaemitenfe, ['id'=>'empresaemitenfe'])}}
        {{Form::hidden('empresaemitenfce',@$empresaemitenfce, ['id'=>'empresaemitenfce'])}}
        {{Form::hidden('matriz',@$matriz, ['id'=>'matriz'])}}
    </div>
</div>