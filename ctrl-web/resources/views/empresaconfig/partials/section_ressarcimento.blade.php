<!-- {{$sector = $setors->prepend('Selecione','')}} -->
<div class="form-group crud_space">
    {{ Form::label('setor_ressarcimento', 'Setor:', ['class'=>'col-md-3 control-label input-sm']) }}
    <div class="col-md-3">
        {{ Form::select('setor_ressarcimento',$sector,null,['id' => 'setor_ressarcimento','class'=>'form-control selectChosen input-sm']) }}
    </div>
    {{ Form::label('operacao_ressarcimento', 'Operação:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('operacao_ressarcimento',$nfoperacao,null,['id' => 'operacao_ressarcimento','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
<br />
