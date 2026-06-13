@extends('layouts.mainmenu')
@section('content')

<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Acompanhamento de Clientes por Promoções</h3>
                    </div><!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Acompanhamento de Cliente por Promoção</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1"><!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'reportpromocoescli','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {{ Form::label('promocoes', 'Promoção:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('promocoes',$promocoes,null,['id' => 'promocoes','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                <div class="col-sm-2">
                                                    <button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.acompanhamentopromo')}}'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                    <button id="btnFiltroPromo" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('filtrar', 'Compras ', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('maiormenor',[1=>'Acima',2=>'Abaixo'],null,['id' => 'maiormenor','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('filtrar', 'De ', ['class'=>'col-sm-1 control-label input-sm','style'=>'margins-left:-15px;']) }}
                                                <div class="col-sm-1">
                                                    {{ Form::text('compras',null,['id'=>'compras','class'=>'form-control input-sm']) }}
                                                </div>
                                            </div>
                                        </div><!-- /.box-body -->
                                    </div>
                                    {{ Form::close() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- /.content-wrapper -->
        </div>
    </div>
</div>
@include('general.modal_report_iframe')
<script type="text/javascript" src="{{URL::to('js/reportpromo.js')}}"></script>
<script type="text/javascript">
</script>
@endsection