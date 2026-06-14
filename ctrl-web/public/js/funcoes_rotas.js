var map;
var mapas = [];
var mgr;
var send_empresa = false;
var moveis;
var marcador_rota = false;
var c_zoom_maximo = 20;
var tentativas = 0;
var escolhido = -1;
var escolhido_vel_max = -1;
var escolhido_vel_max_2 = -1;
var rotas = new Array(0);
var id_monitorado = [];
var points = new Array(0);
var gmarkers = new Array(0);
var infoAnim = new Array(0);
var iconRotaAnimada;
var normalProj;

var imagem;
var imagem_parado = c_host + c_caminho + "img/rota_parado.gif";
var imagem_acima = c_host + c_caminho + "img/rota_excesso.gif";
var imagem_normal = c_host + c_caminho + "img/rota_andando.gif";
var velocidade;
var velocidade_maxima_2;
var velocidade_maxima;

var velocidades = new Array(0);
var direcoes = new Array(0);
var marcadores_parados = new Array(0);
var marcadores_rapidos = new Array(0);
var marcadores_sensores = new Array(0);
var arraydirecoes = new Array(0);
var arrayMarcadoresParados = new Array(0);
var arrayMarcadoresRapidos = new Array(0);
var arrayMarcadoresRotas = new Array(0);
var arrayInfoWindow = new Array(0);
var polylines = new Array(0);
var filiais;
var veiculos;
var last_veiculo_done = -1;
var contador_velocidade;
var max_contador_velocidade = 10;

//alert("antes");

//var geocoder = new google.maps.Geocoder();
//A PARTIR DAQUI
function init() {
    if (document.getElementById("map"))
    {
        //alert("iniciando google");
        inicia_rotas(config_dados_moveis(), config_center_latitude(), config_center_longitude(), config_start_zoom(), config_show_control(), config_overlay(), null);
        //alert("terminou google");
    } else
    {
        //alert("Nao carregou!");
        tentativas++;
        window.setTimeout(function () {
            if (tentativas < 60) //um minuto
            {
                init();
            }
        },
                1000);
    }

}
function inicia_rotas(dados_moveis, centerLatitude, centerLongitude, startZoom, show_control, overlay, empresa_id) {
    var myOptions = {
        zoom: startZoom,
        center: new google.maps.LatLng(centerLatitude, centerLongitude),
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        scrollwheel: false
    };
    map = new google.maps.Map(document.getElementById("map"), myOptions);
    mapas.push(map);
    var mgrOptions = {'maxZoom': c_zoom_maximo};
    mgr = new MarkerManager(map, mgrOptions);
    normalProj = map.getProjection();
    lastOverlay = null;
    google.maps.event.addListener(map, 'zoom_changed', function () {
        if (arrayMarcadoresParados != null) {
            for (var j = 0; j < arrayMarcadoresParados.length; j++) {
                var marker = arrayMarcadoresParados[j];
                var icone = cria_icone_rota(marker.getIcon().url, marker.getIcon().size.height, marker.getIcon().size.width, 0, 0)
                marker.setIcon(
                        icone
                        );
            }
        }
        if (arrayMarcadoresRapidos != null) {
            for (var j = 0; j < arrayMarcadoresRapidos.length; j++) {
                var marker = arrayMarcadoresRapidos[j];
                var icone = cria_icone_rota(marker.getIcon().url, marker.getIcon().size.height, marker.getIcon().size.width, 0, 0)
                marker.setIcon(
                        icone
                        );
            }
        }
        if (arrayMarcadoresRotas != null) {
            for (var j = 0; j < arrayMarcadoresRotas.length; j++) {
                var marker = arrayMarcadoresRotas[j];
                var icone = cria_icone(marker.getIcon().url, marker.getIcon().size.height, marker.getIcon().size.width, 0, 0)
                marker.setIcon(
                        icone
                        );
            }
        }        

    });
    //mostra_rotas();
    setTimeout(function () {
        showDivCerca(empresa_id);
    }, 1000);
}
function gera_rotas(cd_veiculo, velocidade_maxima, velocidade_maxima_2, snap)
{

    apaga_rota();
    escolhido = cd_veiculo;//troca o escolhido
    escolhido_vel_max = velocidade_maxima;
    escolhido_vel_max_2 = velocidade_maxima_2;
    empilha_rotas(velocidade_maxima, velocidade_maxima_2, snap);
    mgr.refresh();
    last_veiculo_done = cd_veiculo;
    //exportarExcel();				
}

