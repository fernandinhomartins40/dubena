package br.inf.qti.movelapp;

import java.util.LinkedList;

/**
 * Created by flavio on 24/06/2014.
 */
public class NotaFiscal {
    public int codigoSeq;
    public int numNf;
    public String dataEmissao;
    public String dataSaida;
    public String Operacao;
    public String Tipo; // entrada ou saida
    public String chaveAcesso;
    public String Serie;
    public String emitRazaoSocial;
    public String emitEndereco;
    public String emitCidade;
    public String emitUF;
    public String emitCEP;
    public String emitTelefone;
    public String emitCNPJ;
    public String emitIE;
    public String destRazaoSocial;
    public String destEndereco;
    public String destCidade;
    public String destUF;
    public String destCEP;
    public String destTelefone;
    public String destCNPJ;
    public String destIE;
    public LinkedList<NotafiscalItem> itens;
    public LinkedList<NotafiscalParcela> parcelas;
    public double valorProdutos;
    public double vBCICMS;
    public double vICMS;
    public double vBCICMSST;
    public double vICMSST;
    public double vFrete;
    public double vTotalNF;
    public double vSeguro;
    public double vDesconto;
    public double vOutro;
    public double vIpi;
    public String informacoesAdicionais;
    public String nfmodelo;
    public String condicaoPagamento;
    public double vTotTrib;
    public String DataHoraAutorizacao;
    public String infCpl;
    public String qrCode;
    public String protocolo;

    public NotaFiscal() {
    }

    public NotaFiscal(int codigoSeq, int numNf, String dataEmissao, String dataSaida, String operacao, String tipo, String chaveAcesso, String serie, String emitRazaoSocial, String emitEndereco, String emitCidade, String emitUF, String emitCEP, String emitTelefone, String emitCNPJ, String emitIE, String destRazaoSocial, String destEndereco, String destCidade, String destUF, String destCEP, String destTelefone, String destCNPJ, String destIE, double valorProdutos) {
        this.codigoSeq = codigoSeq;
        this.numNf = numNf;
        this.dataEmissao = dataEmissao;
        this.dataSaida = dataSaida;
        Operacao = operacao;
        Tipo = tipo;
        this.chaveAcesso = chaveAcesso;
        Serie = serie;
        this.emitRazaoSocial = emitRazaoSocial;
        this.emitEndereco = emitEndereco;
        this.emitCidade = emitCidade;
        this.emitUF = emitUF;
        this.emitCEP = emitCEP;
        this.emitTelefone = emitTelefone;
        this.emitCNPJ = emitCNPJ;
        this.emitIE = emitIE;
        this.destRazaoSocial = destRazaoSocial;
        this.destEndereco = destEndereco;
        this.destCidade = destCidade;
        this.destUF = destUF;
        this.destCEP = destCEP;
        this.destTelefone = destTelefone;
        this.destCNPJ = destCNPJ;
        this.destIE = destIE;
        this.itens = new LinkedList<NotafiscalItem>();
        this.parcelas = new LinkedList<NotafiscalParcela>();
        this.valorProdutos = valorProdutos;
    }

    public int getId() { return numNf; }

    public int getCodigoSeq() {
        return codigoSeq;
    }

    public void setCodigoSeq(int codigoSeq) {
        this.codigoSeq = codigoSeq;
    }

    public int getNumNf() {
        return numNf;
    }

    public void setNumNf(int numNf) {
        this.numNf = numNf;
    }

    public String getDataEmissao() {
        return dataEmissao;
    }

    public void setDataEmissao(String dataEmissao) {
        this.dataEmissao = dataEmissao;
    }

    public String getDataSaida() {
        return dataSaida;
    }

    public void setDataSaida(String dataSaida) {
        this.dataSaida = dataSaida;
    }

    public String getOperacao() {
        return Operacao;
    }

    public void setOperacao(String operacao) {
        Operacao = operacao;
    }

    public void setValorProdutos(double valorProdutos) {
        this.valorProdutos = valorProdutos;
    }

    public String getTipo() {
        return Tipo;
    }

    public String getTipoDescricao() {
        return Tipo + (Tipo.equals("1") ? "-Saida" : "-Entrada");
    }

    public void setTipo(String tipo) {
        Tipo = tipo;
    }

    public String getChaveAcesso() {
        return chaveAcesso;
    }

    public void setChaveAcesso(String chaveAcesso) {
        this.chaveAcesso = chaveAcesso;
    }

    public String getSerie() {
        return Serie;
    }

    public void setSerie(String serie) {
        Serie = serie;
    }

    public String getEmitRazaoSocial() {
        return emitRazaoSocial;
    }

    public void setEmitRazaoSocial(String emitRazaoSocial) {
        this.emitRazaoSocial = emitRazaoSocial;
    }

    public String getEmitEndereco() {
        return emitEndereco;
    }

    public void setEmitEndereco(String emitEndereco) {
        this.emitEndereco = emitEndereco;
    }

    public String getEmitCidade() {
        return emitCidade;
    }

    public void setEmitCidade(String emitCidade) {
        this.emitCidade = emitCidade;
    }

    public String getEmitUF() {
        return emitUF;
    }

    public void setEmitUF(String emitUF) {
        this.emitUF = emitUF;
    }

    public String getEmitCEP() {
        return emitCEP;
    }

    public void setEmitCEP(String emitCEP) {
        this.emitCEP = emitCEP;
    }

    public String getEmitTelefone() {
        return emitTelefone;
    }

    public void setEmitTelefone(String emitTelefone) {
        this.emitTelefone = emitTelefone;
    }

    public String getEmitCNPJ() {
        return emitCNPJ;
    }

