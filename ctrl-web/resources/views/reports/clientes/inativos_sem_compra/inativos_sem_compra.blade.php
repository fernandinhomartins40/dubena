@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Inativos por Falta de Compra</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Inativos por Falta de Compra</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
								<div class="row">
									<div id="tabCadastro" class="col-sm-11 col-sm-offset-4">
										<div class="box-body">
											{{ Form::open(['id' => 'fmFiltros','class'=>'form-horizontal'])}}
											<b><i style="margin-left: 45px">Número de dias estabelecido na config.</i></b>
											<br />
											<br />
											<div class="form-group crud_space">
												<div class="col-sm-4 col-sm-pull-1">
													{{ Form::label('dias', 'Inativos a ', ['class'=>'col-sm-6 control-label input-sm']) }}
													<div class="col-sm-4">
														{{Form::text('dias', null, (['id' => 'dias', 'class' => 'input-sm form-control number', 'readonly', 'tab-index' => '-1']))}}
													</div>
													{{ Form::label('dias', 'dias.', ['class'=>'col-sm-2 control-label input-sm', 'style' => 'margin-left: -25px']) }}
												</div>
												<div class="col-sm-2 col-sm-pull-1">
													<!-- <button type="button" id='btnLimpar-tab_3' class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button> -->
													<!-- <button type="button" id='btnGerarPDF' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
													 --><button id="btnIframe-tab_3" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
												</div>
											</div> 
											{{ Form::close() }}
										</div>
									</div>
								</div>
							</div>
                        </div>
                    </div>
                    <!-- /.content-wrapper -->
                </div>
            </div>
        </div>
    </div>
</div>
@include('general.modal_report_iframe')

<script type="text/javascript">
	var dias = '{{$dias}}';
	$("#dias").attr('min', dias).on('focusout', function  () {
		if(parseInt($(this).val()) < parseInt(dias))
			$(this).val(dias);
	}).val(dias);
	$("#btnLimpar-tab_3").on('click', function() {
		mudaTipoFiltro(0);
		$("#dias").val(dias);
	});
	$("#btnIframe-tab_3").on('click', function () {
		setUrl(function (url) {
			$("#popup_relatorio").modal('show');
			$("#iFrameReport").attr('src',url);
		}, false)
	});
	function setUrl(callback, pdf) {
		var url = root + '/report.inativoFaltaCompra';
		if(typeof pdf != 'undefined' && pdf)
			url += '.pdf';
		url += '?dias=:dias';
		url = url.replace(':dias', $("#dias").val());

		callback(url);
	}
</script>
@endsection