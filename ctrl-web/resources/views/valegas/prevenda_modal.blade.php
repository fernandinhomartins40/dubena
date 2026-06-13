<!-- Modal -->

<div class="modal fade" id="prevenda_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document" style="width:50%">
        <div class="modal-content">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span
                        class="sr-only">Close</span></button>
                <h4 class="modal-title">Fechar Pré-Venda</h4>
            </div>
            <div class="modal-body col-md-12">
                {{ Form::open(['id' => 'searchbar','class'=>'form-horizontal'])}}
                <div class="col-sm-12">
                    <div class="form-group crud_space">
                        {{ Form::label('cliente_id_md', 'Ponto de Venda:', ['class'=>'col-sm-2 control-label input-sm']) }}
                        <div class="col-sm-6">
                            {{-- {{ Form::select('cliente_id_md', $clientej, null, ['id'=> 'cliente_id_md','class' => 'form-control selectChosen']) }} --}}
                            <select id="nomeclientemd" name="nomeclientemd" class="form-control input-sm"
                                placeholder="Buscar Cliente" data-selectize-value='[]'>
                            </select>
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        {{ Form::label('seqinicio', 'Sequência:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;']) }}
                        <div class="col-sm-2 ">
                            {{ Form::text('seqinicio', null,['id'=>'seqinicio','class'=>'input-sm form-control number']) }}
                        </div>
                        {{ Form::label('seqfim', 'Até:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;']) }}
                        <div class="col-sm-2 ">
                            {{ Form::text('seqfim', null, ['id'=>'seqfim','class' => 'form-control input-sm number']) }}
                        </div>
                    </div>
                </div>
                {{Form::close()}}
                <table id="tableprevenda" style="margin-left:1%"
                    class="table table-bordered table-hover table-condensed">
                    <thead>
                        <tr>
                            <th>id</th>
                            <th>Sequência</th>
                            <th>Ponto de Venda</th>
                            <th>Cód. Vale Gás</th>
                            <th>Produto</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button id="btnfecharprevenda" class="btn btn-nw-registro">Continuar</button>
                <button id="btnvoltarmod" type="button" class="btn btn-nw-geral" data-dismiss="modal">Voltar</button>
            </div>
        </div>
    </div>
</div>
