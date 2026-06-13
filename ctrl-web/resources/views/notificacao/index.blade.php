
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
                                @can('create', App\Appnotification::class)
                                    <a href="{{ URL::route('appnotification.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Notificações aplicativo Gás em Casa</h3>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-sm-4">
                                    {{ Form::label('tipo', 'Tipo Layout:', ['class'=>'col-sm-4 control-label input-sm']) }}
                                    <div class="col-sm-8">
                                        {{ Form::select('tipo', @$tipos, null,['id' => 'tipo', 'class'=>'form-control input-sm selectChosen']) }}
                                    </div>
                                </div>

                                <div class="col-sm-4">
                                    <button class="btn btn-sm btn-nw-buscas" id='btnConsultarNotis' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                        <span class="fa fa-search fa-lg"></span>
                                    </button>
                                    <a class="btn btn-sm btn-github" id='btnLimpar' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar" href="{{ route('appnotification.index') }}">
                                        <span class="fa fa-recycle fa-lg"></span>
                                    </a>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <table id="tblCadastro" class="dataTable table table-bordered table-hover table-condensed wrap">
                                        <thead>
                                            <tr>
                                                <th>C&oacute;digo</th>
                                                <th>Descrição</th>
                                                <th>Título</th>
                                                <th>Corpo</th>
                                                <th>Status</th>
                                                <th>Layout</th>
                                                <th style="width:200px;">Operação</th>
                                            </tr>
                                        </thead>
                                        <tbody id="notificacoes-list" name="notificacoes-list">
                                            @isset($notificacoes)
                                                @foreach ($notificacoes as $notificacao)
                                                <tr id="notificacao{{$notificacao->id}}">
                                                    <td>{{$notificacao->id}}</td>
                                                    <td>{{$notificacao->descricao}}</td>
                                                    <td>{{$notificacao->fcmtitle}}</td>
                                                    <td>{{$notificacao->fcmbody}}</td>
                                                    <td>{{$notificacao->status }}</td>
                                                    <td>{{$notificacao->islayout == 1 ? "Sim" : "Não" }}</td>
                                                    <td>
                                                        @can('view', $notificacao)
                                                            <button onclick="window.location.href = '{{route('appnotification.show',$notificacao->id)}}'"
                                                                class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                                    <span class="fa fa-eye fa-lg"></span>
                                                            </button>
                                                        @endcan
                                                        @can('update', $notificacao)
                                                            <button onclick="window.location.href = '{{route('appnotification.edit',$notificacao->id)}}'"
                                                                class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                                        <span class="fa fa-pencil-square-o fa-lg"></span>
                                                            </button>
                                                        @endcan
                                                        @if ($notificacao->status == "pendente")
                                                            <button id="btnEnviar" class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Enviar Notificação">
                                                                    <i class="fa fa-paper-plane-o" aria-hidden="true"></i>
                                                            </button>
                                                        @endif
                                                        @can('delete', $notificacao)
                                                            <button onclick="removeRegister({{$notificacao}})"
                                                                id="btnRemover" class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover">
                                                                        <span class="fa fa-trash fa-lg"></span>
                                                            </button>
                                                        @endcan
                                                    </td>
                                                </tr>
                                                @endforeach
                                            @endisset
                                        </tbody>
                                    </table>
                                </DIV>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-5">
                                @can('create', App\Appnotification::class)
                                    <a href="{{ URL::route('appnotification.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="myModalDel" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabel">Remover Cadastro</h4>
            </div>
            {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmCadastroDel']) }}
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12">
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-10">
                            <input type="hidden" id="id_del" name="id">
                            {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id'=>'descricao_del']) !!}
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseCadastroDel" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                {!! Form::submit('Remover', ['class' => 'btn btn-nw-registro']) !!}
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>
<!--Rota para deletar via ajax-->
<div id='rotaDel' class="hidden">{{url('appnotification')}}/</div>
<!--Rota para redirecionar via ajax-->
<div id='rotaIndex' class="hidden">{{route('appnotification.index')}}</div>

<style>
    td:nth-child(4) {
        word-break: break-all;
        white-space: normal;
    }
</style>

<script>
    const url = "{{route('appnotification.index')}}"

    $("#btnConsultarNotis").click(function () {
        let tipo = $("#tipo").val();

        window.location.href = url + "?tipo=" + tipo;
    });

    $("#tblCadastro").on("click", "#btnEnviar", function () {
        let tbl = $("#tblCadastro").DataTable()
        let parent = $(this).parents("tr");
        let data = tbl.row(parent).data();

        bootbox.confirm({
            title: "Notificação APP Gás Em Casa",
            message: "Deseja enviar essa notificação?",
            buttons: {
                cancel: {
                    label: "Não",
                    className: "btn-nw-geral pull-center",
                },
                confirm: {
                    label: "Sim",
                    className: "btn-nw-registro pull-center",
                },
            },
            callback: (result) => {
                if (!result) return;

                showLoaderAjax("Enviando Notificação", " Por favor aguarde!", false, () => {
                    setTimeout(() => {
                        sendNotification(data[0]);
                    }, 150);
                });
            },
        });
    });

    function sendNotification(id) {
        let url = root + "/appnotification.send/" + id;

        ajaxGenerator(url, "PATCH", function (suc) {
            bootbox.alert("Notificação enviado com sucesso.");

            setTimeout(() => {
                window.location.reload();
            }, 400);
        }, function (err) {
            console.error(err)

            bootbox.alert("Houve um erro ao enviar a notificação: " + err.responseText);
        }, null, false, function () {
            hideLoaderAjax();
        });
    }
</script>
@endsection
