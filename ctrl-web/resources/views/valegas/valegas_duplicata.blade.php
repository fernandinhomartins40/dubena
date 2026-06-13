<HTML>
  <HEAD>
    <title>{{ $titulo }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <STYLE>

    .t0{width: 549px;font: bold 11px 'Tahoma';color: #404040;}
    .t1{width: 397px;font: bold 13px 'Helvetica';}
    .t2{width: 411px;margin-left: 45px;font: 13px 'Helvetica';}
    .t3{width: 468px;margin-left: 41px;margin-top: 8px;font: 13px 'Helvetica';}


    .tr0{height: 41px;}
    .tr1{height: 25px;}
    .tr2{height: 24px;}
    .tr3{height: 17px;}
    .tr4{height: 4px;}
    .tr5{height: 3px;}

    .ft0{font: bold 16px 'Tahoma';color: #404040;line-height: 19px;}
    .ft1{font: 1px 'Helvetica';line-height: 1px;}
    .ft2{font: bold 11px 'Tahoma';color: #404040;line-height: 13px;max-height}
    .ft3{font: 1px 'Helvetica';line-height: 4px;}
    .ft4{font: 1px 'Helvetica';line-height: 3px;}
    .ft5{font: bold 15px 'Tahoma';color: #404040;line-height: 18px;}
    .ft8{font: bold 13px 'Helvetica';line-height: 16px;}
    .ft9{font: 1px 'Helvetica';line-height: 6px;}
    .ft10{font: 1px 'Helvetica';line-height: 7px;}
    .ft11{font: bold 12px 'Helvetica';line-height: 15px;}
    .ft12{font: 8px 'Helvetica';line-height: 10px;}
    .ft13{font: 13px 'Helvetica';line-height: 16px;}

    .bordaPadrao{border: 1px solid black}
    .bordaDireita{border-right: 1px solid black}
    .bordaEsquerda{border-left: 1px solid black}
    .bordaBaicho{border-bottom:  1px solid black}
    .bordaEmCima{border-top:  1px solid black}
    .bordaBaixoSem{border-bottom: none !important}
    .bordaCimaSem{border-top: none !important}

    .margTop_5{margin-top: 5px}

    table.bordasimples {border-collapse: collapse;}
    table.bordasimples tr td {border:1px solid black;}
    
    .valor{width:105px;}
    .num{width:45px;}
    .notafisca{width:75px;}
    .serie{width:55px;}
    .parcelas{font: bold 12px 'Tahoma';color: #404040;}

    </STYLE>
  </HEAD>

  <BODY>

    <div style="width:90%; margin-left:5%">

      <div class="bordaPadrao" style="height:9%">
        <div align="center" class="bordaDireita tr0 td0" style="width: 50%; float:left; height:9%">
          <div class="ft0" style="margin-top:5px">NACIONAL GAS</div>
          <div class="ft0">------------------------------------------------</div>
          <div class="ft5">{{ $empresa->razao_social }}</div>
          <div class="ft2" style="margin-top:5px">Fone: {{$empresa->telefone1}} - {{ $empresa->cidade->descricao }}</div>
        </div>

        <div class="tr0">
          <div style="margin-left: 1%">
            <div class="ft2">{{$empresa->bairro->descricao}} - {{$empresa->rua->descricao}}, {{$empresa->numero}} - {{$empresa->cep}}</div>
            <br>
            <div style="border-top:1px solid black; width:50.5%; float:right"></div>
            <div style="margin-top:5px" class="ft2">CNPJ: {{$empresa->cnpj}} - Insc.: {{$empresa->inscricao_estadual}}</div>
            <div style="margin-top:8px" class="ft2">Data Emissão: {{requestDataOracleSemHora($valegasvenda->datavenda)}}</div>
          </div>
        </div>
      </div>

      <!--------------------->

      <div class="bordaBaicho bordaEsquerda bordaDireita" style="height:33%">

        <div class="bordaDireita" style="height:25.5%; width:5%; float:left"></div>

        <div style="margin-left:7%; margin-top:1%; width:100%;">

          <div class="bordaEmCima bordaEsquerda ft12" style="width:20%; float:right; height:12.6%">
            Para uso da Instituição Financeira
          </div>

          <table class="ft8 bordasimples" style="width:78%;">
            <tr align="center">
              <th class="bordaDireita bordaEsquerda bordaEmCima valor">Valor</th>
              <th class="bordaDireita bordaEmCima num">N°</th>
              <th class="bordaDireita bordaEsquerda bordaEmCima notafisca">NF</th>
              <th class="bordaDireita bordaEmCima serie">Série</th>
            </tr>
            <tr align="center">
              <td>R$ {{ number_format($valegasvenda->valortotal,2) }}</td>
              <td>{{ $valegasvenda->id }}</td>
              <td> </td>
              <td> </td>
            </tr>
          </table>

          <table class="ft8 bordasimples parcelas" style="width:20%; margin-top: 8px">
            <tr align="center">
              <th style="color:black" class="bordaEsquerda bordaDireita bordaEmCima" colspan="{{count($condi) > 1 ? count($condi) : 0}}">Vencimento</th>
            </tr>
            <tr align="center">
            @for ($i = 0; $i < count($condi); $i++)
              <td style="color:black" class="bordaBaixoSem">{{$condi[$i]["datavencimento"]}}</td>
            @endfor
            </tr>
            <tr style="width:55px">
            @for ($i = 0; $i < count($condi); $i++)
              <td style="color:black;padding:2.5px" align="center" class="bordaCimaSem">R$ {{ number_format($condi[$i]["valor"],2) }}</td>
            @endfor
            </tr>
          </table>
        </div>

        <!---------------------->

        <div class="bordaEmCima bordaEsquerda" style="margin-top:1%; width:96.7%; margin-left:7%; padding-left:-4%; height:12%">
          <div class="ft13">
            <div style="float:left; width:70%">
              <div>Nome: {{ $valegasvenda->Cliente->nome }}</div>
              <div class="margTop_5">Rua: <span style="color:black">{{ $valegasvenda->cliente->rua->descricao }}</span>, {{ $valegasvenda->Cliente->numero }} - {{ $valegasvenda->Cliente->bairro->descricao }}</div>
              <div class="margTop_5">Município: {{ $valegasvenda->Cliente->cidade->descricao }}</div>
              <div class="margTop_5" style="color:black">Praça Pagto: {{ $valegasvenda->Cliente->cidade->descricao }} - {{$valegasvenda->Cliente->uf}}</div>
              <div class="margTop_5" style="color:black">CNPJ: {{ $valegasvenda->Cliente->cnpj != "" ? $valegasvenda->Cliente->cnpj : " "}}</div>
            </div>

            <div style="">
              <div>Código: <span style="color:black">{{$valegasvenda->cliente->id}}</span></div>
              <div class="margTop_5">Estado: {{ $valegasvenda->Cliente->uf }}</div>
              <div class="margTop_5">CEP: {{ $valegasvenda->Cliente->cep }}</div>
              <div class="margTop_5">Inscr.Est.: {{ $valegasvenda->Cliente->inscricao_estadual }}</div>
            </div>

          </div>
        </div>

        <!-------------->

        <div align="center" class="ft13 bordaEmCima">
          Reconheço(emos)a exatidão desta duplicata de VENDA MERCANTIL, na importâcia acima
          que pagarei(emos) a {{$empresa->razao_social}}, ou á sua ordem na praça e
          vencimentos acima indicados.
        </div>

        <!-------------->

        <div class="bordaEmCima">
          <div align="center">

            <div style="float:left; width:32%">
              <div class="ft13" style="margin-top:2%">Em___/___/_____</div>
              <div style="margin-top:2px">Data do Aceite</div>
            </div>

            <div style="float:left; width:32%">
              <div class="bordaBaicho" style="margin-top: 1%; margin-left:6%; width:83%">&nbsp;</div>
              <div style="margin-top:2px">Assinatura do Sacado</div>
            </div>

            <div style="float:left; width:32%">
              <div style="margin-top: 1%;margin-left:10%; width:85%" class="bordaBaicho">&nbsp;</div>
              <div>Visto Funcionario</div>
            </div>
            
          </div>
        </div>

      </div>

    </div>

  </BODY>
</HTML>
