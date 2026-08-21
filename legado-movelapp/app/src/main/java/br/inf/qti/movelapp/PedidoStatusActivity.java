package br.inf.qti.movelapp;

import android.app.AlertDialog;
import android.content.Context;
import android.content.DialogInterface;
import android.content.Intent;
import android.graphics.BitmapFactory;
import android.os.AsyncTask;
import android.os.Bundle;
//import android.support.design.widget.FloatingActionButton;
//import android.support.design.widget.Snackbar;
//import android.support.v7.app.AppCompatActivity;
//import android.support.v7.widget.Toolbar;
import android.util.Base64;
import android.view.View;
import android.view.inputmethod.InputMethodManager;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.EditText;
import android.widget.ImageButton;
import android.widget.ImageView;
import android.widget.ProgressBar;
import android.widget.Spinner;
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

public class PedidoStatusActivity extends AppCompatActivity {

    private static atualizaPedidoTask task;
    public String pedidoId;
    public String statusId;
    public String pedeMotivoAtraso;
    public String pedeCartao;
    public String latitude;
    public String longitude;
    public int codMotivoAtraso;
    public String cartaoAutorizacao;
    public EditText txtCartao;
    public boolean conferePix;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_pedido_status);
        Toolbar toolbar = (Toolbar) findViewById(R.id.toolbar);
        setSupportActionBar(toolbar);
        txtCartao = (EditText) findViewById(R.id.txtCartaoAutorizacao);
        pedidoId = getIntent().getStringExtra("pedidoId");
        statusId = getIntent().getStringExtra("statusId");
        pedeMotivoAtraso = getIntent().getStringExtra("pedeMotivoAtraso");
        pedeCartao = getIntent().getStringExtra("pedeCartao");
        longitude = getIntent().getStringExtra("longitude");
        latitude = getIntent().getStringExtra("latitude");
        conferePix = getIntent().getStringExtra("conferePix").equals("true");

        //Verificar conexão
        if (!Utils.verificaConexao(getApplicationContext())) {
            Toast.makeText(PedidoStatusActivity.this, "Operação não realizada - sem conexão com Internet", Toast.LENGTH_LONG).show();
            Intent returnIntent = new Intent();
            setResult(RESULT_OK, returnIntent);
            returnIntent.putExtra("ERRO", "true");
            finish();

        } else {
            if(pedeMotivoAtraso.equals("false") && pedeCartao.equals("false")) {
                //if(conferePix){
                //    taskPix = new conferirPixTask();
                //    taskPix.execute();
                //} else {
                    task = new atualizaPedidoTask();
                    task.execute();
                //}
            } else {
                if(pedeMotivoAtraso.equals("true")) {
                    //Configura label atraso
                    TextView txtAtualizar = (TextView) findViewById(R.id.lblMotivoAtraso);
                    txtAtualizar.setVisibility(View.VISIBLE);
                    //Configura combobox
                    Spinner spinMotivo = (Spinner) findViewById(R.id.spinMotivosAtrasos);
                    spinMotivo.setVisibility(View.VISIBLE);
                    DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());

                    final LinkedList<MotivoAtraso> motivos = dbHandler.getMotivosAtraso();
                    ArrayAdapter<MotivoAtraso> dataAdapter = new ArrayAdapter<MotivoAtraso>(this,
                            android.R.layout.simple_spinner_item, motivos);

                    //SimpleCursorAdapter dataAdapter = new SimpleCursorAdapter(this, android.R.layout.simple_spinner_item, clientes, from, to);
                    dataAdapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);

                    spinMotivo.setAdapter(dataAdapter);

                    spinMotivo.setOnItemSelectedListener(
                            new AdapterView.OnItemSelectedListener() {
                                public void onItemSelected(
                                        AdapterView<?> parent,
                                        View view,
                                        int position,
                                        long id) {
                                    int id1 = ((MotivoAtraso) parent.getItemAtPosition(position)).getId();
                                    if (id1 >= -1) {
                                        codMotivoAtraso = id1;
                                    }
                                }

                                public void onNothingSelected(AdapterView<?> parent) {
                                }
                            }
                    );
                }
                if(pedeCartao.equals("true")) {
                    //Configura label cartao
                    TextView lblCartao = (TextView) findViewById(R.id.lblCartaoAutorizacao);
                    lblCartao.setVisibility(View.VISIBLE);
                    //Configura edit cartao
                    txtCartao.setVisibility(View.VISIBLE);
                    txtCartao.requestFocus();
                    InputMethodManager imm = (InputMethodManager) getSystemService(Context.INPUT_METHOD_SERVICE);
                    //imm.showSoftInput(txtCartao, InputMethodManager.SHOW_FORCED);
                    imm = (InputMethodManager) getSystemService(Context.INPUT_METHOD_SERVICE);
                    imm.toggleSoftInput(InputMethodManager.SHOW_FORCED, 0);
                }
                ImageButton btnAtualizar = (ImageButton) findViewById(R.id.btnAtualizarStatus);
                btnAtualizar.setVisibility(View.VISIBLE);
                btnAtualizar.setOnClickListener(new View.OnClickListener() {
                    @Override
                    public void onClick(View view) {
                        if(txtCartao.getText().toString().trim().equals("")){
                            Toast.makeText(PedidoStatusActivity.this, "Informe o CV (Código da Venda) do Cartão", Toast.LENGTH_SHORT).show();
                        } else{
                            cartaoAutorizacao = txtCartao.getText().toString();
                            //if(conferePix){
                            //    taskPix = new conferirPixTask();
                            //    taskPix.execute();
                            //} else {
                                task = new atualizaPedidoTask();
                                task.execute();
                            //}
                        }
                    }
                });
            }
        }
    }
    public class atualizaPedidoTask extends AsyncTask<String, String, String> {

        @Override
        protected String doInBackground(String... strings) {

            DataResult retorno = atualizaPedido();
            return retorno.getMsg();//returning populated array
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
                AlertDialog.Builder builder = new AlertDialog.Builder(PedidoStatusActivity.this);
                builder.setTitle("Erro ao atualizar pedido " + pedidoId);
                builder.setMessage(result);

                builder.setPositiveButton("OK", new DialogInterface.OnClickListener() {
                    @Override
                    public void onClick(DialogInterface dialog, int which) {
                        dialog.dismiss();
                        Intent returnIntent = new Intent();
                        setResult(RESULT_OK, returnIntent);
                        returnIntent.putExtra("ERRO", "true");
                        finish();
                    }
                });
                AlertDialog alert = builder.create();
                alert.show();
            } else {
                Intent returnIntent = new Intent();
                setResult(RESULT_OK, returnIntent);
                returnIntent.putExtra("ERRO", "false");
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

        public DataResult atualizaPedido() {

            try {
                // url to get all users list
                DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
                Config config = dbHandler.getConfig();
                //confere pix
                if(conferePix){
                    Map<String, String> params = new HashMap<String, String>();
                    params.put("pedido_id", pedidoId);
                    DataResult res = Utils.getDataFromServer("pix/transacaocompleta", getApplicationContext(), config, params);
                    if(res.getStatus()) {
                        JSONObject json = new JSONObject(res.getMsg());
                        String status = json.getString("status");
                        if (status.equals("OK")) {
                            JSONObject data = json.getJSONObject("data");
                            boolean finalizou = data.getBoolean("istransactioncomplete");
                            if(finalizou){
                                res.setMsg("");
                                res.setStatus(true);
                            } else {
                                res.setStatus(false);
                                res.setMsg("Recebimento do PIX ainda não confirmado. Por favor, tente mais tarde.");
                                return res;
                            }
                        } else {
                            res.setMsg(json.getString("dados"));
                            res.setStatus(false);
                        }
                    }
                    if(!res.getStatus() || res.getMsg().length() > 0) {
                        res.setStatus(false);
                        res.setMsg("Não foi possível confirmar o recebimento do PIX. Por favor, tente mais tarde.");
                        return res;
                    }
                }

                if(longitude == null)
                    longitude = "";
                if(latitude == null)
                    latitude = "";
                Map<String, String> params = new HashMap<String, String>();
                params.put("pedido_id", pedidoId);
                params.put("pedidosituacao_id", statusId);
                params.put("latitude", latitude);
                params.put("longitude", longitude);
                params.put("pedidomotivoatraso_id", (pedeMotivoAtraso.equals("true")?String.valueOf(codMotivoAtraso):"-1"));
                params.put("cartao_autorizacao", (pedeCartao.equals("true")?cartaoAutorizacao:""));

                DataResult res = Utils.getDataFromServer("setPedidoSituacao", getApplicationContext(), config, params);
                if(res.getStatus()) {
                    JSONObject json = new JSONObject(res.getMsg());
                    String status = json.getString("status");

                    if (status.equals("OK")) {
                        //Atualizar pedido
                        Pedido pedido = new Pedido(Integer.parseInt(pedidoId), "", "", "", 0.0, "", "", "", "", "", "", Integer.parseInt(statusId), "");
                        pedido.setDescStatus(dbHandler.getSituacao(Integer.valueOf(statusId)).descricao);
                        if (dbHandler.atualizaStatusPedido(pedido)) {
                            res.setStatus(true);
                            res.setMsg("");
                        } else {
                            res.setStatus(false);
                            res.setMsg("Erro ao atualizar pedido no dispositivo local.");
                        }
                    } else {
                        res.setMsg(json.getString("dados"));
                        res.setStatus(false);
                    }
                }
                return res;
            } catch (Throwable t) {
                return new DataResult(false, t.toString());
            }
        }

    }

}
