/**
 * Created by jeff on 01/06/2018.
 */
var iconsLegend = {
    blue: {
        name: "< 15m",
        icon: root + '/img/marker_blue.png'
    },
    green:{
        name: "15-30m",
        icon: root + '/img/marker_green.png'
    },
    yellow: {
        name: "30-45m",
        icon: root + '/img/marker_yellow.png'
    },
    orange: {
        name: "45-60m",
        icon: root + '/img/marker_orange.png'
    },
    red: {
        name: "> 60m",
        icon: root + '/img/marker_red.png'
    }    ,
    black: {
        name: "Vários Registros",
        icon: root + '/img/marker_black.png'
    }
};
var dataChart;
$(document).ready(function () {
    tblDataChart = $("#tblDataChart").DataTable({
        "language": {
            "url": urlDataTable
        },
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": false,
        "bInfo": false,
        "bAutoWidth": false,
        "destroy": true,
        "sScrollY": "400px"
    });
    tblDataChart.showElement = function () {
        $("#divTblDataChart").show();
    };
    tblDataChart.hideElement = function () {
        $("#divTblDataChart").hide();
    };
    tblDataChart.hideElement();
    updateChart([0,0,0,0,0]);
});

$("#btnLimpar").on('click', function() {
    $(".selectChosen").val('').trigger('chosen:updated');
    $("#datainicio, #datafim").val(dataAtual());
    $("#divChartCanvas").html('<canvas id="chart"></canvas>');
    clearAllMarkers();
    tblDataChart.hideElement();
});
$("#btnFiltro").on('click', function () {
    if(isEmpty($("#datainicio").val())){
        bootbox.alert('O campo Data Início é obrigatório.');
        return;
    }
    if(isEmpty($("#datafim").val())){
        bootbox.alert('O campo Data Fim é obrigatório.');
        return;
    }
    var setor_id = $("#setor_id").val();
    setor_id = isEmpty(setor_id) || setor_id == 'null' ? -1 : setor_id;
    searchPedidos($("#datainicio").val(), $("#datafim").val(), setor_id);
});

/**
 * busca os pedidos e chama as funções para inserir no mapa e no gráfico
 * @param datainicio
 * @param datafim
 * @param setor_id
 */
function searchPedidos (datainicio, datafim, setor_id) {
    dataChart = {
        tempo1: [],
        tempo2: [],
        tempo3: [],
        tempo4: [],
        tempo5: []
    };
    var url = root + '/api/searchPedidosMapaEntregas?datainicio=' + datainicio + '&datafim=' + datafim + '&setor_id=' + setor_id;
    ajaxGenerator(url, 'GET', function (data) {
        clearAllMarkers();
        tblDataChart.hideElement();
        $("#divChartCanvas").html('<canvas id="chart"></canvas>');
        if (data.length > 0) {
            insertChartsAndMaps(data, data.unique('latlng'));
        } else {
            bootbox.alert('Nenhum pedido encontrado para estes filtros.');
        }
    });
}

/**
 * valida e insere os pedidos no mapa e no gráfico.
 * @param data
 * @param unique
 */
function insertChartsAndMaps(data, unique) {
    var tempo1 = 0;
    var tempo2 = 0;
    var tempo3 = 0;
    var tempo4 = 0;
    var tempo5 = 0;
    var position;
    var pathImage;
    var contentInfo;
    var entrega;
    var table = "<div style='font-size: 11.5px'>" +
        "<table class='table table-hover table-responsive table-condensed'>" +
        "<thead><tr><th>Pedido</th><th>Cliente</th><th>Endereço Pedido</th><th>Hora Pedido</th>" +
        "<th>Hora Entrega</th><th>Tempo Entrega</th></tr></thead><tbody>";
    var contentTable = '';
    var qdeEquals = 0;

    $.each(unique, function (index, uniqueEl) {
        var typeMarker;
        var equalsElements = data.where("latlng", "===", uniqueEl.latlng);
        position = {
            lat: parseFloat(uniqueEl.entregalatitude),
            lng: parseFloat(uniqueEl.entregalongitude)
        };
        qdeEquals = equalsElements.length;
        $.each(equalsElements, function (i, el) {
            var tempoentrega = parseInt(el.tempoentrega);
            var tempoentregaInt = isNaN(tempoentrega) ? 60 : tempoentrega ;
            if (tempoentregaInt < 15) {
                typeMarker = 'blue';
                tempo1++;
            } else if (tempoentregaInt < 30) {
                typeMarker = 'green';
                tempo2++;
            } else if (tempoentregaInt < 45) {
                typeMarker = 'yellow';
                tempo3++;
            } else if (tempoentregaInt < 60) {
                typeMarker = 'orange';
                tempo4++;
            } else {
                typeMarker = 'red';
                tempo5++;
            }
            pushDataChart(tempoentrega, el);
            entrega = isEmpty(el.entrega) ? 'Sem Registro' : el.entrega;
            contentTable += putContentTable(el, tempoentrega, entrega, typeMarker);
            if (qdeEquals > 1) {
                typeMarker = "black";
            }
            pathImage = '/img/marker_' + typeMarker + '.png';
        });
        contentInfo = table + contentTable + "</tbody></table></div>";
        addMarker(position, pathImage, 40, 'Clique para ver detalhes', contentInfo);
        contentTable = '';
    });
    updateChart([tempo1, tempo2, tempo3, tempo4, tempo5]);
}

