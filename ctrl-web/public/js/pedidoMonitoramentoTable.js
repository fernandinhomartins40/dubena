
var searching = false;
var atualPage = 0;
var empresaSelected = false;
var cleaning = false;
function init(totalPages, extraTotalPages) {
    initTable(totalPages, extraTotalPages);
    $('[data-toggle="tooltip"]').tooltip();
    shortcut.add("F1", parent.showHideHelpDialog);

    // verifica qual foi a td que o usuário selecionou e abre as modals de edição, caso necessário
    $("#tblAcompanhamentoPedidos").on('click', 'td', function () {
        if ( ! pode ) return;
        var trElem = $(this).parent('tr');
        var id = $($(trElem).children('td')[4]).text();
        var nome_informal = $($(trElem).children('td')[10]).text();
        var td = $(this).text();
        parent.editaUm(id, td, nome_informal);
    });

    // verifica qual foi a td que o usuário selecionou e abre as modals de edição, caso necessário
    $("#tblAcompanhamentoPedidos").on('dblclick', 'td', function () {
        var trElem = $(this).parent('tr');
        var checkbox = $($(trElem).children('td')[0]).text();
        var id = $($(trElem).children('td')[4]).text();
        var td = $(this).text();
        if (td != id && id !== '' && checkbox != td) {
            var url = root + '/pedido/' + id;
            window.open(url, '_blank');
        }
    });

    $("#btnEditaVariosStatus").on('click', function () {
        var ids = [];
        nome_informal = '';
        $('#tblAcompanhamentoPedidos').children('tbody').find('input:checkbox').each(function (i, el) {
            if (el.checked) {
                var trElem = $($(el).parent('td')).parent('tr');
                var id = $($(trElem).children('td')[4]).text();
                ids.push(id);
            }
        });
        parent.editaVarios(ids);
    });
}

function initTable(totalPages, extraTotalPages) {
    var urlPars = window.location.search.split("&");
    var sort = 'codigo';
    var order = 'desc';
    $.each(urlPars, function (i, el) {
        var par = el.split('=');
        if (par[0] == 'sortBy')
            sort = par[1];
        if (par[0] == 'order')
            order = par[1]
        if (par[0] == 'empresa_id') {
            if (par[1] !== '')
                empresaSelected = true;
        }
    });
    totalPages = parseInt(totalPages) + (extraTotalPages > 0 ? 1 : 0);

    var contentHeight = totalPages > 1 ? 330 : 400;

    //essa função seta a variavel atualPage
    getParsStringUrl(window.location.search.split('&'));
    if (atualPage === 0)
        atualPage = 1;
    tblAcompanhamentoPedidos = $("#tblAcompanhamentoPedidos");
    var url = parent.getUrlSearch();

    var tablePars = {
        'checkbox': true,
        'sort': {'sort': sort, 'order': order, 'noSortOnTable': true},
        'showHide': true,
        'cache': true,
        'contentHeight': contentHeight,
        'iFrame': true,
        'paginateServerSide': {
            'serverSide': true,
            'url': url,
            'atualPage': atualPage,
            'totalPages': totalPages
        },
        'cols': {
            'datahora': {showHide: true},
            'datahoraenvioentregador': {showHide: true},
            'setorcolaborador': {showHide: true},
            'cliente': {showHide: true},
            'status': {showHide: true},
            'empresa': {showHide: true},
            'endereco': {showHide: true},
            'pagamento': {showHide: true},
            'telefone': {showHide: true},
            'valor': {showHide: true},
            'entregataxa': {showHide: true},
            'urgente': {showHide: true}
        }
    };
    tblAcompanhamentoPedidos = new GreatTable(tblAcompanhamentoPedidos, tablePars);
    tblAcompanhamentoPedidos.render();

    $("#great-table-header-tblAcompanhamentoPedidos").on('click', 'th', function () {
        if (typeof $(this).attr('sort-by') !== 'undefined') {
            if ($(this).hasClass('sort-asc') && !$(this).hasClass('sort'))
                var order = 'desc';
            else
                var order = 'asc';
            var sort = $(this).attr('field-id');
            var url = root + '/pedidomonitoramento/getPedidos' + getParsStringUrl(window.location.search.split('&'));
            url += 'sortBy=' + sort + '&order=' + order;
            var sort = JSON.stringify({sort: sort, order: order});
            sessionStorage.removeItem("sorting-by-" + tblAcompanhamentoPedidos.tblId);
            sessionStorage.setItem("sorting-by-" + tblAcompanhamentoPedidos.tblId, sort);
            window.location.href = url;
        }
    });
}

function onLoadWindow(pedidos) {
    $('[data-toggle="tooltip"]').tooltip();
    tblAcompanhamentoPedidos.appendDataToTable(pedidos, true);
    if (empresaSelected) {
        $("#divEditaVariosStatus").removeClass('hidden');
    } else {
        $("table").find('input:checkbox').prop('disabled', true);
    }
    $("#great-table-body-tblAcompanhamentoPedidos")
            .scrollTop(sessionStorage.getItem('prevScrollTop'))
            .scrollLeft(sessionStorage.getItem('prevScrollLeft'));
}
function getParsStringUrl(arrayUrl) {
    var url = '?';
    var atual = '';
    for (var i = 0; i < arrayUrl.length; i++) {
        atual = arrayUrl[i].toLowerCase();
        var permitido = hasStringIn('datainicio', atual)
                || hasStringIn('datafinal', atual)
                || hasStringIn('status_id', atual)
                || hasStringIn('setor_id', atual)
                || hasStringIn('colaborador_id', atual)
                || hasStringIn('empresa_id', atual);
        if (permitido) {
            if (hasStringIn('?', atual))
                atual = atual.replace('?', '');
            url += atual + '&';
        } else if (hasStringIn('page', atual)) {
            atualPage = atual.split('=')[1];
            atualPage = atualPage.length === 0 ? 1 : atualPage;
            if (!cleaning)
                sessionStorage.setItem("prevPage-tblAcompanhamentoPedidos", atualPage);
        } else if (hasStringIn('clear', atual)) {
            cleaning = true;
            sessionStorage.removeItem('prevScrollTop');
            sessionStorage.removeItem('prevScrollLeft');
            sessionStorage.removeItem("prevPage-tblAcompanhamentoPedidos");
        }
    }
    return url;
}

function hasStringIn(str, container) {
    return container.indexOf(str) !== -1;
}
