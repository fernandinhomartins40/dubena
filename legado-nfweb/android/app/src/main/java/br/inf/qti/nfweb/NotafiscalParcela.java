package br.inf.qti.nfweb;

/**
 * Created by flavio on 25/06/2014.
 */
public class NotafiscalParcela {
    public int codigo;
    public int cod_pedido;
    public String vencimento;
    public Double valor;

    public NotafiscalParcela(int codigo, int cod_pedido, String vencimento, double valor){
        this.codigo=codigo;
        this.cod_pedido=cod_pedido;
        this.vencimento=vencimento;
        this.valor=valor;
    }

    public int getId() { return codigo; }

    @Override
    public String toString(){ return ""; }
    public int getCodPedido() { return cod_pedido; };
    public String getVencimento() { return  vencimento; }
    public Double getValor() { return  valor; }

    public String getVencimentoTexto(){
        return vencimento.substring(8,10) + "/" + vencimento.substring(5,7) + "/" + vencimento.substring(0,4);
    }
}
