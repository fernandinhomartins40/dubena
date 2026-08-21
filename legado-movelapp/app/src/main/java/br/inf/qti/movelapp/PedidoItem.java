package br.inf.qti.movelapp;

/**
 * Created by flavio on 20/06/2014.
 */
public class PedidoItem {

    public int codigo;
    public int cod_pedido;
    public String produto;
    public Double quantidade;
    public Double preco;
    public Double valor_total;
    public String unid_med;

    public PedidoItem(int codigo, int cod_pedido, String produto, Double quantidade, Double preco, Double valor_total, String unid_med) {
        this.codigo = codigo;
        this.cod_pedido = cod_pedido;
        this.produto = produto;
        this.quantidade = quantidade;
        this.preco = preco;
        this.valor_total = valor_total;
        this.unid_med = unid_med;
    }

    public int getId() {
        return codigo;
    }

    @Override
    public String toString() {
        return produto;
    }

    public int getCod_pedido() {
        return cod_pedido;
    }

    public void setCod_pedido(int cod_pedido) {
        this.cod_pedido = cod_pedido;
    }

    public String getProduto() {
        return produto;
    }

    public void setProduto(String produto) {
        this.produto = produto;
    }

    public Double getQuantidade() {
        return quantidade;
    }

    public void setQuantidade(Double quantidade) {
        this.quantidade = quantidade;
    }

    public Double getPreco() {
        return preco;
    }

    public void setPreco(Double preco) {
        this.preco = preco;
    }

    public Double getValor_total() {
        return valor_total;
    }

    public void setValor_total(Double valor_total) {
        this.valor_total = valor_total;
    }

    public String getUnid_med() {
        return unid_med;
    }

    public void setUnid_med(String unid_med) {
        this.unid_med = unid_med;
    }
}
