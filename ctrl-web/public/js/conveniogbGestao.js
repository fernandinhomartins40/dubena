$(document).ready(function () {
    chartConvenio = undefined;
    tblConv12 = undefined;
    chartConvenioClientes = undefined;
    tblConvCli12 = undefined;
    tblConvMes = undefined;
    chartValegas = undefined;
    tblVale12 = undefined;
    tblValeMes = undefined;

    renderDashboard();
});

$(document).on('change', 'input[type="checkbox"]', function () {
  const tipo = $(this).data('tipo');
  const valor = $(this).val().toLowerCase();
  const checked = $(this).is(':checked');

  // Mapeia os tipos para os gráficos correspondentes
  const graficos = {
    chartConvenio: chartConvenio,
    chartConvenioClientes: chartConvenioClientes,
    chartValegas: chartValegas,
  };

  const chart = graficos[tipo];
  if (!chart) return;

  chart.w.config.series.forEach((serie) => {
    if (serie.name.toLowerCase().includes(valor)) {
      checked ? chart.showSeries(serie.name) : chart.hideSeries(serie.name);
    }
  });
});


$("#btnRefresh").click(function () {
    renderDashboard();
});

function renderDashboard(){
  showLoaderAjax("Aguarde", "Carregando Informações", false);
  setTimeout(()=>{
    carregarDashboard();
  }, 500)
}

function renderTabelaMes(produto, mes, containerId){
  if(produto!='Clientes' && (containerId=='tbl_valegas12meses' || containerId=='tbl_convenioClientes12meses')){
    showLoaderAjax("Aguarde", "Carregando Informações", false);
    setTimeout(()=>{
      carregarTabelaMes(produto, mes, containerId);
    }, 500)
  }
}

