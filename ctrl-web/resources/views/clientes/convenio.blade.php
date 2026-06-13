<div class="col-md-12">

    <div class="header panel-default">
    </div><!-- /.box-header -->
    <ul class="nav nav-tabs subtabs">
        <li class="active subtab_1"><a href="#subtab_1" onclick="showConvenioEmpresa();" data-toggle="tab">Empresa Conveniada</a></li>
        <li class="subtab_2"><a href="#subtab_2" onclick="hideConvenioEmpresa()" data-toggle="tab">Colaborador de Convenio</a></li>
    </ul>
    <div class="tab-content">
        <div class="active tab-pane subtab_1" id="subtab_1">
            <div class="row">
                <div id="tabCadastro" class="col-md-12">
                    <div class="box-body margTop_15">
                        <div class="form-group crud_space">
                            <div class="convenioativo">
                                {!! Form::label('convenioativo', 'Habilitar Convênio:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                <div class="col-md-1 checkbox">
                                    {{ Form::checkbox('convenioativo') }}
                                </div>
                            </div>
                        </div>
                        <div class="form-group crud_space">
                            {!! Form::label('datacontrato', 'Data Contrato:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                            <div class="col-sm-2">
                                <div class="input-group generalDatePickerDefaultDateFalse">
                                    @if(isset($cliente->clienteConvenio->datacontrato))
                                    {!! Form::text('datacontrato',date('d/m/Y', strtotime(@$cliente->clienteConvenio->datacontrato)),['class'=>'form-control generalDatePickerDefaultDateFalse']) !!}
                                    @else
                                    {!! Form::text('datacontrato',null,['class'=>'form-control generalDatePickerDefaultDateFalse']) !!}
                                    @endif
                                    <span class="input-group-addon">
                                        <i class="glyphicon glyphicon-calendar"></i>
                                    </span>
                                </div>
                            </div>
                            {!! Form::label('limitecompra', 'Limite Compra:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                            <div class="col-md-1">
                                {!! Form::text('limitecompra',@$cliente->clienteConvenio->limitecompra,['class'=>'form-control']) !!}
                            </div>
                            {!! Form::label('comissao', 'Desconto:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                            <div class="col-md-1">
                                {!! Form::text('comissao',@$cliente->clienteConvenio->comissao,['class'=>'form-control percentagem']) !!}
                            </div>
                            {!! Form::label('comissaodestino', 'Desconto Para:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                            <div class="col-md-2">
                                @if(@$cliente->clienteConvenio->comissaodestino == 1)
                                    {!! Form::select('comissaodestino',["1"=>"Conveniado","2"=>"Empresa"], 1, ['class' => 'form-control selectChosen']) !!}
                                @elseif(@$cliente->clienteConvenio->comissaodestino == 2)
                                    {!! Form::select('comissaodestino',["1"=>"Conveniado","2"=>"Empresa"], 2, ['class' => 'form-control selectChosen']) !!}
                                @else
                                    {!! Form::select('comissaodestino',[1=>"Conveniado",2=>"Empresa"], null, ['class' => 'form-control selectChosen']) !!}
                                @endif
                            </div>
                        </div>
                        <div class="form-group crud_space">
                            {!! Form::label('diafechamento', 'Dia Fechamento:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                            <div class="col-md-1">
                                {!! Form::number('diafechamento',@$cliente->clienteConvenio->diafechamento,['class'=>'form-control number days', 'min'=>'0', 'max'=>'31']) !!}
                            </div>
                            {!! Form::label('diavencimento', 'Dia Vencimento:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                            <div class="col-md-1">
                                {!! Form::number('diavencimento',@$cliente->clienteConvenio->diavencimento,['class'=>'form-control number days', 'min'=>'0', 'max'=>'31']) !!}
                            </div>
                            <div class="col-md-1 col-md-offset-1">
                                <button type="button" class="btn btn-nw-buscas btn-sm" id="btnChangeProdConvenio">Produtos</button>
                            </div>
                        </div>
                        @include('clientes.modal.modal_produtos_convenio')
                        <div class="form-group crud_space margTop_15">
                            <div class="col-md-10 col-md-offset-2">
                                <strong>Representante Legal</strong>
                            </div>
                        </div>
                        <div class="form-group crud_space">
                            {!! Form::label('nomerepresentante', 'Nome:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                            <div class="col-md-3">
                                {!! Form::text('nomerepresentante',@$cliente->clienteConvenio->nomerepresentante,['class'=>'form-control', 'id' => 'nomerepresentante']) !!}
                            </div>
                            {!! Form::label('cpfrepresentante', 'CPF:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                            <div class="col-md-2">
                                {!! Form::text('cpfrepresentante',@$cliente->clienteConvenio->cpfrepresentante,['class'=>'form-control cpf', 'id' => 'cpfrepresentante']) !!}
                            </div>
                            {!! Form::label('rgrepresentante', 'RG:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                            <div class="col-md-2">
                                {!! Form::text('rgrepresentante',@$cliente->clienteConvenio->rgrepresentante,['class'=>'form-control rg', 'id' => 'rgrepresentante']) !!}
                            </div>
                        </div>

                        <div class="form-group crud_space">
                            <div class="col-sm-2"></div>
                            <div class="col-sm-8">
                                @if(isset($cliente) and isset($show))
                                <a href="{{URL::to('cliente/contrato/'.$cliente->id)}}" target="_blank" type="button" id="btnPdf" class="btn btn-danger">
                                    Gerar Contrato
                                </a>
                                <button type="button" id='btnGerarContrato' class="btn btn-nw-buscas" data-toggle="modal" data-target="#modalChangeProdutosConvenio">
                                    Gerar Etiquetas
                                </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!------------------------------------>
        <div class="tab-pane subtab_2" id="subtab_2">
            <div class="row">
                <div id="tabCadastro" class="col-md-12">
                    <div class="box-body margTop_15">
                        <div class="form-group crud_space">
                            <div class="conveniado">
                                {!! Form::label('convenio', 'Conveniado:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                <div class="col-md-1 checkbox">
                                    {{ Form::checkbox('convenio') }}
                                </div>
                            </div>
                        </div>
                        <div class="form-group crud_space">
                            {!! Form::label('convenio_id', 'Empresa:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                            <div class="col-md-4">
                                {!! Form::select('convenio_id',$empresaConveniada, null, ['class' => 'form-control selectChosen']) !!}
                            </div>
                            {!! Form::label('conveniolimite', 'Limite:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                            <div class="col-md-1">
                                {!! Form::text('conveniolimite',null,['class'=>'form-control number', 'id'=>'conveniolimite']) !!}
                            </div>
                        </div>
                        <div class="form-group crud_space">
                            {!! Form::label('codigo_convenio', 'Cód. no Convênio:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                            <div class="col-md-2">
                                {!! Form::text('codigo_convenio',null,['class'=>'form-control', 'id'=>'codigo_convenio']) !!}
                            </div>
                        </div>
                        <div class="form-group crud_space margTop_15">
                            <div class="col-md-10 col-md-offset-2">
                                <strong>Parentesco</strong>
                            </div>
                        </div>
                        <div class="form-group crud_space">
                            {!! Form::label('nomeClienteParentesco', 'Nome:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                            <div class="col-md-3">
                                {!! Form::text('nomeClienteParentesco',null,['class'=>'form-control input-sm', 'id' => 'nomeClienteParentesco']) !!}
                            </div>
                            {!! Form::label('parentesco_id', 'Parentesco:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                            <div class="col-md-2">
                                {!! Form::select('parentesco_id',$parentesco,null,['class'=>'form-control selectChosen', 'id' => 'parentesco_id', 'style'=>'padding:0px;max-height:24px;border-radius: 5px ! important;']) !!}
                            </div>
                            {!! Form::label('parentescoAtivo', 'Ativo:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                            <div class="col-md-1 checkbox">
                                {{ Form::checkbox('parentescoAtivo') }}
                            </div>
                            <div class="col-md-1 checkbox">
                                <button disabled id="btnAddParentesco" type="button" class="btn btn-nw-buscas btn-xs">Adicionar</button>
                            </div>
                        </div>
                        <div class="col-md-6 col-md-push-3">
                            {!! Form::hidden('parentesco',null,['id' => 'parentesco']) !!}
                            <table id="tblParentesco" class="table table-bordered table-hover table-condensed">
                                <thead>
                                    <tr>
                                        <th>codigo</th>
                                        <th>Nome</th>
                                        <th class="hidden">Parentesco id</th>
                                        <th>Parentesco</th>
                                        <th>Ativo</th>
                                        <th>Operação</th>
                                    </tr>
                                </thead>
                                <tbody id="clientes-list" name="clientes-list">
                                    @if(isset($cliente->clienteConvenioDependete))
                                    @foreach ($cliente->clienteConvenioDependete as $dependente)
                                    <tr id="">
                                        <td>{{ $dependente->id }}</td>
                                        <td>{{ $dependente->nome }}</td>
                                        <td class="hidden">{{ $dependente->parentesco_id }}</td>
                                        <td>{{ $dependente->parentesco->descricao }}</td>
                                        <td>{{ $dependente->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                        <td><button id="btnRemoverParentesco" type="button" class="btn btn-nw-registro btn-xs">Remover</button></td>
                                    </tr>
                                    @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
