<!-- Modal -->
<div class="modal fade" id="modalChangeProdConvenio" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <button type="button" class="close btnClosePromocao" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title">Adicionar Produtos ao Convênio</h4>
            </div>
            <div class="modal-body col-sm-12 form-horizontal">
                <div class="form-group crud_space margTop_15">
                    {!! Form::label('prodconvenio', 'Produto:', ['class' => 'control-label col-sm-2 input-sm'])!!}
                    <div class="col-sm-4">
                        {!! Form::select('prodconvenio', $produtos, null, ['class'=>'form-control input-sm selectChosen', 'id' => 'prodconvenio'])!!}
                    </div>
                    {!! Form::label('precoprodconvenio', 'Preço:', ['class' => 'control-label col-sm-2 input-sm'])!!}
                    <div class="col-sm-2">
                        {!! Form::text('precoprodconvenio', null, ['class'=>'form-control input-sm dinheiro', 'id' => 'precoprodconvenio'])!!}
                    </div>
                    <div class="col-md-1">
                        <button id="btnAddProdConvenio" type="button" class="btn btn-xs btn-nw-buscas">
                            Adicionar
                        </button>
                    </div>
                </div>
                <div class="form-group crud_space margTop_15">
                    {{Form::hidden('clienteprodutosconvenios', null, ['id' => 'clienteprodutosconvenios'])}}
                    <div class="col-sm-10 col-sm-offset-1">
                        <table class="table table-bordered table-hover table-condensed" id="tblProdConvenio">
                            <thead>
                            <tr>
                                <th>codigo</th>
                                <th>Cod.</th>
                                <th>Produto</th>
                                <th>Preço</th>
                                <th>Operação</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if(isset($cliente))
                                <!--{{$produtosC = $cliente->produtoconvenio}}-->
                                @foreach($produtosC as $produto)
                                    <tr>
                                        <td>{{$produto->id}}</td>
                                        <td>{{$produto->produto_id}}</td>
                                        <td>{{$produto->produto->descricao}}</td>
                                        <td>{{requestNumeroDecimalOracle($produto->preco)}}</td>
                                        <td><button type="button" class="btn btn-xs btn-nw-registro" id="btnRemoveProdConvenio">Remover</button></td>
                                    </tr>
                                @endforeach
                            @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-nw-geral btnCloseProdConvenio" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>