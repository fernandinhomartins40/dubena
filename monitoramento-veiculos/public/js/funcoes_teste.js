var map;
var mgr;
var moveis;
var marcador_rota = false;
var tentativas=0;
//representa o codigo do veiculo escolhido
var escolhido=-1;
var rotas = new Array(0);
var velocidades = new Array(0);
var direcoes = new Array(0);
var marcadores_parados = new Array(0);
var marcadores_rapidos = new Array(0);
var filiais;
var veiculos;

//Apaga a rota do escolhido
function apaga_rota()
{
	//alert(debug++);
	map.clearOverlays();
	mgr.clearMarkers();

	if (escolhido != -1)
	{				
		while (rotas.length > 0) 
		{
			rotas.pop();
		}
		while (velocidades.length > 0)
		{
			velocidades.pop();
		}
		while (direcoes.length > 0)
		{
			direcoes.pop();
		}
		while (marcadores_parados.length > 0)
		{
			marcadores_parados.pop();
		}
		while (marcadores_rapidos.length > 0)
		{
			marcadores_rapidos.pop();
		}		
	}	
	//alert("apagou rota");
}

function cria_marcador_rota(data)
{
	//alert(debug++);
	var ltlng;
	if (escolhido != -1)
	{	
		if (rotas.length>0)
		{
			var velocidade = velocidades[0];
			var imagem;
			ltlng = rotas[0];
			var request = GXmlHttp.create();
			//open the request to storeMarker.php on your server
			request.open('GET', '_ajax/dados_veiculo.php?cd_veiculo='+escolhido+'&'+ Math.ceil ( Math.random() * 100000 ), true);
			request.setRequestHeader("Cache-Control", "no-store, no-cache, must-revalidate");	
			request.setRequestHeader("Cache-Control", "post-check=0, pre-check=0");
			request.setRequestHeader("Pragma", "no-cache");
			request.onreadystatechange = function() 
			{
				if (request.readyState == 4) 				
				{
					//alert("carregou dados veiculo");
					var xmlDoc = request.responseXML;
					if (navigator.appName.indexOf('Microsoft') != -1){//internet explorer
						var markers = xmlDoc.getElementsByTagName("marker");			
					}
					else
					{
						var markers = xmlDoc.documentElement.getElementsByTagName("marker");
					}
						
					if (markers.length > 0) {
						var condutor = URLDecode(markers[0].getAttribute("condutor"));
						var modelo = URLDecode(markers[0].getAttribute("modelo"));
						var imagem_parado = markers[0].getAttribute("imagem_parado");
						var imagem_normal = markers[0].getAttribute("imagem_normal");
						var imagem_acima = markers[0].getAttribute("imagem_acima");
						var velocidade_maxima = markers[0].getAttribute("velocidade_maxima");
						var identificador = markers[0].getAttribute("identificador");
		
				quilometragem = calcula_distancia();
						var dados = {
							'id':''+escolhido+'',	
							'nome':''+condutor+'',
							'modelo':''+modelo+'',
							'data': ''+data+'',
							'identificacao':''+identificador+'',
							'image_parado': ''+imagem_parado+'',
							'image_normal': ''+imagem_normal+'',
							'image_acima': ''+imagem_acima+'',
							'velocidade_maxima': ''+velocidade_maxima+'',
							'velocidade': ''+velocidade+'',
							'quilometragem': ''+quilometragem+''
						}
						if (marcador_rota)
							map.removeOverlay(marcador_rota);
													
						if (velocidade < velocidade_maxima){
							imagem = imagem_normal;
							if (velocidade == 0)
								imagem = imagem_parado;
						} else {
							imagem = imagem_acima;
						}
												
						marcador_rota = createMarker(ltlng.lat(), ltlng.lng(), dados, velocidade,null,null,imagem,c_alt_mark,c_comp_mark,c_rota);//cria marcador dinamico
						mgr.addMarker(marcador_rota, c_zoom_min_mark);
						clicar(marcador_rota,moveToMapa);
						//mgr.refresh();
						//map.addOverlay(marcador_rota);						
						//abre_informacoes(marcador_rota,dados,c_dinamico,velocidade);
					}					
				}
			}
			request.send(null);		
		}
	}
}

