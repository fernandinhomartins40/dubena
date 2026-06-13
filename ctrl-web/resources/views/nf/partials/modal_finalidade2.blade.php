<div class="modal fade" id="modalFinalidade2" tabindex="-1" role="dialog" aria-labelledby="myModalLabelModalFinalidade2" aria-hidden="true">
    <div class="modal-dialog" style="width: 60%">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close btnCloseModalFinalidade2" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabelModalFinalidade2">NFe Complementar</h4>
            </div>
            {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmModalFinalidade2Ajax', 'route' => 'nfemitida.store']) }}
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('numnfe_complementar', 'Número NFe:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-4">
                            {!! Form::text('numnfe_complementar',null,['class'=>'form-control input-sm', 'id'=>'numnfe_complementar']) !!}
                        </div>
                        {!! Form::label('nfserie_complementar', 'Número Série:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-2">
                            {!! Form::text('nfserie_complementar',null,['class'=>'form-control input-sm', 'id'=>'nfserie_complementar']) !!}
                        </div>
                        <button type="button" class="btn btn-nw-registro btn-xs" id="btnGetNfFinalidade2">Carregar NFe</button>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('chavenfe_complementar', 'Chave de Acesso:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-8">
                            {!! Form::text('chavenfe_complementar',null,['class'=>'form-control input-sm', 'id'=>'chavenfe_complementar', 'readonly']) !!}
                        </div>
                    </div>
                    {{ Form::hidden('nfcomplementar', 0, ['id' => 'nfcomplementar'])}}
                    {{ Form::hidden('produtos_complementar', null, ['id' => 'produtos_complementar'])}}
                    <div class="form-group crud_space">
                        <div class="col-sm-12">
                            <table class="table table-condensed table-bordered table-hover" id="tblNfFinalidade2">
                                <thead>
                                    <tr>
                                        <th>Cód. Item</th>
                                        <th>Produto</th>
                                        <th>Valor Unitário</th>
                                        <th>Quantidade</th>
                                        <th>Operações</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseModalFinalidade2" class="btn btn-nw-geral btnCloseModalFinalidade2" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-nw-registro" id="btnProcessModalFinalidade2">Concluir</button>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>

<div class="modal fade" id="modalUpdateProdComp" tabindex="-1" role="dialog" aria-labelledby="myModalLabelModalFinalidade2" aria-hidden="true">
    <div class="modal-dialog" style="width: 50%">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabelModalFinalidade2"><div id="divEditProdComp"></div></h4>
            </div>
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12 form-horizontal">
                        {!! Form::label('valor_complementar', 'Valor:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-4">
                            {!! Form::text('valor_complementar',null,['class'=>'form-control dinheiro input-sm', 'id'=>'valor_complementar']) !!}
                        </div>
                        {!! Form::label('quantidade_complementar', 'Quantidade:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-4">
                            {{ Form::text('quantidade_complementar',null,['class'=>'form-control input-sm', 'id'=>'quantidade_complementar']) }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-nw-geral" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-nw-registro" id="btnSalvaProdComp">Salvar</button>
            </div>
        </div>
    </div>
</div>