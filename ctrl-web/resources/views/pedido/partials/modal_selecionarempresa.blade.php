<div class="modal fade" id="modalSelecionarEmpresa" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabelCadastro">Selecionar Empresa</h4>
            </div>
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12">
                        {{ Form::label('empresa_id_modal', 'Empresa:', ['class'=>'col-sm-2 control-label input-sm', 'style' => 'text-align: right']) }}
                        <div class="col-sm-9">
                            {{ Form::select('empresa_id_modal',$empresas,null,['class'=>'form-control input-sm selectChosen', 'id' => 'empresa_id_modal']) }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseModalEmpresa" class="btn btn-nw-geral" data-dismiss="modal">Cancelar</button>
                {{ Form::button('Ok', ['class' => 'btn btn-nw-registro', 'id' => 'btnSelecionaEmpresa']) }}
                <div id="saveError" class="alert alert-danger alert-dismissable" style="display:none;">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fa fa-ban"></i>Erro</h5>
                    <div id="save_result"></div>
                </div>
            </div>
        </div>
    </div>
</div>
