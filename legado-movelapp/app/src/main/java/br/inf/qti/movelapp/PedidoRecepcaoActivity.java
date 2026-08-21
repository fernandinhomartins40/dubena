package br.inf.qti.movelapp;

import android.app.AlertDialog;
import android.content.Context;
import android.content.DialogInterface;
import android.content.Intent;
import android.net.ConnectivityManager;
import android.os.AsyncTask;
import android.os.Bundle;
//import android.support.design.widget.FloatingActionButton;
//import android.support.design.widget.Snackbar;
//import android.support.v7.app.AppCompatActivity;
//import android.support.v7.widget.Toolbar;
import android.view.View;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;
import androidx.appcompat.widget.Toolbar;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.LinkedList;
import java.util.List;
import java.util.Map;

public class PedidoRecepcaoActivity extends AppCompatActivity {

    private static exportPedidoTask task;
    public String pedidoId;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_pedido_recepcao);
        Toolbar toolbar = (Toolbar) findViewById(R.id.toolbar);
        setSupportActionBar(toolbar);

        pedidoId = getIntent().getStringExtra("pedidoId");

        //Verificar conexão
        if (!Utils.verificaConexao(this.getApplicationContext())) {
            Toast.makeText(PedidoRecepcaoActivity.this, "Operação não realizada - sem conexão com Internet", Toast.LENGTH_LONG).show();
            Intent returnIntent = new Intent();
            setResult(RESULT_OK, returnIntent);
            finish();

        } else {
            task = new exportPedidoTask();
            task.execute();
        }
    }

    /**
     * Created by flavio on 22/09/2014.
     */

    public class exportPedidoTask extends AsyncTask<String, String, String> {

        @Override
        protected String doInBackground(String... strings) {

            String retorno = "";
            DataResult res;

            res = getPedidosPendentes();
            if(!res.getStatus())
                retorno = res.getMsg();
            else {
                retorno = "";
            }
            return retorno;
        }

        @Override
        protected void onPreExecute() {
            super.onPreExecute();
            ProgressBar prog = (ProgressBar) findViewById(R.id.import_progress);
            prog.setVisibility(View.VISIBLE);
        }

        @Override
        protected void onPostExecute(String result) {
            if(result.length() > 0) {
                AlertDialog.Builder builder = new AlertDialog.Builder(PedidoRecepcaoActivity.this);
                builder.setTitle("Erro ao receber pedido " + pedidoId);
                builder.setMessage(result);

                builder.setPositiveButton("OK", new DialogInterface.OnClickListener() {
                    @Override
                    public void onClick(DialogInterface dialog, int which) {
                        dialog.dismiss();
                        Intent returnIntent = new Intent();
                        setResult(RESULT_OK, returnIntent);
                        finish();
                    }
                });
                AlertDialog alert = builder.create();
                alert.show();
            } else {
                Intent returnIntent = new Intent();
                setResult(RESULT_OK, returnIntent);
                finish();
            }
        }


        @Override
        protected void onProgressUpdate(String... values) {
            TextView txtProgresso = (TextView) findViewById(R.id.txtImportProgress);
            txtProgresso.setText(String.valueOf(values[1]));
            if (values[0].equals("0")) {
            } else if (values[0].equals("1")) {
            } else if (values[0].equals("2")) {
            }
        }

        public DataResult getPedidosPendentes() {
            try {
                // url to get all users list
                DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
                Config config = dbHandler.getConfig();
                Map<String, String> params = new HashMap<String, String>();
                DataResult res = Utils.getDataFromServer("getPedidosPendentes", getApplicationContext(), config, params);
                if(res.getStatus()) {
                    JSONObject json = new JSONObject(res.getMsg());
                    JSONArray dados = json.getJSONArray("dados");
                    List<Pedido> listPedidos = new ArrayList<Pedido>();
                    for (int i = 0; i < dados.length(); i++) {
                        JSONObject c = dados.getJSONObject(i);
                        String codigo = c.getString("id");
                        String razao_social = c.getString("razao_social");
                        String data = c.getString("datahora");
                        String condicao = c.getString("condicao");
                        String valor_venda = c.getString("valorvenda");
                        String rua = c.getString("entregarua");
                        String numero = c.getString("entreganumero");
                        String complemento = c.getString("entregacomplemento");
                        String observacao = c.getString("observacao");
                        String bairro = c.getString("entregabairro");
                        String ponto_referencia = c.getString("entregapontoreferencia");
                        String cod_status = c.getString("pedidosituacao_id");
                        String desc_status = c.getString("pedidosituacao_descricao");
                        String cidade = c.getString("entregacidade");
                        String uf = c.getString("entregauf");
                        String urgente = c.getString("urgente");
                        int cod_motivo_atraso = c.getInt("motivo_atraso");
                        String convenio = c.getString("convenio");
                        String app = c.getString("app");
                        int cartao = c.getInt("cartao");
                        int gasdopovo = c.getInt("gasdopovo");

                        Pedido objPedido = new Pedido(Integer.parseInt(codigo), data, razao_social, condicao, Double.valueOf(valor_venda), rua, numero, complemento, observacao, bairro, ponto_referencia, Integer.parseInt(cod_status), desc_status);
                        objPedido.setCidade(cidade);
                        objPedido.setUf(uf);
                        objPedido.setUrgente(urgente);
                        objPedido.setCodMotivoAtraso(cod_motivo_atraso);
                        objPedido.setConvenio(convenio);
                        objPedido.setCartao(cartao);
                        objPedido.setApp(app);
                        objPedido.setGasdopovo(gasdopovo);
                        JSONArray itens = c.getJSONArray("itens");
                        objPedido.itens = new LinkedList<PedidoItem>();
                        for (int j = 0; j < itens.length(); j++) {
                            JSONObject itemJ = itens.getJSONObject(j);
                            PedidoItem item = new PedidoItem(itemJ.getInt("id"), itemJ.getInt("pedido_id"), itemJ.getString("produto"), itemJ.getDouble("quantidade"), itemJ.getDouble("precovendaunitario"), itemJ.getDouble("precovendatotal"),  itemJ.getString("unidademedida"));
                            objPedido.itens.add(item);
                        }
                        // adding HashList to ArrayList
                        listPedidos.add(objPedido);
                    }
                    res.setStatus(dbHandler.importPedido(listPedidos));
                    if(!res.getStatus())
                        res.setMsg("Erro ao gravar Pedido no banco de dados.");
                    return res;
                }
                return res;
           } catch (Throwable t) {
                return new DataResult(false, t.toString());
            }
        }

    }
}
