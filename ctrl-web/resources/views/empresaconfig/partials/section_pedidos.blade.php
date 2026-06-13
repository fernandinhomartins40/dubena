<div class="form-group crud_space">
    {{ Form::label('pedidooperacao_id', 'Operação Pedido:', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-4">
        {{ Form::select('pedidooperacao_id',$cfop,null,['id' => 'pedidooperacao_id','class'=>'form-control selectChosen input-sm']) }}
    </div>
    {{ Form::label('operacaodisk', 'Operação Disk:', ['class'=>'col-sm-1 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('operacaodisk',$oppedido,null,['id' => 'operacaodisk','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('tempourgente', 'Tempo Entrega Urgente(min):', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::text('tempourgente',null,['id' => 'tempourgente','class'=>'form-control number input-sm']) }}
    </div> 
    {{ Form::label('tempoentrega', 'Tempo Entrega (min):', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::text('tempoentrega',null,['id' => 'tempoentrega','class'=>'form-control number input-sm']) }}
    </div>
    {{ Form::label('pedidostatuspadrao', 'Status Padrão:', ['class'=>'col-md-1 control-label input-sm']) }}
    <div class="col-md-3">
        {{ Form::select('pedidostatuspadrao',$status,null,['id' => 'pedidostatuspadrao','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('maximoparcelas', 'Máximo Parcelas - Cond. Pagamento:', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::text('maximoparcelas',null,['id' => 'maximoparcelas','class'=>'form-control number input-sm']) }}
    </div> 
    {{ Form::label('validagasbolso', 'Pedido Valida Vale Gás', ['class'=>'col-md-2 control-label input-sm']) }}
    <div class="col-md-1 checkbox">
        {{ Form::checkbox('validagasbolso',1) }}
    </div>
    {{ Form::label('quant_padrao', 'Qtdade Itens:', ['class'=>'col-sm-1 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::text('quant_padrao',null,['id' => 'quant_padrao','class'=>'form-control number input-sm']) }}
    </div> 
</div>
<div class="form-group crud_space">
    <div id="boxvalidaatraso">
        {{ Form::label('validaatraso', 'Valida Atraso', ['class'=>'col-sm-3 control-label input-sm']) }}
        <div class="col-md-1 checkbox">
            {{ Form::checkbox('validaatraso',1) }}
        </div>
    </div>
    <div id="boxpedidovalidacartao">
        {{ Form::label('pedidovalidacartao', 'Pedido Valida Cartão', ['class'=>'col-md-2 control-label input-sm']) }}
        <div class="col-md-1 checkbox">
            {{ Form::checkbox('pedidovalidacartao',1) }}
        </div>
    </div>  
    {{ Form::label('pedidovalidacartaodias', 'N° Dias Cartões:', ['class'=>'col-md-1 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::text('pedidovalidacartaodias',null,['id' => 'pedidovalidacartaodias','class'=>'form-control number input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    <div id="boxvalidapixentrega">
        {{ Form::label('validapixentrega', 'Valida Pix na Entrega', ['class'=>'col-sm-3 control-label input-sm']) }}
        <div class="col-md-1 checkbox">
            {{ Form::checkbox('validapixentrega',1) }}
        </div>
    </div>
</div>
