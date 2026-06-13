    var chartVendaGeral = null;
    var tblVendaGeral = undefined;

    var divsToSend = [];
    var chartsToSend = [];

    $(document).ready(function () {
        //renderDashboard();
        $('#mes').val((moment().month() + 1)).trigger('chosen:updated');
    });

    $('#btnFiltro').on('click', ()=>{
        renderDashboard();
    })

    $('#btnExport').on('click', ()=>{
        renderPowerPoint();
    })

    $('#btnUpdate').on('click', ()=>{
        bootbox.confirm({
            title: "Atenção!",
            className: "dontHideEsc",
            message: 'Esse processo vai enviar todas as informações necessárias para o servidor. Isso pode ser demorado, por isso antes de confirmar, verifique se todas as imagens e mapas na tela estão carregados corretamente. Se você já fez isso, clique em Sim.',
            buttons: {
                confirm: {
                    label: "Sim",
                    className: "btn-nw-registro"
                },
                cancel: {
                    label: "Não",
                    className: "btn-nw-geral"
                }
            },
            backdrop: true,
            closeButton: false,
            callback: function (res) {
                if (res) {
                    showLoaderAjax('Aguarde', 'Verificando mapas', false);
                    const targetElement = document.getElementById('myTargetElement');
                    divsToSend.filter((div)=>div.id.startsWith('divMapa'))
                              .map((div)=>{
                                const targetElement = document.getElementById(div.id);
                                targetElement.scrollIntoView({
                                    behavior: 'smooth', // For a smooth scrolling animation
                                    block: 'center',    // Aligns the element to the center of the viewport vertically
                                    inline: 'nearest'   // Aligns the element to the nearest edge horizontally
                                });
                              });
                    $('#content-loader-ajax').html(`Gerando imagens e enviando para o servidor`);
                    console.log('fim scroll')
                    setTimeout(()=>{
                        console.log('inicioproc')
                        processarImagensBackground();
                    }, 500)
                }
            }
        });
    })

    function renderDashboard(){
        showLoaderAjax("Aguarde", "Carregando Informações", false);
        setTimeout(()=>{
            carregarDashboard();
        }, 500)
    }

    function carregarDashboard() {
        var url = root + '/vendasmensaisgestao.getDashboard?ano=' + $('#ano').val() + '&mes=' + $('#mes').val();
        ajaxGenerator(url, "GET", function (data) {
                hideLoaderAjax();
                if (typeof data === 'object')
                    if(data.status == 'OK'){
                        $('#divMainContainer').show();
                        divsToSend = [];
                        chartsToSend = [];
                        renderChart12Meses("#chart_vendageral", 'chartVendaGeral', 'Quantidade e Preço Médio de Venda em ' + $('#ano').val(), data.data.dataChartVendaGeral);
                        renderTabela12Meses('tbl_vendageral', data.data.dataChartVendaGeral);
                        renderSetores(data.data.dataChartVendaSetores);
                        $('#btnUpdate').show();
                        if(data.data.gerado){
                            $('#btnExport').show();
                        } else {
                            $('#btnExport').hide();
                        }
                        console.log('fim render')
                        
                    } else {
                        bootbox.alert(data.msg);
                    }
                else if (typeof data === 'string')
                    bootbox.alert("Erro: " + data);
                else
                    bootbox.alert("Erro desconhecido");
            }, function () {
                hideLoaderAjax();
                bootbox.alert('Erro ao carregar dados');
        });
    }

