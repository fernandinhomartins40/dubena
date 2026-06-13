<HTML>
	<HEAD>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<STYLE>
			html{
				margin: 0;
				padding-top: 2.7mm;
			}
			
			body{margin: 12.7mm 4.8mm; }

			.geral0{font-size: 12px; }
			
			.rasura{padding-top:13px; }

			@media print{
				.no-print{display: none;}
				.top {margin-top: -55px;}
				.page-break {page-break-after: always; }
				.geral3{position:absolute; }
			}
			
			@page {
				margin-top: 23mm;
			}

			.etiqueta{
				height:25.4mm;
				width: 66.7mm;
				margin-left: 0.5mm;
				margin-right: 2.5mm;
				margin-bottom: 7mm;
			}

			.subetiqueta{
				margin-left: 2.2mm;
				padding-left: 2mm;
				margin-bottom: 0.7mm;
				padding-bottom: 4mm;
			}
			.geral1{
				clear:both;
				position:absolute; 
			}
			
			.geral2{
				position:absolute; 
				clear:both;
				margin-left:83.6mm;
			}
			
			.geral3{
				clear:both;
				margin-left:166.4mm;
			}

			.seq{padding-left: 3.5mm;}

			.align-center{text-align: center;}

		</STYLE>
	</HEAD>
	<BODY>
		<div class="no-print align-center"><i>Esta é somente uma prévia da impressão, para visualizar como a impressão ficará você deve clicar no botão "Imprimir".</i><br /><br /><br /></div>
		<div class="top"></div>
		<div class="geral0">
			<div class="geral1">
				@for($i=0;$i < 10; $i++)
				<div class="etiqueta">
					<div class="subetiqueta">
						{{strlen(@$clientes[$i]->nome) > 40 ? substr(@$clientes[$i]->nome, 0, 40) . '...' : @$clientes[$i]->nome}}
						<div class="rua_numero">
							{{substr(@$clientes[$i]->rua_numero, 0, 50)}}
						</div>
						<div class="endereco"> 
							{{substr(@$clientes[$i]->bairro_cidade, 0, 50)}}
						</div>
						<div class="cep" >
							{{substr(@$clientes[$i]->cep, 0, 50)}}
						</div>
						<br/>
					</div>
				</div>
				@endfor
			</div>
			@if(count($clientes)>10)
			<div class="geral2">
				@for($i=10;$i< 20; $i++)
				<div class="etiqueta">
					<div class="subetiqueta">
						{{strlen(@$clientes[$i]->nome) > 40 ? substr(@$clientes[$i]->nome, 0, 40) . '...' : @$clientes[$i]->nome}}
						<div class="rua_numero">
							{{substr(@$clientes[$i]->rua_numero, 0, 50)}}
						</div>
						<div class="endereco"> 
							{{substr(@$clientes[$i]->bairro_cidade, 0, 50)}}
						</div>
						<div class="cep" >
							{{substr(@$clientes[$i]->cep, 0, 50)}}
						</div>
						<br/>
					</div>
				</div>
				@endfor
			</div>
			@endif
			@if(count($clientes)>20)
			<div class="geral3">
				@for($i=20;$i< 30; $i++)
				<div class="etiqueta">
					<div class="subetiqueta">
						{{strlen(@$clientes[$i]->nome) > 40 ? substr(@$clientes[$i]->nome, 0, 40) . '...' : @$clientes[$i]->nome}}
						<div class="rua_numero">
							{{substr(@$clientes[$i]->rua_numero, 0, 50)}}
						</div>
						<div class="endereco"> 
							{{substr(@$clientes[$i]->bairro_cidade, 0, 50)}}
						</div>
						<div class="cep" >
							{{substr(@$clientes[$i]->cep, 0, 50)}}
						</div>
						<br/>
					</div>
				</div>
				@endfor
			</div>
			@endif
			@if(count($clientes)>30)
			<div class="page-break"></div>
			<div class="geral1">
				@for($i=30;$i< 40; $i++)
				<div class="etiqueta">
					<div class="subetiqueta">
						{{strlen(@$clientes[$i]->nome) > 40 ? substr(@$clientes[$i]->nome, 0, 40) . '...' : @$clientes[$i]->nome}}
						<div class="rua_numero">
							{{substr(@$clientes[$i]->rua_numero, 0, 50)}}
						</div>
						<div class="endereco"> 
							{{substr(@$clientes[$i]->bairro_cidade, 0, 50)}}
						</div>
						<div class="cep" >
							{{substr(@$clientes[$i]->cep, 0, 50)}}
						</div>
						<br/>
					</div>
				</div>
				@endfor
			</div>
			@endif
			@if(count($clientes)>40)
			<div class="geral2">
				@for($i=40;$i< 50; $i++)
				<div class="etiqueta">
					<div class="subetiqueta">
						{{strlen(@$clientes[$i]->nome) > 40 ? substr(@$clientes[$i]->nome, 0, 40) . '...' : @$clientes[$i]->nome}}
						<div class="rua_numero">
							{{substr(@$clientes[$i]->rua_numero, 0, 50)}}
						</div>
						<div class="endereco"> 
							{{substr(@$clientes[$i]->bairro_cidade, 0, 50)}}
						</div>
						<div class="cep" >
							{{substr(@$clientes[$i]->cep, 0, 50)}}
						</div>
						<br/>
					</div>
				</div>
				@endfor
			</div>
			@endif
			@if(count($clientes)>50)
			<div class="geral3">
				@for($i=50;$i< 60; $i++)
				<div class="etiqueta">
					<div class="subetiqueta">
						{{strlen(@$clientes[$i]->nome) > 40 ? substr(@$clientes[$i]->nome, 0, 40) . '...' : @$clientes[$i]->nome}}
						<div class="rua_numero">
							{{substr(@$clientes[$i]->rua_numero, 0, 50)}}
						</div>
						<div class="endereco"> 
							{{substr(@$clientes[$i]->bairro_cidade, 0, 50)}}
						</div>
						<div class="cep" >
							{{substr(@$clientes[$i]->cep, 0, 50)}}
						</div>
						<br/>
					</div>
				</div>
				@endfor
			</div>
			@endif
			@if(count($clientes)>60)
			<br>
			<div class="page-break"></div>
			<div class="geral1">
				@for($i=60;$i< 70; $i++)
				<div class="etiqueta">
					<div class="subetiqueta">
						{{strlen(@$clientes[$i]->nome) > 40 ? substr(@$clientes[$i]->nome, 0, 40) . '...' : @$clientes[$i]->nome}}
						<div class="rua_numero">
							{{substr(@$clientes[$i]->rua_numero, 0, 50)}}
						</div>
						<div class="endereco"> 
							{{substr(@$clientes[$i]->bairro_cidade, 0, 50)}}
						</div>
						<div class="cep" >
							{{substr(@$clientes[$i]->cep, 0, 50)}}
						</div>
						<br/>
					</div>
				</div>
				@endfor
			</div>
			@endif
			@if(count($clientes)>70)
			<div class="geral2">
				@for($i=70;$i< 80; $i++)
				<div class="etiqueta">
					<div class="subetiqueta">
						{{strlen(@$clientes[$i]->nome) > 40 ? substr(@$clientes[$i]->nome, 0, 40) . '...' : @$clientes[$i]->nome}}
						<div class="rua_numero">
							{{substr(@$clientes[$i]->rua_numero, 0, 50)}}
						</div>
						<div class="endereco"> 
							{{substr(@$clientes[$i]->bairro_cidade, 0, 50)}}
						</div>
						<div class="cep" >
							{{substr(@$clientes[$i]->cep, 0, 50)}}
						</div>
						<br/>
					</div>
				</div>
				@endfor
			</div>
			@endif
			@if(count($clientes)>80)
			<div class="geral3">
				@for($i=80;$i< 90; $i++)
				<div class="etiqueta">
					<div class="subetiqueta">
						{{strlen(@$clientes[$i]->nome) > 40 ? substr(@$clientes[$i]->nome, 0, 40) . '...' : @$clientes[$i]->nome}}
						<div class="rua_numero">
							{{substr(@$clientes[$i]->rua_numero, 0, 50)}}
						</div>
						<div class="endereco"> 
							{{substr(@$clientes[$i]->bairro_cidade, 0, 50)}}
						</div>
						<div class="cep" >
							{{substr(@$clientes[$i]->cep, 0, 50)}}
						</div>
						<br/>
					</div>
				</div>
				@endfor
			</div>
			@endif
		</div>
	</BODY>
</HTML>