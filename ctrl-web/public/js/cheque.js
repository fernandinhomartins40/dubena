$(document).ready(function () {
	initSelectize();
});

function inicializarTabelas() {
	if(typeof chequeRecebido == 'undefined') {
		tblChequeEmitido = $("#tblChequeEmitido").DataTable({
			"language": {"url": urlDataTable},
			"processing": false,
			"bPaginate": false,
			"bLengthChange": false,
			"bFilter": false,
			"bSort": false,
			"bInfo": false,
			"bAutoWidth": false
		});
	} else {
		tblChequeRecebido = $("#tblChequeRecebido").DataTable({
			"language": {"url": urlDataTable},
			"processing": false,
			"bPaginate": false,
			"bLengthChange": false,
			"bFilter": false,
			"bSort": false,
			"bInfo": false,
			"bAutoWidth": false
		});
	}
}

function carregarParcelasErro() {
	var parcelas = JSON.parse($("#parcelas").val());

	if(parcelas.length == 1)
		$(".divUnicoCheque").hide();

	$.each(parcelas, function(index, el) {
		tblChequeEmitido.row.add([
			el[0],
			el[1],
			el[2],
			el[3],
			el[4],
			el[5],
			el[6],
			el[7],
			el[8],
			el[9]
		]).draw(false);
	});
}

$(document).on("shown.bs.modal", ".bootbox.modal", function (e) {
	$(".dontHideEsc").removeAttr('tabindex');
});

function bootboxConfirm(numero, acao, url, redirect, callback) {
	bootbox.confirm({
		title: "Atenção!",
		className: "warning",
		message: "Deseja " + acao + " o cheque número "+ numero +"?",
		buttons: {
			confirm: {
				label: "Sim",
				className: "btn-nw-registro"
			},
			cancel: {
				label: "Não",
				className: "btn-nw-geral"
			}
		},
		callback: function (result) {
			if (result) {
				if(acao == 'cancelar') {
					bootbox.confirm({
						title: "Atenção!",
						className: "dontHideEsc",
						message: "Deseja inutilizar esse cheque (Sim) ou utiliza-lo posteriormente (Não)?",
						buttons: {
							confirm: {
								label: "Sim",
								className: "btn-nw-registro"
							},
							cancel: {
								label: "Não",
								className: "btn-nw-geral"
							}
						},
						backdrop: true,
						closeButton: false,
						callback: function (res) {
							if (res) {
								url = url.replace('cancelar', 'cancelarinutilizar');
								editarCheque(url, redirect);
							} else {
								editarCheque(url, redirect);
							}
						}
					});
				} else {
					if(typeof callback == 'function')
						callback();
					else
						editarCheque(url, redirect);
				}
			}
		}
	});
};

function editarCheque(url, redirect, method, formData) {
	if(typeof method == 'undefined')
		method = "GET";
	ajaxGenerator(url, method,
		function (data) {
			if (data.substr(0, 3) === "OK|") {
				var dialog = bootbox.dialog({
					title: 'Operação realizada com sucesso!',
					message: '<p><i class="fa fa-spin fa-spinner"></i> Aguarde, você será redirecionado..</p>'
				});
				dialog.init(function () {
					window.setTimeout("location.href='" + redirect + "'", 1500);
				});
			} else {
				bootbox.alert('Erro: ' + data);
			}
		},null, formData);
}

function verificaChequeMaior(formData, diferenca) {
	var dif = "R$ " + formataDecimal(diferenca, 2);
	bootbox.dialog({
		title: "Diferença de valor: " + dif,
		message: 'Escolha o que fazer com a diferença.',
		buttons: {
			'troco' : {
				label: 'Troco',
				className: "btn-success",
				callback: function(e) {
					formData.append('troco', true);
					validaContasTroco(formData);
				}
			},
			confirm : {
				label: 'Adiantamento',
				className: "btn-nw-registro",
				callback: function(e) {
					formData.append('adiantamento', true);
					validaContasAdiantamento(formData);
				}
			},
			cancel : {
				label: 'Cancelar',
				className: "btn-nw-geral"
			}
		}
	});
}

function validaContasTroco(formData) {
	var contas = $('#contas').html();
	var message = 'Informe o caixa de onde será retirado o troco:';
	message += '<br /> <select id="transferidoconta_id" class="selectChosen bootbox-input bootbox-input-select form-control">' + contas + '</select>';
	message += '<script>$(".selectChosen").chosen({no_results_text: "nenhum registro encontrado", placeholder_text_single: "Selecione", width: "100%"});</script>';

	dialogContas(message, function() {
		var transferidoconta_id = $("#transferidoconta_id").val();
		if(isEmpty(transferidoconta_id)){
			bootbox.alert({message: 'Informe a conta!', callback: function () {validaContasTroco(formData)}});
		} else {
			formData.append('transferidoconta_id', transferidoconta_id);
			gravarCheque(formData);
		}
	});
}