function renderChart12Meses(div, chart, titulo, dataChart, setor = null){
    const dataByProd = dataChart.reduce((acc, prod) => {
      if (!acc[prod.descricao]) {
        acc[prod.descricao] = [];
      }
      acc[prod.descricao].push(prod);
      return acc;
    }, {});
    let seriescol = [];
    let serieslin = [];
    let categories = [];
    let yaxis = [];
    let maxpreco = 0;
    let seriepreco = '';
    let maxquantidade = 0;
    let seriequantidade = '';
    Object.keys(dataByProd).forEach(function(key, index) {
        const datacol = dataByProd[key].map(obj => parseInt(obj.quantidade));
        const maxqtde = Math.max(...datacol);
        if(maxqtde > maxquantidade){
          maxquantidade = maxqtde;
          seriequantidade = key;
        }
        const seriecol = {name: key, type: 'column', data: datacol, hidden: false};
        seriescol.push(seriecol);
        if(key == 'Realizado'){
            const datalin = dataByProd[key].map(obj => parseFloat(obj.precomedio));
            const maxprc = Math.max(...datalin);
            if(maxprc > maxpreco){
            maxpreco = maxprc;
            seriepreco = 'Preço Médio';
            }
            const serielin = {name: 'Preço Médio ' + key, type: 'line', data: datalin, hidden: false};
            serieslin.push(serielin);
        }
        categories = dataByProd[key].map(obj => (obj.mes_seq.substring(4, 6) + '/' + obj.mes_seq.substring(0, 4)));
    });

    seriescol.map((serie)=>{
      const axis = {
            seriesName: seriequantidade,
            axisTicks:  { show: true },
            axisBorder: { show: true },
            title:      { text: "Quantidade (un)"},
            labels: {
              formatter: function (val) {
                try {
                    const newval = formataDecimal(val, 0);
                    return newval; // Format as integer
                } catch(e) {
                    console.log('erro', val)
                    return val;
                }
              }
            }
          };
      yaxis.push(axis);
    });
    serieslin.map((serie)=>{
      const axis = {
            min: 0,
            seriesName: seriepreco,
            opposite: true,
            axisTicks:  { show: true },
            axisBorder: { show: true },
            title:      { text: "Preço Médio (R$)"},
            labels: {
              formatter: function (val) {
                return 'R$ ' + formataDecimal(val, 2); // Format with one decimal place
              }
            },
            decimalsInFloat: 2 // Ensure one decimal place for rendering
          };
      yaxis.push(axis);
    });
    const series = seriescol.concat(serieslin);

    var options = {
        series: series,
        chart: {
          height: 320,
          width: 900,
          type: 'line',
          stacked: false,
          zoom: {
            enabled: false
          }
        },
        markers: {
          shape: "circle", 
          size: 5, 
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          width: [1, 1, 1]
        },
        title: {
          text: titulo,
          align: 'center',
          //offsetX: 110
        },
        xaxis: {
          categories: categories,
          labels: {
              rotate: -45, // Rotate labels at a -45 degree angle
              rotateAlways: true // Ensure labels are always rotated
          }
        },
        yaxis: yaxis,
        tooltip: {
          shared: true,
          x: {
            show: true
          },
          y: {
            show: false
          }
        },
        legend: {
          horizontalAlign: 'center',
          //offsetX: 40
        }
      };

      if(chart == 'chartVendaGeral'){
        if (chartVendaGeral) {
            chartVendaGeral.destroy();
        }
        chartVendaGeral = new ApexCharts(document.querySelector(div), options);
        chartVendaGeral.render();
        chartsToSend.push({chart: chartVendaGeral, input: 'chartVendaGeral', setor_id: 999999, descricao: 'Geral'});
      } else 
      if(chart == 'chartValegas'){
        chartValegas = new ApexCharts(document.querySelector(div), options);
        chartValegas.render();
      } else {
        chartSetor = new ApexCharts(document.querySelector(div), options);
        chartSetor.render();
        chartsToSend.push({chart: chartSetor, input: 'chartSetor', setor_id: setor.id, descricao: setor.descricao});
      }

      
}

