@php
    $routeName = \Route::current()->getName();
@endphp

<div class="modal fade" id="modalSenha" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" id="btnTopCloseModalSenha" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabel">Digite a senha mestra para continuar!</h4>
            </div>
            {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmVerificaSenha']) }}
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12">
                        {{-- <input type="hidden" name="_token" value="{{ csrf_token() }}"> --}}
                        {{-- <input type="hidden" id="metodo" name="_method"> --}}
                        <input type="hidden" id="rota" name="rota" value="{{@$routeName}}">
                    </div>
                    <div class="crud_space form-group col-sm-12">
                        {!! Form::label('motivo','Motivo',['class'=>'control-label input-sm col-sm-2']) !!}
                        <div class='col-sm-8'>
                            <input name='motivo' id='motivo' class='form-control input-sm'>
                        </div>
                    </div>
                    <div class="crud_space form-group col-sm-12">
                        {!! Form::label('pass','Senha',['class'=>'control-label input-sm col-sm-2']) !!}
                        <div class='col-sm-8'>
                            <input name='pass' id='pass' type='password' class='form-control input-sm' required="required" autocomplete="off">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="divFooterModalSenha">
                <button type="button" id="btnCloseModalSenhaMestra" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
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


<!--Rota para deletar via ajax-->
<div id='rotaSenha' class="hidden">{{url('empresaconfig/verificasenhamestre')}}</div>
<!--Rota para deletar via ajax-->
<div id='rotaDel' class="hidden">{{url('estoquerequisicao')}}/</div>
<!--Rota para redirecionar via ajax-->
<div id='rotaIndex' class="hidden"></div>
