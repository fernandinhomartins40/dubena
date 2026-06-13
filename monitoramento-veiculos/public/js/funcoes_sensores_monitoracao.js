var map;
var mgr;
var moveis;
var marcador_rota = false;
var c_zoom_maximo=20;
var tentativas=0;
//representa o codigo do veiculo escolhido
var escolhido=-1;
var escolhido_vel_max=-1;
var escolhido_vel_max_2=-1;
var rotas = new Array(0);
var direcoes = new Array(0);
var filiais;
var veiculos;
var last_veiculo_done = -1;
var contador_velocidade;
var max_contador_velocidade = 10;

//Apaga a rota do escolhido
function apaga_rota()
{
	map.clearOverlays();
	mgr.clearMarkers();

	if (escolhido != -1)
	{				
		while (rotas.length > 0) 
		{
			rotas.pop();
		}
		while (direcoes.length > 0)
		{
			direcoes.pop();
		}
	}	
}

function gera_marcador_sensores(cd_status_posicionamento, lat, lng, nome, data_hora, velocidade, rpm,rpm_cem, altitude, cd_veiculo, cd_motorista)
{
	//alert(cd_status_posicionamento + " : " + lat + " : " + lng + " : " + nome + " : " + data_hora + " : " + velocidade + " : " + altitude + " : " + cd_veiculo + " : " + cd_motorista);
	var marcador;
	if (velocidade > 0) {
		informacoes_sensores = {
			'cd_motorista': '' + cd_motorista + '',
			'latitude': '' + lat + '',
			'longitude': '' + lng + '',
			'sensor': '' + nome + '',
			'acao': 'Desativado',
			'icone': '' + c_icon_sensores_desativado + '',
			'data': '' + data_hora + '',
			'rpm': '' + rpm + '',
			'rpm_cem': '' + rpm_cem + ''
		};		
		marcador = createMarker(lat, lng, informacoes_sensores, velocidade, rpm, rpm_cem, c_icon_sensores_desativado, c_alt_mark, c_comp_mark, c_sensores);
	}else{
		informacoes_sensores = {
			'cd_motorista': '' + cd_motorista + '',
			'latitude': '' + lat + '',
			'longitude': '' + lng + '',
			'sensor': '' + nome + '',
			'acao': 'Ativo',
			'icone': '' + c_icon_sensores_ativo + '',
			'data': '' + data_hora + '',
			'rpm': '' + rpm + '',
			'rpm_cem': '' + rpm_cem + ''
		};
		
		marcador = createMarker(lat, lng, informacoes_sensores, velocidade, rpm, rpm_cem, c_icon_sensores_ativo, c_alt_mark, c_comp_mark, c_sensores);	
	}
	zoomMin = getZoomLevel(17, 15);
	mgr.addMarker(marcador, zoomMin);
}

