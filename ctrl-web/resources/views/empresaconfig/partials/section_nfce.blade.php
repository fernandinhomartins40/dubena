<div class="form-group crud_space">                                               
    <div id="boxpedidoemitenfce">
        {{ Form::label('pedidoemitenfce', 'Pedido Emite NFC-e', ['class'=>'col-sm-3 control-label input-sm']) }}
        <div class="col-sm-1 checkbox">
            {{ Form::checkbox('pedidoemitenfce',1) }}
        </div>
    </div>  
</div>
<div class="form-group crud_space">
    {{Form::hidden('cliente_id',@$empconfig->nfcecliente_id, ['id'=>'cliente_id'])}}
    {{Form::hidden('cliente_nome',@$empconfig->nfcecliente->nome, ['id'=>'cliente_nome'])}}
    {{Form::label('nfcecliente', 'Cliente:', ['class'=>'col-md-3 control-label input-sm']) }}
    <div class=" col-md-3">
        <select id="nfcecliente_id" name="nfcecliente_id" placeholder="Buscar cliente" class="form-control" value="" data-selectize-value = '[]'></select>
    </div> 
    {{ Form::label('nfoperacoes_id', 'Operação:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-md-3">
        {{ Form::select('nfoperacoes_id',$nfoperacao,null,['id' => 'nfoperacoes_id','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('centrocusto_id', 'Centro Custo:', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('centrocusto_id',@$centrocusto_id, ['id'=>'centrocusto_id'])}}
        <div class="input-group">
            {{ Form::text('centrocusto_descricao',@$centrocusto_descricao,['id'=>'centrocusto_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcusto" onclick="abrirCentroCusto('jstreecc3','centrocusto_id','centrocusto_descricao');">Mudar</button>
            </span>
        </div>
    </div>
    {{ Form::label('planoconta_id', 'Plano Conta:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('planoconta_id',@$planoconta_id, ['id'=>'planoconta_id'])}}
        <div class="input-group">
            {{ Form::text('planoconta_descricao',@$planoconta_descricao,['id'=>'planoconta_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPconta" onclick="abrirPlanoConta('jstreepc3','planoconta_id','planoconta_descricao');">Mudar</button>
            </span>
        </div>
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('presencacomprador', 'Presença Comprador:', ['class'=>'col-md-3 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('presencacomprador',$comprador,null,['id' => 'presencacomprador','class'=>'form-control selectChosen input-sm']) }}
    </div>
    {{ Form::label('fretemodalidade', 'Modalidade Frete:', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('fretemodalidade',$frete,null,['id' => 'fretemodalidade','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('ccfrete_id', 'Centro Custo Frete:', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('ccfrete_id',@$ccfrete_id, ['id'=>'ccfrete_id'])}}
        <div class="input-group">
            {{ Form::text('ccfrete_descricao',@$ccfrete_descricao,['id'=>'ccfrete_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcFrete" onclick="abrirCentroCusto('jstreecc4','ccfrete_id','ccfrete_descricao');">Mudar</button>
            </span>
        </div>
    </div>
    {{ Form::label('pcfrete_id', 'Plano Conta Frete:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{Form::hidden('pcfrete_id',@$pcfrete_id, ['id'=>'pcfrete_id'])}}
        <div class="input-group">
            {{ Form::text('pcfrete_descricao',@$pcfrete_descricao,['id'=>'pcfrete_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
            <span class="input-group-btn">
                <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPcFrete" onclick="abrirPlanoConta('jstreepc10','pcfrete_id','pcfrete_descricao');">Mudar</button>
            </span>
        </div>
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('transportadorpadrao_id', 'Transportadora:', ['class'=>'col-md-3 control-label input-sm']) }}
    <div class="col-md-4">
        {{ Form::select('transportadorpadrao_id',$transportadoras,null,['id' => 'transportadorpadrao_id','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>