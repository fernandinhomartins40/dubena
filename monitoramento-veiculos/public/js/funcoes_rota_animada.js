var pointsColor = new Array(0);
var pulaPosicionamento;
var divisor = 20;
var zoom;
var lastOverlay;
var to, from;
var delay = 200;
var routeBuildMap;
var routeGoToFrom;
var marcadorPosicao;
var contador_animacao = 0;
var innerCount = 1;
var stopClick = false;
var poly2 = null;
var bMarker;

function pararAnim() {
	var player_pause = document.getElementById('player_pause');
	var player_continuar = document.getElementById('player_continuar');
	var player_rew = document.getElementById('player_rew');
	var player_play = document.getElementById('player_play');
	var player_stop = document.getElementById('player_stop');
	
	var tdControle_animacao = document.getElementById('tdControle_animacao');
	var tdOpcoesCerca = document.getElementById('tdShowDivCerca');
	var tdInfoAnimacao = document.getElementById('tdShowInfoAnimacao');
	
	
	clearTimeout(routeGoToFrom);
	clearTimeout(routeBuildMap);
	stopClick = true;
	
	player_pause.style.display = 'none';
	player_rew.style.display = 'none';
	player_continuar.style.display = 'none';
	player_play.style.display = 'inline';
	player_stop.style.display = 'none';
	
//	tdInfoAnimacao.style.display = 'none';
//	tdInfoAnimacao.style.width = '0';
//
	tdOpcoesCerca.style.display = '';
//	tdOpcoesCerca.style.width = '65%';
	
//	tdControle_animacao.style.display = 'block';
	document.getElementById("tdShowInfoAnimacao").innerHTML = '<div id="showInfoAnimacao"></div>';
}

function pausarAnim() {
	var player_pause = document.getElementById('player_pause');
	var player_continuar = document.getElementById('player_continuar');
	
	if (routeGoToFrom) {
		clearTimeout(routeGoToFrom);
		player_pause.style.display = 'none';
		player_continuar.style.display = 'inline';
	} else if (routeBuildMap) {
		clearTimeout(routeBuildMap);
		player_pause.style.display = 'none';
		player_continuar.style.display = 'inline';
	}
	stopClick = true;
}

function continuarAnim() {
	var player_pause = document.getElementById('player_pause');
	var player_continuar = document.getElementById('player_continuar');
	
	if (stopClick == true) {
		if (routeGoToFrom) {
			goToFrom();
			player_pause.style.display = 'inline';
			player_continuar.style.display = 'none';
		} else if (routeBuildMap) {
			anim();
			player_pause.style.display = 'inline';
			player_continuar.style.display = 'none';
		}
	}
	stopClick = false;
}

function goToFrom() {
	zoom = map.getZoom();
        normalProj = map.getProjection();
        var toPx = normalProj.fromLatLngToPoint(to);
        var fromPx = normalProj.fromLatLngToPoint(from);

        var from_x = fromPx.x;
	var from_y = fromPx.y;
	var to_x = toPx.x;
	var to_y = toPx.y;

	if (innerCount == 1) {
		distance = distanceXY(toPx, fromPx);
                for(i = 0; i < document.getElementsByName('velocidade').length; i++){
                    if (document.getElementsByName('velocidade')[i].checked) {
                        divisor = document.getElementsByName('velocidade')[i].value;
                        if(divisor == 2){
                            pulaPosicionamento = divisor;
                            divisor = 1;
                        }else if(divisor == 3){
                            pulaPosicionamento = divisor;
                            divisor = 1;
                        }else{
                            pulaPosicionamento = divisor;
                        }
                        break;
                    }
                }
	}

	var dx = (to_x - from_x) / divisor;
	var dy = (to_y - from_y) / divisor;
//            alert(innerCount +"<"+ divisor);
	if (innerCount < divisor) {
		var minX = from_x + ((divisor - innerCount) * dx);
		var minY = from_y + ((divisor - innerCount) * dy);
                var newPoint = normalProj.fromPointToLatLng(new google.maps.Point(minX, minY));
                if(bMarker == null){
                    bMarker = new google.maps.Marker({
                                            icon:iconRotaAnimada,
                                            draggable:false
                                        });
                    bMarker.setMap(map);
                }else{
                    bMarker.setPosition(newPoint);
                    bMarker.setIcon(iconRotaAnimada);
                }
                
		if (lastOverlay != null){
                    lastOverlay.setMap(null);
                }
//		map.panTo(bMarker.getPosition());
		lastOverlay = bMarker;
                lastOverlay.setMap(map);
		innerCount++;
		routeGoToFrom = setTimeout("goToFrom();", delay);
	} else {
		routeGoToFrom = setTimeout("anim()", delay);
	}
}

