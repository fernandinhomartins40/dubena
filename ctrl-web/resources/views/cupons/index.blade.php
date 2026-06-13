
@extends('layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="row">
                    <div class="col-md-12" style="margin-bottom:1%">
                        <div class="col-md-6">
                            @can('create', App\Cupom::class)
                                <a href="{{ route('cupons.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                            @endcan
                        </div>
                    </div>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Cupons aplicativo gás em casa</h3>
                    </div>
                    <div class="panel-body">
                        <div class="col-md-12">

                            <table id="tblCadastro" class="dataTable table table-bordered table-hover table-condensed">
                                <thead>
                                    <tr>
                                        <th>C&oacute;digo</th>
                                        <th>Data inicial</th>
                                        <th>Data final</th>
                                        <th>Tipo</th>
                                        <th>Valor</th>
                                        <th>Cupom</th>
                                        <th>Limite de uso</th>
                                        <th>Ativo</th>
                                        <th style="width:200px;">Operação</th>
                                    </tr>
                                </thead>
                                <tbody id="clientes-list" name="clientes-list">
                                    @foreach ($cupons as $cupom)
                                    <tr>
                                        <td>{{ $cupom->id }}</td>
                                        <td>{{ requestDataOracle($cupom->datainicio, false) }}</td>
                                        <td>{{ requestDataOracle($cupom->datafim, false) }}</td>
                                        <td>
                                            @if ($cupom->tipo == 0)
                                            Valor
                                            @else
                                                Percentual
                                            @endif
                                        </td>
                                        <td>
                                            @if ($cupom->tipo == 0)
                                                {{ requestNumeroDecimalOracle($cupom->valor) }}
                                            @else
                                                {{ requestPercentualOracleSemDigitos($cupom->valor) }}
                                            @endif
                                        </td>
                                        <td>{{ $cupom->codigo }}</td>
                                        <td>{{ $cupom->limiteuso }}</td>
                                        <td>{{ $cupom->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                        <td>
                                            <button onclick="window.location.href = '{{route('cupons.show',$cupom->id)}}'"
                                                class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                    <span class="fa fa-eye fa-lg"></span>
                                            </button>
                                            <button onclick="window.location.href = '{{route('cupons.edit',$cupom->id)}}'"
                                                class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                        <span class="fa fa-pencil-square-o fa-lg"></span>
                                            </button>
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
                            @can('create', App\Cupom::class)
                                <a href="{{ route('cupons.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                            @endcan
                        </div>
                    </div>
                </div>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.content-wrapper -->
</div>
{{Form::hidden('contrato',null,['id' => 'contrato'])}}

<script src="{{URL::to('js/cupom.js')}}"></script>

<script>
    @if (isset($_GET['cod']))
    if (performance.navigation.type != 1) {
        if($("#contrato").val() === '') {
            $("#contrato").val('true');
            bootbox.confirm({
                title: "Confirmação",
                message: "Deseja imprimir o contrato do cupom?",
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
                        var url = '{!!url("cupom/contrato/:id")!!}';
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
