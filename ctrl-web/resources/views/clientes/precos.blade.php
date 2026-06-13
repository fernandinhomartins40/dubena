
<div id="tabCadastro6" class="col-sm-12">
    <div class="box-body">
        <div class="form-group crud_space">
            {{ Form::label('condicaopagamento_id', 'Tipo de Pagamento:', ['class'=>'col-sm-2 control-label input-sm']) }}
            <div class="col-sm-3">
            @if(isset($condicaoPagamentoCliente))
                {{ Form::select('condicaopagamento_id',$condicaoPagamentoCliente,null,['class'=>'form-control selectChosen']) }}
                @else
                {{ Form::select('condicaopagamento_id',$condicaoPagamento,null,['class'=>'form-control selectChosen']) }}
                @endif
            </div>
            @can('canSee', App\Role::class)
                <button id="btnAddCondicaoPagamento" disabled class="btn btn-nw-buscas btn-xs" type="button">Adicionar</button>
            @endcan
            @cannot('canSee', App\Role::class)
                <button disabled class="btn btn-nw-buscas btn-xs" type="button">Adicionar</button>
            @endcannot
        </div>
        <div class="form-group crud_space">
            <div class="col-sm-8 col-sm-push-2">
                {{ Form::hidden('condicoespagamento',null,['id' => 'condicoespagamento']) }}
                <table id="tblCondPgto" class="table table-bordered table-hover table-condensed">
                    <thead>
                        <tr>
                            <th style='width: 15%;'>Cód.</th>
                            <th>Descrição</th>
                            <th style='width: 15%;'>Operação</th>
                        </tr>
                    </thead>
                    <tbody id="clientes-list" name="clientes-list">
                        @if(isset($cliente->condicaoPagamento))
                        @foreach ($cliente->condicaoPagamento as $condicaoPgto)
                        <tr id="">
                            <td>{{ $condicaoPgto->id }}</td>
                            <td>{{ $condicaoPgto->descricao }}</td>
                            <td>
                                @can('canSee', App\Role::class)
                                    <button id="btnRemoverCondicaoPagamento" type="button" class="btn btn-nw-registro btn-xs">Remover</button>
                                @endcan
                                @cannot('canSee', App\Role::class)
                                    <button disabled type="button" class="btn btn-nw-registro btn-xs">Remover</button>
                                @endcannot
                            </td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        <div class="form-group crud_space" >
            {{ Form::label('cliProdutoPreco', 'Produto:', ['class'=>'col-sm-2 control-label input-sm']) }}
            <div class="col-sm-3">
                {{ Form::select('selectProdutosPrecos',$produtos,null,['class'=>'form-control selectChosen', 'id' => 'selectProdutosPrecos', 'style'=>'padding:0px;max-height:24px;border-radius: 5px ! important;']) }}
            </div>
            {{ Form::label('cliProdutoValor', 'Preço:', ['class'=>'col-sm-1 control-label input-sm']) }}
            <div class="col-sm-1">
                {{ Form::text('produtoValor',null,['class'=>'form-control dinheiroNoZero  input-sm', 'id' => 'produtoValor']) }}
            </div>
            {{ Form::label('cliDescontoPara', 'Desconto Para:', ['class'=>'col-sm-2 control-label input-sm']) }}
            <div class="col-sm-2">
                {{ Form::select('selectDescontoPara',$descpara,null,['class'=>'form-control selectChosen', 'id' => 'selectDescontoPara']) }}
            </div>
        </div>
        <div class="form-group crud_space">
            {{ Form::label('tipo', 'Tipo:', ['class'=>'col-sm-2 control-label input-sm']) }}
            <div class="col-sm-2 radio">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                {{ Form::radio('tipo', '1' , true, ['onclick'=>'changeClassValue()']) }} <label> Valor </label>
                <br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                {{ Form::radio('tipo', '2' , false, ['onclick'=>'changeClassValue()']) }} <label> Percentual </label>
            </div>
            {{ Form::label('valordesc', 'Desconto:', ['class'=>'col-sm-2 control-label input-sm']) }}
            <div class="col-sm-1">
                {{ Form::text('valor0', null, ['id'=>'valor0', 'class' => 'form-control input-sm dinheiroNoZero']) }}
                {{ Form::text('valor1', null, ['id'=>'valor1', 'class' => 'form-control input-sm percentagem hidden']) }}
                {{ Form::hidden('valor_banco', @$atualizacao->valor, ['id'=>'valor_banco']) }}
            </div>
            @can('canSee', App\Role::class)
                <div class="col-sm-2">
                    <button id="btnAddPreco" type="button" class="btn btn-nw-buscas btn-xs">Adicionar</button>
                </div>
            @endcan
            @cannot('canSee', App\Role::class)
                <div class="col-sm-1">
                    <button disabled type="button" class="btn btn-nw-buscas btn-xs">Adicionar</button>
                </div>
            @endcannot
        </div>
        <div class="col-sm-8 col-sm-push-2">
            {{ Form::hidden('produtos',null,['id' => 'produtos']) }}
            <table id="tblProdutosPrecos" class="table table-bordered table-hover table-condensed">
                <thead>
                    <tr>
                        <th>codigo</th>
                        <th style='width: 10%;'>Cód.</th>
                        <th style='width: 40%;'>Produto</th>
                        <th style='width: 20%;'>Preço</th>
                        <th style='width: 15%;'>Desconto</th>
                        <th>tipo</th>
                        <th>descpara</th>
                        <th style="width: 15%">Para</th>
                        <th style='width: 15%;'>Operação</th>
                    </tr>
                </thead>
                <tbody id="clientes-list" name="clientes-list">
                    @if(isset($cliente->clienteProduto))
                    @foreach ($cliente->clienteProduto as $cliProduto)
                    <tr id="">
                        <td>{{$cliProduto->id}}</td>
                        <td>{{ $cliProduto->produto->id }}</td>
                        <td>{{ $cliProduto->produto->descricao }}</td>
                        <td>{{ is_null($cliProduto->preco) ? " " : requestNumeroDecimalOracle($cliProduto->preco) }}</td>
                        @if (is_null($cliProduto->tipo))
                            <td></td>
                        @else
                            <td>{{ $cliProduto->tipo == 1 ? requestNumeroDecimalOracle($cliProduto->desconto) : requestPercentualOracle($cliProduto->desconto * 100) }}</td>
                        @endif
                        <td>{{ $cliProduto->tipo }}</td>
                        <td>{{ $cliProduto->descontopara }}</td>
                        <td>{{ $descpara[$cliProduto->descontopara] }}</td>
                        <td>
                            @can('canSee', App\Role::class)
                                <button id="btnRemoverProduto" type="button" class="btn btn-nw-registro btn-xs">Remover</button>
                            @endcan
                            @cannot('canSee', App\Role::class)
                                <button disabled type="button" class="btn btn-nw-registro btn-xs">Remover</button>
                            @endcannot
                        </td>
                    </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