function renderTabela12Meses(div, dados, setor = null) {
    const transposto = [];
    const mapa = {};

    dados.forEach(item => {
        const desc = item.descricao;
        const mes = item.mes_seq;
        const qtde = parseFloat(item.quantidade);

        if (!mapa[desc]) {
        mapa[desc] = { descricao: desc };
        transposto.push(mapa[desc]);
        }
        mapa[desc][mes] = qtde;
    });
    const novaLinhaPercentual = {
        descricao: "DIF (%)"
    };
    const metaData = transposto.find(item => item.descricao === 'Meta');
    const realizadoData = transposto.find(item => item.descricao === 'Realizado');
    for (const key in metaData) {
        if (key !== "descricao") {
            const meta = metaData[key];
            const realizado = realizadoData[key];

            // Se a meta for 0, o percentual é nulo para evitar erro de divisão por zero
            if (meta === 0) {
                novaLinhaPercentual[key] = null;
            } else {
                const percentual = ((realizado - meta) / meta) * 100;
                // Arredonde o valor para duas casas decimais
                novaLinhaPercentual[key] = parseFloat(percentual.toFixed(2));
            }
        }
    }

    transposto.push(novaLinhaPercentual);

    const mesesUnicos = [...new Set(dados.map(item => item.mes_seq))].sort();

    const formatarMesSeq = (mesSeq) => {
        const ano = mesSeq.slice(2, 4);
        const mes = mesSeq.slice(4, 6);
        return `${mes}/${ano}`;
    };

    const colunasvalores = mesesUnicos.map(mesSeq => ({
        title: formatarMesSeq(mesSeq),
        field: mesSeq,
        headerSort: false,
        //width: 100,
        formatter: function(cell, formatterParams, onRendered) {
            const rowData = cell.getData();
            const value = cell.getValue();

            if (rowData.descricao === "DIF (%)") {
                // Linha de Diferença Percentual
                if (value === null || isNaN(value)) return "0,00 %";

                const formattedValue = value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const classColor = value < 0 ? 'negativo' : 'positivo';
                const sign = value > 0 ? '+' : '';

                // Adiciona a classe de cor condicional à célula
                cell.getElement().classList.add(classColor);
                return `<span class="${classColor}">${sign}${formattedValue} %</span>`;
            } else {
                // Linhas META e VENDA
                if (value === null || isNaN(value)) return "0";
                return value.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            }
        },
    }));

    const colunas = [{ title: "", field: "descricao", frozen: false, width: 120, headerSort: false }].concat(colunasvalores);
    const tabela = new Tabulator("#" + div, {
        locale: "pt-br",
        langs: {
        "pt-br": tabulatorPtBr,
        },
        height: "100%",
        layout: "fitData",
        data: transposto,
        columns: colunas,
        rowFormatter: function(row){
            if(row.getData().descricao === "DIF (%)"){
                row.getElement().classList.add("diff-row");
            }
        },
    });
    divsToSend.push({id: div, setor_id: setor?setor.id:999999, descricao:setor?setor.descricao:'Geral'});
}

function renderSetores(data){
    //primeiro renderiza as div
    let html = '';
    let totalvenda = 0;
    let totalpotencial = 0;
    data.map((setor)=>{
        const rowvendasmes = setor.dados.filter((item)=>{
            return item.mes_seq == $('#ano').val() + $('#mes').val().padStart(2, '0') && item.descricao == 'Realizado'; 
        })[0];
        let vendasmes = rowvendasmes?parseInt(rowvendasmes.quantidade):0;
        totalvenda += vendasmes;
        totalpotencial += setor.potencialvendas;
        let participacao = setor.potencialvendas && setor.potencialvendas > 0 ? vendasmes/setor.potencialvendas * 100 : 0;
        html += 
        `<div class="row">
            <div class="col-md-12">
                <div class="box-container">
                    <div class="row" style="display: flex; justify-content: center; margin-bottom:15px;">
                        <div class="box-title">
                                <h4>Venda - ${setor.descricao}</h4>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-8 col-sm-offset-2"  style="display: flex; justify-content: center;">
                            <div id="chart_vendasetor_${setor.id}"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12" style="display:flex; justify-content:center;">
                            <div style="width:fit-content;">
                            <div class="tbl_vendasetor" id="tbl_vendasetor_${setor.id}"></div>
                            </div>
                        </div>
                    </div>
                    <div class="row" style="margin-top: 30px;">
                        <div id="mapa-container_${setor.id}" class="col-sm-8 col-sm-offset-2" style="display:flex;justify-content: center;"> 
                            <div id="divMapa_${setor.id}" style="height: 700px; max-height: 650px; width: 750px;"></div>
                        </div>
                        <div class='hidden'>
                            <div class="legendMaps" id=legendMaps_${setor.id}><span>Legenda</span></div>
                        </div>
                    </div>
                    <div class="row" style="margin-top: 30px;">
                        <div class="col-sm-10 col-sm-offset-1"> 
                            <div id="divcharttempo_${setor.id}" class="col-sm-5" style="display:flex; justify-content: end;"></div>
                            <div class="col-sm-6" style="display:flex; justify-content: center; margin-top: 30px;">
                                <div id="divtblparticip_${setor.id}" class="sales-info-card">
                                    <div class="sales-info-title bg-black">
                                        POTENCIAL DE VENDA
                                    </div>
                                    <div class="sales-info-value bg-white">
                                        ${formataDecimal(setor.potencialvendas, 0)}
                                    </div>
                                    <div class="sales-info-title bg-black">
                                        VENDA NO MÊS
                                    </div>
                                    <div id="divVendasMes_${setor.id}" class="sales-info-value bg-white">
                                        ${formataDecimal(vendasmes, 0)}
                                    </div>
                                    <div class="sales-info-title bg-lime-500">
                                        PARTICIPAÇÃO NO SETOR
                                    </div>
                                    <div id="divPartcipacaoMes_${setor.id}" class="sales-info-value-perc bg-lime-500">
                                        ${formataDecimal(participacao, 1)}%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        `;
    });
    //TOTAL PARTICIPACAO
     html += 
        `<div class="row">
            <div class="col-md-12">
                <div class="box-container">
                    <div class="row" style="display: flex; justify-content: center; margin-bottom:15px;">
                        <div class="box-title">
                                <h4>PARTICIPAÇÃO GERAL</h4>
                        </div>
                    </div>
                    <div class="row" style="margin-top: 30px;">
                        <div class="col-sm-10 col-sm-offset-1"> 
                            <div class="col-sm-6 col-sm-offset-3" style="display:flex; justify-content: center;">
                                <div id="divtblparticip_999999" class="sales-info-card">
                                    <div class="sales-info-title bg-black">
                                        POTENCIAL DE VENDA
                                    </div>
                                    <div class="sales-info-value bg-white">
                                        ${formataDecimal(totalpotencial, 0)}
                                    </div>
                                    <div class="sales-info-title bg-black">
                                        VENDA NO MÊS
                                    </div>
                                    <div  class="sales-info-value bg-white">
                                        ${formataDecimal(totalvenda, 0)}
                                    </div>
                                    <div class="sales-info-title bg-lime-500">
                                        PARTICIPAÇÃO
                                    </div>
                                    <div  class="sales-info-value-perc bg-lime-500">
                                        ${formataDecimal(totalpotencial>0?totalvenda/totalpotencial*100:0, 1)}%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;



    $('#div_setores').html(html);
    //depois renderiza tabelas e gráficos
    data.map((setor)=>{
        renderChart12Meses(`#chart_vendasetor_${setor.id}`, `chartVendaSetor_${setor.id}`, 'Quantidade e Preço Médio de Venda em ' + $('#ano').val() + ' - ' + setor.descricao, setor.dados, setor);
        renderTabela12Meses(`tbl_vendasetor_${setor.id}`, setor.dados, setor);
        renderMap(16, setor);
        renderChartTempo(setor);
        divsToSend.push({id: `divtblparticip_${setor.id}`, setor_id: setor.id, descricao:setor.descricao});
    });
    divsToSend.push({id: `divtblparticip_999999`, setor_id: 999999, descricao:'Geral'});
}

