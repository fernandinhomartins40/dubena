<?php

namespace App\Processors;

use App\Financeiroparcela;
use DB;
use Storage;
use Session;
use App\Boleto;
use App\Empresa;
use App\Boletohistorico;
use App\Ocorrenciasremessas;
use App\Services\BoletoService;
use App\Services\BoletoCaixaService;
use App\Services\BoletoItauService;
use App\Repository\SelectRepository;
use App\Services\CarbonCustom as Carbon;
use Eduardokum\LaravelBoleto as LaravelBoleto;
use App\Services\RemessaCaixaService as RemessaCaixa;
use App\Services\RemessaItauService as RemessaItau;

class BoletoProcessor
{

    private $instrucoes;
    private $boletoSequencia;
    private $pagador;
    private $beneficiario;
    private $codigoBanco;
    private $cliente_id;
    private $filename;
    private $headers;
    private $bancosPermitidos = ['104', '341'];
    private $errors;
    private $empresa_id;
    private $pdf;
    private $handle;
    private $diasProtesto;
    private $desconto;
    private $cancelaAbatimento;
    private $createdNow = false;
    private $instrucoesPadraoCaixa = [
        '',
        'SAC CAIXA: 0800 726 0101 (informações, reclamações, sugestões e elogios)',
        'Para pessoas com deficiência auditiva ou de fala: 0800 726 2492',
        'Ouvidoria: 0800 725 7474',
        'caixa.gov.br'
    ];
    private $detalhe;
    private $lastHistorico;
    public $boletos = [];

    protected function setLastHistorico($lastHistorico)
    {
        $this->lastHistorico = $lastHistorico;
    }

    public function getLastHistorico()
    {
        return $this->lastHistorico;
    }

    public function setDiasProtesto($days)
    {
        $this->diasProtesto = $days;
    }

    public function setCodigoBanco($codigoBanco)
    {
        $this->setBeneficiario();
        if (in_array($codigoBanco, $this->bancosPermitidos))
            $this->codigoBanco = $codigoBanco;
        else
            throw new \Exception("Sistema não gera boletos para o banco com o código: $codigoBanco");
    }

    public function getCodigoBanco()
    {
        return $this->codigoBanco;
    }

    public function getErrors($e = null)
    {
        if (is_null($e)) {
            return $this->errors;
        } else {
            $errors = '';
            if (isset($e->validator)) {
                $errorsValidation = $e->validator->messages()->toArray();
                foreach ($errorsValidation as $errorsArray) {
                    $errors .= ' ' . implode(' ', $errorsArray);
                }
            } else {
                $errors = $e->getMessage();
            }
            return $errors;
        }
    }

    protected function setErrors($error)
    {
        $this->errors .= ' ' . $error;
        return;
    }

    private function setInstrucoes($conta, $par, $descricaoParcela)
    {
        $instrucoes = [];
        $instrucoesExp = explode(';', $conta->boletoinstrucoes);
        array_push($instrucoesExp, $descricaoParcela);
        foreach ($instrucoesExp as $inst) {
            if (!empty($inst)) {
                $inst = str_replace('#multa', requestNumeroDecimalOracle(floatVal($par->valorefetivado) * ($conta->boletomulta / 100)), $inst);
                $inst = str_replace('#juros', requestNumeroDecimalOracle(floatVal($par->valorefetivado) * ($conta->boletojuros / 100)), $inst);
                $vencimento = strpos($par->datavencimento, "-") !== false ? requestDataOracle($par->datavencimento, false) : $par->datavencimento;
                $inst = str_replace('#vencimento', $vencimento, $inst);
                $inst = str_replace('#diasprotesto', $conta->boletodiasprotesto, $inst);
                array_push($instrucoes, ltrim($inst));
            }
        }
        if ($this->codigoBanco == '104') {
            $this->instrucoes = array_merge($instrucoes, $this->instrucoesPadraoCaixa);
        } else {
            $this->instrucoes = $instrucoes;
        }
    }

