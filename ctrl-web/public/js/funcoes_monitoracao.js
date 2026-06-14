var map;
var mapas = [];
var mgr;
var rodando = false;
var mgrs = [];
var peds = [];
var centrosMap = [];
var moveis;
var pedidos;
var marcadores_moveis;
var marcadores_pedidos;
var marcadores_moveis_cada;
var marcadores_estaticos = [];
var arrayInfoTabs = [];
var arrayMarkerMonitoracao = [];
var arrayMarkerPedidos = [];
var estaticos;
var id_monitorado = [];
var monitorados = [];
var tentativas = 0;
var c_zoom_maximo = 20;
var zoom_mapas = [];
var c_tempo_refresh = 10000;
var revenda_id = -1;
var qnt_enderecos = 0;
var cd_veiculo_end = [];
var enderecos = [];

var popup = null;
//alert("antes");

//var geocoder = new google.maps.Geocoder();

function setNoVeiculo() {
    id_monitorado[0] = -1;
}

function apagar_macadores_moveis(idMapa) {
    if (marcadores_moveis != null) {
        if (marcadores_moveis[idMapa] != null) {
            for (var y = 0; y < arrayMarkerMonitoracao.length; y++) {
                arrayMarkerMonitoracao[y].setMap(null);
            }

            //for (i in mgrs) {
            //    mgrs[idMapa][i].setMap(null);
            //}
            //mgrs[idMapa][i].length = 0;

            for (aux in marcadores_moveis[idMapa]) {
                //if(moveis[aux].alterou){
                marcadores_moveis[idMapa][aux].setMap(null);
                //}
            }
            for (var x = 0; x < arrayInfoTabs.length; x++) {
                arrayInfoTabs[x].close();
            }
            arrayInfoTabs = [];
        }
    } else {
        init();
    }
}

function apagar_macadores_pedidos(idMapa) {
    if (marcadores_pedidos != null) {
        if (marcadores_pedidos[idMapa] != null) {
            for (var y = 0; y < arrayMarkerPedidos.length; y++) {
                arrayMarkerPedidos[y].setMap(null);
            }

            for (aux in marcadores_pedidos[idMapa]) {
                marcadores_pedidos[idMapa][aux].setMap(null);
            }
            marcadores_pedidos[idMapa] = [];
        }
    }
    arrayMarkerPedidos = [];
}

function cria_marcador_movel(id, idMapa) {
    if (moveis != null && moveis[id] != null && moveis[id].aparece && mgrs[idMapa]) {
        if (id == id_monitorado[idMapa]) {
            if (parseFloat(moveis[id].velocidade) < moveis[id].dados.velocidade_maxima) {
                if (moveis[id].velocidade == 0) {
                    moveis[id].image = moveis[id].dados.image_parado;
                } else {
                    moveis[id].image = moveis[id].dados.image_normal;
                }
            } else {
                moveis[id].image = moveis[id].dados.image_acima;
            }
        } else {
            if (parseFloat(moveis[id].velocidade) < moveis[id].dados.velocidade_maxima) {
                moveis[id].image = moveis[id].dados.image_normal;
                if (moveis[id].velocidade == 0) moveis[id].image = moveis[id].dados.image_parado;
            } else {
                moveis[id].image = moveis[id].dados.image_acima;
            }
        }
        moveis[id].image = moveis[id].dados.image_normal;
        Demo.map = mapas[idMapa];
        google.maps.event.addListener(mapas[idMapa], "click", Demo.closeInfoWindow);
        var retorno = createMarkerMovel(
            moveis[id].latitude,
            moveis[id].longitude,
            moveis[id].dados,
            moveis[id].velocidade,
            moveis[id].rpm,
            moveis[id].rpm_cem,
            moveis[id].image,
            c_alt_mark,
            c_comp_mark,
            c_dinamico,
            idMapa,
            0,
            0,
            moveis[id].azimute
        );
        var temp = retorno[0];
        //arrayInfoTabs.push(retorno[1]);
        arrayInfoTabs.push(retorno[1]);
        temp.setMap(mapas[idMapa]);
        arrayMarkerMonitoracao.push(temp);
        var marcadores_moveis_temp = [];
        //marcadores_moveis_temp = marcadores_moveis[idMapa];
        marcadores_moveis_temp[id] = temp;
        marcadores_moveis[idMapa] = marcadores_moveis_temp;
        if (idMapa == null || idMapa == "") {
            idMapa = 0;
        }
        if (id == id_monitorado[idMapa]) {
            mapas[idMapa].panTo(temp.getPosition());
            google.maps.event.trigger(temp, "click");
        }
    } else {
        window.setTimeout(function () {
            cria_marcador_movel(id, idMapa);
        }, 2000);
    }
}
function cria_marcador_pedido(id, idMapa) {
    if (pedidos[id].latitude == "") {
        if (geocoder) {
            geocoder.getLocations(pedidos[id].endereco, function (point) {
                addMarkerPedido(point, idMapa, id);
            });
        }
    } else {
        var point = new google.maps.LatLng(pedidos[id].latitude, pedidos[id].longitude);
        addMarkerPedidoPonto(point, idMapa, id);
    }
}

