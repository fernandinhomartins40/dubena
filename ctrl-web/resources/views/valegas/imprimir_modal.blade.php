<!-- Modal -->
<div class="modal fade" id="imprimir_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document" style="width:50%">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span
                        class="sr-only">Close</span></button>
                <h4 class="modal-title">Impressão de Vale Gás</h4>
            </div>
            <div class="modal-body col-md-12">
                <div class="col-sm-12">
                    {{ Form::open(['id'=>'fmImpressa','url' => 'vendavalegas.imprimirvalegas', 'class' => 'form-horizontal', 'files' => true, 'target'=>'_blank']) }}
                    <div class="form-group crud_space">
                        {{ Form::label('nomecliente', 'Cliente:', ['class'=>'col-md-2 control-label input-sm']) }}
                        <div class="col-md-6">
                            {{-- {{ Form::select('nomecliente', $clientes, null, ['class' => 'form-control selectChosen']) }}
                            --}}
                            <select id="nomecliente" name="nomecliente" class="form-control input-sm"
                                placeholder="Buscar Cliente" data-selectize-value='[]'>
                            </select>
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        {{ Form::label('apartir', 'A partir:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;']) }}
                        <div class="col-sm-2">
                            {{ Form::text('apartir', null,['id'=>'apartir','class'=>'input-sm form-control number']) }}
                        </div>
                        <div id="boxprevenda">
                            {{ Form::label('checkprevenda', 'Pré-Venda', ['class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-md-1 checkbox">
                                {{ Form::checkbox('checkprevenda',1) }}
                            </div>
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        <br />
                        <div class="col-md-8 col-md-offset-2">
                            <!--
                            <strong>Atenção: Apenas 90 etiquetas serão geradas! E assim que geradas o status da mesma
                                mudará para impresso.</strong>
                            -->
                            <strong>Atenção: As etiquetas serão geradas! E assim que geradas o status da mesma
                                mudará para impresso.</strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" id='btnModalImprimir' class="btn btn-nw-registro">Gerar Etiquetas</button>
                <button id="btnvoltarmod" type="button" class="btn btn-nw-geral" data-dismiss="modal">Voltar</button>
            </div>
            {{ Form::hidden('cliente_id',null,['id' => 'cliente_id']) }}
            {{Form::close()}}
        </div>
    </div>
</div>
