@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Relatório de Notas Fiscais Emitidas</h3>
                    </div>
                    <!-- /.box-header -->
                    {{ Form::open(['id' => 'fmFiltros','class'=>'form-horizontal'])}}
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Nota Fiscal Emitida</a></li>
                            <li class=""><a href="#tab_2" data-toggle="tab">Nota Fiscal Emitida por Produto</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                @include('reports.nf.emitidas.partials.nfemitida')
                            </div>
                            <div class="tab-pane" id="tab_2"><!-- form start -->
                                @include('reports.nf.emitidas.partials.nfemitidaproduto')
                            </div>
                        </div>
                    </div><!-- /.content-wrapper -->
                    {{ Form::close() }}
                </div>
            </div>
        </div>
    </div>
</div>
@include('general.modal_report_iframe')
<script type="text/javascript" src="{{asset('js/reportnfemitidas.js')}}"></script>
@endsection