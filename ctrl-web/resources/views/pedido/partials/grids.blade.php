
<div class="panel panel-default" style="margin-top: -10px">
    <div class="panel-heading" style="height:38px;" id="divHeaderAddProduto">
        <h3 class="panel-title" id='page-title'>
            <div class="form-group crud_space" style="margin-top: -10px;">
                <div class="col-sm-7">
                    {!! Form::label('produto_id', 'Produto:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                    <div class="col-sm-4">
                        @if(isset($produtos))
                        {!! Form::select('produto_id', $produtos, null, ['class' => 'form-control selectChosenClear', 'id' => 'produto_id']) !!}
                        @else
                        {!! Form::select('produto_id', [], null, ['class' => 'form-control selectChosenClear', 'id' => 'produto_id']) !!}
                        @endif
                        {!! Form::hidden('precosProdutosPadrao', null, ['id' => 'precosProdutosPadrao']) !!}
                        {!! Form::hidden('precoprodutos', null, ['id' => 'precoprodutos']) !!}
                        {!! Form::hidden('produtosconvenio', null, ['id' => 'produtosconvenio']) !!}
                    </div>
                    {!! Form::label('preco', 'Preço:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                    <div class="col-sm-2">
                        {!! Form::text('preco',null,['class'=>'form-control input-sm dinheiroPrefixNone', 'id' => 'preco']) !!}
                    </div>
                    {!! Form::label('quantidade', 'Qtdade:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                    <div class="col-sm-2">
                        {!! Form::text('quantidade',null,['class'=>'form-control input-sm number', 'id' => 'quantidade', 'data-toggle' => "tooltip", 'data-trigger'=>"hover", 'data-placement'=>"bottom"]) !!}
                    </div>
                    <div style="padding-top: 2px !important">
                        <button type="button" id="btnAddProduto" style="padding-top: 3.3px !important" class="btn btn-nw-buscas btn-xs"><span class="fa fa-cart-plus fa-1" style="font-size: 18px"></span></button>
                    </div>
                </div>
                {!! Form::hidden('produtospedido', null, ['id' => 'produtospedido']) !!}
            </div>
        </h3>
    </div>
    <div class="form-group crud_space" >
        <div class="col-md-7">
            <div class="col-md-12" style='background-color: white; margin-left:1.5%'>
                <table id="tblProdutosPedido" class="table table-bordered table-striped table-hover table-condensed">
                    <thead class="tblPedidos">
                        <tr>
                            <th style="width: 10%">Cod</th>
                            <th style="width: 30%">Produto</th>
                            <th style="width: 30%">Preço</th>
                            <th style="width: 20%">Qtdade</th>
                            <th style="width: 10%">Operação</th>
                        </tr>
                    </thead>
                    <tbody id="clientes-list" class="tblPedidos" name="clientes-list">
                        @if(isset($pedidoitens))
                        @foreach($pedidoitens as $produto)
                        <tr>
                            <td>{{$produto->produto_id}}</td>
                            <td>{{$produto->produto->descricao}}</td>
                            <td>{{str_replace("R$ ", '', requestNumeroDecimalOracle($produto->precovendaunitario))}}</td>
                            <td>{{$produto->quantidade}}</td>
                            <td><button class="btn btn-nw-registro btn-xs btn-remocao-produtos" type="button" id="btnRemoverProduto">Remover</button></td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-5">
            <div class="col-md-12" style='background-color: white'>
                <table id="tblHistorico" class="table table-bordered table-striped table-hover table-condensed">
                    <thead class="tblPedidos">
                        <tr>
                            <th>Data</th>
                            <th>Prod.</th>
                            <th>Status</th>
                            <th>Qde</th>
                            <th>Valor</th>
                            <th>Vencimento</th>
                            <th>Forma Pgto</th>
                            <th>#</th>
                        </tr>
                    </thead>
                    <tbody id="historico-list" class="tblPedidos" name="clientes-list">

                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="form-group crud_space">
        {!! Form::label('valordesconto', 'Desconto:', ['class'=>'col-sm-1 control-label input-sm']) !!}
        <div class="col-sm-1">
            {!! Form::text('valordesconto', requestNumeroDecimalOracle(@$pedido->valordesconto),['class' => 'input-sm form-control dinheiro', 'id' => 'valordesconto']) !!}
        </div>
        {!! Form::label('entregataxa', 'Taxa Entrega:', ['class'=>'col-sm-1 control-label input-sm']) !!}
        <div class="col-sm-1">
            {!! Form::text('entregataxa',requestNumeroDecimalOracle(@$pedido->entregataxa),['class' => 'input-sm form-control dinheiro', 'id' => 'entregataxa']) !!}
        </div>
        {!! Form::label('valorvenda', 'Total:', ['class'=>'col-sm-1 control-label input-sm']) !!}
        <div class="col-sm-2">
            {!! Form::text('valortotalpedidodisabled',requestNumeroDecimalOracle(@$pedido->valorvenda),['class' => 'input-sm form-control dinheiro', 'id' => 'valortotalpedidodisabled', 'disabled']) !!}
            {!! Form::hidden('valorvenda',requestNumeroDecimalOracle(@$pedido->valorvenda),['id' => 'valorvenda']) !!}
        </div>
        <div class="col-sm-5">
            <div class="col-sm-3">
                <span class="info-box-icon bg-blue" style="width:15px;height:15px;"></span>
                <span class="info-box-text fontSize_11" style="padding-left: 5px !important"> Pendentes</span>
            </div>
            <div class="col-sm-3">
                <span class="info-box-icon" style="width:15px;height:15px; background-color: red"></span>
                <span class="info-box-text fontSize_11" style="padding-left: 5px !important"> Atrasadas</span>
            </div>
            <div class="col-sm-3">
                <span class="info-box-icon" style="width:15px;height:15px; background-color: green"></span>
                <span class="info-box-text fontSize_11" style="padding-left: 5px !important"> Baixadas</span>
            </div>
            <div class="col-sm-3">
                <span class="info-box-icon" style="width:15px;height:15px;background-color: #808080"></span>
                <span class="info-box-text fontSize_11" style="padding-left: 5px !important"> Canceladas</span>
            </div>
            <div class="col-sm-8 col-sm-push-4 margTop_5">
                <span class="info-box-text fontSize_11">Legenda financeira</span>
            </div>
        </div>
    </div>
</div>
