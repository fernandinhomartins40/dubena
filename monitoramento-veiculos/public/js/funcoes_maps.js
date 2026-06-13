var rpmAtual;
var rpmCemAtual;
var qnt_mapas = 0;
var qnt_rotas = 0;
var c_cor_rota = "#0024ff";
var c_cor_rota2 = "#FF0000";
var c_cor_rota3 = "#006400";
var c_cor_rota4 = "#FF1493";
var c_cor_rota5 = "#00F5FF";
var c_cor_rota6 = "#C0FF3E";
var c_cor_rota7 = "#912CEE";
var c_imagem_monitorado = c_host + c_caminho + "img/monitoracao.gif";
var c_imagem_monitorado_parado = c_host + c_caminho + "img/monitoracao_parado.gif";
var c_imagem_monitorado_acima = c_host + c_caminho + "img/monitoracao_acima.gif";
var c_imagem_rapido = c_host + c_caminho + "img/rota_excesso.gif";
var c_imagem_parado = c_host + c_caminho + "img/rota_parado.gif";
var fundo = c_host + c_caminho + "img/fundo_cel.jpg";
var c_zoom_info = 15;
var c_maximo_polyline = 500;
var c_velocidade_parado = 1;
var c_graus = 15;
var c_icon_aceclerometro_aceleracao_acima = "img/icones_acelerometro/acel_acel_grave.png";
var c_icon_acelerometro_frenagem_acima = "img/icones_acelerometro/acel_fre_grave.png";
var c_icon_acelerometro_esquerda_acima = "img/icones_acelerometro/acel_esq_grave.png";
var c_icon_acelerometro_direita_acima = "img/icones_acelerometro/acel_dir_grave.png";
var c_icon_acelerometro_aceleracao_media = "img/icones_acelerometro/acel_acel_media.png";
var c_icon_acelerometro_frenagem_media = "img/icones_acelerometro/acel_fre_media.png";
var c_icon_acelerometro_esquerda_media = "img/icones_acelerometro/acel_esq_media.png";
var c_icon_acelerometro_direita_media = "img/icones_acelerometro/acel_dir_media.png";

var c_icon_sensores_ativo = "img/ico_sensor_ativado.png";
var c_icon_sensores_desativado = "img/ico_sensor_desativado.png";

var c_icon_verde = "img/entrega_normal.gif";
var c_icon_amarelo = "img/entrega_alerta.gif";
var c_icon_vermelho = "img/entrega_atraso.gif";

var c_graus_met = 7.5;
var c_max_tempo_parado = 0; //setado dinamicamente atraves do grupo
//var c_max_tempo_parado = 300;//cinco minutos
//var c_max_tempo_parado = 10;//dez segundos
var c_offset_dir = 4;
var c_offset_vel = 8;
var c_alt_dir = 22;
var c_comp_dir = 22;
var c_zoom_min_dir = 9;
var c_exp_rota = 5;
var c_opacidade = 0.4;
var c_alt_mark = 45;
var c_comp_mark = 45;
var c_zoom_min_mark = 9;
var c_numLevels = 5;
var c_zoomFactor = 4;
var c_scale = 1.01;

var c_alt_mark_e = 20;
var c_comp_mark_e = 20;
var c_zoom_min_mark_e = 5;
var c_parado = 1;
var c_rapido = 2;
var c_estatico = 3;
var c_dinamico = 4;
var c_rota = 5;
var c_seta = 6;
var c_chat = 7;
var c_acelerometro = 8;
var c_sensores = 9;
var c_zoom_min_info = 12;
var c_busca_pedidos = true;

function clicar(marker, funcao) {
    google.maps.event.trigger(marker, "click");
    if (typeof (funcao) == "function")
        funcao.call();
}

function moveToMapa() {
    document.location.hash = "#map_add1";
    document.location.hash = "#mapa";
}

function centraliza(centerLatitude, centerLongitude)
{
    mapas[0].panTo(new google.maps.LatLng(centerLatitude, centerLongitude));
}

function progress() {
    $("#pb1").progressBar(rpmCemAtual, {
        max: 100,
        text_value: rpmAtual,
        barImage: {
            0: 'img/progressbar/progressbg_green.gif',
            40: 'img/progressbar/progressbg_orange.gif',
            70: 'img/progressbar/progressbg_red.gif'
        }
    });
}

