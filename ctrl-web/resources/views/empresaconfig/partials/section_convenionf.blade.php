<div class="form-group crud_space">
    {{ Form::label('nfoperacaoconvenio_id', 'Operação Convênio:', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('nfoperacaoconvenio_id',$nfoperacao,null,['id' => 'nfoperacaoconvenio_id','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('ccconvenio_id', 'Centro Custo Convênio:', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('ccconvenio_id',@$ccconvenio_id, ['id'=>'ccconvenio_id'])}}
        <div class="input-group">
            {{ Form::text('ccconvenio_descricao',@$ccconvenio_descricao,['id'=>'ccconvenio_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcustoConvenio" onclick="abrirCentroCusto('jstreecc3','ccconvenio_id','ccconvenio_descricao');">Mudar</button>
            </span>
        </div>
    </div>
    {{ Form::label('pcconvenio_id', 'Plano Conta Convênio:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('pcconvenio_id',@$pcconvenio_id, ['id'=>'pcconvenio_id'])}}
        <div class="input-group">
            {{ Form::text('pcconvenio_descricao',@$pcconvenio_descricao,['id'=>'pcconvenio_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPcontaConvenio" onclick="abrirPlanoConta('jstreepc3','pcconvenio_id','pcconvenio_descricao');">Mudar</button>
            </span>
        </div>
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('presencacompradorconvenionf', 'Presença Comprador Convênio:', ['class'=>'col-md-3 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('presencacompradorconvenionf',$comprador,null,['id' => 'presencacompradorconvenionf','class'=>'form-control selectChosen input-sm']) }}
    </div>
    {{ Form::label('fretemodalidadeconvenionf', 'Modalidade Frete Convênio:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('fretemodalidadeconvenionf',$frete,null,['id' => 'fretemodalidadeconvenionf','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('ccfreteconvenio_id', 'C. Custo Frete Convënio:', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('ccfreteconvenio_id',@$ccfreteconvenio_id, ['id'=>'ccfreteconvenio_id'])}}
        <div class="input-group">
            {{ Form::text('ccfreteconvenio_descricao',@$ccfreteconvenio_descricao,['id'=>'ccfreteconvenio_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcFreteConvenio" onclick="abrirCentroCusto('jstreecc4','ccfreteconvenio_id','ccfreteconvenio_descricao');">Mudar</button>
            </span>
        </div>
    </div>
    {{ Form::label('pcfreteconvenio_id', 'P. Conta Frete Convënio:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('pcfreteconvenio_id',@$pcfreteconvenio_id, ['id'=>'pcfreteconvenio_id'])}}
        <div class="input-group">
            {{ Form::text('pcfreteconvenio_descricao',@$pcfreteconvenio_descricao,['id'=>'pcfreteconvenio_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPcFreteConvenio" onclick="abrirPlanoConta('jstreepc10','pcfreteconvenio_id','pcfreteconvenio_descricao');">Mudar</button>
            </span>
        </div>
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('transportadorconvenionf_id', 'Transportadora Convênio:', ['class'=>'col-md-3 control-label input-sm']) }}
    <div class="col-md-3">
        {{ Form::select('transportadorconvenionf_id',$transportadoras,null,['id' => 'transportadorconvenionf_id','class'=>'form-control selectChosen input-sm']) }}
    </div>
    {{ Form::label('veiculoconvenio_id', 'Veículo Padrão Convênio:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-md-3">
        {{ Form::select('veiculoconvenio_id',$veiculos,null,['id' => 'veiculoconvenio_id','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('setorconvenio_id', 'Setor Convênio:', ['class'=>'col-md-3 control-label input-sm']) }}
    <div class="col-md-3">
        {{ Form::select('setorconvenio_id',$setors,null,['id' => 'setorconvenio_id','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('contaconvenionf_id', 'Conta p/ Boleto Convênio:', ['class'=>'col-md-3 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('contaconvenionf_id',$contasboleto,null,['id' => 'contaconvenionf_id','class'=>'form-control selectChosen input-sm']) }}
    </div>
    {{ Form::label('condicaopagamentoconvenio_id', 'Cond. Pagto p/ Boleto Convênio:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('condicaopagamentoconvenio_id',$condicaopagamentosboleto,null,['id' => 'condicaopagamentoconvenio_id','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
