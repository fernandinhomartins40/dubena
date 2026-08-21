package br.inf.qti.movelapp;

import android.content.Context;
import android.content.Intent;
import android.graphics.Bitmap;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.Paint;
import android.graphics.Rect;
import android.net.ConnectivityManager;
import android.os.AsyncTask;
import android.os.Bundle;
//import android.support.v7.app.AppCompatActivity;
import android.view.MenuItem;
import android.view.MotionEvent;
import android.view.View;
import android.widget.ScrollView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.util.HashMap;
import java.util.LinkedList;
import java.util.Map;

public class BoletoImpressaoActivity extends AppCompatActivity {

    private static importNotaFiscalTask task;
    public Bitmap retBitmap = null;
    DrawView mDrawing;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_print_boleto);

        task = new importNotaFiscalTask();
        task.execute();

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


    public  boolean verificaConexao() {
        boolean conectado;
        ConnectivityManager conectivtyManager = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        if (conectivtyManager.getActiveNetworkInfo() != null
                && conectivtyManager.getActiveNetworkInfo().isAvailable()
                && conectivtyManager.getActiveNetworkInfo().isConnected()) {
            conectado = true;
        } else {
            conectado = false;
        }
        return conectado;
    }

    public void viewNF(){
        if(this.retBitmap != null) {
            mDrawing = new DrawView(this);
            ScrollView.LayoutParams lp = new ScrollView.LayoutParams(1000, 1000);
            setContentView(mDrawing, lp);
        }
    }


    /**
     * Created by flavio on 22/09/2014.
     */

    public class importNotaFiscalTask extends AsyncTask<String, String, String> {


        // Creating JSON Parser object
        JSONParser jParser = new JSONParser();

        // url to get all users list
        private String url = "http://www.gasemcasa.com.br/export/";
        private String url_all_notas_fiscais = url + "notafiscal.php";

        // JSON Node names
        private static final String TAG_SUCCESS = "success";
        // Table usuarios
        private static final String TAG_NOTAFISCAL = "notasfiscais";
        private static final String TAG_CODIGO = "codigo";
        private static final String TAG_USUARIO = "usuario";
        private static final String TAG_SENHA = "senha";
        JSONArray notafiscalJSON = null;
        JSONArray itensJSON = null;
        JSONArray parcelasJSON = null;
        public Bitmap retBitmapTask = null;

        //protected ProgressDialog progressD;

        @Override
        protected String doInBackground(String... strings) {
            String ret;
            int tentativa = 0;
            int maxTentativas = 8;
            String retorno = "0"; //0=não encontrou NF na base; 1=encontrou NF na base, mas está com situação <> 100; 2=acho NF autorizada; 8=erro conexão impressora 9=erro na impressão
            DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
            Empresa empresa = dbHandler.getEmpresa();
            if (1 == 1) {
                if (1 == 1) {
                    url = empresa.getServidor();
                    url_all_notas_fiscais = "http://192.168.0.107/ctrl2/public/api/nfeConsultaTeste?nfce_id=2913&colaborador_id=345";

                    while (tentativa <= 0 && (retorno.equals("0") || retorno.equals("1"))) {
                        retorno = LoadNotaFiscal();
                        tentativa++;
                        if (retorno.equals("0") || retorno.equals("1")) {
                            try {
                                Thread.sleep(5000);
                            } catch (InterruptedException e) {
                                e.printStackTrace();
                            }
                        }
                    }

                    if (retorno.equals("2")) {
                        ret = "2Impressão Finalizada";
                    } else if (retorno.equals("1")) {
                        ret = "1Nota Fiscal não autorizada. Aguarde 1 minuto e tente novamente. Se o erro persistir, entre em contato com o setor administrativo.";
                    } else if (retorno.equals("0")) {
                        ret = "0Esgotado o número máximo de tentativas de buscar a NF";
                    } else {
                        if (retorno.substring(0, 1).equals("8") || retorno.substring(0, 1).equals("9")) {
                            ret = retorno;
                        } else {
                            ret = "8Processo finalizado";
                        }
                    }
                } else {
                    ret = "8Configuração de endereço do servidor incorreta";
                }
            } else {
                ret = "8Configuração de empresa incorreta";
            }

            return ret;//returning populated array
        }

        @Override
        protected void onPreExecute() {
            super.onPreExecute();
            //ProgressBar prog = (ProgressBar) findViewById(R.id.import_progress);
            //prog.setVisibility(View.VISIBLE);
        }

        @Override
        protected void onPostExecute(String result) {
            BoletoImpressaoActivity.this.retBitmap = this.retBitmapTask;
            if (BoletoImpressaoActivity.this.retBitmap == null || !result.substring(0, 1).equals("2")) {
                Toast.makeText(BoletoImpressaoActivity.this, result.substring(1, result.length()), Toast.LENGTH_LONG).show();
                Intent returnIntent = new Intent();
                setResult(RESULT_OK, returnIntent);
                finish();
            }
            BoletoImpressaoActivity.this.viewNF();
        }


        public String LoadNotaFiscal() {
            // Building Parameters
            try {
                Map<String, String> params = new HashMap<String, String>();
                params.put("nfce_id", "2913");
                params.put("colaborador_id", "345");
                DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
                Config config = dbHandler.getConfig();

                String msg = "{\"dataVencimento\":\"10/05/2020\",\"valor\":85,\"multa\":0,\"juros\":0.99,\"numero\":\"109/00003432\",\"numeroDV\":\"7\",\"numeroDocumento\":\"9024\",\"pagador\":{\"nome\":\"Flavio Hideki Ono\",\"endereco\":\"Rua Luiz Carollo, 264\",\"bairro\":\"Vila Bela\",\"cep\":\"85025-040\",\"uf\":\"PR\",\"cidade\":\"Guarapuava\",\"documento\":null},\"beneficiario\":{\"nome\":\"Distribuidora Dubena Ltda\",\"endereco\":\"Rodovia PR-466, 1277\",\"cep\":\"85050-290\",\"uf\":\"PR\",\"cidade\":\"Guarapuava\",\"documento\":\"04.190.715/0001-05\",\"bairro\":\"Primavera\"},\"agencia\":\"3857\",\"conta\":\"59604\",\"contaDv\":\"7\",\"carteira\":\"109\",\"codigoCliente\":\"385759604\",\"descricaoDemonstrativo\":[\"MULTA DE R$ R$ 1,70 APÓS O VENCIMENTO\\r\\nJUROS DE R$ R$ 0,03 AO DIA\\r\\nNÃO RECEBER APOS 29 DIAS DE ATRASO\",\"1\"],\"instrucoes\":[\"MULTA DE R$ R$ 1,70 APÓS O VENCIMENTO#JUROS DE R$ R$ 0,03 AO DIA#NÃO RECEBER APOS 29 DIAS DE ATRASO\",\"1\"],\"aceite\":\"N\",\"especieDoc\":\"01\",\"desconto\":null,\"jurosApos\":1,\"dataDocumento\":\"10/04/2020\",\"localPagamento\":\"PAGÁVEL EM QUALQUER BANCO ATÉ O VENCIMENTO. APÓS O VENCIMENTO, ACESSE #ITAU.COM.BR/BOLETOS E PAGUE EM QUALQUER BANCO.\",\"cedente\":\"385759604-7\"}";
                JSONObject c = new JSONObject(msg);
                Boleto bol = new Boleto();
                bol.setVencimento(c.getString("dataVencimento"));
                bol.setDataDocumento(c.getString("dataDocumento"));
                bol.setValor(c.getDouble("valor"));
                bol.setJuros(c.getDouble("juros"));
                bol.setMulta(c.getDouble("multa"));
                bol.setNossoNumero(c.getString("numero"));
                bol.setDvNossoNumero(c.getString("numeroDV"));
                bol.setDocumento(c.getString("numeroDocumento"));
                JSONObject pag = c.getJSONObject("pagador");
                bol.setPagadorNome(pag.getString("nome"));
                bol.setPagadorEndereco(pag.getString("endereco"));
                bol.setPagadorBairro(pag.getString("bairro"));
                bol.setPagadorCep(pag.getString("cep"));
                bol.setPagadorCidade(pag.getString("cidade"));
                bol.setPagadorUf(pag.getString("uf"));
                bol.setPagadorDocumento(pag.getString("documento"));
                JSONObject ben = c.getJSONObject("beneficiario");
                bol.setBenefNome(ben.getString("nome"));
                bol.setBenefEndereco(ben.getString("endereco"));
                bol.setBenefBairro(ben.getString("bairro"));
                bol.setBenefCep(ben.getString("cep"));
                bol.setBenefCidade(ben.getString("cidade"));
                bol.setBenefUf(pag.getString("uf"));
                bol.setBenefDocumento(ben.getString("documento"));
                bol.setAgencia(c.getString("agencia"));
                bol.setConta(c.getString("conta"));
                bol.setDvConta(c.getString("contaDv"));
                bol.setCarteira(c.getString("carteira"));
                bol.setCodigoCliente(c.getString("codigoCliente"));
                bol.setAceite(c.getString("aceite"));
                bol.setEspecie(c.getString("especieDoc"));
                bol.setCedente(c.getString("cedente"));
                bol.setLinhaDigitavel("03399.64355 86600.000003 08288.001012 1 61950000000123");
                bol.setCodigoBarras("03391619500000001239643586600000000828800101");
                bol.setBanco("341");
                bol.setBancoDv("7");

                bol.instrucoes = new LinkedList<String>();
                String[] instrucoes = c.getString("instrucoes").split("#");
                for (int j = 0; j < instrucoes.length; j++) {
                    bol.instrucoes.add(instrucoes[j]);
                }
                bol.localPagamento = new LinkedList<String>();
                String[] local = c.getString("localPagamento").split("#");
                for (int j = 0; j < local.length; j++) {
                    bol.localPagamento.add(local[j]);
                }

                BoletoImpressao nfI = new BoletoImpressao(bol, BoletoImpressaoActivity.this);
                this.retBitmapTask = nfI.gerarBoletoBmp();
                if (this.retBitmapTask == null) {
                    return "8Erro ao gerar a imagem da NF";
                } else {
                    return "2";
                }
            } catch (JSONException e) {
                e.printStackTrace();
                return "0";
            }
        }
    }

    private class DrawView extends View
    {
        private boolean move=false;
        private int X=0, Y=0, iX=0, iY=0;

        public DrawView(Context context) {
            super(context);
            //this.setBackgroundResource(R.color.window_background);
        }
        @Override

        public boolean onTouchEvent(final MotionEvent event)
        {
            boolean handled = false;
            int xTouch;
            int yTouch;
            int pointerId;
            int actionIndex = event.getActionIndex();

            switch (event.getActionMasked()) {
                case MotionEvent.ACTION_DOWN:
                    xTouch = (int) event.getX(0);
                    yTouch = (int) event.getY(0);

                    iX=(xTouch-X);
                    iY=(yTouch-Y);

                    invalidate();
                    handled = true;
                    move = true;
                    break;

                case MotionEvent.ACTION_POINTER_DOWN:
                    pointerId = event.getPointerId(actionIndex);

                    xTouch = (int) event.getX(actionIndex);
                    yTouch = (int) event.getY(actionIndex);

                    iX=(xTouch-X);
                    iY=(yTouch-Y);

                    invalidate();
                    handled = true;
                    move=true;
                    break;

                case MotionEvent.ACTION_MOVE:
                    final int pointerCount = event.getPointerCount();

                    for (actionIndex = 0; actionIndex < pointerCount; actionIndex++)
                    {
                        pointerId = event.getPointerId(actionIndex);

                        xTouch = (int) event.getX(actionIndex);
                        yTouch = (int) event.getY(actionIndex);

                        if (move) {
                            X = (xTouch - iX );
                            Y = (yTouch - iY);
                        }
                    }
                    invalidate();
                    handled = true;
                    break;

                case MotionEvent.ACTION_UP:
                    move=false;
                    invalidate();
                    handled = true;
                    break;

                case MotionEvent.ACTION_POINTER_UP:
                    move=false;
                    pointerId = event.getPointerId(actionIndex);
                    invalidate();
                    handled = true;
                    break;

                case MotionEvent.ACTION_CANCEL:
                    move=false;
                    handled = true;
                    break;

                default:
                    break;
            }

            return super.onTouchEvent(event) || handled;
        }

        protected void onDraw(Canvas canvas)
        {
            if (retBitmap!=null)
            {
                Paint myPaint = new Paint();
                myPaint.setColor(Color.BLACK);

                boolean resize=false;
                if (!resize){
                    canvas.drawBitmap(retBitmap, X, Y, myPaint);
                }
                else
                {
                    int ih = retBitmap.getHeight();
                    int iw = retBitmap.getWidth();
                    int mh = getHeight();
                    float fat=( ih / mh);
                    int mw = (int)((iw * mh)/ih);
                    canvas.drawBitmap(retBitmap,
                            new Rect(0,0,iw,ih),
                            new Rect(0,0,mw,mh),
                            myPaint);
                }
            }

        }
    }

}