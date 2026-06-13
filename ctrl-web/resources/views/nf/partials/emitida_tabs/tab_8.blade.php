
<div class="row">
    <div id="tabCadastro" class="col-md-12">
        <div class="box-body">
            <div class="form-group crud_space">
                {{ Form::label('', '', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-10">
                    <!--{{$btnDesc = "Transmitir"}}-->
                    @if(isset($contingenciaemissao) && $contingenciaemissao == '1')
                        @if(isset($nfemitida) && $nfemitida->nfmodelo == 55)
                            @if($empresa->nfetipoemissao == 4)
                                <!--{{$btnDesc = "Transmitir EPEC"}}-->
                            @elseif($empresa->nfetipoemissao == 6)
                                <!--{{$btnDesc = "Transmitir SVC-AN"}}-->
                            @elseif($empresa->nfetipoemissao == 7)
                                <!--{{$btnDesc = "Transmitir SVC-RS"}}-->
                            @endif
                        @else
                            @if($empresa->nfcetipoemissao == 4)
                                <!--{{$btnDesc = "Transmitir EPEC"}}-->
                            @endif
                        @endif
                    @endif

                    @can('especial', App\Nfemitida::class)
                        @if(isset($nfemitida) && $nfemitida->nfsituacao_id != 102)
                            <button type="button" id="btnTransmitirNF" class="btn btn-nw-buscas btn-xs">{{$btnDesc}}</button>

                            <button type="button" id="btnCancelarNF" class="btn btn-nw-buscas btn-xs">Cancelar</button>

                            <button type="button" id="btnAtualizarStatus" class="btn btn-nw-buscas btn-xs">Consulta Status/Imprimir</button>

                            <button type="button" id="btnEnviarEmail" class="btn btn-nw-buscas btn-xs">Enviar E-mail</button>
                        @endif
                    @endcan

                    @isset($nfemitida)
                        @if($nfemitida->nfmodelo == 55 && ! $nfemitida->protocoloretornocancelamento)
                            @can('especial', App\Nfemitida::class)
                                <button type="button" id="btnCartaCorrecao" class="btn btn-nw-buscas btn-xs">Carta Correção</button>
                            @endcan
                        @endif
                    @endisset
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('nfsituacao_descricao', 'Situação NF:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-9">
                    {{ Form::text('nfsituacao_descricao', isset($nfemitida) ? $nfemitida->nfsituacao_id . ' - ' . $nfemitida->descricaosituacao : null, ['class'=>'form-control input-sm']) }}
                </div>
            </div>
            {{ Form::hidden('nfsituacao_id', isset($nfemitida) ? $nfemitida->nfsituacao_id : null, ['id' => 'nfsituacao_id']) }}
            <div class="form-group crud_space">
                {{ Form::label('statusevento', 'Status Evento:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-9">
                    <!--{{$statusevento = null}}-->
                    <!--{{$motivoevento = null}}-->
                    @isset($nfemitida)
                        @if($nfemitida->cancelamentomotivo)
                            <!--{{$statusevento = "135" . ": NF-e Cancelada"}}-->
                            <!--{{$motivoevento = $nfemitida->cancelamentomotivo}}-->
                        @elseif(count($nfemitida->nfEmitidaCartaCorrecao))
                            <!--{{$statusevento = "135" . ": Carta de Correção"}}-->
                            <!--{{$motivoevento = $nfemitida->nfEmitidaCartaCorrecao->last()->xcorrecao}}-->
                        @endif
                    @endisset
                    {{ Form::text('statusevento',$statusevento,['class'=>'form-control input-sm']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('motivoevendo', 'Mot. Evento:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-9">
                    {{ Form::text('motivoevendo', $motivoevento,['class'=>'form-control input-sm']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('xml', 'XML NF:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-9">
                    <div class="input-group">
                        {{ Form::textarea('xml',null,['rows'=>'3', 'class'=>'form-control input-sm', 'id'=>'xml']) }}
                        <span data-clipboard-target="#xml" class="input-group-addon xmlCopyClipboard copyClipboard" 
                              title="Copiar XML" data-trigger="hover" data-placement="right" data-toggle="tooltip"
                              id="spanCopy" style="font-size: 13px; padding-top: 0px; padding-bottom: 0px;">
                            <i class="glyphicon glyphicon-copy"></i>
                        </span>
                    </div>
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('chaveacesso', 'Chave de acesso:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-4">
                    <div class="input-group">
                        {{ Form::text('chaveacesso',null,['class'=>'form-control input-sm']) }}
                        <span data-clipboard-target="#chaveacesso" class="input-group-addon chaveacessoCopyClipboard copyClipboard" 
                              title="Copiar Chave" data-trigger="hover" data-placement="right" data-toggle="tooltip"
                              id="spanCopy" style="font-size: 13px; padding-top: 0px; padding-bottom: 0px;">
                            <i class="glyphicon glyphicon-copy"></i>
                        </span>
                    </div>
                </div>
                {{ Form::label('chaveacessoref', 'Chave de Ref.:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-4">
                    <div class="input-group">
                        {{ Form::text('chaveacessoref',null,['class'=>'form-control input-sm number', 'maxlength' => 44]) }}
                        <span data-clipboard-target="#chaveacessoref" class="input-group-addon chaveacessorefCopyClipboard copyClipboard" 
                              title="Copiar Chave Ref" data-trigger="hover" data-placement="right" data-toggle="tooltip"
                              id="spanCopy" style="font-size: 13px; padding-top: 0px; padding-bottom: 0px;">
                            <i class="glyphicon glyphicon-copy"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>