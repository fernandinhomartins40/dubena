<?php

namespace App\Domain\Fiscal;

use App\Models\Fiscal\NotaFiscal;
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

    private function infNFe(Make $make): void
    {
        $std = new \stdClass;
        $std->versao = '4.00';
        $make->taginfNFe($std);
    }

    private function ide(Make $make, NotaFiscal $nota, array $emitente): void
    {
        $std = new \stdClass;
        $std->cUF = (int) ($emitente['cuf'] ?? 35);          // código IBGE da UF
        $std->natOp = $emitente['natureza_operacao'] ?? 'VENDA';
        $std->mod = (int) $nota->modelo->value;               // 55 / 65
        $std->serie = (int) $nota->serie;
        $std->nNF = (int) $nota->numero;
        $std->dhEmi = ($nota->emitida_em ?? now())->format('Y-m-d\TH:i:sP');
        $std->tpNF = $nota->tipo === 'E' ? 0 : 1;             // 0=entrada,1=saída
        $std->idDest = (int) ($emitente['id_dest'] ?? 1);    // 1=interna
        $std->cMunFG = (int) ($emitente['cod_municipio'] ?? 0);
        $std->tpImp = 1;
        $std->tpEmis = 1;
        $std->tpAmb = (int) ($emitente['ambiente'] ?? 2);    // 2=homologação
        $std->finNFe = 1;
        $std->indFinal = (int) ($emitente['consumidor_final'] ?? 1);
        $std->indPres = 1;
        $std->procEmi = 0;
        $std->verProc = 'erp-novo';
        $make->tagide($std);
    }

    private function emit(Make $make, array $e): void
    {
        $std = new \stdClass;
        $std->xNome = $e['razao_social'] ?? '';
        $std->xFant = $e['nome_fantasia'] ?? null;
        $std->IE = $e['ie'] ?? null;
        $std->CRT = (int) ($e['crt'] ?? 3);                  // 3=regime normal
        if (! empty($e['cnpj'])) {
            $std->CNPJ = $e['cnpj'];
        } else {
            $std->CPF = $e['cpf'] ?? null;
        }
        $make->tagemit($std);

        $end = new \stdClass;
        $end->xLgr = $e['logradouro'] ?? 'N/I';
        $end->nro = $e['numero'] ?? 'S/N';
        $end->xBairro = $e['bairro'] ?? 'N/I';
        $end->cMun = (int) ($e['cod_municipio'] ?? 0);
        $end->xMun = $e['municipio'] ?? 'N/I';
        $end->UF = $e['uf'] ?? 'SP';
        $end->CEP = $e['cep'] ?? null;
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
        $icms->orig = 0;
        $icms->CST = $item->cst_icms ?? '00';
        $icms->modBC = 3;
        $icms->vBC = (float) $item->bc_icms;
        $icms->pICMS = (float) $item->aliq_icms;
        $icms->vICMS = (float) $item->valor_icms;
        $make->tagICMS($icms);

        // PIS/COFINS (CST tributado por percentual).
        $pis = new \stdClass;
        $pis->item = $n;
        $pis->CST = '01';
        $pis->vBC = (float) $item->valor_total;
        $pis->pPIS = (float) $item->aliq_pis;
        $pis->vPIS = (float) $item->valor_pis;
        $make->tagPIS($pis);

        $cofins = new \stdClass;
        $cofins->item = $n;
        $cofins->CST = '01';
        $cofins->vBC = (float) $item->valor_total;
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
