<div class="row">
    <div id="tabCadastro" class="col-sm-12">
        <div class="box-body">
            <div class="form-group crud_space">
                {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    <div class="input-group generalDatePicker">
                        {{Form::text('datainicio_pr', null, ['id' => 'datainicio_pr', 'class' => 'input-sm form-control generalDatePicker'])}}
                        <span class="input-group-addon">
                            <span class="glyphicon glyphicon-calendar"></span>
                        </span>
                    </div>
                </div>  
                {{ Form::label('datafim_pr', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    <div class="input-group generalDatePicker">
                        {{Form::text('datafim_pr', null, (['id' => 'datafim_pr', 'class' => 'input-sm form-control generalDatePicker']))}}
                        <span class="input-group-addon">
                            <span class="glyphicon glyphicon-calendar"></span>
                        </span>
                    </div>
                </div>
                {{Form::label('nfoperacao_pr', "Operação:", ['class' => 'col-sm-1 control-label input-sm'])}}
                <div class="col-sm-3">
                    {{Form::select('nfoperacao_pr', $cfop, null, ['id'=>'nfoperacao_pr','class' =>'selectChosen'])}}
                </div>
            </div>
            <div class="form-group crud_space">
                {{Form::label('produto_id', "Produto:", ['class' => 'col-sm-1 control-label input-sm'])}}
                <div class="col-sm-2">
                    {{Form::select('produto_id', $produto, null, ['id'=>'produto_id','class' =>'selectChosen'])}}
                </div>
                <div class="col-sm-2 col-sm-offset-1">
                    <button type="button" id='btnLimparPr' class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                    <button id="btnIframePr" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
                </div>
            </div>
        </div>
    </div>
</div>