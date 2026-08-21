package br.inf.qti.movelapp;

import java.util.LinkedList;

/**
 * Created by flavio on 24/06/2014.
 */
public class Boleto {
    public String vencimento;
    public double valor;
    public double multa;
    public double juros;
    public String nossoNumero;
    public String dvNossoNumero;
    public String documento;
    public String pagadorNome;
    public String pagadorEndereco;
    public String pagadorBairro;
    public String pagadorCep;
    public String pagadorUf;
    public String pagadorCidade;
    public String pagadorDocumento;
    public String benefNome;
    public String benefEndereco;
    public String benefBairro;
    public String benefCep;
    public String benefUf;
    public String benefCidade;
    public String benefDocumento;
    public String agencia;
    public String conta;
    public String dvConta;
    public String carteira;
    public String codigoCliente;
    public String aceite;
    public String especie;
    public String dataDocumento;
    public String cedente;
    public String linhaDigitavel;
    public String codigoBarras;
    public String banco;
    public String bancoDv;
    public LinkedList<String> instrucoes;
    public LinkedList<String> localPagamento;

    public Boleto() {
    }

    public String getVencimento() {
        return vencimento;
    }

    public void setVencimento(String vencimento) {
        this.vencimento = vencimento;
    }

    public double getValor() {
        return valor;
    }

    public void setValor(double valor) {
        this.valor = valor;
    }

    public double getMulta() {
        return multa;
    }

    public void setMulta(double multa) {
        this.multa = multa;
    }

    public double getJuros() {
        return juros;
    }

    public void setJuros(double juros) {
        this.juros = juros;
    }

    public String getNossoNumero() {
        return nossoNumero;
    }

    public void setNossoNumero(String nossoNumero) {
        this.nossoNumero = nossoNumero;
    }

    public String getDvNossoNumero() {
        return dvNossoNumero;
    }

    public void setDvNossoNumero(String dvNossoNumero) {
        this.dvNossoNumero = dvNossoNumero;
    }

    public String getDocumento() {
        return documento;
    }

    public void setDocumento(String documento) {
        this.documento = documento;
    }

    public String getPagadorNome() {
        return pagadorNome;
    }

    public void setPagadorNome(String pagadorNome) {
        this.pagadorNome = pagadorNome;
    }

    public String getPagadorEndereco() {
        return pagadorEndereco;
    }

    public void setPagadorEndereco(String pagadorEndereco) {
        this.pagadorEndereco = pagadorEndereco;
    }

    public String getPagadorBairro() {
        return pagadorBairro;
    }

    public void setPagadorBairro(String pagadorBairro) {
        this.pagadorBairro = pagadorBairro;
    }

    public String getPagadorCep() {
        return pagadorCep;
    }

    public void setPagadorCep(String pagadorCep) {
        this.pagadorCep = pagadorCep;
    }

    public String getPagadorUf() {
        return pagadorUf;
    }

    public void setPagadorUf(String pagadorUf) {
        this.pagadorUf = pagadorUf;
    }

    public String getPagadorCidade() {
        return pagadorCidade;
    }

    public void setPagadorCidade(String pagadorCidade) {
        this.pagadorCidade = pagadorCidade;
    }

    public String getPagadorDocumento() {
        return pagadorDocumento;
    }

    public void setPagadorDocumento(String pagadorDocumento) {
        this.pagadorDocumento = pagadorDocumento;
    }

    public String getBenefNome() {
        return benefNome;
    }

    public void setBenefNome(String benefNome) {
        this.benefNome = benefNome;
    }

    public String getBenefEndereco() {
        return benefEndereco;
    }

    public void setBenefEndereco(String benefEndereco) {
        this.benefEndereco = benefEndereco;
    }

    public String getBenefBairro() {
        return benefBairro;
    }

    public void setBenefBairro(String benefBairro) {
        this.benefBairro = benefBairro;
    }

    public String getBenefCep() {
        return benefCep;
    }

    public void setBenefCep(String benefCep) {
        this.benefCep = benefCep;
    }

    public String getBenefUf() {
        return benefUf;
    }

    public void setBenefUf(String benefUf) {
        this.benefUf = benefUf;
    }

    public String getBenefCidade() {
        return benefCidade;
    }

    public void setBenefCidade(String benefCidade) {
        this.benefCidade = benefCidade;
    }

    public String getBenefDocumento() {
        return benefDocumento;
    }

    public void setBenefDocumento(String benefDocumento) {
        this.benefDocumento = benefDocumento;
    }

    public String getAgencia() {
        return agencia;
    }

    public void setAgencia(String agencia) {
        this.agencia = agencia;
    }

    public String getConta() {
        return conta;
    }

    public void setConta(String conta) {
        this.conta = conta;
    }

    public String getDvConta() {
        return dvConta;
    }

    public void setDvConta(String dvConta) {
        this.dvConta = dvConta;
    }

    public String getCarteira() {
        return carteira;
    }

    public void setCarteira(String carteira) {
        this.carteira = carteira;
    }

    public String getCodigoCliente() {
        return codigoCliente;
    }

    public void setCodigoCliente(String codigoCliente) {
        this.codigoCliente = codigoCliente;
    }

    public String getAceite() {
        return aceite;
    }

    public void setAceite(String aceite) {
        this.aceite = aceite;
    }

    public String getEspecie() {
        return especie;
    }

    public void setEspecie(String especie) {
        this.especie = especie;
    }

    public String getDataDocumento() {
        return dataDocumento;
    }

    public void setDataDocumento(String dataDocumento) {
        this.dataDocumento = dataDocumento;
    }

    public String getCedente() {
        return cedente;
    }

    public void setCedente(String cedente) {
        this.cedente = cedente;
    }

    public LinkedList<String> getInstrucoes() {
        return instrucoes;
    }

    public void setInstrucoes(LinkedList<String> instrucoes) {
        this.instrucoes = instrucoes;
    }

    public LinkedList<String> getLocalPagamento() {
        return localPagamento;
    }

    public void setLocalPagamento(LinkedList<String> localPagamento) {
        this.localPagamento = localPagamento;
    }

    public String getLinhaDigitavel() {
        return linhaDigitavel;
    }

    public void setLinhaDigitavel(String linhaDigitavel) {
        this.linhaDigitavel = linhaDigitavel;
    }

    public String getCodigoBarras() {
        return codigoBarras;
    }

    public void setCodigoBarras(String codigoBarras) {
        this.codigoBarras = codigoBarras;
    }

    public String getBanco() {
        return banco;
    }

    public void setBanco(String banco) {
        this.banco = banco;
    }

    public String getBancoDv() {
        return bancoDv;
    }

    public void setBancoDv(String bancoDv) {
        this.bancoDv = bancoDv;
    }
}