function validaContasAdiantamento(formData) {
	var contas = $('#contas').html();
	var message = 'Informe a conta de adiantamento do cliente:';
	message += '<br /> <select id="adiantamentoconta_id" class="selectChosen bootbox-input bootbox-input-select form-control">' + contas + '</select>';
	message += '<script>$(".selectChosen").chosen({no_results_text: "nenhum registro encontrado", placeholder_text_single: "Selecione", width: "100%"});</script>';

	dialogContas(message, function () {
		var adiantamentoconta_id = $("#adiantamentoconta_id").val();
		if(isEmpty(adiantamentoconta_id)){
			bootbox.alert({message: 'Informe a conta!', callback: function () {validaContasAdiantamento(formData)}});
		} else {
			formData.append('adiantamentoconta_id', adiantamentoconta_id);
			gravarCheque(formData);
		}
	});
}
function dialogContas(message, callback) {
	bootbox.dialog({
		title: "Selecione!",
		message: message,
		buttons: {
			confirm : {
				label: 'Gravar',
				className: "btn-nw-registro",
				callback: function() {
					callback();
				}
			},
			cancel : {
				label: 'Cancelar',
				className: "btn-nw-geral"
			}
		}
	});
}

function validaCampos(callback) {
	var banco_id_erro = $("#banco_id_erro").val();
	var agencia = $("#agencia").val();
	var numeroconta = $("#numeroconta").val();
	var numerocheque = $("#numerocheque").val();
	var errorsArray = [];
	isEmpty(banco_id_erro) ? errorsArray.push('Banco') : '';
	isEmpty(agencia) ? errorsArray.push('Agência') : '';
	isEmpty(numeroconta) ? errorsArray.push('Conta') : '';
	isEmpty(numerocheque) ? errorsArray.push('Nº Cheque') : '';
	var errors = '';
	$.each(errorsArray, function(i, el) {
		errors += ' <br /> O campo ' + el + ' é obrigatório.';
	});
	if(isEmpty(errors))
		callback();
	else
		bootbox.alert('Erro ao gravar: ' + errors);
}

function initSelectize() {
	if(typeof $("#cliente_id").val() != 'undefined') {
		$("#cliente_id").selectize({
			valueField: "id",
			labelField: "nome",
			searchField: ["nome"],
			maxOptions: 10,
			hideSelected: true,
			options: [],
			create: false,
			render: {
				option: function (item, escape) {
					return "<div><b>" + escape(item.nome) + "</b>" + "</div>";
				}
			},
			optgroups: [
			{value: "cliente", label: "Clientes"}
			],
			optgroupField: "class",
			optgroupOrder: ["cliente"],
			load: function (query, callback) {
				var select = $("#cliente_id").selectize()[0].selectize;
				select.clearOptions();
				if (!query.length)
					return callback();
				$.ajax({
					url: root + "/api/searchClientes",
					type: "GET",
					dataType: "json",
					data: {
						q: query
					},
					error: function (data) {
						console.log(data);
						callback();
					},
					success: function (res) {
						callback(res.data);
					}
				});
			},
			onChange: function (data) {
				if (typeof $("#cliente_id").selectize()[0].selectize.getItem(this.items[0]).context === "object") {
					$("#cliente_nome_reload").val($("#cliente_id").selectize()[0].selectize.getItem(this.items[0]).context.innerText);
					$("#cliente_id_reload").val($("#cliente_id").selectize()[0].selectize.getValue());
				}
			}, onInitialize: function () {
				var select = $("#cliente_id").selectize()[0].selectize;
				var cliente_id = $("#cliente_id_reload").val();
				var cliente_nome = $("#cliente_nome_reload").val();
				if (typeof select.getItem(this.items[0]).context !== "object" && !isEmpty(cliente_nome)) {

					select.addOption([{
						nome: cliente_nome,
						id: cliente_id}]);
					select.refreshOptions(true);
					select.refreshItems();
					select.addItem(cliente_id);
				}
			}, onDropdownOpen: function ( $dropdown ) {
				$dropdown.css( 'visibility', this.lastQuery != null && this.lastQuery.length ? 'visible' : 'hidden' );
			}
		});
	}
}

$('.selectize-input input').keyup(function(e){
	if(e.keyCode == 8) {
		var select = $("#cliente_id").selectize()[0].selectize;
		select.clearOptions();
		select.refreshOptions(true);
		select.refreshItems();
		$("#cliente_id_reload").val('');
		$("#cliente_nome_reload").val('');
	}
});
