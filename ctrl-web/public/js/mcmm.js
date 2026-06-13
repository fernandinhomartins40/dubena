var saldoAnterior = '{}';
$(document).ready(function($) {
	initTables();
	$("#fmCadastro").on('submit', function (e) {
		$('#divTipoInstalacoes :input[type="checkbox"]').attr("disabled", false);	
		setDataTablesToInput();
	});
});

$('#divTipoInstalacoes :input[type="checkbox"]').attr("disabled", true);	
$('#btnSearch').on("click", function () {
	bootbox.confirm({
		title: 'Atenção!',
		className: 'warning',
		message: 'Ao buscar os dados novamente todas as alterações serão perdidas, deseja continuar?',
		buttons: {
			confirm: {
				label: 'Sim',
				className: 'btn-nw-registro'
			},
			cancel: {
				label: 'Não',
				className: 'btn-nw-geral'
			}
		},
		callback: function (result) {
			if (result) {
				searchEntradaSaidaMcmm();
			} 
		}
	});
});

function setDataTablesToInput (original = false) {
	var data = [];
	var trEntradas = $("#tblEntradas").children('tbody, tfoot').find('tr');
	var countRows = trEntradas.length;
	trEntradas.each(function (i, el) {
		var d = {};
	 	var el = $(el).find('td');
	 	d.nfserie = '';
	 	d.datahoraentradasaida = '';
	 	d.id = '';
	 	d.nfnumero = '';
 		d.total = false;
 		d.mesanterior = false;
		if(i === 0) {
	 		d.mesanterior = true;
		} else if (i === countRows - 1){
	 		d.total = true;
		} else {
		 	d.nfnumero = parseInt(el.eq(0).text());
		 	d.nfserie = el.eq(1).text();
		 	d.datahoraentradasaida = el.eq(2).text();
		}
		if(d.mesAnterior || d.total) {
		 	d.qdep02 = el.eq(1).text();
		 	d.qdep08 = el.eq(2).text();
		 	d.qdep13 = el.eq(3).text();
		 	d.qdep20 = el.eq(4).text();
		 	d.qdep45 = el.eq(5).text();
		 	d.qdep90 = el.eq(6).text();
		 	d.tipo_nf = el.eq(7).text();
		 	d.nfnumero = '';
		} else {
		 	d.qdep02 = el.eq(3).text();
		 	d.qdep08 = el.eq(4).text();
		 	d.qdep13 = el.eq(5).text();
		 	d.qdep20 = el.eq(6).text();
		 	d.qdep45 = el.eq(7).text();
		 	d.qdep90 = el.eq(8).text();
		 	d.tipo_nf = el.eq(9).text();
		 	d.id = el.eq(10).text();
		}
		data.push(d);
	});
	if(original)
		$("#entradas_original").val(JSON.stringify(data));	
	else 
		$("#entradas").val(JSON.stringify(data));
	var html = $("#tblEntradas").children('tbody, tfoot').html();
	$("#entradas_html").val(html);

	var data = [];
	var trSaidas = $("#tblSaidas").children('tbody, tfoot').find('tr');
	var countRows = trSaidas.length;
	trSaidas.each(function (i, el) {
		var d = {};
	 	var el = $(el).find('td');
	 	d.operacao = '';
 		d.soma = false;
 		d.somaseguinte = false;
		if(i === countRows - 1) {
	 		d.soma = true;
		} else if (i === countRows - 2){
	 		d.somaseguinte = true;
		} else {
		 	d.operacao = parseInt(el.eq(0).text());
		}
	 	d.qdep02 = el.eq(1).text();
	 	d.qdep08 = el.eq(2).text();
	 	d.qdep13 = el.eq(3).text();
	 	d.qdep20 = el.eq(4).text();
	 	d.qdep45 = el.eq(5).text();
	 	d.qdep90 = el.eq(6).text();
		data.push(d);
	});
	if(original)
		$("#saidas_original").val(JSON.stringify(data));	
	else 
		$("#saidas").val(JSON.stringify(data));
	var html = $("#tblSaidas").children('tbody, tfoot').html();
	$("#saidas_html").val(html);
}

