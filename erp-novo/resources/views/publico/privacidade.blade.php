@extends('publico.layout')

@section('titulo', 'Política de Privacidade')

@section('conteudo')
<h1>Política de Privacidade</h1>
<p class="vig">Aplicativos {{ $p['app_nome'] }} e {{ $p['app_nome'] }} Entregador · vigente desde {{ \Illuminate\Support\Carbon::parse($p['vigencia'])->format('d/m/Y') }}</p>

<p>Esta política explica quais dados pessoais os aplicativos <strong>{{ $p['app_nome'] }}</strong> (para clientes)
e <strong>{{ $p['app_nome'] }} Entregador</strong> (para entregadores da revenda) tratam, para quê, com quem são
compartilhados e como você exerce seus direitos, nos termos da Lei Geral de Proteção de Dados (Lei 13.709/2018).</p>

<h2>1. Quem é o controlador</h2>
<p><strong>{{ $p['razao_social'] }}</strong>@if($p['cnpj']), CNPJ {{ $p['cnpj'] }}@endif, é a controladora dos dados
tratados pelos aplicativos. Contato para assuntos de privacidade:</p>
@include('publico.contato')

<h2>2. Dados que tratamos</h2>
<p><strong>Aplicativo do cliente</strong></p>
<ul>
    <li><strong>Telefone celular</strong> — para entrar no aplicativo por código SMS e para a revenda falar com você sobre o pedido.</li>
    <li><strong>Nome, CPF (quando informado), e-mail (opcional) e endereços de entrega</strong> — para cadastrar você, entregar os pedidos e emitir o documento fiscal.</li>
    <li><strong>Localização aproximada ou precisa, só com o aplicativo aberto</strong> — para sugerir o endereço de entrega e mostrar revendas próximas. Não coletamos localização em segundo plano no aplicativo do cliente.</li>
    <li><strong>Pedidos, pagamentos e avaliações</strong> — histórico de compras, forma de pagamento escolhida e situação do pagamento. Os dados do cartão são digitados no ambiente do intermediador de pagamento; a revenda não armazena o número do cartão.</li>
    <li><strong>Identificador de notificações do aparelho</strong> — para avisar sobre o andamento do pedido.</li>
</ul>
<p><strong>Aplicativo do entregador</strong></p>
<ul>
    <li><strong>Nome e credenciais de acesso</strong> do colaborador, fornecidos pela revenda.</li>
    <li><strong>Localização precisa, inclusive com o aplicativo em segundo plano, somente enquanto o entregador estiver em serviço (jornada iniciada)</strong> — para o cliente acompanhar a entrega e a revenda organizar a logística. O rastreamento é indicado por uma notificação permanente e termina quando a jornada é encerrada ou o entregador sai do aplicativo.</li>
    <li><strong>Fotos tiradas com a câmera</strong> — apenas como comprovante de entrega, quando o entregador decide fotografar.</li>
</ul>

<h2>3. Para que usamos e com qual base legal</h2>
<ul>
    <li>Executar a compra e a entrega que você pediu (execução de contrato).</li>
    <li>Emitir documentos fiscais e manter registros contábeis (cumprimento de obrigação legal).</li>
    <li>Prevenir fraude e proteger sua conta (legítimo interesse).</li>
    <li>Enviar notificações sobre seus pedidos (execução de contrato; você pode desativá-las no aparelho).</li>
</ul>
<p>Não vendemos dados pessoais e não os usamos para publicidade de terceiros.</p>

<h2>4. Com quem compartilhamos</h2>
<ul>
    <li><strong>Google Firebase</strong> — verificação do telefone por SMS e envio de notificações.</li>
    <li><strong>Google Maps Platform</strong> — mapas, busca e conversão de endereços.</li>
    <li><strong>Intermediador de pagamento</strong> — processamento de PIX e cartão, quando você escolhe pagar pelo aplicativo.</li>
    <li><strong>Secretaria da Fazenda</strong> — dados do documento fiscal, por obrigação legal.</li>
    <li>Provedor de hospedagem dos servidores, sob contrato e dever de sigilo.</li>
</ul>

<h2>5. Por quanto tempo guardamos</h2>
<p>Os dados de cadastro ficam enquanto a conta estiver ativa. Pedidos e documentos fiscais são guardados pelo prazo
exigido pela legislação tributária (em regra, 5 anos). Registros de acesso são guardados por 6 meses (Marco Civil da Internet).
Depois disso, são eliminados ou anonimizados.</p>

<h2>6. Seus direitos</h2>
<p>Você pode pedir confirmação do tratamento, acesso, correção, anonimização, portabilidade, informação sobre
compartilhamento, revogação do consentimento e eliminação dos dados, pelos contatos da seção 1. Também pode
reclamar à Autoridade Nacional de Proteção de Dados (ANPD).</p>
<p class="caixa">Para encerrar sua conta, veja <a href="{{ url('excluir-conta') }}">como excluir sua conta</a>.</p>

<h2>7. Segurança</h2>
<p>A comunicação com os servidores é criptografada (HTTPS), o acesso é controlado por credenciais individuais e os
dados de cada revenda ficam isolados no banco de dados.</p>

<h2>8. Crianças</h2>
<p>Os aplicativos não se destinam a menores de 18 anos.</p>

<h2>9. Alterações</h2>
<p>Se esta política mudar, a data de vigência no topo será atualizada e mudanças relevantes serão avisadas no aplicativo.</p>
@endsection
