package br.inf.qti.movelapp;

/**
 * Created by fl_on on 31/05/2017.
 */

import android.content.ContentValues;
import android.content.Context;
import android.database.Cursor;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;

import java.util.LinkedList;
import java.util.List;

/**
 * Created by flavio on 11/06/2014.
 */
public class DataBaseHandler extends SQLiteOpenHelper {

    private static final int DATABASE_VERSION = 21;

    private static final String DATABASE_NAME = "moveldb",
            TABLE_USUARIOS = "tbl_usuarios",
            KEY_CODIGO = "codigo",
            KEY_USUARIO = "usuario",
            KEY_SENHA = "senha",
    //Pedidos
    TABLE_PEDIDOS = "tbl_pedidos",
            KEY_DATA_PEDIDO = "data_pedido",
            KEY_CONDICAO = "condicao",
            KEY_CODIGO_STATUS = "cod_status",
            KEY_DESCRICAO_STATUS = "desc_status",
            KEY_VALOR_VENDA = "valor_venda",
            KEY_RAZAOSOCIAL = "razao_social",
            KEY_NOMEFANTASIA = "nome_fantasia",
            KEY_RUA = "rua",
            KEY_NUMERO = "numero",
            KEY_COMPLEMENTO = "complemento",
            KEY_OBSERVACAO = "observacao",
            KEY_BAIRRO = "bairro",
            KEY_PONTO_REFERENCIA = "referencia",
            KEY_CIDADE = "cidade",
            KEY_UF = "uf",
            KEY_URGENTE = "urgente",
            KEY_CODIGO_MOTIVO_ATRASO = "cod_motivo_atraso",
            KEY_CONVENIO = "convenio",
            KEY_CARTAO = "cartao",
            KEY_APP = "app",
            KEY_GASDOPOVO = "gasdopovo",
    //Itens
    TABLE_ITENS = "tbl_pedidos_itens",
            KEY_CODIGO_PEDIDO = "cod_pedido",
            KEY_PRODUTO = "produto",
            KEY_PRECO = "preco",
            KEY_VALOR_TOTAL = "valor_total",
            KEY_QUANTIDADE = "quantidade",
            KEY_UN_MED = "unid_med",
    //Empresa
    TABLE_EMPRESA = "tbl_empresa",
            KEY_SERVIDOR = "servidor",
            KEY_VALIDA_GB = "valida_gb",
            KEY_VALIDA_ATRASO = "valida_atraso",
            KEY_TEMPO_ENTREGA = "tempo_entrega",
            KEY_TEMPO_ENTREGA_URGENTE = "tempo_entrega_urgente",
            KEY_VALIDA_COORDENADAS = "valida_coordenadas_entrega",
            KEY_VALIDA_PIX = "valida_pix",
    //Situação
    TABLE_SITUACOES = "tbl_situacoes",
            KEY_ENTREGA_FINALIZADA = "entrega_finalizada",
            KEY_ENTREGA_CANCELADA = "entrega_cancelada",
            KEY_ENTREGA_PENDENTE = "entrega_pendente",
            KEY_ENTREGA_TRANSFERIDA = "entrega_transferida",
            KEY_EM_ENTREGA = "em_entrega",
            KEY_VALE_GAS = "vale_gas",
            KEY_MENSAGEM_ENVIADA = "mensagem_enviada",
            KEY_MENSAGEM_LIDA = "mensagem_lida",
    //Motivo Atraso
    TABLE_MOTIVOS_ATRASOS = "tbl_motivos_atrasos",
            KEY_DESCRICAO = "descricao",
    //Config
    TABLE_CONFIG = "tbl_config",
            KEY_TOKEN = "token",
            KEY_SECRET = "secret",
            KEY_CODIGO_CLIENTE = "cod_cliente",
            KEY_CODIGO_REVENDA = "cod_revenda",
            KEY_URL = "url",
    //Veiculos
    TABLE_VEICULOS = "tbl_veiculos",
    KEY_PLACA = "placa",
    KEY_ATIVO = "ativo";


    public DataBaseHandler(Context context) {
        super(context, DATABASE_NAME, null, DATABASE_VERSION);
    }

