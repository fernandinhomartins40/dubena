<?php

namespace App\Domain\Monitora;

use App\Domain\Monitora\Contracts\SgcasaDriver;
use App\Models\Monitora\Veiculo;

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
    ) {
    }

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
        $ingeridas = 0;

        foreach ($posicoes as $p) {
            $veiculo = $veiculos->get($p['imei']);
            if (! $veiculo) {
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

        return $ingeridas;
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

        $empresa = \App\Models\Empresa::find($empresaId);
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
