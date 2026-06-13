<meta name="csrf-token" content="{{ csrf_token() }}" />
<!-- DATA TABES SCRIPT -->
<script src="{{URL::to('plugins/jQuery/jQuery-2.1.4.min.js')}}"></script>
<!-- Bootstrap 3.3.2 JS -->
<script src="{{URL::to('bootstrap/js/bootstrap.min.js')}}" type="text/javascript"></script>
<!-- AdminLTE App -->
<script src="{{URL::to('dist/js/app.min.js')}}" type="text/javascript"></script>

<script src="{{URL::to('plugins/datepicker1/moment/moment-with-locales.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/datepicker1/js/bootstrap-datetimepicker.min.js')}}" type="text/javascript"></script>


<script src="{{URL::to('plugins/datatables/jquery.dataTables.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/datatables/dataTables.bootstrap.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/slimScroll/jquery.slimscroll.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/fastclick/fastclick.min.js')}}"></script>
<script src="{{URL::to('plugins/custom_utils.js')}}"></script>
<script src="{{URL::to('plugins/bootbox.min.js')}}"></script>
<script src="{{URL::to('plugins/chosen/chosen.jquery.latin.js')}}"></script>
<script src="{{URL::to('plugins/selectize/js/standalone/selectize.min.js')}}"></script>
<script src="{{URL::to('plugins/tree-multiselect.min.js')}}"></script>
<script src="{{URL::to('plugins/jstree/dist/jstree.min.js')}}"></script>
<script src="{{URL::to('plugins/handsontable/dist/handsontable.full.js')}}"></script>

<script src="{{URL::to('js/jquery.mask.min.js')}}"></script>
<script src="{{URL::to('plugins/input-mask/jquery.inputmask.js')}}"></script>


<script src="{{URL::to('js/custom.js')}}"></script>
<script src="{{URL::to('js/jqueryMaskMoney.js')}}"></script>
<script src="{{URL::to('js/shortcut.js')}}"></script>

