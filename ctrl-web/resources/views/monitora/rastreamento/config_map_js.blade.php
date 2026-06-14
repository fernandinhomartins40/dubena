<script>
	var c_host = '{{url("/")}}';
	var c_caminho = "/";
	var c_motoristas = [
		@foreach($veiculos as $veiculo)
			{
				'id': {{$veiculo->id}}, 'condutor': '{{$veiculo->motorista}}'
			},
		@endforeach
	];

	function get_motorista(id){
			var i =0;
			encontrado = false;
			while (i < c_motoristas.length && !encontrado)
			{
				encontrado = c_motoristas[i].id == id;
				i++;
			}
			if (encontrado){
				return c_motoristas[i-1].condutor;
			}
			else{
				return URLEncode('Motorista Desconhecido!');
			}
	}

	//$obj_tempo_parado = $config_maps->max_tempo_parado();
	//$tempo_parado = $obj_tempo_parado->tempo_parado * 60;
	function config_dados_moveis()
	{
		c_max_tempo_parado = {{$maps->tempo_parado}};
		var dados = [
			@foreach($veiculos as $veiculo)
				{
					'id': {{$veiculo->id}},
					'cd_grupo': {{$veiculo->empresa_id}},
					'cd_motorista': {{$veiculo->id}},
					'modelo': '{{$veiculo->descricao}}',
					'identificacao': '{{$veiculo->placa}}',
					'image_parado': "{{URL::to('img/'.$veiculo->veiculotipo->imagem_parado)}}",
					'image_normal': "{{URL::to('img/'.$veiculo->veiculotipo->imagem_movimento)}}",
					'image_acima': "{{URL::to('img/'.$veiculo->veiculotipo->imagem_acima)}}",
					'data': '',
					'velocidade_maxima': {{$veiculo->veiculotipo->velocidade_maxima}},
					'status_acesso': 0,
					'show_km': 0,
					'show_chat': 0,
					'km_atual': 0,
					'revenda_id': {{$veiculo->empresa_id}},
				},
			@endforeach
		];
		return dados;
	}

	function config_dados_estaticos()
	{
		var dados = [
			@foreach($setors as $setor)
				{
					'id': {{$setor->id}},
					'estabelecimento': {{$setor->id}},
					'endereco': '{{$setor->rua.', '.$setor->numero.' - '.$setor->bairro}}',
					'cidade': '{{$setor->cidade}}',
					'estado': '{{$setor->uf}}',
					'latitude': {{$setor->latitude}},
					'longitude': {{$setor->longitude}},
					'image': "{{URL::to('img/default.png')}}",
				},
			@endforeach
		];
		return dados;
	}

function config_center_latitude()
{
	return {{$maps->latitude}};
}
function config_center_longitude()
{
	return {{$maps->longitude}};
}
function config_start_zoom()
{
	return {{$maps->start_zoom}};
}
function config_show_control()
{

	return show_control = {
		'control_large': {{$maps->control_large}},
		'control_small': {{$maps->control_small}},
		'control_type': {{$maps->control_type}},
		'control_zoom': {{$maps->control_zoom}},
		'control_scale': {{$maps->control_scale}},
		'overview': {{$maps->overview}}
	};

}
function config_overlay()
{
	return overlay = {
		'traffic_info': {{$maps->traffic_info}}
	};
}
</script>
