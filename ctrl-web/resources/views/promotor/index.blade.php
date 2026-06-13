
@extends('layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="row">
                        <div class="col-md-12" style="margin-bottom:1%">
                            <div class="col-md-6">
                                @can('create', App\Promotorvenda::class)
                                    <a href="{{ route('promover.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>

                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Promover Vendas</h3>
                        </div>
                        <div class="panel-body">
                            <div class="col-md-12 ">
                                <div class="col-md-12">
                                    <div class="col-md-3">
                                        {{ Form::label('filtro', 'Data Inicial:', ['class'=>'col-sm-4 control-label input-sm','style'=>'text-align:right;']) }}
                                        <div class="col-sm-7 input-group date generalDatePicker">
                                            {{ Form::text('datainicio', null,['class'=>'form-control input-sm', 'id' => 'datainicio']) }}
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-3 p-b-5">
                                        {{ Form::label('filtro', 'Data Final:', ['class'=>'col-sm-4 control-label input-sm','style'=>'text-align:right;']) }}
                                        <div class="col-sm-7 input-group date generalDatePicker">
                                            {{ Form::text('datafim', null,['class'=>'form-control input-sm', 'id' => 'datafim']) }}
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <button class="btn btn-nw-buscas btn-sm" type='button' id='btnFiltro'>Filtrar</button>
                                        <a class="btn btn-nw-registro btn-sm" type='button' href="{{ URL::route('promover.index') }}" id='btnLimpar'>Limpar</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <table id="tblCadastro" class="dataTable table table-bordered table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Usuário</th>
                                            <th>Cliente</th>
                                            <th>Ausente</th>
                                            <th>Rua</th>
                                            <th>Número</th>
                                            <th>Cidade</th>
                                            <th>Data/Hora</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="clientes-list" name="clientes-list">
                                        @foreach ($promotores as $promotor)
                                        <tr>
                                            <td>{{ $promotor->id }}</td>
                                            <td>{{ $promotor->usuario }}</td>
                                            <td>{{ $promotor->cliente }}</td>
                                            <td>{{ $promotor->ausente == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>{{ $promotor->rua }}</td>
                                            <td>{{ $promotor->numero }}</td>
                                            <td>{{ $promotor->cidade }}</td>
                                            <td>{{ $promotor->datahora }}</td>
                                            <td>
                                                <button onclick="window.location.href = '{{route('promover.show',$promotor->id)}}'"
                                                    class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                        <span class="fa fa-eye fa-lg"></span>
                                                </button>
                                                {{-- <button onclick="window.location.href = '{{route('promover.edit',$promotor->id)}}'"
                                                    class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                            <span class="fa fa-pencil-square-o fa-lg"></span>
                                                </button> --}}
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12" style="margin-bottom:1%">
                            <div class="col-md-6">
                                @can('create', App\Promotorvenda::class)
                                    <a href="{{ route('promover.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.content-wrapper -->
</div>

<script>
    $( document ).ready( function () {
        let datainicio = retornarData("datainicio");
        let datafim = retornarData("datafim");
        if ( ! datainicio.isEmpty() ) $("#datainicio").val( datainicio );
        else $("#datainicio").val( moment().format("D/MM/YYYY") );

        if ( ! datafim.isEmpty() ) $("#datafim").val( datafim );
        else $("#datafim").val( moment().format("D/MM/YYYY") );
    });

    $('#btnFiltro').click( function ( e ) {
        if ( $("#datainicio").isEmpty() || $("#datafim").isEmpty() ) {
            e.preventDefault();
            bootbox.alert('Por favor, informe a data inicial e final!');
            return false;
        }
        let datainicio = insertDataOracle($("#datainicio").val());
        let datafim = insertDataOracle($("#datafim").val());

        let url = root + `/promover?datainicio=${ datainicio }&datafim=${ datafim }`;

        window.location.href = url;
    });
</script>

@endsection
