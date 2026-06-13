<!DOCTYPE html>
<html>
	<head>		
		<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	</head>
	<body>
		<p style="font-size: 14px; font-family: Arial">Olá, {!! $content['nome'] !!}</p>
		<p style="font-size: 14px; font-family: Arial">Existem documentos vencidos ou próximos do vencimento sob sua responsabilidade:</p>
		<table>
			@foreach($content['documentos'] as $doc)
				<tr>
					<td {!! $doc->qtdiasvencer<0?"style='color: red;'":"" !!}>{!! $doc->msg !!}</td>
				</tr>
			@endforeach
		</table>
		<p style="font-size: 14px; font-family: Arial">Por favor, realize a renovação dos documentos e, em seguida, atualize os dados no sistema Gás em Casa.</p>
	</body>
</html>
