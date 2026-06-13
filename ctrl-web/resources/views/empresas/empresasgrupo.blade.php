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
                                @can('create', App\EmpresasGrupo::class)
                                    <button type="button" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModal">Novo Registro</button>                                
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Grupos de Empresas</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed">
                                    <!--{{ route("empresas_grupo.show", ":id") }}-->
                                    <thead>
                                        <tr>
                                            <th class="hidden">Id</th>
                                            <th style="width:125px;">C&oacute;digo</th>
                                            <th>Descri&ccedil;&atilde;o</th>
                                            <th>Ativo</th>
                                            <th style="width:200px;">Operação</th>
                                            <th class="hidden">IMG</th>
                                        </tr>
                                    </thead>
                                    <tbody id="empresasgrupo-list" name="empresasgrupo-list">
                                        @foreach ($empresasgrupos as $empresasgrupo)
                                        <tr id="empresasgrupo{{$empresasgrupo->id}}">
                                            <td class="hidden">{{$empresasgrupo->id}}</td>
                                            <td>{{$empresasgrupo->id}}</td>
                                            <td>{{$empresasgrupo->descricao}}</td>
                                            <td>{{$empresasgrupo->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>
                                                @can('view', $empresasgrupo)
                                                    <button onclick="viewRegister({{$empresasgrupo}})" id="btnVisualizar"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('update', $empresasgrupo)
                                                    <button onclick="editRegister({{$empresasgrupo}})" id="btnEditar"
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                                <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @endcan
                                            </td>
                                            <td class="hidden">{{$empresasgrupo->logo}}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->

                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-5">
                                @can('create', App\EmpresasGrupo::class)
                                    <button type="button" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModal">Novo Registro</button>                                
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->

            <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                            <h4 class="modal-title" id="myModalLabelCadastro"></h4>
                        </div>
                        {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmCadastroAjax']) }}
                        <div class="modal-body">
                            <div class="box-body">
                                <div class="form-group crud_space col-sm-12">
                                    <input type="hidden" id="id" name="id">
                                    {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                    <div class="col-sm-10">
                                        <input type="hidden" id="grupo_id" name="grupo_id">
                                        <input type="hidden" id="empresa_id" name="grupo_id">
                                        <input type="hidden" id="metodo" name="_method">
                                        {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id'=>'descricao']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    <label for="ativo" class="col-sm-2 control-label input-sm required">Ativo:</label>
                                    <div class="col-sm-10 checkbox">

                                        {{ Form::checkbox('ativo', 1, null, ['id'=>'ativo']) }}
                                    </div>
                                </div>

                                <div class="form-group crud_space col-sm-12">
                                  {!! Form::label('logocrop', 'Logotipo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                  <div class="col-sm-5">
                                    <!-- @if(isset($Empresa->logo))
                                      <input type="text" id="logo" name="logo" value="{{ $Empresa->logo }}" style="visibility: hidden; width: 1px; height: 1px" multiple ></input>
                                    @else -->
                                      <input type="text" id="logo" name="logo" style="visibility: hidden; width: 1px; height: 1px" multiple />
                                    <!-- @endif -->
                                    <input type="file" id="file" name="file" style="visibility: hidden; width: 1px; height: 1px" multiple />
                                    <div class="action">
                                      <a href="" onclick="document.getElementById('file').click(); return false">
                                        <!-- @if(isset($Empresa->logo))
                                          <img id="cropped" style="max-width:200px; border-radius:50%; padding-left: 4.5px;" src="data:image/png;base64,{{ $Empresa->logo }}" alt="Logotipo"/>
                                        @else -->
                                          <img id="cropped" style="max-width:200px; border-radius:50%; padding-left: 4.5px;" src="{{ URL::to('dist/img/upload.jpg') }}" alt="Logotipo"/>
                                        <!-- @endif -->
                                      </a>
                                      {{-- <input type="file" id="file"> --}}
                                    </div>
                                  </div>
                                  <div class="col-sm-2" id="divImgBox">
                                    <div class="imageBox">
                                      <div class="thumbBox"></div>
                                      <div class="spinner" style="display: none">Loading...</div>
                                    </div>
                                  </div>
                                </div> <!-- form-group  -->
                                <div class="form-group crud_space col-sm-12" id="divBtns">
                                  <div class="col-sm-5 col-sm-push-2">
                                    {{-- <label id="btnCarregarImagem" for="file" onclick="document.getElementById('file').click(); return false" class="btn btn-nw-geral">Carregar</label>
                                    <label id="btnRemoverImagem" for="" onclick="" class="btn btn-danger">Remover</label> --}}
                                    <input type="button" id="btnCarregarImagem" for="file" onclick="document.getElementById('file').click(); return false" class="btn btn-nw-geral" value="Carregar">
                                    <input type="button" id="btnRemoverImagem" for="" onclick="" class="btn btn-danger" value="Remover">
                                  </div>
                                  <div class="col-sm-5 col-sm-push-2">
                                    <div class="col-sm-5">
                                      <input type="button" id="btnCrop" class="btn btn-nw-geral" value="Cortar">
                                    </div>
                                    <div class="col-sm-3">
                                      <input type="button" id="btnZoomIn" class="btn btn-nw-geral" value="+">
                                    </div>
                                    <div class="col-sm-2">
                                      <input type="button" id="btnZoomOut" class="btn btn-nw-geral" value="-">
                                    </div>
                                  </div>
                                </div>

                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="btnCloseCadastro" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>

                            {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro', 'id' => 'btnCadastro']) !!}
                            <div id="saveError" class="alert alert-danger alert-dismissable" style="display:none;">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                <span class="glyphicon glyphicon-remove"></span>
                                <div id="save_result"></div>
                            </div>
                        </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>

            <div class="modal fade" id="myModalDel" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                            <h4 class="modal-title" id="myModalLabelDel">Remover Registro</h4>
                        </div>
                        {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmCadastroDel']) }}
                        <div class="modal-body">
                            <div class="box-body">
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                    <div class="col-sm-10">
                                        <input type="hidden" id="id_del" name="id">
                                        {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id'=>'descricao_del']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('ativo', 'Ativo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                    <div class="col-sm-10 checkbox">
                                        {{ Form::checkbox('ativo_del', 1, null, ['id'=>'ativo_del']) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="btnCloseCadastroDel" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                            {!! Form::submit('Remover', ['class' => 'btn btn-nw-registro']) !!}
                            <div id="saveErrorDel" class="alert alert-danger alert-dismissable" style="display:none;">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                <span class="glyphicon glyphicon-remove"></span>
                                <div id="save_result"></div>
                            </div>
                        </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
            <!--Rota para um novo cadastro via ajax-->
            <div id='rotaStore' class="hidden">{{route('empresas_grupo.store')}}</div>
            <!--Rota para atualizar via ajax-->
            <div id='rotaUpdate' class="hidden">{{url('empresas_grupo')}}/</div>
            <!--Rota para deletar via ajax-->
            <div id='rotaDel' class="hidden">{{url('empresas_grupo')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{route('empresas_grupo.index')}}</div>
            <!--Rota para a linguagem do plugin de paginação-->
            <div id='urlLanguage' class="hidden">{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}</div>

            <link href="{{URL::to('css/cropbox.css')}}" rel="stylesheet" type="text/css" />
            <script src="{{URL::to('plugins/cropbox/cropbox.js')}}"></script>

            <script type="text/javascript">
                $(document).ready(function () {

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
                    });

                    $('#btnCrop').on('click', function(){
                      var img = cropper.getDataURL();
                      // $('.cropped').append('<img src="'+img+'">');
                      $('#cropped').prop('src', img);
                      // $('#logo').prop('value', $('#cropped').attr('src'));
                      $('#logo').prop('value', img);
                    });

                    $('#btnZoomIn').on('click', function(){
                      cropper.zoomIn();
                    });

                    $('#btnZoomOut').on('click', function(){
                      cropper.zoomOut();
                    });

                    $('#btnRemoverImagem').on('click', function(){
                      $('#cropped').prop('src', root + '/dist/img/upload.jpg');
                      $('#logo').prop('value', '');
                    });

                    document.getElementById('logo').onchange = function (evt) {
                      var tgt = evt.target || window.event.srcElement,
                      files = tgt.files;
                      // FileReader support
                      if (FileReader && files && files.length) {
                        var fr = new FileReader();
                        fr.onload = function () {
                          document.getElementById('logoImg').src = fr.result;
                          alert('a');
                        }
                        fr.readAsDataURL(files[0]);
                      }
                    };
                });

                $('#tblCadastro').on('click', '#btnEditar', function (e) {
                    e.stopPropagation();
                    var trElem = $(this).closest("tr");
                    var firstTd = $(trElem).children("td")[0];
                    var logo = $(trElem).children("td")[5];
                    var id = $(firstTd).text();
                    if($("#divImgBox").hasClass('hidden')){
                        $("#divImgBox").removeClass('hidden');
                        $("#divBtns").removeClass('hidden');
                    }
                    if ($(firstTd).text() !== "") {
                        $('#logo').val($(logo).text());
                        if($(logo).text() != ''){
                            $('#cropped').attr('src', "data:image/png;base64," + $(logo).text());
                        }else{
                            $('#cropped').attr('src', 'dist/img/upload.jpg');
                        }
                        $('#myModal').modal('show');
                    }
                });

                $('#tblCadastro').on('click', '#btnVisualizar', function (e) {
                    e.stopPropagation();
                    var trElem = $(this).closest("tr");
                    var firstTd = $(trElem).children("td")[0];
                    var logo = $(trElem).children("td")[5];
                    var id = parseInt($(firstTd).text());
                    $('#btnCarregarImagem').prop('disabled', true);
                    $('#btnRemoverImagem').prop('disabled', true);
                    $('#btnCrop').prop('disabled', true);
                    $('#btnZoomIn').prop('disabled', true);
                    $('#btnZoomOut').prop('disabled', true);
                    if(!$("#divImgBox").hasClass('hidden')){
                        $("#divImgBox").addClass('hidden');
                        $("#divBtns").addClass('hidden');
                    }
                    $('#logo').val($(logo).text());
                    if($(logo).text() != ''){
                        $('#cropped').attr('src', "data:image/png;base64," + $(logo).text());
                    }else{
                        $('#cropped').attr('src', 'dist/img/upload.jpg');
                    }
                    $('#myModal').modal('show');
                });

                $(".btnNovoCadastro").click(function(){
                    if($("#divImgBox").hasClass('hidden')){
                        $("#divImgBox").removeClass('hidden');
                        $("#divBtns").removeClass('hidden');
                    }
                    $('#cropped').attr('src', 'dist/img/upload.jpg');
                });
            </script>
        </div><!-- /.content-wrapper -->
    </div>
</div>
@endsection
