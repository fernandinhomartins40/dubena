
var tblMovto;
var root = '{{url("")}}';
var atualPage = data.atualPage;
var totalPages = data.totalPages;
var cleaning = false;
bootbox = parent.bootbox;
$(document).ready(function () {
    parent.showLoaderAjax("Carregando..", "Carregando movimentos.");
    parent.updateValueCheques(data.valorcheques);
    var tablePars = {
        'checkbox': true,
        'cols': {
            'id': {
                title: "Cód",
                showHide: true
            },
            'datahoraabertura': {
                title: "Data Movimento",
                dataType: 'date',
                showHide: true
            },
            'descricao': {
                title: "Descrição",
                showHide: true,
                limit: 80
            },
            'cliente': {
                title: "Cliente/Fornecedor",
                showHide: true,
                limit: 80
            },
            'valorefetivado': {
                title: "Valor",
                dataType: 'money',
                showHide: true
            },
            'pagarreceber': {
                title: "E/S",
                showHide: true
            },
            'datavencimento': {
                title: "Vencimento",
                dataType: 'date',
                showHide: true
            },
            'contamovimentotipo': {
                title: "Tipo",
                showHide: true
            },
            'botoes': {
                title: "",
                showHide: true
            },
            'origem': {
                title: "Origem",
                hidden: true,
                showHide: true
            }
        },
        'cache': false,
        'contentHeight': totalPages > 1 ? 310 : 350,
        'hideColumns': false,
        'afterDraw': function () {
            parent.hideLoaderAjax();
        }
    };

    setPages(window.location.search.split('&'));
    tablePars.paginateServerSide = {
        'serverSide': true,
        'url': false,
        'onclick': function (page) {
            parent.carregarMovimentoConta(page);
        },
        'atualPage': atualPage,
        'totalPages': totalPages
    };
    tblMovto = new GreatTable($("#tblMovto"), tablePars);
    tblMovto.render().draw();
    var style = $("#tblMovto").attr('style');
    $("#tblMovto").attr('style', style + 'margin-top: 4px');
    tblMovto.appendDataToTable(data.data, true).render();
});

$(document).on("hide.bs.modal", ".bootbox.modal", function () {
    adjustWidth(100);
});

$(window).load(function () {
    adjustWidth(1);
    if (data.msg !== "") {
        bootbox.alert(data.msg, function () {
            adjustWidth(500);
        });
    }
});

function adjustWidth(timeout = 1) {
    setTimeout(function () {
        tblMovto.adjustWidth();
    }, timeout);
}

function setPages(arrayUrl) {
    var atual = '';
    for (var i = 0; i < arrayUrl.length; i++) {
        atual = arrayUrl[i].toLowerCase();
        if (atual.indexOf('totalPages') !== -1) {
            totalPages = atual.split('=')[1];
            totalPages = totalPages.length === 0 ? 1 : totalPages;
        } else if (atual.indexOf('page') !== -1) {
            atualPage = atual.split('=')[1];
            atualPage = atualPage.length === 0 ? 1 : atualPage;
            if (!cleaning)
                sessionStorage.setItem("prevPage-tblMovto", atualPage);
        } else if (atual.indexOf('clear') !== -1) {
            cleaning = true;
            sessionStorage.removeItem('prevScrollTop');
            sessionStorage.removeItem('prevScrollLeft');
            sessionStorage.removeItem("prevPage-tblMovto");
        }
    }
}

function getAtualPage() {
    return atualPage;
}