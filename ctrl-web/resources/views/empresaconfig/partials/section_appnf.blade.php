<div class="form-group crud_space">
    {{ Form::label('pedidooperacaoappnf_id', 'Operação App NF:', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-4">
        {{ Form::select('pedidooperacaoappnf_id',$oppedido,null,['id' => 'pedidooperacaoappnf_id','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('presencacompradorappnf', 'Presença Comprador App:', ['class'=>'col-md-3 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('presencacompradorappnf',$comprador,null,['id' => 'presencacompradorappnf','class'=>'form-control selectChosen input-sm']) }}
    </div>
    {{ Form::label('fretemodalidadeappnf', 'Modalidade Frete App:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('fretemodalidadeappnf',$frete,null,['id' => 'fretemodalidadeappnf','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('transportadorappnf_id', 'Transportadora App:', ['class'=>'col-md-3 control-label input-sm']) }}
    <div class="col-md-4">
        {{ Form::select('transportadorappnf_id',$transportadoras,null,['id' => 'transportadorappnf_id','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('contaappnf_id', 'Conta p/ Boleto App:', ['class'=>'col-md-3 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('contaappnf_id',$contasboleto,null,['id' => 'contaappnf_id','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
