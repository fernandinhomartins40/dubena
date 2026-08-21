package br.inf.qti.movelapp;

/**
 * Created by flavio on 08/09/2015.
 */
public class Situacao {
    public int codigo;
    public String descricao;
    public int entregaRealizada;
    public int entregaPendente;
    public int entregaCancelada;
    public int entregaTransferida;
    public int emEntrega;
    public int valeGas;
    public int mensagemEnviada;
    public int mensagemLida;
    public int cartao;

    public Situacao(int codigo, String descricao, int entregaRealizada, int entregaPendente, int entregaCancelada) {
        this.codigo = codigo;
        this.descricao = descricao;
        this.entregaRealizada = entregaRealizada;
        this.entregaPendente = entregaPendente;
        this.entregaCancelada = entregaCancelada;
    }

    public Situacao(int codigo, String descricao, int entregaRealizada, int entregaPendente, int entregaCancelada, int entregaTransferida, int emEntrega, int valeGas, int mensagemEnviada, int mensagemLida) {
        this.codigo = codigo;
        this.descricao = descricao;
        this.entregaRealizada = entregaRealizada;
        this.entregaPendente = entregaPendente;
        this.entregaCancelada = entregaCancelada;
        this.entregaTransferida = entregaTransferida;
        this.emEntrega = emEntrega;
        this.valeGas = valeGas;
        this.mensagemEnviada = mensagemEnviada;
        this.mensagemLida = mensagemLida;
    }

    public Situacao(int codigo, String descricao) {
        this.codigo = codigo;
        this.descricao = descricao;
    }

    public int getId() { return codigo; }

    @Override
    public String toString(){
        return descricao;
    }

    public int getEntregaRealizada() {
        return entregaRealizada;
    }

    public void setEntregaRealizada(int entregaRealizada) {
        this.entregaRealizada = entregaRealizada;
    }

    public int getEntregaPendente() {
        return entregaPendente;
    }

    public void setEntregaPendente(int entregaPendente) {
        this.entregaPendente = entregaPendente;
    }

    public int getEntregaCancelada() {
        return entregaCancelada;
    }

    public void setEntregaCancelada(int entregaCancelada) {
        this.entregaCancelada = entregaCancelada;
    }

    public int getEntregaTransferida() {
        return entregaTransferida;
    }

    public void setEntregaTransferida(int entregaTransferida) {
        this.entregaTransferida = entregaTransferida;
    }

    public int getEmEntrega() {
        return emEntrega;
    }

    public void setEmEntrega(int emEntrega) {
        this.emEntrega = emEntrega;
    }

    public int getValeGas() {
        return valeGas;
    }

    public void setValeGas(int valeGas) {
        this.valeGas = valeGas;
    }

    public int getMensagemEnviada() {
        return mensagemEnviada;
    }

    public void setMensagemEnviada(int mensagemEnviada) {
        this.mensagemEnviada = mensagemEnviada;
    }

    public int getMensagemLida() {
        return mensagemLida;
    }

    public void setMensagemLida(int mensagemLida) {
        this.mensagemLida = mensagemLida;
    }

    public int getCartao() {
        return cartao;
    }

    public void setCartao(int cartao) {
        this.cartao = cartao;
    }
}
