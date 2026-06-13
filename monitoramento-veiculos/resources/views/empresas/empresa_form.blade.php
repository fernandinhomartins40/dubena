@extends('layouts.mainmenu')
@section('content')
  <div id="mainContent" class="content">
    <div id="divCadastro" class="row">
      <div class="col-md-12">
        <!-- Custom Tabs -->
        <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
        @if(isset($Empresa))
          {{ Form::model($Empresa, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('empresa.update', $Empresa->id))) }}
        @else
          {{ Form::open(['id'=>'fmCadastro', 'route' => 'empresa.store', 'class' => 'form-horizontal', 'files' => true]) }}
        @endif
        <ul>
          <div class="panel panel-default">
            <div class="panel-heading">
              <h3 class="panel-title">Empresa</h3>
            </div>
            <div class="nav-tabs-custom">
              <ul class="nav nav-tabs">
                <li class="active"><a href="#tab_1" data-toggle="tab">Dados da Empresa</a></li>
              </ul>
              <div class="tab-content">
                <div class="tab-pane active" id="tab_1">
                  <!-- form start -->
                  <div class="row">
                    <div id="tabCadastro" class="col-md-10">
                      <div class="box-body">
                        <div class="form-group crud_space">
                          {!! Form::label('grupo_id', 'Grupo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                          <div class="col-sm-10">
                            {!! Form::select('grupo_id', $grupos, null, ['class' => 'form-control selectDisableSearch']) !!}
                          </div>
                        </div>
                        <div class="form-group crud_space">
                          {!! Form::label('razao_social', 'Razão Social:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                          <div class="col-sm-10">
                            {!! Form::text('razao_social',null,['class'=>'form-control input-sm']) !!}
                          </div>
                        </div>
                        <div class="form-group crud_space">
                          {!! Form::label('nome_fantasia', 'Nome Fantasia:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                          <div class="col-sm-10">
                            {!! Form::text('nome_fantasia',null,['class'=>'form-control input-sm']) !!}
                          </div>
                        </div>
                        <div class="form-group crud_space">
                          {!! Form::label('nome_informal', 'Nome Informal:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                          <div class="col-sm-10">
                            {!! Form::text('nome_informal',null,['class'=>'form-control input-sm']) !!}
                          </div>
                        </div>
                        <div class="form-group crud_space">
                          {!! Form::label('cnpj', 'CNPJ:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                          <div class="col-sm-3">
                            {!! Form::text('cnpj',null,['class'=>'form-control input-sm cnpj']) !!}
                          </div>
                          {!! Form::label('inscricao_estadual', 'Insc.Estadual:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                          <div class="col-sm-4">
                            {!! Form::text('inscricao_estadual',null,['class'=>'form-control input-sm']) !!}
                          </div>
                        </div>
                        <div class="form-group crud_space">
                            {{ Form::label('keygooglemaps', 'Key API Google Maps:', ['class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-sm-10">
                                {{ Form::text('keygooglemaps',null,['id' => 'keygooglemaps','class'=>'form-control input-sm']) }}
                            </div> 
                        </div>
                        <div class="form-group crud_space">
                            {{ Form::label('tempoparado', 'Tempo Max.Parada:', ['class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-sm-2">
                                {{ Form::number('tempoparado',null,['id' => 'tempoparado','class'=>'form-control input-sm']) }}
                            </div> 
                        </div>
                        <div class="form-group crud_space">
                                {!! Form::label('ativo', 'Ativo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                <div class="col-sm-9">
                                        <!--
                                        {!! Form::text('ativo',null,['class'=>'form-control input-sm']) !!}
                                        {!! Form::checkbox('ativo',null,['class'=>'form-control input-sm']) !!}
                                        -->

                                        {{ Form::checkbox('ativo') }}

                                </div>
                        </div>
                        {{-- <div class="form-group crud_space">
                          {!! Form::label('logo', 'Logotipo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                          <div class="col-sm-10">
                            <input type="file" id="logo" name="logo" style="visibility: hidden; width: 1px; height: 1px" multiple />
                            <a href="" onclick="document.getElementById('logo').click(); return false">
                              @if(isset($Empresa->logo))
                                <img id="logoImg" style="max-width:200px;" src="data:image/png;base64,{{ $Empresa->logo }}" alt="Logotipo"/>
                              @else
                                <img id="logoImg" style="max-width:200px;" src="{{ URL::to('dist/img/upload.jpg') }}" alt="Logotipo"/>
                              @endif
                            </a>
                          </div>
                        </div> <!-- form-group  --> --}}
                        <div class="form-group crud_space">
                          {!! Form::label('logocrop', 'Logotipo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                          <div class="col-sm-2">
                            @if(isset($Empresa->logo))
                              <input type="text" id="logo" name="logo" value="{{ $Empresa->logo }}" style="visibility: hidden; width: 1px; height: 1px" multiple ></input>
                            @else
                              <input type="text" id="logo" name="logo" style="visibility: hidden; width: 1px; height: 1px" multiple />
                            @endif
                            <input type="file" id="file" name="file" style="visibility: hidden; width: 1px; height: 1px" multiple />
                            <div class="action">
                              <a href="" onclick="document.getElementById('file').click(); return false">
                                @if(isset($Empresa->logo))
                                  <img id="cropped" style="max-width:200px; border-radius:50%; padding-left: 4.5px;" src="data:image/png;base64,{{ $Empresa->logo }}" alt="Logotipo"/>
                                @else
                                  <img id="cropped" style="max-width:200px; border-radius:50%; padding-left: 4.5px;" src="{{ URL::to('dist/img/upload.jpg') }}" alt="Logotipo"/>
                                @endif
                              </a>
                              {{-- <input type="file" id="file"> --}}
                            </div>
                          </div>
                          <div class="col-sm-2">
                            <div class="imageBox">
                              <div class="thumbBox"></div>
                              <div class="spinner" style="display: none">Loading...</div>
                            </div>
                          </div>
                          {{-- <div class="col-sm-3"> --}}
                            {{-- @if(isset($Empresa->logo))
                              <img id="cropped" src="data:image/png;base64,{{ $Empresa->logo }}" alt="Logotipo"/>
                            @else
                              <img id="cropped" src="data:image/png;base64,{{ URL::to('dist/img/upload.jpg') }}" alt="Logotipo"/>
                            @endif --}}
                          {{-- </div> --}}
                        </div> <!-- form-group  -->
                        <div class="form-group crud_space">
                          <div class="col-sm-4 col-sm-push-2">
                            {{-- <label id="btnCarregarImagem" for="file" onclick="document.getElementById('file').click(); return false" class="btn btn-nw-geral">Carregar</label>
                            <label id="btnRemoverImagem" for="" onclick="" class="btn btn-danger">Remover</label> --}}
                            <input type="button" id="btnCarregarImagem" for="file" onclick="document.getElementById('file').click(); return false" class="btn btn-nw-geral" value="Carregar">
                            <input type="button" id="btnRemoverImagem" for="" onclick="" class="btn btn-danger" value="Remover">
                          </div>
                          <div class="col-sm-5">
                            <div class="col-sm-3">
                              <input type="button" id="btnCrop" class="btn btn-nw-geral" value="Cortar">
                            </div>
                            <div class="col-sm-1">
                              <input type="button" id="btnZoomIn" class="btn btn-nw-geral" value="+">
                            </div>
                            <div class="col-sm-1">
                              <input type="button" id="btnZoomOut" class="btn btn-nw-geral" value="-">
                            </div>
                          </div>
                        </div>
                      </div> <!-- box-body  -->
                    </div> <!-- tab-cadastro -->
                  </div> <!-- row -->
                </div><!-- /.tab-pane 1 -->
              </div><!-- /.tab-pane -->
              <div class="box-footer">
                <div class="col-md-4">
                  {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                  <a href='{{url("empresa")}}' type="button" class="btn btn-nw-geral">Voltar</a>
                </div>
              </div>
            </div>
            {!! Form::close() !!}
            @if(isset($Empresa))

			@endif
    </div><!-- /.col -->
  </div>
</div>
</div>
</div>
<link href="{{URL::to('css/cropbox.css')}}" rel="stylesheet" type="text/css" />
<script src="{{URL::to('plugins/cropbox/cropbox.js')}}"></script>

<script src="{{URL::to('js/empresa.js')}}"></script>
<script type="text/javascript">

$('.modal-wide').on('show.bs.modal', function () {
  var height = $(window).height() - 200;
  $(this).find('.modal-body').css('max-height', height);
});

jQuery(document).ready(function ($) {

  var options =
  {
    thumbBox: '.thumbBox',
    spinner: '.spinner',
    imgSrc: 'dist/img/upload.jpg'
  }
  var cropper = $('.imageBox').cropbox(options);

  $('#file').on('change', function(){
    var reader = new FileReader();
    reader.onload = function(e) {
      options.imgSrc = e.target.result;
      cropper = $('.imageBox').cropbox(options);
    }

    reader.readAsDataURL(this.files[0]);
    this.files = [];
  })

  $('#btnCrop').on('click', function(){
    var img = cropper.getDataURL();
    // $('.cropped').append('<img src="'+img+'">');
    $('#cropped').prop('src', img);
    // $('#logo').prop('value', $('#cropped').attr('src'));
    $('#logo').prop('value', img);
  })

  $('#btnZoomIn').on('click', function(){
    cropper.zoomIn();
  })

  $('#btnZoomOut').on('click', function(){
    cropper.zoomOut();
  })

  $('#btnRemoverImagem').on('click', function(){
    $('#cropped').prop('src', root + '/dist/img/upload.jpg');
    $('#logo').prop('value', '');
  })

  document.getElementById('logo').onchange = function (evt) {
    var tgt = evt.target || window.event.srcElement,
    files = tgt.files;
    // FileReader support
    if (FileReader && files && files.length) {
      var fr = new FileReader();
      fr.onload = function () {
        document.getElementById('logoImg').src = fr.result;
      }
      fr.readAsDataURL(files[0]);
    }

  };

});
$(function(){

  // ## EXEMPLO 2
  // Aciona a validação ao sair do input
  $('.cnpj').blur(function(){

    // O CPF ou CNPJ
    var cpf_cnpj = $(this).val();

    // Testa a validação
    if ( valida_cpf_cnpj( cpf_cnpj ) ) {
      //                    alert('OK');
    } else {
      if(cpf_cnpj !== ''){
        alert('CNPJ inválido!');
        $(this).focus();
        $(this).val('');
      }
    }

  });
});
</script>
<style>
.valido {
  border: 1px solid green;
}
.invalido {
  border: 1px solid red;
}
</style>
<script>
setTimeout(function () {


  @if (isset($show))
  desativarInputs();
  var ids = ['#btnCrop',
  '#btnZoomIn', '#btnZoomOut', '#btnCarregarImagem', '#btnRemoverImagem'];
  desativarInputsEspecificos(ids);
  @endif
}, $(document).ready());

</script>
@endsection
