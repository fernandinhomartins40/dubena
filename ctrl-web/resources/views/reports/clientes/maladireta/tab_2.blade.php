<div class="tab-pane" id="tab_2">
    <!-- form start -->
    <div class="row">
        <div id="tabCadastro" class="col-sm-12">
            <div class="box-body">
                {{ Form::open(['id' => 'fmReport','class'=>'form-horizontal'])}}
                <div class="form-group crud_space">
                    {{ Form::label('cidade_id_tab_2', 'Cidade:', ['class'=>'col-sm-1 control-label input-sm', 'id' => 'labelData']) }}
                    <div class="col-sm-3">
                        {{ Form::select('cidade_id_tab_2',$cidades,null,['id' => 'cidade_id_tab_2','class'=>'form-control selectChosen input-sm']) }}
                    </div>
                    {{ Form::label('bairro_id_tab_2', 'Bairro:', ['class'=>'col-sm-1 control-label input-sm']) }}
                    <div class="col-sm-3">
                        {{ Form::select('bairro_id_tab_2',[],null,['id' => 'bairro_id_tab_2','class'=>'form-control selectChosen input-sm']) }}
                    </div>
                    {{ Form::label('rua_id_tab_2', 'Rua:', ['class'=>'col-sm-1 control-label input-sm', 'id' => 'labelData']) }}
                    <div class="col-sm-3">
                        {{ Form::select('rua_id_tab_2',[],null,['id' => 'rua_id_tab_2','class'=>'form-control selectChosen input-sm']) }}
                    </div>
                </div>
                <div class="form-group crud_space">
                    {{ Form::label('setor_id_tab_2', 'Setor:', ['class'=>'col-sm-1 control-label input-sm', 'id' => 'labelData']) }}
                    <div class="col-sm-3">
                        {{ Form::select('setor_id_tab_2',$setores,@$setor_id_tab_2,['id' => 'setor_id_tab_2','class'=>'form-control selectChosen input-sm']) }}
                    </div>
                </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>