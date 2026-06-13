<div class="row">
    <div id="tabCadastro" class="col-sm-12">
        <div class="box-body">
            <div class="form-group crud_space">
                <div class="col-sm-6">
                    {{ Form::label('descricaofinanceiro', 'Descrição:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-8">
                        {{ Form::text('descricaofinanceiro',null,['class'=>'form-control input-sm']) }}
                    </div>
                </div>
                <div class="col-sm-6 col-sm-pull-1">
                    {{ Form::label('condicaopagamento_id', 'Condição de Pgto:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-8" id="div_condicaopagamento">
                        {{ Form::select('condicaopagamento_id', $condicaopagamentos, null,['class'=>'form-control input-sm selectChosen'])}}
                        {{ Form::hidden('condicoesPgtoDB', $tiponf !== "E" ?: $condicoesPgtoDB, ['id'=>'condicoesPgtoDB']) }}
                        {{ Form::hidden('vista', null, ['id'=>'vista','class' => 'form-control input-sm']) }}
                        {{ Form::hidden('boleto', null, ['id'=>'boleto','class' => 'form-control input-sm']) }}
                        {{ Form::hidden('cartao', null, ['id'=>'cartao','class' => 'form-control input-sm']) }}
                        {{ Form::hidden('condicaoparcelas', null, ['id'=>'condicaoparcelas','class' => 'form-control input-sm']) }}
                        {{ Form::hidden('condicao', null, ['id'=>'condicao','class' => 'form-control input-sm']) }}
                    </div>
                    @if ($tiponf === "emitida")
                        <div id="div_tpag">
                            {!! Form::label('nfc_tpag', 'Tipo:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                            <div class="col-sm-3">
                                {!! Form::select('nfc_tpag', $nfc_tpag, null, ['class'=>'selectChosen', 'id' => 'nfc_tpag']) !!}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            <div class="form-group crud_space ">
                <div class="col-sm-6">
                    {{ Form::label('vbruto', 'Valor Bruto:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vbruto',null,['class'=>'form-control input-sm dinheiro', 'id' => 'vbruto']) }}
                    </div>
                    {{ Form::label('liquidoParcelas', 'Valor Líq:', ['class'=>'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('liquidoParcelas',null,['class'=>'form-control input-sm dinheiro', 'id' => 'liquidoParcelas']) }}
                    </div>
                </div>
                <div class="col-sm-6 col-sm-pull-1">
                    {{ Form::label('vdesc', 'Desconto:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vdesc',null,['class'=>'form-control input-sm dinheiro']) }}
                    </div>
                    {{ Form::label('', 'Parcelas:', ['class'=>'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-3">
                        <button type="button" id="btnVisualizarParcelas" class="btn btn-nw-buscas btn-xs">Visualizar Parcelas</button>
                        {{ Form::hidden('parcelas_financeiro', @$parcelasfinanceiro, ['id'=>'parcelas_financeiro']) }}
                    </div>
                </div>
            </div>
            <div class="form-group crud_space">
                <div class="col-sm-6">
                    {{ Form::label('centrocusto_id', 'Centro Custo:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-8">
                        {{Form::hidden('centrocusto_id',$centrocusto_id, ['id'=>'centrocusto_id'])}}
                        <div class="input-group">
                            {{ Form::text('centrocusto_descricao',$centrocusto_descricao,['id'=>'centrocusto_descricao', 'class'=>'form-control input-sm', 'readonly']) }}
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcusto">Mudar</button>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-sm-pull-1">
                    {{ Form::label('planoconta_id', 'Plano Conta:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-8">
                        {{Form::hidden('planoconta_id',$planoconta_id, ['id'=>'planoconta_id'])}}
                        <div class="input-group">
                            {{ Form::text('planoconta_descricao',$planoconta_descricao,['id'=>'planoconta_descricao', 'class'=>'form-control input-sm', 'readonly']) }}
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPconta">Mudar</button>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <hr />
            <div class="form-group crud_space">
                <div class="col-sm-6">
                    {{ Form::label('vprod', 'Produtos:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vprod',null,['class'=>'form-control input-sm dinheiro']) }}
                    </div>
                    {{ Form::label('totalvfrete', 'Frete:', ['class'=>'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('totalvfrete',null,['class'=>'form-control input-sm dinheiro']) }}
                    </div>
                </div>
                <div class="col-sm-6 col-sm-pull-1">
                    {{ Form::label('voutro', 'Outras Despesas:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('voutro',null,['class'=>'form-control input-sm dinheiro', 'id' => 'voutro']) }}
                    </div>
                    {{ Form::label('vseg', 'Seguro:', ['class'=>'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vseg',null,['class'=>'form-control input-sm dinheiro']) }}
                    </div>
                </div>
            </div>
            <div class="form-group crud_space">
                <div class="col-sm-6">
                    {{ Form::label('vipi', 'IPI:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vipi',null,['class'=>'form-control input-sm dinheiro']) }}
                    </div>
                    {{ Form::label('vbc', 'BC ICMS:', ['class'=>'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vbc',null,['class'=>'form-control input-sm dinheiro', 'id' => 'vbc']) }}
                    </div>
                </div>
                <div class="col-sm-6 col-sm-pull-1">
                    {{ Form::label('vicms', 'Valor ICMS:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vicms',null,['class'=>'form-control input-sm dinheiro', 'id' => 'vicms']) }}
                    </div>
                    {{ Form::label('vbcst', 'BC ICMS ST:', ['class'=>'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vbcst',null,['class'=>'form-control input-sm dinheiro', 'id' => 'vbcst']) }}
                    </div>
                </div>
            </div>
            <div class="form-group crud_space">
                <div class="col-sm-6">
                    {{ Form::label('vst', 'Valor ICMS ST:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vst',null,['class'=>'form-control input-sm dinheiro', 'id' => 'vst']) }}
                    </div>
                    {{ Form::label('vicmsdeson', 'ICMS Deson:', ['class'=>'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vicmsdeson', null,['class'=>'form-control input-sm dinheiro']) }}
                    </div>
                </div>
                <div class="col-sm-6 col-sm-pull-1">
                    {{ Form::label('totalvdesc', 'Desconto:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('totalvdesc',null,['class'=>'form-control input-sm dinheiro']) }}
                    </div>
                    {{ Form::label('vnf', 'Total:', ['class'=>'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vnf',null,['class'=>'form-control input-sm dinheiro']) }}
                    </div>
                </div>
            </div>
            <div class="form-group crud_space">
                <div class="col-sm-6">
                    {{ Form::label('vcofins', 'Valor COFINS:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vcofins',null,['class'=>'form-control input-sm dinheiro', 'id' => 'vcofins']) }}
                    </div>
                    {{ Form::label('vpis', 'Valor PIS:', ['class'=>'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vpis',null,['class'=>'form-control input-sm dinheiro', 'id' => 'vpis']) }}
                    </div>
                </div>
                <div class="col-sm-6 col-sm-pull-1">
                    {{ Form::label('vfcp', 'Valor FCP:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vfcp',null,['class'=>'form-control input-sm dinheiro', 'id' => 'vfcp']) }}
                    </div>
                    {{ Form::label('vfcpst', 'Valor FCP ST:', ['class'=>'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vfcpst',null,['class'=>'form-control input-sm dinheiro', 'id' => 'vfcpst']) }}
                    </div>
                </div>
            </div>
            <div class="form-group crud_space">
                <div class="col-sm-6">
                    <div class="col-sm-1 col-sm-push-3">
                        <button type="button" style="margin-top: 5px !important;" class="btn btn-xs btn-nw-buscas" id="btnCalculaTotais">Calcular Totais</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>