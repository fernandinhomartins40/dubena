<!-- Modal -->
<div class="modal fade" id="modalClientePromocoes" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-xl" style="max-width: 96%; ">
        <div class="modal-content">

            <div class="modal-header">
                <button type="button" class="close btnClosePromocao"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title">Promoções</h4>
            </div>

            <div class="modal-body col-md-12">
                <div class="form-group crud_space">
                    {!! Form::label('promocao_id', 'Promoção:', ['class' => 'control-label col-md-1 input-sm'])!!}
                    <div class="col-md-4">
                        {!! Form::select('promocao_id',$promocoes, null,['class'=>'form-control input-sm selectChosen','onchange'=>'enableDisableFieldsPromo']) !!}
                    </div>
                </div>
                <div class="form-group crud_space margTop_15">
                    {!! Form::label('datainicio', 'Data Início:', ['class' => 'control-label col-md-1 input-sm'])!!}
                    <div class="col-md-2">
                        <div class="input-group datePickerCliente">
                            {!! Form::text('datainicio',null,['class'=>'form-control input-sm', 'onkeyup'=>'enableDisableFieldsPromo', 'onchange'=>'enableDisableFieldsPromo', 'id' => 'datainicio']) !!}
                            <span class="input-group-addon">
                                <i class="glyphicon glyphicon-calendar"></i>
                            </span>
                        </div>
                    </div>
                    {!! Form::label('datafim', 'Data Fim:', ['class' => 'control-label col-md-1 input-sm'])!!}
                    <div class="col-md-2">
                        <div class="input-group datePickerCliente">
                            {!! Form::text('datafim',null,['class'=>'form-control input-sm', 'onkeyup'=>'enableDisableFieldsPromo', 'onchange'=>'enableDisableFieldsPromo', 'id' => 'datafim']) !!}
                            <span class="input-group-addon">
                                <i class="glyphicon glyphicon-calendar"></i>
                            </span>
                        </div>
                    </div>
                    {!! Form::label('mediadias', 'Média de Consumo Diário:', ['class' => 'control-label col-md-2 input-sm'])!!}
                    <div class="col-md-2">
                        {!! Form::text('mediadias',null,['class'=>'form-control input-sm number', 'onkeyup'=>'enableDisableFieldsPromo', 'onchange'=>'enableDisableFieldsPromo']) !!}
                    </div>
                    <div class="col-md-1">
                        <button disabled='true' id="btnAddPromocao" type="button" class="btn btn-xs btn-nw-buscas">
                            Adicionar
                        </button>
                    </div>
                </div>

                <div class="col-md-12">
                    <table id="tblClientePromocoes" class="table bordered table-condensed">
                        <thead>
                            <tr>
                                <th>codigo</th>
                                <th>Cód. Promoção</th>
                                <th>Descrição</th>
                                <th>Data Inicio</th>
                                <th>Data Fim</th>
                                <th>Média Consumo Diário</th>
                                <th>Operações</th>
                            </tr>
                        </thead>

                        <tbody>
                            @if(isset($cliente->clientePromocao))
                            @foreach ($cliente->clientePromocao as $promocao)
                            <tr>
                                <td>{{$promocao->id}}</td>
                                <td>{{ $promocao->promocao->id }}</td>
                                <td>{{ $promocao->promocao->descricao }}</td>
                                <td>{{ requestDataOracle($promocao->datainicio, false) }}</td>
                                <td>{{ requestDataOracle($promocao->datafim, false)}}</td>
                                <td>{{ $promocao->mediadias}}</td>
                                <td><button id="removerPromocao" type="button" class="btn btn-nw-registro btn-xs">Remover</button></td>
                            </tr>
                            @endforeach
                            @endif
                        </tbody>

                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-nw-geral btnClosePromocao">Fechar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="popup_contato" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <button type="button" class="close btnCloseContato"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title">Adicionar Interação</h4>
            </div>

            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('contatotipo_id', 'Tipo:', ['class'=>'col-sm-3 control-label input-sm','style'=>'text-align:right;']) !!}
                        <div class="col-sm-7">
                            {!! Form::select('contatotipo_id', $contatotipos, null, ['class' => 'form-control input-sm input-sm selectChosen', 'style'=>'border-radius: 5px ! important;', 'id' => 'contatotipo_id']) !!}
                        </div>
                    </div>

                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('datacontato', 'Data:', ['class'=>'col-sm-3 control-label input-sm','style'=>'text-align:right;']) !!}
                        <div class="col-sm-4">
                            <div class="input-group generalDatePicker">
                                {!! Form::text('datacontato',null,['class'=>'form-control input-sm input-sm generalDatePicker']) !!}
                                <span class="input-group-addon">
                                    <span class="glyphicon glyphicon-calendar"></span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('descricaocontato', 'Descrição:', ['class'=>'col-sm-3 control-label input-sm','style'=>'text-align:right;']) !!}
                        <div class="col-sm-9">
                            {!! Form::textarea('descricaocontato',null,['rows'=>'4', 'class'=>'form-control input-sm input-sm', 'id'=>'descricaocontato']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('acaocontato', 'Ação:', ['class'=>'col-sm-3 control-label input-sm','style'=>'text-align:right;']) !!}
                        <div class="col-sm-9">
                            {!! Form::textarea('acaocontato',null,['rows'=>'4', 'class'=>'form-control input-sm input-sm', 'id'=>'acaocontato']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('contatosituacao_id', 'Situação:', ['class'=>'col-sm-3 control-label input-sm','style'=>'text-align:right;']) !!}
                        <div class="col-sm-7">
                            {!! Form::select('contatosituacao_id', $contatosituacoes, null, ['class' => 'form-control input-sm input-sm selectChosen', 'style'=>'border-radius: 5px ! important;', 'id' => 'contatosituacao_id']) !!}
                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseContato" class="btn btn-nw-geral btnCloseContato">Fechar</button>
                <button type="button" id="btnGravarContato" class="btn btn-nw-registro" onclick="addContato();">Gravar</button>
            </div>
        </div>
    </div>
</div>