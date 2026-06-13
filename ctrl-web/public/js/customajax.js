var urlBuscarParcelasAjax = root + '/vendavalegas/buscarparcelasajax/:id';

function buscarParcelasAjax(idDataOperacao, idData1Vencimento, callback, idVista, idBoleto, idCartao, idCondicaoPagamento, idCondicaoparcelas, idCondicao) {
  return new Promise(((resolve, reject) => {
      if(typeof idCondicaoPagamento === 'undefined')
          idCondicaoPagamento = "condicaopagamento_id";

      var id = $("#" + idCondicaoPagamento).val();
      var url = urlBuscarParcelasAjax.replace(':id', id);
      if (id !== '') {
          $.ajax({
              headers: {
                  'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
              },
              url: url,
              type: 'GET',
              complete: function (d) {
                if (d.status === 200) {
                  resolve();
                } else {
                  reject(d);
                }
              },
              success: function (data) {
                  var cartao = data.condicao;
                  var boleto = data.condicaoparcelas;
                  var vista = data.condicao;

                  var condicaoparcelas = data.condicaoparcelas;
                  var condicao = data.condicao;

                  var date = trazerData($("#" + idDataOperacao).val());

                  if(typeof idVista === 'undefined')
                      idVista = "vista";
                  if(typeof idBoleto === 'undefined')
                      idBoleto = "boleto";
                  if(typeof idCartao === 'undefined')
                      idCartao = "cartao";
                  if(typeof idCondicaoparcelas === 'undefined')
                      idCondicaoparcelas = "condicaoparcelas";
                  if(typeof idCondicao === 'undefined')
                      idCondicao = "condicao";

                  $("#" + idVista).val(JSON.stringify(vista));
                  $("#" + idBoleto).val(JSON.stringify(boleto));
                  $("#" + idCartao).val(JSON.stringify(cartao));

                  try{
                      $("#" + idCondicaoparcelas).val(JSON.stringify(condicaoparcelas));
                      $("#" + idCondicao).val(JSON.stringify(condicao));
                  }catch(err) {
                      $("#" + idCondicaoparcelas).val("");
                      $("#" + idCondicao).val("");
                  }

                  if (boleto.length === 0) {
                      for (var i = 0; i < cartao.length; i++) {
                          var primeirapar = cartao[i].dias_primeira;
                          var primeira = parseInt(primeirapar);
                          var parcelasdate = date.addDays(primeira);
                          $("#" + idData1Vencimento).val(padronizacaoData(parcelasdate));
                      }
                  } else {
                      var dia = boleto[0].dias;
                      var dias = parseInt(dia);
                      var parcelasdate = date.addDays(dias);
                      $("#" + idData1Vencimento).val(padronizacaoData(parcelasdate));
                  }
                  if(typeof callback === 'function'){
                      callback();
                  }
              }
          });
      }
  }))
}
