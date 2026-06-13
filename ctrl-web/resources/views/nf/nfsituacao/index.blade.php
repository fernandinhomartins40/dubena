
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
                                @can('create', App\Nfsituacao::class)
                                <button type="button" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Situação de NF</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12 col-md-offset-2 margTop_20">
                                {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmFilter', 'method' => 'GET']) }}
                                <div class="col-md-8">
                                    {{Form::label('codigo_s', 'Código:', ['class' => 'control-label input-sm col-sm-1'])}}
                                    <div class="col-sm-2">
                                        {{Form::text('codigo_s', null, ['class' => 'form-control input-sm number', 'id' => 'codigo_s', 'autofocus'])}}
                                    </div>
                                    {{Form::label('msgerroreceita_s', 'Mensagem Sefaz:', ['class' => 'control-label input-sm col-sm-2'])}}
                                    <div class="col-sm-5">
                                        {{Form::text('msgerroreceita_s', null, ['class' => 'form-control input-sm', 'id' => 'msgerroreceita_s', 'autofocus'])}}
                                    </div>
                                    <div class="col-sm-2">
                                        <button type="submit" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar Rejeição"><span class="fa fa-search fa-lg"></span></button>
                                        <button type="button" onclick="window.location.href = '{{route('nfsituacao.index')}}'" class="btn btn-github btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                    </div>
                                </div>
                                {{Form::close()}}
                            </div>
                            <div class="col-md-12 margTop_20">
                                <table id="tblCadastro" url="" btnClick="false" class="table table-bordered table-hover table-condensed padding-table-5">
                                    <thead>
                                        <tr>
                                            <th class="hidden">Id</th>
                                            <th>C&oacute;digo</th>
                                            <th>Status Sefaz</th>
                                            <th>Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="nfsituacao-list" name="nfsituacao-list">
                                        @foreach ($nfsituacaos as $sit)
                                        <tr id="nfsituacao{{$sit->id}}">
                                            <td class="hidden">{{$sit->id}}</td>
                                            <td>{{$sit->id}}</td>
                                            <!--{{$sit->codigo = $sit->id}}-->
                                            <td>{{$sit->msgerroreceita}}</td>
                                            <td>
                                                @can('view', $sit)
                                                <button onclick="viewRegister({{$sit}})" 
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                    <span class="fa fa-eye fa-lg"></span>
                                                </button>
                                                @endcan
                                                @can('update', $sit)
                                                <button onclick="editRegister({{$sit}}); editing = true;" 
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                    <span class="fa fa-pencil-square-o fa-lg"></span>
                                                </button>
                                                @endcan
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                {{$nfsituacaos->links()}}
                            </div>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->

                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-5">
                                @can('create', App\Nfsituacao::class)
                                <button type="button" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->

            <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                <div class="modal-dialog" style="width: 60%">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                            <h4 class="modal-title" id="myModalLabelCadastro"></h4>
                        </div>
                        {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmCadastroAjax']) }}
                        <div class="modal-body">
                            <div class="box-body">
                                <div class="form-group crud_space col-sm-12">
                                    {{ Form::label('codigo', 'Código:', ['class'=>'col-sm-2 control-label input-sm ']) }}
                                    <div class="col-sm-3">
                                        {{ Form::text('codigo',null,['class'=>'form-control input-sm number', 'id'=>'codigo', 'autofocus' => 'true']) }}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    <input type="hidden" id="id" name="id">
                                    {{ Form::label('msgerroreceita', 'Status Sefaz:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                    <div class="col-sm-10">
                                        <input type="hidden" id="grupo_id" name="grupo_id">
                                        <input type="hidden" id="empresa_id" name="grupo_id">
                                        <input type="hidden" id="metodo" name="_method">
                                        {{ Form::text('msgerroreceita',null,['class'=>'form-control input-sm', 'id'=>'msgerroreceita']) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="btnCloseCadastro" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>

                            {{ Form::submit('Gravar', ['class' => 'btn btn-nw-registro', 'id' => 'btnCadastro']) }}
                        </div>
                        {{ Form::close() }}
                    </div>
                </div>
            </div>
            <meta name="csrf-token" content="{{ csrf_token() }}" />
            <!--Rota para um novo cadastro via ajax-->
            <div id='rotaStore' class="hidden">{{route('nfsituacao.store')}}</div>
            <!--Rota para atualizar via ajax-->
            <div id='rotaUpdate' class="hidden">{{url('nfsituacao')}}/</div>
            <!--Rota para deletar via ajax-->
            <div id='rotaDel' class="hidden">{{url('nfsituacao')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{request()->url() . '?' . request()->getQueryString()}}</div>
            <!--Rota para a linguagem do plugin de paginação-->
            <div id='urlLanguage' class="hidden">{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}</div>

        </div><!-- /.content-wrapper -->
    </div>
</div>
<script type="text/javascript">
    var editing = false;
    $(document).ready(function () {
        adjustPaginate();
    });
    $('#myModal').on('shown.bs.modal', function() {
        var $cod = $("#codigo");
        if (editing) {
            
            $cod.prop('disabled', true);
            $("#msgerroreceita").focus();
        } else {
            $cod.focus();
        }
    });
    $('#myModal').on('hidden.bs.modal', function() {
        editing = false;
    });
</script>
@endsection