function cria_informacoes(dados, tipo, velocidade, rpm, rpm_cem)
{
    if (rpm_cem != null) {
        rpmCemAtual = rpm_cem;
        rpmAtual = rpm;
        setTimeout("progress()", 200);
    }
    var infoTabs;
    var ret = [];
    switch (tipo) {
        case c_chat:
            ret[0] = "<table><tr><td  style='text-align:right;padding-right:8px;'><strong>Data:</strong></td><td>" + separador_data(URLDecode(dados.data)) + "</td></tr>" +
                    "<tr><td  style='text-align:right;padding-right:8px;'><strong>Nome:</strong></td><td>" + URLDecode(dados.nome) + "</td></tr>" +
                    "<tr><td  style='text-align:right;padding-right:8px;'><strong>Mensagem:</strong></td><td>" + URLDecode(dados.mensagem) + "</td></tr></table>";
            ret[1] = new google.maps.InfoWindow({content:
                        "<div id='bodyContent'>" +
                        ret[0] +
                        "</div>"});
            break;
        case c_parado:
            if (dados.show_km == '1') {
                ret[0] = "<table><tr><td><img src='img/clock.png'/></td></tr>" +
                        "<tr><td  style='text-align:right;padding-right:8px;'><strong>Condutor:</strong></td><td>" + URLDecode(get_motorista(dados.cd_motorista)) + "</td></tr>" +
                        "<tr><td  style='text-align:right;padding-right:8px;'><strong>O tempo foi de:</strong></td><td>" + dados.tempo_parado + "</td></tr>" +
                        "<tr><td  style='text-align:right;padding-right:8px;'><strong>Máximo Permitido:</strong></td><td>" + Math.floor(c_max_tempo_parado / 3600) + " h " + Math.floor((c_max_tempo_parado % 3600) / 60) + " m " + Math.floor(c_max_tempo_parado % 60) + " s" + "</td></tr>" +
                        "<tr><td  style='text-align:right;padding-right:8px;'><strong>Data Início:</strong></td><td>" + separador_data(dados.data_inicio) + "</td></tr>" +
                        "<tr><td  style='text-align:right;padding-right:8px;'><strong>Data Fim:</strong></td><td>" + separador_data(dados.data_fim) + "</td></tr>" +
                        "<tr><td  style='text-align:right;padding-right:8px;'><strong>Hodometro:</strong</td><td>" + Math.round(dados.km_atual * 10) / 10 +
                        "</table>";
                ret[1] = new google.maps.InfoWindow({content:
                            "<div id='bodyContent'>" +
                            ret[0] +
                            "</div>"});
            } else {
                ret[0] = "<table><tr><td><img src='img/clock.png'/></td></tr>" +
                        "<tr><td  style='text-align:right;padding-right:8px;'><strong>Condutor:</strong></td><td>" + URLDecode(get_motorista(dados.cd_motorista)) + "</td></tr>" +
                        "<tr><td  style='text-align:right;padding-right:8px;'><strong>O tempo foi de:</strong></td><td>" + dados.tempo_parado + "</td></tr>" +
                        "<tr><td  style='text-align:right;padding-right:8px;'><strong>Máximo Permitido:</strong></td><td>" + Math.floor(c_max_tempo_parado / 3600) + " h " + Math.floor((c_max_tempo_parado % 3600) / 60) + " m " + Math.floor(c_max_tempo_parado % 60) + " s" + "</td></tr>" +
                        "<tr><td  style='text-align:right;padding-right:8px;'><strong>Data Início:</strong></td><td>" + separador_data(dados.data_inicio) + "</td></tr>" +
                        "<tr><td  style='text-align:right;padding-right:8px;'><strong>Data Fim:</strong></td><td>" + separador_data(dados.data_fim) + "</td></tr>" +
                        "</table>";
                ret[1] = new google.maps.InfoWindow({content:
                            "<div id='bodyContent'>" +
                            ret[0] +
                            "</div>"});
            }
            break;

        case c_rapido:
            if (dados.show_km == '1') {
                ret[0] = "<table><tr><td><img src='img/excesso.png'/></td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Condutor:</strong></td><td>" + URLDecode(get_motorista(dados.cd_motorista)) + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Velocidade:</strong></td><td>" + Math.round(velocidade) + " km/h" + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Velocidade Maxima:</strong></td><td>" + dados.velocidade_maxima + " km/h" + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Data:</strong></td><td>" + separador_data(dados.data) + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Hodometro:</strong></td><td>" + Math.round(dados.km_atual * 10) / 10 + " km" + "</td></tr>" +
                        "</table>";
                ret[1] = new google.maps.InfoWindow({content:
                            "<div id='bodyContent'>" +
                            ret[0] +
                            "</div>"});
            } else {
                ret[0] = "<table><tr><td><img src='img/excesso.png'/></td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Condutor:</strong></td><td>" + URLDecode(get_motorista(dados.cd_motorista)) + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Velocidade:</strong></td><td>" + Math.round(velocidade) + " km/h" + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Velocidade Maxima:</strong></td><td>" + dados.velocidade_maxima + " km/h" + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Data:</strong></td><td>" + separador_data(dados.data) + "</td></tr>" +
                        "</table>";
                ret[1] = new google.maps.InfoWindow({content:
                            "<div id='bodyContent'>" +
                            ret[0] +
                            "</div>"});
            }
            break;

        case c_estatico:
            ret[0] = "<table><tr><td><img src='" + URLDecode(dados.image) + "'/></td></tr> " +
                    "<tr><td style='text-align:right;padding-right:8px;'><strong>Setor:</strong></td><td>" + URLDecode(dados.estabelecimento) + "</td></tr>" +
                    "<tr><td style='text-align:right;padding-right:8px;'><strong>Endereço:</strong></td><td>" + URLDecode(dados.endereco) + "</td></tr>" +
                    "<tr><td style='text-align:right;padding-right:8px;'><strong>Cidade:</strong></td><td>" + URLDecode(dados.cidade) + "</td></tr>" +
                    "<tr><td style='text-align:right;padding-right:8px;'><strong>Estado:</strong></td><td>" + URLDecode(dados.estado) + "</td></tr></table>";
            ret[1] = new google.maps.InfoWindow({content:
                        "<div id='bodyContent'>" +
                        ret[0] +
                        "</div>"});
            break;
        case c_dinamico:
            var msg = "<table border='0'><tr><td colspan='1'>";
            if (dados.status_acesso == 1)
                msg += "<img src='img/conectado.png' title='Ve&iacute;culo Conectado'/>";
            if (dados.status_acesso == 0)
                msg += "<img src='img/desconectado.png' title='Ve&iacute;culo Desconectado'/>";
            msg += "<td align='right'><img src='img/mymac.png'/></td></tr>";
            msg += "<tr><td style='text-align:right;padding-right:8px;'><strong>Modelo:</strong></td><td colspan='1'>" + URLDecode(dados.modelo) + "</td></tr>"
                    + "<tr><td style='text-align:right;padding-right:8px;'><strong>Data:</strong></td><td colspan='1'>" + separador_data(dados.data) + "</td></tr>"
                    + "<tr><td style='text-align:right;padding-right:8px;'><strong>Velocidade:</strong></td><td colspan='1'>" + Math.round(velocidade) + " Km/h" + "</td></tr>"
                    + "<tr><td style='text-align:right;padding-right:8px;'><b>Motorista:</b></td><td colspan='1'>" + URLDecode(get_motorista(dados.cd_motorista)) + "</td></tr>";
            if (dados.show_km == '1') {
                msg += "<tr><td style='text-align:right;padding-right:8px;'><strong>Hodometro:</strong></td><td colspan='1'>" + Math.round(dados.km_atual * 10) / 10 + " Km</td></tr>";
            }
            if (rpm_cem != "") {
                msg += "<tr><td colspan='2' height='20'><span class='progressBar' id='pb1'></span></td></tr>";
            }

            if (dados.status_acesso == '1')
            {
                msg += "<tr><td colspan='2' height='20'><a href=sensores.php?cd_veiculo=" + dados.id + "><img border='0' src='img/sensores.png' title='Sensores e Atuadores'></a>";
                msg += "&nbsp;&nbsp;";
                if (dados.show_chat == '1') {
                    msg += "<a href=chat_on_line.php?cd_veiculo=" + dados.id + "&cd_motorista=" + dados.cd_motorista + "><img border='0' src='img/chat.png' title='Chat On Line'></a>";
                }
            }
            msg += "</td></tr></table>";
            ret[0] = msg;
            ret[1] = new google.maps.InfoWindow({content:
                        "<div id='bodyContent'>" +
                        msg +
                        "</div>"});

            break;

        case c_rota:
            if (dados.show_km == '1') {
                ret[0] = "<table>" +
                        "<tr><td><img src='img/keys.png'/></td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Modelo:</strong></td><td>" + dados.modelo + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Quilometragem:</strong></td><td>" + Math.floor(dados.quilometragem / 1000) + ' Km e ' + Math.floor(dados.quilometragem % 1000) + ' m' + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Velocidade:</strong></td><td>" + Math.round(velocidade) + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Hodometro:</strong></td><td>" + Math.round(dados.km_atual * 10) / 10 + " Km</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Data:</strong></td><td>" + separador_data(dados.data) + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Motorista:</strong></td><td>" + URLDecode(get_motorista(dados.cd_motorista)) + "<td><tr>" +
                        "</table>";
                ret[1] = new google.maps.InfoWindow({content:
                            "<div id='bodyContent'>" +
                            ret[0] +
                            "</div>"});
            } else {
                ret[0] = "<table>" +
                        "<tr><td><img src='img/keys.png'/></td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Modelo:</strong></td><td>" + dados.modelo + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Quilometragem:</strong></td><td>" + Math.floor(dados.quilometragem / 1000) + ' Km e ' + Math.floor(dados.quilometragem % 1000) + ' m' + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Velocidade:</strong></td><td>" + Math.round(velocidade) + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Data:</strong></td><td>" + separador_data(dados.data) + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Motorista:</strong></td><td>" + URLDecode(get_motorista(dados.cd_motorista)) + "</td></tr>" +
                        "</table>";
                ret[1] = new google.maps.InfoWindow({content:
                            "<div id='bodyContent'>" +
                            ret[0] +
                            "</div>"});
            }
            break;

        case c_seta:
            var msg = "<table><tr><td><img src='img/flecha.png'/></td></tr>" +
                    "<tr><td  style='text-align:right;padding-right:8px;'><strong>Data/hora:</strong></td><td>" + separador_data(dados.data_hora) + "</td></tr>" +
                    "<tr><td   style='text-align:right;padding-right:8px;'><strong>Velocidade:</strong></td><td>" + Math.round(velocidade) + " Km</td></tr>";
            if (dados.show_km == '1') {
                msg += "<tr><td style='text-align:right;padding-right:8px;'><strong>Hodometro:</strong></td><td>" + Math.round(dados.km_atual * 10) / 10 + " Km</td></tr>";
            }
            if (rpm_cem != null) {
                msg += "<tr><td height='20' colspan='2'><span class='progressBar' id='pb1'></span></td></tr>";
            }
            msg += "</table>";
            ret[0] = msg;
            ret[1] = new google.maps.InfoWindow({content:
                        "<div id='bodyContent'>" +
                        msg +
                        "</div>"});
            break;

        case c_acelerometro:
            if (dados.show_km == '1') {
                ret[0] = "<table><tr><td><img src='" + URLDecode(dados.icone) + "'/></td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Condutor:</strong></td><td>" + URLDecode(get_motorista(dados.cd_motorista)) + "</td></tr>" +
                        //"<tr><td><strong>Velocidade:</strong></td><td>" + Math.round(velocidade) + " km/h"+"</td></tr>"+
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Ação:</strong></td><td>" + URLDecode(dados.posicionamento_acelerometro_nome) + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Força:</strong></td><td>" + dados.valor + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Data:</strong></td><td>" + separador_data(dados.data) + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Hodometro:</strong></td><td>" + Math.round(dados.km_atual * 10) / 10 + "</td></tr>" +
                        "</table>";
                ret[1] = new google.maps.InfoWindow({content:
                            "<div id='bodyContent'>" +
                            ret[0] +
                            "</div>"});
            } else {
                ret[0] = "<table><tr><td><img src='" + URLDecode(dados.icone) + "'/></td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Condutor:</strong></td><td>" + URLDecode(get_motorista(dados.cd_motorista)) + "</td></tr>" +
                        //"<tr><td><strong>Velocidade: </strong></td><td>" + Math.round(velocidade) + " km/h"+"</td></tr>"+
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Ação:</strong></td><td>" + URLDecode(dados.posicionamento_acelerometro_nome) + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Força:</strong></td><td>" + dados.valor + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Data:</strong></td><td>" + separador_data(dados.data) + "</td></tr>" +
                        "</table>";
                ret[1] = new google.maps.InfoWindow({content:
                            "<div id='bodyContent'>" +
                            ret[0] +
                            "</div>"});
            }
            break;

        case c_sensores:
            if (dados.show_km == '1') {
                ret[0] = "<table><tr><td><img src='" + URLDecode(dados.icone) + "'/></td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Condutor:</strong></td><td>" + URLDecode(get_motorista(dados.cd_motorista)) + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Sensor:</strong></td><td>" + dados.sensor + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Status:</strong></td><td>" + dados.acao + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Data:</strong></td><td>" + separador_data(dados.data) + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Hodometro:</strong</td><td>" + Math.round(dados.km_atual * 10) / 10 + "</td></tr>" +
                        "</table>";
                ret[1] = new google.maps.InfoWindow({content:
                            "<div id='bodyContent'>" +
                            ret[0] +
                            "</div>"});
            } else {
                ret[0] = "<table><tr><td><img src='" + URLDecode(dados.icone) + "'/></td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Condutor:</strong></td><td>" + URLDecode(get_motorista(dados.cd_motorista)) + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Sensor:</strong></td><td>" + dados.sensor + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Status:</strong></td><td>" + dados.acao + "</td></tr>" +
                        "<tr><td style='text-align:right;padding-right:8px;'><strong>Data:</strong</td><td>" + separador_data(dados.data) + "</td></tr>" +
                        "</table>";
                ret[1] = new google.maps.InfoWindow({content:
                            "<div id='bodyContent'>" +
                            ret[0] +
                            "</div>"});
            }
            break;
    }

    return ret;
}


