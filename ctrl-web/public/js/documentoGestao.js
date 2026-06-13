
     $(document).ready(function () {
        renderDashboard();
    });

    $('#btnRefresh').on('click', ()=>{
        renderDashboard();
    })

    function renderDashboard(){
        showLoaderAjax("Aguarde", "Carregando Informações", false);
        setTimeout(()=>{
            carregarDashboard();
        }, 500)
    }

    function carregarDashboard() {
        var url = root + '/documentogestao.getDashboard';
        ajaxGenerator(url, "GET", function (data) {
            hideLoaderAjax();
            if (typeof data === 'object')
                if(data.status == 'OK'){
                    console.log(data);
                    renderTabela60(data.data.dataTabela60dias);
                    renderTabelaDocumentos(data.data.dataTabelaDocumentos);
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
    
    function renderTabela60(dados) {
        tblConvMes = new Tabulator("#tbl_vencer60", {
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
                    title: "Documento",
                    field: "documentodescricao",
                    hozAlign: "left",
                    headerHozAlign: "left",
                },
                {
                    title: "Versão",
                    field: "numeroversao",
                    hozAlign: "center",
                    headerHozAlign: "center",
                },
                { title: "Vencimento",
                  field: "datavencimento", 
                  hozAlign: "center",
                  headerHozAlign: "center", 
                  formatter: "datetime",
                  formatterParams: {
                    inputFormat: "yyyy-MM-dd HH:mm:ss", // Format of the date in your data
                    outputFormat: "dd/MM/yyyy", // Desired display format
                    invalidPlaceholder: "(data inválida)", // Text for invalid dates
                 }
                },
                { title: "Dias para Vencimento", field: "qtdiasvencer", hozAlign: "center",
                headerHozAlign: "center", },
                {
                    title: "",
                    field: "alerta",
                    width: 10,
                    headerSort:false,
                    formatter: (cell) => {
                        const data = cell.getRow().getData();
                        return `<div class="alert-circle" style="background-color: ${data.alerta};"></div>`;
                    },
                    tooltip: (_e, cell) => {
                        const data = cell.getRow().getData();
                        if(data.alerta == 'green'){
                            return 'Documento está dentro da validade';
                        } else if(data.alerta == 'orange'){
                            return 'Documento está dentro da validade, mas já em alerta para vencimento';
                        } else if(data.alerta == 'red'){
                            return 'Documento está vencido';
                        }
                        return '';
                    },
                },
                {
                    title: "Arquivo",
                    formatter: function(cell, formatterParams, onRendered) {
                        const rowData = cell.getRow().getData();
                        return '<button class="btn btn-nw-default btn-xs"><span class="fa fa-download fa-sm"></span></button>';
                    },
                    cellClick: function(e, cell) {
                        const rowData = cell.getRow().getData();
                        downloadVersao(rowData.id);
                    },
                    width: 100, 
                    headerSort: false, 
                    resizable: false, 
                    hozAlign: "center",
                    headerHozAlign: "center",
                },
            ],
        });
    }

    function renderTabelaDocumentos(dados) {
        tblConvMes = new Tabulator("#tbl_documentos", {
            locale: "pt-br",
            langs: {
                "pt-br": tabulatorPtBr,
            },
            dataTree: true,
            dataTreeStartExpanded: true,
            //height: 500,
            layout: "fitData",
            data: dados,
            pagination: false,
            paginationSize: 15,
            paginationSizeSelector: [15, 25, 50, 100],
            dataReceiveParams: {
                last_page: "last_page",
            },
            columns: [
                {
                    title: "Descrição",
                    field: "descricao",
                    hozAlign: "left",
                    headerHozAlign: "left",
                    headerSort:false,
                },
                { title: "Emissao",
                  field: "dataemissao", 
                  hozAlign: "center",
                  headerHozAlign: "center", 
                  headerSort:false,
                  formatter: "datetime",
                  formatterParams: {
                    inputFormat: "yyyy-MM-dd HH:mm:ss", // Format of the date in your data
                    outputFormat: "dd/MM/yyyy", // Desired display format
                    invalidPlaceholder: "(data inválida)", // Text for invalid dates
                 }
                },
                { title: "Vencimento",
                  field: "datavencimento", 
                  hozAlign: "center",
                  headerHozAlign: "center",
                  headerSort:false, 
                  formatter: "datetime",
                  formatterParams: {
                    inputFormat: "yyyy-MM-dd HH:mm:ss", // Format of the date in your data
                    outputFormat: "dd/MM/yyyy", // Desired display format
                    invalidPlaceholder: "(data inválida)", // Text for invalid dates
                 }
                },
                { title: "Dias para Vencimento", field: "qtdiasvencer", hozAlign: "center",headerSort:false,
                headerHozAlign: "center", },
                {
                    title: "",
                    field: "alerta",
                    width: 10,
                    headerSort:false,
                    formatter: (cell) => {
                        const data = cell.getRow().getData();
                        return `<div class="alert-circle" style="background-color: ${data.alerta};"></div>`;
                    },
                    tooltip: (_e, cell) => {
                        const data = cell.getRow().getData();
                        if(data.alerta == 'green'){
                            return 'Documento está dentro da validade';
                        } else if(data.alerta == 'orange'){
                            return 'Documento está dentro da validade, mas já em alerta para vencimento';
                        } else if(data.alerta == 'red'){
                            return 'Documento está vencido';
                        }
                        return '';
                    },
                },
                {
                    title: "Arquivo",
                    formatter: function(cell, formatterParams, onRendered) {
                        const rowData = cell.getRow().getData();
                        return rowData.dataemissao != undefined?'<button class="btn btn-nw-default btn-xs"><span class="fa fa-download fa-sm"></span></button>':'';
                    },
                    cellClick: function(e, cell) {
                        const rowData = cell.getRow().getData();
                        downloadVersao(rowData.id);
                    },
                    width: 100, 
                    headerSort: false, 
                    resizable: false, 
                    hozAlign: "center",
                    headerHozAlign: "center",
                },
            ],
        });
    }

    async function downloadVersao(id) {
        const downloadUrl = root + '/documento.downloadversao/' + id;
        showLoaderAjax("Aguarde", "Baixando arquivo", false);
        try {
            const response = await fetch(downloadUrl);
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(errorText || `Erro no servidor: ${response.status}`);
            }
            const contentDisposition = response.headers.get('Content-Disposition');
            let filename = 'downloaded-file'; 

            if (contentDisposition) {
                const filenameMatch = contentDisposition.match(/filename\*?=["']?(.*?)["']?$/i);
                if (filenameMatch && filenameMatch[1]) {
                    filename = decodeURIComponent(filenameMatch[1].replace(/^UTF-8''/, ''));
                }
            }
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            a.remove();
            hideLoaderAjax();
        } catch (error) {
            hideLoaderAjax();
            console.error('Falha no download:', error);
            bootbox.alert('Ocorreu um erro ao baixar o arquivo.');
        }
    }