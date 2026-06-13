<div class="modal fade" id="modalEditaVariosPedidos" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabelCadastro">Editar Pedido</h4>
            </div>
            {{Form::open(['id' => 'fmModalEditaVariosPedidos'])}}
            <div class="modal-body">
                <div class="box-body">
                    <div class="col-sm-12">
                        <div class="form-group crud_space col-sm-12">
                            {{ Form::label('pedidosituacao_id', 'Status:', ['class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-sm-10">
                            {{ Form::select('pedidosituacao_id',$status,@$status_id,['class'=>'form-control input-sm selectChosen', 'id' => 'modalvariospedidosituacao_id']) }}
                            {{Form::hidden('arraypedidos_id', null, ['id' => 'arraypedidos_id'] )}}
                            {{Form::hidden('motivoatraso_id', null, ['id' => 'modalvariospedidomotivoatraso_id'] )}}
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
