<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabelCadastro"></h4>
            </div>
            {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmCadastroAjax']) }}
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('codigo', 'Código:', ['class'=>'col-sm-2 control-label input-sm ']) !!}
                        <div class="col-sm-3">
                            {!! Form::text('codigo',null,['class'=>'form-control input-sm number', 'id'=>'codigo', 'autofocus' => 'true']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        <input type="hidden" id="id" name="id">
                        {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-10">
                            <input type="hidden" id="grupo_id" name="grupo_id">
                            <input type="hidden" id="empresa_id" name="grupo_id">
                            <input type="hidden" id="metodo" name="_method">
                            {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id'=>'descricao']) !!}
                        </div>
                    </div>
                    @isset($classtrib)
                        <div class="form-group crud_space col-sm-12">
                            <label for="ind_gtribregular" class="col-sm-5 control-label input-sm">Grupo da Tributação Regular:</label>
                            <div class="col-sm-1 checkbox">
                                {{ Form::checkbox('ind_gtribregular', 1, null, ['id'=>'ind_gtribregular']) }}
                            </div>
                            {!! Form::label('ind_gcredpresoper', 'Crédito Presumido da Operação:', ['class'=>'col-sm-5 control-label input-sm']) !!}
                            <div class="col-sm-1 checkbox">
                                {{ Form::checkbox('ind_gcredpresoper', 1, null, ['id'=>'ind_gcredpresoper']) }}
                            </div>
                        </div>
                        <div class="form-group crud_space col-sm-12">
                            {!! Form::label('ind_gmonopadrao', 'Grupo Trib Monofásica Padrão:', ['class'=>'col-sm-5 control-label input-sm']) !!}
                            <div class="col-sm-1 checkbox">
                                {{ Form::checkbox('ind_gmonopadrao', 1, null, ['id'=>'ind_gmonopadrao']) }}
                            </div>
                            {!! Form::label('ind_gmonoreten', 'Grupo Trib Monofásica Ret:', ['class'=>'col-sm-5 control-label input-sm']) !!}
                            <div class="col-sm-1 checkbox">
                                {{ Form::checkbox('ind_gmonoreten', 1, null, ['id'=>'ind_gmonoreten']) }}
                            </div>
                        </div>
                        <div class="form-group crud_space col-sm-12">
                            {!! Form::label('ind_gmonoret', 'Grupo Trib Monofásica Ret Ant:', ['class'=>'col-sm-5 control-label input-sm']) !!}
                            <div class="col-sm-1 checkbox">
                                {{ Form::checkbox('ind_gmonoret', 1, null, ['id'=>'ind_gmonoret']) }}
                            </div>
                            {!! Form::label('ind_gmonodif', 'Grupo Dif Trib Monofásica:', ['class'=>'col-sm-5 control-label input-sm']) !!}
                            <div class="col-sm-1 checkbox">
                                {{ Form::checkbox('ind_gmonodif', 1, null, ['id'=>'ind_gmonodif']) }}
                            </div>
                        </div>
                        <div class="form-group crud_space col-sm-12">
                            {!! Form::label('ind_gestornocred', 'Estorno de Crédito:', ['class'=>'col-sm-5 control-label input-sm']) !!}
                            <div class="col-sm-1 checkbox">
                                {{ Form::checkbox('ind_gestornocred', 1, null, ['id'=>'ind_gestornocred']) }}
                            </div>
                        </div>
                    @endisset
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseCadastro" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>

                {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro', 'id' => 'btnCadastro']) !!}
                <div id="saveError" class="alert alert-danger alert-dismissable" style="display:none;">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <span class="glyphicon glyphicon-remove"></span>
                    <div id="save_result"></div>
                </div>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>

<div class="modal fade" id="myModalDel" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabel">Remover Registro</h4>
            </div>
            {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmCadastroDel']) }}
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('codigo', 'Código:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-3">
                            <input type="hidden" id="id_del" name="id">
                            {!! Form::text('codigo',null,['class'=>'form-control input-sm', 'id'=>'codigo_del', 'autofocus' => 'true']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-10">
                            <input type="hidden" id="id_del" name="id">
                            {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id'=>'descricao_del']) !!}
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseCadastroDel" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                {!! Form::submit('Remover', ['class' => 'btn btn-nw-registro']) !!}
                <div id="saveErrorDel" class="alert alert-danger alert-dismissable" style="display:none;">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <span class="glyphicon glyphicon-remove"></span>
                    <div id="save_result"></div>
                </div>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>
<script type="text/javascript">
    $(function () {
        $(".modal").on('shown.bs.modal', function () {
            var $self = $(this);
            setTimeout(function () {
                $self.find("#codigo").focus();
            }, 200);
        });
    });
</script>