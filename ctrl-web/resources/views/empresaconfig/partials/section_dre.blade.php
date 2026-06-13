<div class="form-group crud_space">
    {{ Form::label('percentualencargos', 'Encargos (%):', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-2">
        {{ Form::text('percentualencargos',null,['id' => 'percentualencargos','class'=>'form-control percentagem input-sm']) }}
    </div>
    {{ Form::label('percentualprovisaodevedores', 'Provisão Devedores (%):', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-2">
        {{ Form::text('percentualprovisaodevedores',null,['id' => 'percentualprovisaodevedores','class'=>'form-control percentagem input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('percentualremuneracaocapital', 'Remuneração Capital (%):', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-2">
        {{ Form::text('percentualremuneracaocapital',null,['id' => 'percentualremuneracaocapital','class'=>'form-control percentagem input-sm']) }}
    </div>
    {{ Form::label('percentualdistribuicaoresul', 'Distribuição Resultado (%):', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-2">
        {{ Form::text('percentualdistribuicaoresul',null,['id' => 'percentualdistribuicaoresul','class'=>'form-control percentagem input-sm']) }}
    </div>
</div>