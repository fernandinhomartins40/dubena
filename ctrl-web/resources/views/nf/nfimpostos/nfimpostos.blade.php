
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
                                @can('create', App\Nfimposto::class)
                                <a href="{{ $fullUrl }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Impostos</h3>
                        </div><!-- /.box-header -->
                        {{ Form::open(['id' => 'searchbar','class'=>'form-horizontal'])}}
                        <div class="panel-body">
                            <div class="form-group crud_space">
                                {{ Form::label('grupofiscalsearch', 'Grupo Fiscal:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                <div class="col-sm-2">
                                    {{ Form::select('grupofiscalsearch',$grupofiscal,null,['id' => 'grupofiscalsearch','class'=>'form-control selectChosen input-sm']) }}
                                </div>
                                {{ Form::label('operacoessearch', 'Operação:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                <div class="col-sm-3">
                                    {{ Form::select('operacoessearch',$operacoes,null,['id' => 'operacoessearch','class'=>'form-control selectChosen input-sm']) }}
                                </div>

                                <div class="col-md-2">
                                    <button class="btn btn-sm btn-nw-buscas" id='btnFiltroImpostos' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                        <span class="fa fa-search fa-lg"></span>
                                    </button>
                                    <a type="button" href="{{ route('nfimposto.index') }}" class="btn btn-sm btn-github" id='btnZeraFiltro' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar">
                                        <span class="fa fa-recycle fa-lg"></span>
                                    </a>
                                </div>
                            </div>
                            {{ Form::close() }}
                            <div class="col-md-12">
                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                    <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Operação</th>
                                            <th>Grupo Fiscal</th>
                                            <th style="width:200px;">Operações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="impostos-list" name="impostos-list">
                                        @if(isset($impostos))
                                        @foreach ($impostos as $imp)
                                        <tr id="imposto{{$imp->id}}">
                                            <td>{{$imp->id}}</td>
                                            <td>{{$imp->nfoperacao->descricao}}</td>
                                            <td>{{$imp->nfgrupofiscal->descricao}}</td>
                                            <td>
                                                <!--{{$redirectUrl = '?index=' . str_replace("&", 'extPar', \Request::fullUrl())}}-->
                                                @can('view', $imp)
                                                <button onclick="window.location.href = '{{route('nfimposto.show',$imp->id) . $redirectUrl}}'" type="button"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                    <span class="fa fa-eye fa-lg"></span>
                                                </button>
                                                @endcan
                                                @can('update', $imp)
                                                <button onclick="window.location.href = '{{route('nfimposto.edit',$imp->id) . $redirectUrl}}'" type="button"
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                    <span class="fa fa-pencil-square-o fa-lg"></span>
                                                </button>
                                                @endcan
                                                @can('delete', $imp)
                                                <button onclick="removeRegister({
                                                    'id':'{{$imp->id}}', 
                                                    'descricao':'{{$imp->nfoperacao->descricao}}'})" type="button"
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
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-5">
                                @can('create', App\Nfimposto::class)
                                <a href="{{ $fullUrl }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->
            @include('general.modal_del')
            <!--Rota para deletar via ajax-->
            <div id='rotaDel' class="hidden">{{url('nfimposto')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{ url('nfimposto') }}</div>
        </div><!-- /.content-wrapper -->
    </div>
</div>
<script type="text/javascript">
    $(document).ready(function () {
        let current = window.location.href;
        let previous = $("#rotaIndex").html();
        if (current && current !== previous)
            $("#rotaIndex").html(current);

        checarFiltro();
    });
    $('#btnFiltroImpostos').click(function () {
        var urlFiltro = root + '/nfimposto?operacoessearch=:opera&grupofiscalsearch=:grupo';
        var grupofiscal = parseInt($("#grupofiscalsearch").val()) ? parseInt($("#grupofiscalsearch").val()).toString() : '0';
        var operacao = parseInt($("#operacoessearch").val()) ? parseInt($("#operacoessearch").val()).toString() : '0';
        if (!isEmpty(operacao) && !isEmpty(grupofiscal)) {
            urlFiltro = urlFiltro.replace(':opera', operacao);
            urlFiltro = urlFiltro.replace(':grupo', grupofiscal);
        } else if (!isEmpty(operacao) || isEmpty(grupofiscal)) {
            urlFiltro = urlFiltro.replace(':opera', operacao);
            urlFiltro = urlFiltro.replace(':grupo', "0");
        } else {
            urlFiltro = urlFiltro.replace(':opera', "0");
            urlFiltro = urlFiltro.replace(':grupo', grupofiscal);
        }
        window.location.href = urlFiltro;
    });
    function checarFiltro() {
        var grupofiscal = $("#grupofiscalsearch").val();
        var operacao = $("#operacoessearch").val();
        $("#btnFiltroImpostos").prop('disabled', isEmpty(grupofiscal) && isEmpty(operacao));
    }
    $("#grupofiscalsearch").change(function () {
        checarFiltro();
    });
    $("#operacoessearch").change(function () {
        checarFiltro();
    });
</script>
@endsection