function empilha_rotas(velocidade_maxima, velocidade_maxima_2, snap)
{
    points = null;
    points = [];
    gmarkers = null;
    gmarkers = [];
    infoAnim = null;
    infoAnim = [];
    $.ajax({
        url: root + '/api/getRotas' + '?' + Math.ceil(Math.random() * 100000),
        type: 'GET',
        dataType: 'json',
        data: {
            veiculo_id: escolhido,
            datainicio: $('#datainicio').val(),
            datafim: $('#datafim').val(),
        },
        error: function () {
        },
        success: function (res) {

            contador_velocidade = 0;
            var markers = res.data;
            for (var i = 0; i < markers.length; i++) {

                var lng = markers[i].longitude;
                var lat = markers[i].latitude;
                var cd_veiculo = markers[i].deviceid;
                var velocidade = markers[i].speed;
                var data_hora = markers[i].dhposition;
                var km_atual = 0; //markers[i].getAttribute("km_atual");
                var show_km = 0; //markers[i].getAttribute("show_km");
                var show_chat = 0; //markers[i].getAttribute("show_chat");

                //check for lng and lat so MSIE does not error
                //on parseFloat of a null value
                if (cd_veiculo && lng && lat) {

                    //latlng = new GLatLng(lat, lng);
                    latlng = new google.maps.LatLng(lat, lng);
                    if (i != (markers.length - 1)) {
                        rotas.push(latlng);
                    }
                    velocidades.push(velocidade);
                    points[i] = latlng; // Array auxiliar para rota animada

                    if (velocidade < velocidade_maxima) {
                        imagem = imagem_normal;
                        if (velocidade == 0)
                            imagem = imagem_parado;
                    } else {
                        imagem = imagem_acima;
                    }
                    imagem = imagem_normal;
                    var image = new google.maps.MarkerImage(imagem, new google.maps.Size(22, 20), new google.maps.Point(0, 0), new google.maps.Point(11, 10));
                    iconRotaAnimada = new google.maps.Marker({
                        position: points[i],
                        icon: image,
                        draggable: false
                    });
                    iconRotaAnimada.setZIndex(c_zoom_min_info);
                    gmarkers[i] = iconRotaAnimada;
                    arrayVeloDh = new Array(2);
                    arrayVeloDh['velocidade'] = velocidade;
                    arrayVeloDh['data_hora'] = data_hora;
                    arrayVeloDh['km_atual'] = km_atual;
                    arrayVeloDh['show_km'] = show_km;
                    arrayVeloDh['show_chat'] = show_chat;
                    infoAnim[i] = arrayVeloDh;
                }
            } //for			
            if (markers.length < 2)
            {
                bootbox.alert("O Veiculo Nao se Movimentou no Periodo");
                escolhido = -1;
            } else
            {
                points.reverse(); // Inverte o array para animacao
                gmarkers.reverse(); // Inverte o array para animacao
                infoAnim.reverse(); // Inverte o array para animacao
                //create_Polyline(rotas);				
                if(snap == 1){
                    cria_marcador_rota(res.veiculo, markers[0].dhposition, markers[0].course);
                    gera_marcadores_parados(markers, res.veiculo, snap);
                    habilita_rota_animada();
                } else
                if(snap == 2){
                    cria_marcador_rota(res.veiculo, markers[0].dhposition, markers[0].course);
                    gera_marcadores_parados(markers, res.veiculo, snap);
                    habilita_rota_animada();
                    var snaps = snapToRoad(res.data, res.veiculo, snap);
                } else {
                    var snaps = snapToRoad(res.data, res.veiculo, snap);
                    cria_marcador_rota(res.veiculo, markers[0].dhposition, markers[0].course);
                    gera_marcadores_parados(markers, res.veiculo, snap);
                    habilita_rota_animada();
                }

            }
            //controlaCamada("map", "map2");

        }
    });
}
//Apaga a rota do escolhido
function apaga_rota()
{
    //alert(debug++);
    //map.clearOverlays();
    if (mgr != undefined) {
        mgr.clearMarkers();
    }

    if (escolhido != -1)
    {
        while (rotas.length > 0) {
            rotas.pop();
        }
        for (var x = 0; x < rotas.length; x++) {
            rotas[x].setMap(null);
        }

        while (velocidades.length > 0) {
            velocidades.pop();
        }
        for (var x = 0; x < velocidades.length; x++) {
            velocidades[x].setMap(null);
        }

        while (direcoes.length > 0) {
            direcoes.pop();
        }
        for (var x = 0; x < direcoes.length; x++) {
            direcoes[x].setMap(null);
        }

        while (marcadores_parados.length > 0) {
            marcadores_parados.pop();
        }
        for (var x = 0; x < marcadores_parados.length; x++) {
            marcadores_parados[x].setMap(null);
        }

        while (marcadores_rapidos.length > 0) {
            marcadores_rapidos.pop();
        }
        for (var x = 0; x < marcadores_rapidos.length; x++) {
            marcadores_rapidos[x].setMap(null);
        }

        for (var x = 0; x < polylines.length; x++) {
            polylines[x].setMap(null);
        }
        for (var x = 0; x < arraydirecoes.length; x++) {
            arraydirecoes[x].setMap(null);
        }
        for (var x = 0; x < arrayMarcadoresParados.length; x++) {
            arrayMarcadoresParados[x].setMap(null);
        }
        for (var x = 0; x < arrayMarcadoresRapidos.length; x++) {
            if (arrayMarcadoresRapidos[x] != null)
                arrayMarcadoresRapidos[x].setMap(null);
        }
        for (var x = 0; x < arrayMarcadoresRotas.length; x++) {
            if (arrayMarcadoresRotas[x] != null)
                arrayMarcadoresRotas[x].setMap(null);
        }

        //alert("apagou rota");
        rotas = new Array(0);
        velocidades = new Array(0);
        direcoes = new Array(0);
        marcadores_parados = new Array(0);
        marcadores_rapidos = new Array(0);
        polylines = new Array(0);
        marcadores_rotas = new Array(0);
        arraydirecoes = new Array(0);
        arrayMarcadoresParados = new Array(0);
        arrayMarcadoresRapidos = new Array(0);
        arrayMarcadoresRotas = new Array(0);

        if (poly2 != null) {
            poly2.setMap(null);
        }
        if (bMarker != null) {
            bMarker.setMap(null);
        }
    }
    //alert("apagou rota");
}

