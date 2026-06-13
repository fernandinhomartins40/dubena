<div class="tab-pane" id="tab_3">
    <!-- form start -->
    <div class="row">
        <div id="tabCadastro" class="col-sm-12">
            <div class="box-body">
                {{ Form::open(['id' => 'fmReport','class'=>'form-horizontal'])}}
                <div class="form-group crud_space">
                    {{ Form::label('cidade_id_tab_3', 'Cidade:', ['class'=>'col-sm-1 control-label input-sm', 'id' => 'labelData']) }}
                    <div class="col-sm-3">
                        {{ Form::select('cidade_id_tab_3',$cidades,null,['id' => 'cidade_id_tab_3','class'=>'form-control selectChosen input-sm']) }}
                    </div>
                    {{ Form::label('bairro_id_tab_3', 'Bairro:', ['class'=>'col-sm-1 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::select('bairro_id_tab_3',[],null,['id' => 'bairro_id_tab_3','class'=>'form-control selectChosen input-sm']) }}
                    </div>
                    {{ Form::label('rua_id_tab_3', 'Rua:', ['class'=>'col-sm-1 control-label input-sm', 'id' => 'labelData']) }}
                    <div class="col-sm-3">
                        {{ Form::select('rua_id_tab_3',[],null,['id' => 'rua_id_tab_3','class'=>'form-control selectChosen input-sm']) }}
                    </div>
                </div>
                <div class="form-group crud_space">
                    {{ Form::label('setor_id_tab_3', 'Setor:', ['class'=>'col-sm-1 control-label input-sm', 'id' => 'labelData']) }}
                    <div class="col-sm-3">
                        {{ Form::select('setor_id_tab_3',$setores,@$setor_id_tab_3,['id' => 'setor_id_tab_3','class'=>'form-control selectChosen input-sm']) }}
                    </div>
                    {{ Form::label('compram', 'Compram a:', ['class'=>'col-sm-1 control-label input-sm', 'id' => 'labelData']) }}
                    <div class="col-sm-2">
                        <div class="col-sm-10" style="margin-left: -8.5%">
                            {{ Form::number('compram',@$compram,['id' => 'compram','class'=>'form-control number input-sm']) }}
                        </div>
                        {{ Form::label('compram', 'dias', ['class'=>'col-sm-1 control-label input-sm', 'id' => 'labelData']) }}
                    </div>
                    {{ Form::label('naocompram', 'Não compram a:', ['class'=>'col-sm-2 control-label input-sm', 'id' => 'labelData']) }}
                    <div class="col-sm-2">
                        <div class="col-sm-10" style="margin-left: -8.5%">
                            {{ Form::number('naocompram',@$naocompram,['id' => 'naocompram','class'=>'form-control number input-sm']) }}
                        </div>
                        {{ Form::label('naocompram', 'dias', ['class'=>'col-sm-1 control-label input-sm', 'id' => 'labelData']) }}
                    </div>
                </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>