function carregarTabelaMes(produto, mes, containerId){
  console.log(produto, mes, containerId);
    if(containerId=='tbl_valegas12meses' || containerId=='tbl_convenioClientes12meses'){
        let url = root + '/conveniogbgestao.getConvenioValegasMes';
        url += '?produto=' + produto + '&mes=' + mes + '&tipo=' + (containerId=='tbl_valegas12meses' ? 'valegas':'convenio'); 
        ajaxGenerator(url, "GET", function (data) {
                hideLoaderAjax();
                if (typeof data === 'object')
                    if(data.status == 'OK'){
                        if(containerId=='tbl_valegas12meses'){
                            renderTblvalegasMes(data.data.dataTabela);
                            $('#tituloValegasMes').html(`Vendas de Vale Gás (${produto}) no Mês ${mes}`);

                        } else {
                            renderTblConvenioMes(data.data.dataTabela);
                            $('#tituloConvenioMes').html(`Quantidade de Conveniados x Vendas (${produto}) no Mês ${mes}`);
                        }
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
    } else {
      hideLoaderAjax();
    }
}


function carregarDashboard() {
    var url = root + '/conveniogbgestao.getDashboard';
    ajaxGenerator(url, "GET", function (data) {
            hideLoaderAjax();
            if (typeof data === 'object')
                if(data.status == 'OK'){
                    renderChart12Meses("#chart_convenio12meses", 'chartConvenio', 'Quantidade e Preço Médio de Venda para Convênio', data.data.dataChartConvenio);
                    renderChart12Meses("#chart_valegas12meses", 'chartValegas', 'Quantidade e Preço Médio de Venda para Vale Gás',   data.data.dataChartValegas);
                    renderTabela12Meses('tbl_convenio12meses', data.data.dataChartConvenio, "soma")
                    renderTabela12Meses('tbl_valegas12meses', data.data.dataChartValegas,  "soma")
                    renderTabela12Meses('tbl_convenioClientes12meses', data.data.dataChartConvenioClientes, "percentualSobreClientes")
                    renderChartConvenioClientes(data.data.dataChartConvenioClientes);
                    renderTblConvenioMes(data.data.dataTabelaConvenio);
                    renderTblvalegasMes(data.data.dataTabelaValegas);
                    renderCheckboxes(data.data.dataChartprodutos);
                    $('#tituloConvenioMes').html('Quantidade de Conveniados x Vendas (GlpP13) no Mês Atual');
                    $('#tituloValegasMes').html('Vendas de Vale Gás (Glp P13) no Mês Atual');
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

function renderChart12Meses(div, chart, titulo, dataChart){
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
          seriequantidade = 'Qtde ' + key;
        }
        const seriecol = {name: 'Qtde ' + key, type: 'column', data: datacol, hidden: !key.toLowerCase().includes('p13')};
        seriescol.push(seriecol);
        const datalin = dataByProd[key].map(obj => parseFloat(obj.precomedio));
        const maxprc = Math.max(...datalin);
        if(maxprc > maxpreco){
          maxpreco = maxprc;
          seriepreco = 'Preço Médio ' + key;
        }
        const serielin = {name: 'Preço Médio ' + key, type: 'line', data: datalin, hidden: !key.toLowerCase().includes('p13')};
        serieslin.push(serielin);
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
                return val.toFixed(0); // Format as integer
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
          height: 350,
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
          width: [5, 5, 5]
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
          shared: false,
          x: {
            show: true
          },
          y: {
            show: true
          }
        },
        legend: {
          horizontalAlign: 'center',
          //offsetX: 40
        }
      };

      if(chart == 'chartConvenio'){
        chartConvenio = new ApexCharts(document.querySelector(div), options);
        chartConvenio.render();
      } else 
      if(chart == 'chartValegas'){
        chartValegas = new ApexCharts(document.querySelector(div), options);
        chartValegas.render();
      }

      
}

function renderTabela12Meses(div, dados, calculoRodape = "soma") {
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
    width: 65,
    hozAlign: "center",
    headerHozAlign: "center",
    formatter: "money",
    formatterParams: {
      decimal: ",",
      thousand: ".",
      negativeSign: true,
      precision: 0,
    },
    cellClick:function(e, cell){
       const mes = cell.getColumn().getDefinition().title; 
       const produto = cell.getRow().getData().descricao;    
       const containerId = cell.getTable().element.id;
       renderTabelaMes(produto, mes, containerId);

    },
    bottomCalc: function (values, data) {
      if (calculoRodape === "percentualSobreClientes") {
        const clientes = data.find(row => row.descricao === "Clientes")?.[mesSeq] || 0;
        const totalGLP = data
          .filter(row => row.descricao !== "Clientes")
          .reduce((soma, row) => soma + (row[mesSeq] || 0), 0);
        return clientes ? (totalGLP / clientes) * 100 : 0;
      } else {
        return values.reduce((soma, val) => soma + (val || 0), 0);
      }
    },
    bottomCalcFormatter: function (cell) {
      const valor = cell.getValue();
      if (calculoRodape === "percentualSobreClientes") {
        return valor.toLocaleString("pt-BR", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }) + " %";
      } else {
        return valor.toLocaleString("pt-BR", {
          minimumFractionDigits: 0,
          maximumFractionDigits: 0,
        });
      }
    }
  }));

  const colunas = [{ title: "Descrição", field: "descricao", frozen: true, width: 120 }].concat(colunasvalores);



  const tabela = new Tabulator("#" + div, {
    locale: "pt-br",
    langs: {
      "pt-br": tabulatorPtBr,
    },
    height: "100%",
    layout: "fitColumns",
    data: transposto,
    columns: colunas,
  });
  /*
  tabela.on("tableBuilt", () => {
    const ultimaColuna = colunas[colunas.length - 1].field;
    tabela.scrollToColumn(ultimaColuna, "right", true);
  });
  */
}

function renderChartConvenioClientes(dataChart){
    const dataByProd = dataChart.reduce((acc, prod) => {
      if (!acc[prod.descricao]) {
        acc[prod.descricao] = [];
      }
      acc[prod.descricao].push(prod);
      return acc;
    }, {});
    let series = [];
    let categories = [];
    let yaxis = [];
    let maxquantidade = 0;
    let seriequantidade = '';
    Object.keys(dataByProd).forEach(function(key, index) {
        const datacol = dataByProd[key].map(obj => parseInt(obj.quantidade));
        const maxqtde = Math.max(...datacol);
        if(maxqtde > maxquantidade){
          maxquantidade = maxqtde;
          seriequantidade = 'Qtde ' + key;
        }
        const seriecol = {name: 'Qtde ' + key, type: 'column', data: datacol, hidden: !key.toLowerCase().includes('p13') && !key.toLowerCase().includes('cliente')};
        series.push(seriecol);
        categories = dataByProd[key].map(obj => (obj.mes_seq.substring(4, 6) + '/' + obj.mes_seq.substring(0, 4)));
    });

    series.map((serie)=>{
      const axis = {
            seriesName: seriequantidade,
            axisTicks:  { show: true },
            axisBorder: { show: true },
            title:      { text: "Quantidade (un)"},
          };
      yaxis.push(axis);
    });

    var options = {
        series: series,
        chart: {
          height: 350,
          type: 'bar',
          stacked: false,
          zoom: {
            enabled: false
          }

        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          width: [1, 1, 1]
        },
        title: {
          text: 'Quantidade de Clientes x Venda para Convênio',
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
          intersect: false,
          x: {
            show: true
          },
          y: {
            show: true
          }
        },
        legend: {
          horizontalAlign: 'center',
          //offsetX: 40
        }
      };

      chartConvenioClientes = new ApexCharts(document.querySelector("#chart_convenioClientes12meses"), options);
      chartConvenioClientes.render();
}

