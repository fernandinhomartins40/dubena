<?php

namespace App\Domain\Fiscal;

use App\Models\Fiscal\NotaFiscal;
use Illuminate\Validation\ValidationException;
use NFePHP\NFe\Make;

/**
 * XmlNfeBuilder (C7b) — monta o XML da NF-e/NFC-e com a lib NFePHP (Make),
 * espelhando o MakeXml/TagMaker do legado: ide → emit → dest → det (por item,
 * com prod + imposto já calculado na C7a) → total → transp → pag.
 *
 * Não reimplementa a serialização de tags (a lib faz isso e a SEFAZ valida o
 * schema). O que porta é a MONTAGEM (quais campos preenchem cada grupo) a partir
 * do nosso modelo. A assinatura/transmissão é responsabilidade do SefazDriver.
 *
 * GATE: requer dados fiscais completos (emitente com CNPJ/IE, certificado etc.).
 * Em CI/homolog usa-se o FakeSefazDriver, que não chama este builder.
 */
class XmlNfeBuilder
{
    /**
     * Constrói o objeto Make da nota. Retorna o Make pronto para
     * monta()/getXML() (a assinatura é feita pelo driver com o certificado).
     *
     * @param  array<string,mixed>  $emitente  dados do emitente (cnpj, ie, razao, endereco...)
     * @param  array<string,mixed>  $destinatario  dados do destinatário
     */
    public function montar(NotaFiscal $nota, array $emitente, array $destinatario): Make
    {
        $nota->loadMissing('itens');
        $this->validarSnapshotTributario($nota);
        $make = new Make;

        $this->infNFe($make);
        $this->ide($make, $nota, $emitente);
        $this->emit($make, $emitente);
        $this->dest($make, $destinatario);

        foreach ($nota->itens as $item) {
            $this->det($make, $nota, $item);
        }

        $this->total($make, $nota);
        $this->transp($make);
        $this->pag($make, $nota);

        return $make;
    }

    /**
     * O schema atual ainda não persiste a decisão fiscal completa. Até existir
     * o snapshot versionado da F5, a emissão real deve falhar fechado em vez de
     * reconstruir tributos pela regra vigente ou inventar valores padrão.
     */
    private function validarSnapshotTributario(NotaFiscal $nota): void
    {
        $obrigatorios = [
            'origem_icms', 'modalidade_bc_icms', 'cst_pis', 'bc_pis',
            'cst_cofins', 'bc_cofins', 'bc_icms_st', 'aliq_icms_st',
            'valor_icms_st', 'perc_red_bc', 'perc_red_bc_st',
            'bc_fcp', 'aliq_fcp', 'valor_fcp', 'valor_difal_dest',
            'valor_difal_remet', 'valor_fcp_difal',
        ];

        foreach ($nota->itens as $item) {
            $ausentes = array_values(array_filter(
                $obrigatorios,
                fn (string $campo): bool => ! array_key_exists($campo, $item->getAttributes()),
            ));
            if ($ausentes !== []) {
                throw ValidationException::withMessages([
                    'snapshot_fiscal' => 'Emissão real bloqueada: item #'.(int) $item->numero_item
                        .' não possui snapshot tributário completo ('.implode(', ', $ausentes).').',
                ]);
            }
        }
    }

    private function infNFe(Make $make): void
    {
        $std = new \stdClass;
        $std->versao = '4.00';
        $make->taginfNFe($std);
    }

    private function ide(Make $make, NotaFiscal $nota, array $emitente): void
    {
        $std = new \stdClass;
        $std->cUF = (int) $emitente['cuf'];
        $std->natOp = $emitente['natureza_operacao'];
        $std->mod = (int) $nota->modelo->value;               // 55 / 65
        $std->serie = (int) $nota->serie;
        $std->nNF = (int) $nota->numero;
        $std->dhEmi = ($nota->emitida_em ?? now())->format('Y-m-d\TH:i:sP');
        $std->tpNF = $nota->tipo === 'E' ? 0 : 1;             // 0=entrada,1=saída
        $std->idDest = (int) $emitente['id_dest'];
        $std->cMunFG = (int) $emitente['cod_municipio'];
        $std->tpImp = 1;
        $std->tpEmis = 1;
        $std->tpAmb = (int) $emitente['ambiente'];
        $std->finNFe = 1;
        $std->indFinal = (int) $emitente['consumidor_final'];
        $std->indPres = 1;
        $std->procEmi = 0;
        $std->verProc = 'erp-novo';
        $make->tagide($std);
    }

