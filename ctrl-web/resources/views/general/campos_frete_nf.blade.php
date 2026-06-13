<div class="form-group crud_space">
    {!! Form::label('freterazaosocial', 'Razão Social:', ['class'=>'col-sm-2 control-label input-sm']) !!}
    <div class="col-sm-8">
        {!! Form::text('freterazaosocial',null,['class'=>'form-control input-sm']) !!}
    </div>
</div>
<div class="form-group crud_space">
    {!! Form::label('fretie', 'Inscrição Estadual:', ['class'=>'col-sm-2 control-label input-sm']) !!}
    <div class="col-sm-2">
        {!! Form::text('fretie',null,['class'=>'form-control input-sm']) !!}
    </div>
    {!! Form::label('fretecnpj', 'CNPJ:', ['class'=>'col-sm-1 control-label input-sm']) !!}
    <div class="col-sm-2">
        {!! Form::text('fretecnpj',null,['class'=>'form-control input-sm']) !!}
    </div>
    {!! Form::label('fretecpf', 'CPF:', ['class'=>'col-sm-1 control-label input-sm']) !!}
    <div class="col-sm-2">
        {!! Form::text('fretecpf',null,['class'=>'form-control input-sm']) !!}
    </div>
</div>
<div class="form-group crud_space">
    {!! Form::label('fretuf', 'UF:', ['class'=>'col-sm-2 control-label input-sm']) !!}
    <div class="col-sm-2">
        {!! Form::text('fretuf',null,['class'=>'form-control input-sm']) !!}
    </div>
    {!! Form::label('fretecidadenome', 'Cidade:', ['class'=>'col-sm-1 control-label input-sm']) !!}
    <div class="col-sm-2">
        {!! Form::text('fretecidadenome',null,['class'=>'form-control input-sm']) !!}
    </div>
    {!! Form::label('freteenderecocompl', 'Endereço:', ['class'=>'col-sm-1 control-label input-sm']) !!}
    <div class="col-sm-2">
        {!! Form::text('freteenderecocompl',null,['class'=>'form-control input-sm']) !!}
    </div>
</div>
<div class="form-group crud_space">
    {!! Form::label('freteplacauf', 'UF Placa:', ['class'=>'col-sm-2 control-label input-sm']) !!}
    <div class="col-sm-2">
        {!! Form::text('freteplacauf',null,['class'=>'form-control input-sm']) !!}
    </div>
    {!! Form::label('freteplaca', 'Placa:', ['class'=>'col-sm-1 control-label input-sm']) !!}
    <div class="col-sm-2">
        {!! Form::text('freteplaca',null,['class'=>'form-control input-sm placa']) !!}
    </div>
    {!! Form::label('vfrete', 'Total Frete:', ['class'=>'col-sm-1 control-label input-sm']) !!}
    <div class="col-sm-2">
        {!! Form::text('vfrete',null,['class'=>'form-control input-sm dinheiro']) !!}
    </div>
</div>
<div class="form-group crud_space">
    {!! Form::label('formapagamento', 'Gera Financeiro:', ['class'=>'col-sm-2 control-label input-sm']) !!}
    <div class="col-sm-3">
        {!! Form::select('formapagamento', [0 => "Nada", 1 => "Pagar", 2 => "Receber"], null,['class'=>'form-control selectChosen']) !!}
    </div>
</div>
