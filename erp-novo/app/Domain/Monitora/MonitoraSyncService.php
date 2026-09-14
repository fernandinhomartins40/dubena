<?php

namespace App\Domain\Monitora;

use App\Domain\Monitora\Contracts\SgcasaDriver;
use App\Models\Empresa;
use App\Models\Monitora\UltimaPosicao;
use App\Models\Monitora\Veiculo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * MonitoraSyncService (N11 — GATE SGCasa). Busca posições no provedor externo
 * (via SgcasaDriver) e as ingere via MonitoraService. Roda como job agendado
 * (report:positions) em produção.
 */
class MonitoraSyncService
{
    public function __construct(
        private SgcasaDriver $sgcasa,
        private MonitoraService $monitora,
    ) {}

    /** Sincroniza posições dos veículos ativos de uma empresa. @return int posições ingeridas */
    public function sincronizar(int $empresaId): int
    {
        // O auto-cadastro roda para UMA empresa só, a dona da conta no
        // provedor. A lista de aparelhos do Traccar é global: rodando para
        // todas, cada empresa ganhava uma cópia dos mesmos 25 rastreadores —
        // 277 veículos fantasmas na primeira noite em produção.
        if ($this->autocadastroVale($empresaId)) {
            $this->cadastrarAparelhosNovos($empresaId);
        }

        $veiculos = Veiculo::query()->where('empresa_id', $empresaId)->where('ativo', true)
            ->whereNotNull('imei')->get()->keyBy('imei');

        if ($veiculos->isEmpty()) {
            return 0;
        }

        $posicoes = $this->sgcasa->buscarPosicoes($veiculos->keys()->all());

        if ($posicoes === []) {
            return 0;
        }

        // Instante do ultimo fix de cada veiculo, em UMA query.
        //
        // Antes isto era `ultimaPosicao()->first()` DENTRO do laco: uma consulta
        // por veiculo, a cada ciclo, para descobrir algo que cabe numa consulta
        // so. Com a frota pequena de hoje passa despercebido; o polling e por
        // minuto e por empresa, entao o desperdicio se multiplica por N frotas
        // sem nunca aparecer como lentidao numa tela.
        $ultimas = UltimaPosicao::query()
            ->whereIn('veiculo_id', $veiculos->pluck('id')->all())
            ->pluck('registrado_em', 'veiculo_id');

        $ingeridas = 0;

        // Uma transacao para o ciclo inteiro, e nao uma por posicao. Cada
        // BEGIN/COMMIT e ida e volta ao Postgres; com a frota inteira reportando
        // ao mesmo tempo, eram dezenas de transacoes por minuto para gravar
        // poucas linhas.
        DB::transaction(function () use ($posicoes, $veiculos, $ultimas, &$ingeridas) {
            foreach ($posicoes as $p) {
                $veiculo = $veiculos->get($p['imei']);

                if (! $veiculo) {
                    // Rede de segurança: o driver já filtra por IMEI conhecido (e é
                    // lá que o device desconhecido fica registrado, F6-02). Chegar
                    // aqui significaria driver devolvendo o que não foi pedido.
                    continue;
                }

                // O provedor devolve a ÚLTIMA posição conhecida, tendo ela mudado
                // ou não. Com polling a cada 30 s, um veículo parado a noite toda
                // regravaria a mesma leitura milhares de vezes: em produção deram
                // 27.891 linhas num dia para 3.749 posições reais, uma delas
                // repetida 1.859 vezes. No traçado isso empilha pontos no mesmo
                // lugar, e no banco cresce sem trazer informação nova.
                if ($this->jaGravada($ultimas->get($veiculo->id), $p)) {
                    continue;
                }

                $this->monitora->registrarPosicao($veiculo, [
                    'latitude' => $p['latitude'],
                    'longitude' => $p['longitude'],
                    'velocidade' => $p['velocidade'] ?? 0,
                    'direcao' => $p['direcao'] ?? null,
                    'ignicao' => $p['ignicao'] ?? false,
                    'registrado_em' => $p['registrado_em'] ?? now(),
                ]);
                $ingeridas++;
            }
        });

        return $ingeridas;
    }

