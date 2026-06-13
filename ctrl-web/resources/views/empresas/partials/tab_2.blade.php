<!-- form start -->
<div class="row">
    <div id="tabCadastro" class="col-md-12">
        <div class="box-body">
            <div class="form-group crud_space">
                {{ Form::label('nfeemitemodelos', 'Modelos:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    <!--{{$modeloemitido = ''}}-->
                    @isset($Empresa)
                    @if($Empresa->nfeemite && $Empresa->nfceemite)
                    <!--{{$modeloemitido = 3}}-->
                    @elseif($Empresa->nfeemite && !$Empresa->nfceemite)
                    <!--{{$modeloemitido = 1}}-->
                    @elseif(!$Empresa->nfeemite && $Empresa->nfceemite)
                    <!--{{$modeloemitido = 2}}-->
                    @endif
                    @endisset
                    {{ Form::select('nfeemitemodelos', $nfeemitemodelos, $modeloemitido, ['id'=>'nfeemitemodelos','class' => 'form-control selectDisableSearch', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'']) }}
                    {{ Form::hidden('nfeemite', 0, ['id' => 'nfeemite'])}}
                    {{ Form::hidden('nfceemite', 0, ['id' => 'nfceemite'])}}
                </div>
                {{ Form::label('nfecrt', 'CRT:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::select('nfecrt', $nfecrt, null, ['class' => 'form-control selectDisableSearch', 'style'=>'border-radius: 5px ! important;', 'onchange'=> 'showHideCrt()']) }}
                </div>
                <div class="nfecrtcred">
                    {{ Form::label('nfecreditosimplesnacional', 'Crédito Simples Nacional:', ['class'=>'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-1">
                        {{ Form::text('nfecreditosimplesnacional',null,['class'=>'form-control input-sm percentagemAlowZero']) }}
                    </div>
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('contingenciaemissao', 'Contingência:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1 checkbox contingenciaemissao">
                    {{ Form::checkbox('contingenciaemissao') }}
                </div>
                {{ Form::label('contingenciadatahora', 'Data/Hora da Contingência:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    <div class="input-group generalDateTimePickerDefaultDateFalse" >
                        {{ Form::text('contingenciadatahora',null,['class'=>'form-control input-sm generalDateTimePickerDefaultDateFalse', 'id' => 'contingenciadatahora']) }}
                        <span class="input-group-addon">
                            <span class="glyphicon glyphicon-calendar "></span>
                        </span>
                    </div>
                </div>
                {{ Form::label('contingenciajustificativa', 'Justificativa Contingência:', ['class'=>'col-sm-3 control-label input-sm']) }}
                <div class="col-sm-3">
                    {{ Form::text('contingenciajustificativa',null,['class'=>'form-control input-sm']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('nfesenhapfx', 'Senha Certif.:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    <input type="password" class='input-sm form-control' name="nfesenhapfx" id="nfesenhapfx"/>
                </div>
                {{ Form::label('certificadodigital', 'Certificado Digital:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <label class="mousehover-pointer col-sm-4">
                    <span class="btn btn-sm btn-nw-registro fa fa-upload fa-lg">
                        <input type="file" id="certificadodigital" name="certificadodigital" class="btn-file" style="display: none;" accept=".pfx">
                    </span>
                    &nbsp;&nbsp;&nbsp;&nbsp;<span id='upload-filename'>Nenhum arquivo selecionado..</span>
                </label>
            </div>
            <div class="form-group crud_space">
                 {{ Form::label('geraibscbs', 'Gera TAGs IBS/CBS:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1 checkbox">
                    {{ Form::checkbox('geraibscbs') }}
                </div>
            </div>
            <hr />
            <div class="form-group crud_space">
                <div class="col-sm-2 col-sm-push-1" style="font-size: 15px">
                    NF-e
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('nfetipoambiente', 'Ambiente:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::select('nfetipoambiente', $nfetiposambiente, null, ['id'=>'nfetipoambiente','class' => 'form-control selectDisableSearch', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'']) }}
                </div>
                {{ Form::label('nfenumero', 'N°Produção:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('nfenumero',null,['id'=>'nfenumero','class'=>'form-control input-sm']) }}
                    {{ Form::hidden('nfenumero_atual',@$Empresa->nfenumero,['id'=>'nfenumero_atual','class'=>'form-control input-sm']) }}
                </div>
                {{ Form::label('nfenumerohomologacao', 'N° Homologação:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('nfenumerohomologacao',null,['id'=>'nfenumerohomologacao','class'=>'form-control input-sm']) }}
                    {{ Form::hidden('nfenumerohomologacao_atual',@$Empresa->nfenumerohomologacao,['id'=>'nfenumerohomologacao_atual','class'=>'form-control input-sm']) }}
                </div>
                {{ Form::label('nfeserie', 'Série:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('nfeserie',null,['class'=>'form-control input-sm']) }}
                </div>
                <div class="hidden">
                    {{ Form::label('nfemodelo', 'Modelo:', ['class'=>'col-sm-1 control-label input-sm']) }}
                    <div class="col-sm-1">
                        {{ Form::text('nfemodelo', "55", ['class' => 'form-control input-sm','readonly']) }}
                    </div>
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('nfetipoemissao', 'Emissão:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::select('nfetipoemissao', $nfetiposemissao, null, ['class' => 'form-control selectDisableSearch', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'']) }}
                </div>
            </div>
            <hr />
            <div class="form-group crud_space">
                <div class="col-sm-2 col-sm-push-1" style="font-size: 15px">
                    NFC-e
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('nfcetipoambiente', 'Ambiente:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::select('nfcetipoambiente', $nfcetiposambiente, null, ['id'=>'nfcetipoambiente','class' => 'form-control selectDisableSearch', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'']) }}
                </div>
                {{ Form::label('nfcenumero', 'N° Produção:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('nfcenumero',null,['id'=>'nfcenumero','class'=>'form-control input-sm']) }}
                    {{ Form::hidden('nfcenumero_atual',@$Empresa->nfcenumero,['id'=>'nfcenumero_atual','class'=>'form-control input-sm']) }}
                </div>
                {{ Form::label('nfcenumerohomologacao', 'N° Homologação:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('nfcenumerohomologacao',null,['id'=>'nfcenumerohomologacao','class'=>'form-control input-sm']) }}
                    {{ Form::hidden('nfcenumerohomologacao_atual',@$Empresa->nfcenumerohomologacao,['id'=>'nfcenumerohomologacao_atual','class'=>'form-control input-sm']) }}
                </div>
                {{ Form::label('nfceserie', 'Série:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('nfceserie',null,['class'=>'form-control input-sm']) }}
                </div>
                <div class="hidden">
                    {{ Form::label('nfcemodelo', 'Modelo:', ['class'=>'col-sm-1 control-label input-sm']) }}
                    <div class="col-sm-1">
                        {{ Form::text('nfcemodelo', "65", ['class' => 'form-control input-sm','readonly']) }}
                    </div>
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('nfcetipoemissao', 'Emissão:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::select('nfcetipoemissao', $nfcetiposemissao, null, ['class' => 'form-control selectDisableSearch', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'']) }}
                </div>
                {{ Form::label('nfcevalorlimite', 'Valor Limite:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('nfcevalorlimite',null,['class'=>'form-control input-sm dinheiro']) }}
                </div>
                {{ Form::label('nfcetokenid', 'Token ID:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('nfcetokenid', null, ['id' => 'nfcetokenid','class' => 'form-control input-sm number']) }}
                    {{ Form::text('nfcetokenid_prod', null, ['id' => 'nfcetokenid_prod','class' => 'form-control input-sm number hidden']) }}
                </div>
                {{ Form::label('nfcetoken', 'CSC:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-3">
                    {{ Form::text('nfcetoken', null, ['id' => 'nfcetoken','class' => 'form-control input-sm']) }}
                    {{ Form::text('nfcetoken_prod', null, ['id' => 'nfcetoken_prod','class' => 'form-control input-sm hidden']) }}
                </div>
            </div>
            <hr />
            <div class="form-group crud_space">
                <div class="col-sm-2 col-sm-push-1" style="font-size: 15px">
                    SAT CF-e
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('usasat', 'Usa Sat:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1 checkbox">
                    {{ Form::checkbox('usasat') }}
                </div>
                {{ Form::label('sattipoambiente', 'Ambiente:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::select('sattipoambiente', $sattipoambiente, null, ['id'=>'sattipoambiente','class' => 'form-control selectDisableSearch', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'']) }}
                </div>
            </div>
        </div>
    </div>
</div>