function cria_marcador_rota(veiculo, data, azimute)
{
    //alert(debug++);
    var ltlng;
    if (escolhido != -1)
    {
        if (rotas.length > 0)
        {
            velocidade = velocidades[0];
            ltlng = rotas[0];
            var modelo = URLDecode(veiculo.descricao);
            var imagem_paradoaux = 'img/' + veiculo.veiculotipo.imagem_parado;
            var imagem_normalaux = 'img/' + veiculo.veiculotipo.imagem_movimento;
            var imagem_acimaaux = 'img/' + veiculo.veiculotipo.imagem_acima;
            velocidade_maxima = veiculo.veiculotipo.velocidade_maxima;
            velocidade_maxima_2 = veiculo.veiculotipo.velocidade_maxima;
            
            km_atual = 0;
            show_km = 0;
            show_chat = 0;
            var identificador = veiculo.deviceid;
            cd_motorista = veiculo.id;
            quilometragem = calcula_distancia();
            var dados = {
                'id': '' + escolhido + '',
                'cd_motorista': '' + cd_motorista + '',
                'modelo': '' + modelo + '',
                'data': '' + data + '',
                'identificacao': '' + identificador + '',
                'image_parado': '' + imagem_paradoaux + '',
                'image_normal': '' + imagem_normalaux + '',
                'image_acima': '' + imagem_acimaaux + '',
                'velocidade_maxima': '' + velocidade_maxima + '',
                'velocidade_maxima_2': '' + velocidade_maxima_2 + '',
                'velocidade': '' + velocidade + '',
                'quilometragem': '' + quilometragem + '',
                'km_atual': '' + km_atual + '',
                'show_km': '' + show_km + '',
                'show_chat': '' + show_chat + ''
            }
            if (marcador_rota) {
                //map.removeOverlay(marcador_rota);
                marcador_rota.setMap(null);
            }

            if (velocidade < velocidade_maxima) {
                imagem = imagem_normalaux;
                if (velocidade == 0)
                    imagem = imagem_parado;
            } else {
                imagem = imagem_acimaaux;
            }
            imagem = imagem_normalaux;
            iconImage = imagem + '_' + calcula_graus(azimute) + '.png';
            Demo.map = map;
            google.maps.event.addListener(map, 'click', Demo.closeInfoWindow);
            var retorno = createMarker(ltlng.lat(), ltlng.lng(), dados, velocidade, null, null, iconImage, c_alt_mark, c_comp_mark, c_rota);//cria marcador dinamico
            marcador_rota = retorno[0];
            arrayInfoWindow.push(retorno[1]);
            //marcador_rota.setMap(map);
            mgr.addMarker(marcador_rota, c_zoom_min_mark);
//                                                map.setCenter(marcador_rota.getPosition());
            map.panTo(marcador_rota.getPosition());
            map.setZoom(15);

            //marcador_rota.setZIndex(c_zoom_min_info);
            clicar(marcador_rota, moveToMapa);
            mgr.refresh();
            //map.addOverlay(marcador_rota);
            abre_informacoes(marcador_rota, dados, c_dinamico, velocidade);
            arrayMarcadoresRotas.push(marcador_rota);
        }
    }
}

