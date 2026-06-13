<div class="col-sm-12">
    <div class="form-group crud_space">
        {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;'])}}
        <div class="col-sm-2">
            <div class="input-group date generalDatePicker" id="datetimepicker1">
                {{ Form::datetime('datainicio',null,['id'=>'datainicio','class'=>'form-control input-sm generalDatePicker']) }}
                <span class="input-group-addon">
                    <span class="glyphicon glyphicon-calendar"></span>
                </span>
            </div>
        </div>
        {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;'])}}
        <div class="col-sm-2">
            <div class="input-group date generalDatePicker" id="datetimepicker1">
                {{ Form::datetime('datafim',null,['id'=>'datafim','class'=>'form-control input-sm generalDatePicker']) }}
                <span class="input-group-addon">
                    <span class="glyphicon glyphicon-calendar"></span>
                </span>
            </div>
        </div>
        {{ Form::label('situacao', 'Situação:', ['class'=>'col-sm-1 control-label input-sm']) }}
        <div class="col-sm-3">
            {{ Form::select('situacao',$situacao,null,['id' => 'situacao','class'=>'form-control selectChosen input-sm']) }}
        </div>
    </div>
    <div class="form-group crud_space">
        {{ Form::label('cliente_baixado', 'Cliente:', ['class'=>'col-sm-2 control-label input-sm']) }}
        <div class="col-sm-4">
            {{ Form::select('cliente_baixado',$clientes,null,['id' => 'cliente_baixado','class'=>'form-control selectChosen input-sm']) }}
        </div>
        <div class="col-sm-2">
            <button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.valegas')}}?tab=tab_2'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
            <button type="button" id='gerarPdfBaixado' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
            <button id="btnFiltroBaixado" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
        </div>
    </div>
</div>