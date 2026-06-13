try {
    var __gTbl;
    var GreatTable = function (tbl, _e) {

        if (typeof _e !== 'object') {
            console.error('The construct need to be object');
            return;
        }

        __gTbl = this;
        this.checkbox = false;
        this.html = '';
        this.hideColumns = false;
        this.hiddenColumns = [];
        this.tbl = tbl;
        this.th = [];
        this.contentHeight = 450;
        this.tblId = '';
        this.cols = [];
        this.searching = false;
        this.firstTime = true;
        this.prevTopScroll = 0;
        this.prevScrollSort = {top: 0, left: 0};
        this.prevSort = null;
        this.callbackClick = [];
        this.onCheckAll = null;
        this.tableRows = false;
        this.sorting = false;
        this.data = [];
        this.afterDraw = null;
        this.rendered = false;
        this.afterRender = _e.afterRender;
        this.paginateServerSide = {};
        this.atualPage = 1;
        this.renderOnLoad = true;
        this.lastSelectedRow = null;
        this.multipleSelectLines = typeof _e.multipleSelectLines !== "undefined" ? _e.multipleSelectLines : false;

        if (typeof this.afterRender !== "undefined") {
            Object.defineProperty(this, 'rendered', {
                set: function () {
                    setTimeout(function () {
                        __gTbl.afterRender(__gTbl);
                    }, 100);
                }
            });
        }

        this.writeHtml(_e, function () {
            __gTbl.events(_e);
        });

        $(document).on('click', '.page-link', function (e) {
            if ($(this).parent('li').hasClass('disabled'))
                e.preventDefault();
        });
    };

    GreatTable.prototype.events = function (ev, callback) {
        var hiddenColumns = [];

        __gTbl.renderOnLoad = typeof ev.renderOnLoad === "undefined" ? true : ev.renderOnLoad;

        __gTbl.tbl.find('th').each(function (i) {
            var th = $(this);
            th.attr('id', 'th-' + th.attr('field-id'));
            hiddenColumns[i] = __gTbl.getHiddenColumns(th);
        });

        __gTbl.hiddenColumns = hiddenColumns;
        __gTbl.hideColumnsTd();
        if (typeof ev.loadOnScrollEnd === 'object') {
            __gTbl.onScrollEndEv(ev.loadOnScrollEnd);
        }

        __gTbl.$header.on('click', 'input[type=checkbox]', function () {
            var checkbox = __gTbl.$header.find('input[type=checkbox]').eq(0);
            __gTbl.selectAllCheckboxes(checkbox.is(':checked'));
        });

        if (typeof callback === "function") {
            callback();
        }
    };

    GreatTable.prototype.addEvent = function (eventName, eventPars) {
        if (eventName === 'loadOnScrollEnd') {
            if (typeof eventPars === 'object')
                __gTbl.onScrollEndEv(eventPars);
            else
                console.error("The parameters for " + eventName + " needs to be a \"object\"");
            return this;
        } else if (eventName === 'onCheckAll') {
            if (typeof eventPars === 'function')
                __gTbl.onCheckAll = eventPars;
            else
                console.error("The parameters for " + eventName + " needs to be a \"function\"");
            return this;
        }
        console.error("The event " + eventName + ' does not exists');
    };

    GreatTable.prototype.onScrollEndEv = function (loadOnScrollEnd) {
        __gTbl.$body.on('scroll', function () {
            var height = $(this).height();
            var scrollHeight = $(this).get(0).scrollHeight;
            var scrollTop = $(this).scrollTop();
            var diff = __gTbl.prevTopScroll - scrollTop < -70 || __gTbl.prevTopScroll - scrollTop > 70;
            if (scrollTop + height >= scrollHeight - 500 && !__gTbl.searching && height < scrollHeight && diff) {
                __gTbl.searching = true;
                var url = loadOnScrollEnd.url;
                var callbackComplete = '';
                if (loadOnScrollEnd.appendDataLenghtUrl)
                    url += __gTbl.getData().length;
                if (typeof loadOnScrollEnd.onCompleteCallback === "function")
                    callbackComplete = loadOnScrollEnd.onCompleteCallback();
                var callbackSuccess = function (res) {
                    __gTbl.appendDataToTable(res, true);
                };
                __gTbl.loadDataAjax(loadOnScrollEnd.headers, url, loadOnScrollEnd.method, null, callbackSuccess, callbackComplete);
                __gTbl.prevTopScroll = scrollTop;
            }
        });
        return this;
    };

    GreatTable.prototype.loadDataAjax = function (headers, url, method, data, callbackSuccess, callbackComplete, clear = false, add = true) {
        __gTbl.processingData();
        if (typeof callbackComplete !== "function") {
            callbackComplete = function () {
                console.info('Request completed');
            };
        }
        if (typeof callbackSuccess !== "function") {
            callbackSuccess = function () {
                console.info('Request completed with success');
            };
        }
        if (data === null)
            data = {};

        $.ajax({
            headers: headers,
            url: url,
            data: data,
            dataType: 'json',
            type: method,
            cache: false,
            success: function (res) {
                if (add) {
                    if (clear) {
                        __gTbl.addRow(res, true);
                    } else {
                        __gTbl.appendDataToTable(res, true);
                    }
                }
                callbackSuccess(res);
            },
            error: function (res) {
                var msg = "Erro ao carregar os dados: :newLine";
                var responseText = '';
                if (typeof (res) === 'object') {
                    for (var key in res) {
                        if (key === 'responseJSON') {
                            for (var key1 in res['responseJSON'])
                                msg += '\n' + res['responseJSON'][key1];
                        }
                        if (key === 'responseText')
                            responseText = res['responseText'];
                    }
                    if (responseText !== '')
                        msg += responseText;
                } else if (typeof (res) === 'string') {
                    msg += res;
                } else {
                    msg = "Houve um erro desconhecido ao carregar os dados!";
                }
                if (typeof bootbox !== "undefined")
                    bootbox.alert(msg.replace(/\:newLine/g, '<br>'));
                else
                    alert(msg.replace(/\:newLine/g, '\n'));
                console.error(res);
            },
            complete: function (res) {
                __gTbl.searching = false;
                callbackComplete(res);
                return this;
            }
        });
    };

    GreatTable.prototype.writeHeader = function (cols) {
        if (typeof cols === "object") {
            let html = '<thead><tr>';
            var keys = Object.keys(cols);

            for (let i = 0; i < keys.length; i++) {
                let key = keys[i];
                let el = cols[key];
                let th = "<th ";
                th = this.addAttrTh(th, 'field-id', key);
                th = this.addAttrTh(th, 'data-type', el.dataType);
                th = this.addAttrTh(th, 'sort-by', el.sort);
                th = this.addAttrTh(th, 'data-none', el.dataNone);
                th = this.addAttrTh(th, 'hidden', el.hidden);
                th = this.addAttrTh(th, 'limit', el.limit);
                th += ">" + el.title + "</th>";
                html += th;
            }

            html += '</tr></thead>';
            this.tbl.html(html);
        }
        return this;
    };

    GreatTable.prototype.addAttrTh = function (th, ev, value) {
        if (typeof ev !== "undefined" && typeof value !== "undefined")
            th += ev + "='" + value + "' ";
        return th;
    };

    GreatTable.prototype.writeHtml = function (ev, callback) {
        if (this.tbl.find('th').length === 0)
            this.writeHeader(ev.cols);

        var tbl = __gTbl.tbl;

        tbl.find('th').each(function () {
            var prepend = "<span class='fa fa-sort'></span>&nbsp;";
            $(this).attr('sort-by') === "true" ? $(this).prepend(prepend).addClass('cursorPointer sort') : '';
        });

        if (typeof ev.afterDraw === "function")
            this.afterDraw = ev.afterDraw;

        var tableClass = tbl.attr('class');
        var tblId = tbl.attr('id');
        __gTbl.tblId = tblId;
        __gTbl.contentHeight = typeof ev.contentHeight !== "undefined" ? ev.contentHeight : 450;
        var tblHtml = tbl.html();
        var attributes = tbl.prop("attributes");
        tbl.addClass('great-table hidden');

        tbl.before("<div class='great-table-all hidden'><div class='great-table-container no-select' id='great-table-container-" + tblId + "'></div></div>");
        tbl.remove();
        $("#great-table-container-" + tblId)
                .append("<table id='great-table-header-" + tblId + "' class='" + tableClass + " great-table great-table-header'><thead><tr></tr></thead></table>")
                .append("<div id='great-table-body-" + tblId + "' class='great-table-body'><table id='" + tblId + "'>" + tblHtml + "</table></div>");

        $("#" + tblId + " th").clone().appendTo("#great-table-header-" + tblId + ' tr');

        tbl = $("#" + tblId);
        __gTbl.$container = $("#great-table-container-" + __gTbl.tblId);
        __gTbl.$body = $("#great-table-body-" + __gTbl.tblId);
        __gTbl.$header = $("#great-table-header-" + __gTbl.tblId);

        if (typeof ev.cols === 'object')
            __gTbl.colsEv(__gTbl, tbl, ev);

        __gTbl.tbl = tbl;
        for (var i = 0; attributes.length > i; i++) {
            tbl.attr(attributes[i].name, attributes[i].value);
        }

        if (typeof ev.sort === 'object') {
            __gTbl.putSortIcons(ev.sort);
            __gTbl.sortEv(ev.sort);
        }
        if (typeof ev.paginateServerSide === 'object') {
            __gTbl.paginateServerSide = ev.paginateServerSide;
            __gTbl.writeHtmlPaginate(ev.paginateServerSide);
        }
        this.tbl = tbl;
        this.th = tbl.find('th');

        tbl.offset({top: tbl.offset().top - 30});

        callback();
        return this;
    };

    GreatTable.prototype.writeHtmlPaginate = function (pars) {
        var atualPage = parseInt(pars.atualPage);
        var totalPages = parseInt(pars.totalPages);
        var onclick = typeof pars.onclick !== "undefined" ? pars.onclick : "";
        var $div = $("#paginate-great-table");
        if ($div.length === 0)
            $(".great-table-all").append("<div id='paginate-great-table'></div>");
        $div.html('');

        if (totalPages > 1) {
            var url = pars.url !== false ? __gTbl.urlFormater(pars.url) : false;
            var href = pars.url !== false ? url + "page=:page" : "javascript:void(:page);";
            var itemPagination = "<li class='page-item :extraClass'><a class='page-link' redirect-page=':redirect-page' href='" + href + "'>:pageDescription</a></li>";
            var html = "<div class='col-sm-4'><nav class='great-table-paginate' aria-label='Paginação'><ul class='pagination'>";
            var pages = atualPage + (atualPage === totalPages ? 0 : 1) + (atualPage === 1 ? 1 : 0);
            var pageStart = atualPage - (atualPage === 1 ? 0 : 1) - (atualPage === totalPages ? 1 : 0);
            var pageDescription = "<span aria-hidden='true'>&laquo;</span><span class='sr-only'>Primeira</span>";
            html += __gTbl.getItemPagination(itemPagination, 1, pageDescription, atualPage === 1 ? 'disabled' : '');

            if (atualPage > 2 && pages > 3)
                html += "<li class='page-item disabled'><a class='page-link' href='#'>...</a></li>";

            if (pageStart === 0)
                pageStart = 1;

            if (pages > totalPages)
                pages = totalPages;

            for (var i = pageStart; i <= pages; i++) {
                var extraClass = i === atualPage ? "active-table active disabled" : '';
                html += __gTbl.getItemPagination(itemPagination, i, i, extraClass);
            }

            if (totalPages > pages)
                html += "<li class='page-item disabled'><a class='page-link' href='#'>...</a></li>";

            pageDescription = "<span aria-hidden='true'>&raquo;</span><span class='sr-only'>Última</span>";
            html += __gTbl.getItemPagination(itemPagination, totalPages, pageDescription, atualPage === totalPages ? 'disabled' : '');
            html += "</ul></nav></div>";
            $("li.disabled").on('click', function (e) {
                e.preventDefault();
            });
            $("#paginate-great-table").html(html);
            $("a.page-link").on('click', function () {
                var $self = $(this);
                if (typeof onclick === "function" && !$self.parent().hasClass('disabled'))
                    onclick($self.attr('redirect-page'));
                __gTbl.atualPage = $self.attr('redirect-page');
                sessionStorage.removeItem('prevScrollTop');
                sessionStorage.removeItem('prevScrollLeft');
            });
        }
        return this;
    };

    GreatTable.prototype.sortEv = function (sortEv) {
        var $header = __gTbl.$header;
        $header.on('click', 'th', function () {
            var $cell = $(this);
            var sort = $cell.attr('field-id');
            if (sort === "checkbox") {
                return;
            }
            var dataType = $cell.attr('data-type');
            var order = $cell.hasClass('sort') || $cell.hasClass('sort-desc') ? 'ASC' : 'DESC';
            var sortIsUndefined = typeof $cell.attr('sort-by') === "undefined";

            if (sortIsUndefined || (!sortIsUndefined && $cell.attr('sort-by') === "false")) {
                return false;
            }

            if (!(typeof sortEv.noSortOnTable !== 'undefined' && sortEv.noSortOnTable)) {
                if ($cell.hasClass('sort') || $cell.hasClass('sort-asc') || $cell.hasClass('sort-desc')) {
                    __gTbl.sorting = true;
                    var prevData = __gTbl.data;
                    var sortData = [];
                    if (typeof dataType === "undefined")
                        dataType = false;

                    for (let i = 0; prevData.length > i; i++) {
                        let element = prevData[i][sort];
                        if (dataType === "date") {
                            var datasplit = element.split(" ");
                            element = datasplit[0].split("/");
                            element = element[2] + '-' + element[1] + '-' + element[0];
                            if (typeof datasplit[1] !== 'undefined')
                                element += " " + datasplit[1];
                        } else if (dataType === "money") {
                            element = parseFloat(element.replace(/\./g, '').replace(',', '.').replace("R$ ", "").trim());
                        }
                        var jsonObj = {
                            element: element,
                            index: i
                        };
                        sortData.push(JSON.stringify(jsonObj));
                    }
                    var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
                    sortData.sort(collator.compare);
                    __gTbl.resetIconSort();
                    $(this).addClass('sort-' + order.toLowerCase()).removeClass('sort').children('span').removeClass('sort').addClass('fa-sort-' + order.toLowerCase());

                    if (order === "DESC")
                        sortData.reverse();

                    var newData = [];
                    for (var i = 0; sortData.length > i; i++) {
                        var element = JSON.parse(sortData[i]);
                        newData.push(prevData[element.index]);
                    }
                    __gTbl.data = [];
                    __gTbl.clear().addRow(newData, true, function () {
                        this.sorting = false;
                    });
                    __gTbl.backPrevScroll();
                    __gTbl.prevSort = {sort: sort, order: order};
                }
            } else if (typeof sortEv.serverSide === "object" && typeof sortEv.serverSide.url !== "undefined") {
                var url = __gTbl.removeUrlParameter('page', sortEv.serverSide.url);
                url = __gTbl.removeUrlParameter('sort', url);
                url = __gTbl.removeUrlParameter('order', url);
                url = __gTbl.urlFormater(url, false);
                url += "order=" + order.toLowerCase() + "&sort=" + sort.toLowerCase();
                window.location.href = url;
            }
        });

        return this;
    };

    GreatTable.prototype.resetIconSort = function () {
        this.$header.find('th.sort,th.sort-desc,th.sort-asc').removeClass('sort sort-asc sort-desc').addClass('sort')
                .find('span.fa').removeClass('fa-sort fa-sort-asc fa-sort-desc').addClass('fa-sort');
        return this;
    };

    GreatTable.prototype.backPrevScroll = function (session = false) {
        this.$body.scrollTop(this.prevScrollSort.top).scrollLeft(this.prevScrollSort.left);
        return this;
    };

    GreatTable.prototype.putSortIcons = function (sort) {
        this.resetIconSort();
        $("th[field-id=" + sort.sort + "]")
                .removeClass('sort sort-asc sort-desc')
                .addClass('sort-' + sort.order)
                .children('span')
                .removeClass('fa-sort-asc fa-sort-desc fa-sort')
                .addClass('fa-sort-' + sort.order);
        return this;
    };

    GreatTable.prototype.colsEv = function (__gTbl, tbl, ev) {
        var cols = Object.keys(ev.cols);
        __gTbl.cols = cols;

        if (typeof ev.hideColumns === "undefined")
            ev.hideColumns = true;

        __gTbl.hideColumns = ev.hideColumns;
        if (__gTbl.hideColumns) {
            var html = '<div style="margin-top: 5px !important" class="div-hide-cols"><div id="dropdown-cols-great-table-' + __gTbl.tblId + '" class="dropdown">';

            html += '<button class="btn btn-default dropdown-toggle" type="button" id="dropdown-hide-cols-' + __gTbl.tblId + '" ';
            html += ' data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">';
            html += "<span class='fa fa-th-list'></span>&nbsp;&nbsp;<span class='fa fa-caret-down'></span>";
            html += "</button>";
            html += '<ul class="dropdown-menu" aria-labelledby="dropdownMenu1">';

            for (var i = 0; i < cols.length; i++) {
                var fieldId = cols[i];
                var col = ev.cols[fieldId];
                if (typeof col.showHide !== 'undefined' && col.showHide) {
                    var el = $("#" + __gTbl.tblId + " th[field-id=" + fieldId + "]")[0];
                    var description = tbl.children('thead').find(el).text().trim();
                    html += '<li><a tabindex="-1" class="btn-link hide-cols-btn cursor-pointer" th-field="' + fieldId + '">' + description + '</a></li>';
                }
                if (typeof col.callbackClick === 'function')
                    __gTbl.callbackClick.push({fieldId: fieldId, callback: col.callbackClick});
            }
            var enableClickEv = __gTbl.callbackClick.length > 0 || __gTbl.multipleSelectLines;

            if (enableClickEv) {
                tbl.on("click", "td", function () {
                    var fieldId = $(this).attr("field-id");
                    var cell = this;
                    for (var i = 0; __gTbl.callbackClick.length > i; i++) {
                        if (fieldId === __gTbl.callbackClick[i].fieldId)
                            __gTbl.callbackClick[i].callback(cell);
                    }

                    if (__gTbl.multipleSelectLines) {
                        var $tr = $(cell).parent('tr');

                        if (window.event.ctrlKey) {
                            __gTbl.selectLine($tr);
                        }

                        if (window.event.button === 0) {
                            if (!window.event.ctrlKey && !window.event.shiftKey) {
                                tbl.find('.selected').removeClass('selected');
                                __gTbl.selectLine($tr);
                            }
                            if (window.event.shiftKey) {
                                var indexLastSelected = __gTbl.lastSelectedRow.index();
                                var indexInit = $tr.index();

                                if (indexLastSelected > indexInit) {
                                    __gTbl.selectLinesBetweenIdx(indexInit, indexLastSelected);
                                } else if (indexLastSelected < indexInit) {
                                    __gTbl.selectLinesBetweenIdx(indexLastSelected, indexInit);
                                }
                            }
                        }
                    }
                });
            }
            html += '</ul>';
            html += '</div>';
            __gTbl.$container.before(html);

            $('.dropdown-menu').on('click', function (e) {
                e.stopPropagation();
            });
            $(".hide-cols-btn").on('click', function (e) {
                e.stopPropagation();
                var field = $(this).attr('th-field');
                var hidden = JSON.parse(localStorage.getItem("hidden-cols-" + __gTbl.tblId));
                var $fields = $('[field-id="' + field + '"]');
                if (!$fields.hasClass('hidden')) {
                    $fields.addClass('hidden');
                    $(this).addClass('line-hidden');
                    hidden[field] = {hidden: true, field: field};
                } else {
                    $fields.removeClass('hidden');
                    $(this).removeClass('line-hidden');
                    hidden[field] = false;
                }
                localStorage.removeItem("hidden-cols-" + __gTbl.tblId);
                __gTbl.adjustWidth();
                localStorage.setItem("hidden-cols-" + __gTbl.tblId, JSON.stringify(hidden));
            });
        }
        var map = {17: false, 116: false};
        if (typeof ev.cache !== 'undefined' && ev.cache) {
            $(document).keydown(function (e) {
                if (e.keyCode in map) {
                    map[e.keyCode] = true;
                    if (map[17] && map[116]) {
                        localStorage.removeItem("hidden-cols-" + __gTbl.tblId);
                    }
                }
            });
        }
        return this;
    };

    GreatTable.prototype.selectLinesBetweenIdx = function (init, last) {
        for (var i = init; i <= last; i++) {
            this.tbl.find("tr:eq(" + (i + 1) + ")").addClass('selected');
        }
        return this;
    };

    GreatTable.prototype.selectLine = function ($tr) {
        var isSelected = $tr.hasClass('selected');
        isSelected ? $tr.removeClass('selected') : $tr.addClass('selected');
        this.lastSelectedRow = $tr;
        return this;
    };

    GreatTable.prototype.render = function (callback) {
        this.tbl.removeClass('hidden');
        this.rendered = true;
        $(".great-table-all").removeClass('hidden');
        $(".great-table-body tr").css('cssText', 'visibility: visible; !important');
        if (typeof callback === "function")
            callback();
        return this;
    };

    GreatTable.prototype.selectAllCheckboxes = function (checked, uncheckHeader) {
        var tbl = this.tbl;
        checked = checked === 'true' || checked === true;
        tbl.find('tbody').find('input[type=checkbox]').each(function () {
            $(this).prop('checked', checked);
        });
        if (this.onCheckAll !== null && checked) {
            this.onCheckAll();
        }
        if (uncheckHeader) {
            this.$header.find('input[type=checkbox]').removeAttr('checked');
        }

        return this;
    };

    GreatTable.prototype.getHiddenColumns = function (th) {
        var hidden = th.attr('hidden');
        return typeof hidden !== "undefined" && (hidden === "true" || hidden === "hidden");
    };

    GreatTable.prototype.hideColumnsTd = function () {
        var data = this.getData(true);
        this.tbl.find('tbody').html('');
        this.appendDataToTable(data, true);
    };

    GreatTable.prototype.adjustWidth = function () {
        var tbl = this.tbl;
        var $header = $("#great-table-header-" + __gTbl.tblId);
        $header.removeClass('hidden');
        tbl.find('td').attr('style', 'width:1px; white-space: nowrap;');
        __gTbl.tbl.find('th').each(function (i) {
            var thHeader = $header.find("th:eq(" + i + ")");
            var thBodyWidth = $(this).css('width');
            $(this).attr('style', 'width:1px; white-space: nowrap;');
            thHeader.attr('style', '').attr('id', '').css('cssText', "padding: " + $(this).css('padding'));
            if (typeof thHeader.css('width') !== 'undefined') {
                thBodyWidth = __gTbl.getFloatPixels(thBodyWidth);
                thHeader.css('cssText', 'width: ' + thBodyWidth + 'px !important');
                var thHeaderWidth = __gTbl.getFloatPixels(thHeader.css('width'));
                var thBodyPadding = __gTbl.getFloatPixels($(this).css('padding-right'));
                var p = thBodyWidth - thHeaderWidth + thBodyPadding;
                thHeader.css('cssText', 'padding-right: ' + p + 'px !important');
            }
        });
        if (__gTbl.firstTime) {
            var $container = __gTbl.$container;
            var $body = __gTbl.$body;
            var css = "margin-bottom: 0px !important;";
            if (__gTbl.hideColumns)
                css += "margin-top: 45px !important";
            $header.css("cssText", css);
            var contentHeight = __gTbl.contentHeight - 40;
            if (__gTbl.hideColumns)
                contentHeight -= 45;
            $body.height(contentHeight);
            $container.height(__gTbl.contentHeight);
            $body.scroll(function () {
                $header.offset({left: (this.scrollLeft - $(this).offset().left) * -1});
            });
            __gTbl.firstTime = false;
        }
        return this;
    };

    GreatTable.prototype.getFloatPixels = function (str) {
        return parseFloat(str.replace('px', ''));
    };

    GreatTable.prototype.getHtmlThead = function () {
        return this.tbl.children('thead').html();
    };

    GreatTable.prototype.appendDataToTable = function (data, draw = false, sorting = false, callback = undefined) {
        if (!sorting)
            $('.no-registers-found').remove();
        this.putDataToHtml(data, draw, callback, true);
        return this;
    };

    GreatTable.prototype.addDataToTable = function (data, draw = false, callback = undefined) {
        __gTbl.clear();
        this.putDataToHtml(data, draw, callback);
        return this;
    };

    GreatTable.prototype.addRow = function (data, draw = false, callback = undefined) {
        if ($.type(data) === "object")
            data = [data];
        this.appendDataToTable(data, draw, false, callback);
        return this;
    };

    GreatTable.prototype.putDataToHtml = function (data, draw = false, callback, appending = false) {
        var html = '';

        if (!__gTbl.searching && __gTbl.rendered)
            __gTbl.processingData();

        let $th = __gTbl.th;
        let lastField = $th.eq($th.length - 1).attr('field-id');
        if (!appending)
            __gTbl.data = [];
        let count = 0;
        for (let i = 0; data.length > i; i++) {
            let objString = "{";
            let el = data[i];
            if (typeof el[lastField] !== "undefined") {
                count++;
                if (typeof el.classTr !== "undefined")
                    html += "<tr class='" + el.classTr;
                else
                    html += "<tr class='";
                html += " mousehover-pointer visible-scroll' index='" + i + "'>";

                let hasTdClass = typeof el.classTd !== "undefined" && el.classTd != '';
                let hasSpecificColumn = typeof el.specificColumn !== "undefined"
                    && (
                        (Array.isArray(el.specificColumn) && el.specificColumn.length > 0)
                        || (typeof el.specificColumn === "string" && el.specificColumn !== "")
                    );
                let tdClass = hasTdClass ? el.classTd : '';

                for (let j = 0; $th.length > j; j++) {
                    let $thisTh = $th.eq(j);
                    let fieldId = $thisTh.attr('field-id');
                    let limit = $thisTh.attr('limit');
                    let value = el[fieldId] === null ? "" : el[fieldId];

                    if (fieldId === 'checkbox')
                        html += "<td field-id='checkbox'><input type='checkbox' class='great-table-checkbox'></td>";

                    if (typeof fieldId !== 'undefined' && typeof value !== 'undefined') {
                        html += "<td ";
                        let isHidden = $thisTh.hasClass('hidden');
                        let shouldAdd = hasSpecificColumn
                            ? this.checkSpecificColumn(fieldId, el.specificColumn)
                            : false;

                        if (isHidden && shouldAdd) {
                            html += ` class='hidden' ${tdClass}`;
                        } else if (isHidden && !shouldAdd) {
                            html += ` class='hidden' `;
                        } else if (shouldAdd) {
                            html += ` class='${tdClass}' `;
                        }

                        if (__gTbl.hiddenColumns[j]) html += "hidden='true'";

                        if ($.isNumeric(value)) value = value.toString();

                        objString += '"' + fieldId + '": "' + __gTbl.getHtmlString(value) + '",';

                        if (typeof limit !== "undefined" && value.length > parseInt(limit))
                            value = value.substr(0, limit) + '...';

                        html += " field-id='" + fieldId + "'>" + value + "</td>";
                    }
                }

                if (objString.length > 1) objString = objString.substr(0, objString.length - 1);

                objString += "}";

                __gTbl.data.push(JSON.parse(objString));

                html += "</tr>";
            } else {
                let message = "Impossible to create table because the keys of object is wrong!";

                if (typeof bootbox !== "undefined"){
                    bootbox.alert(message);
                } else {
                    alert(message);
                }

                console.log(el, el[lastField]);
                return false;
            }
        }

        __gTbl.html = html;

        if (__gTbl.data.length === 0) {
            var headers = __gTbl.tbl.find('th');
            var colspan = headers.length - headers.find('hidden').length;
            var minWidth = (__gTbl.getFloatPixels(__gTbl.$container.css('width')) - 1);
            $("#great-table-header-" + __gTbl.tblId).children('th').css("min-width", "" + minWidth + 'px');
            this.tbl.append("<tr class='no-registers-found'> <td colspan='" + colspan + "' class='text-center'>Sem Registros encontrados..</td> </tr>");
            if (__gTbl.renderOnLoad)
                __gTbl.render();
        } else {
            $("#great-table-header-" + __gTbl.tblId).css("min-width", "");
        }
        if (draw)
            __gTbl.draw();
        $('.processing-data').remove();
        if (typeof callback === 'function')
            callback();
        return this;
    };

    GreatTable.prototype.checkSpecificColumn = function (element, ele_specific) {
        let isArray = Array.isArray(ele_specific);

        if (isArray) return ele_specific.includes(element);

        return ele_specific === element || ele_specific === '*';
    };

    GreatTable.prototype.processingData = function () {
        $('.no-registers-found').remove();
        var headers = __gTbl.tbl.find('th');
        var colspan = headers.length - headers.find('hidden').length;
        var minWidth = (__gTbl.getFloatPixels(__gTbl.$container.css('width')) - 1);
        $("#great-table-header-" + __gTbl.tblId).children('th').css("min-width", "" + minWidth + 'px');
        $('.processing-data').remove();
        this.tbl.append("<tr class='processing-data'> <td colspan='" + colspan + "' class='text-center'>Carregando..</td> </tr>");
        __gTbl.render();
        return this;
    };

    GreatTable.prototype.hideColsStorage = function () {
        if (localStorage.getItem("hidden-cols-" + __gTbl.tblId) === null) {
            localStorage.setItem("hidden-cols-" + __gTbl.tblId, '{}');
        } else {
            var hidden = JSON.parse(localStorage.getItem("hidden-cols-" + __gTbl.tblId));
            $.each(hidden, function(i, el) {
                if (el.hidden) {
                    $('[field-id="' + i + '"]').addClass('hidden');
                    $('[th-field="' + i + '"]').addClass('line-hidden');
                }
            });
        }
        return this;
    };

    GreatTable.prototype.draw = function () {
        __gTbl.tbl.append(__gTbl.html).ready(function ( ) {
            __gTbl.html = '';
            __gTbl.hideColsStorage();
            setTimeout(function () {
                __gTbl.adjustWidth();
                if (typeof __gTbl.afterDraw === "function")
                    __gTbl.afterDraw(__gTbl);
            }, 500);
        });
        __gTbl.tableRows = __gTbl.tbl.find('tr');

        return this;
    };

    GreatTable.prototype.clear = function () {
        this.tbl.children('tbody').html('');
        return this;
    };

    GreatTable.prototype.getData = function (all = false, fromInside = false) {
        if (all)
            return this.getDataGeneral(this.tbl.find('tr'), fromInside, all);
        return this.data;
    };

    GreatTable.prototype.getDataChecked = function (fromInside = false) {
        return this.getDataGeneral(this.tbl.find('tr:has(input[type=checkbox]:checked)'), fromInside);
    };

    GreatTable.prototype.getDataSelected = function (fromInside = false) {
        return this.getDataGeneral(this.tbl.find('tr.selected'), fromInside);
    };

    GreatTable.prototype.getRow = function ($row, fromInside = false) {
        return this.getDataGeneral($row, fromInside);
    };

    GreatTable.prototype.removeRow = function ($row) {
        $row.remove();
        this.adjustWidth();
        this.data = this.getData(true);
    };

    GreatTable.prototype.getDataGeneral = function (array, fromInside = false, all = false) {
        console.log('array: ', array);
        var data = [];
        var i = 0;
        array.each(function () {
            var tds = $(this).children('td');
            if (tds.length > 0) {
                var dataTd = __gTbl.getDataTd(tds, all, fromInside);
                if (!$.isEmptyObject(dataTd)) {
                    data[i] = dataTd;
                    i++;
                }
            }
        });
        return data;
    };

    GreatTable.prototype.getDataTd = function (tds, all, fromInside) {
        var objString = "{";
        var countPlus = __gTbl.checkbox || !fromInside ? 0 : 1;
        for (var j = 0; j < tds.length; j++) {
            var colHeader = __gTbl.tbl.find("th:eq(" + (j + countPlus) + ")");
            colHeader = colHeader.attr('field-id');
            if (colHeader !== "checkbox")
                objString += '"' + colHeader + '": "' + __gTbl.getHtmlString($(tds[j]).html()) + '",';
        }

        if (objString.length > 1)
            objString = objString.substr(0, objString.length - 1);
        objString += "}";
        return JSON.parse(objString);
    };

    GreatTable.prototype.getHtmlString = function (str) {
        return str.replace(/(\r\n|\n|\r|\t)/gm, "").replace(/(")/gm, "&quot;");
    };

    GreatTable.prototype.getItemPagination = function (item, page, pageDescription, extraClass = '') {
        return item.replace(':extraClass', extraClass).replace(':page', page).replace(':pageDescription', pageDescription).replace(':redirect-page', page);
    };

    GreatTable.prototype.urlFormater = function (url, removePage = true) {
        var lastChar = url.substr(url.length - 1, url.length);
        if (lastChar === "#")
            url = url.substr(0, url.length - 1);

        lastChar = url.substr(url.length - 1, url.length);
        if (url.indexOf("?") === -1) {
            if (lastChar !== "?")
                url = url + "?";
        } else {
            if (lastChar !== "&")
                url = url + "&";
        }
        if (removePage)
            return __gTbl.removeUrlParameter('page', url);
        else
            return url;
    };

    GreatTable.prototype.removeUrlParameter = function (parameter, url) {
        var urlparts = url.split('?');
        if (urlparts.length >= 2) {
            var prefix = encodeURIComponent(parameter) + '=';
            var pars = urlparts[1].split(/[&;]/g);

            for (var i = pars.length; i-- > 0; ) {
                if (pars[i].lastIndexOf(prefix, 0) !== -1) {
                    pars.splice(i, 1);
                }
            }

            url = urlparts[0] + '?' + pars.join('&');
            return url;
        } else {
            return url;
        }
    };
} catch (e) {
    e = "" + e;
    var date = new Date();
    var logName = 'log-great-table';
    var storageObj = {
        message: e,
        url: window.location.href,
        datetime: date
    };
    localStorage.removeItem(logName);
    var atualStorage = localStorage.getItem(logName);
    if (atualStorage === null) {
        atualStorage = [];
    } else {
        atualStorage = JSON.parse(atualStorage);
    }
    atualStorage.push(storageObj);
    atualStorage = JSON.stringify(atualStorage);
    localStorage.setItem(logName, atualStorage);
    localStorage.removeItem(logName);
}