function gera_marcadores_parados(markers, veiculo, snap){
	var i = 0;
	var menor;
	var datain,datafim;
	var tempo_parado;	
	var qnt_rotas_parada = 0;
	var latlng_parados = new Array(0);
		
	var qtdeRotas = 0;
        var qtdePontos = 0;
	while (i < markers.length-1)
	{
		var cd_motorista = veiculo.id;
		var lng = markers[i].longitude;
		var lat = markers[i].latitude;
		var cd_veiculo = markers[i].deviceid;
		var velocidade = markers[i].speed;
		var data_hora = markers[i].dhposition;
		var azimute = markers[i].course;
                var show_km = 0;
                var km_atual = 0;
                var show_chat = 0;
		//var latlng = new GLatLng(lat, lng);
                var latlng = new google.maps.LatLng(lat, lng);
		tempo_parado = 0;		
		menor = parseFloat(velocidades[(i)]) <= 0.0;
		if (menor) //para pegar as velocidades do vetor que forem muito baixas
		{				
			var informacoes_parado;
                        if(show_km == '1'){
                            informacoes_parado = {
                                    'cd_motorista': ''+veiculo.id+'',
                                    'latitude': ''+markers[(i)].latitude+'',
                                    'longitude': ''+markers[(i)].longitude+'',
                                    'data_fim': ''+markers[(i)].dhposition+'',
                                    'km_atual': ''+ '0' +'',
                                    'show_km': ''+'0'+'',
                                    'show_chat': ''+'0'+'',
                                    'data_inicio': 0,
                                    'tempo_parado' : 0
                            };
                        }else{
                            informacoes_parado = {
                                    'cd_motorista': ''+veiculo.id+'',
                                    'latitude': ''+markers[(i)].latitude+'',
                                    'longitude': ''+markers[(i)].longitude+'',
                                    'data_fim': ''+markers[(i)].dhposition+'',
                                    'data_inicio': 0,
                                    'tempo_parado' : 0
                            };
                        }			
			
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
				informacoes_parado.data_inicio = markers[(i+1)].dhposition;
				datain = new Date(
				informacoes_parado.data_inicio.substr(0,4),//ano
				informacoes_parado.data_inicio.substr(5,2),//mes
				informacoes_parado.data_inicio.substr(8,2),//dia
				informacoes_parado.data_inicio.substr(11,2),//hora
				informacoes_parado.data_inicio.substr(14,2),//minuto
				informacoes_parado.data_inicio.substr(17,2),//segundo
				00);
				informacoes_parado.tempo_parado=Math.abs(Math.floor((datain-datafim)/1000));
				
				tempo_parado = informacoes_parado.tempo_parado;
				
                                if (markers.length+1 == i) {
                                        informacoes_parado.latitude = markers[i + 1].latitude;
                                        informacoes_parado.longitude = markers[i + 1].longitude;
                                }
				
				i++;
//				count++;
				if (i < markers.length)
					menor = parseFloat(velocidades[(i+1)]) <= 0.0;
                                else
					menor = false;
			}// while
		} // if
		if (tempo_parado > c_max_tempo_parado)  // cria o marcador de parada após c_max_tempo_parado segundos
		{							
			//alert('tempo_parado '+tempo_parado);
			informacoes_parado.tempo_parado = Math.floor(informacoes_parado.tempo_parado/3600)+" h "+Math.floor((informacoes_parado.tempo_parado%3600)/60)+" m "+Math.floor(informacoes_parado.tempo_parado%60)+" s";
                        Demo.map = map;
                        google.maps.event.addListener(map, 'click', Demo.closeInfoWindow);
			var retorno = createMarker(informacoes_parado.latitude,informacoes_parado.longitude,informacoes_parado,0,null,null,c_imagem_parado,17,27,c_parado, null, 5, 29);
                        marcador = retorno[0];
                        //marcador.setMap(map);
                        //marcador.setZIndex(c_zoom_min_info);
			marcadores_parados.push(marcador);
                        arrayMarcadoresParados.push(marcador);
//		   	map.addOverlay(marcador);
			mgr.addMarker(marcador, c_zoom_min_info);
			latlng_parados.push(latlng);
			qnt_rotas_parada++;
                        qtdePontos++;
		}else if(parseFloat(velocidades[(i)]) > escolhido_vel_max){
			var markerT = gera_marcador_velocidade_acima(cd_motorista,lat,lng,velocidade,escolhido_vel_max,escolhido_vel_max_2,data_hora, i, km_atual, show_km);
                        arrayMarcadoresRapidos.push(markerT);
//                        if(map != null){
//                            markerT.setMap(map);
//                        }
                        qtdePontos++;
		}else{
		    if (! $("#somente_paradas").is(":checked"))
                        if(snap!=2){
                            var markerTemp = gera_direcoes(i, latlng, azimute, data_hora, velocidade, null, null, km_atual, show_km);
                            //markerTemp.setMap(map);
                            arraydirecoes.push(markerTemp);
                            qtdeRotas++;
                            qtdePontos++;
                        }
		}
		i++;
	}// while

        mgr.refresh();
        if(snap!=2){
            if (qnt_rotas_parada > 0) {
                    var rotas_temp = new Array(0);
                    for (ii=0;ii < markers.length ;ii++) {
                            var lng = markers[ii].longitude;
                            var lati = markers[ii].latitude;
                            var latlng = new google.maps.LatLng(lati, lng);

                            rotas_temp.push(latlng);
                            for (j = 0; j < latlng_parados.length; j++) {
                                    if (latlng_parados[j].lat() == lati) {
                                            if (rotas_temp.length >= 2 && lng!=0) {						
                                                    var polyline = create_PolylineCores(rotas_temp);
                                                    if(polyline != null){
                                                        polyline.setMap(map);
                                                        polylines.push(polyline);
                                                    }
                                                    var t = rotas_temp[rotas_temp.length-1];
                                                    var rotas_temp = new Array(0);
                                                    rotas_temp.push(t);
                                            }											
                                    }
                            }		
                    }
                    if (rotas_temp.length >= 2 && lng!=0) {
                            var polyline = create_PolylineCores(rotas_temp);
                            if(polyline != null){
                                polyline.setMap(map);
                                polylines.push(polyline);
                            }
                    }
            }else{
                    var polyline = create_Polyline(rotas);
                    if(polyline != null){
                        polyline.setMap(map);
                        polylines.push(polyline);
                    }
            }
        }
}

