<div class="form-group crud_space">
    {{ Form::label('produtogp_id', 'Produto Gás do Povo:', ['class'=>'col-md-3 control-label input-sm']) }}
    <div class="col-md-3">
        {{ Form::select('produtogp_id',$produtos,null,['id' => 'produtogp_id','class'=>'form-control selectChosen input-sm']) }}
    </div>
    {{ Form::label('condicaopagamentogp_id', 'Cond. Pagto Gás do Povo:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('condicaopagamentogp_id',$condicaopagamentos,null,['id' => 'condicaopagamentogp_id','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('ccfretegp_id', 'Centro Custo Entrega:', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('ccfretegp_id',@$ccfretegp_id, ['id'=>'ccfretegp_id'])}}
        <div class="input-group">
            {{ Form::text('ccfretegp_descricao',@$ccfretegp_descricao,['id'=>'ccfretegp_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcustoFreteGp" onclick="abrirCentroCusto('jstreecc3','ccfretegp_id','ccfretegp_descricao');">Mudar</button>
            </span>
        </div>
    </div>
    {{ Form::label('pcfretegp_id', 'Plano Conta Entrega:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('pcfretegp_id',@$pcfretegp_id, ['id'=>'pcfretegp_id'])}}
        <div class="input-group">
            {{ Form::text('pcfretegp_descricao',@$pcfretegp_descricao,['id'=>'pcfretegp_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPcontaFreteGp" onclick="abrirPlanoConta('jstreepc3','pcfretegp_id','pcfretegp_descricao');">Mudar</button>
            </span>
        </div>
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('condicaopagamentofretegp_id', 'Cond. Pagto Entrega:', ['class'=>'col-md-3 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('condicaopagamentofretegp_id',$condicaopagamentos,null,['id' => 'condicaopagamentofretegp_id','class'=>'form-control selectChosen input-sm']) }}
    </div>
    {{ Form::label('valorfretegp', 'Valor Entrega:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-2">
        {{ Form::text('valorfretegp',null,['id' => 'valorfretegp','class'=>'form-control dinheiro input-sm']) }}
    </div>

</div>
