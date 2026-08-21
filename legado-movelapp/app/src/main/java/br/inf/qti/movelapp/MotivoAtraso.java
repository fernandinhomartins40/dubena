package br.inf.qti.movelapp;

/**
 * Created by flavio on 08/09/2015.
 */
public class MotivoAtraso {
    public int codigo;
    public String descricao;

    public MotivoAtraso(int codigo, String descricao) {
        this.codigo = codigo;
        this.descricao = descricao;
    }

    public int getId() { return codigo; }

    @Override
    public String toString(){
        return descricao;
    }

}
