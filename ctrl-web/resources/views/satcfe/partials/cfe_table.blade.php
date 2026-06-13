<div class="panel panel-default">
    <table id="tblCadastroCustom" class="table">
        <thead>
            <tr>
                <th field-id="id" sort-by="true">C&oacute;digo</th>
                <th field-id="ncfe" sort-by="true">Núm</th>
                <th field-id="datahoraemissao" data-type="date" sort-by="true">Data</th>
                <th field-id="cliente" sort-by="true">Destinatário</th>
                <th field-id="status" sort-by="true">Cód Status</th>
                <th field-id="status_descricao" sort-by="true">Situação</th>
                <th field-id="vcfe" data-type="money" sort-by="true">Total CF-e</th>
                <th field-id="buttons">Operação</th>
            </tr>
        </thead>
    </table>
</div>
<link href="{{asset('css/lib/great-table.css')}}" rel="stylesheet" type="text/css" />
<script src="{{asset('js/lib/great-table.js')}}" type="text/javascript"></script>
<script src="{{asset('js/lib/collection.js')}}" type="text/javascript"></script>

<script type="text/javascript">
    initTable({!!$data!!}, {!!$currentPage!!}, {!!$totalPages!!});

function initTable(nf, currentPage, totalPages) {
    var sort = getParametro('sort');
    sort = typeof sort === "string" ? sort : "nfnumero";
    var order = getParametro('order');
    order = typeof order === "string" ? order : "desc";

    var $tblCadastroCustom = $("#tblCadastroCustom");
    var tblCadastroCustom = $tblCadastroCustom;

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
        'multipleSelectLines': true,
        'sort': sortObj,
        'cols': {
            'id': {
                showHide: false
            },
            'ncfe': {showHide: true},
            'datahoraemissao': {showHide: true},
            'cliente': {showHide: true},
            'status': {showHide: true},
            'status_descricao': {showHide: true},
            'vcfe': {showHide: true},
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

    var style = $tblCadastroCustom.attr('style');
    $tblCadastroCustom.attr('style', style + 'margin-top: 4px;');

    tblCadastroCustom.addDataToTable(nf, true, function () {
        tblCadastroCustom.render();
    });

    tblCadastroCustom.prevSort = {
        sort: sortObj.sort,
        order: sortObj.order
    };
}

</script>