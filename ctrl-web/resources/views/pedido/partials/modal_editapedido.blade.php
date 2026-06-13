<div class="modal fade" id="modalEditaPedido" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="titleModalEditOne"></h4>
            </div>
            {{Form::open(['id' => 'fmModalEditaPedido'])}}
            <div class="modal-body">
                <div class="box-body">
                    <div class="col-sm-12">
                        <div class="form-group crud_space col-sm-12">
                            {{ Form::hidden('modalpedido_id',null,['class'=>'form-control input-sm','id' => 'modalpedido_id']) }}
                            {{ Form::hidden('pedidomotivoatraso_id',null,['class'=>'form-control input-sm','id' => 'pedidomotivoatraso_id']) }}
                            {{ Form::label('modalsetor_id', 'Setor:', ['class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-sm-10">
                                {{ Form::select('modalsetor_id',[],null,['class'=>'form-control input-sm selectChosen', 'id' => 'modalsetor_id']) }}
                            </div>
                        </div>
                        <div class="form-group crud_space col-sm-12">
                            {{ Form::label('modalcolaborador_id', 'Colaborador:', ['class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-sm-10">
                                {{ Form::select('modalcolaborador_id',[],null,['class'=>'form-control input-sm selectChosen', 'id' => 'modalcolaborador_id']) }}
                            </div>
                        </div>
                        <div class="form-group crud_space col-sm-12">
                            {{ Form::label('modalpedidosituacao_id', 'Status:', ['class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-sm-10">
                                {{ Form::select('modalpedidosituacao_id',$status,@$status_id,['class'=>'form-control input-sm selectChosen', 'id' => 'modalpedidosituacao_id']) }}
                            </div>
                        </div>
                        <div class="form-group crud_space col-sm-12">
                        {{ Form::label('modalcondicaopagamento_id', 'Pagamento:', ['class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-sm-10">
                                {{ Form::select('modalcondicaopagamento_id',$condicaoPagamento,null,['class'=>'form-control input-sm selectChosen', 'id' => 'modalcondicaopagamento_id']) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-nw-geral" data-dismiss="modal">Cancelar</button>
                {{ Form::submit('Salvar', ['class' => 'btn btn-nw-registro']) }}
                <div id="saveError" class="alert alert-danger alert-dismissable" style="display:none;">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fa fa-ban"></i>Erro</h5>
                    <div id="save_result"></div>
                </div>
            </div>
            {{Form::close()}}
        </div>
    </div>
</div>
