CollectJS = function (items) {
    if (typeof items === "object") {
        this.items = items;
    } else {
        this.items = [];
        console.warn("Items need to be a array");
    }
    return this;
};

collect = function (obj) {
    return new CollectJS(obj);
};

if (!Array.prototype.get) {
    Array.prototype.get = function (value) {
        return typeof this[value] !== "undefined" ? this[value] : null;
    }
}

CollectJS.prototype.get = function (value) {
    return typeof this.items[value] !== "undefined" ? this.items[value] : null;
};

if (!Array.prototype.getFloat) {
    Array.prototype.getFloat = function (value) {
        return this.get(value) ?  parseFloat(this.get(value)) : 0.0;
    }
}

CollectJS.prototype.getFloat = function (value) {
    return this.get(value) ? parseFloat(this.get(value)) : 0.0;
};

if (!Array.prototype.has) {
    Array.prototype.has = function (index, value, strict) {
        var operator = _getOperatorForStrict(strict);
        return _whereFunction(index, operator, value, this, true);
    }
}

CollectJS.prototype.has = function (index, value, strict) {
    var operator = _getOperatorForStrict(strict);
    return _whereFunction(index, operator, value, this.items, true);
};

if (!Array.prototype.unique) {
    Array.prototype.unique = function (index, strict) {
        return _uniqueFunction(index, this, strict);
    }
}

CollectJS.prototype.unique = function (index, strict) {
    return _uniqueFunction(index, this.items, strict);
};

var _uniqueFunction = function (index, elements, strict) {
    var items = [];
    $.each(elements, function (i, element) {
        if (!items.has(index, element[index], strict)) {
            items.push(element);
        }
    });

    return items ? items : collect([]);
};

CollectJS.prototype.where = function (index, operator, value) {
    return _whereFunction(index, operator, value, this.items);
};

var _whereFunction = function (index, operator, value, collection, has) {
    has = typeof has !== "undefined" ? has : false;
    var arr = [];
    if (typeof value === "undefined") {
        value = operator;
        operator = "==";
    }
    $.each(collection, function (i, element) {
        if (typeof element[index] === "undefined") {
            console.error("index " + index + " not found in collection");
        }
        if (operatorForWhere(element[index], operator, value))
            arr.push(element);
    });
    if (has) {
        return arr.length > 0;
    }
    return arr ? arr : collect([]);
};

if (!Array.prototype.where) {
    Array.prototype.where = function (index, operator, value) {
        return _whereFunction(index, operator, value, this);
    };
}

if (!Array.prototype.first) {
    Array.prototype.first = function (returnNull) {
        if (!returnNull) {
            returnNull = false;
        }
        var ret = returnNull ? null : [];
        return typeof this[0] !== "undefined" ? this[0] : ret;
    };
}


if (!Array.prototype.latest) {
    Array.prototype.latest = function () {
        return typeof this[this.length - 1] !== "undefined" ? this[this.length - 1] : [];
    };
}

if (!Array.prototype.sum) {
    Array.prototype.sum = function (index) {
        if (typeof index === "undefined") {
            console.error("Index is not defined");
            return 0;
        }
        var sum = 0;
        $.each(this, function (i, el) {
            sum += parseFloat(el[index]) || 0;
        });
        return sum;
    };
}

function operatorForWhere(el1, operator, el2) {
    switch (operator) {
        case '==':
            // noinspection EqualityComparisonWithCoercionJS
            return el1 == el2;
        case '=':
            // noinspection EqualityComparisonWithCoercionJS
            return el1 == el2;
        case '===':
            return el1 === el2;
        case '!==':
            return el1 !== el2;
        case '!=':
            // noinspection EqualityComparisonWithCoercionJS
            return el1 != el2;
        case '<>':
            // noinspection EqualityComparisonWithCoercionJS
            return el1 != el2;
        case '>':
            return el1 > el2;
        case '>=':
            return el1 >= el2;
        case '<':
            return el1 < el2;
        case '<=':
            return el1 <= el2;
        case 'in':
            if ($.type(el1) === "object") {
                var exists = false;
                $.each(el1, function (i, el) {
                    if (exists)
                        return;
                    if (el === el1)
                        exists = true;
                });
                return exists;
            } else {
                return $.inArray(el2, el1) > -1;
            }
        default:
            console.error("Operator not found");
            return false;
    }
}

var _getOperatorForStrict = function (strict){
    return !strict ? "==" : "===";
};