async function renderMap(zoom = 13, setor) {
    const {id, markers, cerca, descricao } = setor;
    const idMap = `divMapa_${id}`;
    const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");
    setLatLgtEmpresa();
    var position = {lat: latitude, lng: longitude};
    map = new google.maps.Map(document.getElementById(idMap), {
        center: position,
        zoom: zoom,
        mapId: "DEMO_MAP_ID" 
    });
    //Markers dos pedidos
    const unique = markers.unique('latlng');

    $.each(unique, function (index, uniqueEl) {
        var typeMarker;
        position = {
            lat: parseFloat(uniqueEl.entregalatitude),
            lng: parseFloat(uniqueEl.entregalongitude)
        };
        var tempoentrega = parseInt(uniqueEl.tempoentrega);
        var tempoentregaInt = isNaN(tempoentrega) ? 60 : tempoentrega ;
        if (tempoentregaInt < 15) {
            typeMarker = 'blue';
        } else if (tempoentregaInt < 30) {
            typeMarker = 'green';
        } else if (tempoentregaInt < 45) {
            typeMarker = 'yellow';
        } else if (tempoentregaInt < 60) {
            typeMarker = 'orange';
        } else {
            typeMarker = 'red';
        }
        pathImage = root + '/img/marker_' + typeMarker + '.png';
        const markerImage = document.createElement('img');
        markerImage.src = pathImage;

        new AdvancedMarkerElement({
            position: position,
            map: map,
            content: markerImage,
        });
    });
    //Cerca do setor
    if(cerca && cerca.length > 0){
        var polygon = new google.maps.Polygon({
            paths: cerca,
            strokeColor: '#FF0000',
            strokeOpacity: 0.8,
            strokeWeight: 2,
            fillColor: '#FF0000',
            fillOpacity: 0.35,
            editable: false, 
            draggable: false 
        });
         polygon.setMap(map);
         //centraliza
         const bounds = new google.maps.LatLngBounds();
        polygon.getPath().forEach(function(latLng) {
            bounds.extend(latLng);
        });
        map.fitBounds(bounds);
    }
    //Legenda
    map.controls[google.maps.ControlPosition.LEFT_BOTTOM].push(createLegends(id));
    divsToSend.push({id: idMap, setor_id: id, descricao: descricao});
}

