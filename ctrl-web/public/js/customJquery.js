(function ($) {
    $.fn.extend({
        isEmpty: function () {
            var myVal = $(this).val();

            if (myVal === null)
                return true;

            return myVal.isEmpty();
        },

        moneyToFloat: function () {
            return moneyToFloatFun($(this).val());
        },

        validateEmail: function () {
            if ($(this).isEmpty())
                return false;

            var value = $(this).val();
            return value.validateEmail();
        },

        refresh: function ( picker ) {
            if ( typeof picker === "undefined" ) picker = false;

            if ( picker && $(this).hasClass('selectpicker') ) {
                $(this).selectpicker('refresh');
            } else {
                $(this).trigger('chosen:updated');
            }

            return this;
        },

        intVal: function () {
            let $self = $(this);
            return parseInt($self.val()) ? parseInt($self.val()) : 0;
        }
    });
}(jQuery));

// jQuery plugin to prevent double submission of forms
jQuery.fn.preventDoubleSubmission = function () {
    $(this).on('submit', function (e) {
        var $form = $(this);

        if ($form.data('submitted') == true) {
            // Previously submitted - don't submit again
            e.preventDefault();
            console.log('preventou');
        } else {
            // Mark it so that the next submit can be ignored
            $form.data('submitted', true);
            console.log('colocou');
        }
        // setTimeout(function() {
        //     $form.removeData('submitted');
        // }, 5000);
        console.log('passou');
    });
    // var last_clicked, time_since_clicked;

    // $(this).bind('submit', function(event) {

    //     if(last_clicked) {
    //         console.log('time');
    //         time_since_clicked = jQuery.now() - last_clicked;
    //     }

    //     last_clicked = jQuery.now();

    //     if(time_since_clicked < 2000) {
    //         // Blocking form submit because it was too soon after the last submit.
    //         console.log('travou!');
    //         event.preventDefault();
    //     }

    //     return true;
    // });
    // Keep chainability
    return this;
};

String.prototype.validateEmail = function () {
    if (this.isEmpty())
        return false;

    var reg = /^(?:[a-z0-9!#$%&amp;'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&amp;'*+/=?^_`{|}~-]+)*|"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])$/;
    return reg.test(this);
};

String.prototype.isEmpty = function () {
    return !(!this || (this.length !== 0 && this !== '') || this === null);

};

Number.prototype.floatToMoney = function () {
    if (typeof this === "undefined") {
        console.error('undefined');
        return '';
    }

    return "R$ " + this.toFixed(2).replace(".", ",");
};

String.prototype.moneyToFloat = function () {
    return moneyToFloatFun(this);
};

function moneyToFloatFun(value) {
    return parseFloat(parseFloat(value.replace('R$ ', '').replace(/\./g, '').replace(',', '.')).toFixed(2));
}

Array.prototype.diff = function (a) {
    return this.filter(function (i) {
        return a.indexOf(i) < 0;
    });
};

String.prototype.equalsStrictIgnoreCase = function (compare) {
    return this.replaceSpecialChars().toUpperCase().trim() === compare.replaceSpecialChars().toUpperCase().trim();
};

String.prototype.replaceSpecialChars = function () {
    return replaceSpecialCharsFun(this);
};

function replaceSpecialCharsFun(str) {
    str = str.replace(/[ÀÁÂÃÄÅ]/, "A");
    str = str.replace(/[àáâãäå]/, "a");
    str = str.replace(/[ÈÉÊË]/, "E");
    str = str.replace(/[éèêë]/, "e");
    str = str.replace(/[ÏÌÍÎ]/, "I");
    str = str.replace(/[ïìíî]/, "i");
    str = str.replace(/[ÓÒÔÖÕ]/, "O");
    str = str.replace(/[óòôöõ]/, "o");
    str = str.replace(/[ÚÙÛÜ]/, "U");
    str = str.replace(/[úùûü]/, "u");
    str = str.replace(/[ÝŸ]/, "Y");
    str = str.replace(/[ýÿ]/, "y");
    str = str.replace(/[Ç]/, "C");
    str = str.replace(/[ç]/, "c");
    str = str.replace(/[Ñ]/, "N");
    str = str.replace(/[ñ]/, "n");
    return str.replace(/[^a-z0-9]/gi, '');
}

String.prototype.mask = function (mask) {
    let maskared = '';
    let j = 0;
    let value = this;
    for (let i = 0; i <= mask.length - 1; i++) {
        if (mask[i] === "#" || parseInt(mask[i])) {
            if (typeof value[j] !== "undefined") {
                maskared += value[j++];
            }
        } else {
            if (typeof mask[i] !== "undefined") {
                maskared += mask[i];
            }
        }
    }
    return maskared;
};
