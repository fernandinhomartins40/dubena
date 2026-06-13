<?php
require_once("../include/config.php");
require_once(_DIR_CLASS_."class_config_maps.php");
require_once (_DIR_CLASS_ . "class_veiculos.php");

$config_veiculos = new config_veiculos;
$config_maps = new config_maps;

echo "
//alert('carregado config_map');
	var c_host = '"._HOST_."';
	var c_caminho = '"._CAMINHO_."';" .
	"var c_motoristas = [";

	$motoristas = $config_maps->motoristas();
	$msg = "";
	foreach ($motoristas as $id=>$dados)
	{
		if (!empty($msg))
			$msg .= ",";
		$msg .= "
	{
		'id':'".$dados["cd_motorista"]."',
		'condutor':'".urlencode($dados["condutor"])."'
	}";
	}
	echo $msg;
	echo "];";
	$dados_moveis = $config_maps->config_maps_dados_moveis($_SESSION["grupos"]);

	echo "
	function get_motorista(id){
		//if(id != 1)
		//{
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
		//}else{
		//	return URLEncode('Sem ID-CARD');
		//}
	}\n";
	$obj_tempo_parado = $config_maps->max_tempo_parado();
	$tempo_parado = $obj_tempo_parado->tempo_parado * 60;
	echo "function config_dados_moveis()
	{
		c_max_tempo_parado = {$tempo_parado};
		var dados = [
	";
	$msg = "";
	foreach ($dados_moveis as $id=>$dados)
	{
		if (!empty($msg))
			$msg .= ",";
		$msg .= "
	{
		'id':'{$dados["cd_veiculo"]}',
		'cd_grupo':'{$_SESSION["grupos"]}',
		'cd_motorista':'',
		'modelo':'".urlencode($dados["modelo"])."',
		'identificacao':'{$dados["identificador"]}',
		'image_parado': c_host+c_caminho+'{$dados["imagem_parado"]}',
		'image_normal': c_host+c_caminho+'{$dados["imagem_normal"]}',
		'image_acima': c_host+c_caminho+'{$dados["imagem_acima"]}',
		'data': '{$dados["data_hora"]}',
		'velocidade_maxima': '{$dados["velocidade_maxima"]}',
		'status_acesso': 0,
                'show_km': '{$dados["show_km"]}',
                'show_chat': '{$dados["show_chat"]}',
                'km_atual': '{$dados["km_atual"]}'
	}";//nas image antes estava com: c_host+c_caminho+
	}
	echo $msg;
	echo "];
	return dados;
}
";
//$dados_estaticos = $config_maps->config_maps_dados_estaticos();
//$dados_estaticos = $config_maps->estabelecimentos_do_grupo($_SESSION["grupos"]);
$dados_estaticos =$config_veiculos->estabelecimentos_menu($_SESSION["grupos"]);
echo "
function config_dados_estaticos()
{
	var dados = [
";
	$msg = "";
	foreach ($dados_estaticos as $id=>$dados)
	{
		if (!empty($msg))
			$msg .= ",";
		$msg .= "
	{
		'id': '".urlencode($dados["cd_estabelecimento"])."',
		'estabelecimento': '".urlencode($dados["estabelecimento"])."',
		'endereco': '".urlencode("{$dados["endereco"]}, {$dados["numero"]} - {$dados["bairro"]}")."',
		'cidade': '".urlencode($dados["cidade"])."',
		'estado': '".urlencode($dados["uf_estado"])."',
		'latitude': {$dados["latitude"]},
		'longitude': {$dados["longitude"]},
		'image': c_host+c_caminho+'{$dados["imagem"]}'
	}";//c_host+c_caminho+
	}
	echo $msg;
	echo "];
	return dados;
}
";

	list($latitude,$longitude)= split(',',$_SESSION["centraliza_mapa"]);

echo "
function config_center_latitude()
{
	return {$latitude};
}
function config_center_longitude()
{
	return {$longitude};
}
function config_start_zoom()
{
	return "._MAPS_START_ZOOM_.";
}
";
echo
"
function config_show_control()
{

	return show_control = {
		'control_large': "._MAPS_CONTROL_LARGE_.",
		'control_small': "._MAPS_CONTROL_SMALL_.",
		'control_type': "._MAPS_CONTROL_TYPE_.",
		'control_zoom': "._MAPS_CONTROL_ZOOM_.",
		'control_scale': "._MAPS_CONTROL_SCALE_.",
		'overview': "._MAPS_OVERVIEW_."
	};

}
function config_overlay()
{
	return overlay = {
		'traffic_info': "._MAPS_TRAFFIC_INFO_."
	};
}
";
?>
