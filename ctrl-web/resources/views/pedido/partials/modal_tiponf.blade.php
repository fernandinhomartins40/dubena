<div class="modal fade dontHideEsc" id="modal-tiponf" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog" style="min-width: 84%">
        <div class="modal-content">
            <div class="modal-header">
                <div id="btn-close-tiponf"></div>
                <h4 class="modal-title" id="myModalLabelCadastro">Dados para NFC-e</h4>
            </div>
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-horizontal">
                        <div class="form-group crud_space">
                            {!! Form::hidden('pedido_id_nf',null,['id' => 'pedido_id_nf']) !!}
                            <div class="divTiponf">
                                {!! Form::label('nftipo', 'Tipo:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                <div class="col-sm-2">
                                    {!! Form::select('nftipo',[1 => 'Identificar Destinatário', 0 => 'Não Identificar Destinatário'],null,['class'=>'selectChosen', 'id' => 'nftipo']) !!}
                                </div>
                            </div>
                            {!! Form::label('fisicajuridica', 'Tipo Pessoa:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                            <div class="col-sm-2">
                                {!! Form::select('fisicajuridica',['F' => 'Física', 'J' => 'Jurídica'], null, ['class'=>'selectChosen', 'id' => 'fisicajuridica']) !!}
                            </div>
                            <div id="divIndicadorIe">
                                {!! Form::label('indicador_ie', 'Indic. I.E:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                <div class="col-sm-2">
                                    {!! Form::select('indicador_ie',[""=>"Selecione","1"=>"Contribuinte ICMS","2"=>"Contribuinte Isento", "9"=>"Não Contribuinte"], null, ['class' => 'form-control selectChosen']) !!}
                                </div>
                            </div>
                            {!! Form::label('nfcpfcnpj', 'Cpf/Cnpj:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                            <div class="col-sm-2">
                                {!! Form::text('nfcpfcnpj',null,['class'=>'form-control input-sm cpf', 'id' => 'nfcpfcnpj']) !!}
                            </div>
                            <div id="div_tpag">
                                {!! Form::label('nfc_tpag', 'Tipo de Pgto:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                <div class="col-sm-2">
                                    {!! Form::select('nfc_tpag', $nfc_tpag, null, ['class'=>'selectChosen', 'id' => 'nfc_tpag']) !!}
                                </div>
                            </div>
                        </div>
                        <div class="divTransportadora">
                            <div class="form-group crud_space">
                                {!! Form::label('transportador_id', 'Transportador:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                <div class="col-sm-8">
                                    {!! Form::select('transportador_id',[],null,['class'=>'selectChosen', 'id' => 'transportador_id']) !!}
                                </div>
                            </div>
                            @include('general.campos_frete_nf')
                            <div class="form-group crud_space">
                                {!! Form::label('fretecondicaopagamento_id', 'Condição de Pagamento:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                <div class="col-sm-5">
                                    {!! Form::select('fretecondicaopagamento_id',[],null,['class'=>'selectChosen', 'id' => 'fretecondicaopagamento_id']) !!}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCancelarNf" class="btn btn-nw-geral">Cancelar</button>
                <button type="button" id="btnGravarNF" class="btn btn-nw-registro">Gerar NFCe</button>
                <div id="saveError" class="alert alert-danger alert-dismissable" style="display:none;">
                    <button type="button" id="" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fa fa-ban"></i>Erro</h5>
                    <div id="save_result"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="{{asset('js/freteNf.js')}}"></script>
<script src="{{asset('js/lib/collection.js')}}"></script>
