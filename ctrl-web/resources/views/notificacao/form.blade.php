@extends('layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-sm-12">

            @if(isset($notificacao))
                {{ Form::model($notificacao, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('appnotification.update', $notificacao->id))) }}
            @else
                {{ Form::open(['id'=>'fmCadastro', 'route' => 'appnotification.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif

            <ul>
                <div class="nav-tabs-custom">
                    <div class="header panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                Notificação aplicativo Gás em Casa
                            </h3>
                        </div>
                    </div>
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab_1">
                            <div class="row">
                                <div id="tabCadastro" class="col-sm-12">
                                    <div class="form-group crud_space">
                                        {{ Form::label('fcmtitle', 'Titulo: ', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-md-8">
                                            {{ Form::text('fcmtitle',null,['id' => 'fcmtitle', 'class'=>'form-control input-sm']) }}
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {{ Form::label('fcmbody', 'Corpo: ', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-md-8">
                                            {{ Form::textarea('fcmbody',null,['id' => 'fcmbody', 'class'=>'form-control input-sm', 'rows' => 4]) }}
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {{ Form::label('status', 'Status: ', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-md-8">
                                            {{ Form::text('status',null,['id' => 'fcmtitle', 'class'=>'form-control input-sm', 'disabled']) }}
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        <label for="instant" class="col-sm-1 control-label input-sm required">Agora:</label>
                                        <div class="col-sm-4 checkbox">
                                            {{ Form::checkbox('instant', 1, null, ['id'=>'instant']) }}
                                        </div>

                                        <label for="islayout" class="col-sm-1 control-label input-sm required">Layout:</label>
                                        <div class="col-sm-4 checkbox">
                                            {{ Form::checkbox('islayout', 1, null, ['id'=>'islayout']) }}
                                        </div>
                                    </div>

                                    <div class="form-group crud_space">
                                        <div class="col-md-2 col-md-offset-1">
                                            <h4>Imagem</h4>
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        <div class="col-sm-2 col-sm-offset-2">
                                            {{ Form::checkbox('img-remove', 1, null, ['id' => 'img-remove', 'class' => 'hidden']) }}
                                            <input type="file" id="file" name="file" style="visibility: hidden; width: 1px; height: 1px" accept="image/png, image/jpeg"  />
                                            <div class="action">
                                                <a href="" onclick="document.getElementById('file').click(); return false">
                                                    @if(isset($notificacao->imagem))
                                                        <img id="image" style="max-width:250px; padding-left: 4.5px;" src="{{ asset($notificacao->imagem) }}" alt="Logotipo"/>
                                                    @else
                                                        <img id="image" style="max-width:250px; padding-left: 4.5px;" src="{{ URL::to('dist/img/upload.jpg') }}" alt="Logotipo"/>
                                                    @endif
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        <div class="col-sm-4 col-sm-push-2">
                                            <input type="button" id="btnCarregarImagem" for="file" onclick="document.getElementById('file').click(); return false" class="btn btn-nw-geral" value="Carregar">
                                            <input type="button" id="btnRemoverImagem" for="" onclick="" class="btn btn-danger" value="Remover">
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <div class="box-footer">
                            <div class="col-sm-4">
                                <button class="btn btn-nw-registro">Gravar</button>
                                <a type="button" href="{{url('appnotification')}}" class="btn btn-nw-geral">Voltar</a>
                            </div>
                        </div>
                    </div>
                </div>
            </ul>

            {!! Form::close() !!}
        </div>
    </div>
</div>

<script>
    setTimeout(function () {
        @if (isset($show) && $show)
            desativarInputs();
        @endif
    }, $(document).ready());

    $(document).ready(function () {
        @if (!isset($show))
            checkForLayout();
        @endif
    });

    $("#islayout").change(function () {
        checkForLayout();
    });

    $('#file').on('change', function (event) {
        let target = event.target || window.event.srcElement;
        let files = target.files;

        let fr = new FileReader();
        fr.onload = function () {
            $("#image").attr("src", fr.result);
            $("#img-remove").prop("checked", false);
        };
        fr.readAsDataURL(files[0]);
    });

    $('#btnRemoverImagem').on('click', function () {
        $('#image').prop('src', root + '/dist/img/upload.jpg');
        $("#img-remove").prop("checked", true);
    });

    function checkForLayout() {
        let isLayout = $("#islayout").prop("checked");

        if (isLayout) {
            $("#instant").prop("checked", false);
            $("#instant").prop("disabled", true);
        } else {
            $("#instant").prop("disabled", false);
        }
    }
</script>
@endsection