    @Override
    public void onCreate(SQLiteDatabase db) {
        db.execSQL("CREATE TABLE IF NOT EXISTS " + TABLE_USUARIOS + "(" + KEY_CODIGO + " INTEGER PRIMARY KEY AUTOINCREMENT," + KEY_USUARIO + " TEXT," + KEY_SENHA + " TEXT)");
        db.execSQL("CREATE TABLE " + TABLE_ITENS + "(" + KEY_CODIGO + " INTEGER PRIMARY KEY AUTOINCREMENT," + KEY_CODIGO_PEDIDO + " INTEGER," + KEY_PRODUTO + " TEXT," + KEY_PRECO + " FLOAT," + KEY_QUANTIDADE + " FLOAT," + KEY_VALOR_TOTAL + " FLOAT, " + KEY_UN_MED + " TEXT)");
        db.execSQL("CREATE TABLE " + TABLE_PEDIDOS + "(" + KEY_CODIGO + " INTEGER PRIMARY KEY AUTOINCREMENT,"  + KEY_DATA_PEDIDO + " DATETIME," + KEY_RAZAOSOCIAL + " TEXT," + KEY_CONDICAO + " TEXT," + KEY_RUA + " TEXT,"+ KEY_NUMERO + " TEXT,"  + KEY_COMPLEMENTO + " TEXT," + KEY_VALOR_VENDA + " FLOAT," + KEY_OBSERVACAO + " TEXT," + KEY_BAIRRO + " TEXT," + KEY_CODIGO_STATUS + " INTEGER," + KEY_DESCRICAO_STATUS + " TEXT," + KEY_PONTO_REFERENCIA + " TEXT," + KEY_CIDADE + " TEXT," + KEY_UF + " TEXT," + KEY_URGENTE + " TEXT," + KEY_CODIGO_MOTIVO_ATRASO + " INTEGER," + KEY_CONVENIO + " TEXT," + KEY_CARTAO + " INTEGER," + KEY_APP + " TEXT," + KEY_GASDOPOVO + " INTEGER)" );
        db.execSQL("CREATE TABLE " + TABLE_EMPRESA + "(" + KEY_CODIGO + " INTEGER PRIMARY KEY AUTOINCREMENT," + KEY_RAZAOSOCIAL + " TEXT," + KEY_NOMEFANTASIA + " TEXT," + KEY_SERVIDOR + " TEXT," + KEY_VALIDA_ATRASO + " INTEGER," + KEY_VALIDA_GB + " INTEGER," + KEY_TEMPO_ENTREGA + " INTEGER," + KEY_TEMPO_ENTREGA_URGENTE + " INTEGER," + KEY_VALIDA_COORDENADAS + " INTEGER," + KEY_VALIDA_PIX + " INTEGER)");
        db.execSQL("CREATE TABLE " + TABLE_SITUACOES + "(" + KEY_CODIGO + " INTEGER PRIMARY KEY AUTOINCREMENT," + KEY_DESCRICAO_STATUS + " TEXT," + KEY_ENTREGA_CANCELADA + " INTEGER," + KEY_ENTREGA_FINALIZADA + " INTEGER," + KEY_ENTREGA_PENDENTE + " INTEGER, " + KEY_ENTREGA_TRANSFERIDA + " INTEGER, " + KEY_EM_ENTREGA + " INTEGER, " + KEY_VALE_GAS + " INTEGER, " + KEY_MENSAGEM_ENVIADA + " INTEGER," + KEY_MENSAGEM_LIDA + " INTEGER," + KEY_CARTAO + " INTEGER)");
        db.execSQL("CREATE TABLE " + TABLE_MOTIVOS_ATRASOS + "(" + KEY_CODIGO + " INTEGER PRIMARY KEY AUTOINCREMENT," + KEY_DESCRICAO + " TEXT)");
        db.execSQL("CREATE TABLE " + TABLE_CONFIG + "(" + KEY_CODIGO + " INTEGER PRIMARY KEY AUTOINCREMENT," + KEY_TOKEN + " TEXT," + KEY_SECRET + " TEXT," + KEY_URL + " TEXT, "  + KEY_CODIGO_CLIENTE + " TEXT, "  + KEY_CODIGO_REVENDA + " TEXT, " + KEY_USUARIO + " TEXT)");
        db.execSQL("CREATE TABLE " + TABLE_VEICULOS + "(" + KEY_CODIGO + " INTEGER PRIMARY KEY AUTOINCREMENT," + KEY_DESCRICAO + " TEXT," + KEY_PLACA + " TEXT," + KEY_ATIVO + " INTEGER)");
    }

    @Override
    public void onUpgrade(SQLiteDatabase db, int oldVersion, int newVersion) {
        if(oldVersion == 20) {
            db.execSQL("ALTER TABLE  " + TABLE_PEDIDOS + " ADD " + KEY_GASDOPOVO + " INTEGER");
        } else if(oldVersion == 19) {
            db.execSQL("ALTER TABLE  " + TABLE_EMPRESA + " ADD " + KEY_VALIDA_PIX + " INTEGER");
        } else if(oldVersion == 18){
            db.execSQL("ALTER TABLE  " + TABLE_PEDIDOS + " ADD " + KEY_APP + " TEXT");
        } else if(oldVersion == 17){
            db.execSQL("CREATE TABLE " + TABLE_VEICULOS + "(" + KEY_CODIGO + " INTEGER PRIMARY KEY AUTOINCREMENT," + KEY_DESCRICAO + " TEXT," + KEY_PLACA + " TEXT," + KEY_ATIVO + " INTEGER)");
            db.execSQL("ALTER TABLE  " + TABLE_PEDIDOS + " ADD " + KEY_CARTAO + " INTEGER");
            db.execSQL("ALTER TABLE  " + TABLE_PEDIDOS + " ADD " + KEY_APP + " TEXT");
            db.execSQL("ALTER TABLE  " + TABLE_SITUACOES + " ADD " + KEY_CARTAO + " INTEGER");
        } else if(oldVersion == 16){
        } else if(oldVersion == 14){
            db.execSQL("ALTER TABLE  " + TABLE_SITUACOES + " ADD " + KEY_MENSAGEM_ENVIADA + " INTEGER");
            db.execSQL("ALTER TABLE  " + TABLE_SITUACOES + " ADD " + KEY_MENSAGEM_LIDA + " INTEGER");
        } else if(oldVersion == 13){
            db.execSQL("ALTER TABLE  " + TABLE_SITUACOES + " ADD " + KEY_ENTREGA_TRANSFERIDA + " INTEGER");
            db.execSQL("ALTER TABLE  " + TABLE_SITUACOES + " ADD " + KEY_EM_ENTREGA + " INTEGER");
            db.execSQL("ALTER TABLE  " + TABLE_SITUACOES + " ADD " + KEY_VALE_GAS + " INTEGER");
        } else if(oldVersion == 12){
            db.execSQL("ALTER TABLE  " + TABLE_CONFIG + " ADD " + KEY_CODIGO_CLIENTE + " TEXT");
            db.execSQL("ALTER TABLE  " + TABLE_CONFIG + " ADD " + KEY_CODIGO_REVENDA + " TEXT");
        } else {
            db.execSQL("DROP TABLE IF EXISTS " + TABLE_EMPRESA);
            db.execSQL("DROP TABLE IF EXISTS " + TABLE_PEDIDOS);
            db.execSQL("DROP TABLE IF EXISTS " + TABLE_ITENS);
            db.execSQL("DROP TABLE IF EXISTS " + TABLE_SITUACOES);
            db.execSQL("DROP TABLE IF EXISTS " + TABLE_MOTIVOS_ATRASOS);
            db.execSQL("DROP TABLE IF EXISTS " + TABLE_CONFIG);
            onCreate(db);
        }
    }

