<div class="panel panel-default">
    <table id="tblCadastroCustom" class="table">
        <thead>
            <tr>
                <th field-id="id" sort-by="true">C&oacute;digo</th>
                @if ($tiponf === "recebida")
                    <th field-id="tipolancamento" sort-by="true">Emissão</th>
                @endif
                <th field-id="nfnumero" sort-by="true">Núm</th>
                <th field-id="nfmodelo" sort-by="false">Modelo</th>
                <th field-id="datahoraemissao" data-type="date" sort-by="true">Data</th>
                <th field-id="cliente" sort-by="true">Destinatário</th>
                @if ($tiponf === "emitida")
                    <th field-id="nfsituacao_id" sort-by="true">Cód Status</th>
                @endif
                <th field-id="nfsituacao" sort-by="true">Situação</th>
                <th field-id="vnf" data-type="money" sort-by="true">Total NF</th>
                <th field-id="buttons">Operação</th>
            </tr>
        </thead>
    </table>
</div>
<link href="{{asset('css/lib/great-table.css')}}" rel="stylesheet" type="text/css" />
<script src="{{asset('js/lib/great-table.js')}}" type="text/javascript"></script>
<script src="{{asset('js/lib/collection.js')}}" type="text/javascript"></script>

<script type="text/javascript">
    var multipleSelect = false;
    @isset($empresas)
        var empresas = JSON.parse('{!!$empresas!!}');
        multipleSelect = true;
    @endisset

    initTable({!!$nfTable!!}, {!!$currentPage!!}, {!!$totalPages!!}, multipleSelect);

function initTable(nf, currentPage, totalPages, multipleSelect) {
    var sort = getParametro('sort');
    sort = typeof sort === "string" ? sort : "nfnumero";
    var order = getParametro('order');
    order = typeof order === "string" ? order : "desc";

    tblCadastroCustom = $("#tblCadastroCustom");

    var sortObj = {
        'sort': sort,
        'order': order,
        'noSortOnTable': true,
        'serverSide': {
            url: window.location.href
        }
    };

    var tablePars = {
        'checkbox': false,
        'multipleSelectLines': multipleSelect,
        'sort': sortObj,
        'cols': {
            'id': {
                showHide: false
            },
            'nfnumero': {showHide: true},
            'tipolancamento': {showHide: true},
            'nfmodelo': {showHide: true},
            'datahoraemissao': {showHide: true},
            'cliente': {showHide: true},
            'nfsituacao_id': {showHide: true},
            'nfsituacao': {showHide: true},
            'vnf': {showHide: true},
            'buttons': {showHide: false}
        },
        'cache': false,
        'contentHeight': 400,
        'renderOnLoad': false
    };

    tablePars.paginateServerSide = {
        'serverSide': true,
        'url': window.location.href,
        'atualPage': currentPage,
        'totalPages': totalPages
    };

    tblCadastroCustom = new GreatTable(tblCadastroCustom, tablePars);

    var style = $("#tblCadastroCustom").attr('style');
    $("#tblCadastroCustom").attr('style', style + 'margin-top: 4px;');

    tblCadastroCustom.addDataToTable(nf, true, function () {
        tblCadastroCustom.render();
    });

    tblCadastroCustom.prevSort = {
        sort: sortObj.sort,
        order: sortObj.order
    };
}

</script>