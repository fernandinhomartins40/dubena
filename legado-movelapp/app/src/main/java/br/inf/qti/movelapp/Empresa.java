package br.inf.qti.movelapp;

/**
 * Created by flavio on 13/06/2014.
 */
public class Empresa {

    public int codigo;
    public String razao_social;
    public String nome_fantasia;
    public String servidor;
    public int validaAtraso;
    public int validaGB;
    public int tempoEntrega;
    public int tempoEntregaUrgente;
    public int validaCoordenadas;
    public int validaPix;

    public Empresa(int codigo, String razao_social, String nome_fantasia, String servidor){
        this.codigo=codigo;
        this.razao_social=razao_social;
        this.nome_fantasia=nome_fantasia;
        this.servidor=servidor;
    }

    public Empresa(int codigo, String razao_social){
        this.codigo=codigo;
        this.razao_social=razao_social;
    }

    public int getId() { return codigo; }

    @Override
    public String toString(){
        return razao_social;
    }

    public String getRazaoSocial() {
        return razao_social;
    }
    public String getNomeFantasia(){ return  nome_fantasia; }

    public void setId(int id){
        this.codigo = id;
    }

    public String getServidor() {
        return servidor;
    }

    public void setServidor(String servidor) {
        this.servidor = servidor;
    }

    public int getValidaAtraso() {
        return validaAtraso;
    }

    public void setValidaAtraso(int validaAtraso) {
        this.validaAtraso = validaAtraso;
    }

    public int getValidaPix() {
        return validaPix;
    }

    public void setValidaPix(int validaPix) {
        this.validaPix = validaPix;
    }

    public int getValidaGB() {
        return validaGB;
    }

    public void setValidaGB(int validaGB) {
        this.validaGB = validaGB;
    }

    public int getTempoEntrega() {
        return tempoEntrega;
    }

    public void setTempoEntrega(int tempoEntrega) {
        this.tempoEntrega = tempoEntrega;
    }

    public int getTempoEntregaUrgente() {
        return tempoEntregaUrgente;
    }

    public void setTempoEntregaUrgente(int tempoEntregaUrgente) {
        this.tempoEntregaUrgente = tempoEntregaUrgente;
    }

    public int getValidaCoordenadas() {
        return validaCoordenadas;
    }

    public void setValidaCoordenadas(int validaCoordenadas) {
        this.validaCoordenadas = validaCoordenadas;
    }
}