/**
 * gera o conteudo da tabela para o pedido
 * @param el
 * @param tempoentrega
 * @param entrega
 * @param typeMarker
 * @returns {string}
 */
function putContentTable(el, tempoentrega, entrega, typeMarker) {
    var endereco = el.endereco.length > 25 ? el.endereco.substr(0, 25) + "..." : el.endereco;
    var nome = el.nome.length > 25 ? el.nome.substr(0, 25) + "..." : el.nome;
    var contentTable = "<tr style='background-color: '><td>" + el.id + "</td>" +
        "<td>" + nome + "</td>" +
        "<td>" + endereco + "</td>" +
        "<td>" + el.previsao + "</td><td>";
    var img = "<img style='max-height: 25px; max-width: 25px;' src='img/marker_" + typeMarker + ".png'></img>";
    tempoentrega = isNaN(tempoentrega) ? "Sem Registro" : tempoentrega + ' min.';
    contentTable += entrega + "</td><td>" + tempoentrega + "</td><td class='marker" + el.latlng + "'>" + img + "</td></tr>";
    return contentTable;
}
/**
 * adiciona os pedidos no array do gráfico dependendo do tempo de entrega
 * @param tempoentrega
 * @param el
 */
function pushDataChart (tempoentrega, el) {
    if(tempoentrega < 15)
        dataChart.tempo1.push(el);
    else if (tempoentrega < 30)
        dataChart.tempo2.push(el);
    else if (tempoentrega < 45)
        dataChart.tempo3.push(el);
    else if (tempoentrega < 60)
        dataChart.tempo4.push(el);
    else
        dataChart.tempo5.push(el);
}

/**
 * atualza o gráfico após adicionar todos os pedidos
 * @param data
 */
function updateChart (data) {
    var divChart = document.getElementById("chart");
    var background = ["#75B0FD", "#B7EF56","#F4FD2B","#FDB72C","#D54A40"];
    var datasets = [{ backgroundColor: background, data: data }];
    var labels = [" < 15m", " 15-30m", " 30-45m", " 45-60m", " > 60m"];
    chart = new Chart(divChart.getContext("2d"), {
        type: 'pie',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            title: {
                text: 'Entrega por tempo.'
            }
        }
    });
    divChart.onclick = function(evt) {
        var activePoints = chart.getElementsAtEvent(evt);
        if (activePoints[0]) {
            var chartData = activePoints[0]['_chart'].config.data;
            var idx = activePoints[0]['_index'];

            var label = chartData.labels[idx];
            var value = chartData.datasets[0].data[idx];

            var data;
            if (label.indexOf('< 15m') !== -1)
                data = dataChart.tempo1;
            else if (label.indexOf('15-30m') !== -1)
                data = dataChart.tempo2;
            else if(label.indexOf('30-45m') !== -1)
                data = dataChart.tempo3;
            else if(label.indexOf('45-60m') !== -1)
                data = dataChart.tempo4;
            else if(label.indexOf('> 60m') !== -1)
                data = dataChart.tempo5;
            tblDataChart.clear();
            $.each(data, function (i, el) {
                var tempoentrega = parseInt(el.tempoentrega);
                tempoentrega = isNaN(tempoentrega) ? "Sem Registro" : tempoentrega + ' min.';
                tblDataChart.row.add([el.id, el.nome, el.endereco, tempoentrega]);
            });
            if (data.length > 0)
                tblDataChart.showElement();
            else
                tblDataChart.hideElement();
            tblDataChart.draw();
        }
    }
}