function inicia_estaticos(dados_estaticos) {
    //alert(debug++);
    estaticos = new Array(dados_estaticos.length);
    for (var id = 0; id < dados_estaticos.length; id++) {
        estaticos[dados_estaticos[id].id] = {
            latitude: dados_estaticos[id].latitude,
            longitude: dados_estaticos[id].longitude,
            dados: dados_estaticos[id],
            image: dados_estaticos[id].image,
        };
    }
    return estaticos;
}

function cria_marcadores_estaticos(ii) {
    //mgrs[ii].clearMarkers();
    for (id in estaticos) {
        var retorno = createMarker(
            estaticos[id].latitude,
            estaticos[id].longitude,
            estaticos[id].dados,
            0,
            0,
            0,
            estaticos[id].image,
            c_alt_mark_e,
            c_comp_mark_e,
            c_estatico,
            ii,
            0,
            0
        );
        var temp_est = retorno[0];
        temp_est.setMap(mapas[0]);
        //map.addOverlay(marcadores_estaticos[id]);
        if (ii == 0) {
            marcadores_estaticos[id] = temp_est;
        }
        //mgrs[ii].addMarker(temp_est, c_zoom_min_mark_e);
        mgrs[ii].push(temp_est);
    }
}

function movimenta_marcadores(grupos) {
    //, idMapa
    store_retrieveUltimasPosicoes(grupos);
    if (c_busca_pedidos) {
        //} && grupos==1) {
        busca_pedidos(grupos);
    }
}

function store_retrieveUltimasPosicoes(grupos) {
    $.ajax({
        url: root + "/api/getPosicaoAtual" + "?" + Math.ceil(Math.random() * 100000),
        type: "GET",
        dataType: "json",
        data: {
            grupo_id: grupos,
        },
        error: function () {},
        success: function (res) {
            markers = res.data;
            for (x = 0; x < qnt_mapas; x++) {
                apagar_macadores_moveis(x);
                //cria_marcadores_estaticos(x);
            }
            for (i = 0; i < markers.length; i++) {
                var cd_motorista = markers[i].veiculo_id;
                var lng = parseFloat(markers[i].longitude);
                var lat = parseFloat(markers[i].latitude);
                var id = markers[i].veiculo_id;
                var data = markers[i].datahora;
                var velocidade = parseFloat(
                    markers[i].velocidade == null ? "0" : markers[i].velocidade
                );
                var rpm = 0;
                var rpm_cem = 0;
                var status_acesso = 0;
                var azimute = parseFloat(markers[i].azimute);
                //check for lng and lat so MSIE does not error
                //on parseFloat of a null value

                if (id && lng && lat) {
                    if (moveis != null && moveis[id] != null) {
                        moveis[id].alterou = true;
                        if (lat == moveis[id].latitude && lng == moveis[id].longitude) {
                            moveis[id].alterou = false;
                        }
                        moveis[id].aparece = true;
                        moveis[id].dados.cd_motorista = cd_motorista;
                        moveis[id].latitude = lat;
                        moveis[id].longitude = lng;
                        moveis[id].velocidade = velocidade;
                        moveis[id].rpm = rpm;
                        moveis[id].rpm_cem = rpm_cem;
                        moveis[id].dados.data = data;
                        moveis[id].dados.status_acesso = status_acesso;
                        moveis[id].azimute = azimute;
                    }
                    for (x = 0; x < qnt_mapas; x++) {
                        cria_marcador_movel(id, x);
                    }
                }
            }
            window.setTimeout(function () {
                movimenta_marcadores(grupos);
            }, c_tempo_refresh);
        },
    });
}