function empilha_rotas(velocidade_maxima,velocidade_maxima_2)
{
	if(validaData(document.getElementById('data1').value, document.getElementById('data2').value)){
	
		//alert(debug++);
		if (escolhido != -1) {
			var data1 = URLEncode(document.getElementById('data1').value);
			var data2 = URLEncode(document.getElementById('data2').value);
			
			controlaCamada("map","map2");
			
			var request = GXmlHttp.create();
			
			if(data1 != "")
			{
				dia = data1.substr(0, 2);
				mes = data1.substr(5, 2);
				ano = data1.substr(10, 4);
			
				dia = parseInt(dia);
				dia--;
				data3 = ano + "\/" + mes + "\/" + dia;
				request.open('GET', '_ajax/ultimos_sensores_monitoracao.php?cd_veiculo='+escolhido+'&data1='+data1+'&data2='+data2+'&data3='+data3+'&'+ Math.ceil ( Math.random() * 100000 ), true);
			}else{
				dat = new Date();
				dat2 = new Date(dat.getYear(), dat.getMonth(), dat.getDate()-1);	//cria data do dia anterior para pegar a ultima rodo daquele dia		
				data3 = dat2.getYear()+"\/"+(dat2.getMonth()+1)+"\/"+dat2.getDate();
				request.open('GET', '_ajax/ultimos_sensores_monitoracao.php?cd_veiculo='+escolhido+'&data1='+data1+'&data3='+data3+'&'+ Math.ceil ( Math.random() * 100000 ), true);
			}
			
			request.setRequestHeader("Cache-Control", "no-store, no-cache, must-revalidate");	
			request.setRequestHeader("Cache-Control", "post-check=0, pre-check=0");
			request.setRequestHeader("Pragma", "no-cache");
			request.onreadystatechange = function() {
				if (request.readyState == 4) {
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
					
					contador_velocidade = 0;
					for (var i = 0; i < markers.length; i++) {
						var cd_motorista = markers[i].getAttribute("cd_motorista");
						var lng = markers[i].getAttribute("lng");
						var lat = markers[i].getAttribute("lat");
						var cd_veiculo = markers[i].getAttribute("id");
						var velocidade = markers[i].getAttribute("velocidade");
						var data_hora = markers[i].getAttribute("data_hora");
						var azimute = markers[i].getAttribute("azimute");				
						
						//Sensores
						var cd_status_posicionamento = markers[i].getAttribute("cd_status_posicionamento");
						var nome = markers[i].getAttribute("nome");
						var altitude = markers[i].getAttribute("altitude");
						var rpm = markers[i].getAttribute("valor_atual");
						var rpm_cem = markers[i].getAttribute("valor_atual_cem");
						
						//check for lng and lat so MSIE does not error
						//on parseFloat of a null value
						if(cd_veiculo && lng && lat) {
							
							latlng = new GLatLng(lat, lng);
							if (i != (markers.length - 1)) {
								rotas.push(latlng);
							}
							if (cd_status_posicionamento == 8 || cd_status_posicionamento == 9) { 
								gera_marcador_sensores(cd_status_posicionamento, lat, lng, nome, data_hora, velocidade, rpm, rpm_cem, altitude, cd_veiculo, cd_motorista);
							}
							else {
								gera_direcoes(i, latlng, azimute, data_hora, velocidade, rpm, rpm_cem);
							}
						}
					} //for			
					if (rotas.length < 2)
					{
						alert("O Veiculo Nao se Movimentou no Periodo");
						escolhido = -1;
					}
					else
					{
						create_Polyline(rotas);				
						inicia_tabela_resolucao(markers[0].getAttribute("para_retirar"),markers.length);	
						map.setCenter(latlng);
					}
					controlaCamada("map","map2");
				} // if
			}; // onreadychange
		
			request.send(null);
			
		} //escolhido
	}else{
		alert("Período máximo para pesquisa é de 7 dias.");
	}
}

