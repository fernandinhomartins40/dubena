
@extends('layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="row">
                    <div class="col-md-12" style="margin-bottom:1%">
                        <div class="col-md-6">
                            @can('create', App\Comodato::class)
                                <a href="{{ route('comodato.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                            @endcan
                        </div>
                    </div>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Comodatos</h3>
                    </div>
                    <div class="panel-body">
                        <div class="col-md-12">

                            <table id="tblCadastro" class="dataTable table table-bordered table-hover table-condensed">
                                <thead>
                                    <tr>
                                        <th>C&oacute;digo</th>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Nome/Razão Social</th>
                                        <th>Ativo</th>
                                        <th style="width:200px;">Operação</th>
                                    </tr>
                                </thead>
                                <tbody id="clientes-list" name="clientes-list">
                                    @foreach ($comodatos as $comodato)
                                    <tr>
                                        <td>{{ $comodato->id }}</td>
                                        <td>{{ requestDataOracle($comodato->datacontrato, false) }}</td>
                                        <td>
                                            @if ($comodato->tipo == 0)
                                            Revenda p/ Cliente PJ
                                            @elseif ($comodato->tipo == 1)
                                            Revenda p/ Cliente PF
                                            @else
                                            Distribuidora p/ Revenda
                                            @endif
                                        </td>
                                        <td>{{ $comodato->cliente->nome }}</td>
                                        <td>{{ $comodato->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                        <td>
                                            @can('view', $comodato)
                                                <button onclick="window.location.href = '{{route('comodato.show',$comodato->id)}}'"
                                                    class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                        <span class="fa fa-eye fa-lg"></span>
                                                </button>
                                            @endcan
                                            @can('update', $comodato)
                                                <button onclick="window.location.href = '{{route('comodato.edit',$comodato->id)}}'"
                                                    class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                            <span class="fa fa-pencil-square-o fa-lg"></span>
                                                </button>
                                            @endcan
                                            @if ($comodato->ativo == 1)
                                                <!--<button type="button" url='{!!url("comodato/contrato")!!}' class="btn btn-danger btn-xs" id="btnGerarContrato">Gerar Contrato</button>-->
                                                @can('view', $comodato)
                                                    <button url='{!!url("comodato/contrato")!!}' id="btnGerarContrato" type="button"
                                                        class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar Contrato">
                                                                <span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span>
                                                    </button>
                                                @endcan
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tebody>
                            </table>
                        </div>

                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12" style="margin-bottom:1%">
                        <div class="col-md-6">
                            @can('create', App\Comodato::class)
                                <a href="{{ route('comodato.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                            @endcan
                        </div>
                    </div>
                </div>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.content-wrapper -->
</div>
{{Form::hidden('contrato',null,['id' => 'contrato'])}}
<script src="{{URL::to('js/comodato.js')}}"></script>
<script>
    @if (isset($_GET['cod']))
    if (performance.navigation.type != 1) {
        if($("#contrato").val() === '') {
            $("#contrato").val('true');
            bootbox.confirm({
                title: "Confirmação",
                message: "Deseja imprimir o contrato do comodato?",
                buttons: {
                    cancel: {
                        label: "Não",
                        className: "btn-nw-geral pull-center"
                    },
                    confirm: {
                        label: "Sim",
                        className: "btn-nw-registro pull-center"
                    }
                },
                callback: function (result) {
                    var id = '{{$_GET["cod"]}}';
                    if (result) {
                        var url = '{!!url("comodato/contrato/:id")!!}';
                        url = url.replace(':id', id);
                        window.open(url, '_blank');
                    }
                }
            });
        } 
    }
    @endif
</script>
@endsection