    public boolean importUsuario (List<Usuario> queryValues){
        try {
            SQLiteDatabase database = this.getWritableDatabase();

            database.execSQL("DELETE FROM " + TABLE_USUARIOS);

            for (int i = 0; i < queryValues.size(); i++) {
                ContentValues values = new ContentValues();
                values.put(KEY_CODIGO, queryValues.get(i).getId());
                values.put(KEY_USUARIO, queryValues.get(i).getUsuario());
                values.put(KEY_SENHA, queryValues.get(i).getSenha());
                database.insert(TABLE_USUARIOS, null, values);
            }
            database.close();
            return true;
        } catch (Exception e) {
            return false;
        }
    }


     public Usuario getUsuarioByLogin(String login) {
        SQLiteDatabase db = getReadableDatabase();

        Cursor cursor = db.query(TABLE_USUARIOS, new String[] { KEY_CODIGO, KEY_USUARIO, KEY_SENHA }, KEY_USUARIO + "=?", new String[] { login }, null, null, null, null );

        Usuario usuario = new Usuario(-1, login, "");

        if (cursor != null)
            if(cursor.moveToFirst()){
                do {
                    usuario = new Usuario(cursor.getInt(0), cursor.getString(1), cursor.getString(2));
                } while (cursor.moveToNext());
            }
        db.close();
        cursor.close();
        return usuario;
    }

    public boolean importEmpresa (List<Empresa> queryValues){
        try {
            SQLiteDatabase database = this.getWritableDatabase();

            database.execSQL("DELETE FROM " + TABLE_EMPRESA);

            for (int i = 0; i < queryValues.size(); i++) {
                ContentValues values = new ContentValues();
                values.put(KEY_CODIGO, queryValues.get(i).getId());
                values.put(KEY_RAZAOSOCIAL, queryValues.get(i).getRazaoSocial());
                values.put(KEY_NOMEFANTASIA, queryValues.get(i).getNomeFantasia());
                values.put(KEY_SERVIDOR, queryValues.get(i).getServidor());
                values.put(KEY_VALIDA_ATRASO, queryValues.get(i).getValidaAtraso());
                values.put(KEY_VALIDA_GB, queryValues.get(i).getValidaGB());
                values.put(KEY_TEMPO_ENTREGA, queryValues.get(i).getTempoEntrega());
                values.put(KEY_TEMPO_ENTREGA_URGENTE, queryValues.get(i).getTempoEntregaUrgente());
                values.put(KEY_VALIDA_COORDENADAS, queryValues.get(i).getValidaCoordenadas());
                values.put(KEY_VALIDA_PIX, queryValues.get(i).getValidaPix());
                database.insert(TABLE_EMPRESA, null, values);
            }
            database.close();
            return true;
        } catch (Exception e) {
            System.out.println(e.getMessage());
            return false;
        }
    }

    public boolean importSituacoes (List<Situacao> queryValues){
        try {
            SQLiteDatabase database = this.getWritableDatabase();

            database.execSQL("DELETE FROM " + TABLE_SITUACOES);

            for (int i = 0; i < queryValues.size(); i++) {
                ContentValues values = new ContentValues();
                values.put(KEY_CODIGO, queryValues.get(i).getId());
                values.put(KEY_DESCRICAO_STATUS, queryValues.get(i).toString());
                values.put(KEY_ENTREGA_CANCELADA, queryValues.get(i).getEntregaCancelada());
                values.put(KEY_ENTREGA_FINALIZADA, queryValues.get(i).getEntregaRealizada());
                values.put(KEY_ENTREGA_PENDENTE, queryValues.get(i).getEntregaPendente());
                values.put(KEY_ENTREGA_TRANSFERIDA, queryValues.get(i).getEntregaTransferida());
                values.put(KEY_EM_ENTREGA, queryValues.get(i).getEmEntrega());
                values.put(KEY_VALE_GAS, queryValues.get(i).getValeGas());
                values.put(KEY_MENSAGEM_ENVIADA, queryValues.get(i).getMensagemEnviada());
                values.put(KEY_MENSAGEM_LIDA, queryValues.get(i).getMensagemLida());
                values.put(KEY_CARTAO, queryValues.get(i).getCartao());
                database.insert(TABLE_SITUACOES, null, values);
            }
            database.close();
            return true;
        } catch (Exception e) {
            System.out.println(e.getMessage());
            return false;
        }
    }

