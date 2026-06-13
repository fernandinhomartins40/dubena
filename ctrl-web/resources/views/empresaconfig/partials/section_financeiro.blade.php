<div class="form-group crud_space">
    {{ Form::label('contadevolucaocheque', 'Conta Devolução Cheque:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('contadevolucaocheque',$contas,null,['id' => 'contadevolucaocheque','class'=>'form-control selectChosen input-sm']) }}
    </div>
    {{ Form::label('contachecktroco', 'Conta Troco Cheque:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('contachecktroco',$contas,null,['id' => 'contachecktroco','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('maloteconta_id', 'Conta Fechamento de Malotes:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('maloteconta_id',$contas,null,['id' => 'maloteconta_id','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('pccartao_id', 'P.C. Cartão:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('pccartao_id',@$pccartao_id, ['id'=>'pccartao_id'])}}
        <div class="input-group">
            {{ Form::text('pccartao_descricao',@$pccartao_descricao,['id'=>'pccartao_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPcontaCartao" onclick="abrirPlanoConta('jstreepc4','pccartao_id','pccartao_descricao');">Mudar</button>
            </span>
        </div>
    </div>
    {{ Form::label('cccartao_id', 'C.C. Cartão:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('cccartao_id',@$cccartao_id, ['id'=>'cccartao_id'])}}
        <div class="input-group">
            {{ Form::text('cccartao_desc',@$cccartao_desc,['id'=>'cccartao_desc', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcCartao" onclick="abrirCentroCusto('jstreecc10','cccartao_id','cccartao_desc');">Mudar</button>
            </span>
        </div>
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('pcreceitadesconto_id', 'P.C. Receita Desconto:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('pcreceitadesconto_id',@$pcreceitadesconto_id, ['id'=>'pcreceitadesconto_id'])}}
        <div class="input-group">
            {{ Form::text('pcreceitadesconto_desc',@$pcreceitadesconto_desc,['id'=>'pcreceitadesconto_desc', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPcontaRecDes" onclick="abrirPlanoConta('jstreepc5','pcreceitadesconto_id','pcreceitadesconto_desc');">Mudar</button>
            </span>
        </div>
    </div>
    {{ Form::label('pcrecetajuro_id', 'P.C. Receita Juros/Multa:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('pcrecetajuro_id',@$pcrecetajuro_id, ['id'=>'pcrecetajuro_id'])}}
        <div class="input-group">
            {{ Form::text('pcreceitajuros_desc',@$pcreceitajuro_desc,['id'=>'pcreceitajuros_desc', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPcontaRecJu" onclick="abrirPlanoConta('jstreepc6','pcrecetajuro_id','pcreceitajuros_desc');">Mudar</button>
            </span>
        </div>
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('ccreceitasdescontos_id', 'C.C. Receita Desconto:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('ccreceitasdescontos_id',@$ccreceitadesc_id, ['id'=>'ccreceitasdescontos_id'])}}
        <div class="input-group">
            {{ Form::text('ccreceitasdescontos_desc',@$ccreceitadesc_desc,['id'=>'ccreceitasdescontos_desc', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcRecDesc" onclick="abrirCentroCusto('jstreecc9','ccreceitasdescontos_id','ccreceitasdescontos_desc');">Mudar</button>
            </span>
        </div>
    </div>
    {{ Form::label('ccreceitasjuros_id', 'C.C. Receita Juros/Multa:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('ccreceitasjuros_id',@$ccreceitajuros_id, ['id'=>'ccreceitasjuros_id'])}}
        <div class="input-group">
            {{ Form::text('ccreceitasjuros_desc',@$ccreceitajuros_desc,['id'=>'ccreceitasjuros_desc', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcRecJuros" onclick="abrirCentroCusto('jstreecc8','ccreceitasjuros_id','ccreceitasjuros_desc');">Mudar</button>
            </span>
        </div>
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('pcdespesasdesconto_id', 'P.C. Despesas Desconto:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('pcdespesasdesconto_id',@$pcdespesasdesconto_id, ['id'=>'pcdespesasdesconto_id'])}}
        <div class="input-group">
            {{ Form::text('pcdespesasdesconto_desc',@$pcdespesasdesconto_desc,['id'=>'pcdespesasdesconto_desc', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPcontaDesDes" onclick="abrirPlanoConta('jstreepc8','pcdespesasdesconto_id','pcdespesasdesconto_desc');">Mudar</button>
            </span>
        </div>
    </div>
    {{ Form::label('pcdespesasjuro_id', 'P.C. Despesas Juros/Multa:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('pcdespesasjuro_id',@$pcdespesasjuro_id, ['id'=>'pcdespesasjuro_id'])}}
        <div class="input-group">
            {{ Form::text('pcdespesasjuro',@$pcdespesasjuro_desc,['id'=>'pcdespesasjuro', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPcontaDesJu" onclick="abrirPlanoConta('jstreepc8','pcdespesasjuro_id','pcdespesasjuro');">Mudar</button>
            </span>
        </div>
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('ccdespesasdescontos_id', 'C.C. Despesas Desconto:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('ccdespesasdescontos_id',@$ccdespesasdesc_id, ['id'=>'ccdespesasdescontos_id'])}}
        <div class="input-group">
            {{ Form::text('ccdespesasdescontos_desc',@$ccdespesasdesc_desc,['id'=>'ccdespesasdescontos_desc', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcDesc" onclick="abrirCentroCusto('jstreecc7','ccdespesasdescontos_id','ccdespesasdescontos_desc');">Mudar</button>
            </span>
        </div>
    </div>
    {{ Form::label('ccdespesasjuros_id', 'C.C. Despesas Juros/Multa:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('ccdespesasjuros_id',@$ccdespesasjuros_id, ['id'=>'ccdespesasjuros_id'])}}
        <div class="input-group">
            {{ Form::text('ccdespesasjuros_desc',@$ccdespesasjuros_desc,['id'=>'ccdespesasjuros_desc', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcJuros" onclick="abrirCentroCusto('jstreecc6','ccdespesasjuros_id','ccdespesasjuros_desc');">Mudar</button>
            </span>
        </div>
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('ccvalegas_id', 'Centro Custo Vale Gás:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('ccvalegas_id',@$ccvalegas_id, ['id'=>'ccvalegas_id'])}}
        <div class="input-group">
            {{ Form::text('ccvalegas_descricao',@$ccvalegas_descricao,['id'=>'ccvalegas_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcustoValGas" onclick="abrirCentroCusto('jstreecc4','ccvalegas_id','ccvalegas_descricao');">Mudar</button>
            </span>
        </div>
    </div>
    {{ Form::label('pcvalegas_id', 'Plano Conta Vale Gás:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('pcvalegas_id',@$pcvalegas_id, ['id'=>'pcvalegas_id'])}}
        <div class="input-group">
            {{ Form::text('pcvalegas_descricao',@$pcvalegas_descricao,['id'=>'pcvalegas_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPcontaValGas" onclick="abrirPlanoConta('jstreepc9','pcvalegas_id','pcvalegas_descricao');">Mudar</button>
            </span>
        </div>
    </div>
</div>