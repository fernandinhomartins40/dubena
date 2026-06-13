$("#btnBusca").on('click', function () {
    urlConsultaGB = urlConsultaGB.replace(':situacao', $("#situacao").val());
    urlConsultaGB = urlConsultaGB.replace(':codigo', $("#codigo").val() + '&');
    window.location = urlConsultaGB;
});
