<?php

namespace App\Domain\Frota;

use App\Models\Frota\Veiculo;
use App\Models\Frota\VeiculoAbastecimento;
use App\Models\Frota\VeiculoTrocaOleo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * VeiculoService (C6) — frota. CRUD + abastecimento (atualiza km do veículo),
 * consumo médio (km/l) e alerta de troca de óleo por km rodado.
 */
class VeiculoService
{
    /** @param array<string,mixed> $dados */
    public function criar(array $dados): Veiculo
    {
        return Veiculo::create($dados);
    }

    /** @param array<string,mixed> $dados */
    public function atualizar(Veiculo $veiculo, array $dados): Veiculo
    {
        $veiculo->update($dados);

        return $veiculo->refresh();
    }

    public function excluir(Veiculo $veiculo): void
    {
        $veiculo->delete();
    }

    /**
     * Registra um abastecimento. O km informado não pode ser menor que o km atual
     * do veículo; ao gravar, o km_atual do veículo avança.
     *
     * @param  array<string,mixed>  $dados
     */
    public function abastecer(Veiculo $veiculo, array $dados): VeiculoAbastecimento
    {
        $km = (int) $dados['km'];
        if ($km < (int) $veiculo->km_atual) {
            throw ValidationException::withMessages([
                'km' => "O km do abastecimento ({$km}) não pode ser menor que o km atual do veículo ({$veiculo->km_atual}).",
            ]);
        }

        return DB::transaction(function () use ($veiculo, $dados, $km) {
            $litros = (float) $dados['litros'];
            $valorLitro = isset($dados['valor_litro']) ? (float) $dados['valor_litro'] : null;

            $ab = $veiculo->abastecimentos()->create([
                'data' => $dados['data'] ?? now()->toDateString(),
                'km' => $km,
                'litros' => $litros,
                'valor_litro' => $valorLitro,
                'valor_total' => $dados['valor_total'] ?? ($valorLitro !== null ? round($litros * $valorLitro, 2) : null),
                'tanque_cheio' => $dados['tanque_cheio'] ?? true,
            ]);

            $veiculo->update(['km_atual' => $km]);

            return $ab;
        });
    }

    /**
     * Consumo médio (km/l) entre o primeiro e o último abastecimento de tanque
     * cheio: (km_final - km_inicial) / litros consumidos entre eles (exclui o 1º,
     * que apenas marca o ponto de partida). Null se não há dados suficientes.
     */
    public function consumoMedio(Veiculo $veiculo): ?float
    {
        $cheios = $veiculo->abastecimentos()
            ->where('tanque_cheio', true)
            ->orderBy('km')->get();

        if ($cheios->count() < 2) {
            return null;
        }

        $kmRodado = (int) $cheios->last()->km - (int) $cheios->first()->km;
        // Litros após o ponto de partida (o 1º tanque cheio não conta).
        $litros = (float) $cheios->slice(1)->sum('litros');

        if ($kmRodado <= 0 || $litros <= 0) {
            return null;
        }

        return round($kmRodado / $litros, 2);
    }

    /**
     * Registra uma troca de óleo (T4.7) e ZERA o alerta.
     *
     * Era só leitura no novo, enquanto o legado tem CRUD completo — sem isto o
     * operador da frota não consegue lançar a troca que acabou de fazer.
     *
     * O ponto sutil está no `km_ultima_troca_oleo`: `alertaTrocaOleo()` calcula
     * o rodado a partir DELE, não da última linha da tabela. Gravar a troca sem
     * atualizá-lo deixaria o alerta de "troca vencida" aceso para sempre, e o
     * operador aprenderia a ignorá-lo — que é o pior resultado possível para um
     * alerta.
     *
     * @param  array<string,mixed>  $dados
     */
    public function registrarTrocaOleo(Veiculo $veiculo, array $dados): VeiculoTrocaOleo
    {
        $km = (int) $dados['km'];

        // Mesma regra do abastecimento: o hodômetro não anda para trás.
        if ($km < (int) $veiculo->km_atual) {
            throw ValidationException::withMessages([
                'km' => "O km da troca ({$km}) não pode ser menor que o km atual do veículo ({$veiculo->km_atual}).",
            ]);
        }

        return DB::transaction(function () use ($veiculo, $dados, $km) {
            // `empresa_id` vem do trait de tenant dos models (como no
            // abastecimento acima); passá-lo aqui seria ignorado pelo $fillable.
            $troca = $veiculo->trocasOleo()->create([
                'data' => $dados['data'] ?? now()->toDateString(),
                'km' => $km,
                'valor' => $dados['valor'] ?? null,
                'observacao' => $dados['observacao'] ?? null,
            ]);

            $veiculo->update([
                'km_atual' => $km,
                'km_ultima_troca_oleo' => $km,
            ]);

            return $troca;
        });
    }

    /**
     * Alerta de troca de óleo: km rodado desde a última troca vs intervalo
     * recomendado. Retorna o status para a tela da frota.
     *
     * @return array{precisa_trocar: bool, km_rodado: int, km_intervalo: ?int, km_restante: ?int}
     */
    public function alertaTrocaOleo(Veiculo $veiculo): array
    {
        $intervalo = $veiculo->km_troca_oleo !== null ? (int) $veiculo->km_troca_oleo : null;
        $ultima = (int) ($veiculo->km_ultima_troca_oleo ?? 0);
        $rodado = max(0, (int) $veiculo->km_atual - $ultima);

        $restante = $intervalo !== null ? $intervalo - $rodado : null;

        return [
            'precisa_trocar' => $intervalo !== null && $rodado >= $intervalo,
            'km_rodado' => $rodado,
            'km_intervalo' => $intervalo,
            'km_restante' => $restante,
        ];
    }
}
