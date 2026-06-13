
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
                                    @can('create', App\Cidade::class)
                                        <button type="button" id="btnNovoCadastro" class="btnNovoCadastro btn btn-nw-registro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
                                    @endcan
                                </div> <!--col-md-6-->
                            </div> <!--col-md-12-->
                        </div><!--row-->
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h3 class="box-title">Cidades</h3>
                            </div><!-- /.box-header -->
                            <div class="panel-body">
                                <div class="col-md-12">
                                    <form action="" class="form-horizontal" method="get">
                                        <div class="form-group crud_space">
                                            {!! Form::label('uf_filtro', 'UF:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::select('uf_filtro', $estados->prepend('Selecione', ''), null,['class'=>'selectChosen', 'id'=>'uf_filtro']) !!}
                                            </div>
                                            {!! Form::label('descricao_filtro', 'Descrição:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('descricao_filtro', null,['class'=>'input-sm form-control', 'id'=>'descricao_filtro']) !!}
                                            </div>
                                            <div class="col-sm-2">
                                                <button type="submit" id="btnImprimirIframe" class="btn btn-sm btn-nw-buscas"
                                                        data-toggle="tooltip" data-trigger="hover" data-placement="bottom" title="Filtar">
                                                    <span class="fa fa-search fa-lg"></span>
                                                </button>
                                                <button type="button" onclick="window.location.href = '{{route('cidade.index')}}'" id="btnImprimirIframe" class="btn btn-sm btn-github"
                                                        data-toggle="tooltip" data-trigger="hover" data-placement="bottom" title="Limpar">
                                                    <span class="fa fa-recycle fa-lg"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                    <table id="tblCadastro" url="" btnClick="false" class="table table-bordered table-hover table-condensed dataTableEndereco">
                                        <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Descri&ccedil;&atilde;o</th>
                                            <th>Uf</th>
                                            <th>Código IBGE</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                        </thead>
                                        <tbody id="-list" name="cidades-list">
                                        @foreach ($cidades as $cidade)
                                            <tr id="cidade{{$cidade->id}}">
                                                <td>{{$cidade->id}}</td>
                                                <td>{{$cidade->descricao}}</td>
                                                <td>{{$cidade->uf}}</td>
                                                <td>{{$cidade->cod_ibge}}</td>
                                                <td>
                                                    @can('view', $cidade)
                                                        <button onclick="viewRegister({{$cidade}})"
                                                                class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                        </button>
                                                    @endcan
                                                    @can('update', $cidade)
                                                        <button onclick="editRegister({{$cidade}})"
                                                                class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                            <span class="fa fa-pencil-square-o fa-lg"></span>
                                                        </button>
                                                    @endcan
                                                    @can('delete', $cidade)
                                                        <button onclick="removeRegister({{$cidade}})"
                                                                id="btnRemover" class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover">
                                                            <span class="fa fa-trash fa-lg"></span>
                                                        </button>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                    {{ $cidades->links() }}
                                </div>
                            </div><!-- /.box-body -->
                        </div><!-- /.box -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="col-md-5">
                                    @can('create', App\Cidade::class)
                                        <button type="button" id="btnNovoCadastro" class="btnNovoCadastro btn btn-nw-registro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
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
                                    <div class="form-group crud_space col-sm-12">
                                        {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                        <div class="col-sm-10">
                                            <input type="hidden" id="id" name="id">
                                            <input type="hidden" id="fromIndex" name="fromIndex" value="1">
                                            <input type="hidden" id="metodo" name="_method">
                                            {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id'=>'descricao']) !!}
                                        </div>
                                    </div>
                                    <div class="form-group crud_space col-sm-12">
                                        {!! Form::label('uf', 'UF:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                        <div class="col-sm-3">
                                            {!! Form::select('uf',$estados, $uf_empresa,['class'=>'selectChosen', 'id'=>'uf']) !!}
                                        </div>
                                        {!! Form::label('cod_ibge', 'Cód. IBGE:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                        <div class="col-sm-5">
                                            {!! Form::text('cod_ibge',null,['class'=>'form-control number input-sm', 'id'=>'cod_ibge']) !!}
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
                <div id='rotaStore' class="hidden">{{route('cidade.store')}}</div>
                <!--Rota para atualizar via ajax-->
                <div id='rotaUpdate' class="hidden">{{url('cidade')}}/</div>
                <!--Rota para deletar via ajax-->
                <div id='rotaDel' class="hidden">{{url('cidade')}}/</div>
                <!--Rota para redirecionar via ajax-->
                <div id='rotaIndex' class="hidden">{{route('cidade.index')}}?{{Request::getQueryString()}}</div>
                <!--Rota para a linguagem do plugin de paginação-->
                <div id='urlLanguage' class="hidden">{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}</div>
            </div><!-- /.content-wrapper -->
        </div>
    </div>
    <script>
        $(document).ready(function () {
            adjustPaginate();
        });
    </script>
@endsection
