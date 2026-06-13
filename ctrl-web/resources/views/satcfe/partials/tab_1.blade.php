<div class="row">
    <div id="tabCadastro" class="col-sm-12">
        {{ Form::hidden('empresa_id', $empresa->id,['class'=>'form-control input-sm', 'id' => 'empresa_id']) }}
        <div class="box-body">
            <div class="form-group crud_space">
                {{ Form::label('nfoperacao_id', 'Operação:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-3">
                    {{ Form::hidden('operacaotiponf', null, array('id' => 'operacaotiponf')) }}
                    {{ Form::hidden('operacaocadastronf', null, array('id' => 'operacaocadastronf')) }}
                    {{ Form::hidden("objOperacoes", $nfoperacaos->toJson(), ['id' => "objOperacoes"]) }}
                <!--{{$ope = $nfoperacaos->pluck('descricao', 'id')->prepend('Selecione', '')}}-->
                    {{ Form::select('nfoperacao_id', $ope, null, ['class'=>'form-control input-sm selectChosen']) }}
                </div>
                {{ Form::hidden('cliente_nome_erro',$cupomFiscal->destxnome, ['id'=>'cliente_nome_erro']) }}
                {{ Form::label('cliente_id', 'Destinatário:', ['class'=> 'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-5">
                    <div class="input-group">
                        <select id="nomecliente" name="nomecliente" class="form-control input-sm" placeholder="Buscar Destinatário" data-selectize-value='[]'></select>
                        <span class="input-group-addon" style="font-size: 13px; padding-top: 0; padding-bottom: 0;">
                            <a disabled="true" href="#" data-toggle="tooltip" data-placement="bottom" data-trigger="hover"
                               title="Recarregar Dados do Destinatário" id="reloadDest">
                                <i class="glyphicon glyphicon-refresh"></i>
                            </a>
                        </span>
                    </div>
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('infcpl', 'Inf. Compl.:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-9">
                    {{ Form::textarea('infcpl', $cupomFiscal->infcpl,['class'=>'form-control input-sm', 'rows' => 2]) }}
                </div>
            </div>
        </div>
    </div>
</div>