function addMarker(position, pathImage, size = 40, title = ' ', contentInfo = false, callback = null, callmaps) {
    var icon = {
        url: root + pathImage, // url
        scaledSize: new google.maps.Size(size, size), // scaled size
    };
    var marker = new google.maps.Marker({
      position: position,
      icon: icon,
      map: map,
      title: title
    });
    if(contentInfo != false){
        marker.addListener('click', function() {
            if (infowindow) 
                infowindow.close();
            infowindow = new google.maps.InfoWindow({
                content: contentInfo    
            });
            if(typeof callback == 'function')
                callback();

            infowindow.open(map, marker);
        });
        google.maps.event.addListener(map, 'click', function(){
            if(map !== null && typeof map !== "undefined"){
                if(typeof $("#info-window").attr('opened') != 'undefined'){
                    if(typeof callmaps == 'function')
                        callmaps();
                    infowindow.close(map, marker);
                }
            }
        });
    }
    markersArray.push(marker);
}

function createLegends(setor_id) {
    var icons = {
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
    var legend = document.getElementById(`legendMaps_${setor_id}`);
    for (var key in icons) {
        var type = icons[key];
        var name = type.name;
        var icon = type.icon;
        var div = document.createElement('div');
        div.innerHTML = '<img src="' + icon + '"> ' + name;
        legend.appendChild(div);
    }
    return legend;
}

function renderChartTempo(setor){
    const markers = setor.markers;
    const div = `#divcharttempo_${setor.id}`;
            // Dados do gráfico baseados na imagem
        const data = {
            series: [32, 6, 1, 60, 2],
            labels: ['15-30m', '30-45m', '45-60m', '<15m', '>60m'],
            colors: ['#EF9048', '#86B564', '#C9392A', '#F9CA4C', '#6495E4']
        };

        const options = {
            chart: {
                type: 'pie',
                height: 400,
                width: 400
            },
            series: data.series,
            labels: data.labels,
            colors: data.colors,
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 250
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }],
            title: {
                text: 'Entrega por Tempo',
                align: 'center',
                style: {
                    fontSize: '20px',
                    fontWeight: 'bold',
                    color: '#4A5568'
                }
            },
            dataLabels: {
                enabled: true,
                style: {
                    fontSize: '14px',
                    fontWeight: 'bold',
                    fontFamily: 'Helvetica, Arial, sans-serif'
                },
                dropShadow: {
                    enabled: false
                },
                formatter: function (val, opts) {
                    const seriesIndex = opts.seriesIndex;
                    const value = opts.w.globals.series[seriesIndex];
                    const percentage = Math.round(val);
                    return [value + ' (' + percentage + '%)'];
                },
                background: {
                    enabled: true,
                    foreColor: '#000',
                    padding: 8,
                    borderRadius: 4,
                    borderWidth: 1,
                    borderColor: '#ccc',
                    opacity: 0.9,
                    dropShadow: {
                        enabled: true,
                        top: 1,
                        left: 1,
                        blur: 1,
                        color: '#000',
                        opacity: 0.45
                    }
                },
                pie: {
                    offsetY: 0,
                    customScale: 1
                }
            },
            legend: {
                position: 'bottom',
                horizontalAlign: 'center',
                fontSize: '14px',
                markers: {
                    radius: 12,
                }
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return val + " entregas";
                    }
                }
            },
            plotOptions:{
                pie:{
                dataLabels:{
                    minAngleToShowLabel:1
                }
                }
            },
        };
        const chart = new ApexCharts(document.querySelector(div), options);
        chart.render();
        chartsToSend.push({chart: chart, input: 'chartTempo', setor_id: setor.id, descricao: setor.descricao});
}

async function tableToImage(div, input) {
    const elementoTabela = document.querySelector("#" + div);
    canvas = await html2canvas(elementoTabela, {
        backgroundColor: null,
        useCORS: true,
        willReadFrequently: true,
    });
    canvas.getContext('2d', { willReadFrequently: true });
    return canvas.toDataURL('image/png');
}

async function chartToImage(chart) {
    const { imgURI } = await chart.dataURI();
    return imgURI;
}

function renderPowerPoint(){
    showLoaderAjax("Aguarde", "Gerando apresentação", false);
    setTimeout(()=>{
        generatePowerPoint();
    }, 500)
}


