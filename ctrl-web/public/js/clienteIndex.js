$("#name, #cod").on('focusin', function () {
	shortcut.add('CTRL+Space', function () {
		$("#btnFiltro").click();
	});
	shortcut.add('Enter', function () {
		$("#btnFiltro").click();
	});
}).on('blur', function () {
    shortcut.remove('CTRL+Space');
    shortcut.remove('Enter');
});

$("#btnFiltro").on('click', function () {
	var url = root + '/cliente?name=:name&cod=:cod';
	var name = $("#name").val();
	var $cod = $("#cod");
	var cod = isNaN(parseInt($cod.val())) ? '' : parseInt($cod.val());
	if(isEmpty(name) && isEmpty(cod)){
		bootbox.alert("Ao menos um dos campos de filtro deve ser preenchido para buscar clientes.");
		return;
	}
	if(name.length < 2 && name.length > 0) {
		bootbox.alert("O nome deve possuir o menos 2 caracteres.");
		return;
	}
	url = url.replace(":name", name);
	url = url.replace(":cod", cod);
	window.location.href = url;
});