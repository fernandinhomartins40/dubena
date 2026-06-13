@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($Empresa))
            {{ Form::model($Empresa, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('empresa.update', $Empresa->id))) }}
            <input type="hidden" name="empresa_up" id="empresa_up" value="{{ $Empresa->id }}">
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
                            <li class=""><a href="#tab_2" data-toggle="tab">Documentos Fiscais</a></li>
                            <li class=""><a href="#tab_3" data-toggle="tab">Sped</a></li>
                            <li class=""><a href="#tab_4" data-toggle="tab">Contabilista</a></li>
                            <li class=""><a href="#tab_5" data-toggle="tab">Dados para Contrato</a></li>
                            <li class=""><a href="#tab_6" data-toggle="tab">Logotipo</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                @include('empresas.partials.tab_1')
                            </div><!-- /.tab-pane 1 -->
                            <div class="tab-pane" id="tab_2">
                                @include('empresas.partials.tab_2')
                            </div><!-- /.tab-pane 2 -->
                            <div class="tab-pane" id="tab_3"><!-- form start -->
                                @include('empresas.partials.tab_3')
                            </div><!-- /.tab-pane 3-->
                            <div class="tab-pane" id="tab_4"><!-- form start -->
                                @include('empresas.partials.tab_4')
                            </div>
                            <div class="tab-pane" id="tab_5">
                                @include('empresas.partials.tab_5')
                            </div>
                            <div class="tab-pane" id="tab_6">
                                @include('empresas.partials.tab_6')
                            </div>
                        </div><!-- /.tab-pane -->
                        <div class="box-footer">
                            <div class="col-md-4">
                                {{ Form::submit('Gravar', ['id' => 'btnGravar', 'class' => 'btn btn-nw-registro']) }}
                                <a href='{{url("empresa")}}' type="button" class="btn btn-nw-geral">Voltar</a>
                            </div>
                        </div>
                    </div>
                    {{ Form::close() }}
                </div><!-- /.col -->
            </ul>
        </div>
    </div>
</div>
@include('general.popupbairrocidade_form_partial')
@include('empresas.reg1010_modal')
<link href="{{URL::to('css/cropbox.css')}}" rel="stylesheet" type="text/css" />
<script src="{{URL::to('plugins/cropbox/cropbox.js')}}"></script>
<script src="{{URL::to('js/empresa.js')}}"></script>
<style>
    .valido {
        border: 1px solid green;
    }
    .invalido {
        border: 1px solid red;
    }
    #registro_modal .modal-dialog {
        overflow-y: initial !important
    }
    #registro_modal .modal-body {
        height: 450px;
        overflow-y: hidden;
    }
</style>
<script>
    var show = false;
    setTimeout(function () {
        showHideCrt();
        @if (!isset($show))
            treatInputsNotShow();
        @else
            show = true;
            treatInputsShow();
        @endif
        @if ($errors -> any())
            treatErrors();
        @endif
    }, $(document).ready());
    @if (isset($Empresa))
        $("#cidade_erro").val('{{$Empresa->cidade_id}}');
        $("#bairro_erro").val('{{$Empresa->bairro_id}}');
        $("#rua_erro").val('{{$Empresa->rua_id}}');
    @endif
</script>
@endsection
