
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
                                @can('create', App\Metavenda::class)
                                <button type="button" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModalData">Novo Registro</button>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Metas de Vendas</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                {{ Form::open(['id'=>'fmFiltros', 'class' => 'form-horizontal']) }}
                                {!! Form::label('setor', 'Setor:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;']) !!}
                                <div class="col-md-3 ">
                                    {!! Form::select('setor_id',$setores, $setor,['class'=>'selectChosen', 'id' => 'setor_id']) !!}
                                </div>
                                {!! Form::label('produto', 'Produto:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;']) !!}
                                <div class="col-md-2 ">
                                    {!! Form::hidden('hiddenproduto_id',$produto,['class'=>'form-control input-sm', 'id'=>'hiddenproduto_id']) !!}
                                    {!! Form::select('produto_id', $produtos, '',['class'=>'selectChosen', 'id' => 'produto_id']) !!}
                                </div>
                                {{ Form::label('data', 'Data:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                <div class="col-sm-2">
                                    <div class="input-group generalDateMesAno">
                                        {{ Form::text('data',null,['id' => 'data','class'=>'form-control generalDateMesAno input-sm']) }}
                                        <span class="input-group-addon">
                                            <span class="glyphicon glyphicon-calendar"></span>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-sm btn-nw-buscas" id='btnBusca' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                        <span class="fa fa-search fa-lg"></span>
                                    </button>
                                    <a class="btn btn-sm btn-github" type="button" href="{{route('metavenda.index')}}" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar">
                                        <span class="fa fa-recycle fa-lg"></span>
                                    </a>
                                </div>
                                {!! Form::close() !!}
                                <br />
                                <br />
                                <br />
                                <table id="tblCadastro" btnClick="false" url='' class="table table-bordered table-hover table-condensed dataTable ">
                                    <thead>
                                        <tr>
                                            <th class="hidden">Id</th>
                                            <th>Mês</th>
                                            <th>Produto</th>
                                            <th>Setor</th>
                                            <th>Quantidade</th>
                                            <th>Valor</th>
                                            <th>Operação</th>
                                            <th class="hidden"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="metavenda-list" name="metavenda-list">
                                        @foreach ($metavendas as $metavenda)
                                        <tr id="metavenda->{{$metavenda->id}}">
                                            <!-- {!!$metavenda->valor = requestNumeroDecimalOracle($metavenda->valormeta)!!} -->
                                            <!-- {!!$metavenda->valordesafio = requestNumeroDecimalOracle($metavenda->valordesafio)!!} -->
                                            <!-- {!!$metavenda->valorperfil = requestNumeroDecimalOracle($metavenda->valorperfil)!!} -->
                                            <!-- {!!$metavenda->valorvalegas = requestNumeroDecimalOracle($metavenda->valorvalegas)!!} -->
                                            <!-- {!!$metavenda->valorconvenio = requestNumeroDecimalOracle($metavenda->valorconvenio)!!} -->
                                            <td class="hidden">{{$metavenda->id}}</td>
                                            <td class="active">{{$metavenda->datameta}}</td>
                                            <td>{{$metavenda->produto->descricao}}</td>
                                            <td>{{$metavenda->setor->descricao}}</td>
                                            <td>{{$metavenda->quantidade}}</td>
                                            <td>{{$metavenda->valor}}</td>
                                            <td>
                                                @can('view', $metavenda)
                                                <button id="btnVisualizar"
                                                        onclick="viewRegister({{$metavenda}})"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                    <span class="fa fa-eye fa-lg"></span>
                                                </button>
                                                @endcan
                                                @can('update', $metavenda)
                                                <button id="btnEditar"
                                                        onclick="editRegister({{$metavenda}})"
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                    <span class="fa fa-pencil-square-o fa-lg"></span>
                                                </button>
                                                @endcan
                                            </td>
                                            <td class="hidden">{{json_encode($metavenda)}}</td>
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
                                @can('create', App\Metavenda::class)
                                <button type="button" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModalData">Novo Registro</button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div>

        <!--Rota para um novo cadastro via ajax-->
        <div id='rotaStore' class="hidden">{{route('metavenda.store')}}</div>
        <div id='rotaUpdate' class="hidden">{{url('metavenda')}}/</div>
        <!--Rota para redirecionar via ajax-->
        <div id='rotaIndex' class="hidden">{{route('metavenda.index') . '?' . \Request::getQueryString()}}</div>
        @include('metavendas.partials.metavendas_modal')
        <!-- page script -->
        <script type="text/javascript">
            urlBuscaIndex = '{{ url("metavenda?setor_id=:setor_idproduto_id=:produto_iddata=:data") }}';
        </script>
        <script src="{{URL::to('js/metasVenda.js')}}"></script>
    </div><!-- /.content-wrapper -->
</div>
@endsection
