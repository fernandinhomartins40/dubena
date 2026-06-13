<div class="form-group crud_space">
    <div class="col-sm-10 col-md-push-2">
        <i>Impostos interestaduais serão usados apenas na emissão da NF-e modelo 55</i>
    </div>
</div>
<br />
<div class="form-group crud_space">
    {{ Form::label('origem_uf', 'Origem:', ['class'=>'col-sm-2 control-label input-sm input-uf']) }}
    <div class="col-sm-3">
        {{ Form::select('origem_uf', $estados, null, ['id'=>'origem_uf', 'class' => 'form-control selectChosen input-uf']) }}
    </div>
    {{ Form::label('destino_uf', 'Destino:', ['class'=>'col-sm-2 control-label input-sm input-uf']) }}
    <div class="col-sm-3">
        {{ Form::select('destino_uf', $estados, null, ['id'=>'destino_uf', 'class' => 'form-control selectChosen input-uf']) }}
    </div>
</div>
<br />
<div class="form-group crud_space">
    <div class="col-sm-5 col-md-push-1">
        <h4>Pessoa Jurídica</h4>
    </div>
</div>
<br />
<div class="form-group crud_space">
    {{ Form::label('estadosnficms_id', 'Cód ICMS:', ['class'=>'col-sm-2 control-label input-sm input-uf']) }}
    <div class="col-sm-3">
        {{ Form::select('estadosnficms_id', $nficms, null, ['id'=>'estadosnficms_id', 'class' => 'form-control selectChosen input-uf']) }}
    </div>
    <div class="ufPjGroupICMSNormal">
        {{ Form::label('estadosnficmsaliq', 'Alíquota Inter:', ['class'=>'col-sm-2 control-label input-sm input-uf']) }}
        <div class="col-sm-1">
            {{ Form::text('estadosnficmsaliq', null, ['id'=>'estadosnficmsaliq', 'class' => 'form-control input-sm input-uf percentagemAlowZero']) }}
        </div>
        {{ Form::label('estadosnficmsbase', 'Base ICMS:', ['class'=>'col-sm-1 control-label input-sm input-uf']) }}
        <div class="col-sm-1">
            {{ Form::text('estadosnficmsbase', null, ['id'=>'estadosnficmsbase', 'class' => 'form-control input-sm input-uf baseCalculoSuffix']) }}
        </div>
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('estadosbeneficiario_id', 'Código Beneficiario:', ['class'=>'col-sm-2 control-label input-sm input-uf']) }}
    <div class="col-sm-3">
        {{ Form::select('estadosbeneficiario_id',$beneficiario,null,['id' => 'estadosbeneficiario_id','class'=>'form-control input-uf selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    <div class="ufPjGroupMODBC">
        {{ Form::label('estadosmodicms', 'Modalidade BC:', ['class'=>'col-sm-2 control-label input-sm input-uf']) }}
        <div class="col-sm-3">
            {{ Form::select('estadosmodicms', $modalidade, null, ['id'=>'estadosmodicms', 'class' => 'form-control selectChosen input-uf']) }}
        </div>
    </div>
    <div class="ufPjGroupICMSFCPNormal">
        {{ Form::label('estadostaxafecop', 'Taxa FECOP:', ['class'=>'col-sm-2 control-label input-sm']) }}
        <div class="col-sm-1">
            {{ Form::text('estadostaxafecop', null, ['id'=>'estadostaxafecop', 'class' => 'form-control input-sm percentagemAlowZero']) }}
        </div>
    </div>
</div>
<div class="form-group crud_space">
    <div class="ufPjGroupMODBCST">
        {{ Form::label('estadosmodicmsst', 'Modalidade BC ST:', ['class'=>'col-sm-2 control-label input-sm input-uf']) }}
        <div class="col-sm-3">
            {{ Form::select('estadosmodicmsst', $modalidadest, null, ['id'=>'estadosmodicmsst', 'class' => 'form-control selectChosen input-uf']) }}
        </div>
    </div>
    <div class="ufPjGroupICMSST">
        {{ Form::label('estadosaliqicmsst', 'Alíquota ST:', ['class'=>'col-sm-2 control-label input-sm input-uf']) }}
        <div class="col-sm-1">
            {{ Form::text('estadosaliqicmsst', null, ['id'=>'estadosaliqicmsst', 'class' => 'form-control input-sm input-uf percentagemAlowZero']) }}
        </div>
        {{ Form::label('estadopjmva', 'MVA Ajustado:', ['class'=>'col-sm-1 control-label input-sm input-uf']) }}
        <div class="col-sm-1">
            {{ Form::text('estadopjmva', null, ['id'=>'estadopjmva', 'class' => 'form-control input-sm input-uf percentagemAlowZero']) }}
        </div>
        {{ Form::label('estadonficmsbasest', 'Base ST:', ['class'=>'col-sm-1 control-label input-sm input-uf']) }}
        <div class="col-sm-1">
            {{ Form::text('estadonficmsbasest', null, ['id'=>'estadonficmsbasest', 'class' => 'form-control input-sm input-uf baseCalculoSuffix']) }}
        </div>
    </div>
</div>
<div class="form-group crud_space">
    <div class="ufPjGroupICMSDeson">
        {{ Form::label('estadosnfmotdesonicms', 'Motivo de Desoneração:', ['class' => 'col-sm-2 control-label input-sm input-uf']) }}
        <div class="col-sm-3">
            {{ Form::select('estadosnfmotdesonicms', [], null, ['id'=>'estadosnfmotdesonicms', 'class' => 'form-control selectChosen input-uf input-sm input-uf']) }}
        </div>
    </div>
</div>
<div class="form-group crud_space">
    <div class="col-sm-5 col-md-push-1">
        <h4>Pessoa Física</h4>
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('estadopfnficms_id', 'Cód ICMS:', ['class'=>'col-sm-2 control-label input-sm input-uf']) }}
    <div class="col-sm-3">
        {{ Form::select('estadopfnficms_id', $nficms, null, ['id'=>'estadopfnficms_id', 'class' => 'form-control selectChosen input-uf']) }}
    </div>
    <div class="ufPfGroupICMSNormal">
        {{ Form::label('estadopfnficmsaliq', 'Alíquota Inter:', ['class'=>'col-sm-2 control-label input-sm input-uf']) }}
        <div class="col-sm-1">
            {{ Form::text('estadopfnficmsaliq', null, ['id'=>'estadopfnficmsaliq', 'class' => 'form-control input-sm input-uf percentagemAlowZero']) }}
        </div>
        {{ Form::label('pfaliqicmsdest', 'Alíquota Dest:', ['class'=>'col-sm-1 control-label input-sm input-uf']) }}
        <div class="col-sm-1">
            {{ Form::text('pfaliqicmsdest', null, ['id'=>'pfaliqicmsdest', 'class' => 'form-control input-sm input-uf  percentagemAlowZero']) }}
        </div>
        {{ Form::label('estadopfnficmsabase', 'Base ICMS:', ['class'=>'col-sm-1 control-label input-sm input-uf']) }}
        <div class="col-sm-1">
            {{ Form::text('estadopfnficmsabase', null, ['id'=>'estadopfnficmsabase', 'class' => 'form-control input-sm input-uf  baseCalculoSuffix']) }}
        </div>
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('estadospfbeneficiario_id', 'Código Beneficiario:', ['class'=>'col-sm-2 control-label input-uf input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('estadospfbeneficiario_id',$beneficiario,null,['id' => 'estadospfbeneficiario_id','class'=>'form-control input-uf selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    <div class="ufPfGroupMODBC">
        {{ Form::label('estadopfmodicms', 'Modalidade BC:', ['class'=>'col-sm-2 control-label input-sm input-uf']) }}
        <div class="col-sm-3">
            {{ Form::select('estadopfmodicms', $modalidade, null, ['id'=>'estadopfmodicms', 'class' => 'form-control selectChosen input-uf']) }}
        </div>
    </div>
</div>
<div class="form-group crud_space">
    <div class="ufPfGroupMODBCST">
        {{ Form::label('estadopfmodicmsst', 'Modalidade BC ST:', ['class'=>'col-sm-2 control-label input-sm input-uf']) }}
        <div class="col-sm-3">
            {{ Form::select('estadopfmodicmsst', $modalidadest, null, ['id'=>'estadopfmodicmsst', 'class' => 'form-control selectChosen input-uf']) }}
        </div>
    </div>
    <div class="ufPfGroupICMSFCPST ufPfGroupICMSFCPNormal">
        {{ Form::label('estadopftxafecop', 'Taxa FECOP:', ['class'=>'col-sm-2 control-label input-sm input-uf']) }}
        <div class="col-sm-1">
            {{ Form::text('estadopftxafecop', null, ['id'=>'estadopftxafecop', 'class' => 'form-control input-sm input-uf percentagemAlowZero']) }}
        </div>
    </div>
</div>

<div class="form-group crud_space">
    <div class="ufPfGroupICMSDeson">
        {{ Form::label('pfestadosnfmotdesonicms', 'Motivo de Desoneração:', ['class' => 'col-sm-2 control-label input-sm input-uf']) }}
        <div class="col-sm-3">
            {{ Form::select('pfestadosnfmotdesonicms', [], null, ['id'=>'pfestadosnfmotdesonicms', 'class' => 'form-control selectChosen input-uf input-sm input-uf']) }}
        </div>
    </div>
    <div class="col-sm-2 col-sm-offset-2">
        <button id="btnAdicionarImpostos" type="button" class="btn btn-nw-buscas btn-xs">Adicionar</button>
    </div>
</div>
<hr/>
<div class="form-group crud_space" style="width: 96%; margin-left: 2%;">
    <div class="col-md-12">
        {{ Form::hidden('impostosestados', null, ['id'=>'impostosestados', 'class' => 'form-control input-sm input-uf']) }}
        {{ Form::hidden('id_imp_est', null, ['id' => 'id_imp_est']) }}
        <table id="tblImpostosEstado" class="table">
            <thead>
                <tr>
                    <th field-id="buttons">Operações &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</th>
                    <th sort-by="true" hidden="true" field-id="id"></th>
                    <th sort-by="true" field-id="origem_uf">Origem</th>
                    <th sort-by="true" field-id="destino_uf">Destino</th>
                    <th sort-by="true" hidden="true" field-id="nficms_id"></th>
                    <th sort-by="true" field-id="nficms_id_desc" limit="50">Cod. ICMS</th>
                    <th sort-by="true" hidden="true" field-id="beneficiario_id"></th>
                    <th sort-by="true" field-id="beneficiario_id_desc" limit="50">Cod. Benef.</th>
                    <th sort-by="true" field-id="nficmsaliq">Alíq Inter</th>
                    <th sort-by="true" field-id="nficmsbase">Base ICMS</th>
                    <th sort-by="true" hidden="true" field-id="nficmsmodalidadebc"></th>
                    <th sort-by="true" field-id="nficmsmodalidadebc_desc" limit="50">Mod. BC</th>
                    <th sort-by="true" hidden="true" field-id="nficmsstmodalidadebc"></th>
                    <th sort-by="true" field-id="nficmsstmodalidadebc_desc" limit="50">Mod. BC ST</th>
                    <th sort-by="true" field-id="taxafecop">Taxa FECOP PJ</th>
                    <th sort-by="true" field-id="nficmsbasest">Base ICMS ST</th>
                    <th sort-by="true" field-id="nficmsstaliq">Alíq ST</th>
                    <th sort-by="true" field-id="mva">MVA Ajustado</th>
                    <th sort-by="true" hidden="true" field-id="pfnficms_id"></th>
                    <th sort-by="true" field-id="pfnficms_id_desc" limit="50">Cod. ICMS PF</th>
                    <th sort-by="true" hidden="true" field-id="pfbeneficiario_id"></th>
                    <th sort-by="true" field-id="pfbeneficiario_id_desc" limit="50">Cod. Benef. PF</th>
                    <th sort-by="true" field-id="pfnficmsaliq">Alíq Inter PF</th>
                    <th sort-by="true" field-id="pfaliqicmsdest">Alíq Dest PF</th>
                    <th sort-by="true" field-id="pfnficmsbase">Base ICMS PF</th>
                    <th sort-by="true" hidden="true" field-id="pfnficmsmodalidadebc"></th>
                    <th sort-by="true" field-id="pfnficmsmodalidadebc_desc" limit="50">Mod. BC PF</th>
                    <th sort-by="true" hidden="true" field-id="pfnficmsstmodalidadebc"></th>
                    <th sort-by="true" field-id="pfnficmsstmodalidadebc_desc" limit="50">Mod. BC ST PF</th>
                    <th sort-by="true" field-id="pftaxafecop">Taxa FECOP PF</th>
                    <th sort-by="true" hidden="true" field-id="nfmotdesonicms"></th>
                    <th sort-by="true" field-id="nfmotdesonicms_desc" limit="50">Motivo Deson.</th>
                    <th sort-by="true" hidden="true" field-id="pfnfmotdesonicms"></th>
                    <th sort-by="true" field-id="pfnfmotdesonicms_desc" limit="50">Motivo Deson. PF</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
