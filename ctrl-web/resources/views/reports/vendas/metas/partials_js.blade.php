<script src="{{asset('plugins/chartjs.min.js')}}"></script>
<script type="text/javascript">
    $(document).ready(function(){
        tblRuas = $("#tblRuas").DataTable({
            "language": {
                "url": urlDataTable
            },
            "processing": false,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": false,
            "bInfo": false,
            "bAutoWidth": false,
            "destroy": true
        });
    });
    objbairros = [];
    iconsLegend = {
        blue:{
            name: "< 90%",
            icon: root + '/img/marker_red.png'
        },
        green:{
            name: ">= 90% < 95%",
            icon: root + '/img/marker_orange.png'
        },
        yellow:{
            name:">= 95% < 100%",
            icon: root + '/img/marker_yellow.png'
        },
        orange:{
            name:">= 100% < 110%",
            icon: root + '/img/marker_green.png'
        },
        red:{
            name:'> 110%',
            icon: root + '/img/marker_blue.png'
        }
    };

    $("#grupo_id").change(function(){
        var gru = $(this).val();
        var reg = $("#regiao_id").val();
        getEmpresas( gru, reg );
    });

    $("#regiao_id").change(function(){
        var gru = $("#grupo_id").val();
        var reg = $(this).val();
        getEmpresas(gru, reg);
    });

    $("#btnFiltro").click(function(){
        clearAllMarkers();
        tblRuas.clear().draw();
        $("#tableRuas").addClass('hidden');
        $("#chart-container").html('');
        $("#chart-container").html('<canvas id="chartBairros" style="height:350px;width:450px;"></canvas>');
        grupo_id = $("#grupo_id").val();
        regional = $("#regiao_id").val();
        empresa = $("#empresa_id").val();
        produto = $("#produto").val();
        ano = $("#ano").val();
        mes = $("#mes").val();
        var url = root + `/getmetasxvendas?grupo=${grupo_id}&regiao=${regional}&empresa=${empresa}&produto=${produto}`+
            `&ano=${ano}&mes=${mes}&hub=:hub`;
        if(isEmpty(grupo_id) || isEmpty(produto)){
            bootbox.alert('Por favor, selecione um grupo e um produto!');
        }else{
            getInfo(url,function(){getBairrosMeta(url);});
        }
    });

    $("#btnLimpar").click(function(){
        limparCampo();
        goCenter();
    });

    function setLatLgtEmpresa(){
        longitude = parseFloat("{{Session::get('empresa_padrao')->longitude}}");
        latitude = parseFloat("{{Session::get('empresa_padrao')->latitude}}");
        if(isEmpty(latitude) || isEmpty(longitude))
            bootbox.alert("Não foi possível localizar a latitude e longitude da empresa.");
    }

    function getInfo(url,callback){
        var urlb = url.replace(':hub',2);
        var url = url.replace(':hub',1);
        ajaxGenerator(url,'GET',
            function(data){
                clearAllMarkers();
                var html = "<div style='font-size:11.5px;' id='info-window' opened='true'><table class='table table-hover table-responsive table-condensed tabelinha'>";
                html += "<thead><tr><th>Setor</th><th>Produto</th><th>Quant. (Meta)</th><th>Quant. (Venda)</th></tr></thead>";
                var content = '';
                var position;
                var pathImage;
                if(typeof data == 'object') {
                    var unique = data.unique('latlong');
                    var typeMarker;
                    $.each(unique, function (index, uniqueEl) {
                        let equalsElements = data.where("latlong", "===", uniqueEl.latlong);
                        let totalMeta = 0;
                        let totalVenda = 0;
                        position = {
                            lat: parseFloat(uniqueEl.latitude),
                            lng: parseFloat(uniqueEl.longitude)
                        };
                        $.each(equalsElements, function (i, value) {
                            totalVenda += parseInt(value.quant) ? parseInt(value.quant) : 0;
                            totalMeta += parseInt(value.meta) ? parseInt(value.meta) : 0;
                            content += `<tr onclick="clickTabelinha(${value.setor_id})"><td>${value.setor}</td><td>${value.produto}</td><td>${value.meta}</td><td>${value.quant}</td>`;
                        });
                        let totalPercent = parseFloat(((totalVenda / (totalMeta > 0 ? totalMeta : 1)) * 100).toFixed(2));
                        console.log(totalPercent, totalVenda, totalMeta);
                        if (totalPercent < 90) {
                            typeMarker = 'red';
                        } else if(totalPercent >= 90 && totalPercent < 95) {
                            typeMarker = 'orange';
                        } else if(totalPercent >= 95 && totalPercent < 100) {
                            typeMarker = 'yellow';
                        } else if(totalPercent >= 100 && totalPercent < 110) {
                            typeMarker = 'green';
                        } else {
                            typeMarker = 'blue';
                        }
                        pathImage = '/img/marker_' + typeMarker + '.png';
                        let footer = "<tfoot class='negrito'><tr><td colspan='2'>Total:</td><td>" + totalMeta + "</td><td>" + totalVenda + "</td></tr></tfoot>";
                        let contentInfo = `${html}<tbody>${content}</tbody>${footer}</table><div>${uniqueEl.uf}, ${uniqueEl.cidade}, ${uniqueEl.mes}</div></div>`;
                        addMarker(position, pathImage, 50, "Clique para ver detalhes!", contentInfo,
                            null,
                            function(){
                                if (!$("#empresa_id").isEmpty())
                                    clickMapa();
                            });
                        content = '';
                    });
                }
            });
        let empresa_id = $("#empresa_id").val();
        let $empresaContainer = $("#empresa-container");
        let $container = $("#mapa-container");
        $container.removeClass('col-sm-7 col-sm-12');
        if(typeof callback === "function" && !isEmpty(empresa_id)){
            $container.addClass('col-sm-7');
            $empresaContainer.removeClass('hidden');
            callback(urlb,empresa_id);
        }else{
            goCenter();
            $container.addClass('col-sm-12');
            $empresaContainer.addClass('hidden');
        }
    }

    var getBairrosMeta = function(url, empresa){
        url_bairros = url;
        url = url.replace(':hub',2);
        ajaxGenerator(url,'GET',
            function(data){
                gerarInfoChart(data,true);
            },null,null,true);
    }

    var randomColor = function(){
        var letras = '0123456789ABCDEF';
        var color = '#';
        for(var i = 0; i<6;i++){
            color += letras[Math.floor(Math.random() * 16)];
        }
        return color;
    };

    var gerarChart = function(labels, info, colors){
        $("#chart-container").html('');
        $("#chart-container").html('<canvas id="chartBairros" style="height:350px;width:450px;"></canvas>');
        var informacoes = [{backgroundColor: colors, data: info}];
        var chart = document.getElementById('chartBairros');
        myChart = new Chart(chart,{
            type: 'pie',
            data:{
                labels: labels,
                datasets: informacoes
            },
            options:{
                title:{
                    text: "Venda por Bairro"
                }
            }
        });
        chart.onclick = function(evt) {
            var activePoints = myChart.getElementsAtEvent(evt);
            if (activePoints[0]) {
                var chartData = activePoints[0]['_chart'].config.data;
                var idx = activePoints[0]['_index'];

                var label = chartData.labels[idx];
                var value = chartData.datasets[0].data[idx];
                var bairro = label.substring(1,label.length).replace(' ','_');
                var url = url_bairros + "&bairro="+bairro;
                render_table(url);
            }
        }
        goCenter();
    };

    var render_table = function(url){
        var url = url.replace(':hub',3);
        var setor_id = $("#hidden_setor").val();
        url += `&setor=${setor_id}`;
        tblRuas.clear().draw();
        ajaxGenerator(url,'GET',function(data){
            for(var i = 0;i < data.length; i++){
                tblRuas.row.add([
                    data[i].rua,
                    data[i].quantidade
                ]);
            }
            tblRuas.draw();
            $("#tableRuas").removeClass( 'hidden' );
        }, null, null, true );
    };

    var getEmpresas = function(gru, reg){
        $("#empresa_id").empty().trigger('chosen:updated');
        var url  = root + `/api/empresasbyregional?regional=${reg}&grupo=${gru}`;
        var html = "<option value=''>Selecione</option>";
        ajaxGenerator( url,'GET',function( data ) {
            if(typeof data == 'object'){
                $.each( data, function( key,val ) {
                    html += "<option value='"+key+"'>"+val+"</option>";
                });
                $("#empresa_id").append(html).trigger('chosen:updated');
            }
        },null,null,true);
    };

    var limparCampo = function(){
        var inputs = ["grupo_id","regiao_id","produto","ano","mes"];
        for(var i=0;i<inputs.length;i++){
            $("#"+inputs[i]).children( 'option:enabled' ).eq(0).prop( 'selected',true ).trigger( 'chosen:updated' );
        }
        $("#empresa_id").empty().trigger( 'chosen:updated' );
        tblRuas.clear().draw();
        $("#tableRuas").addClass( 'hidden' );
        $("#chart-container").html( '' );
        $("#chart-container").html( '<canvas id="chartBairros" style="height:350px;width:450px;"></canvas>' );
        clearAllMarkers();
        if( !$("#empresa-container").hasClass( 'hidden' ) ){
            $("#mapa-container").removeClass( 'col-sm-7' );
            $("#mapa-container").addClass( 'col-sm-12' );
            $("#empresa-container").addClass( 'hidden' );
        }
    };

    var clickTabelinha = function( setor_id, cidade ) {
        $("#hidden_setor").val( setor_id );
        var gru = grupo_id;
        var reg = regional;
        var emp = empresa;
        var pro = produto;
        var yea = ano;
        var mon = mes;
        var url= root + `/getmetasxvendas?grupo=${gru}&regiao=${reg}&empresa=${emp}` +
            `&produto=${produto}&setor=${setor_id}&ano=${yea}&mes=${mon}&hub=4`;

        ajaxGenerator( url, 'GET', function( data ) {
            gerarInfoChart( data );
        }, null, null, true );
    };

    var setBairros = function(labels, info, colors){
        objbairros = {legenda: labels, quantidades: info, cores: colors};
    };

    var getBairros = function(which){
        return objbairros[which];
    };

    var clickMapa = function() {
        console.log('alo');
        $("#hidden_setor").val('');
        gerarInfoChart( null, false, true )
    };

    var gerarInfoChart = function( data,set = false,padrao = false ) {
        var labels = [];
        var info   = [];
        var colors = [];
        if(!padrao){
            for(var i = 0; i < data.length; i++){
                labels.push(` ${data[i].bairro}`);
                info.push(data[i].quant);
                colors.push(randomColor());
            }
        }else{
            labels = getBairros( 'legenda' );
            info   = getBairros( 'quantidades' );
            colors = getBairros( 'cores' );
        }
        gerarChart( labels, info, colors );
        if( set ) setBairros(labels, info, colors);
    };

</script>
<script type="text/javascript" src="{{asset('js/maps.js')}}"></script>
<script src="https://maps.googleapis.com/maps/api/js?key={{$keygooglemaps}}&callback=initMap" async defer></script>

