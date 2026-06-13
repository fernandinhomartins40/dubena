<div class="modal fade" id="modalValidaGasBolsoMonit" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="max-width: 80%; margin-left: 15% ">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabelCadastro">Digite o Código do Vale Gás</h4>
            </div>
            {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmModalValidaGasBolsoMonit']) }}
            <div class="modal-body">
                <div class="box-body">
                    {{Form::hidden('valegaspedido_id', null, ['id' => 'valegaspedido_id'])}}
                    <div class="form-group crud_space">
                        <div id="divPedido_id" class="col-sm-10 col-sm-offset-1 fontSize_15" style="display: none">

                        </div>
                    </div>
                    <br />
                    <div class="form-group crud_space col-sm-12">
                        {{ Form::label('cod_gasbolso', 'Cód.:', ['class'=>'col-sm-1 control-label input-sm ']) }}
                        <div class="col-sm-3">
                            {{Form::text('cod_gasbolso', null, ['class' => 'number input-sm form-control', 'id' => 'cod_gasbolso'])}}
                        </div>
                        {{ Form::label('valegasproduto_id', 'Produto:', ['class'=>'col-sm-2 control-label input-sm ']) }}
                        <div class="col-sm-5">
                            {{Form::select('valegasproduto_id',[], null, ['class' => 'input-sm form-control selectChosen', 'id' => 'valegasproduto_id'])}}
                        </div>
                        <button type="button" id="btnAddValeGas" class="btn btn-primary btn-xs"><i class="glyphicon glyphicon-plus" style=" color: white "></i></button>
                    </div>
                    <div class="col-sm-12">
                        <table id="tblCodValeGasMonit" class="table table-bordered table-striped table-hover table-condensed">
                            <thead>
                                <tr>
                                    <th>Cód Vale Gás</th>
                                    <th>Preço Vale Gás</th>
                                    <th>Cód Produto</th>
                                    <th>Produto</th>
                                    <th>Operações</th>
                                </tr>
                            </thead>
                            <tbody>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseModalValidaGasBolso" class="btn btn-nw-geral" data-dismiss="modal">Cancelar</button>
                {{ Form::submit('Salvar', ['class' => 'btn btn-nw-registro', 'id' => 'btnValidaGasBolso']) }}
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
