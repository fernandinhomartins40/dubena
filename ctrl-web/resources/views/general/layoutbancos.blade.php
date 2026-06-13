
@extends('layouts.mainmenu')

@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-6" style="margin-bottom:1%">
                                @can('create', App\Layoutbanco::class)
                                    <button type="button" id="" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Layouts de Cobranças</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                        <div class="col-md-12">
                                <table id="tblCadastro" class="dataTable table table-bordered table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th>C&oacute;d</th>
                                            <th>Descricao</th>
                                            <th>Tipo</th>
                                            <th class="hidden">Mín. Protesto</th>
                                            <th class="hidden">Máx. Protesto</th>
                                            <th class="hidden">Mín. Baixa/Dev</th>
                                            <th class="hidden">Máx. Baixa/Dev</th>
                                            <th class="hidden">Posições Nosso Nº</th>
                                            <th>Cód. Banco</th>
                                            <th>Ativo</th>
                                            <th style="width:11%;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="-list" name="layouts-list">
                                        @foreach ($layouts as $layout)
                                        <tr id="layout{{$layout->id}}">
                                            <td>{{$layout->id}}</td>
                                            <td>{{$layout->descricao}}</td>
                                            <td>{{$layout->cnab == 0 ? 'CNAB 400' : ''}}</td>
                                            <td class="hidden">{{$layout->minimodiasprotesto}}</td>
                                            <td class="hidden">{{$layout->maximodiasprotesto}}</td>
                                            <td class="hidden">{{$layout->minimodiasbaixadevolucao}}</td>
                                            <td class="hidden">{{$layout->maximodiasbaixadevolucao}}</td>
                                            <td class="hidden">{{$layout->boletoposicoesnossonumero}}</td>
                                            <td>{{$layout->codigo_banco}}</td>
                                            <td>{{$layout->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>
                                                @can('view', $layout)
                                                    <button onclick="viewRegister({{$layout}})" 
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('update', $layout)
                                                    <button onclick="editRegister({{$layout}})" 
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                                <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('delete', $layout)
                                                    <button onclick="removeRegister({{$layout}})"  id="btnRemover" 
                                                        class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover">
                                                                <span class="fa fa-trash fa-lg"></span>
                                                    </button>
                                                @endcan
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-5">
                                @can('create', App\Layoutbanco::class)
                                    <button type="button" id="" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->
            <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                            <h4 class="modal-title" id="myModalLabelCadastro"></h4>
                        </div>
                        {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmCadastroAjax']) }}
                        <div class="modal-body">
                            <div class="box-body">
                                <div class="form-group crud_space col-sm-12">
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                    <div class="col-sm-8">
                                        <input type="hidden" id="id" name="id">
                                        <input type="hidden" id="metodo" name="_method">
                                        {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id'=>'descricao']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('cnab', 'Tipo:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                    <div class="col-sm-6">
                                        {!! Form::select('cnab',[0 => 'CNAB 400'], 0, ['class'=>'form-control input-sm selectChosen', 'id'=>'cnab']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('minimodiasprotesto', 'Mín. Dias Protesto:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                    <div class="col-sm-2">
                                        {!! Form::text('minimodiasprotesto',null,['class'=>'form-control input-sm number', 'id'=>'minimodiasprotesto', 'maxlength' => '2']) !!}
                                    </div>
                                    {!! Form::label('maximodiasprotesto', 'Máx. Dias Protesto:', ['class'=>'col-sm-4 control-label input-sm']) !!}
                                    <div class="col-sm-2">
                                        {!! Form::text('maximodiasprotesto',null,['class'=>'form-control input-sm number', 'id'=>'maximodiasprotesto', 'maxlength' => '2']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('minimodiasbaixadevolucao', 'Mín. Dias Baixa/Devolução:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                    <div class="col-sm-2">
                                        {!! Form::text('minimodiasbaixadevolucao',null,['class'=>'form-control input-sm number', 'id'=>'minimodiasbaixadevolucao', 'maxlength' => '2']) !!}
                                    </div>
                                    {!! Form::label('maximodiasbaixadevolucao', 'Máx. Dias Baixa/Devolução:', ['class'=>'col-sm-4 control-label input-sm']) !!}
                                    <div class="col-sm-2">
                                        {!! Form::text('maximodiasbaixadevolucao',null,['class'=>'form-control input-sm number', 'id'=>'maximodiasbaixadevolucao', 'maxlength' => '2']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('boletoposicoesnossonumero', 'Posições Nosso Nº:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                    <div class="col-sm-2">
                                        {!! Form::text('boletoposicoesnossonumero',null,['class'=>'form-control input-sm number', 'id'=>'boletoposicoesnossonumero', 'maxlength' => '2']) !!}
                                    </div>
                                    {!! Form::label('codigo_banco', 'Código do Banco:', ['class'=>'col-sm-4 control-label input-sm']) !!}
                                    <div class="col-sm-2">
                                        {!! Form::text('codigo_banco',null,['class'=>'form-control input-sm number', 'id'=>'codigo_banco', 'maxlength' => '3']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    <label for="ativo" class="col-sm-3 control-label input-sm required">Ativo:</label>
                                    <div class="col-sm-9 checkbox">

                                        {{ Form::checkbox('ativo', 1, null, ['id'=>'ativo']) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="btnCloseCadastro" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                            {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
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
            <!--Rota para um novo cadastro via ajax-->
            <div id='rotaStore' class="hidden">{{route('layoutbancos.store')}}</div>
            <!--Rota para atualizar via ajax-->
            <div id='rotaUpdate' class="hidden">{{url('layoutbancos')}}/</div>
            <!--Rota para deletar via ajax-->
            <div id='rotaDel' class="hidden">{{url('layoutbancos')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{route('layoutbancos.index')}}</div>
            <!-- page script -->
        </div><!-- /.content-wrapper -->
    </div>
</div>
@endsection