function busca_pedidos(grupos) {
    $.ajax({
        url: root + "/api/getPedidosPendentes" + "?" + Math.ceil(Math.random() * 100000),
        type: "GET",
        dataType: "json",
        data: {
            grupo_id: grupos,
        },
        error: function () {},
        success: function (res) {
            for (x = 0; x < qnt_mapas; x++) {
                apagar_macadores_pedidos(x);
            }
            markerPedidos = res.data.dados;
            inicia_pedidos(markerPedidos);
            for (i = 0; i < markerPedidos.length; i++) {
                var id = markerPedidos[i].codigo_pedido;
                var endereco = markerPedidos[i].endereco;
                var urgente = markerPedidos[i].entrega_urgente;
                var dif = markerPedidos[i].dif;
                var envio = markerPedidos[i].data_hora_envio;
                var cliente = markerPedidos[i].razao_social;
                var setor = markerPedidos[i].desc_setores;
                var colaborador = markerPedidos[i].nome_colaborador;
                var latitude = markerPedidos[i].latitude;
                var longitude = markerPedidos[i].longitude;
                var tempoentrega;
                var tempourgente;
                try {
                    tempoentrega = markerPedidos[i].tempo.split("|")[0];
                    tempourgente = markerPedidos[i].tempo.split("|")[1];
                } catch {
                    tempoentrega = 15;
                    tempourgente = 10;
                }

                if (id && endereco) {
                    pedidos[i].endereco = endereco;
                    pedidos[i].id = id;
                    pedidos[i].dif = dif;
                    pedidos[i].urgente = urgente;
                    pedidos[i].envio = envio;
                    pedidos[i].cliente = cliente;
                    pedidos[i].setor = setor;
                    pedidos[i].colaborador = colaborador;
                    pedidos[i].latitude = latitude;
                    pedidos[i].longitude = longitude;
                    pedidos[i].tempoentrega = tempoentrega;
                    pedidos[i].tempourgente = tempourgente;

                    for (x = 0; x < qnt_mapas; x++) {
                        cria_marcador_pedido(i, x);
                    }
                } //if
            }
        },
    });
}

function posicao_inicial_movel(dados_moveis, grupos) {
    $.ajax({
        url: root + "/api/getPosicaoAtual" + "?" + Math.ceil(Math.random() * 100000),
        type: "GET",
        dataType: "json",
        data: {
            grupo_id: grupos,
        },
        error: function () {},
        success: function (res) {
            markers = res.data;

            for (i = 0; i < markers.length; i++) {
                var cd_motorista = markers[i].veiculo_id;
                var lng = parseFloat(markers[i].longitude);
                var lat = parseFloat(markers[i].latitude);
                var id = markers[i].veiculo_id;
                var data = markers[i].datahora;
                var velocidade = markers[i].velocidade;
                var rpm = 0;
                var rpm_cem = 0;
                var status_acesso = 0;
                var azimute = parseFloat(markers[i].azimute);
                //check for lng and lat so MSIE does not error
                //on parseFloat of a null value

                if (id && lng && lat) {
                    if (moveis != null && moveis[id] != null) {
                        moveis[id].aparece = true;
                        moveis[id].dados.cd_motorista = cd_motorista;
                        moveis[id].latitude = lat;
                        moveis[id].longitude = lng;
                        moveis[id].velocidade = velocidade;
                        moveis[id].rpm = rpm;
                        moveis[id].rpm_cem = rpm_cem;
                        moveis[id].dados.data = data;
                        moveis[id].dados.status_acesso = status_acesso;
                        moveis[id].azimute = azimute;
                    }
                    for (x = 0; x < qnt_mapas; x++) {
                        cria_marcador_movel(id, x);
                    }
                }
            }
            window.setTimeout(function () {
                movimenta_marcadores(grupos);
            }, c_tempo_refresh);
        },
    });
}
/*
function criaDescricao(msg) {
    var html_descricao = '<table width="98%" border="0" align="center" cellpadding="0" cellspacing="0">';
    html_descricao += '<tr>';
    html_descricao += '<td bgcolor="#fffbd9">';
    html_descricao += '<table width="100%" border="0" cellspacing="0" cellpadding="0">';
    html_descricao += '<tr>';
    html_descricao += '<td width="10"><img src="_images/pixel.gif" width="1" height="1" /></td>';
    html_descricao += '<td height="38" valign="middle">';
    html_descricao += '<span class="cor2">' + msg + '</span>';
    html_descricao += '</td>';
    html_descricao += '</tr>';
    html_descricao += '</table>';
    html_descricao += '</td>';
    html_descricao += '</tr>';
    html_descricao += '</table>';
    html_descricao += '<BR />';
    return html_descricao;
}
*/
function adicionarNovoMapa() {
    //daqui para frente monta os mapa
    var fator = 1.2;
    if (qnt_mapas != null) {
        if (qnt_mapas >= 3) {
            fator = 2.2;
        }
    }

    var tam = document.body.parentNode.clientHeight / fator;
    var encerrar = 0;

    var html_tabela = "<table width='100%'>";
    for (i = 1; i <= qnt_mapas; i++) {
        //if(id_monitorado[i].length == 0){
        id_monitorado[i - 1] = -1;
        //}
        var wid = "100%";
        if (qnt_mapas == 2) {
            wid = "49%";
        } else if (qnt_mapas >= 3) {
            wid = "32%";
        }
        if (i == 1 || i % 3 == 1) {
            encerrar = i + 3;
            html_tabela += "<tr width='100%'>";
        }
        html_tabela += "<td width='" + wid + "' heigth='" + tam + "px'>";
        html_tabela += "<div id='map_add" + i + "'></div>";
        html_tabela += "</td>";
        if (i == encerrar || i == qnt_mapas) {
            html_tabela += "</tr>";
        }
    }
    html_tabela += "</table>";
    document.getElementById("mapas2").innerHTML = html_tabela;
    mudaSizeMap();
}

