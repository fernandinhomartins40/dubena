<div class="row">
    <div id="tabCadastro" class="col-md-12">
        <div class="box-body">
            <div class="form-group crud_space">
                {{ Form::label('tipolancamento', 'Emissão:', ['class'=>'col-sm-2 control-label input-sm', 'autofocus' => 'true']) }}
                <div class="col-sm-2">
                    {{ Form::select('tipolancamento', ["0" => "Própria", "1" => "Terceiros"], null, ['class'=>'form-control input-sm selectDisableSearch']) }}
                </div>
                {{ Form::label('nfsituacao_id', 'Situação:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::select('nfsituacao_id', $situacoes, null, ['class'=>'form-control input-sm selectDisableSearch']) }}
                </div>
                {{ Form::label('chaveacesso', 'Chave Acesso:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-3">
                    {{ Form::text('chaveacesso',null,['class'=>'form-control input-sm number', 'placeholder'=>'', 'id' => 'chaveacesso']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::hidden('id', null, array('id' => 'id')) }}
                {{ Form::label('nfmodelo', 'Modelo:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('nfmodelo', null, ['class'=>'form-control input-sm', 'placeholder'=>'']) }}
                </div>
                {{ Form::label('nfnumero', 'Número:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('nfnumero',null,['class'=>'form-control input-sm', 'placeholder'=>'']) }}
                </div>
                {{ Form::label('nfserie', 'Série:', ['class'=> 'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('nfserie', null, ['class'=>'form-control input-sm', 'placeholder'=>'']) }}
                </div>
                {{ Form::label('nfsubserie', 'Sub Série:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('nfsubserie', null, ['class'=>'form-control input-sm', 'placeholder'=>'']) }}
                </div>
                {{ Form::label('nftipoemissao', 'Tipo Emissão:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('nftipoemissao', null, ['class'=>'form-control input-sm']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('datahoraemissao', 'Emissão:', ['class'=> 'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    <div class="input-group generalDateTimePicker">
                        {{ Form::text('datahoraemissao',null,['class'=>'form-control input-sm generalDateTimePicker']) }}
                        <span class="input-group-addon">
                            <span class="glyphicon glyphicon-calendar"></span>
                        </span>
                    </div>
                </div>
                {{ Form::label('datahoraentradasaida', 'Entrada/Saída:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    <div class="input-group generalDateTimePicker">
                        {{ Form::text('datahoraentradasaida',null,['class'=>'form-control input-sm generalDateTimePicker']) }}
                        <span class="input-group-addon">
                            <span class="glyphicon glyphicon-calendar"></span>
                        </span>
                    </div>
                </div>
                {{ Form::label('nfefinalidade', 'Finalidade NF:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-3">
                    {{ Form::select('nfefinalidade', $nffinalidade, null,['class'=>'form-control input-sm selectDisableSearch']) }}
                </div>
            </div>
            <div class="form-group crud_space">

                {{ Form::label('nfoperacao_id', 'Operação:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-5">
                    {{ Form::select('nfoperacao_id', [], null, ['class'=>'form-control input-sm selectChosen']) }}
                </div>
                {{ Form::label('empresa_id', 'Emitente:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-3">
                    {{ Form::select('empresa_id', [$empresa->id => $empresa->razao_social], $empresa->id, ['class' => 'form-control selectDisableSearch']) }}
                </div>
            <!--{{$opeE = $operacaoemitida->pluck('descricao', 'id')->prepend('Selecione', '')}}-->
                {{ Form::select('operacaoemitida', $opeE, null, ['id' => 'operacaoemitida', 'class' => 'hidden']) }}
            <!--{{$opeR = $operacaorecebida->pluck('descricao', 'id')->prepend('Selecione', '')}}-->
                {{ Form::select('operacaorecebida', $opeR, null, ['id' => 'operacaorecebida', 'class' => 'hidden']) }}
                {{ Form::hidden('prev_operacao_id', isset($nfrecebida) ? $nfrecebida->nfoperacao_id : null, ['id' => 'prev_operacao_id']) }}
                {{ Form::hidden('operacaotiponf', null, array('id' => 'operacaotiponf')) }}
                {{ Form::hidden('operacaocadastronf', null, array('id' => 'operacaocadastronf')) }}
            </div>
            <div class="form-group crud_space">

                {{ Form::label('cliente_id', 'Destinatário:', ['class'=> 'col-sm-1 control-label input-sm', 'style' => 'margin-left: 8.3% !important']) }}
                <div class="col-sm-5">
                    <div class="input-group">
                        <select id="nomecliente" name="nomecliente" class="form-control input-sm" placeholder="Buscar Destinatário" data-selectize-value = '[]'></select>
                        <span class="input-group-addon" style="font-size: 13px; padding-top: 0px; padding-bottom: 0px;">
                            <a disabled="true" href="#" data-toggle="tooltip" data-placement="bottom" data-trigger="hover"
                               title="Recarregar Emitente/Destinatário" id="reloadDestinatario">
                                <i class="glyphicon glyphicon-refresh"></i>
                            </a>
                        </span>
                    </div>
                </div>
                {{ Form::label('indfinal', 'Consumidor Final:', ['class'=> 'col-sm-2 control-label input-sm', 'style' => 'padding-right: 8% !important']) }}
                <div class="col-sm-1 checkbox" style='left: -8% !important'>
                <!--{{$indFinal = isset($nfrecebida) ? $nfrecebida->indfinal : "0"}}-->
                    {{ Form::checkbox('indfinal', $indFinal, $indFinal, ['id' => 'indfinal']) }}
                </div>
                <div class="col-sm-2" style="right: 14.5% !important;">
                    {{ Form::label('iddest', 'Destino da Operação:', ['class'=>'col-sm-9 control-label input-sm']) }}
                    <div class="col-sm-3">
                    <!--{{$idDest = collect([1 => "Interna", 2 => "Interestadual"])}}-->
                        {{ Form::select('iddest', $idDest, null,['class'=>'form-control input-sm selectChosen']) }}
                    </div>
                </div>
                {{ Form::hidden('cliente_id',isset($nfrecebida) ? $nfrecebida->cliente_id : "",['id' => 'cliente_id']) }}
                {{ Form::hidden('cliente_id_erro',isset($nfrecebida) ? $nfrecebida->cliente_id : "", ['id'=>'cliente_id_erro']) }}
                {{ Form::hidden('cliente_nome_erro',@$nomecliente, ['id'=>'cliente_nome_erro']) }}
            </div>
            <div class="form-group crud_space">
                {{ Form::label('informacaocomplementar', 'Informações Complementares:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-9">
                    {{ Form::textarea('informacaocomplementar',null,['class'=>'form-control input-sm', 'rows' => 3]) }}
                </div>
            </div>
        </div>
    </div>
</div>
