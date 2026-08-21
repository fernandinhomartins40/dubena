package br.inf.qti.movelapp;

/**
 * Created by flavio on 08/09/2015.
 */
public class Veiculo {
    public int codigo;
    public String descricao;
    public String placa;
    public int ativo;

    public Veiculo(int codigo, String descricao, String placa, int ativo) {
        this.codigo = codigo;
        this.descricao = descricao;
        this.placa = placa;
        this.ativo = ativo;
    }

    public int getId() { return codigo; }

    @Override
    public String toString(){
        return descricao;
    }

    public String getPlaca() {
        return placa;
    }

    public void setPlaca(String placa) {
        this.placa = placa;
    }

    public int getAtivo() {
        return ativo;
    }

    public void setAtivo(int ativo) {
        this.ativo = ativo;
    }
}