    public boolean importMotivosAtrasos (List<MotivoAtraso> queryValues){
        try {
            SQLiteDatabase database = this.getWritableDatabase();

            database.execSQL("DELETE FROM " + TABLE_MOTIVOS_ATRASOS);

            for (int i = 0; i < queryValues.size(); i++) {
                ContentValues values = new ContentValues();
                values.put(KEY_CODIGO, queryValues.get(i).getId());
                values.put(KEY_DESCRICAO, queryValues.get(i).toString());
                database.insert(TABLE_MOTIVOS_ATRASOS, null, values);
            }
            database.close();
            return true;
        } catch (Exception e) {
            System.out.println(e.getMessage());
            return false;
        }
    }

    public boolean importVeiculos (List<Veiculo> queryValues){
        try {
            SQLiteDatabase database = this.getWritableDatabase();

            database.execSQL("DELETE FROM " + TABLE_VEICULOS);

            for (int i = 0; i < queryValues.size(); i++) {
                ContentValues values = new ContentValues();
                values.put(KEY_CODIGO, queryValues.get(i).getId());
                values.put(KEY_DESCRICAO, queryValues.get(i).toString());
                values.put(KEY_PLACA, queryValues.get(i).getPlaca());
                values.put(KEY_ATIVO, queryValues.get(i).getAtivo());
                database.insert(TABLE_VEICULOS, null, values);
            }
            database.close();
            return true;
        } catch (Exception e) {
            System.out.println(e.getMessage());
            return false;
        }
    }

    public Empresa getEmpresa() {
        Empresa empresa;
        SQLiteDatabase db = getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT a.* FROM tbl_empresa a", null);

        if (cursor.moveToFirst()) {
            empresa = new Empresa(cursor.getInt(0), cursor.getString(1), cursor.getString(2), cursor.getString(3));
            empresa.setValidaAtraso(cursor.getInt(4));
            empresa.setValidaGB(cursor.getInt(5));
            empresa.setTempoEntrega(cursor.getInt(6));
            empresa.setTempoEntregaUrgente(cursor.getInt(7));
            empresa.setValidaCoordenadas(cursor.getInt(8));
            empresa.setValidaPix(cursor.getInt(9));
        } else {
            empresa = new Empresa(0,"");
        }
        cursor.close();
        db.close();
        return empresa;
    }

    public Config getConfig() {
        Config config;
        SQLiteDatabase db = getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT " +
                KEY_CODIGO + ", " +
                KEY_SECRET + ", " +
                KEY_TOKEN + ", " +
                KEY_USUARIO + ", " +
                KEY_URL + ", " +
                KEY_CODIGO_CLIENTE + ", " +
                KEY_CODIGO_REVENDA + " " +
                "FROM " + TABLE_CONFIG, null);

