
@extends('layouts.mainmenu')

@section('content')

  <link href="{{URL::to('plugins/slideMenu/css/base.css')}}" rel="stylesheet">
  <link href="{{URL::to('plugins/slideMenu/css/style.css')}}" rel="stylesheet">
  <style>
  #floating-panel {
        position: absolute;
        top: 10px;
        left: 25%;
        z-index: 5;
        background-color: #fff;
      border: 1px solid #999;
        text-align: center;
        font-family: 'Roboto','sans-serif';
        line-height: 30px;
      padding: 5px 5px 5px 10px;
  }
    .panel {
        margin-bottom: 5px;
    }
    .box-header {
        padding: 0px;
    }
    .col-xs-12 {
        padding-right: 0px;
    }
    .content {
        padding-top: 5px;
    }
  </style>
@include('rastreamento.css')
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Rastreamento</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                              <table width="98%" border="0" align="center" cellpadding="7" cellspacing="2" bgcolor="#f7f7f7"
style="margin-top: 4px;border: solid 1px #ebebeb; border-top: solid 2px #dedede; ">
<tr>
<td height="30">
  <table width="100%" border="0" cellspacing="2" cellpadding="8">
    <tr>
      <td colspan="4">
        <table style="margin-top: 4px;border: solid 1px #ebebeb; border-top: solid 2px #dedede; " cellpadding="0" cellspacing="0" width="100%" >
          <tr>
            <td>
              <ul id="navigation">
              @foreach($dados as $empresa)
                  @if(isset($empresa['setorspaginas']) && count($empresa['setorspaginas'])>0)
                    <li class="sup">
                      <a class="head" href="#SetorPag{{$empresa['id']}}_1">{{$empresa['nome_informal']}} - Setores</a>
                      <ul class="accordion">
                        @for($i=0;$i<count($empresa['setorspaginas']);$i++)
                        <li class="inf">
                          <a class="inf" href="#SetorPag{{$empresa['id']}}_{{$i+1}}">P&aacute;gina {{$i+1}}</a>
                        </li>
                        @endfor
                      </ul>
                    </li>
                  @endif
                  @if(isset($empresa['veiculospaginas']) && count($empresa['veiculospaginas'])>0)
                    <li class="sup">
                      <a class="head" href="#VeiculoPag{{$empresa['id']}}_1">{{$empresa['nome_informal']}} - Veículos</a>
                      <ul  class="accordion">
                        @for($i=0;$i<count($empresa['veiculospaginas']);$i++)
                        <li class="inf">
                          <a class="inf" href="#VeiculoPag{{$empresa['id']}}_{{$i+1}}">P&aacute;gina {{$i+1}}</a>
                        </li>
                        @endfor
                      </ul>
                    </li>
                  @endif
              @endforeach
            </ul>

              <div id="content">
                @foreach($dados as $empresa)
                  @if(isset($empresa['setorspaginas']) && count($empresa['setorspaginas'])>0)
                    <ul id="section" class="section">
                      @for($i=0;$i<count($empresa['setorspaginas']);$i++)
                      <li class="sub" id="SetorPag{{$empresa['id']}}_{{$i+1}}">
                        <h2>{{$empresa['nome_informal']}} - Setores</h2>
                        <table border="0">
                          <tr valign="top">
                            @for($j=0;$j<count($empresa['setorspaginas'][$i]);$j++)

                            <td>
                              @for($k=0;$k<count($empresa['setorspaginas'][$i][$j]);$k++)
                              <table border="0">
                                <tr>
                                  <td>
                                    <img src="{{URL::to('img/default.png')}}" width="19" height="19" style="cursor:pointer" onclick="javascript: clicar(marcadores_estaticos[{{$empresa['setorspaginas'][$i][$j][$k][0]}}],moveToMapa());centralizar_estatico(marcadores_estaticos[{{$empresa['setorspaginas'][$i][$j][$k][0]}}]);"/>
                                  </td>
                                  <td width="220">
                                    <a style="padding-left:10px;" onclick="javascript: clicar(marcadores_estaticos[{{$empresa['setorspaginas'][$i][$j][$k][0]}}], moveToMapa);centralizar_estatico(marcadores_estaticos[{{$empresa['setorspaginas'][$i][$j][$k][0]}}]);">{{$empresa['setorspaginas'][$i][$j][$k][1]}}</a>
                                  </td>
                                </tr>
                              </table>
                              @endfor
                            </td>
                            @endfor
                            <td />
                          </tr>
                        </table>
                        @if($i>0)
                          <a href="#SetorPag{{$empresa['id']}}_{{$i}}" class="prev">
                          <img src="{{URL::to('img/control_rewind.png')}}" title="Anterior" border="0">
                        </a>
                        @endif
                        <a href="#SetorPag{{$i+1}}" class="pag">P&aacute;gina {{$i+1}}</a>
                        @if($i<count($empresa['setorspaginas'])-1)
                          <a href="#SetorPag{{$empresa['id']}}_{{$i+2}}" class="next">
                          <img src="{{URL::to('img/control_fastforward.png')}}" border="0" title="Pr&oacute;ximo">
                          </a>
                        @endif
                      </li>
                      @endfor
                    </ul>
                @endif
                @endforeach
                @foreach($dados as $empresa)
                  @if(isset($empresa['veiculospaginas']) && count($empresa['veiculospaginas'])>0)
                    <ul id="section" class="section">
                      @for($i=0;$i<count($empresa['veiculospaginas']);$i++)
                      <li class="sub" id="VeiculoPag{{$empresa['id']}}_{{$i+1}}">
                        <h2>{{$empresa['nome_informal']}} - Veículos</h2>
                        <table border="0">
                          <tr valign="top">
                            @for($j=0;$j<count($empresa['veiculospaginas'][$i]);$j++)

                            <td>
                              @for($k=0;$k<count($empresa['veiculospaginas'][$i][$j]);$k++)
                              <table border="0">
                                <tr>
                                  <td>
                                    <img src="{{URL::to("img/".$empresa['veiculospaginas'][$i][$j][$k][2]."_90.png")}}" width="20" height="20" style="cursor:pointer" onclick="javascript: muda_monitorado({{$empresa['veiculospaginas'][$i][$j][$k][0]}});"/>
                                  </td>
                                  <td width="220">
                                    <a style="padding-left:10px;" onclick="javascript: muda_monitorado({{$empresa['veiculospaginas'][$i][$j][$k][0]}});">{{$empresa['veiculospaginas'][$i][$j][$k][1]}}</a>
                                  </td>
                                </tr>
                              </table>
                              @endfor
                            </td>
                            @endfor
                            <td />
                          </tr>
                        </table>
                        @if($i>0)
                          <a href="#VeiculoPag{{$empresa['id']}}_{{$i}}" class="prev">
                          <img src="{{URL::to('img/control_rewind.png')}}" title="Anterior" border="0">
                        </a>
                        @endif
                        <a href="#VeiculoPag{{$i+1}}" class="pag">P&aacute;gina {{$i+1}}</a>
                        @if($i<count($empresa['veiculospaginas'])-1)
                          <a href="#VeiculoPag{{$empresa['id']}}_{{$i+2}}" class="next">
                          <img src="{{URL::to('img/control_fastforward.png')}}" border="0" title="Pr&oacute;ximo">
                          </a>
                        @endif
                      </li>
                      @endfor
                    </ul>
                @endif
                @endforeach

              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</td>
