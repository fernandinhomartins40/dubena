@extends('layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-sm-12">

            @if(isset($appvideo))
                {{ Form::model($appvideo, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('appvideo.update', $appvideo->id))) }}
            @else
                {{ Form::open(['id'=>'fmCadastro', 'route' => 'appvideo.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif

            <ul>
                <div class="panel panel-default form-horizontal">
                    <div class="panel-heading">
                        <h3 class="panel-title">Video abertura Aplicativo Gás em Casa</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            @if (!is_null($appvideo))
                                                <div class="form-group crud_space">
                                                    {{ Form::label('titulo', 'Titulo: ', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-md-4">
                                                        {{ Form::text('titulo',null,['id' => 'titulo', 'class'=>'form-control input-sm', 'readonly']) }}
                                                    </div>
                                                    {{ Form::label('status_desc', 'Status: ', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-md-4">
                                                        {{ Form::text('status_desc',null,['id' => 'status_desc', 'class'=>'form-control input-sm', 'readonly']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('mensagem', 'Mensagem: ', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-md-4">
                                                        {{ Form::text('mensagem',null,['id' => 'mensagem', 'class'=>'form-control input-sm', 'readonly']) }}
                                                    </div>
                                                </div>
                                                @if ($appvideo->status == \App\Enums\AppVideoStatus::Sincronizado)
                                                    <div class="form-group crud_space">
                                                        <label for="ativo" class="col-sm-1 control-label input-sm required">Ativo:</label>
                                                        <div class="col-sm-4 checkbox">
                                                            {{ Form::checkbox('ativo', 1, null, ["id" => "ativo"]) }}
                                                        </div>
                                                    </div>
                                                @endif
                                                @if ($appvideo->caminho && $appvideo->status == \App\Enums\AppVideoStatus::Sincronizado)
                                                    <div id="video-saved-container" class="form-group crud_space" style="margin-top: 10px; margin-bottom: 10px;">
                                                        <div class="col-sm-4 col-sm-offset-1">
                                                            <video width="480" height="420" controls>
                                                                <source src="{{ asset($appvideo->caminho) }}" type="video/mp4">
                                                            </video>
                                                        </div>
                                                    </div>
                                                @elseif($appvideo->status != \App\Enums\AppVideoStatus::Sincronizado)
                                                    <div class="form-group crud_space">
                                                        Video está sendo processado.
                                                    </div>
                                                @endif
                                            @endif
                                            <div class="form-group crud_space m-t-10">
                                                <div class="col-md-2 col-md-push-1">
                                                    <h4>Novo Video</h4>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{-- {{ Form::checkbox('img-remove', 1, null, ['id' => 'img-remove', 'class' => 'hidden']) }}
                                                <input type="file" id="file" name="file" accept="video/mp4"  /> --}}

                                                {{-- {{ Form::label('certificadodigital', 'Certificado Digital:', ['class'=>'col-sm-2 control-label input-sm']) }} --}}
                                                <label class="mousehover-pointer col-sm-4 col-sm-offset-1">
                                                    <span class="btn btn-sm btn-nw-registro fa fa-upload fa-lg">
                                                        <input type="file" id="file" name="file" class="btn-file" style="display: none;" accept="video/mp4" />
                                                    </span>
                                                    &nbsp;&nbsp;&nbsp;&nbsp;<span id='upload-filename'>Nenhum arquivo selecionado..</span>
                                                </label>
                                                {{-- <div class="action">
                                                    <a href="" onclick="document.getElementById('file').click(); return false">
                                                        @if(isset($notificacao->imagem))
                                                            <img id="image" style="max-width:250px; padding-left: 4.5px;" src="{{ asset($notificacao->imagem) }}" alt="Logotipo"/>
                                                        @else
                                                            <img id="image" style="max-width:250px; padding-left: 4.5px;" src="{{ URL::to('dist/img/upload.jpg') }}" alt="Logotipo"/>
                                                        @endif
                                                    </a>
                                                </div> --}}
                                            </div>
                                            <div id="video-preview-container" class="form-group crud_space" style="margin-top: 10px; margin-bottom: 10px;">
                                                <div class="col-sm-4 col-sm-offset-1">
                                                    <video id="video-preview" width="480" height="420" controls style="display:none;"></video>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <div class="col-sm-4">
                            <button id="btnGraver" type="button" class="btn btn-nw-registro">Gravar</button>
                        </div>
                    </div>
                </div>
            </ul>

            {!! Form::close() !!}
        </div>
    </div>
</div>

<script>
    $("#btnGraver").click(function () {
        showLoaderAjax("Salvando Video", "Por favor, aguarde enquanto o upload acontece.", false, function () {
            setTimeout(() => {
                $("#fmCadastro").submit();
            }, 200);
        });
    });

    $('#file').on('change', function () {
        let input = $(this);
        let file = input.get(0).files[0];

        if (!file) return;

        let label = file.name;
        let extension = label.substring(label.lastIndexOf('.')).toLowerCase();
        let allowedExtensions = ['.mp4'];

        if ($.inArray(extension, allowedExtensions) === -1) {
            bootbox.alert("Tipo de arquivo inválido");
            input.val('');
            input.parents('label').children('#upload-filename')
                .text("Formato de arquivo inválido.");


            $('#video-preview').hide();
            $("#video-saved-container").show();
            return;
        }

        let videoURL = URL.createObjectURL(file);

        let video = document.getElementById('video-preview');

        video.src = videoURL;
        video.load();
        video.style.display = 'block';

        video.muted = true;
        video.playsInline = true;

        video.play()
            .then(() => video.pause())
            .catch(() => {
                bootbox.alert(
                    "O vídeo foi selecionado, mas não pode ser visualizado no navegador devido a falta de CODECs. " +
                    "Ele será processado após o envio."
                );
                video.style.display = 'none';
            });

        input.parents('label').children('#upload-filename').text(label);

        $("#video-saved-container").hide();
    });
</script>

@endsection