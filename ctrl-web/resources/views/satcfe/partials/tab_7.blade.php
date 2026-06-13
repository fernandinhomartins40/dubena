
<div class="row">
    <div id="tabCadastro" class="col-md-12">
        <div class="box-body">
            <div class="form-group crud_space">
                {{ Form::label('', '', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-10">
                    <!--{{$btnDesc = "Transmitir"}}-->

                    @can('especial', $cupomFiscal)
                        @if(isset($cupomFiscal))
                            <button type="button" id="btnTransmitirCFe" class="btn btn-nw-buscas btn-xs">Transmitir</button>
                            <button type="button" id="btnCancelarCFe" class="btn btn-nw-buscas btn-xs">Cancelar</button>

                            {{--<button type="button" id="btnCancelarNF" class="btn btn-nw-buscas btn-xs">Cancelar</button>--}}

                            {{--<button type="button" id="btnAtualizarStatus" class="btn btn-nw-buscas btn-xs">Consulta Status/Imprimir</button>--}}

                            {{--<button type="button" id="btnEnviarEmail" class="btn btn-nw-buscas btn-xs">Enviar E-mail</button>--}}
                        @endif
                    @endcan
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('nfsituacao_descricao', 'Situação CF-e:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-9">
                    {{ Form::text('nfsituacao_descricao', isset($cupomFiscal) ? $cupomFiscal->status . ' - ' . $cupomFiscal->status_descricao : null, ['class'=>'form-control input-sm', 'disabled' => 'disabled']) }}
                </div>
            </div>
            {{--TODO verificar as operações e status do CF-e--}}
            {{--{{ Form::hidden('nfsituacao_id', isset($nfemitida) ? $nfemitida->nfsituacao_id : null, ['id' => 'nfsituacao_id']) }}--}}
            {{--<div class="form-group crud_space">--}}
                {{--{{ Form::label('statusevento', 'Status Evento:', ['class'=>'col-sm-2 control-label input-sm']) }}--}}
                {{--<div class="col-sm-9">--}}
                    {{--<!--{{$statusevento = null}}-->--}}
                    {{--<!--{{$motivoevento = null}}-->--}}
                    {{--@isset($nfemitida)--}}
                        {{--@if($nfemitida->cancelamentomotivo)--}}
                            {{--<!--{{$statusevento = "135" . ": NF-e Cancelada"}}-->--}}
                            {{--<!--{{$motivoevento = $nfemitida->cancelamentomotivo}}-->--}}
                        {{--@elseif(count($nfemitida->nfEmitidaCartaCorrecao))--}}
                            {{--<!--{{$statusevento = "135" . ": Carta de Correção"}}-->--}}
                            {{--<!--{{$motivoevento = $nfemitida->nfEmitidaCartaCorrecao->last()->xcorrecao}}-->--}}
                        {{--@endif--}}
                    {{--@endisset--}}
                    {{--{{ Form::text('statusevento', $statusevento,['class'=>'form-control input-sm']) }}--}}
                {{--</div>--}}
            {{--</div>--}}
            {{--<div class="form-group crud_space">--}}
                {{--{{ Form::label('motivoevendo', 'Mot. Evento:', ['class'=>'col-sm-2 control-label input-sm']) }}--}}
                {{--<div class="col-sm-9">--}}
                    {{--{{ Form::text('motivoevendo', $motivoevento,['class'=>'form-control input-sm']) }}--}}
                {{--</div>--}}
            {{--</div>--}}
            <div class="form-group crud_space">
                {{ Form::label('xml', 'XML NF:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-9">
                    <div class="input-group">
                        {{ Form::textarea('xml', $cupomFiscal->xml,['rows'=>'3', 'class'=>'form-control input-sm', 'id'=>'xml', 'readonly' => 'readonly']) }}
                        <span data-clipboard-target="#xml" class="input-group-addon xmlCopyClipboard copyClipboard"
                              title="Copiar XML" data-trigger="hover" data-placement="right" data-toggle="tooltip"
                              id="spanCopy" style="font-size: 13px; padding-top: 0; padding-bottom: 0;">
                            <i class="glyphicon glyphicon-copy"></i>
                        </span>
                    </div>
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('chaveacesso', 'Chave de acesso:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-4">
                    <div class="input-group">
                        {{ Form::text('chaveacesso',null,['class'=>'form-control input-sm', 'disabled' => 'disabled']) }}
                        <span data-clipboard-target="#chaveacesso" class="input-group-addon chaveacessoCopyClipboard copyClipboard" 
                              title="Copiar Chave" data-trigger="hover" data-placement="right" data-toggle="tooltip"
                              id="spanCopy" style="font-size: 13px; padding-top: 0; padding-bottom: 0;">
                            <i class="glyphicon glyphicon-copy"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>