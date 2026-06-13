<div class="row">
    <div id="tabCadastro" class="col-sm-12">
        <div class="box-body">
            <div class="form-group crud_space">
                {{ Form::label('nfoperacao_id_2', 'Operação:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-4">
                    {{ Form::select('nfoperacao_id_2', $nfoperacaos->pluck('descricao', 'id'), null,['class'=>'form-control input-sm selectChosen']) }}
                </div>
                {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-4">
                    {{ Form::select('setor_id', $setores, null, ['class'=>'form-control input-sm selectChosen']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('produto_id', 'Produto:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-4">
                    {{ Form::select('produto_id', $produtos->pluck("descricao", "id")->prepend('', ''), null,['class'=>'form-control input-sm selectChosen']) }}
                    {{ Form::hidden('precosProdutosPadrao', null, ['id' => 'precosProdutosPadrao']) }}
                    {{ Form::hidden('precoprodutos', null, ['id' => 'precoprodutos']) }}
                </div>
                {{ Form::label('produto_valor', 'Valor:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('produto_valor',null,['class'=>'form-control input-sm dinheiro']) }}
                </div>
                {{ Form::label('produto_quantidade', 'Quantidade:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('produto_quantidade',null,['class'=>'form-control input-sm mask4Decimal']) }}
                </div>
                <div class="col-sm-1">
                    <button type="button" id="addProdutos" class='btn btn-nw-buscas btn-xs'>
                        <span class="fa fa-cart-plus fa-1" style="font-size: 18px"></span>
                    </button>
                </div>
            </div>
            <div class="col-md-10 col-md-offset-1">
                {{Form::hidden('produtosJson', $cupomFiscal->produtosjson, ['id'=>'produtos'])}}
                <table id="tblProdutos" class="table table-bordered table-hover table-condensed">
                    <thead>
                    <tr>
                        <th>Operação ID</th><!--0-->
                        <th>Operação</th><!--1-->
                        <th>Setor ID</th><!--2-->
                        <th>Setor</th><!--3-->
                        <th>Produto ID</th><!--4-->
                        <th>Produto</th><!--5-->
                        <th>Valor Unitário</th><!--6-->
                        <th>Quantidade</th><!--7-->
                        <th>Operação</th><!--8-->
                        <th>ncm</th><!--9-->
                        <th>unidademedidasigla</th><!--10-->
                        <th>ean</th><!--11-->
                        <th>nfeextipi</th><!--12-->
                        <th>nfcest</th><!--13-->
                        <th>nfedescricaofiscal</th><!--14-->
                        <th>nfgrupofiscal_id</th><!--15-->
                    </tr>
                    </thead>
                    <tbody id="tbodyProdutosList">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>