    public function insertBoletoHistorico($boleto = null, $args = [])
    {
        $empresa = Session::get('empresa_padrao');

        $data = [
            'grupo_id'             => $empresa->grupo_id,
            'empresa_id'           => $empresa->id,
            'boleto_id'            => $boleto->id,
            'financeiroparcela_id' => $boleto->financeiroparcela_id,
            'conta_id'             => $boleto->conta_id,
            'datahora'             => Carbon::now()->toDateTimeString(),
            'numerosequencia'      => $boleto->numerosequencia,
            'dv'                   => $boleto->dv,
            'nossonumero'          => $boleto->nossonumero,
            'alteracaodatahora'    => Carbon::now()->toDateTimeString(),
            'alteracaouser_id'     => \Auth::user()->id,
        ];

        $data = array_merge($data, $args);

        $boletoHistorico = Boletohistorico::create($data);
        $this->setLastHistorico($boletoHistorico->id);
        return true;
    }

    public function setBeneficiario()
    {
        $empresa = Empresa::find(Session::get('empresa_padrao')->id);
        $enderecoBeneficiario = $empresa->rua->descricao . ', ' . $empresa->numero;
        $pessoa = new LaravelBoleto\Pessoa([
            'nome'      => $empresa->razao_social,
            'endereco'  => $enderecoBeneficiario,
            'cep'       => $empresa->cep,
            'uf'        => $empresa->uf,
            'cidade'    => $empresa->cidade->descricao,
            'documento' => $empresa->cnpj,
            'bairro'    => $empresa->bairro->descricao
        ]);
        $this->beneficiario = $pessoa;
    }

    public function setPagador($cliente)
    {
        $this->pagador = new LaravelBoleto\Pessoa([
            'nome'      => $cliente->nome,
            'endereco'  => $cliente->rua . ', ' . $cliente->numero,
            'bairro'    => $cliente->bairro,
            'cep'       => $cliente->cep,
            'uf'        => $cliente->uf,
            'cidade'    => $cliente->cidade,
            'documento' => $cliente->tipopessoa == 'F' ? $cliente->cpf : $cliente->cnpj
        ]);
    }

    public function getClienteById($cliente_id)
    {
        $select = [
            'rua.descricao as rua', 'cidade.descricao as cidade', 'bairro.descricao as bairro', 'clientes.numero',
            'clientes.nome',
            'clientes.cep', 'clientes.cpf', 'clientes.cnpj', 'tipopessoa.tipopessoacadastro as tipopessoa', 'clientes.uf',
            'clientes.id'
        ];
        $joins = [
            'tipopessoa' => 'tipopessoas',
            'bairro'     => 'bairros',
            'rua'        => 'ruas',
            'cidade'     => 'cidades'
        ];
        $where = ['clientes.id' => $cliente_id];
        return SelectRepository::searchCliente($where, $select, $joins)->get()->first();
    }

