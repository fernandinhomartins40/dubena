<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog" style="min-width: 55%">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" id="btnXCloseModal" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabel"></h4>
            </div>
            {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmCadastroMetas']) }}
            <div class="modal-body">
                <div class="box-body">
                    <div class="alert alert-danger" id='alertDanger' role="alert">
                        <button type="button" class="close" data-dismiss="alert">x</button>
                        <strong>Erro!</strong> Ocorreu um erro ao cadastrar a meta!
                    </div>
                    <div class="alert alert-informacao" id='alertInfo' role="alert">
                        <button type="button" class="close" data-dismiss="alert">x</button>
                        <strong>Antenção!</strong> Já há uma meta cadastrada para esse mês!
                    </div>
                    <div class="alert alert-success-custom" id='alertSuccess' role="alert">
                        <button type="button" class="close" data-dismiss="alert">x</button>
                        <strong>Sucesso!</strong> Meta cadastrada com sucesso!
                    </div>
                    <div class="form-group crud_space col-sm-12 hidden" id="produtoSetor">
                        {!! Form::label('setor', 'Setor:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-3 ">
                            {!! Form::text('setor',null,['class'=>'form-control input-sm', 'id'=>'setor', 'disabled' => 'disabled']) !!}
                        </div>
                        {!! Form::label('produto', 'Produto:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-3 ">
                            {!! Form::text('produto',null,['class'=>'form-control input-sm', 'id'=>'produto', 'disabled' => 'disabled']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('quantidade', 'Quantidade:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-3 ">
                            {!! Form::text('quantidade',null,['class'=>'form-control input-sm number', 'id'=>'quantidade']) !!}
                        </div>
                        {!! Form::label('valor', 'Valor:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-3 ">
                            {!! Form::text('valor',null,['class'=>'form-control input-sm dinheiro', 'id'=>'valor']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('quantidadedesafio', 'Quantidade Desafio:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-3 ">
                            {!! Form::text('quantidadedesafio',null,['class'=>'form-control input-sm number', 'id'=>'quantidadedesafio']) !!}
                        </div>
                        {!! Form::label('valordesafio', 'Valor Desafio:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-3 ">
                            {!! Form::text('valordesafio',null,['class'=>'form-control input-sm dinheiro', 'id'=>'valordesafio']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('quantidadeperfil', 'Quantidade Perfil:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-3 ">
                            {!! Form::text('quantidadeperfil',null,['class'=>'form-control input-sm number', 'id'=>'quantidadeperfil']) !!}
                        </div>
                        {!! Form::label('valorperfil', 'Valor Perfil:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-3 ">
                            {!! Form::text('valorperfil',null,['class'=>'form-control input-sm dinheiro', 'id'=>'valorperfil']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('quantidadevalegas', 'Quantidade Vale Gás:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-3 ">
                            {!! Form::text('quantidadevalegas',null,['class'=>'form-control input-sm number', 'id'=>'quantidadevalegas']) !!}
                        </div>
                        {!! Form::label('valorvalegas', 'Valor Vale Gás:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-3 ">
                            {!! Form::text('valorvalegas',null,['class'=>'form-control input-sm dinheiro', 'id'=>'valorvalegas']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('quantidadeconvenio', 'Quantidade Convênio:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-3 ">
                            {!! Form::text('quantidadeconvenio',null,['class'=>'form-control input-sm number', 'id'=>'quantidadeconvenio']) !!}
                        </div>
                        {!! Form::label('valorconvenio', 'Valor Convênio:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-3 ">
                            {!! Form::text('valorconvenio',null,['class'=>'form-control input-sm dinheiro', 'id'=>'valorconvenio']) !!}
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-nw-geral" id='btnCancelar' data-dismiss="modal" aria-hidden="true"S>Fechar</button>
                <button type="button" class="btn btn-nw-registro" id='btnFechar'>Salvar e Fechar</button>
                <button type="button" id='btnProximo' class="btn btn-nw-buscas"></button>
                <button type="button" class="btn btn-nw-registro" id='btnGravar'>Gravar</button>
                <div id="saveError" class="alert alert-danger alert-dismissable" style="display:none;">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <span class="glyphicon glyphicon-remove"></span>
                    <div id="save_result"></div>
                </div>
            </div>

            {!! Form::hidden('id',null,['id'=>'id']) !!}
            {!! Form::hidden('mesInicial',null,['id'=>'mesInicial']) !!}
            {!! Form::hidden('mesAtual',null,['id'=>'mesAtual']) !!}
            {!! Form::hidden('formsetor_id',null,['id'=>'formsetor_id']) !!}
            {!! Form::hidden('formproduto_id',null,['id'=>'formproduto_id']) !!}
            {!! Form::close() !!}
        </div>
    </div>
</div>
<div class="modal fade" id="myModalData" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title">Selecione a data inicial.</h4>
            </div>
            <div class="modal-body">
                <div class="form-group crud_space">          
                    <div class="col-sm-2">
                    </div>
                    {!! Form::label('mesInicialMeta', 'Data Inicial:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                    <div class="input-group mesInicialMeta col-sm-3" >
                        {!! Form::text('mesInicialMeta',null,['class'=>'form-control input-sm mesInicialMeta', 'id' => 'mesInicialMeta']) !!}
                        <span class="input-group-addon">
                            <span class="glyphicon glyphicon-calendar"></span>
                        </span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id='btnSelecionarData' class="btn btn-nw-registro">Ok</button>
                    <div id="saveError" class="alert alert-danger alert-dismissable" style="display:none;">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <span class="glyphicon glyphicon-remove"></span>
                        <div id="save_result"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>