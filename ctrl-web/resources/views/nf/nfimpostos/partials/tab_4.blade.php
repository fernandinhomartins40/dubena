<div class="form-group crud_space">
    {{ Form::hidden('sped', $sped, ['id'=>'sped', 'class' => 'form-control input-sm']) }}
    {{ Form::label('piscofinsgeracredito', 'Gerar Crédito PIS/Cofins', ['class'=>'col-md-3 control-label input-sm']) }}
    <div class="col-sm-1 checkbox">
        {{ Form::checkbox('piscofinsgeracredito',1) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('piscofinstipocredito', 'Tipo Crédito PIS/Cofins (Tabela 4.3.6):', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-6">
        {{ Form::select('piscofinstipocredito', $tipocred, null, ['id'=>'piscofinstipocredito', 'class' => 'form-control selectChosen']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('piscofinsnatreceita', 'Natureza Receita PIS/Cofins (Tabela 4.3.10a 4.3.16):', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-6">
        @if (isset($natreceita))
            {{ Form::select('piscofinsnatreceita', @$natcred, null, ['id'=>'piscofinsnatreceita', 'class' => 'form-control selectChosen']) }}
            {{ Form::hidden('piscofinsnatreceita_hd', @$natreceita, ['id'=>'piscofinsnatreceita_hd']) }}
        @else 
            {{ Form::select('piscofinsnatreceita', [], null, ['id'=>'piscofinsnatreceita', 'class' => 'form-control selectChosen']) }}
        @endif
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('piscofinstipobccredito', 'Tipo Base Cálculo PIS/Cofins (Tabela 4.3.7):', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-6">
        {{ Form::select('piscofinstipobccredito', $tipobase, null, ['id'=>'piscofinstipobccredito','class'=>'form-control selectChosen']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('nfpisaliqcred', 'Aproveitamento Crédito PIS:', ['class'=>'col-sm-3 control-label input-sm percentagemAlowZero']) }}
    <div class="col-sm-1">
        {{ Form::text('nfpisaliqcred', null, ['id'=>'nfpisaliqcred', 'class' => 'form-control input-sm percentagemAlowZero']) }}
    </div>
    {{ Form::label('nfcofinsaliqcred', 'Aproveitamento Crédito Cofins:', ['class'=>'col-sm-2 control-label input-sm percentagemAlowZero']) }}
    <div class="col-sm-1">
        {{ Form::text('nfcofinsaliqcred', null, ['id'=>'nfcofinsaliqcred', 'class' => 'form-control input-sm percentagemAlowZero']) }}
    </div>
</div>