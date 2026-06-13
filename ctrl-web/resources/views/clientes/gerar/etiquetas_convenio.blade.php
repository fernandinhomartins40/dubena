<HTML>
	<HEAD>
		<title>{{ @$titulo }}</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<STYLE>
			html{
				margin: 0;
				padding-top: 2.7mm;
			}
			body{
				margin: 12.7mm 4.8mm;
			}
			.geral0{
				font-size: 11px;
			}

			.rasura{
				padding-top:13px;
			}

			.etiqueta{
				height:25.4mm;
				width: 66.7mm;
				margin-left: 2.5mm;
				margin-right: 2.5mm;
			}

			.subetiqueta-first{
				margin-left: 2.2mm;
				padding-left: 2mm;
				margin-top: 4.8mm;
				padding-top: 2.3mm;
				margin-bottom: 1.3mm;
				padding-bottom: 2mm;
			}

			.subetiqueta{
				margin-left: 2.2mm;
				padding-left: 2mm;
				margin-bottom: 0.7mm;
				padding-bottom: 2mm;
			}

			.geral1{
				clear:both;
				position:absolute;
			}

			.geral2{
				clear:both;
				position:absolute;
				margin-left:69.6mm;
			}

			.geral3{
				clear:both;
				position:absolute;
				margin-left:139.4mm;
			}

			.seq{
				padding-left: 3.5mm;
			}
			.page-break {
				page-break-after: always;
			}

		</STYLE>
	</HEAD>
	<BODY>
		<div class="geral0">
			<div class="geral1">
				@for($i=0;$i < 10; $i++)
				<div class="etiqueta">
					@if($i == 0)
					<div class="subetiqueta-first">
						@else
						<div class="subetiqueta">
							@endif
							<div class="nome-conveniado">
								{{substr(@$data[$i]->nome_conveniado, 0, 40)}}
							</div>
							<div class="nome-conveniado">
								{{substr(@$data[$i]->cpf, 0, 40)}}
							</div>
							<div class="empresa">
								{{substr(@$data[$i]->empresa, 0, 40)}}
							</div>
							<div class="parentesco" style="margin:0 auto;">
								{{substr(@$data[$i]->parentesco, 0, 40)}}
							</div>
							<br/>
							@if($i == 0)
						</div>
						@else
					</div>
					@endif
				</div>
				@endfor
			</div>
			@if(count($data)>10)
			<div class="geral2">
				@for($i=10;$i< 20; $i++)
				<div class="etiqueta">
					@if($i == 10)
					<div class="subetiqueta-first">
						@else
						<div class="subetiqueta">
							@endif
							<div class="nome-conveniado">
								{{substr(@$data[$i]->nome_conveniado, 0, 40)}}
							</div>
							<div class="nome-conveniado">
								{{substr(@$data[$i]->cpf, 0, 40)}}
							</div>
							<div class="empresa">
								{{substr(@$data[$i]->empresa, 0, 40)}}
							</div>
							<div class="parentesco" style="margin:0 auto;">
								{{substr(@$data[$i]->parentesco, 0, 40)}}
							</div>
							<br/>
							@if($i == 0)
						</div>
						@else
					</div>
					@endif
				</div>
				@endfor
			</div>
			@endif
			@if(count($data)>20)
			<div class="geral3">
				@for($i=20;$i< 30; $i++)
				<div class="etiqueta">
					@if($i == 20)
					<div class="subetiqueta-first">
						@else
						<div class="subetiqueta">
							@endif
							<div class="nome-conveniado">
								{{substr(@$data[$i]->nome_conveniado, 0, 40)}}
							</div>
							<div class="nome-conveniado">
								{{substr(@$data[$i]->cpf, 0, 40)}}
							</div>
							<div class="empresa">
								{{substr(@$data[$i]->empresa, 0, 40)}}
							</div>
							<div class="parentesco" style="margin:0 auto;">
								{{substr(@$data[$i]->parentesco, 0, 40)}}
							</div>
							<br/>
							@if($i == 0)
						</div>
						@else
					</div>
					@endif
				</div>
				@endfor
			</div>
			@endif
			@if(count($data)>30)
			<div class="page-break"></div>
			<div class="geral1">
				@for($i=30;$i< 40; $i++)
				<div class="etiqueta">
					@if($i == 30)
					<div class="subetiqueta-first">
						@else
						<div class="subetiqueta">
							@endif
							<div class="nome-conveniado">
								{{substr(@$data[$i]->nome_conveniado, 0, 40)}}
							</div>
							<div class="nome-conveniado">
								{{substr(@$data[$i]->cpf, 0, 40)}}
							</div>
							<div class="empresa">
								{{substr(@$data[$i]->empresa, 0, 40)}}
							</div>
							<div class="parentesco" style="margin:0 auto;">
								{{substr(@$data[$i]->parentesco, 0, 40)}}
							</div>
							<br/>
							@if($i == 0)
						</div>
						@else
					</div>
					@endif
				</div>
				@endfor
			</div>
			@endif
			@if(count($data)>40)
			<div class="geral2">
				@for($i=40;$i< 50; $i++)
				<div class="etiqueta">
					@if($i == 40)
					<div class="subetiqueta-first">
						@else
						<div class="subetiqueta">
							@endif
							<div class="nome-conveniado">
								{{substr(@$data[$i]->nome_conveniado, 0, 40)}}
							</div>
							<div class="nome-conveniado">
								{{substr(@$data[$i]->cpf, 0, 40)}}
							</div>
							<div class="empresa">
								{{substr(@$data[$i]->empresa, 0, 40)}}
							</div>
							<div class="parentesco" style="margin:0 auto;">
								{{substr(@$data[$i]->parentesco, 0, 40)}}
							</div>
							<br/>
							@if($i == 0)
						</div>
						@else
					</div>
					@endif
				</div>
				@endfor
			</div>
			@endif
			@if(count($data)>50)
			<div class="geral3">
				@for($i=50;$i< 60; $i++)
				<div class="etiqueta">
					@if($i == 50)
					<div class="subetiqueta-first">
						@else
						<div class="subetiqueta">
							@endif
							<div class="nome-conveniado">
								{{substr(@$data[$i]->nome_conveniado, 0, 40)}}
							</div>
							<div class="nome-conveniado">
								{{substr(@$data[$i]->cpf, 0, 40)}}
							</div>
							<div class="empresa">
								{{substr(@$data[$i]->empresa, 0, 40)}}
							</div>
							<div class="parentesco" style="margin:0 auto;">
								{{substr(@$data[$i]->parentesco, 0, 40)}}
							</div>
							<br/>
							@if($i == 0)
						</div>
						@else
					</div>
					@endif
				</div>
				@endfor
			</div>
			@endif
			@if(count($data)>60)
			<div class="page-break"></div>
			<div class="geral1">
				@for($i=60;$i< 70; $i++)
				<div class="etiqueta">
					@if($i == 60)
					<div class="subetiqueta-first">
						@else
						<div class="subetiqueta">
							@endif
							<div class="nome-conveniado">
								{{substr(@$data[$i]->nome_conveniado, 0, 40)}}
							</div>
							<div class="nome-conveniado">
								{{substr(@$data[$i]->cpf, 0, 40)}}
							</div>
							<div class="empresa">
								{{substr(@$data[$i]->empresa, 0, 40)}}
							</div>
							<div class="parentesco" style="margin:0 auto;">
								{{substr(@$data[$i]->parentesco, 0, 40)}}
							</div>
							<br/>
							@if($i == 0)
						</div>
						@else
					</div>
					@endif
				</div>
				@endfor
			</div>
			@endif
			@if(count($data)>70)
			<div class="geral2">
				@for($i=70;$i< 80; $i++)
				<div class="etiqueta">
					@if($i == 70)
					<div class="subetiqueta-first">
						@else
						<div class="subetiqueta">
							@endif
							<div class="nome-conveniado">
								{{substr(@$data[$i]->nome_conveniado, 0, 40)}}
							</div>
							<div class="nome-conveniado">
								{{substr(@$data[$i]->cpf, 0, 40)}}
							</div>
							<div class="empresa">
								{{substr(@$data[$i]->empresa, 0, 40)}}
							</div>
							<div class="parentesco" style="margin:0 auto;">
								{{substr(@$data[$i]->parentesco, 0, 40)}}
							</div>
							<br/>
							@if($i == 0)
						</div>
						@else
					</div>
					@endif
				</div>
				@endfor
			</div>
			@endif
			@if(count($data)>80)
			<div class="geral3">
				@for($i=80;$i< 90; $i++)
				<div class="etiqueta">
					@if($i == 80)
					<div class="subetiqueta-first">
						@else
						<div class="subetiqueta">
							@endif
							<div class="nome-conveniado">
								{{substr(@$data[$i]->nome_conveniado, 0, 40)}}
							</div>
							<div class="nome-conveniado">
								{{substr(@$data[$i]->cpf, 0, 40)}}
							</div>
							<div class="empresa">
								{{substr(@$data[$i]->empresa, 0, 40)}}
							</div>
							<div class="parentesco" style="margin:0 auto;">
								{{substr(@$data[$i]->parentesco, 0, 40)}}
							</div>
							<br/>
							@if($i == 0)
						</div>
						@else
					</div>
					@endif
				</div>
				@endfor
			</div>
			@endif
		</div>
	</BODY>
</HTML>