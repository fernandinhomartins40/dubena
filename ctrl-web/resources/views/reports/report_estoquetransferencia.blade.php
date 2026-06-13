@extends('layouts.reportNoBootstrap')

@section('content')

<div class="fontSize_12 p-b-15">
	Cód Transferência: {{$codigo}}
	<br/>
	Data da Transferência: {{$dataTransferencia}}
	<br/>
	Setor Origem: {{$setorOrigem}}
	<br/>
	Setor Destino: {{$setorDestino}}
	<br/>
	Autorizado por: {{$user}}
	<br/>
	<br/>
	<div>
		<table class="table text-center" style="width: 100%;">
			<thead class='bordered-top th'>
				<tr>
					<td class='text-center bordered destaque'>Cód Produto</td>
					<td class='text-center bordered destaque'>Produto</td>
					<td class='text-center bordered destaque'>Quantidade</td>
				</tr>
			</thead>
			<tbody>
				@foreach ($estoquetransferenciaitems as $estoquetransferenciaitem)
				<tr cellpadding="0" cellspacing="0" style="padding: 0px">
					<td class='bordered' style="margin: 0px">{{$estoquetransferenciaitem->produto_id}}</td>
					<td class='bordered'>{{$estoquetransferenciaitem->produto->descricao}}</td>
					<td class='bordered'>{{$estoquetransferenciaitem->quantidade}}</td>
				</tr>
				@endforeach
			</tbody>
			<tfoot class="">
				<tr class="">
					<td class='bordered' colspan="2">TOTAL</td>
					<td class='bordered'>{{$totalItems}}</td>
				</tr>
			</tfoot>
		</table>
	</div>
</div>
<div class="margRight_10" style="width: 100.1%;">
	<div class="bordered destaque">
		<div align="center">RETORNO</div>
	</div>
	<div  align="center">
		<div style="position: absolute;text-align:left;padding-top:10px;">
			P13 Cheio: _________
		</div>
		<div style="text-align:center;padding-top:9px;">
			P13 Vazio: _________
		</div>
		<div style="text-align:right;padding-top:-18px;">
			P13 Troca: _________
		</div>
		<div style="position: absolute;text-align:left;padding-top:10px;">
			P20 Cheio: _________
		</div>
		<div style="text-align:center;padding-top:9px;">
			P20 Vazio: _________
		</div>
		<div style="text-align:right;padding-top:-18px;">
			P20 Troca: _________
		</div>
		<div style="position: absolute;text-align:left;padding-top:10px;">
			P45 Cheio: _________
		</div>
		<div style="text-align:center;padding-top:9px;">
			P45 Vazio: _________
		</div>
		<div style="text-align:right;padding-top:-18px;">
			P45 Troca: _________
		</div>
	</div>
	<br />
	<div class="bordered" style="background-color: #cfcfcf;">
		<div align="center"> À CARREGAR</div>
	</div>
	<div  align="center">
		<div style="position: absolute;text-align:left;padding-top:10px;">
			P13 Cheio: _________
		</div>
		<div style="position: absolute;text-align:left;padding-top:40px;">
			P20 Cheio: _________
		</div>
		<div style="position: absolute;text-align:left;padding-top:70px;">
			P45 Cheio: _________
		</div>
		<br />
		<br />
		<br />
		<br />
		<br />
		<br />
	</div>
	<div>
		Obs.: <br />
		<div style="border-top: 1px solid black; width:80%;margin-left: 10%"></div><br />
		<div style="border-top: 1px solid black; width:90%;"></div>
	</div>
	<br />
	<br />
	<div class="bordered" style="background-color: #cfcfcf;">
		<div align="center">ESTOQUE FINAL</div>
	</div>
	<div  align="center">
		<div style="position: absolute;text-align:left;padding-top:10px;">
			P13 Cheio: _________
		</div>
		<div style="text-align:center;padding-top:9px;">
			P13 Vazio: _________
		</div>
		<div style="text-align:right;padding-top:-18px;">
			Total: _________
		</div>
		<div style="position: absolute;text-align:left;padding-top:10px;">
			P20 Cheio: _________
		</div>
		<div style="text-align:center;padding-top:9px;">
			P20 Vazio: _________
		</div>
		<div style="text-align:right;padding-top:-18px;">
			Total: _________
		</div>
		<div style="position: absolute;text-align:left;padding-top:10px;">
			P45 Cheio: _________
		</div>
		<div style="text-align:center;padding-top:9px;">
			P45 Vazio: _________
		</div>
		<div style="text-align:right;padding-top:-18px;">
			Total: _________
		</div>
	</div>
</div>
@endsection
