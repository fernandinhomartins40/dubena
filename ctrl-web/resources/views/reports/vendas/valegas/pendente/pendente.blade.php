<div class="col-md-12">
    <div class="form-group crud_space">
        {{ Form::label('cliente_pendente', 'Cliente:', ['class'=>'col-sm-2 control-label input-sm']) }}
        <div class="col-sm-4">
            {{ Form::select('cliente_pendente',$clientes,null,['id' => 'cliente_pendente','class'=>'form-control selectChosen input-sm']) }}
        </div>
        <div id="checkbox">
            {!! Form::label('prevenda', 'Pré-Venda', ['class'=>'col-md-1 control-label input-sm']) !!}
            <div class="col-md-1 checkbox">
                {!! Form::checkbox('prevenda',0) !!}
            </div>
        </div>
        <div class="col-sm-2">
            <button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.valegas')}}?tab=tab_1'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
            <button type="button" id='gerarPdfPendente' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
            <button id="btnFiltroPendente" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
        </div>   
    </div>
</div>