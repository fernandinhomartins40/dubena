<!-- Modal -->
<style>
    #tblHistorico { text-overflow:clip; white-space:nowrap; height:15px; }
</style>
<div id="modal_historico" class="modal fade popupModal modal-wide" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document" style="width:70%">
        <div class="modal-content">
            {{ Form::open(['id'=>'gerarOcorrencia','class' => 'form-horizontal', 'files' => true]) }}
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                    <h4 class="modal-title">Histórico do Cliente</h4>
                </div>
                <div class="modal-body col-md-12">
                    <div class="form-group crud_space">
                        {{ Form::label('cliente', 'Cliente:', ['class'=>'col-sm-1 control-label input-sm']) }}
                        <div class="col-sm-3">
                            {{ Form::text('cliente', null, ['id'=>'cliente', 'class' => 'form-control input-sm','readonly']) }}
                        </div>
                        {{ Form::label('endereco', 'Endereço:', ['class'=>'col-sm-1 control-label input-sm']) }}
                        <div class="col-sm-5">
                            {{ Form::text('endereco', null, ['id'=>'endereco', 'class' => 'form-control input-sm','readonly']) }}
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        {{ Form::label('telefones', 'Telefones:', ['class'=>'col-sm-1 control-label input-sm']) }}
                        <div class="col-sm-5">
                            {{ Form::text('telefones', null, ['id'=>'telefones', 'class' => 'form-control input-sm','readonly']) }}
                        </div>
                    </div>
                    <div class="form-group crud_space tabela">
                        <div class="col-md-10 col-md-push-1">
                            <table id="tblHistorico" class="table table-bordered table-striped table-hover table-condensed" >
                                <thead>
                                    <tr>
                                        <th>Cód. Pedido</th>
                                        <th>Data</th>
                                        <th>Produto</th>
                                        <th>Quantidade</th>
                                        <th>Condição de Pagamento</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <div class="modal-footer">
            </div>
        </div>
        {{Form::close()}}
    </div>
</div>
