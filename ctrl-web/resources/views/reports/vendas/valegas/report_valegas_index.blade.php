@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <ul>
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Relatórios de Vale Gás</h3>
                        </div>
                        <!-- /.box-header -->
                        {{ Form::open(['id' => 'reportvalegas','class'=>'form-horizontal'])}}
                        <div class="nav-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_1" data-toggle="tab">Vale Gás Pendente</a></li>
                                <li class=""><a href="#tab_2" data-toggle="tab">Vale Gás Baixado</a></li>
                                <li class=""><a href="#tab_3" data-toggle="tab">Venda de Vale Gás</a></li>
                                <li class=""><a href="#tab_5" data-toggle="tab">Pedidos de Vale Gás</a></li>
                                <li class=""><a href="#tab_4" data-toggle="tab">Estoque a Considerar de Vale Gás</a></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">
                                    <div class="row">
                                        <div id="tabCadastro" class="col-md-10">
                                            <div class="box-body">
                                                @include('reports.vendas.valegas.pendente.pendente')
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="tab_2">
                                    <div class="row">
                                        <div id="tabCadastro" class="col-md-10">
                                            <div class="box-body">
                                                @include('reports.vendas.valegas.baixado.baixado')
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="tab_3">
                                    <div class="row">
                                        <div id="tabCadastro" class="col-md-10">
                                            <div class="box-body">
                                                @include('reports.vendas.valegas.venda.venda')
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="tab_5">
                                    <div class="row">
                                        <div id="tabCadastro" class="col-md-10">
                                            <div class="box-body">
                                                @include('reports.vendas.valegas.pedido.pedido')
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="tab_4">
                                    <div class="row">
                                        <div id="tabCadastro" class="col-md-10">
                                            <div class="box-body">
                                                @include('reports.vendas.valegas.estoque.estoque')
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{Form::close()}}
                    </div>
                </ul>
            </div>
        </div>
    </div>
</div>
@include('general.modal_report_iframe')
<script type="text/javascript" src="{{URL::to('js/reportvalegas.js')}}"></script>
@endsection