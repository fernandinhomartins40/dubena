package br.inf.qti.nfweb;

/**
 * Created by flavio on 20/06/2014.
 */
public class NotafiscalItem {

    public int codigo;
    public int cod_pedido;
    public int cod_produto;
    public Double quantidade;
    public String descricao;
    public String unidade;
    public Double preco;
    public Double preco_origem;
    public Double valor_total;
    public String cst;
    public int aliq;
    public Double valor_desconto;
    public Double quantidade_troca;
    public int num_nf_venda;

    public NotafiscalItem(int codigo, int cod_pedido, int cod_produto, double quantidade, String descricao, String unidade, double preco, double preco_origem, double valor_total) {
        this.codigo = codigo;
        this.cod_pedido = cod_pedido;
        this.cod_produto = cod_produto;
        this.quantidade = quantidade;
        this.descricao = descricao;
        this.unidade = unidade;
        this.preco = preco;
        this.preco_origem = preco_origem;
        this.valor_total = valor_total;
        this.cst=cst;
        this.aliq=aliq;
        this.valor_desconto=valor_desconto;
    }

    public NotafiscalItem(int codigo, int nf, double quantidade, String descricao) {
        this.codigo = codigo;
        this.num_nf_venda = nf;
        this.quantidade = quantidade;
        this.descricao = descricao;
    }

    public int getId() {
        return codigo;
    }

    @Override
    public String toString() {
        return descricao;
    }

    public int getCodProduto() {
        return cod_produto;
    }

    public int getCodPedido() {
        return cod_pedido;
    }

    public double getQuantidade() {
        return quantidade;
    }

    public double getQuantidadeTroca() {
        return quantidade_troca;
    }

    public String getDescricao() {
        return descricao;
    }

    public String getUnidade() {
        return unidade;
    }

    public double getPreco() {
        return  preco;
    }

    public double getPrecoOrigem() {
        return  preco_origem;
    }

    public double getValorTotal(){
        return valor_total;
    }

    public String getCst() {
        return cst;
    }

    public int getAliq() {
        return aliq;
    }

    public int getNFVenda() {
        return num_nf_venda;
    }

    public Double getValorDesconto() {
        return valor_desconto;
    }

    public void setCst(String cst) {
        this.cst = cst;
    }

    public void setAliq(int aliq) {
        this.aliq = aliq;
    }

    public void setValorDesconto(Double valor_desconto) {
        this.valor_desconto = valor_desconto;
    }

    public void setQuantidadeTroca(Double quantidade_troca) {
        this.quantidade_troca = quantidade_troca;
    }

    public void setNFVenda(int nf) {
        this.num_nf_venda = nf;
    }
}
