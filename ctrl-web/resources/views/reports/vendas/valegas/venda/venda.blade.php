<div class="col-md-12">
    <div class="form-group crud_space">
        {{ Form::label('datainicio_venda', 'Data Início:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;'])}}
        <div class="col-sm-2">
            <div class="input-group date generalDatePicker" id="datetimepicker1">
                {{ Form::datetime('datainicio_venda',null,['id'=>'datainicio_venda','class'=>'form-control input-sm generalDatePicker']) }}
                <span class="input-group-addon">
                    <span class="glyphicon glyphicon-calendar"></span>
                </span>
            </div>
        </div>
        {{ Form::label('datafim_venda', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;'])}}
        <div class="col-sm-2">
            <div class="input-group date generalDatePicker" id="datetimepicker1">
                {{ Form::datetime('datafim_venda',null,['id'=>'datafim_venda','class'=>'form-control input-sm generalDatePicker']) }}
                <span class="input-group-addon">
                    <span class="glyphicon glyphicon-calendar"></span>
                </span>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="col-sm-11">
                {{ Form::label('compram', 'Compram', ['class'=>'col-sm-3 control-label input-sm']) }}
                <div class="col-sm-1 checkbox"> 
                    {{ Form::radio('compram', '1', true,['onclick'=>'checarCompram()']) }}
                </div>
                {!! Form::label('nao_compram', 'Não compram', ['class'=>'col-sm-6 control-label input-sm']) !!}
                <div class="col-sm-1 checkbox">
                    {{ Form::radio('compram', '2', false,['onclick'=>'checarCompram()']) }}
                </div>
            </div>
        </div>
    </div>
    <div class="form-group crud_space">
        {{ Form::label('cliente_venda', 'Cliente:', ['class'=>'col-sm-2 control-label input-sm']) }}
        <div class="col-sm-4">
            {{ Form::select('cliente_venda',$clientes,null,['id' => 'cliente_venda','class'=>'form-control selectChosen input-sm']) }}
        </div>
        {{ Form::label('situacao_val', 'Situação:', ['class'=>'col-sm-1 control-label input-sm']) }}
        <div class="col-sm-2">
            {{ Form::select('situacao_val',[""=>"Selecione","01"=>"Ativo","02"=>"Cancelado"],null,['id' => 'situacao_val','class'=>'form-control selectChosen input-sm']) }}
        </div>
        <div class="col-sm-2">
            <button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.valegas')}}?tab=tab_3'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
            <button type="button" id='gerarPdfVenda' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
            <button id="btnFiltroVenda" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
        </div>
    </div>
</div>