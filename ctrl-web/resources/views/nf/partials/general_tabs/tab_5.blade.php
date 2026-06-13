<div class="row">
    <div id="tabCadastro" class="col-md-12">
        <div class="box-body">
            <div class="form-group crud_space">
                {{ Form::label('nfoperacao_id_2', 'Operação:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-5">
                    @if($tiponf === "emitida")
                        {{ Form::select('nfoperacao_id_2', $nfoperacaos->pluck('descricao', 'id'), null,['class'=>'form-control input-sm selectChosen']) }}
                    @else
                        {{ Form::select('nfoperacao_id_2', [], null,['class'=>'form-control input-sm selectChosen']) }}
                    @endif
                </div>
                {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-4">
                    {{ Form::select('setor_id', $setores, null, ['class'=>'form-control input-sm selectChosen']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('produto_id', 'Produto:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-5">
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
            </div>
        @if($tiponf == 'emitida')
            <!--{{$hidden = ''}}-->
        @else
            <!--{{$hidden = 'hidden'}}-->
        @endif
            <div class="form-group crud_space {{$hidden}}">
                {{ Form::label('qVol', 'qVol:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('qVol',null,['class'=>'form-control input-sm maskNumber']) }}
                </div>
                {{ Form::label('pesoL', 'pesoL:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::hidden('pesoLhidden', null, ['id' => 'pesoLhidden']) }}
                    {{ Form::text('pesoL',null,['class'=>'form-control input-sm mask3Decimal']) }}
                </div>
                {{ Form::label('pesoB', 'pesoB:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::hidden('pesoBhidden', null, ['id' => 'pesoBhidden']) }}
                    {{ Form::text('pesoB',null,['class'=>'form-control input-sm mask3Decimal']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('pGNi', '% GNi:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('pGNi',null,['class'=>'form-control input-sm pGLP']) }}
                </div>
                {{ Form::label('pGNn', '% GNn:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('pGNn',null,['class'=>'form-control input-sm pGLP']) }}
                </div>
                {{ Form::label('pGLP', '% GLP:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('pGLP',null,['class'=>'form-control input-sm pGLP']) }}
                </div>
                <div class="col-sm-offset-1 col-sm-1">
                    <button type="button" id="addProdutos" class='btn btn-nw-buscas btn-xs'>
                        <span class="fa fa-cart-plus fa-1" style="font-size: 18px"></span>
                    </button>
                </div>
            </div>
            <div class="col-md-10 col-md-offset-1">
                {{Form::hidden('produtos',"", ['id'=>'produtos'])}}
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
                        <th>Peso Líquido</th><!--9-->
                        <th>Peso Bruto</th><!--10-->
                        <th>ncm</th><!--11-->
                        <th>unidademedidasigla</th><!--12-->
                        <th>ean</th><!--13-->
                        <th>nfeextipi</th><!--14-->
                        <th>nfcest</th><!--15-->
                        <th>nfedescricaofiscal</th><!--16-->
                        <th>nfgrupofiscal_id</th><!--17-->
                        <th>qVol</th><!--18-->
                        <th>pesoL</th><!--19-->
                        <th>pesoB</th><!--20-->
                        <th>pGNi</th><!--21-->
                        <th>pGNn</th><!--22-->
                        <th>pGLP</th><!--23-->
                    </tr>
                    </thead>
                    <tbody id="tbodyProdutosList" name="tbodyProdutosList">
                    <!--{{$strTypeItens = 'nf' . $tiponf . 'items'}}-->
                    @isset(${$strTypeItens})
                    @foreach (${$strTypeItens} as $item)
                        <tr id="item{{$item->id}}">
                            <td>{{$item->nfoperacao_id}}</td><!--0-->
                            <td>{{$item->nfoperacao_descricao}}</td><!--1-->
                            <td>{{$item->setor_id}}</td><!--2-->
                            <td>{{$item->setor_descricao}}</td><!--3-->
                            <td>{{$item->cprod}}</td><!--4-->
                            <td>{{$item->produto_descricao}}</td><!--5-->
                            <td>{{requestNumeroDecimalOracle($item->vuncom)}}</td><!--6-->
                            <td>{{requestNumeroDecimal4DigitosOracle($item->qcom)}}</td><!--7-->
                            <td><button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverProduto'>Remover</button></td><!--8-->
                            <td>{{$item->pesoliquido}}</td><!--9-->
                            <td>{{$item->pesobruto}}</td><!--10-->
                            <td>{{$item->ncm}}</td><!--11-->
                            <td>{{$item->sigla}}</td><!--12-->
                            <td>{{$item->ean}}</td><!--13-->
                            <td>{{$item->nfeextipi}}</td><!--14-->
                            <td>{{$item->nfcest}}</td><!--15-->
                            <td>{{$item->nfedescricaofiscal}}</td><!--16-->
                            <td>{{$item->nfgrupofiscal_id}}</td><!--17-->
                            <td>{{$item->qvol}}</td><!--18-->
                            <td>{{$item->pesol}}</td><!--19-->
                            <td>{{$item->pesob}}</td><!--20-->
                            <td>{{requestNumeroDecimal4DigitosOracle($item->pgni)}}</td><!--21-->
                            <td>{{requestNumeroDecimal4DigitosOracle($item->pgnn)}}</td><!--22-->
                            <td>{{requestNumeroDecimal4DigitosOracle($item->pglp)}}</td><!--23-->
                        </tr>
                    @endforeach
                    @endisset
                    </tbody>
                </table>
            </div><!-- /.box -->
        </div>
    </div>
</div>