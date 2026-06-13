var contConvenioDependente = 0;
///// Clickar botao remover clientes convenio dependente
function removerConvenioDependentes(dependente) {
    $('#divCliConvDependentes_' + dependente + '').remove();
}

function gerarSelectParentesco() {
    //// gerando select parentesco
    var optionParentesco = '';
    ///var optionParentesco = '<option value="0">Selecione</option>';
    $('select[id=selParentesco] option').each(function () {
        optionParentesco += '<option value=' + $(this).val() + '>' + $(this).text() + '</option>'
    })

    return optionParentesco;
}
////
function convenioDependentes() {
    var colNome = $('#colNome').val();
    var colParentesco = $('#colParentesco').val();
    var dependentes = new Array();

    dependentes.push(colNome);
    dependentes.push(colParentesco);

    //// gerando select parentesco
    var optionParentesco = gerarSelectParentesco();

    //// checa se existe campos gerados
    if ($('#qtdCliDep').val() > 0) {
        contConvenioDependente = contConvenioDependente + parseInt($('#qtdCliDep').val());
    }

    $('#convenioDependentes').append('\
  <div id="divCliConvDependentes_' + contConvenioDependente + '">\
  <div class="col-md-5">\
  <div class="form-group">\
  <label class="col-sm-2 control-label">Nome:</label>\
  <div class="col-sm-10">\
  <input name="convenioDependentes[Nome][' + contConvenioDependente + ']" placeholder="Nome" type="text" class="form-control">\
  </div>\
  </div>\
  </div>\
  <div class="col-md-4">\
  <div class="form-group">\
  <label class="col-sm-3 control-label">Parentesco:</label>\
  <div class="col-sm-9">\
  <select name="convenioDependentes[Parentesco][' + contConvenioDependente + ']" class="form-control">\
  ' + optionParentesco + '\
  </select>\
  </div>\
  </div>\
  </div>\
  \
  <div class="form-group col-md-1">\
  <div class="checkbox">\
  <input type="hidden" name="convenioDependentes[Ativo][' + contConvenioDependente + ']" value=0>\
  <input type="checkbox" name="convenioDependentes[Ativo][' + contConvenioDependente + ']" value="1">\
  <label for="colabInativado"><b>Ativo</b></label>\
  </div>\
  </div>\
  \
  <div class="col-md-2 margBottom_5">\
  <button onclick="removerConvenioDependentes(' + contConvenioDependente + ')" id="bttCliConvDepRemove_' + contConvenioDependente + '" type="button" class="btn btn-nw-registro"><i class="margRight_5 glyphicon glyphicon-minus"></i>Remover</button>\
  </div>\
  </div>\
  ');

    contConvenioDependente++;
    /// atribui valor 0 para qtdCliDep
    $('#qtdCliDep').attr('value', '0');

}

//////
$(document).ready(function () {

    //// gerando select parentesco caso exista com seu devido valor default
    var optionParentesco = gerarSelectParentesco();
    if ($('#qtdCliDep').val() > 0) {
        var convParentVal = '';
        for (var i = 0; i < $('#qtdCliDep').val(); i++) {
            $('#selConvParentesco_' + i + '').append(optionParentesco);

            convParentVal = $('#selConvParentValue_' + i + '').val();
            $('#selConvParentesco_' + i + ' option[value=' + convParentVal + ']').attr('selected', 'selected');

        }
    }

    //// valor atual com default em select empresa conveniada
    // var convenioId = $('#convenioId').val();
    // $("select[name=colabEmpresa]").append(new Option("Selecione", "0"));
    // if(convenioId > 0){
    //   $('select[name=colabEmpresa] option[value='+convenioId+']').attr('selected','selected');
    // }else{
    //   $('select[name=colabEmpresa] option[value=0]').attr('selected','selected');
    // }

    $("#conveniolimite").on('keyup', function () {
        if ($(this).val() === '') {
            $(this).val(0);
        }
    });
});