    public function getRemessa($parcelas, $conta, $id, $sequence, $boletoRemessa)
    {
        if ($this->codigoBanco == '104')
            $remessa = $this->newRemessaCaixa($conta, $sequence);
        else if ($this->codigoBanco == '341')
            $remessa = $this->newRemessaItau($conta, $sequence);
        foreach ($parcelas as $par) {
            $cliente = $par->financeiro->cliente;
            $this->cliente_id = $cliente->id;
            $cliente = $this->getClienteById($this->cliente_id);
            $this->setPagador($cliente);

            if (is_null($par->boleto)) {
                $hist = Boletohistorico::where('financeiroparcela_id', $par->id)->where('alteroucancelou', true)->orderBy('id', 'DESC')->first();
                $par->boleto = is_null($hist) ? null : Boleto::find($hist->boleto_id);
                if (is_null($par->boleto)) {
                    $this->setErrors("A parcela nº : $par->id não possui boleto vinculada a ela.");
                    return false;
                }
            }

            $lastHistBoleto = Boletohistorico::where('boleto_id', $par->boleto->id)
                ->with('ocorrencia')
                ->whereRaw("ocorrencia_id is not null")->orderBy('id', 'DESC')->first();

            $codigoOcorrencia = !is_null($lastHistBoleto) ? $lastHistBoleto->ocorrencia->codigo : "01";

            $remessa->setStatus($codigoOcorrencia);

            $this->diasProtesto = $par->boleto->protesto_devolucao;
            $this->boletoSequencia = $par->boleto->numerosequencia;
            $this->isAbatimento($par->boleto);
            $par->documento = $par->financeiro->documento;
            $boleto = $this->createBoleto($par, $conta, $par->boleto, true, true);
            $remessa->addBoleto($boleto);
        }

        $datahora = Carbon::parse($boletoRemessa->datahora);
        //$this->filename = $datahora->__get('day') . $datahora->__get('month') . str_pad($sequence, 6, '0', STR_PAD_LEFT) . '.rem';
        $this->filename = $datahora->format('dm') . $sequence . '.rem';
        $this->headers = array(
            "Content-Disposition" => "attachment; filename=$this->filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $this->handle = fopen($this->filename, 'w+');

        $arquivo = fwrite($this->handle, $remessa->gerar());

        $boletoRemessa->update(['gerouremessa' => true]);
        $saved = $this->saveArchive($boletoRemessa, $this->filename);

        if (is_bool($saved) && $saved) {
            DB::commit();
            return response()->download($this->filename, $this->filename, $this->headers)->deleteFileAfterSend(true);
        }
        return redirect('remessa')->withMessageDanger($saved);
    }

    public function getFilename()
    {
        return $this->filename;
    }

    private function getInfoRetorno($file)
    {
        $filename = $file->getClientOriginalName();

        $handle = fopen($filename, 'w+');
        fwrite($handle, file_get_contents($file->getRealPath()));

        fclose($handle);
        $retorno = \Eduardokum\LaravelBoleto\Cnab\Retorno\Factory::make($filename);
        unlink($filename);
        return $retorno;
    }

    public function processarRetorno($file)
    {
        $retorno = $this->getInfoRetorno($file);
        $retorno->processar();
        $this->codigoBanco = $retorno->getCodigoBanco();
        $detalhes = $retorno->getDetalhes();
        return $this->getBoletosFromDetalhes($detalhes, $retorno->getCodigoBanco());
    }

    private function getBoletosFromDetalhes($detalhes, $codigoBanco)
    {
        $i = 0;
        $boletos_id = collect([]);
        foreach ($detalhes as $r) {
            $boletos_id[$i] = (object) [];
            if ($codigoBanco == '104') {
                $boletos_id[$i]->boleto_id = (int) substr($r->nossoNumero, 2, strlen($r->nossoNumero));
            } else
            if ($codigoBanco == '341') {
                $boletos_id[$i]->boleto_id = (int) substr($r->nossoNumero, 0, strlen($r->nossoNumero) - 1);
            }
            $boletos_id[$i]->nossoNumero = $r->nossoNumero;
            $i++;
        }

        $boletoDb = Boleto::whereIn('boletos.numerosequencia', $boletos_id->pluck('boleto_id'))
            ->where('boletos.cancelado', 0)
            ->select('boletos.numerosequencia as boleto_id', 'parc.id as parcela_id', 'parc.datavencimento', 'parc.valor as valorparcela', 'parc.multa as multaparcela', 'parc.juros as jurosparcela', 'parc.valorefetivado', 'parc.desconto as descontoparcela', 'c.nome as cliente', 'banco.codigo as codigo_banco')
            ->leftJoin('financeiroparcelas parc', 'boletos.financeiroparcela_id', 'parc.id')
            ->leftJoin('financeiros fin', 'fin.id', 'parc.financeiro_id')
            ->leftJoin('clientes c', 'c.id', 'fin.cliente_id')
            ->leftJoin('contas conta', 'conta.id', 'boletos.conta_id')
            ->leftJoin('bancos banco', 'banco.id', 'conta.banco_id')
            ->get();
        $detalhesFinal = collect([]);
        $ocorrencias = Ocorrenciasremessas::where('codigo_banco', $this->codigoBanco)
            ->where('tipo', 1)
            ->where('uso_banco', 1)
            ->select('descricao', 'codigo', 'id')->get();
        foreach ($detalhes as $detalhe) {
            $this->detalhe = $boletos_id->where('nossoNumero', $detalhe->nossoNumero)->first();
            $filter = function ($f) {
                return $this->detalhe->boleto_id == $f->boleto_id;
            };
            $bol = $boletoDb->filter($filter)->first();
            $detalheObj = is_null($bol) ? ['parcela_id' => '', 'cliente' => ''] : $bol->toArray();
            $detalheObj['nossoNumero'] = $detalhe->getNossoNumero();
            $detalheObj['numeroDocumento'] = $detalhe->getNumeroDocumento();
            $ocorrencia = $ocorrencias->where('codigo', $detalhe->getOcorrencia())->first();
            $ocorrencia_completa = is_null($ocorrencia) ? $detalhe->getOcorrencia() . ' - ' . $detalhe->getOcorrenciaDescricao() : $detalhe->getOcorrencia() . ' - ' . $ocorrencia->descricao;
            $detalheObj['ocorrencia_completa'] = $ocorrencia_completa;
            $detalheObj['ocorrencia'] = $detalhe->getOcorrencia();
            $detalheObj['dataOcorrencia'] = $detalhe->getDataOcorrencia();
            $detalheObj['dataVencimento'] = $detalhe->getDataVencimento();
            $detalheObj['dataCredito'] = empty($detalhe->getDataCredito()) ? 0 : $detalhe->getDataCredito();
            $detalheObj['valor'] = empty($detalhe->getValor()) ? 0 : $detalhe->getValor();
            $detalheObj['valorTarifa'] = empty($detalhe->getValorTarifa()) ? 0 : $detalhe->getValorTarifa();
            $detalheObj['valorAbatimento'] = empty($detalhe->getValorAbatimento()) ? 0 : $detalhe->getValorAbatimento();
            $detalheObj['valorDesconto'] = empty($detalhe->getValorDesconto()) ? 0 : $detalhe->getValorDesconto();
            $detalheObj['valorRecebido'] = empty($detalhe->getValorRecebido()) ? 0 : $detalhe->getValorRecebido();
            $detalheObj['valorMora'] = empty($detalhe->getValorMora()) ? 0 : $detalhe->getValorMora();
            $detalheObj['valorMulta'] = empty($detalhe->getValorMulta()) ? 0 : $detalhe->getValorMulta();
            $detalheObj['error'] = $detalhe->getError();
            $detalheObj['diferenca'] = $detalheObj['valorRecebido'] + $detalheObj['valorTarifa'] - $detalheObj['valor'];
            $detalhesFinal = $detalhesFinal->push($detalheObj);
        }
        return $detalhesFinal;
    }

    private function newRemessaCaixa($conta, $sequence)
    {
        return new RemessaCaixa([
            'agencia'       => explode('-', $conta->agencia)[0],
            'agenciaDv'     => explode('-', $conta->agencia)[1],
            'conta'         => explode('-', $conta->conta)[0],
            'contaDv'       => explode('-', $conta->conta)[1],
            'idRemessa'     => $sequence,
            'carteira'      => 'RG',
            'codigoCliente' => $conta->boletocedente,
            'beneficiario'  => $this->beneficiario,
            'especieDoc'    => $conta->boletoespecie,
            'aceite'        => $conta->boletoaceite
        ]);
    }

    private function newRemessaItau($conta, $sequence)
    {
        return new RemessaItau([
            'agencia'       => explode('-', $conta->agencia)[0],
            'agenciaDv'     => explode('-', $conta->agencia)[1],
            'conta'         => explode('-', $conta->conta)[0],
            'contaDv'       => explode('-', $conta->conta)[1],
            'idRemessa'     => $sequence,
            'carteira'      => $conta->boletocarteira,
            'codigoCliente' => $conta->boletocedente,
            'beneficiario'  => $this->beneficiario,
            'especieDoc'    => $conta->boletoespecie,
            'aceite'        => $conta->boletoaceite
        ]);
    }

    public function getCodMovRemessa($boleto, $baixa = false)
    {
        if ($baixa)
            return '02';
        if ($boleto->alterouvencimento)
            return '05';
        else if ($boleto->alterouVencimento)
            return '02';
    }

    public function createBoleto($par, $conta, $boleto, $instrucoesPar, $isRemessa = false)
    {
        $this->setInstrucoes($conta, $par, $instrucoesPar);
        if ($conta->banco->codigo == '104')
            return $this->newBoletoCaixa($conta, $par, $boleto, $this->instrucoes, $isRemessa);
        else if ($conta->banco->codigo == '341')
            return $this->newBoletoItau($conta, $par, $boleto, $this->instrucoes, $isRemessa);
        else
            $this->setErrors("O sistema não gera boletos para o banco dessa conta.");
    }

    private function newBoletoCaixa($conta, $par, $boleto, $instrucoes, $isRemessa = false)
    {
        $vencimento = strpos($par->datavencimento, '/') === false ? requestDataOracle($par->datavencimento, false) : $par->datavencimento;
        $valor = strpos($par->valorefetivado, 'R$') === false ? $par->valorefetivado : insertNumeroDecimalOracle($par->valorefetivado);

        if (strpos($par->multa, 'R$') === false) {
            $multa = $par->multa + $par->juros;
        } else {
            $multa = insertNumeroDecimalOracle($par->multa) + insertNumeroDecimalOracle($par->juros);
        }
        $valor += ($this->cancelaAbatimento ? 0 : $boleto->valor_abatimento);

        $pars = [
            'logo'                   => '../vendor/eduardokum/laravel-boleto/logos/104.png',
            'dataVencimento'         => Carbon::createFromFormat('d/m/Y', $vencimento),
            'valor'                  => $valor - ($isRemessa ? $multa : 0),
            'multa'                  => $multa,
            'juros'                  => $conta->boletojuros * 30,
            'numero'                 => $this->boletoSequencia,
            'numeroDocumento'        => $par->documento,
            'pagador'                => $this->pagador,
            'beneficiario'           => $this->beneficiario,
            'agencia'                => explode('-', $conta->agencia)[0],
            'conta'                  => explode('-', $conta->conta)[0],
            'contaDv'                => explode('-', $conta->conta)[1],
            'carteira'               => $conta->boletocarteira,
            'codigoCliente'          => $conta->boletocedente,
            'descricaoDemonstrativo' => $instrucoes,
            'instrucoes'             => $instrucoes,
            'aceite'                 => $conta->boletoaceite,
            'especieDoc'             => $conta->boletoespecie,
            'desconto'               => $this->cancelaAbatimento ? 0 : $boleto->valor_abatimento
        ];

        $caixa = new BoletoCaixaService($pars);

        $caixa->setDataDocumento(Carbon::parse($boleto->datahora));

        $protestoBaixa = !is_null($this->diasProtesto) ? $this->diasProtesto : $conta->boletodiasprotesto;
        if (is_null($conta->boletoprotesto_baixadevolucao)) {
            throw new \Exception("O campo \"Instrução\" da conta código "
                . $conta->id . " precisa ser preenchido;");
        }

        if ($conta->boletoprotesto_baixadevolucao === "0") {
            $caixa->setDiasBaixaAutomatica($protestoBaixa);
        } else {
            $caixa->setDiasProtesto($protestoBaixa);
        }

        $caixa->setLocalPagamento("PREFERENCIALMENTE NAS CASAS LOTERICAS ATÉ O VALOR LIMITE");

        return $caixa;
    }

    private function newBoletoItau($conta, $par, $boleto, $instrucoes, $isRemessa = false)
    {
        $vencimento = strpos($par->datavencimento, '/') === false ? requestDataOracle($par->datavencimento, false) : $par->datavencimento;
        $valor = strpos($par->valorefetivado, 'R$') === false ? $par->valorefetivado : insertNumeroDecimalOracle($par->valorefetivado);

        if (strpos($par->multa, 'R$') === false) {
            $multa = $par->multa + $par->juros;
        } else {
            $multa = insertNumeroDecimalOracle($par->multa) + insertNumeroDecimalOracle($par->juros);
        }
        $valor += ($this->cancelaAbatimento ? 0 : $boleto->valor_abatimento);

        $pars = [
            'logo'                   => '../vendor/eduardokum/laravel-boleto/logos/341.png',
            'dataVencimento'         => Carbon::createFromFormat('d/m/Y', $vencimento),
            'valor'                  => $valor - ($isRemessa ? $multa : 0),
            'multa'                  => $multa,
            'juros'                  => $conta->boletojuros * 30,
            'numero'                 => $this->boletoSequencia,
            'numeroDocumento'        => $par->documento,
            'pagador'                => $this->pagador,
            'beneficiario'           => $this->beneficiario,
            'agencia'                => explode('-', $conta->agencia)[0],
            'conta'                  => explode('-', $conta->conta)[0],
            'contaDv'                => explode('-', $conta->conta)[1],
            'carteira'               => $conta->boletocarteira,
            'codigoCliente'          => $conta->boletocedente,
            'descricaoDemonstrativo' => $instrucoes,
            'instrucoes'             => $instrucoes,
            'aceite'                 => $conta->boletoaceite,
            'especieDoc'             => $conta->boletoespecie,
            'desconto'               => $this->cancelaAbatimento ? 0 : $boleto->valor_abatimento,
            'jurosApos'              => ($conta->boletojuros > 0 ? 1 : false)
        ];
        $caixa = new BoletoItauService($pars);

        $caixa->setDataDocumento(Carbon::parse($boleto->datahora));

        $protestoBaixa = !is_null($this->diasProtesto) ? $this->diasProtesto : $conta->boletodiasprotesto;
        if (is_null($conta->boletoprotesto_baixadevolucao)) {
            throw new \Exception("O campo \"Instrução\" da conta código "
                . $conta->id . " precisa ser preenchido;");
        }

        if ($conta->boletoprotesto_baixadevolucao === "0") {
            $caixa->setDiasBaixaAutomatica($protestoBaixa);
        } else {
            $caixa->setDiasProtesto($protestoBaixa);
        }

        $caixa->setLocalPagamento("PAGÁVEL EM QUALQUER BANCO ATÉ O VENCIMENTO. APÓS O VENCIMENTO, ACESSE #ITAU.COM.BR/BOLETOS E PAGUE EM QUALQUER BANCO.");

        return $caixa;
    }

    public function getContasIndexGerar()
    {
        $where = ['boletoemite' => true, 'empresa_id' => Session::get('empresa_padrao')->id, 'ativo' => true];
        return SelectRepository::searchConta($where, ['id', 'descricao'])->get()->pluck('descricao', 'id')->prepend('Selecione', '');
    }

    public function gerarBoleto($data, $isAppNf = false)
    {
        DB::beginTransaction();
        try {
            $inseredescparcela = 'true' == $data['inseredescparcela'];
            $conta = $this->getContaById($data['conta_id']);
            $parcelas = collect(json_decode($data['parcelas']));
            $parcelas_id = $parcelas->pluck('id')->toArray();
            $cliente = $this->getClienteById($parcelas->pluck('cliente_id')->unique('cliente_id')->first());
            $this->setPagador($cliente);
            $this->setBeneficiario();
            $this->cliente_id = $cliente->id;
            $parcelasOrigin = $this->getParcelasById($parcelas_id);
            $boletosDb = $this->getBoletosByParcelasId($parcelas_id);
            if (is_null($conta))
                $conta = SelectRepository::searchConta(null, null, null, null, ['id' => $boletosDb->pluck('conta_id')->toArray()])->get()->first();

            $this->gerarBoletosFromParcelas($parcelas, $parcelasOrigin, $inseredescparcela, $conta, $boletosDb);
            DB::commit();
            if ($isAppNf) {
                return ['status' => 'OK|', 'data' => base64_encode($this->pdf->gerarBoleto('S', null, $conta->boletocomprovanteentrega))];
            } else {
                return response($this->pdf->gerarBoleto('I', null, $conta->boletocomprovanteentrega), 200, [
                    'Content-Type'        => 'application/pdf',
                    'Content-Disposition' => 'inline; Boleto',
                ]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            $errorsBoleto = "Não foi possível gerar o boleto: " . e($e->getMessage() . " " . $e->getFile() . " " . $e->getLine());
            if ($isAppNf) {
                return ['status' => 'NOK', 'msg' => $errorsBoleto];
            } else {
                return view('errors.boleto_error', compact('errorsBoleto'));
            }
        }
        return 'OK|';
    }

    public function gerarBoletosFromParcelas($parcelas, $parcelasOrigin, $inseredescparcela, $conta, $boletosDb)
    {
        $this->pdf = new BoletoService();
        foreach ($parcelas as $par) {
            $parcelaOrigin = $parcelasOrigin->where('id', $par->id)->first();
            $descricaoParcela = $inseredescparcela ? $parcelaOrigin->descricao : '';
            if (is_null($parcelaOrigin))
                throw new \Exception("Não foi encontrada nenhuma parcela com o código: $par->id");

            $boletoGerado = $parcelaOrigin->boletogerado == '1';

            $par->financeiro_id = $parcelaOrigin->financeiro_id;
            $par->documento = $parcelaOrigin->documento;
            $par->desconto = $parcelaOrigin->desconto;

            $boletoDb = $boletosDb->where('financeiroparcela_id', $par->id)->first();

            $parcelaOrigin->update(['boletogerado' => true]);

            $venc = requestDataOracle($parcelaOrigin->datavencimento, false);
            $gerouRem = isset($boletoDb->gerouremessa) && $boletoDb->gerouremessa;
            $alterouVencimento = $gerouRem && $par->datavencimento != $venc;

            $boletoCreate = $this->insertUpdateBoleto($boletoGerado, $conta, $boletoDb, $par, $alterouVencimento);

            if (!$boletoCreate) {
                throw new \Exception("Boleto referente a parcela número " . $par->id . " não encontrado" .
                    ", possivelmente um comando de pedido de baixa foi realizado.");
            }
            $abatimento = $this->isAbatimento($boletoCreate);

            $dataUpdate = [
                'juros' => insertNumeroDecimalOracle($par->juros),
                'multa' => insertNumeroDecimalOracle($par->multa)
            ];

            if ($abatimento && !$alterouVencimento) {
                $dataUpdate['desconto'] = $boletoCreate->valor_abatimento + $parcelaOrigin->desconto;
                $dataUpdate['valorefetivado'] = $parcelaOrigin->valorefetivado - $boletoCreate->valor_abatimento;
            } elseif (!$this->cancelaAbatimento) {
                //dia 01/10/18
                //Ao efetuar operações o valor estava diminuindo 2x o desconto, por isso foi alterado para pegar do valor bruto
                //se isso der problema novamente, verificar a necessidade de somente o "! $this->cancelaAbatimento" na instrução do if
                $dataUpdate['valorefetivado'] = insertNumeroDecimalOracle($par->valor) - $parcelaOrigin->desconto;
            }

            $dataUpdate['datavencimento'] = insertDataOracle($par->datavencimento);

            $imprimiu = array_key_exists('imprimiu', $boletoCreate->toArray()) && $boletoCreate->imprimiu;
            if (!$imprimiu || $alterouVencimento) {
                if ($this->cancelaAbatimento) {
                    $dataUpdate["desconto"] = $parcelaOrigin->desconto - $boletoCreate->valor_abatimento;
                    $dataUpdate["valorefetivado"] = $parcelaOrigin->valorefetivado + $boletoCreate->valor_abatimento;
                }
                $parcelaOrigin->update($dataUpdate);
            }
            //se já imprimiu, seta os parâmetros da variável "par" para pegar as da parcela do banco
            foreach ($parcelaOrigin->toArray() as $key => $p) {
                if (array_key_exists($key, (array) $par)) {
                    $par->{$key} = $parcelaOrigin->{$key};
                }
            }

            $par->valorefetivado = $parcelaOrigin->valorefetivado - $dataUpdate['juros'] - $dataUpdate['multa'];

            $boleto = $this->createBoleto($par, $conta, $boletoCreate, $descricaoParcela);

            $boleto->setCedente($conta->boletocedente . '-' . $conta->boletocedentedigito);
            $this->pdf->addBoleto($boleto);
            array_push($this->boletos, $boleto);

            if ($alterouVencimento && $boletoDb->pendencia) {
                throw new \Exception("Já foi dado um comando ao boleto referente à parcela "
                    . $boletoDb->financeiroparcela_id
                    . ". para gerar um novo comando, você deve gerar/cancelar uma remessa e efetivá-la");
            }

            $boletoPars = [];

            if (!$boletoGerado) {
                $boletoPars['nossonumero'] = substr($boleto->getNossoNumeroBoleto(), 0, strlen($boleto->getNossoNumeroBoleto()) - 2);
                $boletoPars['dv'] = substr($boleto->getNossoNumeroBoleto(), strlen($boleto->getNossoNumeroBoleto()) - 1);
                $boletoPars['alterouvencimento'] = $alterouVencimento;
                $boletoCreate->update($boletoPars);
            }

            $args = [
                'gerouboleto'       => $boletoGerado ? 0 : 1,
                'alterouvencimento' => $alterouVencimento,
                'financeiro_id'     => $par->financeiro_id
            ];

            $this->setCodigoBanco($conta->banco->codigo);
            if ($alterouVencimento) {
                $ocorr = Ocorrenciasremessas::where('codigo_banco', $this->codigoBanco)
                    ->where('uso_banco', false)
                    ->where('tipo', '0')
                    ->where('codigo', '05')
                    ->get()->first();
                if (is_null($ocorr)) {
                    throw new \Exception('Erro ao buscar as ocorrências, contate o suporte');
                }
                $args['ocorrencia_id'] = $ocorr->id;
            }

            if (!$this->insertBoletoHistorico($boletoCreate, $args)) {
                return false;
            }

            if ($imprimiu) {
                continue;
            }

            $upBol = ['imprimiu' => true, 'pendencia' => true];
            if (array_key_exists('ocorrencia_id', $args)) {
                $upBol['utilmaocorrencia_id'] = $args['ocorrencia_id'];
            }
            $boletoCreate->update($upBol);

            if (!$boletoGerado) {
                $conta->update(['boletosequencia' => $this->boletoSequencia]);
            }
        }

        return true;
    }

    public function isAbatimento($boletoCreate)
    {
        $ocorrencia = Ocorrenciasremessas::find($boletoCreate->ultimaocorrencia_id);

        $abatimento = false;
        $this->cancelaAbatimento = false;
        if (!is_null($ocorrencia)) {
            if ($ocorrencia->codigo == '04') {
                $this->cancelaAbatimento = true;
            } elseif ($ocorrencia->codigo == '03') {
                $abatimento = true;
            }
        }
        return $abatimento;
    }

    public function getContaById($conta_id)
    {
        return SelectRepository::searchConta(['id' => $conta_id], null, null, null, null, ['banco'])->get()->first();
    }

    public function getBoletosByParcelasId(array $parcelas_id)
    {
        $whereIn = ['financeiroparcela_id' => $parcelas_id];
        return SelectRepository::searchBoletos(null, null, null, null, $whereIn)->get();
    }

    public function getParcelasById(array $parcelas_id)
    {
        $select = [
            'financeiroparcelas.id',
            'descricao',
            'datavencimento',
            'boletogerado',
            'financeiroparcelas.financeiro_id',
            'documento',
            'desconto',
            'financeiroparcelas.valor',
            'financeiroparcelas.valorefetivado'
        ];

        $joins = ['financeiro' => 'financeiros'];
        $whereIn = ['financeiroparcelas.id' => $parcelas_id];

        return SelectRepository::searchParcelas(null, $select, $joins, null, $whereIn)->get();
    }

    private function insertUpdateBoleto($boletoGerado, $conta, $boletoDb, $par, $alterouVencimento)
    {
        $boletoPars = [];
        if ($boletoGerado) {
            $alterouVencimento = $boletoDb->alterouvencimento ? true : $alterouVencimento;
            $alterouVencimento = $alterouVencimento ? $boletoDb->gerouremessa : false;
            $boletoDb->update([
                'alterouvencimento' => $alterouVencimento
            ]);
            $boletoCreate = $boletoDb;
        } else {
            $boletoPars['datahora'] = Carbon::now();
            $boletoPars['numerosequencia'] = $conta->boletosequencia + 1;
            $boletoPars['gerouremessa'] = false;
            $boletoPars['cancelado'] = false;
            $boletoPars['nossonumero'] = 0;
            $boletoPars['dv'] = 0;
            $boletoPars['financeiroparcela_id'] = $par->id;
            $boletoPars['financeiro_id'] = $par->financeiro_id;
            $boletoPars['conta_id'] = $conta->id;
            $boletoPars['alteroucancelou'] = false;
            $boletoPars['alterouvencimento'] = false;
            $boletoPars['empresa_id'] = Session::get('empresa_padrao')->id;
            $boletoPars['grupo_id'] = Session::get('empresa_padrao')->grupo_id;
            $boletoPars['imprimiu'] = false;
            $this->createdNow = true;
            $boletoCreate = Boleto::create($boletoPars);
        }
        $this->boletoSequencia = $boletoCreate->numerosequencia;
        return $boletoCreate;
    }

    private function saveArchive($remessa, $file)
    {
        $s = DIRECTORY_SEPARATOR;
        $cnpj = str_replace(['/', '.', '-'], '', Session::get('empresa_padrao')->cnpj);
        $has = Storage::disk('boletos')->has($cnpj);
        if (!$has) {
            Storage::disk('boletos')->makeDirectory($cnpj);
        }
        try {
            $title = 'REM_' . $remessa->id . '.rem';
            $path = $s . $cnpj . $s . $title;
            $archive = file_get_contents($file);
            $putf = Storage::disk('boletos')->put($path, $archive);
        } catch (\Exception $e) {
            $error = getErrorsException($e);
            return $error;
        }
        return true;
    }
}
