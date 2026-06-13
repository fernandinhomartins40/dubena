@extends('layouts.mainmenu')

@section('content')
    <div id="mainContent" class="content">
        <div id="divCadastro">
            <div class="row">
                <div class="col-xs-12">
                    <div class="box-header">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h3 class="box-title">Atualização de tabela do IBPT</h3>
                            </div><!-- /.box-header -->
                            <div class="panel-body">
                                <div class="col-md-12">
                                    <div class="box-body">
                                        <div class="form-group crud_space" style="margin-left: 1.5%">
                                            <div class="box-table-scroll col-sm-10 col-sm-offset-1">
                                                <table class="table table-bordered table-condensed">
                                                    <thead>
                                                        <tr>
                                                            <th>UF</th>
                                                            <th>Período de Vigência</th>
                                                            <th>Versão</th>
                                                            <th>Chave</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($data as $d)
                                                            <tr>
                                                                <td>{{$d->uf}}</td>
                                                                @if($d->inicio && $d->fim)
                                                                    <td>{{ requestDataOracleSemHora($d->inicio) . " - " . requestDataOracleSemHora($d->fim)  }}</td>
                                                                @else
                                                                    <td></td>
                                                                @endif
                                                                <td>{{$d->versao}}</td>
                                                                <td>{{$d->chave}}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="col-sm-2 col-sm-offset-5">
                                                <label class="mousehover-pointer" id="btnUpload">
                                                        <span class="btn btn-sm btn-nw-registro fa fa-upload fa-lg"
                                                              data-toggle='tooltip' data-trigger="hover"
                                                              data-placement="bottom" title="Arquivo">
                                                        </span>
                                                    &nbsp;&nbsp;&nbsp;&nbsp;<span>Selecione</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div><!-- /.box-body -->
                        </div><!-- /.box -->
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div><!-- /.content-wrapper -->
        </div>
    </div>
    @include('general.modals.upload_file')
    <form action="" id="fmAux" method="post" enctype="multipart/form-data">
        <input type="hidden" name='file-upload' id="file">
    </form>
    <script>

        $("#btnUpload").on('click', function () {
            $("#modal-upload-file").modal('show');
        });
        $("#uploadInfo").text("Formato esperado na seguinte ordem: [Código (ncm), Ex, Tipo, Descrição, Nacional Federal, Importados Federal, Estadual, Municipal, Vigência Início, Vigência Fim, Chave, Versão, Fonte]").removeClass("hidden");

        validFormatUpload = ["zip"];
        $("#file-upload").attr('accept', '.zip');

        callbackUpload = function () {
            let url = root + '/ibpt';
            let $fmUp = $("#fmUpload");
            let formData = new FormData($fmUp[0]);

            $fmUp.off().on('submit', function () {
                return false;
            });
            if (isEmpty($("#file-upload").val())) {
                bootbox.alert('Selecione um arquivo');
                return;
            }
            showLoaderAjax("Aguarde", "Carregando Arquivos", false);
            ajaxGenerator(url, "POST", function (result) {
                let msg;
                if (typeof result.msg !== "undefined") {
                    msg = result.msg;
                }
                if (typeof result.status === "string" && result.status === "OK|") {
                    bootbox.alert(msg, () => $("#modal-upload-file").modal("hide"));
                } else {
                    if (! msg) {
                        msg = result;
                    }
                    bootbox.alert(msg ? msg : "Erro desconhecido ao tentar a importação do arquivo");
                    console.log(result);
                }
            }, function (result) {
                let msg;
                if (typeof result.msg !== "undefined") {
                    msg = result.msg;
                }
                if (typeof result.message !== "undefined") {
                    msg = result.message;
                }
                bootbox.alert(msg ? msg : "Erro desconhecido ao tentar a importação do arquivo");
            }, formData, true, function () {
                hideLoaderAjax();
            });
        };
    </script>
@endsection
