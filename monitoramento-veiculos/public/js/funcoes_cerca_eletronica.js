//******************************************************************************************
//
//	Funcoes para Cerca Eletronica Poligono
//
//******************************************************************************************

var mostrando_mensagem = false;
var map;
var poly;
var count = 0;
var points = new Array();
var markers = new Array();
var icon_url = "_images/";
var tooltip;
var lineColor;
var fillColor;
var lineWeight = 2;
var lineOpacity = .8;
var fillOpacity = .2;
var acao;
var endereco = null;
var piscando = false;
var alerta_alteracao = 'Clique em "Concluir Crian&ccedil;&atilde;o" para efetivar a altera&ccedil;&atilde;o.';

function carregaMapCercaEletronica(cor, acao)
{
	buildMap(cor, acao);
	mudaSizeMap();
}

var pontosPoligono;

function showCerca(cerca_id){
    clearCercasPolygonsPolylines();
    if(cerca_id  != '-1|-1'){
        mostraCerca(cerca_id);
    }
}

function clearCercasPolygonsPolylines(){
	 if(arrayPoligonos.length > 0)
	 {
            for(x = 0; x < arrayPoligonos.length; x++)
            {
                arrayPoligonos[x].setMap(null);
            }
	 }
}
function mostraCerca(cerca_id){
    $.ajax({
        url: root+'/api/getCercaPoligono',
        type: 'GET',
        dataType: 'json',
        data: {
                cerca_id: cerca_id,
        },
        error: function() {
        },
        success: function(res) {
            var markers = res.data;
            arrayPoligonos = Array();
            for(i=0; i<markers.length; i++) {
                var pontos = new Array();
                var poligono = markers[i].poligono;
                for(j=0; j<poligono.length; j++) {
                    pontos.push(new google.maps.LatLng(poligono[j].latitude, poligono[j].longitude));
                }
                var polygon = new google.maps.Polygon({
                    paths: pontos,
                    strokeColor: markers[i].cor,
                    strokeOpacity: lineOpacity,
                    strokeWeight: lineWeight,
                    fillColor: markers[i].cor,
                    fillOpacity: fillOpacity
                });
                arrayPoligonos.push(polygon);
                if (qnt_mapas!=null && qnt_mapas > 0 && mapas.length > 0) {
                    polygon.setMap(mapas[0]);

                }
                else {
                    polygon.setMap(map);
                }
            }
        }
    });
}
	//******************************************************************************************
	//
	//	Fun��es para Cerca Eletronica Rota
	//
	//******************************************************************************************

	var arrayPoligonos = [], arrayPolylines = [];
	

		