function anim() {
	var infoAnimacao = document.getElementById('showInfoAnimacao');
	contador_animacao++;
        if(pulaPosicionamento==2){
            contador_animacao++;
        }else if(pulaPosicionamento==3){
            contador_animacao++;
            contador_animacao++;
        }
	if (contador_animacao < points.length) {

		if (lastOverlay != null){
                    lastOverlay.setMap(null);
                }
                gmarkers[contador_animacao].setMap(map);
		lastOverlay = gmarkers[contador_animacao];

		if (contador_animacao + 1 < points.length) {
                        to = gmarkers[contador_animacao].getPosition();
			from = gmarkers[contador_animacao + 1].getPosition();
			innerCount = 1;
			iconRotaAnimada = gmarkers[contador_animacao].getIcon(); 
			routeGoToFrom = setTimeout("goToFrom();", delay);
		}
		for ( var i = 0; i <= contador_animacao; i++) {
			pointsColor[i] = points[i];
		}
		
		if(poly2 != null) {
                        poly2.setMap(null);
		}
                poly2 = new google.maps.Polyline({
                            path: pointsColor,
                            strokeColor: "#FF0000",
                            strokeOpacity: 1,
                            strokeWeight: 4
                        });
                poly2.setMap(map);

                map.panTo(points[contador_animacao]);
//		map.setZoom(15);

		infoAnimacao.innerHTML = 
		"<table border='0' width='100%' height='100%' style='text-align:center;'>" +
		"<tr>" + 
			"<td class='table-info' width='38%' style='border:1px solid;'>" +
				"<span style='font-size:17px;'>Velocidade: "+Math.round(infoAnim[contador_animacao]['velocidade'])+" Km/h</span>" +
			"</td>" +
                        "<td width='4%'><b></b></td>" +
			"<td class='table-info' width='58%'  style='border:1px solid;'>" +
				"<span style='font-size:17px;'>Data/Hora: "+formatDateTime(infoAnim[contador_animacao]['data_hora'])+"</span>" +
			"</td>" +
		"</tr>" +
		"</table>";
	} else {
                clearTimeout(routeGoToFrom);
                clearTimeout(routeBuildMap);
                contador_animacao = 0;
                routeGoToFrom = null;
                routeBuildMap = null;
	}
}

function iniciarAnim() {
	var player_pause = document.getElementById('player_pause');
	var player_play = document.getElementById('player_play');
	var player_rew = document.getElementById('player_rew');
	var player_continuar = document.getElementById('player_continuar');
	var player_stop = document.getElementById('player_stop'); 
	
	var tdOpcoesCerca = document.getElementById('tdShowDivCerca');
	var tdInfoAnimacao = document.getElementById('tdShowInfoAnimacao');

        for(var x = 0; x < arrayMarcadoresRotas.length; x++){
            arrayMarcadoresRotas[x].setMap(null);
        }
        Demo.closeInfoWindow();
        
	pointsColor = [];
	if (routeGoToFrom)
		clearTimeout(routeGoToFrom);
	if (routeBuildMap)
		clearTimeout(routeBuildMap);
	stopClick = false;
	contador_animacao = 0;
        map.setZoom(15);
	anim();
	
	player_pause.style.display = 'inline';
	player_play.style.display = 'none';
	player_rew.style.display = 'inline';
	player_continuar.style.display = 'none';
	player_stop.style.display = 'inline';
	
	tdOpcoesCerca.style.display = 'none';
//	tdOpcoesCerca.style.width = '0';
	
	tdInfoAnimacao.style.width = '50%';
	tdInfoAnimacao.style.display = '';
	
}

function distanceXY(point1, point2) {
	var x1 = point1.x;
	var y1 = point1.y;
	var x2 = point2.x;
	var y2 = point2.y;

	var dx = (x1 - x2);
	var dy = (y1 - y2);
	return Math.sqrt((dx * dx) + (dy * dy));
}