function habilita_rota_animada() {
    
	var htmlAnim = 
	"<div align='right'><fieldset><legend>Controle de Anima&ccedil;&atilde;o</legend>" +
	"<table width='100%;' height='100%' style='text-align:center;'>" + 
	"<tr>" + 
	"<td>" +
		"<img id='player_rew' style='cursor: pointer; display:none;' src='img/player_rew.png' onclick='iniciarAnim();return false;'></img>" +
		"<img id='player_play' style='cursor: pointer;' src='img/player_play.png' onclick='iniciarAnim();return false;'></img>" +
	"</td>" +
	"<td>" +
		"<img id='player_continuar' style='cursor: pointer; display:none;' src='img/player_play.png' onclick='continuarAnim();return false;'></img>" +
		"<img id='player_pause' style='cursor: pointer; display:none;' src='img/player_pause.png' onclick='pausarAnim();return false;'></img>" +
	"</td>" +
	"<td>" +
		"<img id='player_stop' style='cursor: pointer; display:none;' src='img/player_stop.png' onclick='pararAnim();return false;'></img>" +
	"</td>" +
//	"</tr>" +
//      "<tr>" +
        "<td colspan='3'>" +
        "<input type='radio' name='velocidade' id='velocidade' value='5'> -2x "+
        "<input type='radio' name='velocidade' id='velocidade' value='4'> -1x " +
        "<input type='radio' name='velocidade' id='velocidade' value='1' checked> 1x " +
        "<input type='radio' name='velocidade' id='velocidade' value='2'> +1x " +
        "<input type='radio' name='velocidade' id='velocidade' value='3'> +2x " +
        "</td>" +
        "</tr>" +
	"</table>" + 
	"</fieldset></div>";
	//document.getElementById("tdControle_animacao").innerHTML = htmlAnim;
        $('#tdControle_animacao').html(htmlAnim);
//	document.getElementById('tdControle_animacao').style.display = 'block';
}