function teste() {
    console.log('qnt_mapas: ', qnt_mapas);
    if (qnt_mapas < 6) {
        qnt_mapas++;

        for (i = 0; i < qnt_mapas; i++) {
            if (i + 1 == qnt_mapas) {
                //centrosMap[i] = new GLatLng(config_center_latitude(), config_center_longitude());
                centrosMap[i] = new google.maps.LatLng(
                    config_center_latitude(),
                    config_center_longitude()
                );
                zoom_mapas[i] = config_start_zoom();
            } else {
                //centrosMap[i] = new GLatLng(mapas[i].getCenter().lat(), mapas[i].getCenter().lng());
                centrosMap[i] = new google.maps.LatLng(
                    mapas[i].getCenter().lat(),
                    mapas[i].getCenter().lng()
                );
                zoom_mapas[i] = mapas[i].getZoom();
            }
        }

        adicionarNovoMapa();

        for (i = 0; i < qnt_mapas; i++) {
            var myOptions = {
                zoom: zoom_mapas[i],
                center: centrosMap[i],
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                scrollwheel: false,
                disableDefaultUI: false,
            };

        //     //carrega mapa do google
            mapas[i] = new google.maps.Map(document.getElementById("map_add" + (i + 1)), myOptions);
            if (mapas[i] != null && mapas[i] != undefined) {
                var map = mapas[i];
                google.maps.event.addListener(map, "zoom_changed", function () {
                    if (arrayMarkerMonitoracao != null) {
                        for (var j = 0; j < arrayMarkerMonitoracao.length; j++) {
                            var marker = arrayMarkerMonitoracao[j];
                            var icone = cria_icone(
                                marker.getIcon().url,
                                marker.getIcon().size.height,
                                marker.getIcon().size.width,
                                0,
                                0
                            );
                            marker.setIcon(icone);
                        }
                    }
                    if (arrayMarkerPedidos != null) {
                        for (var j = 0; j < arrayMarkerPedidos.length; j++) {
                            //change the size of the icon
                            var marker = arrayMarkerPedidos[j];
                            var icone = cria_icone(
                                marker.getIcon().url,
                                marker.getIcon().size.height,
                                marker.getIcon().size.width,
                                4,
                                0
                            );
                            marker.setIcon(icone);
                        }
                    }
                    if (mgrs != null) {
                        mgr1 = mgrs[0];
                        for (var j = 0; j < mgrs[0].length; j++) {
                            //change the size of the icon
                            var marker = mgrs[0][j];
                            var icone = cria_icone(
                                marker.getIcon().url,
                                marker.getIcon().size.height,
                                marker.getIcon().size.width,
                                4,
                                0
                            );
                            marker.setIcon(icone);
                        }
                    }
                });

                mgrs[i] = [];
                if (qnt_mapas == 1) {
                    estaticos = inicia_estaticos(config_dados_estaticos());
                }
                cria_marcadores_estaticos(i);
            } else {
                mapas[i] = new google.maps.Map(
                    document.getElementById("map_add" + (i + 1)),
                    myOptions
                );

                mgrs[i] = [];
                if (qnt_mapas == 1) {
                    estaticos = inicia_estaticos(config_dados_estaticos());
                    //marcadores_estaticos = new Array(estaticos.length - 1);
                }
                //cria marcadores atrav�s da variavel global estaticos
                cria_marcadores_estaticos(i);
            }
        }

        //inicia variavel global moveis conforme os dados moveis
        if (qnt_mapas == 1) {
            inicia_moveis(config_dados_moveis());
            //marcadores_estaticos=estaticos;
        }
        setTimeout("showDivCerca(0)", 1000);
    }
}

