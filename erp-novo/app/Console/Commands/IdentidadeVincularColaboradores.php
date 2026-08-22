<?php

namespace App\Console\Commands;

use App\Domain\Identidade\NormalizadorTexto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * identidade:vincular-colaboradores — uma PESSOA, dois PAPÉIS.
 *
 * Medido na base: 36 colaboradores têm cadastro de cliente com o mesmo CPF, e
 * 63 têm um cliente de mesmo nome. Hoje são dois registros soltos: o
 * funcionário que compra gás aparece como cliente sem qualquer ligação com seu
 * cadastro de RH, e o histórico de compra dele fica partido.
 *
 * O vínculo (`colaboradores.cliente_id`) NÃO funde os cadastros — cliente e
 * colaborador são coisas diferentes e devem continuar separados. Ele só declara
 * que se trata da mesma pessoa, o que basta para o histórico deixar de ser
 * partido sem misturar as duas naturezas de dado.
 *
 * Só vincula por CPF (determinístico). Nome igual vai para o relatório, porque
 * homônimo entre colaborador e cliente é comum e o custo do erro aqui é ligar a
 * ficha de RH de alguém ao cadastro de compra de outra pessoa.
 */
class IdentidadeVincularColaboradores extends Command
{
    protected $signature = 'identidade:vincular-colaboradores
        {--executar : Aplica os vínculos. Sem esta flag, somente leitura.}';

    protected $description = 'Liga o colaborador ao seu cadastro de cliente quando é a mesma pessoa (por CPF).';

    public function handle(): int
    {
        $executar = (bool) $this->option('executar');

        $porCpf = $this->paresPorCpf();
        $porNome = $this->paresApenasPorNome();

        if ($porCpf === [] && $porNome === []) {
            $this->info('Nenhum colaborador com cadastro de cliente correspondente.');

            return self::SUCCESS;
        }

        if ($porCpf !== []) {
            $this->info(count($porCpf).' par(es) com CPF idêntico'.($executar ? ' — vinculando:' : ' (use --executar):'));
            $this->table(
                ['Colaborador', 'Cliente', 'CPF'],
                array_map(fn ($p) => [$p->colaborador_nome, $p->cliente_nome, $p->cpf], $porCpf),
            );
        }

        if ($porNome !== []) {
            $this->newLine();
            $this->warn(count($porNome).' par(es) só por NOME — NÃO vinculados (homônimo é comum; confira à mão):');
            $this->table(
                ['Colaborador', 'Cliente'],
                array_map(fn ($p) => [$p->colaborador_nome, $p->cliente_nome], array_slice($porNome, 0, 20)),
            );
        }

        if (! $executar) {
            $this->newLine();
            $this->warn('Somente leitura: nada foi alterado.');

            return self::SUCCESS;
        }

        $n = 0;
        foreach ($porCpf as $par) {
            DB::table('colaboradores')
                ->where('id', $par->colaborador_id)
                ->update(['cliente_id' => $par->cliente_id]);
            $n++;
        }

        $this->info("{$n} colaborador(es) vinculado(s) ao respectivo cadastro de cliente.");

        return self::SUCCESS;
    }

    /** @return list<object> */
    private function paresPorCpf(): array
    {
        $colaboradores = DB::table('colaboradores')
            ->whereNull('cliente_id')
            ->whereNotNull('cpf')->where('cpf', '<>', '')
            ->get(['id', 'nome', 'cpf', 'empresa_id']);

        $clientes = DB::table('clientes')
            ->whereNotNull('cpf')->where('cpf', '<>', '')
            ->get(['id', 'nome', 'cpf', 'empresa_id']);

        // Indexa por empresa + CPF normalizado: o vínculo não pode cruzar tenant.
        $porChave = [];
        foreach ($clientes as $c) {
            $cpf = NormalizadorTexto::documento($c->cpf, 11);
            if (strlen($cpf) === 11) {
                $porChave[$c->empresa_id.'|'.$cpf] ??= $c;
            }
        }

        $pares = [];
        foreach ($colaboradores as $col) {
            $cpf = NormalizadorTexto::documento($col->cpf, 11);
            $cliente = $porChave[$col->empresa_id.'|'.$cpf] ?? null;

            if ($cliente !== null) {
                $pares[] = (object) [
                    'colaborador_id' => $col->id,
                    'colaborador_nome' => $col->nome,
                    'cliente_id' => $cliente->id,
                    'cliente_nome' => $cliente->nome,
                    'cpf' => $cpf,
                ];
            }
        }

        return $pares;
    }

    /**
     * Pares que casam só por nome — relatório, nunca vínculo automático.
     *
     * @return list<object>
     */
    private function paresApenasPorNome(): array
    {
        $colaboradores = DB::table('colaboradores')->whereNull('cliente_id')->get(['id', 'nome', 'cpf', 'empresa_id']);
        $clientes = DB::table('clientes')->get(['id', 'nome', 'cpf', 'empresa_id']);

        $porNome = [];
        foreach ($clientes as $c) {
            $chave = $c->empresa_id.'|'.NormalizadorTexto::nome($c->nome);
            $porNome[$chave] ??= $c;
        }

        $pares = [];
        foreach ($colaboradores as $col) {
            $cpfCol = NormalizadorTexto::documento($col->cpf, 11);
            $cliente = $porNome[$col->empresa_id.'|'.NormalizadorTexto::nome($col->nome)] ?? null;

            if ($cliente === null) {
                continue;
            }

            // Se ambos têm CPF e ele bate, o par já saiu por CPF acima.
            $cpfCli = NormalizadorTexto::documento($cliente->cpf, 11);
            if (strlen($cpfCol) === 11 && strlen($cpfCli) === 11 && $cpfCol === $cpfCli) {
                continue;
            }

            $pares[] = (object) [
                'colaborador_nome' => $col->nome,
                'cliente_nome' => $cliente->nome,
            ];
        }

        return $pares;
    }
}
