@extends('layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12"><!-- Custom Tabs -->
            @if($empconfig !== null)
            {{ Form::model($empconfig, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true, 'route' => array('empresaconfig.update', $empconfig->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro','route' => 'empresaconfig.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Configurações da Empresa</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Pedido</a></li>
                            <li class=""><a href="#tab_2" data-toggle="tab">E-Mail</a></li>
                            <li class=""><a href="#tab_3" data-toggle="tab">Financeiro</a></li>
                            <li class=""><a href="#tab_4" data-toggle="tab">Diversos</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                <div class="col-md-2 col-md-push-1">
                                                    <h4>Pedidos</h4>
                                                </div>
                                            </div>
                                            @include('empresaconfig.partials.section_pedidos')
                                            <hr />
                                            <div class="form-group crud_space">
                                                <div class="col-md-2 col-md-push-1">
                                                    <h4>NFCe</h4>
                                                </div>
                                            </div>
                                            @include('empresaconfig.partials.section_nfce')
                                            <hr />
                                            <div class="form-group crud_space">
                                                <div class="col-md-2 col-md-push-1">
                                                    <h4>App NF</h4>
                                                </div>
                                            </div>
                                            @include('empresaconfig.partials.section_appnf')
                                            <hr />
                                            <div class="form-group crud_space">
                                                <div class="col-md-2 col-md-push-1">
                                                    <h4>Convênio NF</h4>
                                                </div>
                                            </div>
                                            @include('empresaconfig.partials.section_convenionf')
                                            <hr />
                                            <div class="form-group crud_space">
                                                <div class="col-md-2 col-md-push-1">
                                                    <h4>Android</h4>
                                                </div>
                                            </div>
                                            @include('empresaconfig.partials.section_android')
                                            <hr />
                                            <div class="form-group crud_space">
                                                <div class="col-md-2 col-md-push-1">
                                                    <h4>Impressão Pedido</h4>
                                                </div>
                                            </div>
                                            @include('empresaconfig.partials.section_impressao')
                                            <hr />
                                            <div class="form-group crud_space">
                                                <div class="col-md-2 col-md-push-1">
                                                    <h4>Gás do Povo</h4>
                                                </div>
                                            </div>
                                            @include('empresaconfig.partials.section_gasdopovo')
                                            <hr />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane" id="tab_2">
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            @include('empresaconfig.partials.aba_email')
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane" id="tab_3">
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10 col-md-push-1">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                <div class="form-group crud_space">
                                                    <div class="col-md-2 col-md-push-1">
                                                        <h4>Financeiro</h4>
                                                    </div>
                                                </div>
                                                @include('empresaconfig.partials.section_financeiro')
                                                <hr />
                                                <div class="form-group crud_space">
                                                    <div class="col-md-2 col-md-push-1">
                                                        <h4>D.R.E.</h4>
                                                    </div>
                                                </div>
                                                @include('empresaconfig.partials.section_dre')
                                                <hr />
                                                <div class="form-group crud_space">
                                                    <div class="col-md-2 col-md-push-1">
                                                        <h4>API PIX</h4>
                                                    </div>
                                                </div>
                                                @include('empresaconfig.partials.section_pix')
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane" id="tab_4">
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                <div class="col-md-2 col-md-push-1">
                                                    <h4>Diversos</h4>
                                                </div>
                                            </div>
                                            @include('empresaconfig.partials.section_diversos')
                                            <hr />
                                            <div class="form-group crud_space">
                                                <div class="col-md-2 col-md-push-1">
                                                    <h4>Ressarcimento</h4>
                                                </div>
                                            </div>
                                            @include('empresaconfig.partials.section_ressarcimento')
                                            <hr />
                                            <div class="form-group crud_space">
                                                <div class="col-md-3 col-md-push-1">
                                                    <h4>Mensagens Vale Gás</h4>
                                                </div>
                                            </div>
                                            @include('empresaconfig.partials.section_valegas')
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <div class="col-md-4">
                            @can('update', App\Empresaconfig::class)
                                {{ Form::submit('Gravar', ['id'=>'btngravar','class' => 'btn btn-nw-registro']) }}
                                <a id="habilitaredicao" type="button" class="btn btn-nw-geral">Habilitar Edição</a>
                            @endcan
                        </div>
                    </div>
                </div>
            </ul>
            {{ Form::close() }}
        </div>
    </div>
</div>
@include('general.modal_senhamestra')

@include('financeiro.centrocustos_partial1_js')
@include('financeiro.centrocustos_partial2_js')
@include('financeiro.centrocustos_partial1')
@include('financeiro.planocontas_partial1_js')
@include('financeiro.planocontas_partial2_js')
@include('financeiro.planocontas_partial1')

<script type="text/javascript" src="{{URL::to('js/empresaconfig.js')}}"></script>
<script type="text/javascript">
var root = '{{url("/")}}';
@if($errors->any())
    errorsany = true;
@else
    errorsany = false;
@endif
</script>
@endsection