function inicia_moveis(dados_moveis) {
    //alert(debug++);
    moveis = new Array(dados_moveis.length);
    marcadores_moveis = new Array(moveis.length);

    for (var i = 0; i < dados_moveis.length; i++) {
        moveis[dados_moveis[i].id] = {
            aparece: false,
            latitude: 0.0,
            longitude: 0.0,
            velocidade: 0.0,
            rpm: 0,
            rpm_cem: 0,
            cd_veiculo: dados_moveis[i].id,
            dados: dados_moveis[i],
            image: dados_moveis[i].image_normal,
            azimute: 0,
            alterou: true,
        };
    } // for
    revenda_id = dados_moveis[0].revenda_id;
    posicao_inicial_movel(dados_moveis, dados_moveis[0].cd_grupo);
}

function centralizar_estatico(marker) {
    centraliza(marker.getPosition().lat(), marker.getPosition().lng());
}
function muda_monitorado(id) {
    valorCombo = 0;
    var aux = id_monitorado[valorCombo];
    //console.log(moveis);
    //alert(moveis[id].aparece);
    if (moveis[id].aparece) {
        id_monitorado[valorCombo] = id;
        if (aux != -1) {
            apaga_marcador(marcadores_moveis[0][aux]);
            cria_marcador_movel(aux, valorCombo);
        }
        apaga_marcador(marcadores_moveis[0][id]);
        cria_marcador_movel(id, valorCombo);
        clicar(marcadores_moveis[0][id], moveToMapa());
        centraliza(moveis[id].latitude, moveis[id].longitude);
    } else {
        if (aux != -1) {
            apaga_marcador(marcadores_moveis[0][aux]);
            cria_marcador_movel(aux, valorCombo);
        }
        id_monitorado[valorCombo] = -1;
        alert("Não há dados para este veículo");
    }
}

function init_muda_monitorado(id) {
    if (moveis == null || moveis[id] == undefined) {
        window.setTimeout("init_muda_monitorado(" + id + ")", 400);
    } else {
        window.setTimeout("muda_monitorado(" + id + ")", 3000);
    }
}

function init() {
    if (document.getElementById("map_add1") != null && qnt_mapas != undefined) {
        //alert("iniciando google");
        //alert(config_dados_moveis(),config_dados_estaticos(),config_center_latitude(), config_center_longitude(), config_start_zoom(), config_show_control(),config_overlay());
        //inicia_monitoracoes(config_dados_moveis(),config_dados_estaticos(),config_center_latitude(), config_center_longitude(), config_start_zoom(), config_show_control(),config_overlay());
        mapas = new Array();
        teste();
        //alert("terminou google");
    } else {
        //alert("Nao carregou!");
        tentativas++;
        window.setTimeout(function () {
            if (tentativas < 60) {
                //um minuto
                init();
            }
        }, 1000);
    }
}

function inicia_pedidos(dados_pedidos) {
    pedidos = new Array(dados_pedidos.length);
    marcadores_pedidos = new Array(pedidos.length);
    for (var i = 0; i < dados_pedidos.length; i++) {
        pedidos[i] = {
            aparece: false,
            endereco: dados_pedidos[i].endereco,
            urgente: dados_pedidos[i].pedido_urgente,
            dif: dados_pedidos[i].dif,
            envio: dados_pedidos[i].envio,
            cliente: dados_pedidos[i].cliente,
            setor: dados_pedidos[i].setor,
            colaborador: dados_pedidos[i].colaborador,
            latitude: dados_pedidos[i].latitude,
            longitude: dados_pedidos[i].longitude,
            id: dados_pedidos[i].id,
            tempoentrega: dados_pedidos[i].tempoentrega,
            tempourgente: dados_pedidos[i].tempourgente,
        };
    }
}
function addMarkerPedido(response, idMapa, id) {
    //if(pedidos[id].latitude != ''){
    //   alert (response);
    //}
    if (!response.Placemark) {
        return;
    }
    if (pedidos[id].latitude != "") {
        //alert ("ped=" + " lat=" + pedidos[id].latitude + " long=" + pedidos[id].longitude);
    }
    place = response.Placemark[0];
    point = new new google.maps.LatLng(place.Point.coordinates[1], place.Point.coordinates[0])();

    var retorno = createMarkerPedido(
        point,
        place.address,
        place.Point.coordinates[1],
        place.Point.coordinates[0],
        id,
        idMapa
    ); // cria o marcador no mapa / creates the marker on the map

    var temp = retorno;
    arrayMarkerPedidos.push(temp);
    var marcadores_pedidos_temp = [];
    marcadores_pedidos_temp[id] = temp;
    marcadores_pedidos[idMapa] = marcadores_pedidos_temp;
}

