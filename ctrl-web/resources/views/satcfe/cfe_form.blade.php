@extends('layouts.mainmenu')
@section('content')
    <div id="mainContent" class="content">
        <div id="divCadastro" class="row">
            <div class="col-md-12">
                @if(!is_null($cupomFiscal->id))
                    {{ Form::model($cupomFiscal, ['id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('satcfe.update', $cupomFiscal->id)]) }}
                @else
                    {{ Form::open(['id'=>'fmCadastro', 'route' => 'satcfe.store', 'class' => 'form-horizontal', 'files' => true]) }}
                @endif
                <ul>
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                Emissão de SAT CF-e
                                <span class="fright" style="color: red;">
                                    SAT em
                                    @if($empresa->sattipoambiente == 2)
                                        Homologação
                                    @else
                                        Produção
                                    @endif
                                </span>
                            </h3>
                        </div>
                        <div class="nav-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_1" data-toggle="tab">Definições</a></li>
                                <li><a href="#tab_2" data-toggle="tab">Emitente</a></li>
                                <li><a href="#tab_3" data-toggle="tab">Destinatário</a></li>
                                <li><a href="#tab_4" data-toggle="tab">Itens</a></li>
                                <li><a href="#tab_5" data-toggle="tab">Financeiro</a></li>
                                <li><a href="#tab_6" data-toggle="tab">Rateio de PC e CC</a></li>
                                <li><a href="#tab_7" data-toggle="tab">Operações/Status</a></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">
                                    @include('satcfe.partials.tab_1')
                                </div>
                                <div class="tab-pane" id="tab_2">
                                    @include('satcfe.partials.tab_2')
                                </div>
                                <div class="tab-pane" id="tab_3">
                                    @include('satcfe.partials.tab_3')
                                </div>
                                <div class="tab-pane" id="tab_4">
                                    @include('satcfe.partials.tab_4')
                                </div>
                                <div class="tab-pane" id="tab_5">
                                    @include('satcfe.partials.tab_5')
                                </div>
                                <div class="tab-pane" id="tab_6">
                                    @include('satcfe.partials.tab_6')
                                </div>
                                <div class="tab-pane" id="tab_7">
                                    @include('satcfe.partials.tab_7')
                                </div>
                                vdescsubtot
                                vacressubtot
                                vcfelei12741
                            </div>
                            <div class="box-footer">
                                <div class="col-md-4">
                                <!--{{$urlBack = Input::get('index', route("satcfe.index"))}}-->
                                    @if(! $show)
                                        {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro', 'id' => 'btnCadastro']) !!}
                                        <a type="button" href="{{$urlBack}}" class="btn btn-nw-geral">Voltar</a>
                                    @else
                                        <a type="button" href="{{$urlBack}}" class="btn btn-nw-geral">Voltar</a>
                                        <a type="button" href="{{route("satcfe.edit", $cupomFiscal->id)}}" id="actionEdit" class="btn btn-nw-registro">Editar</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div> <!-- nav-tabs-custom -->
                </ul>
            </div>
        </div>
        @include('general.modal_parcelas')
    </div>

    <script src="{{URL::to('js/customajax.js')}}"></script>
    <script src="{{URL::to('js/customajaxext.js')}}"></script>
    <script src="{{URL::to('js/satCFe.js')}}"></script>
    <script src="{{URL::to('js/nfCommon.js')}}"></script>

    <script src="{{URL::to("js/lib/collection.js")}}"></script>

    @include("satcfe.partials.js")

    @include('financeiro.centrocustos_partial1_js')
    @include('financeiro.planocontas_partial1_js')

    @include('financeiro.centrocustos_partial2_js')
    @include('financeiro.planocontas_partial2_js')

    @include('financeiro.centrocustos_partial1')
    @include('financeiro.planocontas_partial1')

@endsection