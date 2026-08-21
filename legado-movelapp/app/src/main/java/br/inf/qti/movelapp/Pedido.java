package br.inf.qti.movelapp;

import java.util.LinkedList;

/**
 * Created by flavio on 24/06/2014.
 */
public class Pedido {
    public int codigo;
    public String data_pedido;
    public String cliente;
    public String condicao;
    public Double valor_venda;
    public String rua;
    public String numero;
    public String complemento;
    public String observacao;
    public String bairro;
    public String ponto_referencia;
    public LinkedList<PedidoItem> itens;
    public int codStatus;
    public String descStatus;
    public String androidId;
    public Double quantidade;
    public String cidade;
    public String uf;
    public int codMotivoAtraso;
    public String urgente;
    public String longitude;
    public String latitude;
    public String convenio;
    public int cartao;
    public String app;
    public int gasdopovo;

    public Pedido(int codigo, String data_pedido, String cliente, String condicao, Double valor_venda, String rua, String numero, String complemento, String observacao, String bairro, String ponto_referencia, int codStatus, String descStatus) {
        this.codigo = codigo;
        this.data_pedido = data_pedido;
        this.cliente = cliente;
        this.condicao = condicao;
        this.valor_venda = valor_venda;
        this.rua = rua;
        this.numero = numero;
        this.complemento = complemento;
        this.observacao = observacao;
        this.bairro = bairro;
        this.ponto_referencia = ponto_referencia;
        this.codStatus = codStatus;
        this.descStatus = descStatus;
    }

    public int getId() { return codigo; }

    @Override
    public String toString(){ return ""; }

    public String getData_pedido() {
        return data_pedido;
    }

    public String getDataPedidoTexto() {
        try {
            return data_pedido.substring(8,10) + "/" + data_pedido.substring(5,7) + "/" + data_pedido.substring(0,4) + " " + data_pedido.substring(11,19);
            //return data_pedido;
        } catch (Exception e) {
            e.printStackTrace();
            return data_pedido;
        }
    }

    public String getHoraPedidoTexto() {
        return data_pedido.substring(11,19);
    }

    public void setData_pedido(String data_pedido) {
        this.data_pedido = data_pedido;
    }

    public String getCliente() {
        return cliente;
    }

    public void setCliente(String cliente) {
        this.cliente = cliente;
    }

    public String getCondicao() {
        return condicao;
    }

    public void setCondicao(String condicao) {
        this.condicao = condicao;
    }

    public Double getValor_venda() {
        return valor_venda;
    }

    public void setValor_venda(Double valor_venda) {
        this.valor_venda = valor_venda;
    }

    public String getRua() {
        return rua;
    }

    public void setRua(String rua) {
        this.rua = rua;
    }

    public String getNumero() {
        return numero;
    }

    public void setNumero(String numero) {
        this.numero = numero;
    }

    public String getComplemento() {
        return complemento;
    }

    public void setComplemento(String complemento) {
        this.complemento = complemento;
    }

    public String getObservacao() {
        return observacao;
    }

    public void setObservacao(String observacao) {
        this.observacao = observacao;
    }

    public String getBairro() {
        return bairro;
    }

    public void setBairro(String bairro) {
        this.bairro = bairro;
    }

    public String getPonto_referencia() {
        return ponto_referencia;
    }

    public void setPonto_referencia(String ponto_referencia) {
        this.ponto_referencia = ponto_referencia;
    }

    public LinkedList<PedidoItem> getItens() {
        return itens;
    }

    public void setItens(LinkedList<PedidoItem> itens) {
        this.itens = itens;
    }


    public int getCodStatus() {
        return codStatus;
    }

    public void setCodStatus(int codStatus) {
        this.codStatus = codStatus;
    }

    public String getDescStatus() {
        return descStatus;
    }

    public void setDescStatus(String descStatus) {
        this.descStatus = descStatus;
    }

    public String getAndroidId() {
        return androidId;
    }

    public void setAndroidId(String androidId) {
        this.androidId = androidId;
    }

    public Double getQuantidade() {
        return quantidade;
    }

    public void setQuantidade(Double quantidade) {
        this.quantidade = quantidade;
    }

    public String getCidade() {
        return cidade;
    }

    public void setCidade(String cidade) {
        this.cidade = cidade;
    }

    public String getUf() {
        return uf;
    }

    public void setUf(String uf) {
        this.uf = uf;
    }

    public int getCodMotivoAtraso() {
        return codMotivoAtraso;
    }

    public void setCodMotivoAtraso(int codMotivoAtraso) {
        this.codMotivoAtraso = codMotivoAtraso;
    }

    public String getUrgente() {
        return urgente;
    }

    public void setUrgente(String urgente) {
        this.urgente = urgente;
    }

    public String getLongitude() {
        return longitude;
    }

    public void setLongitude(String longitude) {
        this.longitude = longitude;
    }

    public String getLatitude() {
        return latitude;
    }

    public void setLatitude(String latitude) {
        this.latitude = latitude;
    }

    public String getConvenio() {
        return convenio;
    }

    public void setConvenio(String convenio) {
        this.convenio = convenio;
    }

    public int getCartao() {
        return cartao;
    }

    public void setCartao(int cartao) {
        this.cartao = cartao;
    }

    public String getApp() {
        return app;
    }

    public void setApp(String app) {
        this.app = app;
    }

    public int getGasdopovo() {
        return gasdopovo;
    }

    public void setGasdopovo(int gasdopovo) {
        this.gasdopovo = gasdopovo;
    }
}