</tr>
</table>

                            </div>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->
                </div><!-- /.col -->
            </div><!-- /.row -->

        </div><!-- /.content-wrapper -->
    </div>
    <div id="divMapa"  style="padding-bottom:5px;">
        <div class="row">
            <div class="col-xs-12">
              <div id="mapas2">
                <div id="map_add1" width="100%" height="420px"></div>
              </div>
            </div>
        </div>
    </div>
    <div id="divCadastro1">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="panel panel-default">
                        <div class="panel-body">
                            <div class="col-md-12">
                                <div id="tabFiltro" class="col-md-12" style="padding-left: 5px; padding-right: 5px;">

                                    <div id="showDivCerca">
                                        <table border="0" align="right" width="100%" height="100%">
                                            <tbody>
                                                <tr>
                                                    <td id="tdControle_animacao" width="25%" nowrap=""></td>
                                                    <td id="tdShowInfoAnimacao" width="50%" style="width: 50%; padding-left: 60px;"><div id="showInfoAnimacao"></div></td>
                                                    <td id="tdShowDivCerca" align="right" width="25%">
                                                        <table border="0" align="right" width="100%" height="100%">
                                                            <tbody>
                                                                <tr>
                                                                    <td align="right" width="90%" style="padding-right:10px;"><b>EXIBIR CERCA ELETRÔNICA: </b></td>
                                                                    <td align="right">
                                                                        <select name="showCerca" id="selCerca" onchange="selectedCerca(this.value);">
                                                                            <option value="0" id="id0" selected="selected">Nenhuma</option>
                                                                        </select>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <script>

    var geocoder;
    var c_host = '{{url("/")}}';
    var c_caminho = "/";
    var empresa_id = 0;
    var Demo = {
        map: null,
        infoWindow: null
    };
    </script>
