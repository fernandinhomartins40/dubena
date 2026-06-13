<!--suppress JSUnusedLocalSymbols, JSUnusedAssignment, JSDuplicatedDeclaration -->
<script>
    showLoaderAjax()
    let nfeImporting = null;
    var isSN = "{{ $empresa->nfecrt < 3 ? 1 : 0 }}" === "1";
    var show = false;
    var editOrShow = false;
    var duplicate = false;
    var _nfe_id;
    var erros = false;

    @if ($errors->any())
      erros = true;
    @endif

    var loadEditOrShow = false;
    var notClearIdFieldOnHideModal = true;
    @if (isset($show) && $show)
        show = true;
    @endif
    @if($tiponf === "emitida")
        var tipoNf = "E";
        @if(!isset($show))
            var ids = ['#btnTransmitirNF', '#btnTransmitirDPEC', '#btnCancelarNF', '#btnAtualizarStatus', '#btnEnviarEmail',
                '#btnCartaCorrecao', '#btnAtualizarStatusTela'];
            desativarInputsEspecificos(ids);
        @endif
    @else
        var tipoNf = "R";
    @endif
    vNf = 0;
    @if ((isset($nfrecebida) || isset($nfemitida)) && !isset($show))
        desabilitaCamposEdicao();
        editOrShow = true;
        loadEditOrShow = true;
    @endif
    _nfe_id = parseInt('{{ isset($nfemitida) ? $nfemitida->id : 0}}');
    _nfe_id = _nfe_id ? _nfe_id : 0;
    $(document).ready(function () {
        setPricesItems({!! $produtos !!});
        onDocReadyGeneral( function () {
            let callback = function () {
                tratarOutrosDados();
            };
            let callError = function () {
                bootbox.alert("Erro ao abrir a página, entre em contato com o suporte.");
            };
            @if ($errors->any())
                tratarErros();
            @endif

            @if (isset($show) && $show)
                tratarDadosShow().then(callback).catch(callError);
            @else
                carregaOperacaoTrataTela(true).then(callback).catch(callError);
            @endif
        });
    });
    setTimeout(function () {
        loadEditOrShow = false;
        @if(isset($show) && $show)
            $("#addProdutos, #reloadDestinatario, #addRateio").attr("disabled", "disabled");
        @endisset

        hideLoaderAjax();
        @if(isset($nfemitida) && isset($duplicate))
            duplicate = true;
            $("#fmCadastro").submit();
        @endif
    }, 1000);
</script>
