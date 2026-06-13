<meta name="csrf-token" content="{{ csrf_token() }}" />
<!-- DATA TABES SCRIPT -->
<script src="{{URL::to('plugins/jQuery/jQuery-2.1.4.min.js')}}"></script>
<!-- Bootstrap 3.3.2 JS -->
<script src="{{URL::to('bootstrap/js/bootstrap.min.js')}}" type="text/javascript"></script>
<!-- AdminLTE App -->
<script src="{{URL::to('dist/js/app.min.js')}}" type="text/javascript"></script>

<script src="{{URL::to('plugins/datepicker1/moment/moment-with-locales.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/datepicker1/js/bootstrap-datetimepicker.min.js')}}" type="text/javascript"></script>


<script src="{{URL::to('plugins/datatables/jquery.dataTables.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/datatables/dataTables.bootstrap.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/slimScroll/jquery.slimscroll.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/fastclick/fastclick.min.js')}}"></script>
<script src="{{URL::to('plugins/custom_utils.js')}}"></script>
<script src="{{URL::to('plugins/bootbox.min.js')}}"></script>
<script src="{{URL::to('plugins/chosen/chosen.jquery.latin.js')}}"></script>
<script src="{{URL::to('plugins/selectize/js/standalone/selectize.min.js')}}"></script>
<script src="{{URL::to('plugins/tree-multiselect.min.js')}}"></script>
<script src="{{URL::to('plugins/jstree/dist/jstree.min.js')}}"></script>
<script src="{{URL::to('plugins/handsontable/dist/handsontable.full.js')}}"></script>
<script src="{{URL::to('js/jquery.mask.min.js')}}"></script>
<script src="{{URL::to('plugins/input-mask/jquery.inputmask.js')}}"></script>


<script src="{{URL::to('js/custom.js')}}"></script>
<script src="{{URL::to('js/jqueryMaskMoney.js')}}"></script>
<script src="{{URL::to('js/shortcut.js')}}"></script>
<!-- page script -->
<script type="text/javascript">
var root = '{{url("/")}}';
var hotParcelas;
var urlDataTable = '';
numeral.language('pt-br', {
    delimiters: {
        thousands: '.',
        decimal: ','
    },
    abbreviations: {
        thousand: 'k',
        million: 'm',
        billion: 'b',
        trillion: 't'
    },
    ordinal: function (number) {
        return number === 1 ? 'er' : 'ème';
    },
    currency: {
        symbol: '$'
    }
});
$('.modal-wide').on('show.bs.modal', function () {
    var height = $(window).height() - 200;
    $(this).find('.modal-body').css('max-height', height);
});
var myDate = new Date();
jQuery(document).ready(function ($) {
    /*
    $('#datetimepicker1').datetimepicker({
        locale: 'pt-br',
        defaultDate: myDate,
        format: 'DD/MM/YYYY HH:mm:ss',
    });
    */
    submitted = false;
    @if($errors->any())
    carregarParcelasErro();
    @else
    carregarParcelas();
    @endif
    $('.dinheiro').each(function(){ // function to apply mask on load!
        var value = parseDinheiro($(this).val(), 2);
        $(this).val(value.toFixed(2));
        $(this).maskMoney('mask', $(this).val());
    })

});


