$(document).ready(function () {
    if (errorsany === true) {
        habilitarInputs(inputs);
        $("#btngravar").show();
        $("#habilitaredicao").hide();
        errorBtree();
    } else {
        bloquearInputs(inputs);
        $("#btngravar").hide();
    }
});

inputs = ['urlsistemaweb', 'urltraccar', 'usertraccar', 'passwordtraccar', 'keygooglemaps', 'temporefresh'];


$("#habilitaredicao").click(function () {
        habilitarInputs(inputs);
        $("#btngravar").show();
        $("#habilitaredicao").hide();
});

function bloquearInputs(inputs) {
    for (var i = 0; i < inputs.length; i++) {
        $("#" + inputs[i]).prop('disabled', true).trigger('chosen:updated');
    }
}

function habilitarInputs(inputs) {
    for (var i = 0; i < inputs.length; i++) {
        $("#" + inputs[i]).prop('disabled', false).trigger('chosen:updated');
    }
}
