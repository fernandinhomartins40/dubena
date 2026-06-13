
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
                                    <button type="button" id="btnNovo" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" onclick="novoCadastro();" data-target="#myModal">Novo Registro</button>
                                @can('create', App\Ocorrenciasremessas::class)
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Ocorrências de Remessas</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmFiltro', 'method' => 'GET']) }}
                                    <div class="form-group crud_space col-sm-9">
                                        {!! Form::label('codigo_banco_search', 'Código Banco:', ['class'=>'col-sm-5 control-label input-sm']) !!}
                                        <div class="col-sm-2">
                                            {!! Form::text('codigo_banco_search',null,['class'=>'form-control input-sm', 'id'=>'codigo_banco_search']) !!}
                                        </div>
                                        {!! Form::label('tipo_search', 'Tipo:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                        <div class="col-sm-2">
                                            {!! Form::select('tipo_search',$tipo, null,['class'=>'selectChosen', 'id'=>'tipo_search']) !!}
                                        </div>
                                        <button type="button" id='btnLimpar' class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                        <button id="btnFiltro" type="submit" type='button' class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-search fa-lg"></span></button>
                                    </div>
                                {{Form::close()}}
                                <table id="tblCadastro" url="" btnClick="false" class="table table-bordered table-hover table-condensed dataTable">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>C&oacute;digo</th>
                                            <th>C&oacute;digo Banco</th>
                                            <th>Descri&ccedil;&atilde;o</th>
                                            <th>Tipo</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="-list" name="ocorrenciasremessas-list">
                                        @foreach ($ocorrenciasremessas as $ocorrenciasremessa)
                                        <tr id="ocorrenciasremessa{{$ocorrenciasremessa->id}}">
                                            <td>{{$ocorrenciasremessa->id}}</td>
                                            <td>{{$ocorrenciasremessa->codigo}}</td>
                                            <td>{{$ocorrenciasremessa->codigo_banco}}</td>
                                            <td>{{$ocorrenciasremessa->descricao}}</td>
                                            <td>{{$ocorrenciasremessa->tipo == 2 ? 'Pré-crítica' : ($ocorrenciasremessa->tipo == 0 ? 'Remessa' : 'Retorno') }}</td>
                                            <td>
                                                    <button onclick="viewRegister({{$ocorrenciasremessa}})" 
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @can('view', $ocorrenciasremessa)
                                                @endcan
                                                    <button onclick="editRegister({{$ocorrenciasremessa}})" 
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                            <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @can('update', $ocorrenciasremessa)
                                                @endcan
                                                    <button onclick="removeRegister({{$ocorrenciasremessa}})"
                                                        id="btnRemover" class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover">
                                                            <span class="fa fa-trash fa-lg"></span>
                                                    </button>
                                                @can('delete', $ocorrenciasremessa)
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
                                    <button type="button" id="btnNovo" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" onclick="novoCadastro();" data-target="#myModal">Novo Registro</button>
                                @can('create', App\Ocorrenciasremessas::class)
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->
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
                                </div>
                                <input type="hidden" id="id" name="id">
                                <input type="hidden" id="metodo" name="_method">
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('codigo', 'Código:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                    <div class="col-sm-4">
                                        {!! Form::text('codigo',null,['class'=>'form-control input-sm', 'id'=>'codigo']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                    <div class="col-sm-10">
                                        {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id'=>'descricao']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('tipo', 'Tipo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                    <div class="col-sm-4">
                                        {!! Form::select('tipo',$tipo, null,['class'=>'selectChosen', 'id'=>'tipo']) !!}
                                    </div>
                                    {!! Form::label('codigo_banco', 'Cód. Banco:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                    <div class="col-sm-4">
                                        {!! Form::text('codigo_banco',null,['class'=>'form-control input-sm', 'id'=>'codigo_banco']) !!}
                                    </div>
                                    {!! Form::label('uso_banco', 'Uso Banco:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                    <div class="col-sm-3 checkbox">
                                        {!! Form::checkbox('uso_banco') !!}
                                    </div>
                                    {!! Form::label('allowed_user', 'Aparece na Tela:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                    <div class="col-sm-4 checkbox">
                                        {!! Form::checkbox('allowed_user') !!}
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
    
            @include('general.modal_del')
                <!--Rota para um novo cadastro via ajax-->
            <div id='rotaStore' class="hidden">{{route('ocorrenciasremessas.store')}}</div>
            <!--Rota para atualizar via ajax-->
            <div id='rotaUpdate' class="hidden">{{url('ocorrenciasremessas')}}/</div>
            <!--Rota para deletar via ajax-->
            <div id='rotaDel' class="hidden">{{url('ocorrenciasremessas')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{request()->url() . '?' . request()->getQueryString()}}</div>

            <!-- page script -->
            <script type="text/javascript">
                $(document).ready(function () {
                    $("#fmFiltro").on('submit', function () {
                        if($("#codigo_banco_search").val().length == 0){
                            bootbox.alert('O código do banco é obrigatório!');
                            return false;
                        }     
                    });
                    $("#btnLimpar").on('click', function () {
                        $(".selectChosen").val('').trigger('chosen:updated');
                        $("#codigo_banco_search").val('');
                    });
                });
            </script>
        </div><!-- /.content-wrapper -->
    </div>
    @endsection