function gera_marcador_velocidade_acima(cd_motorista,lat,lng,velocidade,velocidade_maxima,velocidade_maxima_2,data_hora, i, km_atual, show_km){
	var informacoes_rapidas;
	var marcador;
	if (contador_velocidade < max_contador_velocidade)
	{ 
		if (parseFloat(velocidade) > velocidade_maxima)
		{
			informacoes_rapidas={		
				'cd_motorista': ''+cd_motorista+'',	
				'latitude': ''+lat+'',
				'longitude': ''+lng+'',
				'velocidade': ''+velocidade+'',
				'velocidade_maxima': ''+velocidade_maxima+'',
				'data': ''+data_hora+'',
                                'km_atual': ''+km_atual+'',
                                'show_km': ''+show_km+''
			};
                        Demo.map = map;
                        google.maps.event.addListener(map, 'click', Demo.closeInfoWindow);
			var retorno = createMarker(lat,lng,informacoes_rapidas,velocidade,null,null,c_imagem_rapido,19,29,c_rapido, null, 5, 29);
                        marcador = retorno[0];
                        //marcador.setMap(map);
                        var zoomMin = getZoomLevel(17, i+1);
			marcadores_rapidos.push(marcador);
                        arrayMarcadoresRapidos.push(marcador);
			//map.addOverlay(marcador);
			mgr.addMarker(marcador, zoomMin);
			contador_velocidade++;
		}
		else contador_velocidade = 0;
	}
	else
	{
		if (parseFloat(velocidade) > velocidade_maxima_2)
		{
			informacoes_rapidas={		
				'cd_motorista': ''+cd_motorista+'',	
				'latitude': ''+lat+'',
				'longitude': ''+lng+'',
				'velocidade': ''+velocidade+'',
				'velocidade_maxima': ''+velocidade_maxima_2+'',
				'data': ''+data_hora+'',
                                'km_atual': ''+km_atual+'',
                                'show_km': ''+show_km+''
			};
                        Demo.map = map;
                        google.maps.event.addListener(map, 'click', Demo.closeInfoWindow);
			var retorno = createMarker(lat,lng,informacoes_rapidas,velocidade,null,null,c_imagem_rapido,19,29,c_rapido, null, 5, 29);
                        marcador = retorno[0];
                        //marcador.setMap(map);
                        var zoomMin = getZoomLevel(17, i+1);
			marcadores_rapidos.push(marcador);
                        arrayMarcadoresRapidos.push(marcador);
			//map.addOverlay(marcador);
			mgr.addMarker(marcador, zoomMin);
		}
		else contador_velocidade = 0;
	}
	return marcador;
}
function snapToRoad(dados, veiculo, snap){
  var setOfPaths = [];
  var pathValues = [];
  var j = 0;
  for (var i = 0; i < dados.length; i++) {
    pathValues.push(dados[i].latitude + ',' + dados[i].longitude);
    j++;
    if(j>=100){
      setOfPaths.push(pathValues);
      pathValues = [];
      j=0;
    }
  }
  console.log(pathValues);
  if(pathValues.length>0){
      setOfPaths.push(pathValues);
  }
  for(ii=0;ii<setOfPaths.length;ii++){
    $.get('https://roads.googleapis.com/v1/snapToRoads', {
      interpolate: true,
      key: googlemapskey,
      path: setOfPaths[ii].join('|')
    }, function(data) {
      snappedCoordinates = [];
      placeIdArray = [];
      for (var i = 0; i < data.snappedPoints.length; i++) {
        var latlng = new google.maps.LatLng(
            data.snappedPoints[i].location.latitude,
            data.snappedPoints[i].location.longitude);
        snappedCoordinates.push(latlng);
        placeIdArray.push(data.snappedPoints[i].placeId);
        //if(snap==2){
        //    if(data.snappedPoints[i].originalIndex != null){
        //        var idx = data.snappedPoints[i].originalIndex;
        //        gera_direcoes(idx, latlng, dados[idx].course, dados[idx].dhposition, dados[idx].speed,0, 0, 0, 0)
        //    }
        //}
      }
      var snappedPolyline = new google.maps.Polyline({
      path: snappedCoordinates,
      strokeColor: 'green',
      strokeWeight: 3
    });

    snappedPolyline.setMap(map);
    polylines.push(snappedPolyline);
    });
  }
  if(snap==2){
    i=0;
    while (i < dados.length-1)
    {
        var latlng = new google.maps.LatLng(
              dados[i].latitude,
              dados[i].longitude);
        geraDirecoesSnap(i, latlng, dados[i].course, dados[i].dhposition, dados[i].speed,0, 0, 0, 0);
        i++;
    }
  }
}
function geraDirecoesSnap(i,latlng,azimute, data_hora, velocidade,rpm, rpm_cem, km_atual, show_km)
{
	var iconImage;
	var marker;
	var icon;

        if (azimute%c_graus > c_graus_met)
                azimute = azimute - (azimute%c_graus) + c_graus;
        else
                azimute = azimute - (azimute%c_graus);
        iconImage = c_host+c_caminho+'img/direcaosnap/'+azimute+'.png';
        icon = cria_icone(iconImage,c_alt_dir,c_comp_dir);

        marker = new google.maps.Marker({
            position: latlng,
            icon:icon
        });

        direcoes.push(marker);

        var informacoes_seta;
        if(show_km == '1'){
            informacoes_seta = {
                    'data_hora': ''+data_hora+'',
                    'velocidade': ''+velocidade+' Km/h',
                    'km_atual': ''+km_atual+'',
                    'show_km': ''+show_km+''
            };
        }else{
            informacoes_seta = {
                    'data_hora': ''+data_hora+'',
                    'velocidade': ''+velocidade+' Km/h',
                    'show_km': ''+show_km+''
            };
        }

        var zoomMin = getZoomLevel(17, i);
        mgr.addMarker(marker, zoomMin);

        if(rpm_cem == null) {
            abre_informacoes(marker,informacoes_seta, c_seta,velocidade);
//                    google.maps.event.addListener(marker, 'click', function(){
//        		abre_informacoes(marker,informacoes_seta, c_seta,velocidade);
//                    });
        } else {
            abre_informacoes(marker,informacoes_seta, c_seta,velocidade,rpm,rpm_cem);
//                    google.maps.event.addListener(marker, 'click', function(){
//        		abre_informacoes(marker,informacoes_seta, c_seta,velocidade,rpm,rpm_cem);
//                    });
        }
        return marker;
}
