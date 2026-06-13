
    var tblDetalhes = undefined;
    var tblCentroCustos = undefined;
    var tblDre = undefined;
    var tblBalanco = undefined;

    $(document).ready(function () {
        $('#mes').val((moment().month() + 1)).trigger('chosen:updated');
        $('div.wrapper').removeClass('wrapper');
    });

    $('#btnFiltro').on('click', ()=>{
        renderDashboard();
    })
    
    function renderDashboard(){
        openModalLoader("Aguarde", "Carregando Informações");
        setTimeout(()=>{
            carregarDashboard();
        }, 500)
    }

    function carregarDashboard() {
        var url = root + '/fechamentomensalgestao.getDashboard?ano=' + $('#ano').val() + '&mes=' + $('#mes').val();
        ajaxGenerator(url, "GET", function (data) {
                closeModalLoader();
                if (typeof data === 'object')
                    if(data.status == 'OK'){
                        $('#divMainContainerDre').show();
                        renderTabelaDre('tbl_dre', data.data.dataDreMensal);
                        $('#divMainContainerBalanco').show();
                        renderTabelaBalanco('tbl_balanco', data.data.dataBalanco);
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

    function renderTabelaBalanco(div, dados) {
        const colunas = [   { title: "", field: "tipodescricao", vertAlign: "middle", frozen: false, width: 250, headerSort: false},
                            { title: "", field: "descricao", vertAlign: "middle", frozen: false, width: 180, headerSort: false, formatter: function(cell, formatterParams, onRendered) {
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
                            { title: "", field: "valor", width: 170, vertAlign: "middle", hozAlign: "right", formatter: function(cell, formatterParams, onRendered) {
                                const rowData = cell.getData();
                                const value = cell.getValue();
                                if(value == null || isNaN(value)){
                                    return value;
                                } else {
                                    return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
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
    }

    function renderTabelaDre(div, dados) {
        const colunas = [{ title: "", field: "plano", vertAlign: "middle", frozen: false, width: 450, headerSort: false,
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
                            { title: "", field: "valor", width: 120, vertAlign: "middle", hozAlign: "right", formatter: function(cell, formatterParams, onRendered) {
                                const rowData = cell.getData();
                                const value = cell.getValue();
                                if(value == null || isNaN(value)){
                                    return value;
                                } else {
                                    return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                            }},
                            { title: "", field: "percentual", width: 80, vertAlign: "middle", hozAlign: "center", formatter: function(cell, formatterParams, onRendered) {
                                    const rowData = cell.getData();
                                    const value = cell.getValue();
                                    if(value == null || isNaN(value)){
                                        return value;
                                    } else {
                                        return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ((rowData.cabecalho==0 || rowData.plano =='Total') && rowData.tipo != 3?"%":"");
                                    }
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
    }

    function renderCarregarDetalhes(rowData){
        openModalLoader("Aguarde", "Carregando Informações");
        setTimeout(()=>{
            carregarDetalhes(rowData);
        }, 500)
    }

    function carregarDetalhes(rowData) {
        var url = root + '/fechamentomensalgestao.getDetalhes?ano=' + $('#ano').val() + '&mes=' + $('#mes').val() + '&plano_id=' + rowData.plano_id + '&juros=' + (rowData.juros==undefined?0:rowData.juros);
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
                                        return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ((rowData.cabecalho==0 || rowData.plano =='Total') && rowData.tipo != 3?"%":"");
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

    $('#btnEnviarEmail').on('click', ()=>{
         bootbox.confirm({
            title: "Confirmação",
            message: "Deseja enviar o fechamento mensal para a diretoria?",
            buttons: {
                cancel: {
                    label: "Não",
                    className: "btn-nw-geral pull-center"
                },
                confirm: {
                    label: "Sim",
                    className: "btn-nw-registro pull-center"
                }
            },
            callback: function (result) {
                if(result){
                    renderSendMail('pdf');
                }
            }
        });
    })

    function renderSendMail(tipoExport){
        openModalLoader("Aguarde", "Enviando e-mail");
        setTimeout(()=>{
            sendMail(tipoExport);
        }, 500)
    }

    function sendMail(tipoExport) {
        var url = root + '/fechamentomensalgestao.sendMail?ano=' + $('#ano').val() + '&mes=' + $('#mes').val() + '&tipoExport=' + tipoExport;
        ajaxGenerator(url, "GET", function (data) {
                closeModalLoader();
                if (typeof data === 'object')
                    if(data.status == 'OK'){
                        bootbox.alert('E-mail enviado com sucesso');
                    } else {
                        bootbox.alert(data.msg);
                    }
                else if (typeof data === 'string')
                    bootbox.alert("Erro: " + data);
                else
                    bootbox.alert("Erro desconhecido");
            }, function () {
                closeModalLoader();
                bootbox.alert('Erro ao enviar e-mail');
        });
    }

    
    $('#btnExportDreXls').on('click', ()=>{
        renderExport('dre', 'pdf');
    })

    $('#btnExportDrePdf').on('click', ()=>{
        renderExport('dre', 'xlsx');
    })

    $('#btnExportBalancoXls').on('click', ()=>{
        renderExport('balanco', 'pdf');
    })

    $('#btnExportBalancoPdf').on('click', ()=>{
        renderExport('balanco', 'xlsx');
    })



    function renderExport(tipoDocumento, tipoExport){
        openModalLoader("Aguarde", "Gerando arquivo");
        setTimeout(()=>{
            exportar(tipoDocumento, tipoExport);
        }, 500)
    }

    function exportar(tipoDocumento, tipoExport) {
        var url = root + '/fechamentomensalgestao.export?ano=' + $('#ano').val() + '&mes=' + $('#mes').val() + '&tipoExport=' + tipoExport + '&tipoDocumento=' + tipoDocumento;
        ajaxGenerator(url, "GET", function (data) {
                closeModalLoader();
                if (typeof data === 'object')
                    if(data.status == 'OK'){
                        const fileUrl = data.data.fileUrl;
                        
                        openModalAlert('Sucesso', 'Arquivo gerado com sucesso. Clique em OK para fazer o download', function (){ 
                            const link = document.createElement('a');
                            link.href = fileUrl;
                            link.download = fileUrl.substring(fileUrl.lastIndexOf('/') + 1);
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        });
                    } else {
                        openModalAlert('Atenção', data.msg);
                    }
                else if (typeof data === 'string')
                    openModalAlert("Erro", "Erro: " + data);
                else
                    openModalAlert("Erro", "Erro desconhecido");
            }, function () {
                closeModalLoader();
                openModalAlert('Erro', 'Erro ao gerar o arquivo');
        });
    }

    function renderCarregarCentroCustos(rowData){
       openModalLoader("Aguarde", "Carregando Informações");
        setTimeout(()=>{
            carregarCentroCustos(rowData);
        }, 500)
    }

    function carregarCentroCustos(rowData) {
        var url = root + '/fechamentomensalgestao.getCentroCustos?ano=' + $('#ano').val() + '&mes=' + $('#mes').val() + '&plano_id=' + rowData.plano_id;
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
                                        return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ((rowData.cabecalho==0 || rowData.plano =='Total') && rowData.tipo != 3?"%":"");
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

