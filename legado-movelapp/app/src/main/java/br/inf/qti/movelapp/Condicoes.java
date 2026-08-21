package br.inf.qti.movelapp;

/**
 * Created by flavio on 17/06/2014.
 */
public class Condicoes {
    public int codigo;
    public String descricao;

    public Condicoes(int codigo, String descricao) {
        this.codigo = codigo;
        this.descricao = descricao;
    }

    public int getId() {
        return codigo;
    }

    @Override
    public String toString() {
        return descricao;
    }

    public String getDescricao() {
        return descricao;
    }

}
