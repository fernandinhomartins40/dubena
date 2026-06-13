<div class="form-group crud_space">
    {{ Form::label('nfoperacao_id', 'Operação:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('nfoperacao_id',$operacoes,null,['id' => 'nfoperacao_id','class'=>'form-control input-pj selectChosen input-sm']) }}
    </div>
    {{ Form::label('grupofiscal_id', 'Grupo Fiscal:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('grupofiscal_id',$grupofiscal,null,['id' => 'grupofiscal_id','class'=>'form-control input-pj selectChosen input-sm']) }}
    </div>
</div>
<hr />
<div class="form-group crud_space">
    {{ Form::label('nfcofins_id', 'Cód Cofins:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('nfcofins_id',$nfcofins,null,['id' => 'nfcofins_id','class'=>'form-control input-pj selectChosen input-sm']) }}
    </div>
    {{ Form::label('nfcofinsaliq', 'Alíquota Cofins:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::text('nfcofinsaliq', null, ['id'=>'nfcofinsaliq', 'class' => 'form-control input-pj input-sm percentagemAlowZero']) }}
    </div>
    {{ Form::label('nfcofinsbase', 'Base Cofins:', ['class'=>'col-sm-1 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::text('nfcofinsbase', null, ['id'=>'nfcofinsbase', 'class' => 'form-control input-pj input-sm percentagemAlowZero']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('nfpis_id', 'Cód PIS:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('nfpis_id', $nfpis, null, ['id'=>'nfpis_id', 'class' => 'form-control input-pj selectChosen']) }}
    </div>
    {{ Form::label('nfpisaliq', 'Alíquota PIS:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::text('nfpisaliq', null, ['id'=>'nfpisaliq', 'class' => 'form-control input-pj input-sm percentagemAlowZero']) }}
    </div>
    {{ Form::label('nfpisbase', 'Base PIS:', ['class'=>'col-sm-1 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::text('nfpisbase', null, ['id'=>'nfpisbase', 'class' => 'form-control input-pj input-sm percentagemAlowZero']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('nficms_id_pj', 'Cód ICMS:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('nficms_id_pj', $nficms, null, ['id'=>'nficms_id_pj', 'class' => 'form-control input-pj selectChosen']) }}
    </div>
    <div class="pjGroupICMSNormal">
        {{ Form::label('nficmsaliq', 'Alíquota ICMS:', ['id' => 'nficmsaliq_lb', 'class'=>'col-sm-2 control-label input-sm']) }}
        {{ Form::label('nficmsalimono', 'Aliquota Monofásica:', ['id' => 'nficmsalimono_lb', 'class'=>'col-sm-2 control-label input-sm hidden']) }}
        <div class="col-sm-1">
            {{ Form::text('nficmsaliq', null, ['id'=>'nficmsaliq', 'class' => 'form-control input-pj input-sm percentagemAlowZero']) }}
            {{ Form::text('nficmsalimono', null, ['id'=>'nficmsalimono', 'class' => 'form-control input-pj input-sm moneyFourCases hidden']) }}
        </div>
        {{ Form::label('nficmsbase', 'Base ICMS:', ['class'=>'col-sm-1 control-label input-sm']) }}
        <div class="col-sm-1">
            {{ Form::text('nficmsbase', null, ['id'=>'nficmsbase', 'class' => 'form-control input-pj input-sm baseCalculoSuffix']) }}
        </div>
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('beneficiario_id', 'Código Beneficiario:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('beneficiario_id',$beneficiario,null,['id' => 'beneficiario_id','class'=>'form-control input-pj selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    <div class="pjGroupMODBC">
        {{ Form::label('modalidadebcicms', 'Modalidade BC:', ['class'=>'col-sm-2 control-label input-sm']) }}
        <div class="col-sm-3">
            {{ Form::select('modalidadebcicms', $modalidade, null, ['id'=>'modalidadebcicms', 'class' => 'form-control input-pj selectChosen']) }}
        </div>
    </div>
    <div class="pjGroupDiferimento">
        {{ Form::label('nfaliqdiferimento', 'Diferimento:', ['class'=>'col-sm-2 control-label input-sm']) }}
        <div class="col-sm-1">
            {{ Form::text('nfaliqdiferimento', null, ['id'=>'nfaliqdiferimento', 'class' => 'form-control input-pj input-sm percentagemAlowZero']) }}
        </div>
    </div>
    {{ Form::label('origemicms', 'Origem ICMS:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::text('origemicms', 0, ['id'=>'nficmsaliq', 'class' => 'form-control input-pj input-sm','disabled' => 'true']) }}
    </div>
    <div class="pjGroupICMSFCPNormal">
        {{ Form::label('taxafecop', 'Taxa FECOP:', ['class'=>'col-sm-1 control-label input-sm']) }}
        <div class="col-sm-1">
            {{ Form::text('taxafecop', null, ['id'=>'taxafecop', 'class' => 'form-control input-pj input-sm percentagemAlowZero']) }}
        </div>
    </div>
</div>
<div class="form-group crud_space">
    <div class="pjGroupMODBCST">
        {{ Form::label('modalidadebcicmsst', 'Modalidade BC ST:', ['class'=>'col-sm-2 control-label input-sm']) }}
        <div class="col-sm-3">
            {{ Form::select('modalidadebcicmsst', $modalidadest, null, ['id'=>'modalidadebcicmsst', 'class' => 'form-control input-pj selectChosen']) }}
        </div>
    </div>
    <div class="pjGroupICMSST">
        {{ Form::label('aliqicmsst', 'Alíquota ST:', ['class'=>'col-sm-2 control-label input-sm']) }}
        <div class="col-sm-1">
            {{ Form::text('aliqicmsst', null, ['id'=>'aliqicmsst', 'class' => 'form-control input-pj input-sm percentagemAlowZero']) }}
        </div>
        {{ Form::label('nficmsbasest', 'Base ST:', ['class'=>'col-sm-1 control-label input-sm']) }}
        <div class="col-sm-1">
            {{ Form::text('nficmsbasest', null, ['id'=>'nficmsbasest', 'class' => 'form-control input-pj input-sm baseCalculoSuffix']) }}
        </div>
    </div>
</div>
<div class="form-group crud_space">
    <div class="pjGroupICMSST">
        {{ Form::label('mva', 'MVA:', ['class'=>'col-sm-2 control-label input-sm']) }}
        <div class="col-sm-1">
            {{ Form::text('mva', null, ['id'=>'mva', 'class' => 'form-control input-pj input-sm percentagemAlowZero']) }}
        </div>
        {{ Form::label('mvareduzido', 'MVA Reduzido:', ['class'=>'col-sm-1 control-label input-sm']) }}
        <div class="col-sm-1">
            {{ Form::text('mvareduzido', null, ['id'=>'mvareduzido', 'class' => 'form-control input-pj input-sm percentagemAlowZero']) }}
        </div>
    </div>
    <div class="pjGroupICMSDeson">
        {{ Form::label('nfmotdesonicms', 'Motivo de Desoneração:', ['class' => 'col-sm-2 control-label input-sm']) }}
        <div class="col-sm-3">
            {{ Form::select('nfmotdesonicms', [], null, ['id'=>'nfmotdesonicms', 'class' => 'form-control input-pj selectChosen input-sm']) }}
        </div>
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('informacoesadicional', 'Informações Adicionais:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-8">
        {{ Form::textarea('informacoesadicional', null, ['id' => 'informacoesadicional', 'class'=>'form-control input-sm', 'rows' => '4']) }}
    </div>
</div>