function renderTblConvenioMes(dados) {
    tblConvMes = new Tabulator("#tbl_convenioMes", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: 500,
        layout: "fitData",
        data: dados,
        pagination: true,
        paginationSize: 15,
        paginationSizeSelector: [15, 25, 50, 100],
        dataReceiveParams: {
            last_page: "last_page",
        },
        columns: [
           {
                title: "Id",
                field: "convenio_id",
                hozAlign: "center",
                headerHozAlign: "center",
            },
            {
                title: "Cliente",
                field: "nome",
                hozAlign: "center",
                headerHozAlign: "center",
            },
            { title: "Qtde Clientes", field: "quantclientes", hozAlign: "center",
              headerHozAlign: "center", },
            { title: "Qtde Vendas", field: "quantvendida", hozAlign: "center",
              headerHozAlign: "center", },
        ],
    });
}

function renderTblvalegasMes(dados) {
    tblConvMes = new Tabulator("#tbl_valegasMes", {
        locale: "pt-br",
        langs: {
            "pt-br": tabulatorPtBr,
        },
        height: 500,
        layout: "fitData",
        data: dados,
        pagination: true,
        paginationSize: 15,
        paginationSizeSelector: [15, 25, 50, 100],
        dataReceiveParams: {
            last_page: "last_page",
        },
        columns: [
           {
                title: "Id",
                field: "id",
                hozAlign: "center",
                headerHozAlign: "center",
            },
            {
                title: "Cliente",
                field: "nome",
                hozAlign: "center",
                headerHozAlign: "center",
            },
            { title: "Qtde Vendas", field: "quantidade", hozAlign: "center",
              headerHozAlign: "center", bottomCalc: "sum" },
            { title: "Preço Médio", field: "precomedio", hozAlign: "center",
              headerHozAlign: "center", formatter: "money",
              formatterParams: {
                decimal: ",",
                thousand: ".",
                negativeSign: true,
                precision: 2,
              },
               bottomCalc: function(values, data, calcParams) {
                // Soma total e quantidade
                let total = 0;
                let quantidade = 0;

                data.forEach(row => {
                  total += parseFloat(row.valortotal) || 0;
                  quantidade += parseInt(row.quantidade) || 0;
                });

                return quantidade > 0 ? (total / quantidade) : 0;
              },
              bottomCalcFormatter: function (cell) {
                const valor = cell.getValue();
                  return valor.toLocaleString("pt-BR", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  });
              }
             }, 
            { title: "Valor Total", field: "valortotal", formatter: "money",
              hozAlign: "right",
              headerHozAlign: "right",
              bottomCalc: "sum",
              formatterParams: {
                decimal: ",",
                thousand: ".",
                negativeSign: true,
                precision: 2,
              },
              bottomCalcFormatter: function (cell) {
                const valor = cell.getValue();
                  return valor.toLocaleString("pt-BR", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  });
              }
             },  
        ],
    });
}


function renderCheckboxes(datachk){
    $("#checkboxConvenioContainer").html('');
    $("#checkboxConvenioClientesContainer").html('');
    $("#checkboxValegasContainer").html();
    datachk.map((prod)=>{
        $("#checkboxConvenioContainer").append(
          `<input ${prod.descricao.toLowerCase().includes('p13')?"checked":""} type="checkbox" data-tipo="chartConvenio" id="chkConvenio_${prod.id}" value="${prod.descricao}"><label for="chkConvenio_${prod.id}">${prod.descricao}</label>`
        );
        $("#checkboxConvenioClientesContainer").append(
          `<input ${prod.descricao.toLowerCase().includes('p13')?"checked":""} type="checkbox" data-tipo="chartConvenioClientes" id="chkConvenioClientes_${prod.id}" value="${prod.descricao}"><label for="chkConvenioClientes_${prod.id}">${prod.descricao}</label>`
        );
        $("#checkboxValegasContainer").append(
          `<input ${prod.descricao.toLowerCase().includes('p13')?"checked":""} type="checkbox" data-tipo="chartValegas" id="chkValegas_${prod.id}" value="${prod.descricao}"><label for="chkValegas_${prod.id}">${prod.descricao}</label>`
        );
    })
}
