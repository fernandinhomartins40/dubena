<!--<div class="modal fade dontHideEsc" id="modalSetorColaborador" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabelCadastro">Editar Setor e Colaborador</h4>
            </div>
            {{Form::open(['id' => 'fmModalMonitoramentoPedido'])}}
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12">
                        {{ Form::hidden('modal_pedido_id',null,['class'=>'form-control input-sm','id' => 'modal_pedido_id']) }}
                        {{ Form::label('modal_setor_id', 'Setor:', ['class'=>'col-sm-2 control-label input-sm']) }}
                        <div class="col-sm-10">
                            {{ Form::select('modal_setor_id',$setores,@$setor_id,['class'=>'form-control input-sm selectChosen', 'id' => 'modal_setor_id']) }}
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {{ Form::label('modal_colaborador_id', 'Colaborador:', ['class'=>'col-sm-2 control-label input-sm']) }}
                        <div class="col-sm-10">
                            {{ Form::select('modal_colaborador_id',[],@$colaborador_id,['class'=>'form-control input-sm selectChosen', 'id' => 'modal_colaborador_id']) }}
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
</div>-->
