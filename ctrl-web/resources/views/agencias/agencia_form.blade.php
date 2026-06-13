@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">

            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($agencia))
            {{ Form::model($agencia, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('agencia.update', $agencia->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'agencia.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Agência</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                            <li class=""><a href="#tab_2" data-toggle="tab">Endereço</a></li>
                            <li class=""><a href="#tab_3" data-toggle="tab">Contatos</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {!! Form::label('banco_id', 'Banco:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-10">
                                                    {!! Form::select('banco_id', $bancos, null, ['id'=>'banco_id', 'class' => 'form-control  selectChosen', 'style'=>'padding:0px;max-height:24px;']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('agencia', 'Agência:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::text('agencia',null,['class'=>'form-control input-sm number']) !!}
                                                </div>
                                                {!! Form::label('agenciadigito', 'Agência Dígito:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">

                                                    {!! Form::text('agenciadigito',null,['class'=>'form-control input-sm number', 'data-toggle' => "tooltip", 'data-trigger'=>"hover", 'data-placement'=>"bottom", 'title'=>'Substituir "x" por "0"']) !!}
                                                </div>
                                                {!! Form::label('postobeneficiario', 'Posto Beneficiário:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::text('postobeneficiario',null,['class'=>'form-control input-sm number']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-10">
                                                    {!! Form::text('descricao',null,['class'=>'form-control input-sm']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('ativo', 'Ativo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2 checkbox">
                                                    {{ Form::checkbox('ativo') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div><!-- /.tab-pane -->
                            </div><!-- /.tab-pane -->
                            <div class="tab-pane" id="tab_2">
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            @include('general.endereco_form_partial')
                                        </div>
                                    </div>
                                </div>
                            </div><!-- /.tab-pane -->
                            <div class="tab-pane" id="tab_3">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {!! Form::label('email', 'e-mail:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-9">
                                                    {!! Form::text('email',null,['class'=>'form-control input-sm']) !!}
                                                </div>
                                            </div>
                                            <div class="col-md-6  col-md-offset-4">
                                                {{Form::hidden('telefones',"", ['id'=>'telefones'])}}
                                                <table id="tblTelefones" class="table table-bordered table-hover table-condensed">
                                                    <thead>
                                                        <tr>
                                                            <th></th>
                                                            <th>Tipo Telefone</th>
                                                            <th>Número</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="agencias-list" name="agencias-list">
                                                        @if(isset($agencia))
                                                        @foreach ($agencia->telefones as $telefone)
                                                        <tr id="fone{{$telefone->telefonetipo_id}}">
                                                            <td>{{$telefone->telefonetipo_id}}</td>
                                                            <td>{{$telefone->telefonetipo->descricao}}</td>
                                                            <td>{{$telefone->telefone}}</td>
                                                            <td><button type='button' class='btn btn-danger btn-xs' id='btnRemoverTelefone'>Remover</button></td>
                                                        </tr>
                                                        @endforeach
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div><!-- /.box -->
                                            <div class="col-md-6  col-md-offset-4">
                                                <div class="col-md-4">
                                                    {!! Form::select('telefonetipo_id', $telefonetipos, null, ['id'=>'telefonetipo_id', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;']) !!}
                                                </div>
                                                <input type="text" id="telefone" class="col-md-4 telefone">
                                                <button type="button" id='btnAddFone' class="btn-nw-buscas col-md-2 " onclick="addFone();">Adicionar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div><!-- /.tab-pane -->
                        </div>
                        <div class="box-footer">
                            <div class="col-md-4">
                                {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                                <a type="button" href="{{url('agencia')}}" class="btn btn-nw-geral">Voltar</a>
                            </div>
                        </div>
                    </div>
                </div>
            </ul><!-- /.col -->
        </div>
        {!! Form::close() !!}
        @if(isset($agencia))
        <!--
        <div class="nav-tabs-custom">
        <div class="col-md-12">
        <div class="box-footer">
        <div class="col-md-4">
        {!! Form::open(['class' => 'delete', 'action' => ['AgenciaController@destroy', $agencia->id], 'method' => 'delete']) !!}
        {!! Form::submit('Remover', ['class'=>'btn btn-danger']) !!}
        {!! Form::close() !!}
    </div>
</div>
</div>
</div>
        -->
        @endif
    </div>
</div>
</div>
<div id="popup_capture" class="modal fade popupModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document" id="fundo_popup">
        <div class="modal-content">
            <div class="modal-header" id="popup_int">
                <button type="button" id="btnCloseCapture" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel" style="text-align: center;">Capturar Foto</h4>
            </div>
            <div class="container" style="margin-left:20px;">
                <video autoplay></video>
                <div class="photoArea" style="margin-left: 70px;"></div>
            </div>
            <canvas width='360' height='480' style="border: 1px solid #d3d3d3;display:none;"></canvas>
            <img id="foto_popup" width='180' height='240'></img>
            <div class="controls text-center">
                <input type="button" value="Iniciar câmera" onclick="startCapture()" />
                <input type="button" value="Capturar Foto" onclick="takePhoto()" />
                <!--
                <input type="button" value="Parar câmera" onclick="stopCapture()" />
                -->
            </div>
        </div>
    </div>
</div>
@include('general.popupbairrocidade_form_partial')
<!-- DATA TABES SCRIPT -->
<!-- page script -->
<script type="text/javascript">

    var t;
    var root = '{{url("/")}}';
    setTimeout(function () {


        @if (isset($show))
        desativarInputs();
        var ids = [".btnBuscarEndereco", '#btnBuscarCEP',
            '.novoCadEndereco', '#btnAddFone'];
        desativarInputsEspecificos(ids);

        @endif
                @if ($errors -> any())
        carregarTelefonesErro();
        @endif
    }, $(document).ready());

</script>
@endsection
