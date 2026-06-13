@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            @if(isset($imposto))
            {{ Form::model($imposto, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('nfimposto.update', $imposto->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'nfimposto.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Imposto</h3>
                    </div><!-- /.panel-heading -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Impostos Gerais - PJ</a></li>
                            <li class=""><a href="#tab_2" data-toggle="tab">Impostos Gerais - PF</a></li>
                            <li class=""><a href="#tab_3" data-toggle="tab">ICMS por Estado</a></li>
                            <li class=""><a href="#tab_4" data-toggle="tab">Crédito PIS/Cofins</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            @include('nf.nfimpostos.partials.tab_1')
                                        </div>
                                    </div>
                                </div><!-- /.row -->
                            </div><!-- /.tab-pane -->
                            <div class="tab-pane" id="tab_2">
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            @include('nf.nfimpostos.partials.tab_2')
                                        </div>
                                    </div>
                                </div><!-- /.row -->
                            </div><!-- /.tab-pane -->
                            <div class="tab-pane" id="tab_3">
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            @include('nf.nfimpostos.partials.tab_3')
                                        </div>
                                    </div>
                                </div><!-- /.row -->
                            </div><!-- /.tab-pane -->
                            <div class="tab-pane" id="tab_4">
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            @include('nf.nfimpostos.partials.tab_4')
                                        </div>
                                    </div>
                                </div><!-- /.row -->
                            </div>
                        </div><!-- /.tab-pane -->
                    </div><!-- /.tab-content -->
                    {{Form::hidden('index', null)}}
                    <div class="box-footer">
                        <div class="col-md-4">
                            {{ Form::submit('Gravar', ['id'=>'btngravar','class' => 'btn btn-nw-registro']) }}
                            <a type="button" id="goback" class="btn btn-nw-geral">Voltar</a>
                        </div>
                    </div>
                </div>
                {{ Form::close() }}
            </ul>
        </div>
    </div>
</div>
<link href="{{asset('css/lib/great-table.css')}}" rel="stylesheet" type="text/css" />

<script type="text/javascript" src="{{asset('js/lib/great-table.js')}}"></script>
<script type="text/javascript" src="{{asset('js/impostosCustom.js')}}"></script>
<script type="text/javascript" src="{{asset('js/lib/collection.js')}}"></script>
@include('nf.nfimpostos.partials.js')
@endsection
