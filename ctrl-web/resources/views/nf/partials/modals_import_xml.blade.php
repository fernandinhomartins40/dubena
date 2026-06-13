<div class="modal fade dontHideEsc" id="modalImportProductsXml" tabindex="-1" role="dialog" aria-labelledby="myModalLabelModalFinalidade2" aria-hidden="true">
    <div class="modal-dialog" style="width: 90%">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close btnCloseImportProductsXml"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="titleImportXml">Vincular Produtos</h4>
            </div>
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-horizontal">
                        <div class="col-sm-12">
                            <div class="alert alert-informacao" role="alert" id="notify-user" style="display: none;">
                                Todos os produtos foram vinculados, clique em continuar
                            </div>
                            <div class="form-group crud_space">
                                {{ Form::label("nfoperacao_import", "Operação: ", ['class'=>'col-sm-2 control-label input-sm']) }}
                                <div class="col-sm-4">
                                    {{ Form::select("nfoperacao_import", [], null, ["class" => "selectChosen", "id" => "nfoperacao_import"]) }}
                                </div>
                                {{ Form::label("setor_import", "Setor: ", ['class'=>'col-sm-1 control-label input-sm']) }}
                                <div class="col-sm-4">
                                    {{ Form::select("setor_import", [], null, ["class" => "selectChosen", "id" => "setor_import"]) }}
                                </div>
                            </div>
                            <div class="form-group crud_space">
                                {{ Form::label("produtos_import", "Produto Correspondente: ", ['class'=>'col-sm-2 control-label input-sm']) }}
                                <div class="col-sm-4">
                                    {{ Form::select("produtos_import", [], null, ["class" => "selectChosen", "id" => "produtos_import"]) }}
                                </div>
                                {{ Form::label("produtosxml_import", "Produto XML: ", ['class'=>'col-sm-1 control-label input-sm']) }}
                                <div class="col-sm-4">
                                    {{ Form::select("produtosxml_import", [], null, ["class" => "selectChosen", "id" => "produtosxml_import"]) }}
                                </div>
                            </div>
                            <div class="form-group crud_space">
                                {{ Form::label('produto_valor_import', 'Valor:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                <div class="col-sm-2">
                                    {{ Form::text('produto_valor_import',null,['class'=>'form-control input-sm dinheiro', "disabled"]) }}
                                </div>
                                {{ Form::label('produto_quantidade_import', 'Quantidade:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                <div class="col-sm-1">
                                    {{ Form::text('produto_quantidade_import',null,['class'=>'form-control input-sm mask4Decimal', "disabled"]) }}
                                </div>
                                {{ Form::label('produto_total_trib', 'Total Tributos:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                <div class="col-sm-2">
                                    {{ Form::text('produto_total_trib',null,['class'=>'form-control input-sm mask4Decimal', "disabled"]) }}
                                </div>
                                <div class="col-sm-1">
                                    <button type="button" id="addProdutosXML" class='btn btn-nw-buscas btn-xs'>
                                        <span class="fa fa-cart-plus fa-1" style="font-size: 18px"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group crud_space">
                                <div class="col-md-10 col-md-offset-1">
                                    <table id="tblProdutosXML" class="table table-bordered table-hover table-condensed">
                                        <thead>
                                        <tr>
                                            <th>Cód. Produto</th><!--0-->
                                            <th>nItem</th><!--1-->
                                            <th>Produto</th><!--2-->
                                            <th>Valor Unitário</th><!--3-->
                                            <th>Quantidade</th><!--4-->
                                            <th>Operação Fiscal</th><!--5-->
                                            <th>Setor</th><!--6-->
                                            <th>Operação</th><!--7-->
                                        </tr>
                                        </thead>
                                        <tbody id="tbodyProdutosList">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseImportProductsXml" class="btn btn-nw-geral btnCloseImportProductsXml">Cancelar</button>
                <button type="submit" class="btn btn-nw-registro" id="btnContinueImportProductsXml">Continuar</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade dontHideEsc" id="modalFinanceiroXml" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" style="width: 70%">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close btnCloseImportProductsXml"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title">Financeiro</h4>
            </div>
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-horizontal">
                        <div class="col-sm-12">
                            <div class="form-group crud_space">
                                {{ Form::label("condicaopagamento_import", "Condição de Pgto:", ['class'=>'col-sm-2 control-label input-sm']) }}
                                <div class="col-sm-6">
                                    {{ Form::select("condicaopagamento_import", [], null, ["class" => "selectChosen", "id" => "condicaopagamento_import"]) }}
                                </div>
                            </div>
                            <div class="form-group crud_space margTop_15">
                                <div class="col-md-10 col-md-offset-2">
                                    <p>Dados da Fatura:</p>
                                </div>
                            </div>
                            <div class="form-group crud_space">
                                {{ Form::label("vorig_import", "Valor Original: ", ['class'=>'col-sm-2 control-label input-sm']) }}
                                <div class="col-sm-2">
                                    {{ Form::text("vorig_import", null, ["class" => "input-sm form-control input-sm dinheiro", "disabled", "id" => "vorig_import"]) }}
                                </div>
                                {{ Form::label("vdesc_import", "Desconto: ", ['class'=>'col-sm-2 control-label input-sm']) }}
                                <div class="col-sm-2">
                                    {{ Form::text("vdesc_import", null, ["class" => "input-sm form-control input-sm dinheiro", "disabled", "id" => "vdesc_import"]) }}
                                </div>
                                {{ Form::label("vliq_import", "Valor Líquido: ", ['class'=>'col-sm-2 control-label input-sm']) }}
                                <div class="col-sm-2">
                                    {{ Form::text("vliq_import", null, ["class" => "input-sm form-control input-sm dinheiro", "disabled", "id" => "vliq_import"]) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseFinanceiroXml" class="btn btn-nw-geral btnCloseImportProductsXml">Cancelar</button>
                <button type="submit" class="btn btn-nw-registro" id="btnSaveImportXml">Finalizar</button>
            </div>
        </div>
    </div>
</div>