    /**
     * Esta leitura já está gravada?
     *
     * Compara o INSTANTE do fix, e não as coordenadas: um veículo parado num
     * sinal reporta fixes diferentes no mesmo lugar, e esses interessam —
     * mostram que o rastreador continua vivo. O que não interessa é regravar
     * exatamente o mesmo fix que o provedor está apenas repetindo.
     *
     * @param  array{registrado_em?:string}  $nova
     */
    private function jaGravada(?Carbon $ultimoFix, array $nova): bool
    {
        // Recebe o instante ja carregado (uma query para a frota toda) em vez de
        // consultar por veiculo. O provedor devolve no maximo um fix por veiculo
        // por ciclo, entao o valor nao fica velho dentro do laco.
        if ($ultimoFix === null || ! isset($nova['registrado_em'])) {
            return false;
        }

        return $ultimoFix->equalTo(Carbon::parse($nova['registrado_em']));
    }

    /**
     * O auto-cadastro se aplica a esta empresa?
     *
     * Exige `TRACCAR_EMPRESA_ID` apontando para a dona da conta no provedor.
     * Sem essa definição o recurso fica desligado — e é o certo: a conta do
     * Traccar é uma só, e não há como o sistema adivinhar de qual das empresas
     * são os rastreadores. Adivinhar errado enche a frota alheia de veículos
     * que não existem.
     */
    private function autocadastroVale(int $empresaId): bool
    {
        if (! config('services.traccar.autocadastrar')) {
            return false;
        }

        $dona = config('services.traccar.empresa_id');

        return $dona !== null && (int) $dona === $empresaId;
    }

    /**
     * Cadastra veículo para aparelho que o provedor conhece mas o ERP não.
     *
     * Um rastreador instalado num caminhão novo passaria despercebido: não
     * aparece no mapa e ninguém sente falta do que nunca viu. Criando o registro
     * automaticamente, o veículo surge na frota com o apelido que o operador deu
     * no rastreador ("Caminhão Volks", "Fox") e alguém corrige a placa depois.
     *
     * O veículo nasce INATIVO de propósito: entrar sozinho na operação — em
     * roteirização, em relatório de frota — seria o sistema decidindo algo que
     * não lhe cabe. Ativar é um clique, e é uma escolha de quem opera.
     *
     * @return int veículos criados
     */
    private function cadastrarAparelhosNovos(int $empresaId): int
    {
        $aparelhos = $this->sgcasa->listarAparelhos();
        if ($aparelhos === []) {
            return 0;
        }

        // Confere contra TODOS os veículos, de qualquer empresa, e não só os
        // ativos. Dois motivos: um veículo desativado à mão não pode ser
        // recriado a cada rodada; e um rastreador já cadastrado em outra
        // empresa pertence a ela — duplicá-lo aqui criaria dois veículos
        // disputando a mesma posição.
        $conhecidos = array_flip(
            Veiculo::query()->whereNotNull('imei')->pluck('imei')->all()
        );

        $empresa = Empresa::find($empresaId);
        if (! $empresa) {
            return 0;
        }

        $criados = 0;
        foreach ($aparelhos as $a) {
            if ($a['imei'] === '' || isset($conhecidos[$a['imei']])) {
                continue;
            }

            Veiculo::create([
                'empresa_id' => $empresaId,
                'grupo_id' => $empresa->grupo_id,
                // A coluna é NOT NULL e única por empresa; o IMEI serve de
                // marcador provisório e deixa evidente que falta preencher.
                'placa' => $this->placaProvisoria($a['imei']),
                'descricao' => mb_substr($a['nome'], 0, 255),
                'imei' => $a['imei'],
                'ativo' => false,
            ]);
            $criados++;
        }

        return $criados;
    }

    /** Placa provisória a partir do IMEI, dentro dos 10 caracteres da coluna. */
    private function placaProvisoria(string $imei): string
    {
        return mb_substr('?'.$imei, 0, 10);
    }
}