function initTables () {
	var columnsEntradas = [ {
			field: "nfnumero",
			title: "Nota Fiscal"
		}, {
			field: "nfserie",
			title: "Série"
		}, {
			field: "datahoraentradasaida",
			title: "Data Compra"
		}, {
			field: "qdep02",
			title: "GLP P02"
		}, {
			field: "qdep08",
			title: "GLP P08"
		}, {
			field: "qdep13",
			title: "GLP P13"
		}, {
			field: "qdep20",
			title: "GLP P20"
		}, {
			field: "qdep45",
			title: "GLP P45"
		}, {
			field: "qdep90",
			title: "GLP P90"
		}, {
			field: "tipo_nf",
			title: "Tipo",
		}, {
			field: "id",
			title: "Código",
		}];

	var columnsSaidas = [ {
			field: "operacao",
			title: "Operação"
		}, {
			field: "qdep02",
			title: "GLP P02"
		}, {
			field: "qdep08",
			title: "GLP P08"
		}, {
			field: "qdep13",
			title: "GLP P13"
		}, {
			field: "qdep20",
			title: "GLP P20"
		}, {
			field: "qdep45",
			title: "GLP P45"
		}, {
			field: "qdep90",
			title: "GLP P90"
		}];

	$("#tblEntradas").bootstrapTable({
		columns: columnsEntradas,
		onPostBody: function () {
			if(saldoAnterior !== '{}') {
				var html = "<tr><td>Saldo Mês Anterior:</td>";
				html += "<td></td>";
				html += "<td></td>";
				html += "<td>" + saldoAnterior.qdep02 + "</td>";
				html += "<td>" + saldoAnterior.qdep08 + "</td>";
				html += "<td>" + saldoAnterior.qdep13 + "</td>";
				html += "<td>" + saldoAnterior.qdep20 + "</td>";
				html += "<td>" + saldoAnterior.qdep45 + "</td>";
				html += "<td>" + saldoAnterior.qdep90 + "</td>";
				html += "</tr>";
			}
			$("#tblEntradas").children('tbody').prepend(html);
			disableInputCells("tblEntradas", ["qdep02", "qdep08", "qdep13", "qdep20", "qdep45", "qdep90"]);
			$("#tblEntradas").editableTableWidget({editor: $("<input number='true' data-empty='0' type='text'>")});
			var tr = $("#tblEntradas").children("tbody").find("tr")[0];
			if(!$(tr).hasClass("no-records-found"))
				updateTotalEntradas();
		}
	});
	$("#tblSaidas").bootstrapTable({
		columns: columnsSaidas,
		onPostBody: function () {
			disableInputCells("tblSaidas", ["qdep02", "qdep08", "qdep13", "qdep20", "qdep45", "qdep90"]);
			var tr = $("#tblSaidas").children("tbody").find("tr")[0];
			$("#tblSaidas").editableTableWidget({editor: $("<input number='true' data-empty='0' type='text'>")});
			if(!$(tr).hasClass("no-records-found")){
				updateTotalSaidas();
			}
			callbackAfterEdit = function (){
				updateTotalEntradas();
				updateTotalSaidas();
			}
		}
	});
}

function updateTotalGeral (totalP02, totalP08, totalP13, totalP20, totalP45, totalP90) {
	$("#footer-entradas").find('td').each(function (i, el) {
		var qde = !isNaN(parseInt($(el).text())) ? parseInt($(el).text()) : 0;
		if(i === 1)
			totalP02 = qde - totalP02;
		else if(i === 2)
			totalP08 = qde - totalP08;
		else if(i === 3)
			totalP13 = qde - totalP13;
		else if(i === 4)
			totalP20 = qde - totalP20;
		else if(i === 5)
			totalP45 = qde - totalP45;
		else if(i === 6)
			totalP90 = qde - totalP90;
	});
	var contentFoot = "<tr><td>Saldo Para Mês Seguinte:</td>";
	contentFoot += "<td>" + totalP02 + "</td>";
	contentFoot += "<td>" + totalP08 + "</td>";
	contentFoot += "<td>" + totalP13 + "</td>";
	contentFoot += "<td>" + totalP20 + "</td>";
	contentFoot += "<td>" + totalP45 + "</td>";
	contentFoot += "<td>" + totalP90 + "</td>";
	contentFoot += "</tr>";	
	$("#footer-saidas-total").html(contentFoot);
	var noRegister = $("#tblEntradas").find("tr.no-records-found")[0];
	if(typeof noRegister !== 'undefined')
		noRegister.remove();
	setDataTablesToInput(true);
}

