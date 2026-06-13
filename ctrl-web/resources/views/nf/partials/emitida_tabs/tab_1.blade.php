<div class="row">
    <div id="tabCadastro" class="col-md-12">
        <div class="box-body">
            <div class="form-group crud_space">
                {{ Form::hidden('id', null, array('id' => 'id')) }}
                {{ Form::label('nfmodelo', 'Modelo:', ['class'=>'col-sm-1 control-label input-sm', 'autofocus' => 'true']) }}
                <div class="col-sm-2">
                    <div class="col-sm-12">
                        {{ Form::select('nfmodelo', $nfmodelos, null,['class'=>'form-control input-sm selectDisableSearch']) }}
                    </div>
                </div>
                <div class="col-sm-6">
                    {{ Form::label('datahoraemissao', 'Emissão:', ['class'=> 'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-4">
                        <div class="input-group generalDateTimePicker">
                            {{ Form::text('datahoraemissao',null,['class'=>'form-control input-sm generalDateTimePicker']) }}
                            <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
                        </div>
                    </div>
                    {{ Form::label('datahoraentradasaida', 'Entrada/Saída:', ['class'=>'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-4">
                        <div class="input-group generalDateTimePicker">
                            {{ Form::text('datahoraentradasaida',null,['class'=>'form-control input-sm generalDateTimePicker']) }}
                            <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3">
                    {{ Form::label('nfnumero', 'Número:', ['class'=>'col-sm-2 control-label input-sm', 'style' => 'padding-left: 0% !important']) }}
                    <div class="col-sm-5">
                        {{ Form::text('nfnumero',null,['class'=>'form-control input-sm', 'style' => 'width: 90% !important; margin-left: 11% !important']) }}
                    </div>
                    {{ Form::label('nfserie', 'Série:', ['class'=> 'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('nfserie', null, ['class'=>'form-control input-sm']) }}
                    </div>
                </div>
                <div class="hidden">
                    {{ Form::label('nftipoambiente', 'Ambiente:', ['class'=>'col-sm-1 control-label input-sm']) }}
                    <div class="col-sm-2">
                        {{ Form::select('nftipoambiente', $nftiposambiente, null,['class'=>'form-control input-sm selectDisableSearch']) }}
                    </div>
                    {{ Form::hidden('ambiente55', $empresa->nfetipoambiente, array('id' => 'ambiente55')) }}
                    {{ Form::hidden('ambiente65', $empresa->nfcetipoambiente, array('id' => 'ambiente65')) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('nfefinalidade', 'Finalidade NF:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    <div class="col-sm-12">
                        {{ Form::select('nfefinalidade', $nffinalidade, null, ['class'=>'form-control input-sm selectDisableSearch']) }}
                    </div>
                </div>
                <div class="col-sm-5" style="right: 5.2%; !important">
                    {{ Form::label('presencacomprador', 'Presença Comprador:', ['class'=>'col-sm-4 control-label input-sm']) }}
                    <div class="col-sm-8">
                        {{ Form::select('presencacomprador', $presencascomprador, null,
                            ['class'=>'form-control input-sm selectDisableSearch', 'style'=>"width: 120%; !important"]
                        ) }}
                    </div>
                </div>
                <div class="col-sm-4">
                    {{ Form::label('nfoperacao_id', 'Operação:', ['class'=>'col-sm-2 control-label input-sm', 'style' => 'right: 14% !important']) }}
                    <div class="col-sm-10">
                    <!--{{$ope = $nfoperacaos->pluck('descricao', 'id')->prepend('Selecione', '')}}-->
                        {{ Form::select('nfoperacao_id', $ope, null,['class'=>'form-control input-sm selectChosen']) }}
                    </div>
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('cliente_id', 'Destinatário:', ['class'=> 'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-6">
                    <div class="col-sm-12">
                        <div class="input-group" style="width: 105% !important;">
                            <select id="nomecliente" name="nomecliente" class="form-control input-sm"
                                    placeholder="Buscar Destinatário" data-selectize-value='[]'>
                            </select>
                            <span class="input-group-addon" style="font-size: 13px; padding-top: 0px; padding-bottom: 0px;">
                                <a disabled="true" href="#" data-toggle="tooltip" data-placement="bottom" data-trigger="hover"
                                   title="Recarregar Emitente/Destinatário" id="reloadDestinatario">
                                    <i class="glyphicon glyphicon-refresh"></i>
                                </a>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-5">
                    {{ Form::label('indfinal', 'Consumidor Final:', ['class'=> 'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-1 checkbox">
                        <!--{{$indFinal = isset($nfemitida) ? $nfemitida->indfinal : "0"}}-->
                        {{ Form::checkbox('indfinal', $indFinal, $indFinal, ['id' => 'indfinal']) }}
                    </div>
                    {{ Form::label('iddest', 'Destino da Operação:', ['class'=>'col-sm-4 control-label input-sm']) }}
                    <div class="col-sm-4">
                    <!--{{$idDest = collect([1 => "Interna", 2 => "Interestadual"])}}-->
                        {{ Form::select('iddest', $idDest, null,['class'=>'form-control input-sm selectChosen']) }}
                    </div>
                </div>
                {{ Form::hidden('operacaotiponf', null, array('id' => 'operacaotiponf')) }}
                {{ Form::hidden('operacaocadastronf', null, array('id' => 'operacaocadastronf')) }}
            </div>
            <div class="form-group crud_space">
                <div class="hidden">
                    {{ Form::label('empresa_id', 'Emitente:', ['class'=>'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::select('empresa_id', [$empresa->id => $empresa->razao_social], $empresa->id, ['class' => 'form-control selectDisableSearch']) }}
                    </div>
                </div>
                {{ Form::hidden('cliente_id',isset($nfemitida) ? $nfemitida->cliente_id : null,['id' => 'cliente_id']) }}
                {{ Form::hidden('cliente_id_erro',isset($nfemitida) ? $nfemitida->cliente_id : null, ['id'=>'cliente_id_erro']) }}
                {{ Form::hidden('cliente_nome_erro',@$nomecliente, ['id'=>'cliente_nome_erro']) }}
            </div>
            <div class="form-group crud_space">
                {{ Form::label('informacaocomplementar', 'Inf. Compl.:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-11">
                    <div class="col-sm-12">
                        {{ Form::textarea('informacaocomplementar',null,['class'=>'form-control input-sm', 'rows' => 2]) }}
                    </div>
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('informacaoadicionalfisco', 'Inf. Fisco:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-11">
                    <div class="col-sm-12">
                        {{ Form::textarea('informacaoadicionalfisco',null,['class'=>'form-control input-sm', 'rows' => 2]) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
