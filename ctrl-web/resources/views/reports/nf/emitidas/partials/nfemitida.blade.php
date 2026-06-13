<div class="row">
    <div id="tabCadastro" class="col-sm-12">
        <div class="box-body">
            <div class="form-group crud_space">
                {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    <div class="input-group generalDatePicker">
                        {{Form::text('datainicio', null, ['id' => 'datainicio', 'class' => 'input-sm form-control generalDatePicker'])}}
                        <span class="input-group-addon">
                            <span class="glyphicon glyphicon-calendar"></span>
                        </span>
                    </div>
                </div>
                {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    <div class="input-group generalDatePicker">
                        {{Form::text('datafim', null, (['id' => 'datafim', 'class' => 'input-sm form-control generalDatePicker']))}}
                        <span class="input-group-addon">
                            <span class="glyphicon glyphicon-calendar"></span>
                        </span>
                    </div>
                </div>
                {{ Form::label('cliente_id', 'Emitente/Destinatário:', ['class'=>'col-sm-1 control-label input-sm','style'=>'margin-right:8px;']) }}
                <div class="col-sm-4">
                    {{ Form::select('cliente_id',[],null,['id' => 'cliente_id','class'=>'form-control input-sm', 'placeholder' => 'Buscar Emitente/Destinatário', 'data-selectize-value' => '[]']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('situacao', 'Situação:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-3">
                    {{ Form::select('situacao',$situacao,null,['id' => 'situacao','class'=>'form-control selectChosen input-sm']) }}
                </div>
                {{Form::label('nfoperacao', "Operação:", ['class' => 'col-sm-1 control-label input-sm'])}}
                <div class="col-sm-4">
                    {{Form::select('nfoperacao', $cfop, null, ['id'=>'nfoperacao','class' =>'selectChosen'])}}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('nfmodelos', 'Modelos:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-5">
                    {{ Form::select('nfmodelos', $modelos, null, ['id'=>'nfmodelos', 'class' =>'form-control selectChosen input-sm', "multiple", "data-placeholder" => "Todos"])}}
                </div>
                {{ Form::label('ordem', 'Ordenar Por:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2 radio">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    {{Form::radio('ordem', 'D' , true)}} <label id="ordemData"> Emissão </label>
                    <br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    {{Form::radio('ordem', 'O' , false)}} <label > Operação </label>
                </div>
                <div class="col-sm-2">
                    <button type="button" id='btnLimpar' class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                    <button id="btnIframe" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
                </div>
            </div>
        </div>
    </div>
</div>