function updateTotalSaidas () {
	$('#footer-saidas, #footer-saidas-total').remove();
	var totalP02 = 0;
	var totalP08 = 0;
	var totalP13 = 0;
	var totalP20 = 0;
	var totalP45 = 0;
	var totalP90 = 0;
	$("#tblSaidas").children('tbody').find('tr').each(function (i, el) {
		var cols = $($(el).find('td'));
		for (var i = 1; i < cols.length; i++) {
			var qde = !isNaN(parseInt($(cols[i]).text())) ? parseInt($(cols[i]).text()) : 0;
			if(i === 1)
				totalP02 += qde;
			else if(i === 2)
				totalP08 += qde;
			else if(i === 3)
				totalP13 += qde;
			else if(i === 4)
				totalP20 += qde;
			else if(i === 5)
				totalP45 += qde;
			else if(i === 6)
				totalP90 += qde;
		};
	});

	var html = "<tfoot class='negrito' id='footer-saidas'><tr><td>Venda do Mês-soma:</td>";
	html += "<td>" + totalP02 + "</td>";
	html += "<td>" + totalP08 + "</td>";
	html += "<td>" + totalP13 + "</td>";
	html += "<td>" + totalP20 + "</td>";
	html += "<td>" + totalP45 + "</td>";
	html += "<td>" + totalP90 + "</td>";
	html += "</tr></tfoot>";	
	$("#tblSaidas").append("<tfoot class='negrito' id='footer-saidas-total'><tr></tr></tfoot>");
	$("#tblSaidas").append(html);
	updateTotalGeral(totalP02, totalP08, totalP13, totalP20, totalP45, totalP90);
}

function updateTotalEntradas () {
	$('#footer-entradas').remove();
	var totalP02 = 0;
	var totalP08 = 0;
	var totalP13 = 0;
	var totalP20 = 0;
	var totalP45 = 0;
	var totalP90 = 0;
	$("#tblEntradas").children('tbody').find('tr').each(function (i, el) {
		var cols = $($(el).find('td'));
		for (var i = 3; i < cols.length; i++) {
			var qde = !isNaN(parseInt($(cols[i]).text())) ? parseInt($(cols[i]).text()) : 0;
			if(i === 3)
				totalP02 += qde;
			else if(i === 4)
				totalP08 += qde;
			else if(i === 5)
				totalP13 += qde;
			else if(i === 6)
				totalP20 += qde;
			else if(i === 7)
				totalP45 += qde;
			else if(i === 8)
				totalP90 += qde;
		};
	});

	var html = "<tfoot class='negrito' id='footer-entradas'><tr><td colspan='3'>Total ou a Transportar:</td>";
	html += "<td>" + totalP02 + "</td>";
	html += "<td>" + totalP08 + "</td>";
	html += "<td>" + totalP13 + "</td>";
	html += "<td>" + totalP20 + "</td>";
	html += "<td>" + totalP45 + "</td>";
	html += "<td>" + totalP90 + "</td>";
	html += "</tr></tfoot>";	
	$("#tblEntradas").append(html);
	updateTotalGeral();
}

function searchEntradaSaidaMcmm () {
	var url = root + "/mcmm.searchEntradaSaidaMcmm?dataInicio=:dataInicio&dataFim=:dataFim";
	var dataInicio = $('#dataInicio').val();
	var dataFim = $('#dataFim').val();
	if(isEmpty(dataInicio) || isEmpty(dataFim)) {
		bootbox.alert("Os campos 'Compras de' e 'Até' são obrigatórios.");
		return false;	
	}
	url = url.replace(":dataInicio", insertDataOracle(dataInicio));
	url = url.replace(":dataFim", insertDataOracle(dataFim));
	saldoAnterior = '{}';
	ajaxGenerator(url, 'GET', function (data) {
		if(typeof data === "array" || typeof data === "object"){
			$('#footer-entradas, #footer-saidas, #footer-saidas-total').remove();
			saldoAnterior = data["saldoAnterior"];
			$("#tblEntradas").bootstrapTable("load", data["entradas"]);
			$("#tblSaidas").bootstrapTable("load", data["saidas"]);
		} else {
			bootbox.alert('Erro ao buscar dados: ' + data);
		}
	});
}

function validateErrors () {
	$("#tblEntradas").append($("#entradas_html").val());
	$("#tblSaidas").append($("#saidas_html").val());
	$('#divTipoInstalacoes :input[type="checkbox"]').attr("disabled", true);
}