<!-- page script -->
<script type="text/javascript">
        var urlDataTable = '';
	var root = '{{url("/")}}';
	var hotParcelas;
	numeral.language('pt-br', {
		delimiters: {
			thousands: '.',
			decimal: ','
		},
		abbreviations: {
			thousand: 'k',
			million: 'm',
			billion: 'b',
			trillion: 't'
		},
		ordinal : function (number) {
			return number === 1 ? 'er' : 'ème';
		},
		currency: {
			symbol: '$'
		}
	});
	$('.modal-wide').on('show.bs.modal', function () {
		var height = $(window).height() - 200;
		$(this).find('.modal-body').css('max-height', height);
	});
	var myDate = new Date();
	jQuery(document).ready(function($){
		$('#datetimepicker1').datetimepicker({
			locale: 'pt-br',
			defaultDate:myDate,
			format: 'DD/MM/YYYY HH:mm:ss',
		});
		submitted = false;
		tblRateio = $('#tblRateio').DataTable( {
			"language": { "url" : "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}"},
			"processing": false,
			"bPaginate": false,
			"bLengthChange": false,
			"bFilter": false,
			"bSort": true,
			"bInfo": false,
			"bAutoWidth": false,
			"columnDefs": [
				{
					"targets": [ 0 ],
					"visible": false
				},
				{
					"targets": [ 1 ],
					"visible": true
				},
				{
					"targets": [ 2 ],
					"visible": true
				},
				{
					"targets": [ 3 ],
					"visible": true
				}
			 ]

		});
		$('#tblRateio').on( 'click', 'button', function () {
			var trElem = $(this).closest("tr");// grabs the button's parent tr element
			var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id

			if($(firstTd).text() != ""){
				if($(this).context.id == 'btnRemoverRateio'){
					tblRateio
						.row( $(this).parents('tr') )
						.remove()
						.draw();
				}
			};
		});
		@if($errors->any())
			carregarParcelasErro();
			carregarRateioErro();
		@else
			carregarParcelas();
		@endif
                $('.dinheiro').each(function(){ // function to apply mask on load!
                    var value = parseDinheiro($(this).val(), 2);
                    $(this).val(value.toFixed(2));
                    $(this).maskMoney('mask', $(this).val());
                })
	});


	function setTotal(){
		var total = parseDinheiro($('#valor_total').val(), 2);
		var desconto = 0;
		var multa = 0;
		var juros = 0;
		desconto = parseDinheiro($('#valor_desconto').val(), 2); //$.isNumeric($('#valor_desconto').val()) ? parseFloat($('#valor_desconto').val()) : 0;
		multa = parseDinheiro($('#valor_multa').val(), 2); // $.isNumeric($('#valor_multa').val()) ? parseFloat($('#valor_multa').val()) : 0;
		juros = parseDinheiro($('#valor_juros').val(), 2);  //$.isNumeric($('#valor_juros').val()) ? parseFloat($('#valor_juros').val()) : 0;
		valor_liquido = total-desconto+juros+multa;
		valor_credito = $("#valor_credito").val();

		if(typeof valor_credito != 'undefined') {
			valor_credito = parseDinheiro(valor_credito, 2);
			valor_liquido -= valor_credito;
		}

		$('#valor_liquido').val(valor_liquido.toFixed(2));
        $('.dinheiro').each(function(){ // function to apply mask on load!
        	var value = parseDinheiro($(this).val(), 2);
            $(this).maskMoney('mask', $(this).val());
		})

	}


	function carregarParcelasErro(){
		dataParcela = JSON.parse($('#parcelas').val()).data;
		var containerParcelas = document.querySelector('#parcelasGrid');
		hotParcelas = new Handsontable(containerParcelas, {
			data: dataParcela,
			columnSorting: false,
			sortingEnabled:false,
			contextMenu: true,
			rowHeaders: false,
			formulas:true,
			readOnly: false,
			colHeaders: ["id", "pagar_receber", "Vencto", "Nome", "Tipo", "Valor", "Desconto Pont.", "Valor Líquido"],
			colWidths: [1, 1, 100, 250, 150, 100, 100, 100],
			columns: [
			{
				readOnly: true,
				visible: false,
				className: "htCenter",
			},
			{
				readOnly: true,
				visible: false,
				className: "htCenter",
			},
			{
				readOnly: true,
				className: "htCenter",
				type: 'date', dateFormat: 'DD/MM/YYYY', correctFormat: true
			},
			{
				readOnly: true,
				className: "htCenter",
			},
			{
				readOnly: true,
				className: "htCenter",
			},
			{
				type: 'numeric',
				format: '0,0.00',
				language: 'pt-br',
				readOnly: true,
				className: "htCenter",
				renderer: function(instance, td, row, col, prop, value){
					Handsontable.NumericRenderer.apply(this, arguments);
				}
			},
			{
				type: 'numeric',
				format: '0,0.00',
				language: 'pt-br',
				readOnly: true,
				className: "htCenter",
				renderer: function(instance, td, row, col, prop, value){
					Handsontable.NumericRenderer.apply(this, arguments);
				}
			},
			{
				type: 'numeric',
				format: '0,0.00',
				language: 'pt-br',
				readOnly: true,
				className: "htCenter",
				renderer: function(instance, td, row, col, prop, value){
					Handsontable.NumericRenderer.apply(this, arguments);
				}
			}
			]
		});
		//hotParcelas.loadData(JSON.parse($('#parcelas').val()).data);
	  hotParcelas.render();
	}
	function carregarParcelas(){
		var parcelas = [];
		@foreach($parcelas as $parc)
			{!!'parcelas.push(['.
					$parc->id.',"'.
					$parc->pagarreceber.'","'.
					Carbon\Carbon::parse($parc->datavencimento)->format('d/m/Y').'","'.
					$parc->cliente_nome.'","'.''.'", '.
			    $parc->valor.','.
					'0'.','.
					$parc->valorefetivado.
					']);'!!}
		@endforeach
		var containerParcelas = document.querySelector('#parcelasGrid');
		hotParcelas = new Handsontable(containerParcelas, {
			data: parcelas,
			columnSorting: false,
			sortingEnabled:false,
			contextMenu: true,
			rowHeaders: false,
			formulas:true,
			readOnly: false,
			colHeaders: ["id", "pagarreceber", "Vencto", "Nome", "Tipo", "Valor", "Desconto", "Valor Líquido"],
			colWidths: [1, 1, 100, 250, 150, 100, 100, 100],
			columns: [
			{
				readOnly: true,
				visible: false,
				className: "htCenter",
			},
			{
				readOnly: true,
				visible: false,
				className: "htCenter",
			},
			{
				readOnly: true,
				className: "htCenter",
				type: 'date', dateFormat: 'DD/MM/YYYY', correctFormat: true
			},
			{
				readOnly: true,
				className: "htCenter",
			},
			{
				readOnly: true,
				className: "htCenter",
			},
			{
				type: 'numeric',
				format: '0,0.00',
				language: 'pt-br',
				readOnly: true,
				className: "htCenter",
				renderer: function(instance, td, row, col, prop, value){
					Handsontable.NumericRenderer.apply(this, arguments);
				}
			},
			{
				type: 'numeric',
				format: '0,0.00',
				language: 'pt-br',
				readOnly: true,
				className: "htCenter",
				renderer: function(instance, td, row, col, prop, value){
					Handsontable.NumericRenderer.apply(this, arguments);
				}
			},
			{
				type: 'numeric',
				format: '0,0.00',
				language: 'pt-br',
				readOnly: true,
				className: "htCenter",
				renderer: function(instance, td, row, col, prop, value){
					Handsontable.NumericRenderer.apply(this, arguments);
				}
			}
			]
		});
	}

	function gravar(){
		if (submitted) return;
		submitted = true;

		if(parseDinheiro($('#valor_liquido').val(),2)<=0){
			bootbox.alert('Valor líquido deve ser maior que zero.');
			return false;
		}
		if($('#contamovimentotipo_idM').val()==''){
			bootbox.alert('Informe o tipo de recebimento.');
			return false;
		}
		if($('#conta_id').val()=='' || $('#conta_id').val()=='-1' ){
			bootbox.alert('Informe o caixa para baixa.');
			return false;
		}
		if($('#data_pagamentoM').val()==''){
			bootbox.alert('Informe a data de baixa do título.');
			return false;
		}
		var rateio = [];
		var total = 0;
		tblRateio.rows().every( function () {
			var d = this.data();
			total+=parseDinheiro(d[2], 2);
			rateio.push(d);
		});
		total = total.toFixed(2);
		if(total != 0 && total != parseDinheiro($('#valor_liquido').val(),2)){
			bootbox.alert('Total do rateio difere do valor a baixar.');
			return false;
		}
		if(total==0 && ($('#contamovimentotipo_idM').val()=='')){
			bootbox.alert('Informe o Tipo de recebimento ou o rateio.');
			return false;
		}
		$('#rateio').val(JSON.stringify(rateio));
		$('#parcelas').val(JSON.stringify({data: hotParcelas.getData()}));
		var myForm = document.getElementById('fmCadastroR');
		var formData = new FormData(myForm);

		$.ajaxSetup({
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				}
		});

		$.ajax({
				url: '{{ route("caixa.baixar") }}',
				type: 'POST',
				processData: false,
    		contentType: false,
				data: formData,
				success: function(res) {
					if(res.substr(0,3) == 'OK|'){
						urlret = '{{ route("financeiro.fecharModalIframe") }}';
						window.location.href = urlret;
					} else {
						bootbox.alert('erro: ' + res);
					}
				},
				error: function (data) {
					if(typeof(data) == 'object'){
						var msg = '';
						var responseText = '';
						for (var key in data) {
							if(key == 'responseJSON'){
								for(var key1 in data['responseJSON']){
									msg += data['responseJSON'][key1];
								}
							}
							if(key == 'responseText'){
								responseText = data['responseText'];
							}
						}
						if(msg != '')
							bootbox.alert('Erro ao gravar: ' + msg);
						else
							bootbox.alert('Erro ao gravar: ' + responseText);
						//bootbox.alert('Erro ao gravar: ' + data.responseJSON.descricao);
					} else if(typeof(data) == 'string'){
							bootbox.alert('Erro ao gravar: ' + data);
					} else {
						bootbox.alert('Houve um erro desconhecido ao gravar!');
					}
				},
				complete: function() {
					submitted = false;
				}
		});

		//$('form#fmCadastroR').submit();
	}


	function setDadosCaixa(){
		$('#recebimentotipo_id').val($('#contamovimentotipo_idM').val());
		$('#data_pagamento').val($('#data_pagamentoM').val());
		$('form#fmCadastroR').submit();
	}
	function addRateio(){
		if(!isInt($('#recebimentotipo_idR').val())){
			bootbox.alert('Preencha o tipo de recebimento.');
			return;
		}
		if($('#valor_rateio').val()==''){
			bootbox.alert('Informe o valor.');
			return;
		}
		if(parseFloat($('#valor_rateio').val())==0){
			bootbox.alert('Informe o valor.');
			return;
		}
		tblRateio.row.add( [
			$('#recebimentotipo_idR').val(),
			$('#recebimentotipo_idR option:selected').text(),
			 $('#valor_rateio').val(),
			 "<button type='button' class='btn btn-danger small' id='btnRemoverRateio'>Remover</button>"
		 	] ).draw( false );
		 $('#valor_rateio').val('');
	}
	function carregarRateioErro(){
		tblRateio.clear();
		us = JSON.parse($('#rateio').val());
		for(i=0;i<us.length;i++){
			tblRateio.row.add( [
				 us[i][0],
				 us[i][1],
				 us[i][2],
				 us[i][3]
			] ).draw( false );
		}
	}


</script>

