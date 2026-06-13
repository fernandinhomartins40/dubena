
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
                                @can('create', App\Spedcontribuicoescredito::class)
                                    <a href="{{ URL::route('spedcreditos.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Sped Contribuições - Créditos</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <div class="box-body">
                                    <div class="row form-horizontal">
                                        <div class="form-group crud_space">
                                            {{ Form::label('registro', 'Registro:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                            <div class="col-sm-2">
                                                {{ Form::select('registro', $registros, null, ['id'=>'registro', 'class' => 'form-control selectChosen']) }}
                                            </div>
                                            <div class="col-sm-2">
                                                <button class="btn btn-sm btn-nw-buscas" id='btnFiltrar' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                                    <span class="fa fa-search fa-lg"></span>
                                                </button>
                                                <button type="button" id='btnLimpar' onclick="window.location.href = '{{route('spedcreditos.index')}}'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar">
                                                    <span class="fa fa-recycle fa-lg"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                            <thead>
                                                <tr>
                                                    <th>C&oacute;digo</th>
                                                    <th>Registro</th>
                                                    <th>Mês de Apuração</th>
                                                    <th>Saldo de Crédito</th>
                                                    <th style="width:200px;">Operação</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @if(isset($creditos) && count($creditos) > 0)
                                                    @foreach($creditos as $credito)
                                                        <tr>
                                                            <td>{{$credito->id}}</td>
                                                            <td>{{$credito->registro}}&nbsp;-&nbsp;{{$credito->registro === "1100" ? "PIS/Pasep" : "Cofins"}}</td>
                                                            <td>{{$credito->descricao}}</td>
                                                            <td>{{requestNumeroDecimalOracle($credito->saldo)}}</td>
                                                            <td>
                                                                @can('view', $credito)
                                                                    <button onclick="window.location.href = '{{route('spedcreditos.show',$credito->id)}}'"
                                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                                            <span class="fa fa-eye fa-lg"></span>
                                                                    </button>
                                                                @endcan
                                                                @can('update', $credito)
                                                                    <button onclick="window.location.href = '{{route('spedcreditos.edit',$credito->id)}}'"
                                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                                                <span class="fa fa-pencil-square-o fa-lg"></span>
                                                                    </button>
                                                                @endcan
                                                                @can('delete', $credito)
                                                                    <button onclick="removeRegister({{$credito}})"
                                                                        id="btnRemover" class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover">
                                                                                <span class="fa fa-trash fa-lg"></span>
                                                                    </button>
                                                                @endcan
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-5">
                                @can('create', App\Spedcontribuicoescredito::class)
                                    <a href="{{ URL::route('spedcreditos.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div><!-- /.row -->
                </div><!-- /.box-header -->
            </div>
        </div>
    </div>
</div>
<!--Rota para deletar via ajax-->
<div id='rotaDel' class="hidden">{{url('spedcreditos')}}/</div>
<!--Rota para redirecionar via ajax-->
<div id='rotaIndex' class="hidden">{{route('spedcreditos.index')}}</div>
@include('general.modal_del')
<script>
    $("#btnFiltrar").click( function() {
        var reg = $("#registro").val();
        if ( !reg.isEmpty() ) {
            var url = root + `/spedcreditos?registro=${reg}`;
            window.top.location.href = url;
        }
    });
</script>
@endsection
