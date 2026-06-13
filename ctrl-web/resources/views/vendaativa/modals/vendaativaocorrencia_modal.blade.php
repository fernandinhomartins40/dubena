<!-- Modal -->
<div class="modal fade" id="ocorrencia_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document" style="width:50%">
        <div class="modal-content">
            {{ Form::open(['id'=>'gerarOcorrencia','route'=> 'vendaativa.ocorrencia', 'class' => 'form-horizontal', 'files' => true]) }}
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                    <h4 class="modal-title">Gerar Ocorrência</h4>
                </div>
                <div class="modal-body col-md-12">
                    <div class="form-group crud_space">
                        {{ Form::label('cliente_id', 'Cód. Cliente:', ['class'=>'col-sm-3 control-label input-sm']) }}
                        <div class="col-sm-2">
                            {{ Form::text('cliente_id', null, ['id'=>'cliente_id', 'class' => 'form-control input-sm','readonly']) }}
                        </div>
                        {{ Form::label('cliente_nome', 'Cliente:', ['class'=>'col-sm-2 control-label input-sm']) }}
                        <div class="col-sm-4">
                            {{ Form::text('cliente_nome', null, ['id'=>'cliente_nome', 'class' => 'form-control input-sm','readonly']) }}
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        {{ Form::label('user_id', 'Cód. Colaborador:', ['class'=>'col-sm-3 control-label input-sm']) }}
                        <div class="col-sm-2">
                            {{ Form::text('user_id', null, ['id'=>'user_id', 'class' => 'form-control input-sm','readonly']) }}
                        </div>
                        {{ Form::label('colaborador_nome', 'Colaborador:', ['class'=>'col-sm-2 control-label input-sm']) }}
                        <div class="col-sm-4">
                            {{ Form::text('colaborador_nome', null, ['id'=>'colaborador_nome', 'class' => 'form-control input-sm','readonly']) }}
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        {!! Form::label('datahora', 'Data/Hora:', ['class'=>'col-sm-3 control-label input-sm','style'=>'text-align:right;']) !!}
                        <div class="col-sm-4">
                            <div class="input-group date generalDateTimePicker" id="datetimepicker1">
                                {!! Form::datetime('datahora',null,['id'=>'datahora','class'=>'form-control input-sm generalDateTimePicker','readonly']) !!}
                                <span class="input-group-addon">
                                    <span class="glyphicon glyphicon-calendar"></span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        {{ Form::label('vendaativaocorrenciatipo_id', 'Ocorrência:', ['class'=>'col-sm-3 control-label input-sm']) }}
                        <div class="col-sm-4">
                            {{ Form::select('vendaativaocorrenciatipo_id', $ocorrencias, null, ['id'=>'vendaativaocorrenciatipo_id', 'class' => 'form-control selectChosen']) }}
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        {{ Form::label('observacao', 'Observações:', ['class'=>'col-sm-3 control-label input-sm']) }}
                        <div class="col-sm-6">
                            {{ Form::textarea('observacao', null, ['id' => 'observacao', 'class'=>'form-control input-sm', 'rows' => '3','style'=>'resize:vertical;']) }}
                            {{ Form::hidden('vendaativa_id', @$id, ['id' => 'vendaativa_id']) }}
                            {{ Form::hidden('novamente_ocorrencia', null, ['id' => 'novamente_ocorrencia']) }}
                        </div>
                    </div>
                </div>
            <div class="modal-footer">
                {!! Form::submit('Gravar', ['id'=>'btngravarocorrencia','class' => 'btn btn-nw-registro'])!!}
                <button id="btnvoltarmod" type="button" class="btn btn-nw-geral" data-dismiss="modal">Voltar</button>
            </div>
        </div>
        {{Form::close()}}
    </div>
</div>

<script type="text/javascript">
$("#btngravarocorrencia").click(function(e){
    var tblfiltrospedido = tblPedidosFiltros;
    var ocorrencia = $("#vendaativaocorrenciatipo_id").val();
    if(ocorrencia == ""){
        e.preventDefault();
        bootbox.alert('Por favor, escolha uma ocorrência.');
    }
    saidaPagina(tblfiltrospedido,"1");
});
</script>