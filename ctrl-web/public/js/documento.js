    var versaoDropzone = new Dropzone("#fmVersao", { 
        url: "/seu-endpoint-de-upload", 
        autoProcessQueue: false, 
        uploadMultiple: false, 
        maxFiles: 1, 
        addRemoveLinks: true, 
        dictRemoveFile: "Remover",
        init: function() {
            this.on("addedfile", function(file) {
                // Se já houver um arquivo, remova o anterior
                if (this.files.length > 1) {
                    this.removeFile(this.files[0]);
                }
            });
        }
    });


    jQuery(document).ready(function ($) {
        tblVersoes = $('#tblVersoes').DataTable({
            "language": {"url": urlLanguage},
            "processing": false,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": true,
            "order": [[1, 'desc']],
            "bInfo": false,
            "bAutoWidth": false,
            "columnDefs": [
                {
                    "targets": [0,6],
                    "visible": false,
                }
            ]
        });
        setTimeout(function () {
            if(show){
                    desativarInputs();
                var ids = [".btn-danger", ".btn-nw-registro", '#btnAddDoc', '#addSetor', '.btnEditarVersao', '#btnAddVersao'];
                desativarInputsEspecificos(ids);
            }
        }, $(document).ready());

    });

    $('#btnGravarVersao').on('click', ()=>{
        validarVersao();
    })

    function removerVersao(id, numeroversao){
        bootbox.confirm({
            title: "Atenção!",
            className: "dontHideEsc",
            message: 'Deseja remover essa versão (' + numeroversao + ')?',
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
                    operacaoVersao = 'DEL';
                    var formData = new FormData();
                    formData.append('versao_id', id);
                    formData.append('operacao', operacaoVersao);
                    formData.append('documento_id', documento_id);
                    gravarVersao(formData);
                }
            }
        });
    }

    function addVersao(){
        operacaoVersao = 'ADD';
        $('#versao_id').val('');
        $('#numeroversao').val('');
        $('#descricaoversao').val('');
        $('#emissaoversao').val('');
        $('#vencimentoversao').val('');
        $('#ativoversao').prop("checked", true);
        $('#desativarversao').prop("checked", true);
        $('#divDesativarVersao').show();
        $('#versao_modal').modal('show');
        $('#divVersaoUpload').html('Solte seu arquivo aqui ou clique para selecionar.');
    }

    function validarVersao() {
        if ($('#numeroversao').val().trim() == '') {
            bootbox.alert('Preencha o número da versão.');
            return;
        }
        if ($('#emissaoversao').val().trim() == '') {
            bootbox.alert('Preencha a data de emissão.');
            return;
        }
        if ($('#vencimentoversao').val().trim() == '') {
            bootbox.alert('Preencha a data de vencimento.');
            return;
        }
        let erro = false;
        tblVersoes.rows().every(function () {
            var d = this.data();
            if(d[1]==$('#numeroversao').val().trim() && (d[0]!=$('#versao_id').val() || $('#versao_id').val()=='')){
                erro = true;
            }
        });
        if(erro){
            bootbox.alert('Esse número de versão já existe');
            return;
        }
        if(operacaoVersao=='ADD'){
            if (versaoDropzone.files.length === 0) {
                bootbox.alert("Por favor, selecione um arquivo");
                return; 
            }
        }
        var formData = new FormData();
        formData.append('versao_id', $('#versao_id').val());
        formData.append('numeroversao', $('#numeroversao').val());
        formData.append('descricaoversao', $('#descricaoversao').val());
        formData.append('emissaoversao', $('#emissaoversao').val());
        formData.append('vencimentoversao', $('#vencimentoversao').val());
        formData.append('ativoversao', $('#ativoversao').is(':checked'));
        formData.append('desativarversao', $('#desativarversao').is(':checked'));
        formData.append('operacao', operacaoVersao);
        formData.append('documento_id', documento_id);
        const file = versaoDropzone.files[0];
        formData.append("file", file);
        gravarVersao(formData);
    }

    function editarVersao(id){
        let ver = null;
        tblVersoes.rows().every(function () {
            var d = this.data();
            if(d[0]==id){
                ver = d;
            }
        });
        if(!ver){
            bootbox.alert('Registro de versão não encontrado');
            return;
        }
        operacaoVersao = 'UPD';
        $('#versao_id').val(ver[0]);
        $('#numeroversao').val(ver[1]);
        $('#descricaoversao').val(ver[2]);
        $('#emissaoversao').val(ver[3]);
        $('#vencimentoversao').val(ver[4]);
        $('#ativoversao').prop("checked", ver[5]=='Sim');
        $('#desativarversao').prop("checked", true);
        $('#divDesativarVersao').hide();
        versaoDropzone.removeAllFiles();
        $('#divVersaoUpload').html('Solte seu arquivo aqui ou clique para selecionar. Não selecione nenhum arquivo para manter o atual.');
        $('#versao_modal').modal('show');
    }

    function gravarVersao(formData){
        var url = root + '/documento.versao';
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
                        const versoes = data.data;
                        tblVersoes.clear().draw(false);
                        versoes.map((v)=>{
                            tblVersoes.row.add([
                                v.id,
                                v.numeroversao,
                                v.descricao,
                                requestDataOracle(v.dataemissao, false),
                                requestDataOracle(v.datavencimento, false),
                                v.ativo=="1"?'Sim':'Não',
                                v.nomearquivo,
                                `
                                <button type='button' onclick="editarVersao(${v.id})" class='btnEditarVersao btn btn-nw-geral btn-xs' id='btnEditarVersao'><span class="fa fa-pencil-square-o fa-lg" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar Versão"></span></button>
                                <button type='button' onclick="downloadVersao(${v.id})" class='btn btn-nw-geral btn-xs' id='btnDownloadVersao'><span class="fa fa-download fa-lg" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Download do Arquivo"></span></button>
                                <button type='button' onclick="removerVersao(${v.id}, ${v.numeroversao})" class='btn btn-nw-registro btn-xs' id='btnRemoverVersao' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover Versão"><span class="fa fa-trash fa-lg"></span></button>
                                `
                            ]).draw(false);
                        })
                        $('#numeroversao').val('');
                        $('#descricaoversao').val('');
                        $('#emissaoversao').val('');
                        $('#vencimentoversao').val('');
                        $('#ativoversao').prop("checked", true);
                        $('#desativarversao').prop("checked", true);
                        $('#versao_modal').modal('hide');
                        versaoDropzone.removeAllFiles();
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
                bootbox.alert('Erro ao gravar a versão');
            },
            cache: false,
            contentType: false,
            processData: false
        });
    }
    
    async function downloadVersao(id) {
        let ver = null;
        tblVersoes.rows().every(function () {
            var d = this.data();
            if(d[0]==id){
                ver = d;
            }
        });
        if(!ver || ver[6]==''){
            bootbox.alert('Registro de versão não encontrado');
            return;
        }
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