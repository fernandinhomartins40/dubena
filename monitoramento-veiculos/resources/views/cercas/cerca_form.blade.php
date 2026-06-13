@extends('layouts.mainmenu')
@section('content')
  <div id="mainContent" class="content">
    <div id="divCadastro" class="row">
      <div class="col-md-12">
        <!-- Custom Tabs -->
        <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
        @if(isset($Cerca))
          {{ Form::model($Cerca, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('cerca.update', $Cerca->id))) }}
        @else
          {{ Form::open(['id'=>'fmCadastro', 'route' => 'cerca.store', 'class' => 'form-horizontal', 'files' => true]) }}
        @endif
        <ul>
          <div class="panel panel-default">
            <div class="panel-heading">
              <h3 class="panel-title">Cerca Eletrônica</h3>
            </div>
            <div class="nav-tabs-custom">
              <ul class="nav nav-tabs">
                <li><a href="#tab_1" data-toggle="tab">Dados da Cerca</a></li>
                <li class="active"><a href="#tab_2" data-toggle="tab">Polígono</a></li>
              </ul>
              <div class="tab-content">
                <div class="tab-pane" id="tab_1">
                  <!-- form start -->
                  <div class="row">
                    <div id="tabCadastro" class="col-md-10">
                      <div class="box-body">
                        <div class="form-group crud_space">
                          {!! Form::hidden('poligono',null,['id'=>'poligono']) !!}
                          {!! Form::label('setor_id', 'Setor:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                          <div class="col-sm-6">
                            {!! Form::select('setor_id', $setors, null, ['class' => 'form-control selectDisableSearch', 'onchange'=>'centralizaMapaSetor();']) !!}
                          </div>
                        </div>
                        <div class="form-group crud_space">
                          {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                          <div class="col-sm-6">
                            {!! Form::text('descricao',null,['class'=>'form-control input-sm']) !!}
                          </div>
                        </div>
                        <div class="form-group crud_space">
                          {!! Form::label('cor', 'Cor:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                            <div class="col-sm-4">
                                <input class="form-control input-sm" id="cor" name="cor" type="color" value="{{isset($Cerca)?$Cerca->cor:''}}">
                            </div>
                        </div>
                        <div class="form-group crud_space">
                                {!! Form::label('ativo', 'Ativo:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                <div class="col-sm-9">
                                        <!--
                                        {!! Form::text('ativo',null,['class'=>'form-control input-sm']) !!}
                                        {!! Form::checkbox('ativo',null,['class'=>'form-control input-sm']) !!}
                                        -->

                                        {{ Form::checkbox('ativo') }}

                                </div>
                        </div>
                        
                      </div> <!-- box-body  -->
                    </div> <!-- tab-cadastro -->
                  </div> <!-- row -->
                </div><!-- /.tab-pane 1 -->
                <div class="tab-pane active" id="tab_2">
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger" id="delete-button">Apagar polígono</button>
                    </div>
                    <div class="form-group crud_space">
                        {!! Form::label('cercas', 'Mostrar outras cercas:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-3">
                            <select id="cercas" class="form-control selectDisableSearch" onchange="selectedCerca(this.value);">
                                @foreach($cercas as $id=>$value)
                                  <option value="{{Session::get('empresa_padrao')->id}}|{{$id}}">{{$value}}</option>
                                @endforeach
                                <option value="{{Session::get('empresa_padrao')->id}}|0">Todas</option>
                            </select>
                        </div>
                    </div>

                    <div id="map-canvas" style="height: 600px; width: auto;">
                    </div>
                    <div id="info" style="position: absolute; font-family: Arial; font-size: 14px;">
                    </div>
                </div>
              </div><!-- /.tab-pane -->
              <div class="box-footer">
                <div class="col-md-4">
                  {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                  <a href='{{url("cerca")}}' type="button" class="btn btn-nw-geral">Voltar</a>
                </div>
              </div>
            </div>
            {!! Form::close() !!}
    </div><!-- /.col -->
  </div>
</div>
</div>


<script src="https://maps.googleapis.com/maps/api/js?key={{$keygooglemaps}}&libraries=drawing,geometry"></script>
<script type="text/javascript">
var bermudaTriangle;
var map;
var drawingManager;
var selectedShape;
var root = '{{url("/")}}';

function initializeDrawer() {
        drawingManager = new google.maps.drawing.DrawingManager({
          drawingMode: google.maps.drawing.OverlayType.POLYGON,
          drawingControl: true,
          drawingControlOptions: {
            position: google.maps.ControlPosition.TOP_CENTER,
            drawingModes: ['polygon']
          },
          markerOptions: {icon: 'https://developers.google.com/maps/documentation/javascript/examples/full/images/beachflag.png'},
          circleOptions: {
            fillColor: '{{isset($Cerca)?$Cerca->cor:"#000000"}}',
            strokeColor: '{{isset($Cerca)?$Cerca->cor:"#000000"}}',
            fillOpacity: 0.3,
            strokeWeight: 2,
            clickable: false,
            editable: {{isset($show)?"false":"true"}},
            draggable: {{isset($show)?"false":"true"}},
            zIndex: 1
          },
          polygonOptions: {
            fillColor: '{{isset($Cerca)?$Cerca->cor:"#000000"}}',
            strokeColor: '{{isset($Cerca)?$Cerca->cor:"#000000"}}',
            fillOpacity: 0.3,
            strokeWeight: 2,
            clickable: false,
            editable: {{isset($show)?"false":"true"}},
            draggable: {{isset($show)?"false":"true"}},
            zIndex: 1
          },
          rectangleOptions: {
            fillColor: '{{isset($Cerca)?$Cerca->cor:"#000000"}}',
            strokeColor: '{{isset($Cerca)?$Cerca->cor:"#000000"}}',
            fillOpacity: 0.3,
            strokeWeight: 2,
            clickable: false,
            editable: {{isset($show)?"false":"true"}},
            draggable: {{isset($show)?"false":"true"}},
            zIndex: 1
          }
        });
        drawingManager.setMap(map);
        google.maps.event.addListener(drawingManager, 'overlaycomplete', function(e) {
            drawingManager.setOptions({
                drawingControl: false
            });
            drawingManager.setDrawingMode(null);
            var newShape = e.overlay;
            newShape.type = e.type;
            google.maps.event.addListener(newShape, 'click', function() {
              setSelection(newShape);
            });
            setSelection(newShape);
        });
        @if($errors->any())
            drawingManager.setOptions({
                drawingControl: false
            });
            var poliCoords = [];
            var coordserro = JSON.parse($('#poligono').val());
            
            for (var i = 0; i < coordserro.length; i++) {
                ponto = new google.maps.LatLng(coordserro[i][0], coordserro[i][1]);
                poliCoords.push(ponto);
            }
            cercaPoligono = new google.maps.Polygon({
                paths: poliCoords,
                draggable: true,
                editable: true,
                strokeColor: '{{isset($Cerca)?$Cerca->cor:"#000000"}}',
                strokeOpacity: 0.8,
                strokeWeight: 2,
                fillColor: '{{isset($Cerca)?$Cerca->cor:"#000000"}}',
                fillOpacity: 0.3
            });
            cercaPoligono.setMap(map);
            setSelection(cercaPoligono);
            drawingManager.setDrawingMode(null);
        @else
            @if(isset($Cerca))
               drawingManager.setOptions({
                    drawingControl: false
                });
                var poliCoords = [
                @foreach($Cerca->coordenadas as $coord)
                    new google.maps.LatLng({{$coord->latitude}}, {{$coord->longitude}}),
                @endforeach
                ];
                cercaPoligono = new google.maps.Polygon({
                    paths: poliCoords,
                    draggable: {{isset($show)?"false":"true"}},
                    editable: {{isset($show)?"false":"true"}},
                    strokeColor: '{{$Cerca->cor}}',
                    strokeOpacity: 0.8,
                    strokeWeight: 2,
                    fillColor: '{{$Cerca->cor}}',
                    fillOpacity: 0.3
                });
                cercaPoligono.setMap(map);
                setSelection(cercaPoligono);
                drawingManager.setDrawingMode(null);
            
            @endif
        @endif 
        google.maps.event.addListener(drawingManager, 'drawingmode_changed', clearSelection);
        //google.maps.event.addListener(map, 'click', clearSelection);
        google.maps.event.addDomListener(document.getElementById('delete-button'), 'click', deleteSelectedShape);
}

</script>


<script type="text/javascript">

var arrayPoligonos = [];
var lineWeight = 2;
var lineOpacity = .8;
var fillOpacity = .2;

jQuery(document).ready(function ($) {
    var myLatLng = new google.maps.LatLng({{$latlng["latitude"]}}, {{$latlng["longitude"]}});
    var mapOptions = {
        zoom: 14,
        center: myLatLng,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        scrollwheel: false,
        disableDefaultUI: false
    };

    map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);

    $(document).on('shown.bs.tab', 'a[data-toggle="tab"]', function (e) {
         var target = $(e.target).attr("href") // activated tab
         if(target=='#tab_2'){
            //google.maps.event.trigger(map, 'resize');
         }
    })
    initializeDrawer();
    
    setTimeout(function () {
        $('.nav-tabs a:first').tab('show');
    }, 1000);
    
    $("#fmCadastro").on("submit", function () {
        if(!selectedShape){
            bootbox.alert('Para gravar, é necessário criar o polígono da cerca.');
            return false;
        }
        var len = selectedShape.getPath().getLength();
        var coords = [];
        for (var i = 0; i < len; i++) {
            coord = [];
            coord.push(selectedShape.getPath().getAt(i).lat());
            coord.push(selectedShape.getPath().getAt(i).lng());
            coords.push(coord);
        }
        $('#poligono').val(JSON.stringify(coords));
    });
});

setTimeout(function () {
  @if (isset($show))
  desativarInputs();
  $('#delete-button').prop('disabled', true);
  @endif
}, $(document).ready());

    function clearSelection() {
        if (selectedShape) {
          selectedShape.setEditable(false);
          selectedShape = null;
        }
    }

    function setSelection(shape) {
        clearSelection();
        selectedShape = shape;
        shape.setEditable({{isset($show)?"false":"true"}});
        shape.setDraggable({{isset($show)?"false":"true"}});
    }

    function deleteSelectedShape() {
        if (selectedShape) {
          selectedShape.setMap(null);
        // To show:
         drawingManager.setOptions({
           drawingControl: true
         });
        }
    }
    function centralizaMapaSetor(){
	$.ajax({
            url: root+'/api/getCoordenadasSetor',
            type: 'GET',
            dataType: 'json',
            data: {
                    setor_id: $('#setor_id').val(),
            },
            error: function() {
                bootbox.alert('Erro ao centralizar o mapa.');
            },
            success: function(res) {
                var myLatLng = new google.maps.LatLng(res.latitude, res.longitude);
                map.setCenter(myLatLng);
            }
	});
        
    }
    function selectedCerca(cerca_id){
        clearCercasPolygonsPolylines();
        if(cerca_id  != '-1|-1' && cerca_id.split('|')[1] != '-1'){
            mostraCerca(cerca_id);
        }
    }
    function mostraCerca(cerca_id){
        $.ajax({
            url: root+'/api/getCercaPoligono',
            type: 'GET',
            dataType: 'json',
            data: {
                    cerca_id: cerca_id,
                    @if(isset($Cerca))
                    cerca_id_except: {{$Cerca->id}},
                    @endif
            },
            error: function() {
            },
            success: function(res) {
                var markers = res.data;
                arrayPoligonos = Array();
                for(i=0; i<markers.length; i++) {
                    var pontos = new Array();
                    var poligono = markers[i].poligono;
                    for(j=0; j<poligono.length; j++) {
                        pontos.push(new google.maps.LatLng(poligono[j].latitude, poligono[j].longitude));
                    }
                    var polygon = new google.maps.Polygon({
                        paths: pontos,
                        strokeColor: markers[i].cor,
                        strokeOpacity: lineOpacity,
                        strokeWeight: lineWeight,
                        fillColor: markers[i].cor,
                        fillOpacity: fillOpacity
                    });
                    arrayPoligonos.push(polygon);
                    polygon.setMap(map);
                }
            }
        });
    }
    function clearCercasPolygonsPolylines(){
        if(arrayPoligonos.length > 0)
        {
            for(x = 0; x < arrayPoligonos.length; x++)
            {
                arrayPoligonos[x].setMap(null);
            }
        }
    }

</script>

@endsection