    private function emit(Make $make, array $e): void
    {
        $std = new \stdClass;
        $std->xNome = $e['razao_social'];
        $std->xFant = $e['nome_fantasia'] ?? null;
        $std->IE = $e['ie'];
        $std->CRT = (int) $e['crt'];
        if (! empty($e['cnpj'])) {
            $std->CNPJ = $e['cnpj'];
        } else {
            $std->CPF = $e['cpf'] ?? null;
        }
        $make->tagemit($std);

        $end = new \stdClass;
        $end->xLgr = $e['logradouro'];
        $end->nro = $e['numero'];
        $end->xBairro = $e['bairro'];
        $end->cMun = (int) $e['cod_municipio'];
        $end->xMun = $e['municipio'];
        $end->UF = $e['uf'];
        $end->CEP = $e['cep'];
        $make->tagenderEmit($end);
    }

    private function dest(Make $make, array $d): void
    {
        $std = new \stdClass;
        $std->xNome = $d['nome'] ?? 'CONSUMIDOR';
        if (! empty($d['cnpj'])) {
            $std->CNPJ = $d['cnpj'];
            $std->indIEDest = (int) ($d['ind_ie_dest'] ?? 1);
        } elseif (! empty($d['cpf'])) {
            $std->CPF = $d['cpf'];
            $std->indIEDest = 9;
        } else {
            $std->indIEDest = 9;
        }
        $make->tagdest($std);
    }

    private function det(Make $make, NotaFiscal $nota, $item): void
    {
        $n = (int) $item->numero_item;

        $prod = new \stdClass;
        $prod->item = $n;
        $prod->cProd = (string) $item->produto_id;
        $prod->cEAN = 'SEM GTIN';
        $prod->xProd = $item->produto?->descricao ?? ('Item '.$n);
        $prod->NCM = $item->produto?->ncm ?? '00000000';
        $prod->CFOP = $item->cfop ?? '5102';
        $prod->uCom = 'UN';
        $prod->qCom = (float) $item->quantidade;
        $prod->vUnCom = (float) $item->valor_unitario;
        $prod->vProd = (float) $item->valor_total;
        $prod->cEANTrib = 'SEM GTIN';
        $prod->uTrib = 'UN';
        $prod->qTrib = (float) $item->quantidade;
        $prod->vUnTrib = (float) $item->valor_unitario;
        $prod->indTot = 1;
        $make->tagprod($prod);

        $imposto = new \stdClass;
        $imposto->item = $n;
        $make->tagimposto($imposto);

        // ICMS (grupo conforme CST; aqui o caso geral tributado/00).
        $icms = new \stdClass;
        $icms->item = $n;
        $icms->orig = (int) $item->origem_icms;
        $icms->CST = $item->cst_icms;
        $icms->modBC = (int) $item->modalidade_bc_icms;
        $icms->vBC = (float) $item->bc_icms;
        $icms->pICMS = (float) $item->aliq_icms;
        $icms->vICMS = (float) $item->valor_icms;
        $make->tagICMS($icms);

        // PIS/COFINS (CST tributado por percentual).
        $pis = new \stdClass;
        $pis->item = $n;
        $pis->CST = $item->cst_pis;
        $pis->vBC = (float) $item->bc_pis;
        $pis->pPIS = (float) $item->aliq_pis;
        $pis->vPIS = (float) $item->valor_pis;
        $make->tagPIS($pis);

        $cofins = new \stdClass;
        $cofins->item = $n;
        $cofins->CST = $item->cst_cofins;
        $cofins->vBC = (float) $item->bc_cofins;
        $cofins->pCOFINS = (float) $item->aliq_cofins;
        $cofins->vCOFINS = (float) $item->valor_cofins;
        $make->tagCOFINS($cofins);
    }

    private function total(Make $make, NotaFiscal $nota): void
    {
        $std = new \stdClass;
        $std->vBC = (float) $nota->valor_produtos;
        $std->vICMS = (float) $nota->valor_icms;
        $std->vICMSDeson = 0;
        $std->vBCST = (float) ($nota->valor_icms_st ?? 0);
        $std->vST = (float) ($nota->valor_icms_st ?? 0);
        $std->vProd = (float) $nota->valor_produtos;
        $std->vFrete = (float) ($nota->valor_frete ?? 0);
        $std->vSeg = 0;
        $std->vDesc = (float) $nota->valor_desconto;
        $std->vII = 0;
        $std->vIPI = (float) $nota->valor_ipi;
        $std->vPIS = (float) $nota->valor_pis;
        $std->vCOFINS = (float) $nota->valor_cofins;
        $std->vOutro = 0;
        $std->vNF = (float) $nota->valor_total;
        $make->tagICMSTot($std);
    }

    private function transp(Make $make): void
    {
        $std = new \stdClass;
        $std->modFrete = 9; // sem frete
        $make->tagtransp($std);
    }

    private function pag(Make $make, NotaFiscal $nota): void
    {
        $make->tagpag(new \stdClass);
        $detPag = new \stdClass;
        $detPag->tPag = '01'; // dinheiro (default; a forma real vem do pedido)
        $detPag->vPag = (float) $nota->valor_total;
        $make->tagdetPag($detPag);
    }
}