function gera_direcoes(i,latlng,azimute)
{
	var iconImage;
	var marker;
	var icon;
	if ((i%c_offset_dir)==0)//Cria direcoes a cada c_offset_dir pontos
	{
//	alert('azimute '+ i +' antes: ' + azimute);
		if (azimute%c_graus > c_graus_met)
			azimute = azimute - (azimute%c_graus) + c_graus;
		else
			azimute = azimute - (azimute%c_graus);
//	alert('azimute '+ i +' depois: ' + azimute);							
		iconImage = c_host+c_caminho+'/_images/direcao/'+azimute+'.gif';
		//alert(iconImage);
		icon = cria_icone(iconImage,c_alt_dir,c_comp_dir);
		marker = new GMarker(latlng,icon);							
//		map.addOverlay(marker);
		direcoes.push(marker);
		if (i!=0)
		{
			mgr.addMarker(marker, c_zoom_min_info+(direcoes.length%c_offset_dir));
		}
			
	}

}

function gera_marcador_velocidade_acima(condutor,lat,lng,velocidade,velocidade_maxima,data_hora){
	var informacoes_rapidas;
	var marcador;
	if (velocidade > velocidade_maxima)
	{
		informacoes_rapidas={		
			'condutor': ''+condutor+'',	
			'latitude': ''+lat+'',
			'longitude': ''+lng+'',
			'velocidade': ''+velocidade+'',
			'velocidade_maxima': ''+velocidade_maxima+'',
			'data': ''+data_hora+''
		};
		marcador = createMarker(lat,lng,informacoes_rapidas,velocidade,null,null,c_imagem_rapido,c_alt_mark,c_comp_mark,c_rapido);
		marcadores_rapidos.push(marcador);
		//map.addOverlay(marcador);
		mgr.addMarker(marcador, c_zoom_min_info);
	}
        return marcador;
}

function gera_marcadores_parados(markers){
	var i = 0;
//	var count;
	var menor;
	var datain,datafim;
	var tempo_parado;
	while (i < (markers.length-1))
	{
		tempo_parado = 0;
	//	count = 0;
		menor = parseFloat(velocidades[(i+1)]) < c_velocidade_parado;
		if (menor) //para pegar as velocidades do vetor que forem muito baixas
		{						
			informacoes_parado={
				'condutor': ''+markers[(i)].getAttribute("condutor")+'',
				'latitude': ''+markers[(i)].getAttribute("lat")+'',
				'longitude': ''+markers[(i)].getAttribute("lng")+'',
				'data_inicio': 0,
				'data_fim': ''+markers[(i)].getAttribute("data_hora")+'',
				'tempo_parado' : 0
			};
			
			datafim = new Date(
				informacoes_parado.data_fim.substr(0,4),//ano
				informacoes_parado.data_fim.substr(5,2),//mes
				informacoes_parado.data_fim.substr(8,2),//dia
				informacoes_parado.data_fim.substr(11,2),//hora 
				informacoes_parado.data_fim.substr(14,2),//minuto
				informacoes_parado.data_fim.substr(17,2),//segundo
				00);							
			while (menor)
			{
				informacoes_parado.data_inicio = markers[(i+1)].getAttribute("data_hora");
				datain = new Date(
				informacoes_parado.data_inicio.substr(0,4),//ano
				informacoes_parado.data_inicio.substr(5,2),//mes
				informacoes_parado.data_inicio.substr(8,2),//dia
				informacoes_parado.data_inicio.substr(11,2),//hora 
				informacoes_parado.data_inicio.substr(14,2),//minuto
				informacoes_parado.data_inicio.substr(17,2),//segundo
				00);
				informacoes_parado.tempo_parado=Math.floor((datafim-datain)/1000);
				tempo_parado = informacoes_parado.tempo_parado;
				i++;
//				count++;
				if (i < (markers.length-1))
					menor = parseFloat(velocidades[(i+1)]) < c_velocidade_parado;
				else
					menor =false;
			}// while
		} // if
		if (tempo_parado > c_max_tempo_parado)  // cria o marcador de parada após c_max_tempo_parado segundos
		{							
//			alert('tempo_parado '+tempo_parado);
			informacoes_parado.tempo_parado= Math.floor(informacoes_parado.tempo_parado/3600)+" h "+Math.floor((informacoes_parado.tempo_parado%3600)/60)+" m "+Math.floor(informacoes_parado.tempo_parado%60)+" s";
			marcador = createMarker(informacoes_parado.latitude,informacoes_parado.longitude,informacoes_parado,0,null,null,c_imagem_parado,c_alt_mark,c_comp_mark,c_parado);
			marcadores_parados.push(marcador);
//			map.addOverlay(marcador);
			mgr.addMarker(marcador, c_zoom_min_info);
		}//if
		i++;
	}// while	
}