        if (cursor.moveToFirst()) {
            config = new Config(cursor.getInt(0), cursor.getString(1), cursor.getString(2), cursor.getString(3),
                    cursor.getString(4), cursor.getString(5),cursor.getString(6));
        } else {
            config = new Config(0);
        }
        cursor.close();
        db.close();
        return config;
    }

    public Veiculo getVeiculoAtivo() {
        Veiculo veiculo;
        SQLiteDatabase db = getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT " +
                KEY_CODIGO + ", " +
                KEY_DESCRICAO + ", " +
                KEY_PLACA + " " +
                "FROM " + TABLE_VEICULOS +
                " WHERE " + KEY_ATIVO + " = 1 "
                , null);

        if (cursor.moveToFirst()) {
            veiculo = new Veiculo(cursor.getInt(0), cursor.getString(1), cursor.getString(2), 1);
        } else {
            veiculo = new Veiculo(0, "", "", 0);
        }
        cursor.close();
        db.close();
        return veiculo;
    }

    public LinkedList<PedidoItem> getAllItens(String codPedido) {
        LinkedList<PedidoItem> item = new LinkedList<PedidoItem>();

        SQLiteDatabase db = getReadableDatabase();

        Cursor cursor = db.rawQuery("SELECT item." + KEY_CODIGO + ","
                + "item." + KEY_CODIGO_PEDIDO + ","
                + "item." + KEY_PRODUTO + ","
                + "item." + KEY_QUANTIDADE + ","
                + "item." + KEY_PRECO + ","
                + "item." + KEY_VALOR_TOTAL + ","
                + "item." + KEY_UN_MED
                + " FROM "
                + TABLE_ITENS + " item "
                + " WHERE " + KEY_CODIGO_PEDIDO + " = " +  codPedido
                + " ORDER BY item." + KEY_CODIGO, null);

        if (cursor.moveToFirst()) {
            do {
                item.add(new PedidoItem(cursor.getInt(0), cursor.getInt(1), cursor.getString(2), cursor.getDouble(3), cursor.getDouble(4), cursor.getDouble(5), cursor.getString(6)));
            }
            while (cursor.moveToNext());
        }
        cursor.close();
        db.close();
        return item;
    }

    public Pedido getPedido(String codPedido) {
        SQLiteDatabase db = getReadableDatabase();
        Pedido pedido;
        Cursor cursor = db.rawQuery("SELECT " + KEY_CODIGO + ","
                + KEY_DATA_PEDIDO + ","
                + KEY_RAZAOSOCIAL + ","
                + KEY_CONDICAO + ","
                + KEY_RUA + ","
                + KEY_NUMERO + ","
                + KEY_COMPLEMENTO + ", "
                + KEY_VALOR_VENDA + ", "
                + KEY_OBSERVACAO + ", "
                + KEY_BAIRRO + ", "
                + KEY_CODIGO_STATUS + ", "
                + KEY_DESCRICAO_STATUS + ", "
                + KEY_PONTO_REFERENCIA + ", "
                + KEY_CIDADE + ", "
                + KEY_UF + ", "
                + KEY_CODIGO_MOTIVO_ATRASO + ", "
                + KEY_URGENTE + ", "
                + KEY_CONVENIO + ", "
                + KEY_CARTAO + ", "
                + KEY_APP + ", "
                + KEY_GASDOPOVO
                + " FROM "
                + TABLE_PEDIDOS
                + " WHERE " + KEY_CODIGO + " = " +  codPedido, null);

        if (cursor.moveToFirst()) {
            pedido = new Pedido(cursor.getInt(0), cursor.getString(1), cursor.getString(2), cursor.getString(3), cursor.getDouble(7), cursor.getString(4), cursor.getString(5),cursor.getString(6), cursor.getString(8), cursor.getString(9),cursor.getString(12), cursor.getInt(10),cursor.getString(11));
            pedido.setCidade(cursor.getString(13));
            pedido.setUf(cursor.getString(14));
            pedido.setCodMotivoAtraso(cursor.getInt(15));
            pedido.setUrgente(cursor.getString(16));
            pedido.setConvenio(cursor.getString(17));
            pedido.setCartao(cursor.getInt(18));
            pedido.setApp(cursor.getString(19));
            pedido.setGasdopovo(cursor.getInt(20));
        } else {
            pedido = new Pedido(-2,"", "", "", 0.0, "", "", "", "", "", "", 0, "");
        }
        cursor.close();
        db.close();
        return pedido;
    }

    public LinkedList<Pedido> getAllPedidosPendentes() {
        LinkedList<Pedido> pedidos = new LinkedList<Pedido>();
        Pedido pedido;
        String codPendente = this.getSituacoesPedidosPendentes();
        SQLiteDatabase db = getReadableDatabase();

        String query =  "SELECT "
                + KEY_CODIGO + ","
                + KEY_DATA_PEDIDO + ","
                + KEY_RAZAOSOCIAL + ","
                + KEY_CONDICAO + ","
                + KEY_RUA + ","
                + KEY_NUMERO + ","
                + KEY_COMPLEMENTO + ", "
                + KEY_VALOR_VENDA + ", "
                + KEY_OBSERVACAO + ", "
                + KEY_BAIRRO + ", "
                + KEY_CODIGO_STATUS + ", "
                + KEY_DESCRICAO_STATUS + ", "
                + KEY_PONTO_REFERENCIA + ", "
                + KEY_CIDADE + ", "
                + KEY_UF + ", "
                + KEY_CODIGO_MOTIVO_ATRASO + ", "
                + KEY_URGENTE + ", "
                + KEY_CONVENIO + ", "
                + KEY_APP + ", "
                + KEY_GASDOPOVO
                + " FROM " + TABLE_PEDIDOS + " "
                + " WHERE " + KEY_CODIGO_STATUS + " IN (" + codPendente + ") ";
        query += " ORDER BY " + KEY_URGENTE + " DESC, " + KEY_CODIGO + ", " + KEY_RAZAOSOCIAL;

        Cursor cursor = db.rawQuery(query, null);

        if (cursor.moveToFirst()) {
            do {
                pedido = new Pedido(cursor.getInt(0), cursor.getString(1), cursor.getString(2), cursor.getString(3), cursor.getDouble(7), cursor.getString(4), cursor.getString(5),cursor.getString(6), cursor.getString(8), cursor.getString(9),cursor.getString(12), cursor.getInt(10),cursor.getString(11));
                pedido.setCidade(cursor.getString(13));
                pedido.setUf(cursor.getString(14));
                pedido.setCodMotivoAtraso(cursor.getInt(15));
                pedido.setUrgente(cursor.getString(16));
                pedido.setConvenio(cursor.getString(17));
                pedido.setApp(cursor.getString(18));
                pedido.setGasdopovo(cursor.getInt(19));
                pedidos.add(pedido);
            }
            while (cursor.moveToNext());
        }
        cursor.close();
        db.close();
        return pedidos;
    }

    public int getSituacaoMensagemEnviada() {

        SQLiteDatabase db = getReadableDatabase();
        int ret = 0;
        String query =  "SELECT "
                + KEY_CODIGO
                + " FROM " + TABLE_SITUACOES + " "
                + " WHERE " + KEY_MENSAGEM_ENVIADA + " = 1";

        Cursor cursor = db.rawQuery(query, null);

        if (cursor.moveToFirst()) {
            do {
                ret = cursor.getInt(0);
            }
            while (cursor.moveToNext());
        }
        cursor.close();
        db.close();
        return ret;
    }
    public int getSituacaoMensagemLida() {

        SQLiteDatabase db = getReadableDatabase();
        int ret = 0;
        String query =  "SELECT "
                + KEY_CODIGO
                + " FROM " + TABLE_SITUACOES + " "
                + " WHERE " + KEY_MENSAGEM_LIDA + " = 1";

        Cursor cursor = db.rawQuery(query, null);

        if (cursor.moveToFirst()) {
            do {
                ret = cursor.getInt(0);
            }
            while (cursor.moveToNext());
        }
        cursor.close();
        db.close();
        return ret;
    }
    public int getSituacaoEmEntrega() {

        SQLiteDatabase db = getReadableDatabase();
        int ret = 0;
        String query =  "SELECT "
                + KEY_CODIGO
                + " FROM " + TABLE_SITUACOES + " "
                + " WHERE " + KEY_EM_ENTREGA + " = 1";

        Cursor cursor = db.rawQuery(query, null);

        if (cursor.moveToFirst()) {
            do {
                ret = cursor.getInt(0);
            }
            while (cursor.moveToNext());
        }
        cursor.close();
        db.close();
        return ret;
    }
    public int getSituacaoTransferir() {

        SQLiteDatabase db = getReadableDatabase();
        int ret = 0;
        String query =  "SELECT "
                + KEY_CODIGO
                + " FROM " + TABLE_SITUACOES + " "
                + " WHERE " + KEY_ENTREGA_TRANSFERIDA + " = 1";

        Cursor cursor = db.rawQuery(query, null);

        if (cursor.moveToFirst()) {
            do {
                ret = cursor.getInt(0);
            }
            while (cursor.moveToNext());
        }
        cursor.close();
        db.close();
        return ret;
    }

    public int getSituacaoValeGas() {

        SQLiteDatabase db = getReadableDatabase();
        int ret = 0;
        String query =  "SELECT "
                + KEY_CODIGO
                + " FROM " + TABLE_SITUACOES + " "
                + " WHERE " + KEY_VALE_GAS + " = 1";

        Cursor cursor = db.rawQuery(query, null);

        if (cursor.moveToFirst()) {
            do {
                ret = cursor.getInt(0);
            }
            while (cursor.moveToNext());
        }
        cursor.close();
        db.close();
        return ret;
    }

    public int getSituacaoPendente() {

        SQLiteDatabase db = getReadableDatabase();
        int ret = 0;
        String query =  "SELECT "
                + KEY_CODIGO
                + " FROM " + TABLE_SITUACOES + " "
                + " WHERE " + KEY_ENTREGA_PENDENTE + " = 1";

        Cursor cursor = db.rawQuery(query, null);

        if (cursor.moveToFirst()) {
            do {
                ret = cursor.getInt(0);
            }
            while (cursor.moveToNext());
        }
        cursor.close();
        db.close();
        return ret;
    }

    public String getSituacoesPedidosPendentes() {

        SQLiteDatabase db = getReadableDatabase();
        String ret = "-1";
        String query =  "SELECT "
                + KEY_CODIGO
                + " FROM " + TABLE_SITUACOES + " "
                + " WHERE " + KEY_ENTREGA_PENDENTE + " = 1"
                + " OR " + KEY_MENSAGEM_ENVIADA + " = 1 "
                + " OR " + KEY_EM_ENTREGA + " = 1 "
                + " OR " + KEY_MENSAGEM_LIDA + " = 1 ";

        Cursor cursor = db.rawQuery(query, null);

        if (cursor.moveToFirst()) {
            do {
                ret += "," + cursor.getString(0);
            }
            while (cursor.moveToNext());
        }
        cursor.close();
        db.close();
        return ret;
    }

    public boolean isSituacaoPendente(int codSituacao) {
        String situacoes = this.getSituacoesPedidosPendentes() + ",";
        return (situacoes.contains("," + String.valueOf(codSituacao) + ","));
    }

    public LinkedList<Pedido> listVendasPeriodo(String dataInicial, String dataFinal) {
        LinkedList<Pedido> pedidos = new LinkedList<Pedido>();

        dataInicial = dataInicial.substring(6,10) + "-" + dataInicial.substring(3,5) + "-" + dataInicial.substring(0,2) + " 00:00:00";
        dataFinal = dataFinal.substring(6,10) + "-" + dataFinal.substring(3,5) + "-" + dataFinal.substring(0,2) + " 23:59:59";

        SQLiteDatabase db = getReadableDatabase();

        String dbQuery =
                "SELECT ped." + KEY_CODIGO + "," + KEY_RAZAOSOCIAL + "," + KEY_CONDICAO + ", " + KEY_VALOR_VENDA
                        + ", (SELECT SUM(" + KEY_QUANTIDADE + ") FROM " + TABLE_ITENS + " ite "
                        + " WHERE ite." + KEY_CODIGO_PEDIDO + " = ped." + KEY_CODIGO + ") AS quantidade"
                        + " FROM " + TABLE_PEDIDOS + " ped "
                        + " INNER JOIN " + TABLE_SITUACOES + " sit "
                        + " ON sit." + KEY_CODIGO + " = ped." + KEY_CODIGO_STATUS
                        + " WHERE " + KEY_DATA_PEDIDO + " BETWEEN '"
                        + dataInicial + "' AND '" + dataFinal + "'"
                        + " AND sit." + KEY_ENTREGA_CANCELADA + " = 0 "
                        + " ORDER BY 1";

        Cursor cursor = db.rawQuery(dbQuery, null);


        if (cursor.moveToFirst()) {
            do {
                Pedido pedido = new Pedido(cursor.getInt(0), "", cursor.getString(1), cursor.getString(2), cursor.getDouble(3), "", "", "", "", "", "", 0, "");
                pedido.setQuantidade(cursor.getDouble(4));
                pedidos.add(pedido);
            }
            while (cursor.moveToNext());
        }
        cursor.close();
        db.close();
        return pedidos;
    }

    public boolean importPedido (List<Pedido> queryValues){
        try {
            SQLiteDatabase database = this.getWritableDatabase();
            for (int i = 0; i < queryValues.size(); i++) {
                database.execSQL("DELETE FROM " + TABLE_ITENS + " WHERE " + KEY_CODIGO_PEDIDO + " IN " +
                        "(" + String.valueOf(queryValues.get(i).getId()) + ")");
                database.execSQL("DELETE FROM " + TABLE_PEDIDOS + " WHERE " + KEY_CODIGO + " = " + String.valueOf(queryValues.get(i).getId()));

                ContentValues values = new ContentValues();
                values.put(KEY_CODIGO, queryValues.get(i).getId());

                values.put(KEY_DATA_PEDIDO, queryValues.get(i).getData_pedido());
                values.put(KEY_RAZAOSOCIAL, queryValues.get(i).getCliente());
                values.put(KEY_CONDICAO, queryValues.get(i).getCondicao());
                values.put(KEY_PONTO_REFERENCIA, queryValues.get(i).getPonto_referencia());
                values.put(KEY_VALOR_VENDA, queryValues.get(i).getValor_venda());
                values.put(KEY_BAIRRO, queryValues.get(i).getBairro());
                values.put(KEY_CODIGO_STATUS, queryValues.get(i).getCodStatus());
                values.put(KEY_DESCRICAO_STATUS, queryValues.get(i).getDescStatus());
                values.put(KEY_COMPLEMENTO, queryValues.get(i).getComplemento());
                values.put(KEY_NUMERO, queryValues.get(i).getNumero());
                values.put(KEY_OBSERVACAO, queryValues.get(i).getObservacao());
                values.put(KEY_RUA, queryValues.get(i).getRua());
                values.put(KEY_CIDADE, queryValues.get(i).getCidade());
                values.put(KEY_UF, queryValues.get(i).getUf());
                values.put(KEY_URGENTE, queryValues.get(i).getUrgente());
                values.put(KEY_CODIGO_MOTIVO_ATRASO, queryValues.get(i).getCodMotivoAtraso());
                values.put(KEY_CONVENIO, queryValues.get(i).getConvenio());
                values.put(KEY_CARTAO, queryValues.get(i).getCartao());
                values.put(KEY_APP, queryValues.get(i).getApp());
                values.put(KEY_GASDOPOVO, queryValues.get(i).getGasdopovo());

                database.insert(TABLE_PEDIDOS, null, values);
                this.importItensPedido(queryValues.get(i).getItens(), database);
            }
            database.close();
            return true;
        } catch (Exception e) {
            System.out.println(e.getMessage());
            return false;
        }
    }

    public boolean importItensPedido (List<PedidoItem> queryValues, SQLiteDatabase database){
        try {

            for (int i = 0; i < queryValues.size(); i++) {
                ContentValues values = new ContentValues();
                values.put(KEY_CODIGO, queryValues.get(i).getId());

                values.put(KEY_CODIGO_PEDIDO, queryValues.get(i).getCod_pedido());
                values.put(KEY_PRODUTO, queryValues.get(i).getProduto());
                values.put(KEY_PRECO, queryValues.get(i).getPreco());
                values.put(KEY_QUANTIDADE, queryValues.get(i).getQuantidade());
                values.put(KEY_VALOR_TOTAL, queryValues.get(i).getValor_total());
                values.put(KEY_UN_MED, queryValues.get(i).getUnid_med());

                database.insert(TABLE_ITENS, null, values);
            }
            return true;
        } catch (Exception e) {
            System.out.println(e.getMessage());
            return false;
        }
    }
    public boolean atualizaStatusPedido (Pedido pedido){
        try {
            SQLiteDatabase database = this.getWritableDatabase();
            String qry = "UPDATE " + TABLE_PEDIDOS + " SET " + KEY_CODIGO_STATUS + "=" + String.valueOf(pedido.getCodStatus()) +
                    " , " + KEY_DESCRICAO_STATUS + " = '" + pedido.getDescStatus() + "' " +
                    " WHERE " + KEY_CODIGO + "=" + pedido.getId();
            database.execSQL(qry);
            database.close();
            return true;
        } catch (Exception e) {
            return false;
        }
    }
    public boolean insertNewConfig(){
        try {
            SQLiteDatabase database = this.getWritableDatabase();
            String qry = "INSERT INTO " + TABLE_CONFIG + " VALUES(1, '', '', '', '', '', '')";
            database.execSQL(qry);
            database.close();
            return true;
        } catch (Exception e) {
            return false;
        }
    }
    public boolean atualizaConfig (Config config){
        try {
            if(this.getConfigCount()==0){
                this.insertNewConfig();
            }
            SQLiteDatabase database = this.getWritableDatabase();
            String qry = "UPDATE " + TABLE_CONFIG + " SET " + KEY_TOKEN + "='" + config.getToken() +
                    "' , " + KEY_SECRET + " = '" + config.getSecret() + "', " +
                    KEY_USUARIO + "='" + config.getUsuario() + "', " +
                    KEY_CODIGO_CLIENTE + "='" + config.getCliente_id() + "', " +
                    KEY_CODIGO_REVENDA + "='" + config.getRevenda_id() + "', " +
                    KEY_URL + "='" + config.getUrl() +
                    "' WHERE " + KEY_CODIGO + "=" + config.getId();
            database.execSQL(qry);
            database.close();
            return true;
        } catch (Exception e) {
            return false;
        }
    }
    public int getConfigCount() {
        SQLiteDatabase db = getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT * FROM " + TABLE_CONFIG, null);
        int count = cursor.getCount();
        db.close();
        cursor.close();

        return count;
    }

    public int getUsersCount() {
        SQLiteDatabase db = getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT * FROM " + TABLE_USUARIOS, null);
        int count = cursor.getCount();
        db.close();
        cursor.close();

        return count;
    }

    public Situacao getSituacao(int cod) {
        Situacao situacao;
        SQLiteDatabase db = getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT " + KEY_CODIGO + ", " + KEY_DESCRICAO_STATUS + ", " + KEY_CARTAO +
                " FROM " + TABLE_SITUACOES + " WHERE " + KEY_CODIGO + " = " + String.valueOf(cod), null);

        if (cursor.moveToFirst()) {
            situacao = new Situacao(cursor.getInt(0), cursor.getString(1));
            situacao.setCartao(cursor.getInt(2));

        } else {
            situacao = new Situacao(0,"");
            situacao.setCartao(0);
        }
        cursor.close();
        db.close();
        return situacao;
    }

    public LinkedList<Situacao> getSituacoesFinalizar() {
        Situacao situacao;
        LinkedList<Situacao> situacoes = new LinkedList<Situacao>();
        SQLiteDatabase db = getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT * FROM " + TABLE_SITUACOES + " WHERE " + KEY_ENTREGA_FINALIZADA + " = 1" , null);

        if (cursor.moveToFirst()) {
            do {
                situacao = new Situacao(cursor.getInt(0), cursor.getString(1));
                situacoes.add(situacao);
            }
            while (cursor.moveToNext());
        }
        cursor.close();
        db.close();
        return situacoes;
    }
    public LinkedList<Situacao> getSituacoesCancelar() {
        Situacao situacao;
        LinkedList<Situacao> situacoes = new LinkedList<Situacao>();
        SQLiteDatabase db = getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT * FROM " + TABLE_SITUACOES + " WHERE " + KEY_ENTREGA_CANCELADA + " = 1" , null);

        if (cursor.moveToFirst()) {
            do {
                situacao = new Situacao(cursor.getInt(0), cursor.getString(1));
                situacoes.add(situacao);
            }
            while (cursor.moveToNext());
        }
        cursor.close();
        db.close();
        return situacoes;
    }
    public LinkedList<MotivoAtraso> getMotivosAtraso() {
        MotivoAtraso motivo;
        LinkedList<MotivoAtraso> motivos = new LinkedList<MotivoAtraso>();
        SQLiteDatabase db = getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT * FROM " + TABLE_MOTIVOS_ATRASOS + " ORDER BY " + KEY_DESCRICAO , null);

        if (cursor.moveToFirst()) {
            do {
                motivo = new MotivoAtraso(cursor.getInt(0), cursor.getString(1));
                motivos.add(motivo);
            }
            while (cursor.moveToNext());
        }
        cursor.close();
        db.close();
        return motivos;
    }
    public LinkedList<Veiculo> getVeiculos() {
        Veiculo veiculo;
        LinkedList<Veiculo> veiculos = new LinkedList<Veiculo>();
        SQLiteDatabase db = getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT codigo, descricao, placa, ativo FROM " + TABLE_VEICULOS + " ORDER BY ativo DESC, placa", null);

        if (cursor.moveToFirst()) {
            do {
                String descricao = cursor.getString(2) + " " + cursor.getString(1);
                veiculo = new Veiculo(cursor.getInt(0), descricao, cursor.getString(2), cursor.getInt(3));
                veiculos.add(veiculo);
            }
            while (cursor.moveToNext());
        }
        cursor.close();
        db.close();
        return veiculos;
    }
    public boolean atualizaVeiculosInativos (){
        try {
            SQLiteDatabase database = this.getWritableDatabase();
            String qry = "UPDATE " + TABLE_VEICULOS + " SET " + KEY_ATIVO + "= 0";
            database.execSQL(qry);
            database.close();
            return true;
        } catch (Exception e) {
            return false;
        }
    }

    public boolean atualizaVeiculoAtivo (int veiculoId){
        try {
            if(this.atualizaVeiculosInativos()) {
                SQLiteDatabase database = this.getWritableDatabase();
                //String qry = "UPDATE " + TABLE_VEICULOS + " SET " + KEY_ATIVO + "= 0";
                //database.execSQL(qry);

                String qry = "UPDATE " + TABLE_VEICULOS + " SET " + KEY_ATIVO + "= 1" +
                        " WHERE " + KEY_CODIGO + "=" + String.valueOf(veiculoId);
                database.execSQL(qry);
                database.close();
                return true;
            } else {
                return false;
            }
        } catch (Exception e) {
            return false;
        }
    }

}
