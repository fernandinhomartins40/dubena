<div class="modal fade" id="modalMotivoAtrasoPedido" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabelCadastro">Motivo de Atraso</h4>
            </div>
            {{Form::open(['id' => 'fmModalMotivoAtrasoPedido'])}}
            <div id="divCadastro" class="row">
                <br />
                <div class="col-md-12">
                    <div class="form-group crud_space col-sm-12">
                        @if(!isset($pedidoController) && !isset($creating))
                            <div align="center" class="fontSize_14" id="divAvisoEditaVariosPedidos">
                                <i><strong> Aviso: </strong>se estiver editando vários pedidos, o motivo de atraso valerá para todos!</i>
                                <br />
                                <br />
                            </div>
                        @endif
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {{ Form::label('modalpedidomotivoatraso_id', 'Motivo atraso:', ['class'=>'col-sm-2 control-label input-sm']) }}
                        <div class="col-sm-10">
                            @if(isset($motivosatrasos))
                                {{ Form::select('modalpedidomotivoatraso_id',$motivosatrasos,null,['class'=>'form-control input-sm selectChosen', 'id' => 'modalpedidomotivoatraso_id']) }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseModalMotivoAtrasoPedido" class="btn btn-nw-geral" data-dismiss="modal">Cancelar</button>
                {{ Form::submit('Salvar', ['class' => 'btn btn-nw-registro', 'id' => 'btnSubmitModalMotivoAtrasoPedido']) }}
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
