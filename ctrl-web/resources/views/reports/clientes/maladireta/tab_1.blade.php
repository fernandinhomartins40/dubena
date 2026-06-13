<div class="tab-pane active" id="tab_1">
    <!-- form start -->
    <div class="row">
        <div id="tabCadastro" class="col-sm-12">
            <div class="box-body">
                {{ Form::open(['id' => 'fmReport','class'=>'form-horizontal'])}}
                {{ Form::hidden('uf',@$uf,['id' => 'uf','class'=>'form-control input-sm' ]) }}
                <div class="form-group crud_space">
                    {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-1 control-label input-sm']) }}
                    <div class="col-sm-2">
                        <div class="input-group generalDatePicker">
                            {{ Form::text('datainicio',null,['id' => 'datainicio','class'=>'form-control generalDatePicker input-sm']) }}
                            <span class="input-group-addon">
                                <span class="glyphicon glyphicon-calendar"></span>
                            </span>    
                        </div>
                    </div>
                    {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-2 control-label input-sm', 'id' => 'labelData']) }}
                    <div class="col-sm-2">
                        <div class="input-group generalDatePicker">
                            {{ Form::text('datafim',null,['id' => 'datafim','class'=>'form-control generalDatePicker input-sm']) }}
                            <span class="input-group-addon">
                                <span class="glyphicon glyphicon-calendar"></span>
                            </span>    
                        </div>
                    </div>
                    {{ Form::label('cidade_id', 'Cidade:', ['class'=>'col-sm-2 control-label input-sm', 'id' => 'labelData']) }}
                    <div class="col-sm-3">
                        {{ Form::select('cidade_id',$cidades,null,['id' => 'cidade_id','class'=>'form-control selectChosen input-sm']) }}
                    </div>
                </div>
                <div class="form-group crud_space">
                    {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-1 control-label input-sm', 'id' => 'labelData']) }}
                    <div class="col-sm-3">
                        {{ Form::select('setor_id_tab_1',$setores,@$setor_id,['id' => 'setor_id','class'=>'form-control selectChosen input-sm']) }}
                    </div>
                    {{ Form::label('bairro_id', 'Bairro:', ['class'=>'col-sm-1 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::select('bairro_id',[],null,['id' => 'bairro_id','class'=>'form-control selectChosen input-sm']) }}
                    </div>
                    {{ Form::label('rua_id', 'Rua:', ['class'=>'col-sm-1 control-label input-sm', 'id' => 'labelData']) }}
                    <div class="col-sm-3">
                        {{ Form::select('rua_id',[],null,['id' => 'rua_id','class'=>'form-control selectChosen input-sm']) }}
                    </div>
                </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>