<div class="col-sm-12">
    <div class="form-group crud_space">
        {{ Form::label('datainicio_pedido', 'Data Início:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;'])}}
        <div class="col-sm-2">
            <div class="input-group date generalDatePicker" id="datetimepicker1">
                {{ Form::datetime('datainicio_pedido',null,['id'=>'datainicio_pedido','class'=>'form-control input-sm generalDatePicker']) }}
                <span class="input-group-addon">
                    <span class="glyphicon glyphicon-calendar"></span>
                </span>
            </div>
        </div>
        {{ Form::label('datafim_pedido', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;'])}}
        <div class="col-sm-2">
            <div class="input-group date generalDatePicker" id="datetimepicker1">
                {{ Form::datetime('datafim_pedido',null,['id'=>'datafim_pedido','class'=>'form-control input-sm generalDatePicker']) }}
                <span class="input-group-addon">
                    <span class="glyphicon glyphicon-calendar"></span>
                </span>
            </div>
        </div>
    </div>
    <div class="form-group crud_space">
        {{ Form::label('cliente_pedido', 'Cliente:', ['class'=>'col-sm-2 control-label input-sm']) }}
        <div class="col-sm-4">
            {{ Form::select('cliente_pedido',$clientes,null,['id' => 'cliente_pedido','class'=>'form-control selectChosen input-sm']) }}
        </div>
        <div class="col-sm-2">
            <button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.valegas')}}?tab=tab_2'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
            <button type="button" id='gerarPdfPedido' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
            <button id="btnFiltroPedido" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
        </div>
    </div>
</div>