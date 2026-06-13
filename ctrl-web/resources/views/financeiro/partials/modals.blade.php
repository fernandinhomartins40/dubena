
<div style="display:none;">
	<form method="get" target="iframeFinanceiro" id="fmAbrirFinanceiro">
		<input type="submit" value="Do Stuff!" />
		<input type="text" id="tipo_lancamento" name="tipo_lancamento" />
		<input type="text" id="conta_id" name="conta_id" />
		<input type="text" id="contafechamento_id" name="contafechamento_id" value="{{(isset($contafechamento)?$contafechamento->id:-1)}}"/>
	</form>
</div>
<div id="popup_financeiro" class="modal fade popupModal modal-wide" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog" role="document" id="fundo_popup">
		<div class="modal-content">
			<div id="popup_int" style="text-align:center;">
				<button type="button" id="btnCloseFinanceiro" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

				<iframe sandbox="allow-same-origin allow-scripts allow-popups allow-forms" id="iframeFinanceiro" name="iframeFinanceiro" style="border: 0; width:100%; height:500px;margin-top:-20px;"></iframe>
			</div>
		</div>
	</div>
</div>
<div style="display:none;">
	<form method="get" target="iframeReceberCaixa" id="fmAbrirRecebimento">
		<input type="submit" value="Do Stuff!" />
		<input type="text" id="conta_id_receber" name="conta_id_receber" />
		<input type="text" id="parcelas" name="parcelas" />
		<input type="text" id="empresa_razao_social" name="empresa_razao_social" />
		<input type="text" id="empresa_cnpj" name="empresa_cnpj" />
	</form>
</div>
<div id="popup_recebercaixa" class="modal fade popupModal modal-wide" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog" role="document" id="fundo_popup">
		<div class="modal-content">
			<div id="popup_int" style="text-align:center;">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

				<iframe sandbox="allow-same-origin allow-scripts allow-popups allow-forms" id="iframeReceberCaixa" name="iframeReceberCaixa" style="border: 0; width:100%; height:500px;margin-top:-20px;"></iframe>
			</div>
		</div>
	</div>
</div>
<div class="modal fade popupModal" id="popup_fecharcaixa" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
				<h4 class="modal-title" id="myModalLabelFecharCaixa">Confirmar fechamento do caixa</h4>
			</div>
			<div class="modal-body  center text-center">
				<div class="box-body center text-center">
					<div class="form-group crud_space col-sm-12">
						{!! Form::label('data_fechamento', 'Data de Fechamento:', ['class'=>'col-sm-3 control-label input-sm','style'=>'text-align:right;']) !!}
						<div class="col-sm-9">
							<div class="input-group date generalDateAll" id="datetimepicker1">
								{!! Form::text('data_fechamento',null,['class'=>'form-control input-sm']) !!}
								<span class="input-group-addon">
									<span class="glyphicon glyphicon-calendar"></span>
								</span>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" id="btnCloseFecharCaixa" class="btn btn-default" data-dismiss="modal">Cancelar</button>
				<button type="button" id="btnFecharCaixa" class="btn btn-primary" onclick="fecharCaixa();">Fechar Caixa</button>
			</div>
		</div>
	</div>
</div>
<div class="modal fade popupModal" id="popup_transferircaixa" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
				<h4 class="modal-title" id="myModalLabelFecharCaixa">Transferência de Valor do Caixa</h4>
			</div>
			<div class="modal-body  center text-center">
				<div class="box-body center text-center">
					<div class="form-group crud_space col-sm-12">
						{!! Form::label('data_transferencia', 'Data/Hora:', ['class'=>'col-sm-3 control-label input-sm','style'=>'text-align:right;']) !!}
						<div class="col-sm-5">
							<div class="input-group date generalDateAll" id="datetimepicker2">
								{!! Form::text('data_transferencia',null,['class'=>'form-control input-sm']) !!}
								<span class="input-group-addon">
									<span class="glyphicon glyphicon-calendar"></span>
								</span>
							</div>
						</div>
					</div>
					<div class="form-group crud_space col-sm-12">
						{!! Form::label('conta_idT', 'Conta Destino:', ['class'=>'col-sm-3 control-label input-sm','style'=>'text-align:right;']) !!}
						<div class="col-sm-7">
							{!! Form::select('conta_idT', $contastransf, null, ['class' => 'form-control input-sm', 'style'=>'border-radius: 5px ! important;']) !!}
						</div>
					</div>
					<div class="form-group crud_space col-sm-12">
						{!! Form::label('valorT', 'Valor:', ['class'=>'col-sm-3 control-label input-sm']) !!}
						<div class="col-sm-5">
							{!! Form::text('valorT',null,['class'=>'form-control input-sm dinheiro', 'id'=>'valorT']) !!}
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" id="btnCloseTransferirCaixa" class="btn btn-default" data-dismiss="modal">Cancelar</button>
				<button type="button" id="btnTransferirCaixa" class="btn btn-primary" onclick="transferirCaixa();">Transferir</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade popupModal" id="popup_progress" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
				<div id="divProgress"></div>
			</div>
			<div class="modal-body  center text-center">
				<div class="box-body center text-center">
					<div id="myProgress">
						<div id="myBar"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>