$(document).ready(function () {
     depreciacaoValor();
    $("#hiddendepreciacaovalor").val($("#depreciacaovalor").val());
    $("label[for='depreciacaodias']").text("Depreciar a cada X " + $("#tipodepreciacao option:selected").text());
});
$("#tipodepreciacao").change(function () {
    $("label[for='depreciacaodias']").text("Depreciar a cada X " + $("#tipodepreciacao option:selected").text());
});

$("#depreciacaoporcentagem").on('keyup', function () {
    depreciacaoValor();
});
$("#valororiginal").on('keyup', function () {
    depreciacaoValor();
});

function depreciacaoValor() {
    var valorOriginal = $("#valororiginal").val().replace('R$ ', '');
    var valorOriginal = valorOriginal.replace('.', '');
    var valorOriginal = valorOriginal.replace(',', '.');
    var depreciacaoPercentual = $("#depreciacaoporcentagem").val().replace(' %', '');
    var depreciacaoPercentual = depreciacaoPercentual.replace(',', '.');
    var depreciacao = parseFloat((valorOriginal * depreciacaoPercentual) / 100);
    $("#hiddendepreciacaovalor").val("R$ " + depreciacao.toFixed(2).replace('.', ','));
    $("#depreciacaovalor").val($("#hiddendepreciacaovalor").val());
}