    public void setEmitCNPJ(String emitCNPJ) {
        this.emitCNPJ = emitCNPJ;
    }

    public String getEmitIE() {
        return emitIE;
    }

    public void setEmitIE(String emitIE) {
        this.emitIE = emitIE;
    }

    public String getDestRazaoSocial() {
        return destRazaoSocial;
    }

    public void setDestRazaoSocial(String destRazaoSocial) {
        this.destRazaoSocial = destRazaoSocial;
    }

    public String getDestEndereco() {
        return destEndereco;
    }

    public void setDestEndereco(String destEndereco) {
        this.destEndereco = destEndereco;
    }

    public String getDestCidade() {
        return destCidade;
    }

    public void setDestCidade(String destCidade) {
        this.destCidade = destCidade;
    }

    public String getDestUF() {
        return destUF;
    }

    public void setDestUF(String destUF) {
        this.destUF = destUF;
    }

    public String getDestCEP() {
        return destCEP;
    }

    public void setDestCEP(String destCEP) {
        this.destCEP = destCEP;
    }

    public String getDestTelefone() {
        return destTelefone;
    }

    public void setDestTelefone(String destTelefone) {
        this.destTelefone = destTelefone;
    }

    public String getDestCNPJ() {
        return destCNPJ;
    }

    public void setDestCNPJ(String destCNPJ) {
        this.destCNPJ = destCNPJ;
    }

    public String getDestIE() {
        return destIE;
    }

    public void setDestIE(String destIE) {
        this.destIE = destIE;
    }

    public LinkedList<NotafiscalItem> getItens() {
        return itens;
    }

    public void setItens(LinkedList<NotafiscalItem> itens) {
        this.itens = itens;
    }
    public String getDataEmissaoTexto(){
        return dataEmissao.substring(8,10) + "/" + dataEmissao.substring(5,7) + "/" + dataEmissao.substring(0,4);
    }
    public String getDataSaidaTexto(){
        return dataSaida.substring(8,10) + "/" + dataSaida.substring(5,7) + "/" + dataSaida.substring(0,4);
    }
    public String getHoraSaidaTexto(){
        return dataSaida.substring(11,16);
    }
    public double getValorProdutos(){
        return valorProdutos;
    }

    public LinkedList<NotafiscalParcela> getParcelas() {
        return parcelas;
    }

    public void setParcelas(LinkedList<NotafiscalParcela> parcelas) {
        this.parcelas = parcelas;
    }

    public double getvBCICMS() {
        return vBCICMS;
    }

    public void setvBCICMS(double vBCICMS) {
        this.vBCICMS = vBCICMS;
    }

    public double getvICMS() {
        return vICMS;
    }

    public void setvICMS(double vICMS) {
        this.vICMS = vICMS;
    }

    public double getvBCICMSST() {
        return vBCICMSST;
    }

    public void setvBCICMSST(double vBCICMSST) {
        this.vBCICMSST = vBCICMSST;
    }

    public double getvICMSST() {
        return vICMSST;
    }

    public void setvICMSST(double vICMSST) {
        this.vICMSST = vICMSST;
    }

    public double getvFrete() {
        return vFrete;
    }

    public void setvFrete(double vFrete) {
        this.vFrete = vFrete;
    }

    public double getvTotalNF() {
        return vTotalNF;
    }

    public void setvTotalNF(double vTotalNF) {
        this.vTotalNF = vTotalNF;
    }

    public double getvSeguro() {
        return vSeguro;
    }

    public void setvSeguro(double vSeguro) {
        this.vSeguro = vSeguro;
    }

    public double getvDesconto() {
        return vDesconto;
    }

    public void setvDesconto(double vDesconto) {
        this.vDesconto = vDesconto;
    }

    public double getvOutro() {
        return vOutro;
    }

    public void setvOutro(double vOutro) {
        this.vOutro = vOutro;
    }

    public double getvIpi() {
        return vIpi;
    }

    public void setvIpi(double vIpi) {
        this.vIpi = vIpi;
    }

    public String getInformacoesAdicionais() {
        return informacoesAdicionais;
    }

    public void setInformacoesAdicionais(String informacoesAdicionais) {
        this.informacoesAdicionais = informacoesAdicionais;
    }

    public String getNfmodelo() {
        return nfmodelo;
    }

    public void setNfmodelo(String nfmodelo) {
        this.nfmodelo = nfmodelo;
    }

    public String getCondicaoPagamento() {
        return condicaoPagamento;
    }

    public void setCondicaoPagamento(String condicaoPagamento) {
        this.condicaoPagamento = condicaoPagamento;
    }

    public double getvTotTrib() {
        return vTotTrib;
    }

    public void setvTotTrib(double vTotTrib) {
        this.vTotTrib = vTotTrib;
    }

    public String getDataHoraAutorizacao() {
        return DataHoraAutorizacao.substring(8,10) + "/" + DataHoraAutorizacao.substring(5,7) + "/" + DataHoraAutorizacao.substring(0,4) + " " + DataHoraAutorizacao.substring(11,19);
    }

    public void setDataHoraAutorizacao(String dataHoraAutorizacao) {
        DataHoraAutorizacao = dataHoraAutorizacao;
    }

    public String getInfCpl() {
        return infCpl;
    }

    public void setInfCpl(String infCpl) {
        this.infCpl = infCpl;
    }

    public String getQrCode() {
        return qrCode;
    }

    public void setQrCode(String qrCode) {
        this.qrCode = qrCode;
    }

    public String getProtocolo() {
        return protocolo;
    }

    public void setProtocolo(String protocolo) {
        this.protocolo = protocolo;
    }
}
