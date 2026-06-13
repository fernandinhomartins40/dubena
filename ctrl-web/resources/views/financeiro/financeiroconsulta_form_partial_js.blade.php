<!-- page script -->
<script type="text/javascript">
    var urlDataTable = "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}";
    var root = '{{url("/")}}';
    var dataParcela = [];
        @foreach($Financeiro->parcelas as $parc)
            @if($parcelasagrupadas->where('id', $parc->id)->count() === 0)
                {!!'dataParcela.push(["'.
                                Carbon\Carbon::parse($parc->datavencimento)->format('d/m/Y').'",'.
                                $parc->valor.
                                ']);'!!}
            @endif
        @endforeach
        dataParcela.push(['Total', 0]);

    var hotParcelas;
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
        ordinal : function (number) {
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

    jQuery(document).ready(function($){
        @if($tipo_lancamento == "R" && isset($Financeiro->condicaoPagamento) && isset($Financeiro->condicaoPagamento->tipo) && ($Financeiro->condicaoPagamento->tipo == 2 || $Financeiro->condicaoPagamento->tipo == 3))
            $("#divCartao").show().find('input').prop('disabled', true);
        @endif
        $("#condicaopagamento_id").prop('disabled', true)
        $('#datetimepicker1').datetimepicker({
            locale: 'pt-br',
            viewMode: 'days',
            format: 'DD/MM/YYYY',
        });

        $('#datetimepicker2').datetimepicker({
            locale: 'pt-br',
            viewMode: 'days',
            format: 'DD/MM/YYYY',
        });

        $('#datetimepicker3').datetimepicker({
            locale: 'pt-br',
            viewMode: 'days',
            format: 'DD/MM/YYYY',
        });

        $('#datetimepicker4').datetimepicker({
            locale: 'pt-br',
            format: 'DD/MM/YYYY HH:mm:ss',

        });

        $('#searchbox').selectize({
            valueField: 'id',
            labelField: 'nome',
            searchField: ['nome'],
            maxOptions: 10,
            options: [],
            create: false,
            render: {
                    option: function(item, escape) {
                            return '<div>' +escape(item.nome)+'</div>';
                    }
            },
            optgroups: [
                    {value: 'cliente', label: 'Clientes'},
            ],
            optgroupField: 'class',
            optgroupOrder: ['cliente'],
            load: function(query, callback) {
                    if (!query.length) return callback();
                    $.ajax({
                            url: root+"/api/{{$tipo_lancamento=='P'?'searchFornecedores':'searchClientes'}}",
                            type: 'GET',
                            dataType: 'json',
                            data: {
                                    q: query
                            },
                            error: function() {
                                    callback();
                            },
                            success: function(res) {
                                    callback(res.data);
                            }
                    });
            },
            onChange: function(){
                    //alert('aqui');
                    //$('#cliente_id_erro').val($('#searchbox').selectize()[0].selectize.getValue());
                    //$('#cliente_nome_erro').val($('#searchbox').selectize()[0].selectize.getItem(this.items[0]).context.innerText);
                    //preencheDadosCliente($('#searchbox').selectize()[0].selectize.getValue());
                    //console.log($('#searchbox').selectize()[0].selectize.getItem(this.items[0]).context.innerText);
            },onInitialize: function() {
                var existingOptions = JSON.parse(this.$input.attr('data-selectize-value'));
                var self = this;
                @if($errors->any())
                    var opt = [{"id":$('#cliente_id_erro').val(),"nome":$('#cliente_nome_erro').val()}];
                    opt.forEach( function (existingOption) {
                            self.addOption(existingOption);
                            self.addItem(existingOption[self.settings.valueField]);
                    });
                @elseif(isset($origemAgrupar))
                    var opt = [{"id":"{{$cliente_id}}","nome":"{!!$nome!!}"}];
                    opt.forEach( function (existingOption) {
                            self.addOption(existingOption);
                            self.addItem(existingOption[self.settings.valueField]);
                    });
                @else
                    if(Object.prototype.toString.call( existingOptions ) === "[object Array]") {
                            existingOptions.forEach( function (existingOption) {
                                    self.addOption(existingOption);
                                    self.addItem(existingOption[self.settings.valueField]);
                            });
                    }
                    else if (typeof existingOptions === 'object') {
                            self.addOption(existingOptions);
                            self.addItem(existingOptions[self.settings.valueField]);
                    }
                @endif
            }
        });

        tblRateio = $('#tblRateio').DataTable( {
            "language": { "url" : "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}"},
            "processing": false,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": true,
            "bInfo": false,
            "bAutoWidth": false,
            "columnDefs": [
                {
                        "targets": [ 0 ],
                        "visible": false
                },
                {
                        "targets": [ 1 ],
                        "visible": true
                },
                {
                        "targets": [ 2 ],
                        "visible": false
                },
                {
                        "targets": [ 3 ],
                        "visible": true
                },
                {
                        "targets": [ 4 ],
                        "visible": true
                },
             ]

        });

                $('.dinheiro').each(function(){ // function to apply mask on load!
                    var value = parseDinheiro($(this).val(), 2);
                    $(this).val(value.toFixed(2));
                    $(this).maskMoney('mask', $(this).val());
                })
            carregarParcelas();
                $('#searchbox')[0].selectize.disable();
    });

    function getTotal(){
        return dataParcela.reduce(function(sum, row){
            return sum + row[1];
        }, 0);
    }
    function getTotalPerc(){
        return dataParcela.reduce(function(sum, row){
            return sum + row[2];
        }, 0);
    }

    function carregarParcelas(){
        var containerParcelas = document.querySelector('#parcelasGrid');
        hotParcelas = new Handsontable(containerParcelas, {
            data: dataParcela,
            columnSorting: false,
            sortingEnabled:false,
            contextMenu: true,
            rowHeaders: false,
            formulas:true,
            readOnly: false,
                        width: 700,
                        height: 250,
                        maxRows: dataParcela.length,
            colHeaders: ["Dia", "Valor"],
            colWidths: [100, 150],
            columns: [
            {
                readOnly: true,
                className: "htCenter",
                type: 'date', dateFormat: 'DD/MM/YYYY', correctFormat: true
            },
            {
                type: 'numeric',
                format: '0,0.00',
                language: 'pt-br',
                readOnly: true,
                className: "htCenter",
                renderer: function(instance, td, row, col, prop, value){
                    if(row == instance.countRows() - 1){
                        value = getTotal();
                    }
                    Handsontable.NumericRenderer.apply(this, arguments);
                }
            }
            ],
            afterChange: function (changes, source) {
                if(changes != null){
                    // If the source of the changes is named 'sum', we do not want to update the table. (we just did).
                    if(source != '%'){
                        var a, b, c, sum, i, value;
                        var total = parseDinheiro($('#valor').val(), 2);

                        for (var i = 0; i < changes.length; i++) {
                            var change = changes[i];
                            var line = change[0];

                            b = parseFloat(this.getDataAtCell(line, 1));
                            if(total != '' && total != undefined){
                                //alert('q' + total + 'q');
                                value = b / total;
                            }

                            // We want to programmatically update the table.
                            // Let's update it, and associate the source 'sum' to the event.
                            this.setDataAtCell(change[0], 2, value, '%');
                        }

                    }
                }
            }
        });
    }


        function carregarParcelamento(){
        if($('#condicaopagamento_id').val() == '')
            return;
        $.ajax({
                url: root+'/api/searchCondicaoPagamento',
                type: 'GET',
                dataType: 'json',
                data: {
                        q: $('#condicaopagamento_id').val(),
                },
                success: function(res) {
                                        atualizarParcelamento(res);
                }
        });
    }
        function atualizarParcelamento(condicao){
            //A VISTA
            if(condicao.tipo==0 || condicao.tipo==2){
                if(condicao.tipo==0){
                    $('#divCartao').hide();
                    $('#cartaonsu').val('');
                    $('#cartaoautorizacao').val('');
                } else {
                    $('#divCartao').show();
                }

                $('#mainNav li:eq(1) a').hide();
                $('#divVencimento').show();
                if(condicao.dias_primeira != null){
                    dt = trazerData($('#dataemissao').val());
                    $('#datavencimento').val(padronizacaoData(dt.addDays(parseInt(condicao.dias_primeira))));
                }
            } else {
            //A PRAZO
                $('#mainNav li:eq(1) a').show();
                $('#divVencimento').hide();
                if(condicao.tipo==1){
                    $('#divCartao').hide();
                    $('#cartaonsu').val('');
                    $('#cartaoautorizacao').val('');
                    if(condicao.condicao_pagamento_parcela != null){
                        parcs = condicao.condicao_pagamento_parcela;
                        dataParcela = [];
                        total = parseDinheiro($('#valor').val(), 2);
                        var num_dias = 0;
                        for(i=0;i<parcs.length;i++){
                            dt = trazerData($('#dataemissao').val());
                            valorParcela = Math.round(parseFloat(parcs[i].percentualvalor)/100*parseDinheiro($('#valor').val(), 2)*100)/100;
                            total = Math.round(total*100)/100;
                            if(i==(parcs.length-1)){
                                valorParcela = total;
                            }
                            num_dias += parseInt(parcs[i].dias);
                            dataParcela.push([padronizacaoData(dt.addDays(parseInt(num_dias))), valorParcela, parseFloat(parcs[i].percentualvalor)/100]);
                            total-= valorParcela;
                        }
                        dataParcela.push(['', 0, 0]);
                        hotParcelas.loadData(dataParcela);
                        hotParcelas.render();
                        hotParcelas.updateSettings({
                            cells: function (row, col, prop) {
                              var cellProperties = {};

                              if (row == dataParcela.length-1) {
                                cellProperties.readOnly = true;
                              }

                              return cellProperties;
                            }
                          });
                    }
                } else if(condicao.tipo==3) {
                    $('#divCartao').show();
                    if(condicao.num_parcelas > 0){
                        dataParcela = [];
                        total = parseDinheiro($('#valor').val(), 2);
                        var num_dias = 0;
                        for(i=0;i<condicao.num_parcelas;i++){
                            dt = trazerData($('#dataemissao').val());
                            valorParcela = Math.round(parseDinheiro($('#valor').val(), 2)/condicao.num_parcelas*100)/100;
                            total = Math.round(total*100)/100;
                            if(i==(condicao.num_parcelas-1)){
                                valorParcela = total;
                            }
                            num_dias += parseInt(condicao.intervalo);
                            dataParcela.push([padronizacaoData(dt.addDays(parseInt(num_dias))), valorParcela, parseFloat(1/condicao.num_parcelas)]);
                            total-= valorParcela;
                        }
                        dataParcela.push(['', 0, 0]);
                        console.log(dataParcela);
                        hotParcelas.loadData(dataParcela);
                        hotParcelas.render();
                        hotParcelas.updateSettings({
                            cells: function (row, col, prop) {
                              var cellProperties = {};

                              if (row == dataParcela.length-1) {
                                cellProperties.readOnly = true;
                              }

                              return cellProperties;
                            }
                          });
                    }
                }
            }
        }

</script>