function addMarkerPedidoPonto(response, idMapa, id) {
    // cria o marcador no mapa
    var temp = createMarkerPedido(
        response,
        pedidos[id].endereco,
        pedidos[id].latitude,
        pedidos[id].longitude,
        id,
        idMapa
    );
    arrayMarkerPedidos.push(temp);
    var marcadores_pedidos_temp = [];
    marcadores_pedidos_temp[id] = temp;
    marcadores_pedidos[idMapa] = marcadores_pedidos_temp;
}

function createMarkerPedido(point, html, latitude, longitude, id, idMapa) {
    var marker;
    var icon;
    //alert(pedidos[id].dif);
    if ((pedidos[id].urgente = "N")) {
        if (pedidos[id].dif <= pedidos[id].tempoentrega * 0.8) {
            icon = cria_icone(c_icon_verde, 20, 20, c_dinamico, 0);
        } else {
            if (pedidos[id].dif <= pedidos[id].tempoentrega) {
                icon = cria_icone(c_icon_amarelo, 20, 20, c_dinamico, 0);
            } else {
                icon = cria_icone(c_icon_vermelho, 20, 20, c_dinamico, 0);
            }
        }
    } else {
        if (pedidos[id].dif <= pedidos[id].tempourgente * 0.8) {
            icon = cria_icone(c_icon_verde, 20, 20, c_dinamico, 0);
        } else {
            if (pedidos[id].dif <= pedidos[id].tempourgente) {
                icon = cria_icone(c_icon_amarelo, 20, 20, c_dinamico, 0);
            } else {
                icon = cria_icone(c_icon_vermelho, 20, 20, c_dinamico, 0);
            }
        }
    }
    var myLatlng = new google.maps.LatLng(latitude, longitude);
    marker = new google.maps.Marker({
        position: myLatlng,
        visible: true,
        icon: icon,
        map: mapas[0],
    });

    var infoTabs = abre_informacoes_pedido(marker, idMapa, id);

    //retorno[0] = marker;
    //retorno[1] = infoTabs;
    return marker;
}

function abre_informacoes_pedido(marker, idMapa, i) {
    var dados = { id: 1 };
    Demo.map = mapas[idMapa];
    if (marker) {
        var info =
            "<table><tr><td style='text-align:right;padding-right:8px;'><strong>Pedido:</strong></td><td>" +
            pedidos[i].id +
            "</td></tr>" +
            "<tr><td style='text-align:right;padding-right:8px;'><strong>Setor:</strong></td><td>" +
            pedidos[i].setor +
            "</td></tr>" +
            "<tr><td style='text-align:right;padding-right:8px;'><strong>Entregador:</strong></td><td>" +
            pedidos[i].colaborador +
            "</td></tr>" +
            "<tr><td style='text-align:right;padding-right:8px;'><strong>Cliente:</strong></td><td>" +
            pedidos[i].cliente +
            "</td></tr>" +
            "<tr><td style='text-align:right;padding-right:8px;'><strong>Envio:</strong></td><td>" +
            pedidos[i].envio +
            "</td></tr>" +
            "<tr><td style='text-align:right;padding-right:8px;'><strong>Urgente:</strong></td><td>" +
            pedidos[i].urgente +
            "</td></tr>" +
            "<tr><td style='text-align:right;padding-right:8px;'><strong>Endere&ccedil;o:</strong></td><td>" +
            pedidos[i].endereco +
            "</td></tr></table>";
        var infoTabs = new google.maps.InfoWindow({
            content: "<div id='bodyContent'>" + info + "</div>",
        });
        google.maps.event.addListener(marker, "click", function () {
            Demo.openInfoWindow(marker, info, 0, dados);
        });
        return infoTabs;
    } else {
        alert("Problemas ao encontrar marcador!");
    }
}