function abre_informacoes(marker, dados, tipo, velocidade, rpm, rpm_cem, ii)
{
    var m;
    if (ii != null) {
        m = mapas[ii];
        if (m == null)
            m = map;
    } else {
        m = map;
    }

    if (marker)
    {
        var ret = cria_informacoes(dados, tipo, velocidade, rpm, rpm_cem);
        var infoTabs = ret[1];
        if (ret.length > 0)
        {
            google.maps.event.addListener(marker, 'click', function () {
                Demo.openInfoWindow(marker, ret[0], tipo, dados);
            });
            return infoTabs;
        } else
        {
            alert("Problemas ao criar informações!");
        }
    } else {
        alert("Problemas ao encontrar marcador!");
    }
}

function cria_icone(iconImage, alt, comp, cX, cY)
{
    if ((cX == undefined && cY == undefined) || (cX == 0 && cY == 0)) {
        cX = alt / 2;
        cY = comp / 2;
    }
    var minPixelSize = 45;
    var maxPixelSize = 135; //restricts the maximum size of the icon, otherwise the browser will choke at higher zoom levels trying to scale an image to millions of pixels
    var pixelSizeAtZoomPadrao = c_alt_mark; //the size of the icon at zoom level 0
    if (iconImage.indexOf('entrega') != -1) {
        pixelSizeAtZoomPadrao = c_alt_mark_e;
        minPixelSize = c_alt_mark_e;
        maxPixelSize = 80;
    } else if (iconImage.indexOf('default') != -1) {
        pixelSizeAtZoomPadrao = c_alt_mark_e;
        minPixelSize = c_alt_mark_e;
    }
    var zoom = config_start_zoom();
    if (map != undefined) {
        zoom = map.getZoom();
    } else {
        zoom = mapas[0].getZoom();
    }
    var zoomdif = zoom - config_start_zoom();

    var relativePixelSize = zoomdif * c_scale * pixelSizeAtZoomPadrao;
    if (!isNaN(zoomdif) && zoomdif > 0) {
        if (relativePixelSize > maxPixelSize) //restrict the maximum size of the icon
            relativePixelSize = maxPixelSize;
        if (relativePixelSize < minPixelSize) //restrict the maximum size of the icon
            relativePixelSize = minPixelSize;
    } else {
        relativePixelSize = alt;
    }

    icon = {url: iconImage,
        size: new google.maps.Size(relativePixelSize, relativePixelSize),
        origin: new google.maps.Point(0, 0),
        anchor: new google.maps.Point(cX, cY),
        scaledSize: new google.maps.Size(relativePixelSize, relativePixelSize)
    };
    //MarkerImage(url:string, size?:Size, origin?:Point, anchor?:Point, scaledSize?:Size)
    return icon;
}
function cria_icone_rota(iconImage, alt, comp, cX, cY)
{
    if ((cX == undefined && cY == undefined) || (cX == 0 && cY == 0)) {
        cX = alt / 2;
        cY = comp / 2;
    }
    var minPixelSize = 25;
    var maxPixelSize = 80; //restricts the maximum size of the icon, otherwise the browser will choke at higher zoom levels trying to scale an image to millions of pixels
    var pixelSizeAtZoomPadrao = 25; //the size of the icon at zoom level 0
    var zoom = config_start_zoom();
    if (map != undefined) {
        zoom = map.getZoom();
    }
    var zoomdif = zoom - config_start_zoom();

    var relativePixelSize = zoomdif * c_scale * pixelSizeAtZoomPadrao;
    if (!isNaN(zoomdif) && zoomdif > 0) {
        if (relativePixelSize > maxPixelSize) //restrict the maximum size of the icon
            relativePixelSize = maxPixelSize;
        if (relativePixelSize < minPixelSize) //restrict the maximum size of the icon
            relativePixelSize = minPixelSize;
    } else {
        relativePixelSize = alt;
    }

    icon = {url: iconImage,
        size: new google.maps.Size(relativePixelSize, relativePixelSize),
        origin: new google.maps.Point(0, 0),
        anchor: new google.maps.Point(cX, cY),
        scaledSize: new google.maps.Size(relativePixelSize, relativePixelSize)
    };
    return icon;
}
function createMarker(latitude, longitude, dados, velocidade, rpm, rpm_cem, iconImage, alt, comp, tipo, ii, cX, cY) {
    var marker;
    var myLatlng = new google.maps.LatLng(latitude, longitude);
    if (iconImage != '')
    {
        var icon = cria_icone(iconImage, alt, comp, cX, cY);
        marker = new google.maps.Marker({
            position: myLatlng,
            visible: true,
            icon: icon
        });
    } else {
        marker = new google.maps.Marker({
            position: myLatlng,
            visible: true
        });
    }

    var infoTabs = abre_informacoes(marker, dados, tipo, velocidade, rpm, rpm_cem, ii);

    var retorno = [];
    retorno[0] = marker;
    retorno[1] = infoTabs;
    return retorno;
}