function carregarParcelasErro() {
    dataParcela = JSON.parse($('#parcelas').val()).data;
    var containerParcelas = document.querySelector('#parcelasGrid');
    hotParcelas = new Handsontable(containerParcelas, {
        data: dataParcela,
        columnSorting: false,
        sortingEnabled: false,
        contextMenu: true,
        rowHeaders: false,
        formulas: true,
        readOnly: false,
        colHeaders: ["id", "pagar_receber", "Vencto", "Nome", "Tipo", "Valor", "Desconto Pont.", "Valor Líquido"],
        colWidths: [1, 1, 100, 250, 150, 100, 100, 100],
        columns: [
            {
                readOnly: true,
                visible: false,
                className: "htCenter",
            },
            {
                readOnly: true,
                visible: false,
                className: "htCenter",
            },
            {
                readOnly: true,
                className: "htCenter",
                type: 'date', dateFormat: 'DD/MM/YYYY', correctFormat: true
            },
            {
                readOnly: true,
                className: "htCenter",
            },
            {
                readOnly: true,
                className: "htCenter",
            },
            {
                type: 'numeric',
                format: '0,0.00',
                language: 'pt-br',
                readOnly: true,
                className: "htCenter",
                renderer: function (instance, td, row, col, prop, value) {
                    Handsontable.NumericRenderer.apply(this, arguments);
                }
            },
            {
                type: 'numeric',
                format: '0,0.00',
                language: 'pt-br',
                readOnly: true,
                className: "htCenter",
                renderer: function (instance, td, row, col, prop, value) {
                    Handsontable.NumericRenderer.apply(this, arguments);
                }
            },
            {
                type: 'numeric',
                format: '0,0.00',
                language: 'pt-br',
                readOnly: true,
                className: "htCenter",
                renderer: function (instance, td, row, col, prop, value) {
                    Handsontable.NumericRenderer.apply(this, arguments);
                }
            }
        ]
    });
    //hotParcelas.loadData(JSON.parse($('#parcelas').val()).data);
    hotParcelas.render();
}
function carregarParcelas() {
    var parcelas = [];
    @foreach($parcelas as $parc)
        {!!'parcelas.push(['.
            $parc->id.',"'.
            $parc->pagarreceber.'","'.
            Carbon\Carbon::parse($parc->datavencimento)->format('d/m/Y').'","'.
            $parc->cliente_nome.'","'.$parc->descricao.'", '.
            $parc->valor.
            ']);'!!}
    @endforeach
            var containerParcelas = document.querySelector('#parcelasGrid');
    hotParcelas = new Handsontable(containerParcelas, {
        data: parcelas,
        columnSorting: false,
        sortingEnabled: false,
        contextMenu: true,
        rowHeaders: false,
        formulas: true,
        readOnly: false,
        height: 150,
        colHeaders: ["id", "pagar_receber", "Vencimento", "Cli/Forn", "Descrição", "Valor"],
        colWidths: [1, 1, 200, 350, 250, 100],
        columns: [
            {
                readOnly: true,
                visible: false,
                className: "htCenter",
            },
            {
                readOnly: true,
                visible: false,
                className: "htCenter",
            },
            {
                readOnly: true,
                className: "htCenter",
                type: 'date', dateFormat: 'DD/MM/YYYY', correctFormat: true
            },
            {
                readOnly: true,
                //className: "htCenter",
            },
            {
                readOnly: true,
                //className: "htCenter",
            },
            {
                type: 'numeric',
                format: '0,0.00',
                language: 'pt-br',
                readOnly: true,
                className: "htCenter",
                renderer: function (instance, td, row, col, prop, value) {
                    Handsontable.NumericRenderer.apply(this, arguments);
                }
            }
        ]
    });
}

function gravar() {
    if (submitted) return;
    submitted = true;
    /*
    if ($('#conta_id').val() == '' || $('#conta_id').val() == '-1') {
        bootbox.alert('Informe o caixa para registro do cancelamento.');
        return false;
    }
    if ($('#data_pagamentoM').val() == '') {
        bootbox.alert('Informe a data de cancelamento do título.');
        return false;
    }
    */
    if ($('#descricao').val() == '' || $('#descricao').val() == '-1') {
        bootbox.alert('Informe o motivo do cancelamento.');
        return false;
    }
    $('#parcelas').val(JSON.stringify({data: hotParcelas.getData()}));
    var myForm = document.getElementById('fmCadastroR');
    var formData = new FormData(myForm);
    //var formData = new FormData($(this)[0]);
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    //console.log(hotCategorias.getData());
    $.ajax({
        url: '{{ route("financeiro.cancelar") }}',
        type: 'POST',
        processData: false,
        contentType: false,
        data: formData,
        success: function (res) {
            if (res.substr(0, 3) == 'OK|') {
                urlret = '{{ route("financeiro.fecharModalIframe") }}';
                window.location.href = urlret;
            } else {
                bootbox.alert(res);
            }
        },
        error: function (data) {
            if (typeof (data) == 'object') {
                var msg = '';
                var responseText = '';
                for (var key in data) {
                    if (key == 'responseJSON') {
                        for (var key1 in data['responseJSON']) {
                            msg += data['responseJSON'][key1];
                        }
                    }
                    if (key == 'responseText') {
                        responseText = data['responseText'];
                    }
                }
                if (msg != '')
                    bootbox.alert('Erro ao gravar: ' + msg);
                else
                    bootbox.alert('Erro ao gravar: ' + responseText);
                //bootbox.alert('Erro ao gravar: ' + data.responseJSON.descricao);
            } else if (typeof (data) == 'string') {
                bootbox.alert('Erro ao gravar: ' + data);
            } else {
                bootbox.alert('Houve um erro desconhecido ao gravar!');
            }
        },
        complete: function () {
            submitted = false;
        }
    });
    //$('form#fmCadastroR').submit();
}


function setDadosCaixa() {
    $('#recebimentotipo_id').val($('#recebimentotipo_idM').val());
    $('#data_pagamento').val($('#data_pagamentoM').val());
    $('form#fmCadastroR').submit();
}

</script>

