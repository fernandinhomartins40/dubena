
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
                                @can('create', App\Estoquesetoracerto::class)
                                    <a href="{{ Route('estoquesetor.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Ajustes de Estoque</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <div class="col-md-3">
                                    {!! Form::label('dataInicio', 'Data Inicial:', ['class'=>'col-sm-5 control-label input-sm','style'=>'text-align:right;']) !!}
                                    <div class="col-sm-7 input-group generalDatePicker">
                                        {!! Form::text('dataInicio',@$dataInicio,['class'=>'form-control input-sm generalDatePicker', 'id' => 'dataInicio']) !!}
                                        <span class="input-group-addon">
                                            <span class="glyphicon glyphicon-calendar"></span>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    {!! Form::label('dataFim', 'Data Final:', ['class'=>'col-sm-4 control-label input-sm','style'=>'text-align:right;']) !!}
                                    <div class="col-sm-7 input-group generalDatePicker">
                                        {!! Form::text('dataFim',@$dataFim,['class'=>'form-control input-sm generalDatePicker', 'id' => 'dataFim']) !!}
                                        <span class="input-group-addon">
                                            <span class="glyphicon glyphicon-calendar"></span>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <button class="btn btn-sm btn-nw-buscas" id='btnFiltros' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                        <span class="fa fa-search fa-lg"></span>
                                    </button>
                                    <a class="btn btn-sm btn-github" type="button" href="{{route('estoquesetor.index')}}" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar">
                                        <span class="fa fa-recycle fa-lg"></span>
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                    <thead>
                                        <tr>
                                            <th class='hidden'></th>
                                            <th>Data</th>
                                            <th>Descrição Produto</th>
                                            <th>Descrição Setor</th>
                                            <th>Quantidade Antiga</th>
                                            <th>Quantidade Nova</th>
                                            <th>Usuário</th>
                                            <th>Operações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="estoquesetors-list" name="estoquesetor-list">
                                        @foreach ($estoquesetores as $estoquesetor)
                                        <tr id="estoquesetor{{$estoquesetor->id}}">
                                            <td class="hidden">{{$estoquesetor->id}}</td>
                                            <td>{{requestDataOracle($estoquesetor->datahora, false)}}</td>
                                            <td>{{$estoquesetor->produto->descricao}}</td>
                                            <td>{{$estoquesetor->setor->descricao}}</td>
                                            <td>{{$estoquesetor->quantidadeantiga}}</td>
                                            <td>{{$estoquesetor->quantidadenova}}</td>
                                            <td>{{$estoquesetor->user->name}}</td>
                                            <td>
                                                @can('view', $estoquesetor)
                                                <button onclick="window.location.href = '{{route('estoquesetor.show',$estoquesetor->id)}}'" type="button"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                    <span class="fa fa-eye fa-lg"></span>
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
                                @can('create', App\Estoquesetoracerto::class)
                                    <a href="{{ Route('estoquesetor.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.row -->
    </div><!-- /.row -->
</div><!-- /.row -->
@include('general.modal_del')
<!--Rota para deletar via ajax-->
<div id='rotaSenha' class="hidden">{{url('empresaconfig/verificasenhamestre')}}</div>
<!--Rota para deletar via ajax-->
<div id='rotaDel' class="hidden">{{url('estoquesetor')}}/</div>
<!--Rota para redirecionar via ajax-->
<div id='rotaIndex' class="hidden">{{route('estoquesetor.index')}}</div>
<script type="text/javascript">
    $("#btnFiltros").on('click', function() {
        let dataInicio = $("#dataInicio").val();
        let dataFim = $("#dataFim").val();
        let url = root + '/estoquesetor?dataInicio=' + dataInicio + '&dataFim=' + dataFim;
        window.location.href = url;
    });
</script>
@endsection