function createMarkerMovel(latitude, longitude, dados, velocidade, rpm, rpm_cem, iconImage, alt, comp, tipo, ii, cX, cY, azimute) {
    var marker;
    var myLatlng = new google.maps.LatLng(latitude, longitude);
    if (iconImage != '')
    {
        iconImage = iconImage + '_' + calcula_graus(azimute) + '.png';
        var icon = cria_icone(iconImage, alt, comp, cX, cY);

        marker = new google.maps.Marker({
            position: myLatlng,
            visible: true,
            icon: icon,
        });
    } else {
        marker = new google.maps.Marker({
            position: myLatlng,
            visible: true
        });
    }

    var infoTabs = abre_informacoes(marker, dados, tipo, velocidade, rpm, rpm_cem, ii);

    var retorno = [];
    retorno[0] = marker;
    retorno[1] = infoTabs;
    return retorno;
}

function cria_iconeAcelerometro(iconImage, alt, comp, tipoAcelerometro)
{
    var icon;
    var anchor;
    switch (tipoAcelerometro) {
        case 1:
            anchor = new google.maps.Point(alt / 2 + 5, comp / 2);
            break;
        case 2:
            anchor = new google.maps.Point(alt / 2 - 5, comp / 2);
            break;
        case 3:
            anchor = new google.maps.Point(alt / 2, comp / 2 + 5);
            break;
        case 4:
            anchor = new google.maps.Point(alt / 2, comp / 2 - 5);
            break;
    }
    icon = new google.maps.MarkerImage(iconImage, new google.maps.Size(alt, comp), new google.maps.Point(0, 0), anchor);

    return icon;
}

