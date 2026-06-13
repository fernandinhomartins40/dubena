    var chartVendaDiaria = null;
    var chartMarketShare = null;
    var tblDetalhes = undefined;
    var tblCentroCustos = undefined;
    var tblDre = undefined;
    var tblBalanco = undefined;
    var renderedDre = false;
    var renderedBalanco = false;
    var renderedEstoque = false;
    var renderedMarketShare = false;

    $(document).ready(function () {
        google.charts.load('current', {'packages':['gauge']});
        $('div.wrapper').removeClass('wrapper');
        $('#ano').val(ano).trigger('chosen:updated');
        $('#mes').val(mes).trigger('chosen:updated');
        renderDashboard();

    });

    $('#btnRefresh').on('click', ()=>{
        renderDashboard();
    })

    function renderDashboard(){
        openModalLoader("Aguarde", "Carregando Informações");
        setTimeout(()=>{
            carregarDashboard();
            renderSaldos();
        }, 500)
    }

    function carregarDashboard() {
        var url = root + '/dashboardgerencial.getDashboard';
        ajaxGenerator(url, "GET", function (data) {
                closeModalLoader();
                if (typeof data === 'object')
                    if(data.status == 'OK'){
                        renderChartVendaDiaria("#chart_vendadiaria", 'Quantidade de Vendas dos Setores (P13)', data.data.dataChartVendaDiaria);
                        renderChartMarketShare("#chart_marketshare", 'Venda dos Setores (P13) - Últ. 12 Meses', data.data.dataMarketShare);
                        renderChartVendaGauge('chart_resumovenda', data.data.periodo, data.data.dataChartVendaDiaria);
                        renderedDre = false;
                        renderedBalanco = false;
                        renderedEstoque = false;
                        renderedMarketShare = false;
                        renderTabelaDre('tbl_dre', data.data.dataDreMensal);
                        renderTabelaBalanco('tbl_balanco', data.data.dataBalanco);
                        ano = data.data.ano;
                        mes = data.data.mes;
                        $('.dashboard').show();
                        $('#ano').val(ano).trigger('chosen:updated');
                        $('#mes').val(mes).trigger('chosen:updated');



                    } else {
                        bootbox.alert(data.msg);
                    }
                else if (typeof data === 'string')
                    bootbox.alert("Erro: " + data);
                else
                    bootbox.alert("Erro desconhecido");
            }, function () {
                closeModalLoader();
                bootbox.alert('Erro ao carregar dados');
        });
    }

    function renderChartVendaDiaria(div, titulo, dataChart){
        const dataByProd = dataChart.reduce((acc, prod) => {
        if (!acc[prod.descricao]) {
            acc[prod.descricao] = [];
        }
        acc[prod.descricao].push(prod);
        return acc;
        }, {});
        let seriescol = [];
        let categories = [];
        let yaxis = [];
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
            categories = dataByProd[key].map(obj => (obj.dia_seq.substring(6, 8) + '/' + obj.dia_seq.substring(4, 6) + '/' + obj.dia_seq.substring(0, 4)));
        });

        seriescol.map((serie)=>{
            const axis = {
                    seriesName: seriequantidade,
                    max:  Math.ceil(maxquantidade*1.1 / 10) * 10,
                    min:0,
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

        const series = seriescol;

        var options = {
            series: series,
            chart: {
            height: 357,
            width: '100%',
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

        if (chartVendaDiaria) {
            chartVendaDiaria.destroy();
        }
        chartVendaDiaria = new ApexCharts(document.querySelector(div), options);
        chartVendaDiaria.render();
    }

        
    function renderChartMarketShare(div, titulo, dataChart, setor = null){
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
            if(key == 'Realizado'){
                const seriecol = {name: key, type: 'column', data: datacol, hidden: false};
                seriescol.push(seriecol);
            } else {
                const serielin = {name: 'Potencial', type: 'line', data: datacol, hidden: false};
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

        const series = seriescol.concat(serieslin);

        var options = {
            series: series,
            chart: {
                height: 320,
                width: '100%',
                type: 'line',
                stacked: false,
                zoom: {
                    enabled: false
                },
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

        if (chartMarketShare) {
            chartMarketShare.destroy();
        }
        chartMarketShare = new ApexCharts(document.querySelector(div), options);
        chartMarketShare.render().then(() => {
            renderedMarketShare = true;
            resizeEstoqueMarketShare();
        });
      
    }



    function renderChartVendaGauge(div, tituloperiodo, dataChart) {
        const totais = dataChart.reduce((acc, prod) => {
            if(prod.descricao == 'Realizado') {
                acc.realizado += parseInt(prod.quantidade);
                acc.valorrealizado += parseFloat(prod.valor);
            } else
            if(prod.descricao == 'Meta') {
                acc.meta += parseInt(prod.quantidade);
            }
            return acc;
        }, {meta: 0, realizado: 0, valorrealizado: 0});
        // Valores de Exemplo baseados na imagem:
        const VALOR_ATUAL_VENDAS = totais.meta == 0?100:totais.realizado/totais.meta*100; 
        const META_MAXIMA = VALOR_ATUAL_VENDAS >= 115?VALOR_ATUAL_VENDAS:115; 

        // Definição das zonas de cor (Semelhante ao Vermelho-Amarelo-Verde da imagem)
        const ZONA_VERMELHA_TO = 80; 
        const ZONA_AMARELA_TO = 99; 

        var data = google.visualization.arrayToDataTable([
            ['Label', 'Valor'],
            ['', {v: VALOR_ATUAL_VENDAS, f:''}]
        ]);

        var options = {
            width: 250, height: 250, 
            redFrom: 0, redTo: ZONA_VERMELHA_TO,
            yellowFrom: ZONA_VERMELHA_TO, yellowTo: ZONA_AMARELA_TO,
            greenFrom: ZONA_AMARELA_TO, greenTo: META_MAXIMA,
            minorTicks: 5, 
            min: 0, 
            max: META_MAXIMA,
            hideMetricValue: true,
            majorTicks: [],
        };

        var chart = new google.visualization.Gauge(document.getElementById(div));

        // O Google Chart por padrão exibe o valor no centro, que pode ser ajustado
        // com CSS e um div separado para imitar a estrutura exata da imagem.
        chart.draw(data, options);

        $('#chart_resumovenda_title').html('Quantidade de Venda (P13) no Mês');
        $('#tituloQuantidadeResumo').html(formataDecimal(totais.realizado, 0) + " Unidades");
        $('#tituloValorResumo').html('R$ ' + formataDecimal(totais.valorrealizado, 2));
        $('#tituloPeriodoResumo').html(tituloperiodo);
        // 3. Atualizar o texto da Div para mostrar o valor formatado
        //document.getElementById('display_value').innerText = VALOR_ATUAL_VENDAS.toLocaleString('pt-BR', { minimumFractionDigits: 0 });
    }

    function renderTabelaBalanco(div, dados) {
        const colunas = [   
                            { title: "Descrição", field: "descricao", headerHozAlign:"left", vertAlign: "middle", frozen: false, width: 180, headerSort: true, formatter: function(cell, formatterParams, onRendered) {
                                const rowData = cell.getData();
                                const value = cell.getValue();
                                if(value == null || isNaN(value)){
                                    if(value=='Quantidade'){
                                        cell.getElement().classList.add("align-right");
                                    }                                    
                                    return value;
                                } else {
                                    cell.getElement().classList.add("align-right");
                                    return value.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                                }
                            }},
                            { title: "", field: "custo", vertAlign: "middle", hozAlign: "right", frozen: false, width: 150, headerSort: false, formatter: function(cell, formatterParams, onRendered) {
                                const rowData = cell.getData();
                                const value = cell.getValue();
                                if(value == null || isNaN(value)){
                                    return value;
                                } else {
                                    return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                            }},
                            { title: "Saldo", field: "valor", width: 170, headerHozAlign:"right", vertAlign: "middle", hozAlign: "right", bottomCalc: "sum", formatter: function(cell, formatterParams, onRendered) {
                                const rowData = cell.getData();
                                const value = cell.getValue();
                                let $el = cell.getElement();
                                if (value < 0) {
                                    $el.style.color = "red";
                                } else {
                                    $el.style.color = "green";
                                }


                                if(value == null || isNaN(value)){
                                    return value;
                                } else {
                                    return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                            }, bottomCalcFormatter: function (cell) {
                                const valor = cell.getValue();
                                let $el = cell.getElement();
                                if (valor < 0) {
                                    $el.style.color = "red";
                                } else {
                                    $el.style.color = "green";
                                }
                                return valor.toLocaleString("pt-BR", {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                                })
                            }},
                        ];
        tblBalanco = new Tabulator("#" + div, {
            locale: "pt-br",
            langs: {
            "pt-br": tabulatorPtBr,
            },
            height: "100%",
            layout: "fitData",
            data: dados,
            columns: colunas,
            rowHeight: 30,
            headerVisible: true,
            rowFormatter: function(row){
                if(row.getData().cabecalho == 2){
                    row.getElement().classList.add("header-table-row");
                } else
                if(row.getData().cabecalho == 1){
                    row.getElement().classList.add("header-row");
                }
            },
        });
        tblBalanco.on("tableBuilt", function(){
            renderedBalanco = true;
            resizeDreBalanco();
        });
    }

    function renderTabelaDre(div, dados) {
        const colunas = [{ title: "Descrição", field: "plano", headerHozAlign:"left", vertAlign: "middle", frozen: false, width: 400, headerSort: true,
                            cellClick:function(e, cell){
                                // e - the click event object
                                // cell - the cell component
                                const rowData = cell.getData();
                                const value = cell.getValue();
                                if(rowData.clicavel==1){
                                    renderCarregarDetalhes(rowData);
                                    //$(`#btn${rowData.plano_id}`).trigger('click');
                                }
                            }, 
                            formatter: function(cell, formatterParams, onRendered) {
                                const rowData = cell.getData();
                                const value = cell.getValue();
                                if(rowData.clicavel==1){
                                    return `<span class="cell-clicavel">${value}</span>`
                                } else {
                                    return value;
                                }
                            }},
                            { title: "Valor", field: "valor", width: 120, headerHozAlign:"right", vertAlign: "middle", hozAlign: "right", bottomCalc: "sum", formatter: function(cell, formatterParams, onRendered) {
                                const rowData = cell.getData();
                                const value = cell.getValue();
                                if(value == null || isNaN(value)){
                                    return value;
                                } else {
                                    return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                            }, bottomCalcFormatter: function (cell) {
                                const valor = cell.getValue();
                                return valor.toLocaleString("pt-BR", {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                                })
                            }},
                            { title: "%", field: "percentual", width: 80, headerHozAlign:"center", vertAlign: "middle", hozAlign: "center", bottomCalc:"sum", formatter: function(cell, formatterParams, onRendered) {
                                    const rowData = cell.getData();
                                    const value = cell.getValue();
                                    if(value == null || isNaN(value)){
                                        return value;
                                    } else {
                                        return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ((rowData.cabecalho==0 || rowData.plano =='Total') && rowData.tipo != 3?"%":"");
                                    }
                                }, bottomCalcFormatter: function (cell) {
                                const valor = cell.getValue();
                                return valor.toLocaleString("pt-BR", {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                                }) + "%"
                            }},
                            { title: "%Fat", field: "percentualfat", width: 80, headerHozAlign:"center", vertAlign: "middle", hozAlign: "center", bottomCalc: "sum", formatter: function(cell, formatterParams, onRendered) {
                                    const rowData = cell.getData();
                                    const value = cell.getValue();
                                    if(value == null || isNaN(value)){
                                        return value;
                                    } else {
                                        return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ((rowData.cabecalho==0 || rowData.plano =='Total') && rowData.tipo != 3?"%":"");
                                    }
                                }, bottomCalcFormatter: function (cell) {
                                const valor = cell.getValue();
                                return valor.toLocaleString("pt-BR", {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                                }) + "%"
                            }}];
        tblDre = new Tabulator("#" + div, {
            locale: "pt-br",
            langs: {
            "pt-br": tabulatorPtBr,
            },
            height: "100%",
            layout: "fitData",
            data: dados,
            columns: colunas,
            rowHeight: 30,
            headerVisible: true,
            //responsiveLayout: true,
            rowFormatter: function(row){
                if(row.getData().cabecalho == 2){
                    row.getElement().classList.add("header-table-row");
                } else
                if(row.getData().cabecalho == 1){
                    row.getElement().classList.add("header-row");
                }
            },
        });
        tblDre.on("tableBuilt", function(){
            renderedDre = true;
            resizeDreBalanco();
        });
    }

    function resizeDreBalanco(){
        if(renderedBalanco && renderedDre){
            if($('#divDre').height()>$('#divBalanco').height()){
                $('#divBalanco').height($('#divDre').height());
            } else {
                $('#divDre').height($('#divBalanco').height());
            }
        }
    }

    function renderCarregarDetalhes(rowData){
        openModalLoader("Aguarde", "Carregando Informações");
        setTimeout(()=>{
            carregarDetalhes(rowData);
        }, 500)
    }

    function carregarDetalhes(rowData) {
        var url = root + '/dashboardgerencial.getDetalhes?ano=' + ano + '&mes=' + mes + '&plano_id=' + rowData.plano_id + '&juros=' + (rowData.juros==undefined?0:rowData.juros);
        ajaxGenerator(url, "GET", function (data) {
                closeModalLoader();
                if (typeof data === 'object')
                    if(data.status == 'OK'){
                        renderTabelaDetalhe('tbl_detalhes', data.data, rowData);
                    } else {
                        bootbox.alert(data.msg);
                    }
                else if (typeof data === 'string')
                    bootbox.alert("Erro: " + data);
                else
                    bootbox.alert("Erro desconhecido");
            }, function () {
                closeModalLoader();
                bootbox.alert('Erro ao carregar dados');
        });
    }

    function renderTabelaDetalhe(div, dados, rowDataPai) {
        let colunas = [{ title: "", field: "descricao", vertAlign: "middle", frozen: false, width: 450, headerSort: false, cellClick:function(e, cell){
                            const rowData = cell.getData();
                            const value = cell.getValue();
                            if(rowData.clicavel==1){
                                renderCarregarCentroCustos(rowData);
                            }
                        }, formatter: function(cell, formatterParams, onRendered) {
                            const rowData = cell.getData();
                            const value = cell.getValue();
                            if(rowData.clicavel==1){
                                return `<span class="cell-clicavel">${value}</span>`
                            } else {
                                return value;
                            }
                        }}];
        if(rowDataPai.plano_id == -2){
            colunas.push({ title: "Qtde", field: "quantidade", width: 120, vertAlign: "middle", hozAlign: "right", formatter: function(cell, formatterParams, onRendered) {
                                const value = cell.getValue();
                                if(value == null || isNaN(value)){
                                    return value;
                                } else {
                                    return value.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                                }
                        }});
            colunas.push({ title: "Custo Médio", field: "custo", width: 120, vertAlign: "middle", hozAlign: "right", formatter: function(cell, formatterParams, onRendered) {
                                const value = cell.getValue();
                                if(value == null || isNaN(value)){
                                    return value;
                                } else {
                                    return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                        }});
        }
        colunas.push({ title: "", field: "valor", width: 120, vertAlign: "middle", hozAlign: "right", formatter: function(cell, formatterParams, onRendered) {
                            const rowData = cell.getData();
                            const value = cell.getValue();
                            if(value == null || isNaN(value)){
                                return value;
                            } else {
                                return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                        }});
         colunas.push({ title: "", field: "percentual", width: 80, vertAlign: "middle", hozAlign: "center", formatter: function(cell, formatterParams, onRendered) {
                                    const rowData = cell.getData();
                                    const value = cell.getValue();
                                    if(value == null || isNaN(value)){
                                        return value;
                                    } else {
                                        return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ((rowData.cabecalho==0 || rowData.descricao =='Total') && rowData.tipo != 3?"%":"");
                                    }
        }});
        colunas.push({ title: "", field: "percentualfat", width: 80, vertAlign: "middle", hozAlign: "center", formatter: function(cell, formatterParams, onRendered) {
                                    const rowData = cell.getData();
                                    const value = cell.getValue();
                                    if(value == null || isNaN(value)){
                                        return value;
                                    } else {
                                        return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ((rowData.cabecalho==0 || rowData.descricao =='Total') && rowData.tipo != 3?"%":"");
                                    }
        }});
        try {
            tblDetalhes.destroy();
        } catch(e){

       }
        //$(`#btn${rowDataPai.plano_id}`).trigger('click');
        tblDetalhes = new Tabulator("#" + div, {
            locale: "pt-br",
            langs: {
            "pt-br": tabulatorPtBr,
            },
            height: "100%",
            layout: "fitData",
            data: dados,
            columns: colunas,
            rowHeight: 30,
            headerVisible: false,
            rowFormatter: function(row){
                if(row.getData().cabecalho == 2){
                    row.getElement().classList.add("header-table-row");
                } else
                if(row.getData().cabecalho == 1){
                    row.getElement().classList.add("header-row");
                }
            },
        });
        $('#modalDetalhesTitle').html(rowDataPai.plano_id==-1?'Receitas':(rowDataPai.plano==-2?'Custos Variáveis':rowDataPai.plano));
        openModalDetalhes();
    }

    function renderCarregarCentroCustos(rowData){
       openModalLoader("Aguarde", "Carregando Informações");
        setTimeout(()=>{
            carregarCentroCustos(rowData);
        }, 500)
    }

    function carregarCentroCustos(rowData) {
        var url = root + '/dashboardgerencial.getCentroCustos?ano=' + ano + '&mes=' + mes + '&plano_id=' + rowData.plano_id;
        ajaxGenerator(url, "GET", function (data) {
                closeModalLoader();
                if (typeof data === 'object')
                    if(data.status == 'OK'){
                        renderTabelaCentroCustos('tbl_centrocustos', data.data, rowData.plano_id);
                    } else {
                        bootbox.alert(data.msg);
                    }
                else if (typeof data === 'string')
                    bootbox.alert("Erro: " + data);
                else
                    bootbox.alert("Erro desconhecido");
            }, function () {
                closeModalLoader();
                bootbox.alert('Erro ao carregar dados');
        });
    }

    function renderTabelaCentroCustos(div, dados,plano_id) {
        let colunas = [{ title: "", field: "descricao", vertAlign: "middle", frozen: false, width: 450, headerSort: false, 
                        formatter: function(cell, formatterParams, onRendered) {
                            const rowData = cell.getData();
                            const value = cell.getValue();
                            if(rowData.clicavel==1){
                                return `<span class="cell-clicavel">${value}</span>`
                            } else {
                                return value;
                            }
                        }}];
        colunas.push({ title: "", field: "valor", width: 120, vertAlign: "middle", hozAlign: "right", formatter: function(cell, formatterParams, onRendered) {
                            const rowData = cell.getData();
                            const value = cell.getValue();
                            if(value == null || isNaN(value)){
                                return value;
                            } else {
                                return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                        }});
         colunas.push({ title: "", field: "percentual", width: 80, vertAlign: "middle", hozAlign: "center", formatter: function(cell, formatterParams, onRendered) {
                                    const rowData = cell.getData();
                                    const value = cell.getValue();
                                    if(value == null || isNaN(value)){
                                        return value;
                                    } else {
                                        return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ((rowData.cabecalho==0 || rowData.descricao =='Total') && rowData.tipo != 3?"%":"");
                                    }
        }});
        colunas.push({ title: "", field: "percentualfat", width: 80, vertAlign: "middle", hozAlign: "center", formatter: function(cell, formatterParams, onRendered) {
                                    const rowData = cell.getData();
                                    const value = cell.getValue();
                                    if(value == null || isNaN(value)){
                                        return value;
                                    } else {
                                        return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ((rowData.cabecalho==0 || rowData.descricao =='Total') && rowData.tipo != 3?"%":"");
                                    }
        }});        
        try {
            tblCentroCustos.destroy();
        } catch(e){

       }
        tblCentroCustos = new Tabulator("#" + div, {
            locale: "pt-br",
            langs: {
            "pt-br": tabulatorPtBr,
            },
            height: "100%",
            layout: "fitData",
            data: dados,
            columns: colunas,
            rowHeight: 30,
            headerVisible: false,
            rowFormatter: function(row){
                if(row.getData().cabecalho == 2){
                    row.getElement().classList.add("header-table-row");
                } else
                if(row.getData().cabecalho == 1){
                    row.getElement().classList.add("header-row");
                }
            },
        });
        openModalCentroCustos();
    }

    $('#btnFiltroDre').on('click', ()=>{
        renderCarregarDre();
    })

    function renderCarregarDre(){
        openModalLoader("Aguarde", "Carregando Informações");
        setTimeout(()=>{
            carregarDre();
        }, 500)
    }

    function carregarDre(tipoDocumento, tipoExport) {
        ano = $('#ano').val();
        mes = $('#mes').val();
        var url = root + '/dashboardgerencial.getDre?ano=' + ano + "&mes=" + mes;
        ajaxGenerator(url, "GET", function (data) {
                closeModalLoader();
                if (typeof data === 'object')
                    if(data.status == 'OK'){
                        renderTabelaDre('tbl_dre', data.data.dataDreMensal);
                    } else {
                        bootbox.alert(data.msg);
                    }
                else if (typeof data === 'string')
                    bootbox.alert("Erro: " + data);
                else
                    bootbox.alert("Erro desconhecido");
            }, function () {
                closeModalLoader();
                bootbox.alert('Erro ao carregar dados');
        });
    }

    function renderSaldos() {
        tblSaldos = new Tabulator("#tbl_saldos", {
            locale: "pt-br",
            langs: {
                "pt-br": tabulatorPtBr,
            },
            height: "100%",
            layout: "fitData",
            ajaxURL: root + "/comodatogestao.getSaldos",
            ajaxResponse: (_url, _params, response) => response.data,
            columns: [
                { title: "Descrição", field: "descricao" },
                {
                    title: "Estoque Atual",
                    field: "qtde_estoque",
                    hozAlign: "center",
                    formatter: "money",
                    formatterParams: {
                        decimal: ",",
                        thousand: ".",
                        negativeSign: true,
                        precision: 0,
                    },
                },
                {
                    title: "Comodato Cliente",
                    field: "qtde_cliente",
                    hozAlign: "center",
                    formatter: "money",
                    formatterParams: {
                        decimal: ",",
                        thousand: ".",
                        negativeSign: true,
                        precision: 0,
                    },
                },
                {
                    title: "Comodato Companhia",
                    field: "qtde_comp",
                    hozAlign: "center",
                    formatter: "money",
                    formatterParams: {
                        decimal: ",",
                        thousand: ".",
                        negativeSign: true,
                        precision: 0,
                    },
                },
                {
                    title: "Saldo",
                    field: "saldo",
                    hozAlign: "center",
                    formatter: function (cell) {
                        let value = cell.getValue();
                        let $el = cell.getElement();

                        if (value < 0) {
                            $el.style.color = "red";
                        }

                        return formataDecimal(value, 0);
                    },
                },
                {
                    title: "Valor Saldo",
                    field: "valorsaldo",
                    hozAlign: "right",
                    formatter: function (cell) {
                        let value = cell.getValue();
                        let $el = cell.getElement();

                        if (value < 0) {
                            $el.style.color = "red";
                        }

                        return formataDecimal(value, 2);
                    },
                },
            ],
        });
         tblSaldos.on("tableBuilt", function(){
            renderedEstoque = true;
            resizeEstoqueMarketShare();
        });
    }

    function resizeEstoqueMarketShare(){
        if(renderedEstoque && renderedMarketShare){
            if($('#divEstoque').height()>$('#divMarketShare').height()){
                $('#divMarketShare').height($('#divEstoque').height());
            } else {
                $('#divEstoque').height($('#divMarketShare').height());
            }
        }
    }

    //Custom modal to avoid srolling up on hide (happens with bootstrap modal)
    const modal_detalhes = document.getElementById('modal_detalhes');
    const modal_centrocustos = document.getElementById('modal_centrocustos');
    const modal_loader = document.getElementById('modal_loader');
    const modal_alert = document.getElementById('modal_alert');
    const alert_button = document.getElementById('closeModalBtnAlert');

    function openModalDetalhes() {
        modal_detalhes.classList.remove('hidden');
    }
    function openModalCentroCustos() {
        modal_centrocustos.classList.remove('hidden');
    }
    function openModalLoader(title, message) {
        $('#loader_title').html(title);
        $('#loader_message').html(message);
        modal_loader.classList.remove('hidden');
    }
    function openModalAlert(title, message, callback = null) {
        $('#alert_title').html(title);
        $('#alert_message').html(message);
        alert_button.onclick = () => {
            if (typeof callback === 'function') {
                callback();
            }
            closeModalAlert();
        };
        modal_alert.classList.remove('hidden');
    }
    function closeModalDetalhes() {
        modal_detalhes.classList.add('hidden');
    }
    function closeModalCentroCustos() {
        modal_centrocustos.classList.add('hidden');
    }
    function closeModalLoader() {
        modal_loader.classList.add('hidden');
    }
    function closeModalAlert() {
        modal_alert.classList.add('hidden');
    }
    closeModalBtnDetalhes.addEventListener('click', closeModalDetalhes);
    closeModalBtnCentroCustos.addEventListener('click', closeModalCentroCustos);

    modal_detalhes.addEventListener('click', (event) => {
        if (event.target === modal_detalhes) {
            closeModalDetalhes();
        }
    });
    modal_centrocustos.addEventListener('click', (event) => {
        if (event.target === modal_centrocustos) {
            closeModalCentroCustos();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal_centrocustos.classList.contains('hidden')) {
            closeModalCentroCustos();
        } else 
        if (event.key === 'Escape' && !modal_detalhes.classList.contains('hidden')) {
            closeModalDetalhes();
        }
    });