function empilha_rotas(velocidade_maxima)
{
	//alert(debug++);
	if (escolhido != -1)
	{
//		var data1 = URLEncode(document.getElementById('data1').value);
		var data1 = '';
		//var data2 = URLEncode(document.getElementById('data2').value);
		var request = GXmlHttp.create();
		//open the request to storeMarker.php on your server
		//request.open('GET', '_ajax/ultimas_rotas.php?cd_veiculo='+escolhido+'&data1='+data1+'&data2='+data2+'&'+ Math.ceil ( Math.random() * 100000 ), true);
		request.open('GET', '_ajax/ultimas_rotas.php?cd_veiculo='+escolhido+'&data1='+data1+'&'+ Math.ceil ( Math.random() * 100000 ), true);
		request.setRequestHeader("Cache-Control", "no-store, no-cache, must-revalidate");	
		request.setRequestHeader("Cache-Control", "post-check=0, pre-check=0");
		request.setRequestHeader("Pragma", "no-cache");
		request.onreadystatechange = function() {
			if (request.readyState == 4) {
				//alert("carregou ultimas rotas");
				var xmlDoc = request.responseXML;
				var latlng;
				var marker;
				if (navigator.appName.indexOf('Microsoft') != -1){//internet explorer
					var markers = xmlDoc.getElementsByTagName("posicion");			
				}
				else
				{
					var markers = xmlDoc.documentElement.getElementsByTagName("posicion");
				}

//				alert("tamanho xml: "+markers.length);
				for (var i = 0; i < markers.length; i++) {
					var condutor = markers[i].getAttribute("condutor");
					var lng = markers[i].getAttribute("lng");
					var lat = markers[i].getAttribute("lat");
					var cd_veiculo = markers[i].getAttribute("id");
					var velocidade = markers[i].getAttribute("velocidade");
					var data_hora = markers[i].getAttribute("data_hora");
					var azimute = markers[i].getAttribute("azimute");
                                        var km_atual = markers[i].getAttribute("km_atual");
					//check for lng and lat so MSIE does not error
					//on parseFloat of a null value
					if(cd_veiculo &&lng && lat) {
						latlng = new GLatLng(lat, lng);
						rotas.push(latlng);
						velocidades.push(velocidade);
						gera_direcoes(i,latlng,azimute);
						//alert("Posicao do veiculo "+escolhido+" posicao no array "+j+" condutor "+moveis[j].dados.nome+" alterada para lat "+lat+" long "+lng);
						gera_marcador_velocidade_acima(condutor,lat,lng,velocidade,velocidade_maxima,data_hora);
					}
									
				} //for			
	//			alert("rotas_length_depois_push: "+ rotas.length);					
				if (rotas.length == 0)
				{
					alert("Não há posições para esta busca");
					escolhido = -1;
				}
				else
				{
					//create_Polyline(rotas);				
					create_Polyline(rotas);				
					cria_marcador_rota(markers[0].getAttribute("data_hora"));
				}
				gera_marcadores_parados(markers);
//				//mgr.refresh();	
			} // if
		}; // onreadychange
	request.send(null);
	} //escolhido
}

function gera_rotas(cd_veiculo,velocidade_maxima)
{		
//alert(debug++);
		apaga_rota();
		escolhido = cd_veiculo;//troca o escolhido
		empilha_rotas(velocidade_maxima);
		//mgr.refresh();
}

