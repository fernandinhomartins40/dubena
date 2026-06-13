<div class="form-group crud_space">
    {{ Form::label('pfnfcofins_id', 'Cód Cofins:', ['class'=>'col-sm-2 control-label input-sm percentagem']) }}
    <div class="col-sm-3">
        {{ Form::select('pfnfcofins_id', $nfcofins, null, ['id'=>'pfnfcofins_id', 'class' => 'form-control input-pf selectChosen']) }}
    </div>
    {{ Form::label('pfnfcofinsaliq', 'Alíquota Cofins:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::text('pfnfcofinsaliq', null, ['id'=>'pfnfcofinsaliq', 'class' => 'form-control input-pf input-sm percentagemAlowZero']) }}
    </div>
    {{ Form::label('pfnfcofinsbase', 'Base Cofins:', ['class'=>'col-sm-1 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::text('pfnfcofinsbase', null, ['id'=>'pfnfcofinsbase', 'class' => 'form-control input-pf input-sm percentagemAlowZero']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('pfnfpis_id', 'Cód PIS:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('pfnfpis_id', $nfpis, null, ['id'=>'pfnfpis_id', 'class' => 'form-control input-pf selectChosen']) }}
    </div>
    {{ Form::label('pfnfpisaliq', 'Alíquota PIS:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::text('pfnfpisaliq', null, ['id'=>'pfnfpisaliq', 'class' => 'form-control input-pf input-sm percentagemAlowZero']) }}
    </div>
    {{ Form::label('pfnfpisbase', 'Base PIS:', ['class'=>'col-sm-1 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::text('pfnfpisbase', null, ['id'=>'pfnfpisbase', 'class' => 'form-control input-pf input-sm percentagemAlowZero']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('pfnficms_id', 'Cód ICMS:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('pfnficms_id', $nficms, null, ['id'=>'pfnficms_id', 'class' => 'form-control input-pf selectChosen']) }}
    </div>
    <div class="pfGroupICMSNormal">
        {{ Form::label('pfnficmsaliq', 'Alíquota ICMS:', ['id' => 'pfnficmsaliq_lb', 'class'=>'col-sm-2 control-label input-sm']) }}
        {{ Form::label('pfnficmsalimono', 'Aliquota Monofásica:', ['id' => 'pfnficmsalimono_lb', 'class'=>'col-sm-2 control-label input-sm hidden']) }}
        <div class="col-sm-1">
            {{ Form::text('pfnficmsaliq', null, ['id'=>'pfnficmsaliq', 'class' => 'form-control input-pf input-sm percentagemAlowZero']) }}
            {{ Form::text('pfnficmsalimono', null, ['id'=>'pfnficmsalimono', 'class' => 'form-control input-pj input-sm moneyFourCases hidden']) }}
        </div>
        {{ Form::label('pfnficmsbase', 'Base ICMS:', ['class'=>'col-sm-1 control-label input-sm']) }}
        <div class="col-sm-1">
            {{ Form::text('pfnficmsbase', null, ['id'=>'pfnficmsbase', 'class' => 'form-control input-pf input-sm baseCalculoSuffix']) }}
        </div>
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('pfbeneficiario_id', 'Código Beneficiario:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('pfbeneficiario_id',$beneficiario,null,['id' => 'pfbeneficiario_id','class'=>'form-control input-pj selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    <div class="pfGroupMODBC">
        {{ Form::label('modalidadebcicmspf', 'Modalidade BC:', ['class'=>'col-sm-2 control-label input-sm']) }}
        <div class="col-sm-3">
            {{ Form::select('modalidadebcicmspf', $modalidade, null, ['id'=>'modalidadebcicmspf', 'class' => 'form-control input-pf selectChosen']) }}
        </div>
    </div>
    {{ Form::label('pforigemicms', 'Origem ICMS:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::text('pforigemicms', 0, ['id'=>'pforigemicms', 'class' => 'form-control input-pf input-sm','disabled' => 'true']) }}
    </div>
</div>
<div class="form-group crud_space">
    <div class="pfGroupMODBCST">
        {{ Form::label('modalidadebcicmsstpf', 'Modalidade BC ST:', ['class'=>'col-sm-2 control-label input-sm']) }}
        <div class="col-sm-3">
            {{ Form::select('modalidadebcicmsstpf', $modalidadest, null, ['id'=>'modalidadebcicmsstpf', 'class' => 'form-control input-pf selectChosen']) }}
        </div>
    </div>
    <div class="pfGroupICMSFCPNormal">
        {{ Form::label('pftaxafecopgr', 'Taxa FECOP:', ['class'=>'col-sm-2 control-label input-sm']) }}
        <div class="col-sm-1">
            {{ Form::text('pftaxafecopgr', null, ['id'=>'pftaxafecopgr', 'class' => 'form-control input-pf input-sm percentagemAlowZero']) }}
        </div>
    </div>
</div>
<div class="form-group crud_space">
    <div class="pfGroupICMSDeson">
        {{ Form::label('pfnfmotdesonicms', 'Motivo de Desoneração:', ['class' => 'col-sm-2 control-label input-sm']) }}
        <div class="col-sm-3">
            {{ Form::select('pfnfmotdesonicms', [], null, ['id'=>'pfnfmotdesonicms', 'class' => 'form-control input-pf selectChosen input-sm']) }}
        </div>
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('pfinformacoesadicional', 'Informações Adicionais:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-8">
        {{ Form::textarea('pfinformacoesadicional', null, ['id' => 'pfinformacoesadicional', 'class'=>'form-control input-sm', 'rows' => '3']) }}
    </div>
</div>
