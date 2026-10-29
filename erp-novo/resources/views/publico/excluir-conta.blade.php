@extends('publico.layout')

@section('titulo', 'Excluir conta')

@section('conteudo')
<h1>Como excluir sua conta</h1>
<p class="vig">Aplicativo {{ $p['app_nome'] }} · {{ $p['razao_social'] }}</p>

<h2>Pelo aplicativo (imediato)</h2>
<ol>
    <li>Abra o aplicativo <strong>{{ $p['app_nome'] }}</strong> e entre com seu telefone.</li>
    <li>Toque em <strong>Perfil</strong>.</li>
    <li>Toque em <strong>Excluir conta</strong> e confirme.</li>
</ol>

<h2>Sem o aplicativo</h2>
<p>Se você não tem mais o aplicativo ou não consegue entrar, peça a exclusão por um dos contatos abaixo, informando o
telefone cadastrado. Confirmamos a identidade do titular antes de atender e respondemos em até 15 dias.</p>
@include('publico.contato')

<h2>O que acontece quando a conta é excluída</h2>
<ul>
    <li>Seu acesso ao aplicativo é encerrado na hora, em todos os aparelhos.</li>
    <li>Seu cadastro deixa de ser usado para novos pedidos, notificações ou contato comercial.</li>
    <li><strong>O que guardamos por obrigação legal:</strong> pedidos já realizados e os dados que constam nos documentos
        fiscais (nome, CPF quando informado, endereço de entrega e valores), pelo prazo exigido pela legislação
        tributária — em regra, 5 anos. Esses dados não são usados para nenhuma outra finalidade e são eliminados ao fim do prazo.</li>
</ul>

<p>Mais detalhes na <a href="{{ url('privacidade') }}">Política de Privacidade</a>.</p>
@endsection