function createMarkerAcelerometro(latitude, longitude, dados, velocidade, iconImage, alt, comp, tipo, tipoAcelerometro) {
    var latlng = google.maps.LatLng(latitude, longitude);
    if (iconImage != '')
    {
        var icon = cria_iconeAcelerometro(iconImage, alt, comp, tipoAcelerometro);
        var marker = new google.maps.Marker({
            position: latlng,
            visible: true,
            icon: icon
        });
    } else {
        var marker = new google.maps.Marker({
            position: latlng,
            visible: true
        });
    }
    GEvent.addListener(marker, 'click', function () {
        abre_informacoes(marker, dados, tipo, velocidade);
    });
    return marker;
}

function create_Polyline(ArrayLatLng)
{
    var polyline = new google.maps.Polyline({
        path: ArrayLatLng,
        strokeColor: c_cor_rota,
        strokeOpacity: c_opacidade,
        strokeWeight: c_exp_rota
    });
    return polyline;
}

function create_PolylineCores(ArrayLatLng) {
    qnt_rotas++;
    if (qnt_rotas == 1) {
        var polyline = new google.maps.Polyline({
            path: ArrayLatLng,
            strokeColor: c_cor_rota,
            strokeOpacity: c_opacidade,
            strokeWeight: c_exp_rota
        });
    } else if (qnt_rotas == 2) {
        var polyline = new google.maps.Polyline({
            path: ArrayLatLng,
            strokeColor: c_cor_rota,
            strokeOpacity: c_opacidade,
            strokeWeight: c_exp_rota
        });
    } else if (qnt_rotas == 3) {
        var polyline = new google.maps.Polyline({
            path: ArrayLatLng,
            strokeColor: c_cor_rota,
            strokeOpacity: c_opacidade,
            strokeWeight: c_exp_rota
        });
    } else if (qnt_rotas == 4) {
        var polyline = new google.maps.Polyline({
            path: ArrayLatLng,
            strokeColor: c_cor_rota,
            strokeOpacity: c_opacidade,
            strokeWeight: c_exp_rota
        });
    } else if (qnt_rotas == 5) {
        var polyline = new google.maps.Polyline({
            path: ArrayLatLng,
            strokeColor: c_cor_rota,
            strokeOpacity: c_opacidade,
            strokeWeight: c_exp_rota
        });
    } else if (qnt_rotas == 6) {
        var polyline = new google.maps.Polyline({
            path: ArrayLatLng,
            strokeColor: c_cor_rota,
            strokeOpacity: c_opacidade,
            strokeWeight: c_exp_rota
        });
    } else if (qnt_rotas == 7) {
        var polyline = new google.maps.Polyline({
            path: ArrayLatLng,
            strokeColor: c_cor_rota,
            strokeOpacity: c_opacidade,
            strokeWeight: c_exp_rota
        });
        qnt_rotas = 0;
    }
    return polyline;
}

