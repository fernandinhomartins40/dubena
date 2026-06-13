
<script type="text/javascript">
    var root = "{{url('/')}}";
    var selectizeInitialized = false;

    shortcut.add("F1", function () {
        var operation = 'hide';
        if(!$("#modal_ajuda").is(':visible'))
            operation = 'show'

        $("#modal_ajuda").modal(operation);
    });

    $('.modal-wide').on('show.bs.modal', function () {
        var height = $(window).height() - 200;
        $(this).find('.modal-body').css('max-height', height);
    });
    $(".delete").on("submit", function(){
        return confirm("Quer remover o registro atual?");
    });
    $("#fmCadastro").on("submit", function(){
        if($("input[type=checkbox][name=boletoemite]").is(':checked')){
            if(!validaCamposBoleto())
                return false;
            if($("input[type=checkbox][name=boletocorrespondente]").is(':checked')){
                if(! $('#searchboxcorresp').selectize()[0].selectize.getValue()){
                    bootbox.alert('Se marcar que existe correspondente bancário, escolha o banco desejado.');
                    return false;
                }
            }
        }
        var users = [];
        tblUser.rows().every( function () {
            var d = this.data();
            users.push(d);
        } );
        $('#users').val(JSON.stringify(users));
        var talaos = [];
        tblTalao.rows().every( function () {
            var d = this.data();
            talaos.push(d);
        } );
        $('#talaos').val(JSON.stringify(talaos));
    });
    function validaCamposBoleto () {
        var args = [	$('#boletosequencia').val(),
                        $('#boletoremessasequencia').val(),
                        $('#boletoaceite').val(),
                        $('#boletocarteira').val(),
                        $('#boletobyte').val(),
                        $('#boletoespecie').val(),
                        $('#boletomulta').val(),
                        $('#boletojuros').val(),
                        $('#boletocedentedigito').val(),
                        $('#boletoinstrucoes').val()
                    ];
        if(isEmptyMultiple(args)){
            bootbox.alert('Para emissão de boleto, os campos referentes a boleto devem ser prenchidos.');
            return false;
        }
        if(!checkFieldsIntrucoes())
            return false;
        return true;
    }
    function checkFieldsIntrucoes () {
        if(typeof layoutBanco == 'undefined') {
            bootbox.alert('Por favor, defina o layout de cobrança.');
            return false
        }
        if($("#boletoprotesto_baixadevolucao").val() == 0) {
            if(parseInt($("#boletodiasprotesto").val()) < parseInt(layoutBanco.minimodiasbaixadevolucao)){
                bootbox.alert('O mínimo de dias para Baixa/Devolução definido no layout escolhido é de: ' + layoutBanco.minimodiasbaixadevolucao);
                return false;
            } else if (parseInt($("#boletodiasprotesto").val()) > parseInt(layoutBanco.maximodiasbaixadevolucao)){
                bootbox.alert('O máximo de dias para Baixa/Devolução definido no layout escolhido é de: ' + layoutBanco.maximodiasbaixadevolucao);
                return false;
            }
        } else {
            if(parseInt($("#boletodiasprotesto").val()) < parseInt(layoutBanco.minimodiasprotesto)){
                bootbox.alert('O mínimo de dias para Protesto definido no layout escolhido é de: ' + layoutBanco.minimodiasprotesto);
                return false;
            } else if (parseInt($("#boletodiasprotesto").val()) > parseInt(layoutBanco.maximodiasprotesto)){
                bootbox.alert('O máximo de dias para Protesto definido no layout escolhido é de: ' + layoutBanco.maximodiasprotesto);
                return false;
            }
        }
        return true;
    }
    $(window).load(function () {
        @if(isset($show))
            $("#searchboxcorresp").selectize()[0].selectize.disable();
            $("#searchbox").selectize()[0].selectize.disable();
        @endif
    });
    jQuery(document).ready(function($){
        @if(isset($Conta))
            $("#saldo_inicial").prop('disabled', true);
            $("#saldo_atual").prop('disabled', true);
        @endif
        mudarTipoConta();
        let layoutbanco_id = $("#layoutbanco_id").val();
        searchLayoutBanco(layoutbanco_id);
        checkProtestoOrBaixa();
        validaEmiteBoleto();

        let onLoadBanco = function (query, callback) {
            if (! query.length)
                return callback();
            $.ajax({
                url: root + '/api/searchBanco',
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
        };

        let render ={
            option: function(item, escape) {
                return '<div>' + escape(item.descricao) + '</div>';
            }
        };

        let optGroups = [
            {value: 'banco', label: 'Bancos'},
        ];

        let onInitializeBanco = function() {
            var existingOptions = JSON.parse(this.$input.attr('data-selectize-value'));
            var self = this;
            @if($errors->any())
            var opt = [{"id":$('#banco_id_erro').val(),"descricao":$('#banco_descricao_erro').val()}];
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
        };

        let onInitializeBancoCorr = function() {
            let existingOptions;
            try {
                existingOptions = JSON.parse(this.$input.attr('data-selectize-value'));
            } catch {
                existingOptions = [];
            }
            var self = this;
            @if($errors->any())
                var opt = [{"id":$('#banco_id_erro_corresp').val(),"descricao":$('#banco_descricao_erro_corresp').val()}];
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
        };

        let onChangeBanco = function (){
            let selectize = $('#searchbox').selectize()[0].selectize;
            $('#banco_id_erro').val(selectize.getValue());
            $('#banco_descricao_erro').val(selectize.getItem(this.items[0]).context.innerText);
        };

        let onChangeBancoCorr = function () {
            let selectize = $('#searchboxcorresp').selectize()[0].selectize;
            $('#banco_id_erro_corresp').val(selectize.getValue());
            $('#banco_descricao_erro_corresp').val(selectize.getItem(this.items[0]).context.innerText);
        };

        $('#searchbox').selectize({
            valueField: 'id',
            labelField: 'descricao',
            searchField: ['descricao'],
            maxOptions: 10,
            options: [],
            create: false,
            render: render,
            optgroups: optGroups,
            optgroupField: 'class',
            optgroupOrder: ['banco'],
            load: onLoadBanco,
            onChange: onChangeBanco,
            onInitialize: onInitializeBanco
        });

        $('#searchboxcorresp').selectize({
            valueField: 'id',
            labelField: 'descricao',
            searchField: ['descricao'],
            maxOptions: 10,
            options: [],
            create: false,
            render: render,
            optgroups: optGroups,
            optgroupField: 'class',
            optgroupOrder: ['banco'],
            load: onLoadBanco,
            onChange: onChangeBancoCorr,
            onInitialize: onInitializeBancoCorr
        });
        selectizeInitialized = true;
        habilitaCamposBoleto(isEmpty(layoutbanco_id));

        tblUser = $('#tblUsers').DataTable( {
            "language": { "url" : "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}"},
            "processing": false,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": true,
            "bInfo": false,
            "bAutoWidth": false,
            "columnDefs": [{"targets": [ 0 ], "visible": false } ]

        });
        $('#tblUsers').on( 'click', 'button', function () {
            var trElem = $(this).closest("tr");// grabs the button's parent tr element
            var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id

            if($(firstTd).text() != ""){
                if($(this).context.id == 'btnRemoverUser'){
                    tblUser
                    .row( $(this).parents('tr') )
                    .remove()
                    .draw();
                }
            };
        });
        @if($errors->any())
            carregarUsersErro();
        @endif
        tblTalao = $('#tblTalaos').DataTable( {
            "language": { "url" : "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}"},
            "processing": false,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": true,
            "bInfo": false,
            "bAutoWidth": false,
            "columnDefs": [{"targets": [ 0 ], "visible": false }]
        });
        $('#tblTalaos').on( 'click', 'button', function () {
            var trElem = $(this).closest("tr");// grabs the button's parent tr element
            var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
            var thirdTd = $(trElem).children("td")[2];
            if($(firstTd).text() != ""){
                if($(firstTd).text() != $(thirdTd).text()){
                    bootbox.alert('Este talão já foi utilizado. Exclusão não permitida.');
                    return;
                }
                if($(this).context.id == 'btnRemoverTalao'){

                    tblTalao
                    .row( $(this).parents('tr') )
                    .remove()
                    .draw();
                }
            };
        });
        @if($errors->any())
            carregarTalaosErro();
        @endif
    });
    function mudarTipoConta(){
        var tipo = $("#contatipo_id").val();
        $('#divContaBanco1').hide();
        $('#divContaBanco2').hide();
        $('#mainNav li:eq(2) a').hide();
        $('#mainNav li:eq(3) a').hide();
        switch(tipo){
            case '1':
                $('#divContaBanco1').show();
                $('#divContaBanco2').show();
                $('#mainNav li:eq(2) a').show();
                $('#mainNav li:eq(3) a').show();
                break;
            case '2':
                $('#divContaBanco1').hide();
                $('#divContaBanco2').hide();
                $('#mainNav li:eq(2) a').hide();
                $('#mainNav li:eq(3) a').hide();
                break;
            case '3':
                $('#divContaBanco1').hide();
                $('#divContaBanco2').hide();
                $('#mainNav li:eq(2) a').hide();
                $('#mainNav li:eq(3) a').hide();
                break;
            case '5':
                $('#divContaBanco1').show();
                $('#divContaBanco2').show();
                break;
            case '6':
                $('#divContaBanco1').hide();
                $('#divContaBanco2').hide();
                $('#mainNav li:eq(2) a').hide();
                $('#mainNav li:eq(3) a').hide();
                break;
        }
    }
    function addUser(){
        if(!isInt($('#user_id').val())){
            bootbox.alert('Escolha o usuário.');
            return;
        }
        var achou = false;
        tblUser.rows().every( function () {
            var d = this.data();
            if(d[0]==$('#user_id').val()){
                achou = true;
                $('#tblUsers').dataTable().fnUpdate(($('#visualizar').is(':checked') ? 'Sim' : 'Não'), this.index(), 2);
                $('#tblUsers').dataTable().fnUpdate(($('#operar').is(':checked') ? 'Sim' : 'Não'), this.index(), 3);
                $('#tblUsers').dataTable().fnUpdate(($('#transferir').is(':checked') ? 'Sim' : 'Não'), this.index(), 4);
                $('#tblUsers').dataTable().fnUpdate(($('#estornar').is(':checked') ? 'Sim' : 'Não'), this.index(), 5);
                $('#tblUsers').dataTable().fnUpdate(($('#lancarfechado').is(':checked') ? 'Sim' : 'Não'), this.index(), 6);
            }
        });
        if (!achou) {
            tblUser.row.add( [
                $('#user_id').val(),
                $('#user_id option:selected').text(),
                $('#visualizar').is(':checked') ? 'Sim' : 'Não',
                $('#operar').is(':checked') ? 'Sim' : 'Não',
                $('#transferir').is(':checked') ? 'Sim' : 'Não',
                $('#estornar').is(':checked') ? 'Sim' : 'Não',
                $('#lancarfechado').is(':checked') ? 'Sim' : 'Não',
                "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverUser'>Remover</button>"
            ] ).draw( false );
        }

        $('#user_id').val('');
        $('#visualizar').attr('checked', false);
        $('#operar').attr('checked', false);
        $('#transferir').attr('checked', false);
        $('#estornar').attr('checked', false);
        $('#lancarfechado').attr('checked', false);
    }
    function carregarUsersErro(){
        tblUser.clear();
        us = JSON.parse($('#users').val());
        for(i=0;i<us.length;i++){
            tblUser.row.add( [
                us[i][0],
                us[i][1],
                us[i][2],
                us[i][3],
                us[i][4],
                us[i][5],
                us[i][6],
                us[i][7]
                ] ).draw( false );
        }
    }
    function addTalao(){
        if(!isInt($('#chequenuminicial').val()) || !isInt($('#chequenumfinal').val())){
            bootbox.alert('Preencha os números inicial e final do talão.');
            return;
        }
        if(parseInt($('#chequenuminicial').val())>=parseInt($('#chequenumfinal').val())){
            bootbox.alert('O número inicial deve ser menor que o final.');
            return;
        }
        var novoini = parseInt($('#chequenuminicial').val());
        var novofim = parseInt($('#chequenumfinal').val());
        var erro = false;
        tblTalao.rows().every( function () {
            var d = this.data();
            var ini = parseInt(d[1]);
            var fim = parseInt(d[2]);
            if((novofim >= ini && novoini <= fim) || (novoini <= fim && novofim >= ini)){
                bootbox.alert('Os números informados jão estão em uso em outro talão.');
                erro = true;
                return;
            }
        } );
        if(erro) return;
        tblTalao.row.add( [
            -1,
            $('#chequenuminicial').val(),
            $('#chequenumfinal').val(),
            $('#chequenuminicial').val(),
            "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverTalao'>Remover</button>"
            ] ).draw( false );
        $('#chequenuminicial').val('');
        $('#chequenumfinal').val('');

    }
    function carregarTalaosErro(){
        tblTalao.clear();
        us = JSON.parse($('#talaos').val());
        for(i=0;i<us.length;i++){
            tblTalao.row.add( [
                us[i][0],
                us[i][1],
                us[i][2],
                us[i][3],
                us[i][4]
                ] ).draw( false );
        }
    }
    setTimeout(function () {
        @if (isset($show))
            desativarInputs();
            var ids = ['#btnRemoverUser',
            '#btnAddUser', '.btnRemover', '#btnAddTalao'];
            desativarInputsEspecificos(ids);
        @endif
        @if ($errors -> any())
            carregarTelefonesErro();
        @endif
    }, $(document).ready());
    $("#layoutbanco_id").on('change', function () {
        var disabled = true;
        searchLayoutBanco($(this).val());
    });
    function searchLayoutBanco (id) {
        disabled = true;
        if(!isEmpty(id)) {
            ajaxGenerator(root + '/api/getLayoutBanco/' + id, 'GET', function (data) {
                if(typeof data === 'array' || typeof data === 'object') {
                    layoutBanco = data;
                    disabled = false;
                } else {
                    bootbox.alert('' + data);
                }
                habilitaCamposBoleto(disabled);
            });
        } else {
            habilitaCamposBoleto(disabled);
        }
    }
    $("#boletoprotesto_baixadevolucao").change(function () {
        checkProtestoOrBaixa();
    });
    $("#boletoemite").on('change', function () {
        validaEmiteBoleto();
    });
    function habilitaCamposBoleto (disabled) {
        $("#tabCadastroBoleto input").each(function () {
            if($(this).context.id != 'boletoemite')
                $(this).prop('disabled', disabled);
        });

        $("#tabCadastroBoleto select").each(function () {
            if($(this).context.id != 'layoutbanco_id')
                $(this).prop('disabled', disabled).trigger('chosen:updated');
        });
        if (selectizeInitialized) {
            var selectize = $("#searchboxcorresp").selectize()[0].selectize;
            if(disabled)
                selectize.disable();
            else
                selectize.enable();
        }

        if(typeof layoutBanco != 'undefined' && (layoutBanco.codigo_banco == 104 || layoutBanco.codigo_banco == 341)){
            var protestoOrbaixa = 0;
            $("#span_protesto_baixadevolucao").show();
        } else {
            var protestoOrbaixa = 1;
            $("#span_protesto_baixadevolucao").hide();
        }
                @isset($Conta)
                protestoOrbaixa = "{{$Conta->boletoprotesto_baixadevolucao}}";
                @endisset
        $('#boletoprotesto_baixadevolucao').val(protestoOrbaixa).trigger('chosen:updated');
        checkProtestoOrBaixa();
    }

    function checkProtestoOrBaixa () {
        if($('#boletoprotesto_baixadevolucao').val() == 0)
            $("label[for='boletodiasprotesto']").text('Baixa/Devolução (dias)');
        else
            $("label[for='boletodiasprotesto']").text('Protestar em (dias)');
    }
    function validaEmiteBoleto () {
        @if(!isset($show))
            $("#layoutbanco_id").prop('disabled', typeof $('#boletoemite:checked').val() == 'undefined').trigger('chosen:updated');
        @endif
    }
</script>