<script src="{{URL::to('plugins/slideMenu/js/jquery.hoveraccordion.js')}}"></script>
<script src="{{URL::to('plugins/slideMenu/js/jquery.localscroll-min.js')}}"></script>
<script src="{{URL::to('plugins/slideMenu/js/jquery.scrollTo-min.js')}}"></script>
<script src="{{URL::to('plugins/slideMenu/js/menu.js')}}"></script>
@include('rastreamento.config_map_js')

<script>

function initMap() {
        geocoder = new google.maps.Geocoder();

      }
</script>

<script src="https://maps.googleapis.com/maps/api/js?key={{$maps->keygooglemaps}}&callback=initMap"></script>
<script src="{{URL::to('js/ajax.js')}}"></script>
<script src="{{URL::to('js/jquery.progressbar.js')}}"></script>
<script src="{{URL::to('js/jquery.progressbar.min.js')}}"></script>
<script src="{{URL::to('js/funcoes_maps.js')}}"></script>
<script src="{{URL::to('js/funcoes_monitoracao.js')}}"></script>
<script src="{{URL::to('js/funcoes_cerca_eletronica.js')}}"></script>
<script src="{{URL::to('js/functions.js')}}"></script>


<script>
$(document).ready(function($) {
    Demo.closeInfoWindow = function() {
        Demo.infoWindow.close();
        if(id_monitorado[0] != null){
            id_monitorado[0] = 0;
        }
    };
    Demo.openInfoWindow = function(marker, content, tipo, dados) {
        Demo.infoWindow.setContent(content);
        if(tipo == c_dinamico)
        {
            var handle = function() {
                google.maps.event.clearListeners(handle);
                if(id_monitorado[0] != null){
                    id_monitorado[0] = 0;
                }
            };
            google.maps.event.addListener(Demo.infoWindow, 'closeclick', handle);
            id_monitorado[0] = dados.id;
        } else {
            id_monitorado[0] = -1;
        }
        Demo.infoWindow.open(Demo.map, marker);
    };
    Demo.infoWindow = new google.maps.InfoWindow();
    init();
    $('.accordion').hide();
});
$(".sup").click(function(){
  $('.accordion').hide();
  $(this).find('ul').slideToggle();
});
$(".inf").click(function (e) {
  //  e.preventDefault();     // stop the default action if u need
    var url = e.target.href.split('_');
    if(url.length>1){
        var empresa = (url[url.length-2]);
        empresa = empresa.substr(empresa.length - 1)
        if(empresa != empresa_id){
            showDivCerca(empresa);
        }
        empresa_id = empresa;
    }

    e.stopPropagation();
});
$(".sup").click(function (e) {
  //  e.preventDefault();     // stop the default action if u need
    var url = e.target.href.split('_');
    if(url.length>1){
        var empresa = (url[url.length-2]);
        empresa = empresa.substr(empresa.length - 1)
        if(empresa != empresa_id){
            showDivCerca(empresa);
        }
        empresa_id = empresa;
    }

});
</script>
  @endsection
