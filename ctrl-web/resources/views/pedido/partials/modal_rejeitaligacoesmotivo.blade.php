<div class="modal fade" id="modalRejeitaLigacoesMotivo" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabelCadastro">Motivo de Não Venda</h4>
            </div>
            {{Form::open(['id' => 'fmModalRejeitaLigacoesMotivo'])}}
            <div class="modal-body">
                <div class="box-body">

                    <div class="form-group crud_space col-sm-12">
                        {{ Form::label('motivonaovenda_id', 'Motivo:', ['class'=>'col-sm-3 control-label input-sm', 'style' => 'text-align: right']) }}
                        <div class="col-sm-8">
                        {{ Form::select('motivonaovenda_id',$motivonaovendas,null,['class'=>'form-control input-sm selectChosen', 'id' => 'motivonaovenda_id']) }}
                        </div>
                        {{Form::hidden('rejcliente_id', null, ['id' => 'rejcliente_id'])}}
                        {{Form::hidden('telefonerejeitado', null, ['id' => 'telefonerejeitado'])}}
                        {{Form::hidden('empresa_id_telefonerejeitado', null, ['id' => 'empresa_id_telefonerejeitado'])}}
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-nw-geral" data-dismiss="modal">Cancelar</button>
                {{ Form::button('Salvar', ['class' => 'btn btn-nw-registro', 'id' => 'btnSubmitRejeitaLigacoesMotivo']) }}
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