async function generatePowerPoint() {
    var formData = new FormData();
    formData.append('mes', $('#mes').val());
    formData.append('ano', $('#ano').val());

    const url = root + '/vendasmensaisgestao.generatePowerPoint';
    $.ajax({
        type: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        url: url,
        data: formData,
		async: false,        
        success: function (data) {
            console.log('finalfinal')
           hideLoaderAjax();
            if (typeof data === 'object')
                if(data.status == 'OK'){
                    const fileUrl = data.data.fileUrl;
                    console.log(fileUrl, data.data.fileUrl)
                    bootbox.alert('Power Point gerado com sucesso. Clique em OK para fazer o download', function (){ 
                        const link = document.createElement('a');
                        link.href = fileUrl;
                        link.download = fileUrl.substring(fileUrl.lastIndexOf('/') + 1);
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    });
                } else {
                    bootbox.alert(data.msg);
                }
            else if (typeof data === 'string')
                bootbox.alert("Erro: " + data);
            else
                bootbox.alert("Erro desconhecido");
        },
        error: function (data) {
            hideLoaderAjax();
            bootbox.alert('Erro ao fazer o download');
        },
        cache: false,
        contentType: false,
        processData: false
    });
    return false;
}

async function processarImagensBackground() {
    console.log('Iniciando o processamento de ' + divsToSend.length + ' divs.');
    // Usamos um loop 'for...of' para garantir a execução sequencial
    for (const row of divsToSend) {
        const {id, setor_id, descricao} = row;

        const div = document.getElementById(id);
        if (!div) {
            console.error('Div com ID #' + id + ' não encontrada. Pulando para a próxima.');
            continue; // Pula para a próxima iteração do loop
        }
        const content = (id.startsWith('tbl_venda')?'tabela de venda':(id.startsWith('divtblparticip')?'tabela de participação':'mapa')) + ': ' + descricao;
        $('#content-loader-ajax').html(`Gerando ${content}`);
        // 1. Executa o html2canvas e espera a conclusão
        const canvas = await html2canvas(div, {
                    backgroundColor: null,
                    useCORS: true,
                    willReadFrequently: true,
                });

        // 2. Converte o canvas para um Blob
        await new Promise(resolve => {
            canvas.toBlob(blob => {
                // 3. Processa o Blob (por exemplo, envia para o servidor)
                // Esta função não precisa de 'await' se a requisição for assíncrona
                enviarImagemParaServidor(blob, id, setor_id);
                
                console.log('Captura e processamento da div #' + id + ' concluídos.');
                resolve(); // Permite que o loop continue
            });
        });
    }
    console.log('Todas as divs foram processadas!');
    console.log('Iniciando o processamento de ' + chartsToSend.length + ' charts.');

    for (const row of chartsToSend) {
        const {chart, input, setor_id, descricao} = row;
        const { imgURI } = await chart.dataURI();
        const response = await fetch(imgURI);
        const blob = await response.blob();
        const contentchart = (input.startsWith('chartVenda')||input.startsWith('chartSetor')?'gráfico de venda':'gráfico de tempo') + ': ' + descricao;
        $('#content-loader-ajax').html(`Gerando ${contentchart}`);
        enviarImagemParaServidor(blob, input, setor_id);
        console.log('Captura e processamento do chart #' + input + ' concluídos.');
    }
    hideLoaderAjax();
    $('#btnExport').show();
    console.log('Todos os charts foram processados!');

}

function enviarImagemParaServidor(blob, id, setor_id) {
    const formData = new FormData();
    formData.append('imagem', blob, `div-${id}.png`);
    formData.append('mes', $('#mes').val());
    formData.append('ano', $('#ano').val());
    formData.append('setor_id', setor_id);
    formData.append('input_id', id);

    const url = root + '/vendasmensaisgestao.uploadImagem';
    $.ajax({
        type: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        url: url,
        data: formData,
		async: false,        
        success: function (data) {
            if (typeof data === 'object')
                if(data.status == 'OK'){
                    //bootbox.alert('Power Point gerado com sucesso. Clique em OK para fazer o download'); 
                } else {
                    bootbox.alert(data.msg);
                }
            else if (typeof data === 'string')
                bootbox.alert("Erro: " + data);
            else
                bootbox.alert("Erro desconhecido");
        },
        error: function (data) {
            hideLoaderAjax();
            bootbox.alert('Erro ao fazer o download');
        },
        cache: false,
        contentType: false,
        processData: false
    });
}