function create_Polyline2(ArrayLatLng)
{
    var i = 0;
    var miniArrayLatLng = new Array(0);
    var ultimo;
    while (i < ArrayLatLng.length) {
        miniArrayLatLng.push(ArrayLatLng[i]);
        if (((i + 1) % c_maximo_polyline == 0) || ((i + 1) == ArrayLatLng.length))
        {
            var polyline = new GPolyline(miniArrayLatLng, c_cor_rota, c_exp_rota, c_opacidade);
            map.addOverlay(polyline);
            if (miniArrayLatLng.length != 0)
            {
                ultimo = miniArrayLatLng.pop();
            }
            while (miniArrayLatLng.length > 0)
            {
                miniArrayLatLng.pop();
            }
            miniArrayLatLng.push(ultimo);
        }
        i++;
    }
    if (miniArrayLatLng.length != 0)
    {
        miniArrayLatLng.pop();
    }
}

function apaga_marcador_mult_mapa(idMapa, marcador) {
    if (marcador) {
        if (mgrs != null && mgrs[idMapa] != null) {
            mgrs[idMapa].removeMarker(marcador);
            mgrs[idMapa].refresh();
        }
    }
}

function apaga_marcador(marcador)
{
    if (marcador)
    {
        marcador.setMap(null);
        //mgr.removeMarker(marcador);
        //mgr.refresh();
    }
}

