@extends('layouts.mainmenu')
@section('content')

<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Estoques Gerais</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Estoque Geral</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'reportestoquegeral','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('setor_id',$setores,null,['id' => 'setor_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('classe_id', 'Classe:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('classe_id',$classes,null,['id' => 'classe_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                <div id="checkbox">
                                                    {!! Form::label('zerado', 'Mostrar Estoque Zerado', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                    <div class="col-md-1 checkbox">
                                                        {!! Form::checkbox('zerado',0) !!}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('produto_id', 'Produto:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('produto_id',$produtos,null,['id' => 'produto_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                <div class="col-sm-2 col-sm-push-1">
													<button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.estoquegeral')}}'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
													<button type="button" id='gerarPdfGeral' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
													<button id="btnFiltroGeral" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
												</div>
                                            </div>
                                        </div>
                                        <!-- /.box-body -->
                                    </div>
                                    {{ Form::close() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <!-- /.content-wrapper -->
        </div>
    </div>
</div>
@include('general.modal_report_iframe')
<script type="text/javascript" src="{{URL::to('js/reportestoque.js')}}"></script>
<script type="text/javascript">
</script>
@endsection