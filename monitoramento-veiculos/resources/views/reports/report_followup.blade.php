@extends('layouts.report')

@section('content')

<style>
  .bordered { border:1px solid;font-family: Arial; text-align:center }
  .borderedl { border:1px solid;font-family: Arial; text-align:left }
  .noborder { border-spacing: 0px; border-collapse: collapse;}
  .noborderspaced { border-spacing: 1px; border-collapse: separate;}
  table { border-spacing: 0px; border-collapse: collapse; margin-left: auto; margin-right: auto; }

  p {
      margin: 0;
      padding: 2px;
  }
  @page { margin-top: 10px; }
  body { margin-top: 10px; font-size:11px; font-family: Arial;}
  .page-break {
      page-break-after: always;
  }
</style>



<br/>

<table>
  <thead>
    <tr>
      <td class="bordered">Nome</td>
      <td class="bordered">Telefones</td>
      <td class="bordered">Descrição</td>
      <td class="bordered">e-mail</td>
      <td class="bordered">Situação</td>
    </tr>
  </thead>
  <tbody>
    @foreach ($contatos as $contato)
        <tr>
          <td class="bordered">{{$contato->cliente->nome}}</td>
          <td class="bordered">{{implode($contato->cliente->telefones->pluck('telefone')->toArray(),', ')}}</td>
          <td class="bordered">{{$contato->descricao}}</td>
          <td class="bordered">{{$contato->cliente->email}}</td>
          <td class="bordered">{{$contato->contatosituacao->descricao}}</td>
        </tr>
    @endforeach
  </tbody>
</table>

<br/>

@endsection