function empilha_rotas_c_hora(velocidade_maxima,velocidade_maxima_2, hora_ini, hora_fim, seguinte)
{
	if (escolhido != -1)
	{
		controlaCamada("map","map2");
		var data1 = URLEncode(document.getElementById('data1').value);
		if (seguinte==true) {
			data = URLEncode(document.getElementById('data2').value);
			dia = data.substr(0, 2);
			mes = data.substr(5, 2);
			ano = data.substr(10, 4);
			
			dia = parseInt(dia);
			dia++;
			data3 = dia + "\/" + mes + "\/" + ano;
			var data2 = data3;
		}
		else{
			var data2 = URLEncode(document.getElementById('data2').value);
		}
		if(data1==null || data1=="")
		{
			var now = new Date();
			var day, month;
			if((""+now.getDate()).length == 1)
			{
				day = "0" + now.getDate();
			}
			else
			{
				day = ""+now.getDate();
			}
			if((""+(now.getMonth()+1)).length == 1)
			{
				month = "0" + (now.getMonth()+1);
			}
			else
			{
				month = ""+(now.getMonth()+1);
			}
			data1 = URLEncode(day + "/" + month + "/" + now.getFullYear());
		}
			
		data_ini = data1 + URLEncode(" " + hora_ini);
		if(data2==null || data2=="")
		{
			data2 = data1;
		}
		var data_fim = data2 + URLEncode(" " + hora_fim);
	
		var request = GXmlHttp.create();
		request.open('GET', '_ajax/ultimos_sensores_monitoracao.php?cd_veiculo='+escolhido+'&data1='+data_ini+'&data2='+data_fim+'&'+ Math.ceil ( Math.random() * 100000 ), true);
		request.setRequestHeader("Cache-Control", "no-store, no-cache, must-revalidate");	
		request.setRequestHeader("Cache-Control", "post-check=0, pre-check=0");
		request.setRequestHeader("Pragma", "no-cache");
		request.onreadystatechange = function() {
			
			if (request.readyState == 4) {
				var latlng;
				var xmlDoc = request.responseXML;
				var marker;
				if (navigator.appName.indexOf('Microsoft') != -1){//internet explorer
					var markers = xmlDoc.getElementsByTagName("posicion");			
				}
				else
				{
					var markers = xmlDoc.documentElement.getElementsByTagName("posicion");
				}				

				for (var i = 0; i < markers.length; i++) {
					var cd_motorista = markers[i].getAttribute("cd_motorista");
					var lng = markers[i].getAttribute("lng");
					var lat = markers[i].getAttribute("lat");
					var cd_veiculo = markers[i].getAttribute("id");
					var velocidade = markers[i].getAttribute("velocidade");
					var data_hora = markers[i].getAttribute("data_hora");
					var azimute = markers[i].getAttribute("azimute");
					//Sensores
					var cd_status_posicionamento = markers[i].getAttribute("cd_status_posicionamento");
					var nome = markers[i].getAttribute("nome");
					var altitude = markers[i].getAttribute("altitude");
					var rpm = markers[i].getAttribute("valor_atual");
					var rpm_cem = markers[i].getAttribute("valor_atual_cem");
						
					//check for lng and lat so MSIE does not error
					//on parseFloat of a null value
					if(cd_veiculo && lng && lat) {
							
							latlng = new GLatLng(lat, lng);
							if (i != (markers.length - 1)) {
								rotas.push(latlng);
							}
							if (cd_status_posicionamento == 8 || cd_status_posicionamento == 9) {
								gera_marcador_sensores(cd_status_posicionamento, lat, lng, nome, data_hora, velocidade, rpm, rpm_cem, altitude, cd_veiculo, cd_motorista);
							}
							else {
								gera_direcoes(i, latlng, azimute, data_hora, velocidade, rpm, rpm_cem);
							}									
					}
									
				} //for			
				if (rotas.length < 2)
				{
					alert("O Veiculo Nao se Movimentou no Periodo");
					escolhido = -1;
				}
				else
				{
					create_Polyline(rotas);				
					inicia_tabela_resolucao(markers[0].getAttribute("para_retirar"),markers.length);
					map.setCenter(latlng);
				}
				//gera_marcadores_parados(markers);
				controlaCamada("map","map2");
			} // if
		}; // onreadychange
	
	request.send(null);
	} //escolhido
}



function gera_rotas(cd_veiculo,velocidade_maxima,velocidade_maxima_2)
{		
		//alert(cd_veiculo);
		apaga_rota();
		escolhido = cd_veiculo;//troca o escolhido
		escolhido_vel_max = velocidade_maxima;
		escolhido_vel_max_2 = velocidade_maxima_2;
		empilha_rotas(velocidade_maxima,velocidade_maxima_2);
		//mgr.refresh();
		last_veiculo_done = cd_veiculo;
		//exportarExcel();				
}

