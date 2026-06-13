var urlValidaSpedAjax = root + '/spedfiscal/validasped/:datainicio/:datafim';
var urlGerarSpedAjax = root + '/spedfiscal/gerarsped/:datainicio/:datafim/:tipoarquivooriginal';

var listaProdutos = '';

function bloquearCampos() {
  $("#condicaopagamento_id").prop('disabled', true).trigger('chosen:updated');
  $("#btnCalcularParcelas").prop('disabled', true);
  $("#btnVisualizarParcelas").prop('disabled', true);

  $("#vdesc").prop('readonly', true);
  $("#descricaofinanceiro").prop('readonly', true);
  $("#btnCcusto").prop('disabled', true);
  $("#btnPconta").prop('disabled', true);
}

function liberarCampos() {
  $("#condicaopagamento_id").prop('disabled', false).trigger('chosen:updated');
  $("#btnCalcularParcelas").prop('disabled', false);
  $("#btnVisualizarParcelas").prop('disabled', false);

  $("#vdesc").prop('readonly', false);
  $("#descricaofinanceiro").prop('readonly', false);
  $("#btnCcusto").prop('disabled', false);
  $("#btnPconta").prop('disabled', false);
}

function gerarArquivo(){
  botoesAcaoBloquear();
  if(validaSpedAjax()){
    gerarSpedAjax();
  }else{
    // bootbox.alert("Processo parou na valiação das NF para gerar o SPED.");
  }
  botoesAcaoDesbloquear();
}

function gerarSpedAjax() {
  var datainicio = $("#datainicio").val();
  var datafim = $("#datafim").val();
  var tipoarquivooriginal = $("#tipoarquivooriginal").is(':checked');

  var arraydatainicio = datainicio.split('/');
  var datainiciomes = parseInt(arraydatainicio[1]); 
  var datainicioano = parseInt(arraydatainicio[2]);

  var arraydatafim = datafim.split('/');
  var datafimmes = parseInt(arraydatafim[1]); 
  var datafimano = parseInt(arraydatafim[2]);

  if(datainiciomes == datafimmes && datainicioano == datafimano){
    var url = urlGerarSpedAjax;
    url = url.replace(':datainicio', insertDataOracle(datainicio));
    url = url.replace(':datafim', insertDataOracle(datafim));
    url = url.replace(':tipoarquivooriginal', tipoarquivooriginal);
    // alert(url);
    $("#validacoes").empty();
    $.ajax({
      headers: {
        'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
      },
      type: "GET",
      url: url,
      success: function (data) {
        if(data != ""){
          $("textarea[name='validacoes']").val(data);
          return false;
        }else{
          return true;
        }
      },
      error: function (data) {
        bootbox.alert('Erro ao validar NFs para SPED');
        return false;
      },
      cache: false,
      contentType: false,
      processData: false
    });
  }else{
    bootbox.alert("O período informado deve estar no mesmo mês e ano.");
  }
}

function validaSpedAjax() {
  var datainicio = $("#datainicio").val();
  var datafim = $("#datafim").val();

  var arraydatainicio = datainicio.split('/');
  var datainiciomes = parseInt(arraydatainicio[1]); 
  var datainicioano = parseInt(arraydatainicio[2]);

  var arraydatafim = datafim.split('/');
  var datafimmes = parseInt(arraydatafim[1]); 
  var datafimano = parseInt(arraydatafim[2]);

  if(datainiciomes == datafimmes && datainicioano == datafimano){
    var url = urlValidaSpedAjax;
    url = url.replace(':datainicio', insertDataOracle(datainicio));
    url = url.replace(':datafim', insertDataOracle(datafim));
    // alert(url);
    $("#validacoes").empty();
    $.ajax({
      headers: {
        'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
      },
      type: "GET",
      url: url,
      success: function (data) {
        bootbox.alert(data+ '.');
        if(data != ""){
          $("textarea[name='validacoes']").val(data);
          return false;
        }else{
          return true;
        }
      },
      error: function (data) {
        bootbox.alert('Erro ao validar NFs para SPED');
        return false;
      },
      cache: false,
      contentType: false,
      processData: false
    });
  }else{
    bootbox.alert("O período informado deve estar no mesmo mês e ano.");
  }
}

function botoesAcaoBloquear(){
  $("#btnGerarArquivo").prop('disabled', true);
}

function botoesAcaoDesbloquear(){
  $("#btnGerarArquivo").prop('disabled', false);
}

$(document).ready(function () {
  // tblProdutos = $('#tblProdutos').DataTable({
  //   "language": {
  //     "url": urlDataTable
  //   },
  //   "processing": false,
  //   "bPaginate": false,
  //   "bLengthChange": false,
  //   "bFilter": false,
  //   "bSort": true,
  //   "bInfo": false,
  //   "bAutoWidth": false,
  //   "columnDefs": [{
  //     "targets": [0, 2, 4, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20],
  //     "visible": false
  //   }]
  // });

  $("#tipoarquivooriginal").change(function () {
    if ($("#tipoarquivooriginal").is(':checked') == true) {
      $("#tipoarquivosubstituto").prop('checked', false);
    } else if ($("#tipoarquivooriginal").is(':checked') == false) {
      $("#tipoarquivosubstituto").prop('checked', true);
    }
  });

  $("#tipoarquivosubstituto").change(function () {
    if ($("#tipoarquivosubstituto").is(':checked') == true) {
      $("#tipoarquivooriginal").prop('checked', false);
    } else if ($("#tipoarquivosubstituto").is(':checked') == false) {
      $("#tipoarquivooriginal").prop('checked', true);
    }
  });

  
  
  // $("#fmCadastro").on('submit', function () {
  //   var produtos = [];
  //   tblProdutos.rows().every(function () {
  //     var d = this.data();
  //     produtos.push(d);
  //   });
  //   $("#produtos").val(JSON.stringify(produtos));
  // });

});