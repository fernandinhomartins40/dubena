package br.inf.qti.movelapp;

import android.app.ProgressDialog;
import android.content.Context;
import android.content.Intent;
import android.net.ConnectivityManager;
import android.os.AsyncTask;
import android.os.Bundle;
import android.provider.Settings;
//import android.support.v7.app.AppCompatActivity;
//import android.support.v7.widget.Toolbar;
import android.view.MenuItem;
import android.view.View;
import android.widget.Button;
import android.widget.ImageButton;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

public class CadastroImportActivity extends AppCompatActivity {

    private static importCadastrosTask  task;
    private static Config config;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_cadastro_import);
        TextView txtId = (TextView) findViewById(R.id.lblIdImport);
        txtId.setText("Android id: " + Settings.Secure.getString(getApplicationContext().getContentResolver(), Settings.Secure.ANDROID_ID));

        //Verificar conexão
        if(!Utils.verificaConexao(getApplicationContext())) {
            Toast.makeText(CadastroImportActivity.this, "Sem conexão com Internet. Verifique a conexão e tente novamente.", Toast.LENGTH_LONG).show();
            Intent returnIntent = new Intent();
            setResult(RESULT_CANCELED, returnIntent);
            finish();
        }
        else {
            if (!loadDadosIniciais()) {
                Toast.makeText(CadastroImportActivity.this, "Configuração não disponível. Registre o aplicativo antes de importar.", Toast.LENGTH_LONG).show();
                Intent returnIntent = new Intent();
                setResult(RESULT_CANCELED, returnIntent);
                finish();
            }
            ImageButton btnImportar = (ImageButton) findViewById(R.id.btnImportar);
            btnImportar.setOnClickListener(new View.OnClickListener() {
                @Override
                public void onClick(View view) {
                    //Verificar conexão
                    if (!Utils.verificaConexao(CadastroImportActivity.this.getApplicationContext())) {
                        Toast.makeText(CadastroImportActivity.this, "Sem conexão com Internet. Verifique a conexão e tente novamente.", Toast.LENGTH_LONG).show();
                        Intent returnIntent = new Intent();
                        setResult(RESULT_CANCELED, returnIntent);
                        finish();

                    } else {
                        task = new importCadastrosTask();
                        task.execute();
                    }

                }
            });
        }
    }
    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        // Handle action bar item clicks here. The action bar will
        // automatically handle clicks on the Home/Up button, so long
        // as you specify a parent activity in AndroidManifest.xml.
        int id = item.getItemId();
        if (id == R.id.action_settings) {
            return true;
        }
        return super.onOptionsItemSelected(item);
    }

    public boolean loadDadosIniciais() {
        DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
        config = dbHandler.getConfig();

        if(config.getId() == 0 || config.getUrl() == null) {
            return false;
        }
        TextView lblServidor = (TextView) findViewById(R.id.lblServidor);
        lblServidor.setText(config.getUrl());
        return true;
    }
    /**
     * Created by flavio on 22/09/2014.
     */

    public class importCadastrosTask extends AsyncTask<String, String, String> {

        protected ProgressDialog progressD;

        @Override
        protected String doInBackground(String... strings) {
            String ret;
            DataResult res;
            try {
                res = LoadUsuarios();
                if(!res.getStatus())
                    ret = res.getMsg();
                else {
                    res = LoadEmpresas();
                    if(!res.getStatus())
                        ret = res.getMsg();
                    else {
                        res = LoadMotivosAtrasos();
                        if(!res.getStatus())
                            ret = res.getMsg();
                        else {
                            res = LoadSituacoes();
                            if(!res.getStatus())
                                ret = res.getMsg();
                            else {
                                res = LoadVeiculos();
                                if(!res.getStatus())
                                    ret = res.getMsg();
                                else {
                                    ret = "Importação realizada com sucesso";
                                }
                            }
                        }
                    }
                }
            } catch (Exception e) {
                e.printStackTrace();
                ret = e.getMessage();
                if (ret == null) {
                    ret = "Erro na conexão com o servidor - verifique o endereço.";
                }
            }

            return ret;//returning populated array
        }

        @Override
        protected void onPreExecute() {
            super.onPreExecute();
            progressD = new ProgressDialog(CadastroImportActivity.this);
            progressD.setProgressStyle(ProgressDialog.STYLE_HORIZONTAL);
            progressD.setProgress(0);
            progressD.setMax(Integer.valueOf(100));
            progressD.setMessage("teste");
            ImageButton btnImportar = (ImageButton) findViewById(R.id.btnImportar);
            btnImportar.setEnabled(false);
        }

        @Override
        protected void onPostExecute(String result) {
            Toast.makeText(CadastroImportActivity.this, result, Toast.LENGTH_LONG).show();
            progressD.dismiss();
            ImageButton btnImportar = (ImageButton) findViewById(R.id.btnImportar);
            btnImportar.setEnabled(true);
            Intent returnIntent = new Intent();
            setResult(RESULT_OK, returnIntent);
            finish();
        }

        @Override
        protected void onCancelled() {
            Intent returnIntent = new Intent();
            setResult(RESULT_CANCELED, returnIntent);

        }


        @Override
        protected void onProgressUpdate(String... values) {
            TextView txtProgresso = (TextView) findViewById(R.id.txtImportProgress);
            ProgressBar prog = (ProgressBar) findViewById(R.id.import_progress);
            txtProgresso.setText(String.valueOf(values[1]));
            if(values[0].equals("0")){
                prog.setVisibility(View.VISIBLE);
            } else if (values[0].equals("1")){
                prog.setVisibility(View.GONE);
                progressD.setMessage(values[1]);
                progressD.setProgress(0);
                progressD.setMax(Integer.valueOf(values[2]));
                progressD.show();
            }else if (values[0].equals("2")){
                progressD.setProgress(Integer.valueOf(values[2]));
            }
        }



        public DataResult LoadUsuarios() {
            try {
                publishProgress("0", "Buscando dados de Usuários...");
                Map<String, String> params = new HashMap<String, String>();
                DataResult res = Utils.getDataFromServer("getUsuarios", getApplicationContext(), config, params);
                if(res.getStatus()) {
                    JSONObject json = new JSONObject(res.getMsg());
                    JSONArray dados = json.getJSONArray("dados");
                    List<Usuario> listUsuarios = new ArrayList<Usuario>();
                    for (int i = 0; i < dados.length(); i++) {
                        JSONObject c = dados.getJSONObject(i);
                        String codigo = c.getString("id");
                        String usuario = c.getString("email");
                        String senha = "";//c.getString("password");
                        Usuario objUsuario = new Usuario(Integer.parseInt(codigo), usuario, senha);
                        listUsuarios.add(objUsuario);
                        publishProgress("2", "Carregando dados de Usuários...", String.valueOf(i + 1));
                    }
                    DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
                    res.setStatus(dbHandler.importUsuario(listUsuarios));
                    if(!res.getStatus())
                        res.setMsg("Erro ao gravar usuários no banco de dados.");
                    return res;
                }
                return res;

            } catch (JSONException e) {
                e.printStackTrace();
                return new DataResult(false, e.getMessage());
            }
        }
        public DataResult LoadEmpresas() {
            try {
                publishProgress("0", "Buscando dados de Revenda...");
                Map<String, String> params = new HashMap<String, String>();
                DataResult res = Utils.getDataFromServer("getEmpresas", getApplicationContext(), config, params);
                if(res.getStatus()) {
                    JSONObject json = new JSONObject(res.getMsg());
                    JSONArray dados = json.getJSONArray("dados");
                    List<Empresa> listEmpresas = new ArrayList<Empresa>();
                    for (int i = 0; i < dados.length(); i++) {
                        JSONObject c = dados.getJSONObject(i);
                        String codigo = c.getString("codigo");
                        String razao_social = c.getString("razao_social");
                        String nome_fantasia = c.getString("nome_fantasia");
                        String servidor = "";
                        int valida_gb = c.getInt("valida_gb");
                        int valida_atraso = c.getInt("valida_atraso");
                        int tempo_entrega = c.getInt("tempo_entrega");
                        int tempo_entrega_urgente = c.getInt("tempo_entrega_urgente");
                        int validaCoordenadas = c.getInt("valida_coordenadas");
                        int valida_pix = c.getInt("valida_pix");

                        Empresa objEmpresa = new Empresa(Integer.parseInt(codigo), razao_social, nome_fantasia, servidor);
                        objEmpresa.setValidaGB(valida_gb);
                        objEmpresa.setValidaAtraso(valida_atraso);
                        objEmpresa.setTempoEntrega(tempo_entrega);
                        objEmpresa.setTempoEntregaUrgente(tempo_entrega_urgente);
                        objEmpresa.setValidaCoordenadas(validaCoordenadas);
                        objEmpresa.setValidaPix(valida_pix);
                        // adding HashList to ArrayList
                        listEmpresas.add(objEmpresa);
                    }
                    DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
                    res.setStatus(dbHandler.importEmpresa(listEmpresas));
                    if(!res.getStatus())
                        res.setMsg("Erro ao gravar Revendas no banco de dados.");
                    return res;
                }
                return res;

            } catch (JSONException e) {
                e.printStackTrace();
                return new DataResult(false, e.getMessage());
            }
        }
        public DataResult LoadMotivosAtrasos() {
            try {
                publishProgress("0", "Buscando dados de Motivos de Atrasos...");
                Map<String, String> params = new HashMap<String, String>();
                DataResult res = Utils.getDataFromServer("getPedidosMotivosAtrasos", getApplicationContext(), config, params);
                if(res.getStatus()) {
                    JSONObject json = new JSONObject(res.getMsg());
                    JSONArray dados = json.getJSONArray("dados");
                    List<MotivoAtraso> listMotivos = new ArrayList<MotivoAtraso>();
                    for (int i = 0; i < dados.length(); i++) {
                        JSONObject c = dados.getJSONObject(i);
                        String codigo = c.getString("id");
                        String descricao = c.getString("descricao");
                        MotivoAtraso objMotivo = new MotivoAtraso(Integer.parseInt(codigo), descricao);
                        listMotivos.add(objMotivo);
                        publishProgress("2", "Carregando dados de Motivos de Atrasos...", String.valueOf(i+1));
                    }
                    DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
                    res.setStatus(dbHandler.importMotivosAtrasos(listMotivos));
                    if(!res.getStatus())
                        res.setMsg("Erro ao gravar Motivos de Atrasos no banco de dados.");
                    return res;
                }
                return res;

            } catch (JSONException e) {
                e.printStackTrace();
                return new DataResult(false, e.getMessage());
            }
        }
        public DataResult LoadSituacoes() {
            try {
                publishProgress("0", "Buscando dados de Situações...");
                Map<String, String> params = new HashMap<String, String>();
                DataResult res = Utils.getDataFromServer("getPedidosSituacoes", getApplicationContext(), config, params);
                if(res.getStatus()) {
                    JSONObject json = new JSONObject(res.getMsg());
                    JSONArray dados = json.getJSONArray("dados");
                    List<Situacao> listSituacoes = new ArrayList<Situacao>();
                    for (int i = 0; i < dados.length(); i++) {
                        JSONObject c = dados.getJSONObject(i);
                        String codigo = c.getString("id");
                        String descricao = c.getString("descricao");
                        int entrega_finalizada = c.getInt("entregafinalizada");
                        int entrega_cancelada = c.getInt("entregacancelada");
                        int entrega_pendente = c.getInt("entregapendente");
                        int entrega_transferida = c.getInt("entregatranferida");
                        int em_entrega = c.getInt("ementrega");
                        int valegas = c.getInt("valegas");
                        int mensagem_enviada = c.getInt("pedidorecebidomovel");
                        int mensagem_lida = c.getInt("pedidolidomovel");
                        int cartao = c.getInt("solicitacartaoautorizacao");
                        Situacao objSituacao = new Situacao(Integer.parseInt(codigo), descricao, entrega_finalizada, entrega_pendente, entrega_cancelada, entrega_transferida, em_entrega, valegas, mensagem_enviada, mensagem_lida);
                        objSituacao.setCartao(cartao);
                        listSituacoes.add(objSituacao);
                        publishProgress("2", "Carregando dados de Situações...", String.valueOf(i+1));
                    }
                    DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
                    res.setStatus(dbHandler.importSituacoes(listSituacoes));
                    if(!res.getStatus())
                        res.setMsg("Erro ao gravar Situações no banco de dados.");
                    return res;
                }
                return res;

            } catch (JSONException e) {
                e.printStackTrace();
                return new DataResult(false, e.getMessage());
            }
        }
        public DataResult LoadVeiculos() {
            try {
                publishProgress("0", "Buscando dados de Veículos...");
                Map<String, String> params = new HashMap<String, String>();
                DataResult res = Utils.getDataFromServer("getVeiculos", getApplicationContext(), config, params);
                if(res.getStatus()) {
                    JSONObject json = new JSONObject(res.getMsg());
                    JSONArray dados = json.getJSONArray("dados");
                    List<Veiculo> listVeiculos = new ArrayList<Veiculo>();
                    for (int i = 0; i < dados.length(); i++) {
                        JSONObject c = dados.getJSONObject(i);
                        String codigo = c.getString("id");
                        String descricao = c.getString("descricao");
                        String ativo = c.getString("ativo");
                        String placa = c.getString("placa");
                        Veiculo objVeiculo = new Veiculo(Integer.parseInt(codigo), descricao, placa, Integer.parseInt(ativo));
                        listVeiculos.add(objVeiculo);
                        publishProgress("2", "Carregando dados de Veículos...", String.valueOf(i+1));
                    }
                    DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
                    res.setStatus(dbHandler.importVeiculos(listVeiculos));
                    if(!res.getStatus())
                        res.setMsg("Erro ao gravar Veículos no banco de dados.");
                    return res;
                }
                return res;

            } catch (JSONException e) {
                e.printStackTrace();
                return new DataResult(false, e.getMessage());
            }
        }



    }

}
