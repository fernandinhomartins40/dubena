<div class="form-group crud_space">
    {{ Form::label('impressaoqtdviaspedido', 'Quantidade Impressão Pedido:', ['class'=>'col-md-3 control-label input-sm']) }}
    <div class="col-md-1">
        {{ Form::text('impressaoqtdviaspedido',null,['id' => 'impressaoqtdviaspedido','class'=>'form-control input-sm']) }}
    </div>
    <div id="boxeimpressaoautomatica">
        {{ Form::label('impressaoautomatica', 'Impressão Automática:', ['class'=>'col-md-3 control-label input-sm']) }}
        <div class="col-md-1 checkbox">
            {{ Form::checkbox('impressaoautomatica',1) }}
        </div>
    </div>
</div>