function calcula_distancia()
{
    var d = 0;
    for (var i = 0; i < rotas.length - 1; i++) {
        d += distHaversine(rotas[i], rotas[i + 1])
    }
    return d;
}

function reversep(lat, lng)
{
    var point = new GLatLng(lat, lng);
    rgp.reverseGeocode(point);
}

function handleClicks(marker, point)
{
    lat.value = point.lat();
    lng.value = point.lng();
}
var rev;
var rgp, rgr;
function reverse_load()
{
    rgp = google.maps.Geocoder();
    rgr = google.maps.Geocoder();
}
function getZoomLevel(zoomLevel, i)
{
    var zoomMin = c_zoom_min_info;
    if (i != 0)
    {
        switch (i % 16)
        {
            case 0:
                zoomMin = zoomLevel - 1;
                break;
            case 1:
                zoomMin = zoomLevel - 2;
                break;
            case 2:
                zoomMin = zoomLevel - 1;
                break;
            case 3:
                zoomMin = zoomLevel - 3;
                break;
            case 4:
                zoomMin = zoomLevel - 1;
                break;
            case 5:
                zoomMin = zoomLevel - 2;
                break;
            case 6:
                zoomMin = zoomLevel - 1;
                break;
            case 7:
                zoomMin = zoomLevel - 4;
                break;
            case 8:
                zoomMin = zoomLevel - 1;
                break;
            case 9:
                zoomMin = zoomLevel - 2;
                break;
            case 10:
                zoomMin = zoomLevel - 1;
                break;
            case 11:
                zoomMin = zoomLevel - 3;
                break;
            case 12:
                zoomMin = zoomLevel - 1;
                break;
            case 13:
                zoomMin = zoomLevel - 2;
                break;
            case 14:
                zoomMin = zoomLevel - 1;
                break;
            case 15:
                zoomMin = zoomLevel - 5;
                break;
            default:
                zoomMin = zoomLevel - 5;
        }
    }
    return zoomMin;
}
function gera_direcoes(i, latlng, azimute, data_hora, velocidade, rpm, rpm_cem, km_atual, show_km)
{
    var iconImage;
    var marker;
    var icon;

    if (azimute % c_graus > c_graus_met)
        azimute = azimute - (azimute % c_graus) + c_graus;
    else
        azimute = azimute - (azimute % c_graus);
    iconImage = c_host + c_caminho + 'img/direcao/' + azimute + '.png';
    icon = cria_icone(iconImage, c_alt_dir, c_comp_dir);

    marker = new google.maps.Marker({
        position: latlng,
        icon: icon
    });

    direcoes.push(marker);

    var informacoes_seta;
    if (show_km == '1') {
        informacoes_seta = {
            'data_hora': '' + data_hora + '',
            'velocidade': '' + velocidade + ' Km/h',
            'km_atual': '' + km_atual + '',
            'show_km': '' + show_km + ''
        };
    } else {
        informacoes_seta = {
            'data_hora': '' + data_hora + '',
            'velocidade': '' + velocidade + ' Km/h',
            'show_km': '' + show_km + ''
        };
    }

    var zoomMin = getZoomLevel(17, i);
    mgr.addMarker(marker, zoomMin);

    if (rpm_cem == null) {
        abre_informacoes(marker, informacoes_seta, c_seta, velocidade);
    } else {
        abre_informacoes(marker, informacoes_seta, c_seta, velocidade, rpm, rpm_cem);
    }
    return marker;
}

function calcula_graus(azimute)
{
    if (azimute % c_graus > c_graus_met)
        azimute = azimute - (azimute % c_graus) + c_graus;
    else
        azimute = azimute - (azimute % c_graus);

    return azimute;
}
