@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            @if($tiponf === "emitida")
                @if(isset($nfemitida))
                    {{ Form::model($nfemitida,
                        [
                        'id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('nfemitida.update', $nfemitida->id)
                        ]) }}
                @else
                    {{ Form::open(['id'=>'fmCadastro', 'route' => 'nfemitida.store', 'class' => 'form-horizontal', 'files' => true]) }}
                @endif
            @else
                @if(isset($nfrecebida))
                    {{ Form::model($nfrecebida,
                    [
                    'id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('nfrecebida.update', $nfrecebida->id)
                    ]) }}
                @else
                    {{ Form::open(['id'=>'fmCadastro', 'route' => 'nfrecebida.store', 'class' => 'form-horizontal', 'files' => true]) }}
                @endif
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            @if($tiponf === "emitida")
                                Emissão de Nota Fiscal
                                <div class="fright">
                                    <div id="info-ambiente55" style="display: none; color: red;">
                                        @if($empresa->nfetipoambiente == 2)
                                            NF-e em Homologação
                                        @endif
                                        @if(isset($show) && ($empresa->nfetipoambiente == 2 || $empresa->nfcetipoambiente == 2))
                                            <input type="button" id="duplicateNf" class="btn btn-xs btn-info" value="Duplicar"/>
                                        @endif
                                    </div>
                                    <div id="info-ambiente65" style="display: none; color: red;">
                                        @if($empresa->nfcetipoambiente == 2)
                                            NFC-e em Homologação
                                        @endif
                                        @if(isset($show) && ($empresa->nfetipoambiente == 2 || $empresa->nfcetipoambiente == 2))
                                            <input type="button" id="duplicateNf2" class="btn btn-xs btn-info" value="Duplicar"/>
                                        @endif
                                    </div>
                                </div>
                            @else
                                Lançamento de Documentos
                                @if(! isset($nfrecebida) && ! $errors->any())
                                    <div class="fright">
                                        <button class="btn btn-nw-registro btn-xs" id="btnImportXml" type="button">Importar XML</button>
                                    </div>
                                @endif
                            @endif
                        </h3>
                    </div>
                    <div class="nav-tabs-custom">
                        {{Form::hidden('tiponf', $tiponf)}}
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Definições</a></li>
                            <li><a href="#tab_2" data-toggle="tab" id="anchorTab2">Emitente</a></li>
                            <li><a href="#tab_3" data-toggle="tab" id="anchorTab3">Destinatário</a></li>
                            <li><a href="#tab_4" data-toggle="tab">Frete</a></li>
                            <li><a href="#tab_5" data-toggle="tab">Itens</a></li>
                            <li><a href="#tab_6" data-toggle="tab">Financeiro</a></li>
                            <li><a href="#tab_7" data-toggle="tab">Rateio de PC e CC</a></li>
                            @if($tiponf === "emitida")
                                <li><a href="#tab_8" data-toggle="tab">Operações/Status</a></li>
                            @endif
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                {{ Form::hidden("duplicate", "0", ["id" => "duplicate"]) }}
                                @if($tiponf === "emitida")
                                    @include('nf.partials.emitida_tabs.tab_1')
                                @else
                                    @include('nf.partials.recebida_tabs.tab_1')
                                @endif
                            </div>
                            <div class="tab-pane" id="tab_2">
                                @include('nf.partials.general_tabs.tab_2')
                            </div>
                            <div class="tab-pane" id="tab_3">
                                @include('nf.partials.general_tabs.tab_3')
                            </div>
                            <div class="tab-pane" id="tab_4">
                                @include('nf.partials.general_tabs.tab_4')
                            </div>
                            <div class="tab-pane" id="tab_5">
                                @include('nf.partials.general_tabs.tab_5')
                            </div>
                            <div class="tab-pane" id="tab_6">
                                @include('nf.partials.general_tabs.tab_6')
                            </div>
                            <div class="tab-pane" id="tab_7">
                                @include('nf.partials.general_tabs.tab_7')
                            </div>
                            @if($tiponf === "emitida")
                                <div class="tab-pane" id="tab_8">
                                    @include('nf.partials.emitida_tabs.tab_8')
                                </div>
                            @endif
                            <div class="box-footer">
                                <div class="col-md-4">
                                    {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro', 'id' => 'btnCadastro']) !!}
                                    <!--{{$urlBack = str_replace("extPar", "&", Input::get('index', route("nf{$tiponf}.index")))}}-->
                                    <a type="button" href="{{$urlBack}}" class="btn btn-nw-geral">Voltar</a>
                                    @if($tiponf === "emitida")
                                        @if(isset($nfemitida) && isset($show) && $show && !App\Helpers\Utils\NfUtil::isAuthorized($nfemitida->nfsituacao_id))
                                            @can('update', $nfemitida)
                                                <a id="btnEditar" type="button" href="{{ route("nfemitida.edit", $nfemitida->id) . '?index=' . Input::get('index', route("nf{$tiponf}.index")) }}" class="btn btn-nw-registro">Editar</a>
                                            @endcan
                                        @endif
                                    @else
                                        @if(isset($nfrecebida) && isset($show) && $show)
                                            @can('updateForm', App\Nfrecebida::class)
                                                <a id="btnEditar" type="button" href="{{ route("nfrecebida.edit", $nfrecebida->id) . '?index=' . Input::get('index', route("nf{$tiponf}.index")) }}" class="btn btn-nw-registro">Editar</a>
                                            @endcan
                                            @can('updateForm', App\Nfrecebida::class)
                                                <a id="btnImprimir" type="button" onclick="printRegister({{$nfrecebida->id}});" class="btn btn-nw-buscas">Imprimir</a>
                                            @endcan
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div> <!-- nav-tabs-custom -->
                </div> <!-- panel-default -->
            </ul>
            {{Form::hidden("index", Input::get('index', route("nf{$tiponf}.index")), ['id' => "index"])}}
                @if($tiponf === "recebida")
                    {{Form::hidden("xml_import_json", @$xmlImport, ['id' => "xml_import_json"])}}
                    {{Form::hidden("xml", null, ['id' => "xml"])}}
                @endif
            {!! Form::close() !!}
        </div>
        @if ($tiponf === "emitida")
            {{Form::hidden("cfop_venda", $cfopVenda->toJson(), ['id' => "cfop_venda"])}}
            {{Form::hidden("objOperacoes", $nfoperacaos->toJson(), ['id' => "objOperacoes"])}}
        @else
            {{Form::hidden("objOperacoesRecebidas", $operacaorecebida->toJson(), ['id' => "objOperacoesRecebidas"])}}
            {{Form::hidden("objOperacoesEmitidas", $operacaoemitida->toJson(), ['id' => "objOperacoesEmitidas"])}}
        @endif
        {{Form::hidden("cProdAnpGLP", json_encode($cProdAnpGLP), ['id' => "cProdAnpGLP"])}}
        {{Form::hidden("cfopGLP", json_encode($cfopGLP), ['id' => "cfopGLP"])}}
        @include('general.modal_parcelas')
    </div>
</div>

<script src="{{URL::to('js/customajax.js')}}"></script>
<script src="{{URL::to('js/customajaxext.js')}}"></script>
<script src="{{URL::to('js/nfGeneral.js')}}"></script>
<script src="{{URL::to('js/nfCommon.js')}}"></script>
@if($tiponf === "emitida")
    <div class="modal fade" id="myModalCCE" tabindex="-1" role="dialog" aria-labelledby="myModalLabelCCE" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                    <h4 class="modal-title" id="myModalLabelCCE">Carta de Correção</h4>
                </div>
                {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmCCEAjax']) }}
                <div class="modal-body">
                    <div class="box-body">
                        <div class="form-group crud_space col-sm-12">
                            {!! Form::label('xCorrecao', 'Correção:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                            <div class="col-sm-10">
                                {!! Form::textarea('xCorrecao',null,['rows'=>'3', 'class'=>'form-control input-sm', 'id'=>'xCorrecao']) !!}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="btnCloseCCE" class="btn btn-nw-buscas btn-sm" data-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-sm btn-nw-registro" id="btnProcessCCE">Enviar Correção</button>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
@endif

<script src="{{URL::to("js/lib/collection.js")}}"></script>
<script src="{{asset('js/freteNf.js')}}"></script>
<script src="{{URL::to("js/nf".$tiponf.".js")}}"></script>

@include("nf.partials.js")

@include('financeiro.centrocustos_partial1_js')
@include('financeiro.planocontas_partial1_js')

@include('financeiro.centrocustos_partial2_js')
@include('financeiro.planocontas_partial2_js')

@include('financeiro.centrocustos_partial1')
@include('financeiro.planocontas_partial1')

@if($tiponf === "emitida")
    @include('nf.partials.modal_finalidade2')
@else
    @include('general.modals.upload_file')
    @include('nf.partials.modals_import_xml')
@endif


@include('general.modal_report_iframe')

@endsection