function mostra_rotas()
{	
//alert(debug++);
	var request = GXmlHttp.create();	
	var novo;
	//open the request to storeMarker.php on your server
	request.open('GET', '_ajax/rotas_disponiveis.php?'+ Math.ceil ( Math.random() * 100000 ), true);
	request.setRequestHeader("Cache-Control", "no-store, no-cache, must-revalidate");	
	request.setRequestHeader("Cache-Control", "post-check=0, pre-check=0");
	request.setRequestHeader("Pragma", "no-cache");
	request.onreadystatechange = function() {
		if (request.readyState == 4) {	
			//alert("carregou rotas disponiveis");
			var cor;
			var msg;
			var cd_veiculo;
			var condutor;
			var xmlDoc = request.responseXML;
			if (navigator.appName.indexOf('Microsoft') != -1){//internet explorer
				var markers = xmlDoc.getElementsByTagName("mobile");			
			}
			else
			{
				var markers = xmlDoc.documentElement.getElementsByTagName("mobile");
			}
        	msg = "<table width='100%' border='0' cellspacing='2' cellpadding='2'>";
            msg += "<tr>";
            msg += "<td width='90%' heigth='25px' background='"+fundo+"' colspan='3' bgcolor='#FFCC00'><strong> &nbsp;.:&nbsp;VE&Iacute;CULOS</strong></td>";
            msg += "</tr>";		
            msg += "<tr>";	
				
			for (var i = 0; i < markers.length; i++) {						
				cd_veiculo = markers[i].getAttribute("cd_veiculo");
				modelo = URLDecode(markers[i].getAttribute("modelo"));
				identificador = URLDecode(markers[i].getAttribute("identificador"));
				imagem_parado = markers[i].getAttribute("imagem_parado");
				imagem_normal = markers[i].getAttribute("imagem_normal");
				imagem_acima = markers[i].getAttribute("imagem_acima");
				velocidade_maxima = markers[i].getAttribute("velocidade_maxima");

                cor = "";
    			novo = (i % 3 == 2);
    			
                msg += "<td height='4%' bgcolor='#F7F7F7'><div align='left'>";
                msg += "<table cellpadding='1'>";
                //msg += "<tbody>";
                msg += "<tr>";
                msg += "<td bgcolor='"+cor+"'>";
                msg += "<div align='center'>";
				msg += "<img src='"+imagem_normal+"'"+" width='"+c_comp_mark+"' height='"+c_alt_mark+"'/>";
                msg += "</div>";
		 		msg += "</td>";
				msg += "<td bgcolor='"+cor+"'>";
		     	msg += "<a href='#mapa' onclick='javascript: gera_rotas("+cd_veiculo+","+velocidade_maxima+")'>";
				msg += modelo+" - "+identificador;
		 		msg += "</a>";
				msg += "</td>";
                msg += "</tr>";
                msg += "</table>";
                msg += "</td>";
                if (novo)
    			{	
    				msg += "</tr>";  
    				msg += "<tr>";
    			}								
			} //for
			msg += "</tr>";
			msg += "</table>";
			document.getElementById("elementos").innerHTML = msg;
		}
	}	
	request.send(null);		
}

function inicia_rotas(dados_moveis,centerLatitude,centerLongitude,startZoom,show_control,overlay) {	
	//alert(debug++);
	if (GBrowserIsCompatible()) {
		map = new GMap2(document.getElementById("map"));			
		
		if (show_control)
		{
			if (show_control.control_large)
			{
				map.addControl(new GLargeMapControl());
			}
			if (show_control.control_small)
			{
				map.addControl(new GSmallMapControl());				
			}
			if (show_control.control_type)
			{
				map.addControl(new GMapTypeControl());
			}
			if (show_control.control_zoom)
			{
				map.addControl(new GSmallZoomControl());
			}			
			if (show_control.control_scale)
			{
				map.addControl(new GScaleControl());
			}
			if (show_control.overview)
			{
				map.addControl(new GOverviewMapControl());
			}
		}
		if (overlay)
		{
			if (overlay.traffic_info)
			{
				var trafficInfo = new GTrafficOverlay();
		        map.addOverlay(trafficInfo);
			}
		}

		GEvent.addListener(map, "moveend", function() {
		  var center = map.getCenter();
		  var zoom = map.getZoom();
  		  document.getElementById("message").innerHTML = center.toString()+zoom;
		});		

		//alert(debug++);
		
		map.setCenter(new GLatLng(centerLatitude, centerLongitude),startZoom);
		//alert(debug++);
		mgr = new MarkerManager(map);
		//alert(debug++);
		
		mostra_rotas();
	}
}

function init() {			
	if (document.getElementById("map"))
	{
		//alert("iniciando google");
		inicia_rotas(config_dados_moveis(),config_center_latitude(), config_center_longitude(), config_start_zoom(), config_show_control(),config_overlay());
		//alert("terminou google");
	}
	else
	{
		//alert("Nao carregou!");
		tentativas++;
		window.setTimeout(function() {
		if (tentativas < 60) //um minuto
		{
			init();
		}
		},
		1000);
	}
	
}