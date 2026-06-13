
@extends('layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="row">
                    <div class="col-md-12" style="margin-bottom:1%">
                        <div class="col-md-6">
                            <a href="{{ route('descontocheque.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                        </div>
                    </div>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Descontos de Cheques</h3>
                    </div>
                    <div class="panel-body">
                        <div class="col-md-12">

                            <table id="tblCadastro" url="{{URL::to('descontocheque/:id')}}" urlupdate="{{route('descontocheque.edit', ':id')}}" btnClick="false" class="dataTable table table-bordered table-hover table-condensed">
                                <thead>
                                    <tr>
                                        <th>C&oacute;digo</th>
                                        <th>Tipo</th>
                                        <th>Nome/Razão Social</th>
                                        <th>Ativo</th>
                                        <th style="width:200px;">Operação</th>
                                    </tr>
                                </thead>
                                <tbody id="clientes-list" name="clientes-list">
                                    @foreach ($descontoscheques as $descontocheque)
                                    <tr>
                                        <td>{{ $descontocheque->id }}</td>
                                        <td>
                                            @if ($descontocheque->tipo == 0)
                                            Distribuidora p/ Cliente PJ
                                            @elseif ($descontocheque->tipo == 1)
                                            Distribuidora p/ Cliente PF
                                            @else
                                            Fornecedor p/ Distribuidora
                                            @endif
                                        </td>
                                        <td>{{ $descontocheque->cliente->nome }}</td>
                                        <td>{{ $descontocheque->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                        <td>
                                            <button type="button" class='btn btn-nw-geral btn-xs ' id="btnEditar">Editar</button>
                                            @if ($descontocheque->ativo == 1)
                                            <button type="button" url='{!!url("descontocheque/contrato")!!}' class="btn btn-danger btn-xs" id="btnGerarContrato">Gerar Contrato</button>
                                            @endif
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
                            <a href="{{ route('descontocheque.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                        </div>
                    </div>
                </div>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.content-wrapper -->
</div>
{{Form::hidden('contrato',null,['id' => 'contrato'])}}
<script src="{{URL::to('js/descontocheque.js')}}"></script>
<script>
    @if (isset($_GET['cod']))
    if (performance.navigation.type != 1) {
        if($("#contrato").val() === '') {
            $("#contrato").val('true');
            bootbox.confirm({
                title: "Confirmação",
                message: "Deseja imprimir o contrato do descontocheque?",
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
                        var url = '{!!url("descontocheque/contrato/:id")!!}';
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
