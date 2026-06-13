//var contCliProdPreco = 0;
//var contCliPromocao = 0;
//
//function valorProdutoClientePrecos(){
//
//  var id = $("select[name=cliProdutoPreco] option:selected").val();
//
//  $.ajax({
//    method: "GET",
//    url: urlGlobalPadrao + '/produto/preco/' + id + '',
//    cache: false,
//    success: function (result) {
//      ///alert(result);
//      $('input[name=cliProdutoValor]').val(result);
//    }
//  });
//
//}
//
//function alterarPromocao(){
//  var cod = $('select[name=promocao_id]').val();
//
//  $.ajax({
//    method: "GET",
//    url: urlGlobalPadrao + '/promocao/' + cod + '',
//    cache: false,
//    success: function (result) {
//      var dtInicio = dataAtualFormatada(result[0]['datahorainicio']);
//      var dtFim = dataAtualFormatada(result[0]['datahorafim']);
//
//      $('input[name=datainicio]').prop('value',dtInicio);
//      $('input[name=datafim]').prop('value',dtFim);
//
//    }
//  });
//}