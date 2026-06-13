<div class="row">
    <div id="tabCadastro" class="col-sm-12">
        <div class="box-body">
            <div class="form-group crud_space">
                <div class="col-sm-6">
                    {{ Form::label('dataparcela', 'Venc. Financeiro:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-4">
                        <div class="input-group generalDateTimePicker">
                            {{ Form::text('dataparcela',null,['class'=>'form-control input-sm generalDateTimePicker']) }}
                            <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
                        </div>
                    </div>
                </div>
            </div>
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
                        {{ Form::hidden('vista', null, ['id'=>'vista','class' => 'form-control input-sm']) }}
                        {{ Form::hidden('boleto', null, ['id'=>'boleto','class' => 'form-control input-sm']) }}
                        {{ Form::hidden('cartao', null, ['id'=>'cartao','class' => 'form-control input-sm']) }}
                        {{ Form::hidden('condicaoparcelas', null, ['id'=>'condicaoparcelas','class' => 'form-control input-sm']) }}
                        {{ Form::hidden('condicao', null, ['id'=>'condicao','class' => 'form-control input-sm']) }}
                    </div>
                </div>
            </div>
            <div class="form-group crud_space ">
                <div class="col-sm-6">
                    {{ Form::label('vbruto', 'Valor Bruto:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vbruto',null,['class'=>'form-control input-sm dinheiro', 'id' => 'vbruto']) }}
                    </div>
                    {{ Form::label('vliq', 'Valor Líq:', ['class'=>'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vliq',null,['class'=>'form-control input-sm dinheiro', 'id' => 'vliq']) }}
                    </div>
                </div>
                <div class="col-sm-6 col-sm-pull-1">
                    {{ Form::label('vdesc', 'Desconto:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vdesc', null,['class'=>'form-control input-sm dinheiro']) }}
                    </div>
                    {{ Form::label('', 'Parcelas:', ['class'=>'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-3">
                        <button type="button" id="btnVisualizarParcelas" class="btn btn-nw-buscas btn-xs">Visualizar Parcelas</button>
                        {{ Form::hidden('parcelas_financeiro', $parcelasfinanceiro, ['id'=>'parcelas_financeiro']) }}
                    </div>
                </div>
            </div>
            <div class="form-group crud_space">
                <div class="col-sm-6">
                    {{ Form::label('centrocusto_id', 'Centro Custo:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-8">
                        {{Form::hidden('centrocusto_id', $cupomFiscal->centrocusto_id, ['id'=>'centrocusto_id'])}}
                        <div class="input-group">
                            {{ Form::text('centrocusto_descricao', $cupomFiscal->centrocusto_descricao,['id'=>'centrocusto_descricao', 'class'=>'form-control input-sm', 'readonly']) }}
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcusto">Mudar</button>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-sm-pull-1">
                    {{ Form::label('planoconta_id', 'Plano Conta:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-8">
                        {{Form::hidden('planoconta_id', $cupomFiscal->planoconta_id, ['id'=>'planoconta_id'])}}
                        <div class="input-group">
                            {{ Form::text('planoconta_descricao', $cupomFiscal->planoconta_descricao,['id'=>'planoconta_descricao', 'class'=>'form-control input-sm', 'readonly']) }}
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
                    {{ Form::label('icmsvprod', 'Produtos:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('icmsvprod', null, ['class'=>'form-control input-sm dinheiro']) }}
                    </div>
                    {{ Form::label('icmsvdesc', 'Desconto:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('icmsvdesc', null, ['class'=>'form-control input-sm dinheiro']) }}
                    </div>
                </div>
                <div class="col-sm-6 col-sm-pull-1">
                    {{ Form::label('icmsvoutro', 'Outras Desp.:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('icmsvoutro',null,['class'=>'form-control input-sm dinheiro', 'id' => 'icmsvoutro']) }}
                    </div>
                    {{ Form::label('vmp', 'Valor Pago:', ['class'=>'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('vmp', null, ['class'=>'form-control input-sm dinheiro']) }}
                    </div>
                </div>
            </div>
            <div class="form-group crud_space">
                <div class="col-sm-6">
                    {{ Form::label('icmsvicms', 'Valor ICMS:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('icmsvicms', null, ['class'=>'form-control input-sm dinheiro', 'id' => 'icmsvicms']) }}
                    </div>
                    {{ Form::label('icmsvcofins', 'Valor COFINS:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('icmsvcofins',null,['class'=>'form-control input-sm dinheiro', 'id' => 'icmsvcofins']) }}
                    </div>
                </div>
                <div class="col-sm-6 col-sm-pull-1">
                    {{ Form::label('icmsvpis', 'Valor PIS:', ['class'=>'col-sm-3 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('icmsvpis',null,['class'=>'form-control input-sm dinheiro', 'id' => 'icmsvpis']) }}
                    </div>
                    {{ Form::label('icmsvcfe', 'Total:', ['class'=>'col-sm-2 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::text('icmsvcfe', null, ['class'=>'form-control input-sm dinheiro']) }}
                    </div>
                </div>
            </div>
            <div class="form-group crud_space">
                <div class="col-sm-6">
                    <div class="col-sm-1 offset1">
                        <button type="button" style="margin-top: 5px !important;" class="btn btn-xs btn-nw-buscas" id="btnCalculaTotais">Calcular Totais</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>