function gera_rotas_c_hora(cd_veiculo, hora_ini, hora_fim,seguinte)
{		
		//se vai gerar do dia seguinte tambem
		if(!seguinte){
			seguinte = false;
		}
		if(last_veiculo_done == -1)
		{
			alert("Primeiro selecione um veiculo.");
			return 0;
		}
		//alert(cd_veiculo);
		apaga_rota();
		escolhido = cd_veiculo;//troca o escolhido
		empilha_rotas_c_hora(escolhido_vel_max,escolhido_vel_max_2, hora_ini, hora_fim, seguinte);
		//mgr.refresh();
		last_veiculo_done = cd_veiculo;
}

function mostra_rotas()
{	
//alert(debug++);
	var request = GXmlHttp.create();	
	var novo;
	mudaSizeMap();
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
			var xmlDoc = request.responseXML;
			if (navigator.appName.indexOf('Microsoft') != -1){//internet explorer
				var markers = xmlDoc.getElementsByTagName("mobile");			
			}
			else
			{
				var markers = xmlDoc.documentElement.getElementsByTagName("mobile");
			}
        	msg = "<table width='100%' border='0' cellspacing='2' cellpadding='2'>";
            msg += "<tr height='22px'>";
            msg += "<td width='90%' background='"+fundo+"' colspan='3' bgcolor='#FFCC00'><strong> &nbsp;.:&nbsp;VE&Iacute;CULOS</strong></td>";
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
				velocidade_maxima_2 = markers[i].getAttribute("velocidade_maxima_2");

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
		     	msg += "<a href='#mapa' onclick='javascript: gera_rotas("+cd_veiculo+","+velocidade_maxima+","+velocidade_maxima_2+")'>";
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
//			document.getElementById("elementos").innerHTML = msg;
		}
	}	
	request.send(null);		
}


/*Função para montar a tabela com a resolução da rota*/
function inicia_tabela_resolucao(coeficiente,max) {
	
	var msg;
	var porcentagem_precisao;
	var porcentagem_perdida;
	
	if(coeficiente == 1) {
		porcentagem_precisao = 100;
		porcentagem_perdida = 0;
	} else {
		porcentagem_perdida = (max + coeficiente)/(coeficiente * 100);
		porcentagem_precisao = 100 - porcentagem_perdida;
	}
	
	msg = "<table cellspacing='0' border='0' style='width: 150px; border: solid 1px #808080  ;'>";
	msg += "<tr>";
	if(porcentagem_precisao == 100) {
		msg += "<td title='Precis&atilde;o' class='progresso_verde' width=100% colspan=2>100%</td>";
	} else {
		msg += "<td title='Precis&atilde;o' class='progresso_verde' width="+porcentagem_precisao+"%>"+Math.round(porcentagem_precisao)+"%</td>";
		msg += "<td title='Perda' class='progresso_vermelho' width="+porcentagem_perdida+"%>"+Math.round(porcentagem_perdida)+"%</td>";
	}
	msg += "</tr>";
	msg += "</table>";
	
	//document.getElementById("label_tabela_resolucao").innerHTML = "Resolu&ccedil;&atilde;o da Rota ";
	document.getElementById("tabela_resolucao").innerHTML = msg;
}

function inicia_rotas(dados_moveis,centerLatitude,centerLongitude,startZoom,show_control,overlay) {	
	//alert(debug++);
	if (GBrowserIsCompatible()) {
		map = new GMap2(document.getElementById("map"));			
		map.enableContinuousZoom();		
		
		map.setUIToDefault();
		map.disableScrollWheelZoom();
/*
		GEvent.addListener(map, "moveend", function() {
		  var center = map.getCenter();
  		  document.getElementById("message").innerHTML = center.toString();
		});		
*/
		//alert(debug++);
		
		map.setCenter(new GLatLng(centerLatitude, centerLongitude),startZoom);
		//alert(debug++);
		mgr = new MarkerManager(map,{'maxZoom':c_zoom_maximo});
		//alert(debug++);
		
		mostra_rotas();
		//inicia_tabela_resolucao();
		setTimeout("showDivCerca(0)",1000);
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
