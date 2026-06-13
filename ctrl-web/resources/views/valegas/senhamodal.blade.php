<div class="modal fade" id="modalSenha" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabel">Digite a senha mestre para cancelar essa requisição!</h4>
            </div>
            {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmVerificaSenha']) }}
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <input type="hidden" id="metodo" name="_method">
                    </div>
                    <div class="crud_space form-group col-sm-12">
                        {!! Form::label('pass','Senha',['class'=>'control-label input-sm col-sm-3']) !!}
                        <div class='col-sm-8'>
                            <input name='pass' id='pass' type='password' class='form-control input-sm' required="required">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseCadastroDel" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                {!! Form::submit('Confirmar', ['class' => 'btn btn-nw-registro']) !!}
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