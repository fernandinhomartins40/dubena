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
//import android.provider.Settings;
//import android.support.v7.app.AppCompatActivity;
import android.view.MenuItem;
import android.view.MotionEvent;
import android.view.View;
//import android.widget.ProgressBar;
import android.widget.ScrollView;
//import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.LinkedList;
import java.util.List;
import java.util.Map;

public class NotaFiscalImpressaoActivity extends AppCompatActivity {

    private static importNotaFiscalTask task;
    public Bitmap retBitmap = null;
    DrawView mDrawing;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_print_notafiscal);

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
            if (1==1) {
                if (1==1) {
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
            NotaFiscalImpressaoActivity.this.retBitmap = this.retBitmapTask;
            if (NotaFiscalImpressaoActivity.this.retBitmap == null || !result.substring(0, 1).equals("2")) {
                Toast.makeText(NotaFiscalImpressaoActivity.this, result.substring(1, result.length()), Toast.LENGTH_LONG).show();
                Intent returnIntent = new Intent();
                setResult(RESULT_OK, returnIntent);
                finish();
            }
            NotaFiscalImpressaoActivity.this.viewNF();
        }


        public String LoadNotaFiscal() {
            // Building Parameters
            try {
                Map<String, String> params = new HashMap<String, String>();
                params.put("nfce_id", "2913");
                params.put("colaborador_id", "345");
                DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
                Config config = dbHandler.getConfig();

                //String msg = "{\"data\":[{\"id\":2913,\"nfoperacao_id\":\"48\",\"chaveacesso\":\"41200404190715000105550010000090141869464884\",\"chaveacessodv\":\"4\",\"cfop\":\"5405\",\"descricaooperacao\":\"Venda de Glp\",\"formapagamento\":\"0\",\"nfmodelo\":\"55\",\"nfserie\":\"1\",\"nfnumero\":\"9014\",\"datahoraemissao\":\"2020-04-03 10:55:00\",\"datahoraentradasaida\":\"2020-04-03 10:55:00\",\"tipo\":\"1\",\"nftipoimpressao\":\"1\",\"nftipoemissao\":\"1\",\"nftipoambiente\":\"2\",\"nfefinalidade\":\"1\",\"nfprocessoemissao\":\"0\",\"nfversaoprocessamento\":\"1\",\"emitcnpj\":\"04190715000105\",\"emitcpf\":null,\"emitrazaosocial\":\"Distribuidora Dubena Ltda\",\"emitnomefantasia\":\"Distribuidora Dubena\",\"emitendereco\":\"Rodovia PR-466, 1277\",\"emitnumero\":\"1277\",\"emitcomplemento\":\"Sala 04\",\"emitbairro\":\"Primavera\",\"emitcidade_id\":\"4109401\",\"emitcidadenome\":\"Guarapuava\",\"emitcidadecodigoibge\":\"4109401\",\"emituf\":\"PR\",\"emitufcodigoibge\":\"41\",\"emitcep\":\"85050290\",\"emitpaisnome\":\"Brasil\",\"emitpaiscodigoibge\":\"1058\",\"emitie\":\"9022487490\",\"emitinscricaomunicipal\":null,\"emitcnae\":null,\"destcpf\":\"45926182115\",\"destrazaosocial\":\"Flavio Hideki Ono\",\"destendereco\":\"Rua Luiz Carollo, 264\",\"destnumero\":\"264\",\"destcomplemento\":\"Casa\",\"destbairro\":\"Vila Bela\",\"destcidade_id\":\"4109401\",\"destcidadenome\":\"Guarapuava\",\"destcidadecodigoibge\":\"4109401\",\"destuf\":\"PR\",\"destcep\":\"85025040\",\"destpaiscodigoibge\":\"1058\",\"destpaisnome\":\"Brasil\",\"destindicadorie\":\"9\",\"destemail\":null,\"fretemodalidade\":\"3\",\"fretecpf\":null,\"fretecnpj\":\"04190715000105\",\"freterazaosocial\":\"DISTRIBUIDORA DUBENA LTDA\",\"freteenderecocompl\":\"Matriz\",\"fretecidadenome\":\"Guarapuava\",\"fretuf\":\"PR\",\"fretie\":\"9022487490\",\"freteplaca\":\"ABC1B34\",\"freteplacauf\":\"PR\",\"informacaocomplementar\":null,\"informacaoadicionalfisco\":\"ICMS RET ANT P/ ST CFE ANEXO IX ART 63 DEC 7871/2017 RICMS PR.\",\"cliente_id\":\"57273\",\"codcnf\":null,\"codcdv\":null,\"codcrt\":\"3\",\"nitem\":\"1\",\"vbc\":\"0\",\"vicms\":\"0\",\"vbcst\":\"0\",\"vst\":\"0\",\"vprod\":\"85\",\"vfrete\":\"0\",\"vseg\":\"0\",\"vdesc\":\"0\",\"vii\":\"0\",\"vipi\":\"0\",\"vpis\":\"0\",\"vcofins\":\"0\",\"voutro\":\"0\",\"vnf\":\"85\",\"nfsituacao_id\":\"100\",\"financeiro_id\":null,\"fretefinanceiro_id\":null,\"planoconta_id\":\"2\",\"centrocusto_id\":\"7\",\"freteplanoconta_id\":null,\"fretecentrocusto_id\":null,\"user_id\":\"6\",\"fretecliente_id\":\"41882\",\"condicaopagamento_id\":\"297\",\"vbcfunrural\":null,\"vpfunrural\":null,\"vfunrural\":null,\"emissao\":null,\"descricaofinanceiro\":\"Pedido 46784\",\"datahoraautorizacao\":null,\"emittelefone\":\"4236293586\",\"desttelefone\":\"42999126564\",\"numeroreciboenvio\":\"411110219100715\",\"planocontadescricao\":\"Vendas de Glp\",\"naturezasped\":null,\"destie\":null,\"vfcpstret\":\"0\",\"vfcpst\":\"0\",\"vicmsufremet\":\"0\",\"vicmsufdest\":\"0\",\"vfcpufdest\":\"0\",\"vicmsdeson\":\"0\",\"vfcp\":\"0\",\"descricaosituacao\":\"Autorizado o uso da NF-e\",\"nfc_tpag\":null,\"iddest\":\"1\",\"indfinal\":\"1\",\"items\":[{\"id\":4998,\"grupo_id\":\"2\",\"empresa_id\":\"2\",\"nfemitida_id\":\"2913\",\"nfimposto_id\":\"61\",\"nfoperacao_id\":\"48\",\"setor_id\":\"103\",\"cprod\":\"50\",\"xprod\":\"Glp em Botiju00e3o de 13 Kg\",\"ncm\":\"27111910\",\"cfop\":\"5405\",\"ucom\":\"Und\",\"qcom\":\"1\",\"vuncom\":\"85\",\"vprod\":\"85\",\"utrib\":\"Kg\",\"qtrib\":\"13\",\"indtot\":\"1\",\"tagicms\":\"ICMS60\",\"orig\":\"0\",\"cst\":\"60\",\"tagpis\":\"PISNT\",\"cstpis\":\"4\",\"ppis\":\"0\",\"vpis\":\"0\",\"tagcofins\":\"COFINSNT\",\"pcofins\":\"0\",\"vcofins\":\"0\",\"qestoque\":\"1\",\"codigolote\":\" \",\"created_at\":\"2020-04-03 10:55:20\",\"updated_at\":\"2020-04-03 10:55:20\",\"qvol\":\"1\",\"pesol\":\"13\",\"pesob\":\"26\",\"customedio\":\"51.48\",\"cean\":\"SEM GTIN\",\"ceantrib\":\"SEM GTIN\",\"vbcstret\":null,\"vicmsstret\":null,\"vdesc\":null,\"vfrete\":null,\"cprodanp\":\"210203001\",\"qbcprod\":\"0\",\"modbc\":null,\"picmsst\":null,\"vbc\":null,\"picms\":null,\"vicms\":null,\"tagipi\":\"IPINT\",\"cstipi\":null,\"vbcipi\":null,\"pipi\":null,\"vipi\":null,\"vbcpis\":\"0\",\"valiqprod\":\"0\",\"vcide\":\"0\",\"cest\":\"0601100\",\"nitemped\":\"1\",\"modbcst\":null,\"pmvast\":null,\"predbcst\":null,\"vbcst\":null,\"vicmsst\":null,\"predbc\":null,\"vicmsdeson\":null,\"motdesicms\":null,\"vicmsdif\":null,\"pdif\":null,\"vicmsop\":null,\"pbcop\":null,\"vbcstdest\":null,\"pcredsn\":null,\"vcredicmssn\":null,\"pfcp\":null,\"vfcp\":null,\"vbcfcp\":null,\"vbcfcpst\":null,\"pfcpst\":null,\"vfcpst\":null,\"pst\":null,\"vbcfcpstret\":null,\"pfcpstret\":null,\"vfcpstret\":null,\"cstcofins\":\"4\",\"vbccofins\":\"0\",\"vseg\":null,\"voutro\":null,\"movimentaestoque\":\"0\",\"vpart\":\"6.54\",\"pgnn\":\"15.5\",\"pgni\":\"12.2\",\"pglp\":\"72.3\",\"vuntrib\":\"6.5384615385\"}],\"parcelas\":[{\"id\":62726,\"grupo_id\":\"2\",\"empresa_id\":\"2\",\"financeiro_id\":\"49582\",\"numero\":\"1\",\"datavencimento\":\"2020-05-03 00:00:00\",\"datacompetencia\":\"2020-04-03 10:55:00\",\"valor\":\"85\",\"multa\":\"0\",\"juros\":\"0\",\"desconto\":\"0\",\"valorefetivado\":\"85\",\"pagarreceber\":\"R\",\"baixado\":\"0\",\"created_at\":\"2020-04-03 10:55:19\",\"updated_at\":\"2020-04-03 10:57:17\",\"agrupamento_status\":\"0\",\"agrupador_financeiro_id\":null,\"datahorabaixa\":null,\"motivocancelamento\":null,\"boletogerado\":\"1\",\"financeirotaxa_id\":null}]}],\"msg\":\"Sucesso!\",\"status\":\"OK\"}";
                //String msg = "{\"data\":[{\"id\":2915,\"nfoperacao_id\":\"48\",\"chaveacesso\":\"41200404190715000105650010000090721575261206\",\"chaveacessodv\":\"6\",\"cfop\":\"5405\",\"descricaooperacao\":\"Venda de Glp\",\"formapagamento\":\"0\",\"nfmodelo\":\"65\",\"nfserie\":\"1\",\"nfnumero\":\"9072\",\"datahoraemissao\":\"2020-04-03 11:01:00\",\"datahoraentradasaida\":\"2020-04-03 11:01:00\",\"tipo\":\"1\",\"nftipoimpressao\":\"4\",\"nftipoemissao\":\"1\",\"nftipoambiente\":\"2\",\"nfefinalidade\":\"1\",\"nfprocessoemissao\":\"0\",\"nfversaoprocessamento\":\"1\",\"emitcnpj\":\"04190715000105\",\"emitcpf\":null,\"emitrazaosocial\":\"Distribuidora Dubena Ltda\",\"emitnomefantasia\":\"Distribuidora Dubena\",\"emitendereco\":\"Rodovia PR-466, 1277\",\"emitnumero\":\"1277\",\"emitcomplemento\":\"Sala 04\",\"emitbairro\":\"Primavera\",\"emitcidade_id\":\"4109401\",\"emitcidadenome\":\"Guarapuava\",\"emitcidadecodigoibge\":\"4109401\",\"emituf\":\"PR\",\"emitufcodigoibge\":\"41\",\"emitcep\":\"85050290\",\"emitpaisnome\":\"Brasil\",\"emitpaiscodigoibge\":\"1058\",\"emitie\":\"9022487490\",\"emitinscricaomunicipal\":null,\"emitcnae\":null,\"destcpf\":\"45926182115\",\"destrazaosocial\":\"Flavio Hideki Ono\",\"destendereco\":\"Rua Luiz Carollo, 264\",\"destnumero\":\"264\",\"destcomplemento\":\"Casa\",\"destbairro\":\"Vila Bela\",\"destcidade_id\":\"4109401\",\"destcidadenome\":\"Guarapuava\",\"destcidadecodigoibge\":\"4109401\",\"destuf\":\"PR\",\"destcep\":\"85025040\",\"destpaiscodigoibge\":\"1058\",\"destpaisnome\":\"Brasil\",\"destindicadorie\":\"9\",\"destemail\":null,\"fretemodalidade\":\"9\",\"fretecpf\":null,\"fretecnpj\":null,\"freterazaosocial\":null,\"freteenderecocompl\":null,\"fretecidadenome\":null,\"fretuf\":null,\"fretie\":null,\"freteplaca\":null,\"freteplacauf\":null,\"informacaocomplementar\":null,\"informacaoadicionalfisco\":\"ICMS RET ANT P\\/ ST CFE ANEXO IX ART 63 DEC 7871\\/2017 RICMS PR.\",\"cliente_id\":\"57273\",\"codcnf\":null,\"codcdv\":null,\"codcrt\":\"3\",\"nitem\":\"1\",\"vbc\":\"0\",\"vicms\":\"0\",\"vbcst\":\"0\",\"vst\":\"0\",\"vprod\":\"85\",\"vfrete\":\"0\",\"vseg\":\"0\",\"vdesc\":\"10\",\"vii\":\"0\",\"vipi\":\"0\",\"vpis\":\"0\",\"vcofins\":\"0\",\"voutro\":\"0\",\"vnf\":\"75\",\"nfsituacao_id\":\"100\",\"financeiro_id\":null,\"fretefinanceiro_id\":null,\"planoconta_id\":\"2\",\"centrocusto_id\":\"7\",\"freteplanoconta_id\":null,\"fretecentrocusto_id\":null,\"user_id\":\"6\",\"fretecliente_id\":null,\"condicaopagamento_id\":\"297\",\"vbcfunrural\":null,\"vpfunrural\":null,\"vfunrural\":null,\"emissao\":null,\"descricaofinanceiro\":\"Pedido 46786\",\"datahoraautorizacao\":null,\"emittelefone\":\"4236293586\",\"desttelefone\":\"42999126564\",\"numeroreciboenvio\":\"411000003687611\",\"planocontadescricao\":\"Vendas de Glp\",\"naturezasped\":null,\"destie\":null,\"vfcpstret\":\"0\",\"vfcpst\":\"0\",\"vicmsufremet\":\"0\",\"vicmsufdest\":\"0\",\"vfcpufdest\":\"0\",\"vicmsdeson\":\"0\",\"vfcp\":\"0\",\"descricaosituacao\":\"Autorizado o uso da NF-e\",\"nfc_tpag\":null,\"iddest\":\"1\",\"indfinal\":\"1\",\"xmlretorno\":\"<env:Envelope xmlns:env='http:\\/\\/www.w3.org\\/2003\\/05\\/soap-envelope'><env:Body xmlns:env='http:\\/\\/www.w3.org\\/2003\\/05\\/soap-envelope'><nfeResultMsg xmlns='http:\\/\\/www.portalfiscal.inf.br\\/nfe\\/wsdl\\/NFeRetAutorizacao4'><retConsReciNFe versao='4.00' xmlns='http:\\/\\/www.portalfiscal.inf.br\\/nfe'><tpAmb>2<\\/tpAmb><verAplic>PR-v4_4_1<\\/verAplic><nRec>411000003687611<\\/nRec><cStat>104<\\/cStat><xMotivo>Lote processado<\\/xMotivo><cUF>41<\\/cUF><dhRecbto>2020-04-04T18:04:52-03:00<\\/dhRecbto><protNFe versao='4.00'><infProt><tpAmb>2<\\/tpAmb><verAplic>PR-v4_4_1<\\/verAplic><chNFe>41200404190715000105650010000090721575261206<\\/chNFe><dhRecbto>2020-04-03T11:01:00-03:00<\\/dhRecbto><nProt>141200000197690<\\/nProt><digVal>MHkape5HJMhsujIxZkqiRBo\\/mPc=<\\/digVal><cStat>100<\\/cStat><xMotivo>Autorizado o uso da NF-e<\\/xMotivo><\\/infProt><\\/protNFe><\\/retConsReciNFe><\\/nfeResultMsg><\\/env:Body><\\/env:Envelope>\",\"protocolo\":\"141200000197690\",\"items\":[{\"id\":5000,\"grupo_id\":\"2\",\"empresa_id\":\"2\",\"nfemitida_id\":\"2915\",\"nfimposto_id\":\"61\",\"nfoperacao_id\":\"48\",\"setor_id\":\"103\",\"cprod\":\"50\",\"xprod\":\"Glp em Botij\\u00e3o de 13 Kg\",\"ncm\":\"27111910\",\"cfop\":\"5405\",\"ucom\":\"Und\",\"qcom\":\"1\",\"vuncom\":\"85\",\"vprod\":\"85\",\"utrib\":\"Kg\",\"qtrib\":\"13\",\"indtot\":\"1\",\"tagicms\":\"ICMS60\",\"orig\":\"0\",\"cst\":\"60\",\"tagpis\":\"PISNT\",\"cstpis\":\"4\",\"ppis\":\"0\",\"vpis\":\"0\",\"tagcofins\":\"COFINSNT\",\"pcofins\":\"0\",\"vcofins\":\"0\",\"qestoque\":\"1\",\"codigolote\":\" \",\"created_at\":\"2020-04-03 11:01:01\",\"updated_at\":\"2020-04-03 11:01:01\",\"qvol\":\"1\",\"pesol\":\"13\",\"pesob\":\"26\",\"customedio\":\"51.48\",\"cean\":\"SEM GTIN\",\"ceantrib\":\"SEM GTIN\",\"vbcstret\":null,\"vicmsstret\":null,\"vdesc\":\"10\",\"vfrete\":null,\"cprodanp\":\"210203001\",\"qbcprod\":\"0\",\"modbc\":null,\"picmsst\":null,\"vbc\":null,\"picms\":null,\"vicms\":null,\"tagipi\":\"IPINT\",\"cstipi\":null,\"vbcipi\":null,\"pipi\":null,\"vipi\":null,\"vbcpis\":\"0\",\"valiqprod\":\"0\",\"vcide\":\"0\",\"cest\":\"0601100\",\"nitemped\":\"1\",\"modbcst\":null,\"pmvast\":null,\"predbcst\":null,\"vbcst\":null,\"vicmsst\":null,\"predbc\":null,\"vicmsdeson\":null,\"motdesicms\":null,\"vicmsdif\":null,\"pdif\":null,\"vicmsop\":null,\"pbcop\":null,\"vbcstdest\":null,\"pcredsn\":null,\"vcredicmssn\":null,\"pfcp\":null,\"vfcp\":null,\"vbcfcp\":null,\"vbcfcpst\":null,\"pfcpst\":null,\"vfcpst\":null,\"pst\":null,\"vbcfcpstret\":null,\"pfcpstret\":null,\"vfcpstret\":null,\"cstcofins\":\"4\",\"vbccofins\":\"0\",\"vseg\":null,\"voutro\":null,\"movimentaestoque\":\"0\",\"vpart\":\"6.54\",\"pgnn\":\"15.5\",\"pgni\":\"12.2\",\"pglp\":\"72.3\",\"vuntrib\":\"6.5384615385\"}],\"condicao_pagamento\":\"Boleto\",\"parcelas\":[{\"id\":62728,\"grupo_id\":\"2\",\"empresa_id\":\"2\",\"financeiro_id\":\"49584\",\"numero\":\"1\",\"datavencimento\":\"2020-05-03 00:00:00\",\"datacompetencia\":\"2020-04-03 11:00:00\",\"valor\":\"85\",\"multa\":\"0\",\"juros\":\"0\",\"desconto\":\"10\",\"valorefetivado\":\"75\",\"pagarreceber\":\"R\",\"baixado\":\"0\",\"created_at\":\"2020-04-03 11:01:00\",\"updated_at\":\"2020-04-03 11:01:40\",\"agrupamento_status\":\"0\",\"agrupador_financeiro_id\":null,\"datahorabaixa\":null,\"motivocancelamento\":null,\"boletogerado\":\"1\",\"financeirotaxa_id\":null}],\"datahora_autorizacao\":\"2020-04-03 11:01:00\",\"vTotTrib\":\"26.73\",\"infCpl\":\"NF: 9072 - Valor Aprox. Tributos R$ 26.73 - 11.43 Federal, 15.30 Estadual e 0.00 Municipal. Fonte: IBPT D529CB.\",\"qrCode\":\"http:\\/\\/www.fazenda.pr.gov.br\\/nfce\\/qrcode?p=41200404190715000105650010000090721575261206|2|2|2|50DDD5D5979B792375EF99DBEFC5A0630D247EDF\"}],\"msg\":\"Sucesso!\",\"status\":\"OK\"}";
                String msg = "{\"data\":[{\"id\":46784,\"formapagamento\":\"Boleto\",\"datahoraemissao\":\"2020-04-03 10:55:00\",\"datahoraentradasaida\":\"2020-04-03 10:55:00\",\"emitcnpj\":\"04.190.715\\/0001-05\",\"emitrazaosocial\":\"Distribuidora Dubena Ltda\",\"emitnomefantasia\":\"Distribuidora Dubena\",\"emitendereco\":\"Rodovia PR-466, 1277 - Primavera\",\"emitnumero\":\"1277\",\"emitcomplemento\":\"Sala 04\",\"emitbairro\":\"Primavera\",\"emitcidade_id\":\"4109401\",\"emitcidadenome\":\"Guarapuava\",\"emituf\":\"PR\",\"emitcep\":\"85050-290\",\"emitie\":\"9022487490\",\"emitinscricaomunicipal\":null,\"destcpf\":\"459.261.821-15\",\"destrazaosocial\":\"Flavio Hideki Ono\",\"destendereco\":\"Rua Luiz Carollo, 264 - Vila Bela\",\"destnumero\":\"264\",\"destcomplemento\":\"Casa\",\"destbairro\":\"Vila Bela\",\"destcidade_id\":\"4109401\",\"destcidadenome\":\"Guarapuava\",\"destuf\":\"PR\",\"destcep\":\"85025-040\",\"destemail\":\"flavio.ono@gmail.com\",\"cliente_id\":\"57273\",\"condicaopagamento_id\":\"297\",\"vnf\":\"85\",\"vdesc\":\"0\",\"entregapontoreferencia\":\"prox. col\\u00e9gio Plat\\u00e3o\",\"financeiro_id\":\"49582\",\"nfce_id\":\"2913\",\"destie\":null,\"emittelefone\":\"(42) 36293-586\",\"desttelefone\":\"(42) 99912-6564\",\"financeiroparcela_id\":null,\"boleto_id\":null,\"condicao_pagamento\":\"Boleto\",\"items\":[{\"cprod\":\"50\",\"xprod\":\"Glp P13\",\"ucom\":\"Und\",\"qcom\":\"1\",\"vuncom\":\"85\",\"vprod\":\"85\"}],\"parcelas\":[{\"id\":62726,\"grupo_id\":\"2\",\"empresa_id\":\"2\",\"financeiro_id\":\"49582\",\"numero\":\"1\",\"datavencimento\":\"2020-05-03 00:00:00\",\"datacompetencia\":\"2020-04-03 10:55:00\",\"valor\":\"85\",\"multa\":\"0\",\"juros\":\"0\",\"desconto\":\"0\",\"valorefetivado\":\"85\",\"pagarreceber\":\"R\",\"baixado\":\"0\",\"created_at\":\"2020-04-03 10:55:19\",\"updated_at\":\"2020-04-03 10:57:17\",\"agrupamento_status\":\"0\",\"agrupador_financeiro_id\":null,\"datahorabaixa\":null,\"motivocancelamento\":null,\"boletogerado\":\"1\",\"financeirotaxa_id\":null}],\"vprod\":85}],\"msg\":\"Sucesso!\",\"status\":\"OK\"}";
                JSONObject json = new JSONObject(msg);
                JSONArray notafiscalJSON = json.getJSONArray("data");
                for (int i = 0; i < notafiscalJSON.length(); i++) {
                    JSONObject c = notafiscalJSON.getJSONObject(i);
                    NotaFiscal nf = new NotaFiscal();
                    nf.setCodigoSeq(c.getInt("id"));
                    nf.setDataEmissao(c.getString("datahoraemissao"));
                    nf.setDataSaida(c.getString("datahoraentradasaida"));
                    nf.setDestCEP(c.getString("destcep"));
                    nf.setDestCidade(c.getString("destcidadenome"));
                    nf.setDestCNPJ(c.getString("destcpf"));
                    nf.setDestEndereco(c.getString("destendereco"));
                    if (!c.getString("destcomplemento").equals("")) {
                        nf.setDestEndereco(nf.getDestEndereco() + " - "
                                + (c.getString("destcomplemento") == "null" ? "" : c.getString("destcomplemento")));
                    }
                    if (!c.getString("destbairro").equals("")) {
                        nf.setDestEndereco(nf.getDestEndereco() + " - " + c.getString("destbairro"));
                    }
                    nf.setDestIE(c.getString("destie") == "null" ? "" : c.getString("destie"));
                    nf.setDestRazaoSocial(c.getString("destrazaosocial"));
                    nf.setDestTelefone(c.getString("desttelefone") == "null" ? "" : c.getString("desttelefone"));
                    nf.setDestUF(c.getString("destuf"));
                    nf.setEmitCEP(c.getString("emitcep"));
                    nf.setEmitCidade(c.getString("emitcidadenome"));
                    nf.setEmitCNPJ(c.getString("emitcnpj"));
                    nf.setEmitEndereco(c.getString("emitendereco"));
                    if (!c.getString("emitcomplemento").equals("")) {
                        nf.setEmitEndereco(nf.getEmitEndereco() + " - "
                                + (c.getString("emitcomplemento") == "null" ? "" : c.getString("emitcomplemento")));
                    }
                    nf.setEmitIE(c.getString("emitie"));
                    nf.setEmitRazaoSocial(c.getString("emitrazaosocial"));
                    nf.setEmitTelefone(c.getString("emittelefone"));
                    nf.setEmitUF(c.getString("emituf"));
                    nf.setNumNf(c.getInt("financeiro_id"));
                    nf.setOperacao("");
                    nf.setSerie("");
                    nf.setTipo("");
                    nf.setValorProdutos(c.getDouble("vprod"));
                    nf.setvTotalNF(c.getDouble("vnf"));
                    nf.setvDesconto(c.getDouble("vdesc"));
                    nf.setCondicaoPagamento(c.getString("condicao_pagamento"));
                    JSONArray itensJSON = c.getJSONArray("items");
                    nf.itens = new LinkedList<NotafiscalItem>();
                    for (int j = 0; j < itensJSON.length(); j++) {
                        JSONObject itemJ = itensJSON.getJSONObject(j);
                        NotafiscalItem item = new NotafiscalItem(nf.getCodigoSeq(), 0, itemJ.getInt("cprod"), itemJ.getDouble("qcom"), itemJ.getString("xprod"), itemJ.getString("ucom"), itemJ.getDouble("vuncom"), 0, itemJ.getDouble("vprod"));
                        nf.itens.add(item);
                    }
                    JSONArray parcelasJSON = new JSONArray(c.getString("parcelas"));

                    nf.parcelas = new LinkedList<NotafiscalParcela>();
                    for (int j = 0; j < parcelasJSON.length(); j++) {
                        JSONObject itemJ = parcelasJSON.getJSONObject(j);
                        NotafiscalParcela item = new NotafiscalParcela(nf.getNumNf(), itemJ.getInt("numero"),
                                itemJ.getString("datavencimento"), itemJ.getDouble("valor"));
                        nf.parcelas.add(item);
                    }

                    nf.setvBCICMS(0);
                    nf.setvICMS(0);
                    nf.setvBCICMSST(0);
                    nf.setvICMSST(0);
                    nf.setvFrete(0);
                    nf.setvSeguro(0);
                    nf.setvOutro(0);
                    nf.setvIpi(0);
                    nf.setNfmodelo("");
                    nf.setvTotTrib(0);
                    nf.setInfCpl("");
                    nf.setQrCode("");
                    nf.setDataHoraAutorizacao("");
                    nf.setProtocolo("");
                    nf.setInformacoesAdicionais("");


                    NotaFiscalImpressao nfI = new NotaFiscalImpressao(nf, NotaFiscalImpressaoActivity.this);
                    this.retBitmapTask = nfI.gerarDuplicataBmp();
                    if(this.retBitmapTask == null){
                        return "8Erro ao gerar a imagem da NF";
                    } else {
                        return "2";
                    }
                    /*
                    if(!c.get("nfsituacao_id").equals("100")){
                        return "8 não autorizada";
                    }
                    else {
                        nf.setChaveAcesso(c.getString("chaveacesso"));
                        nf.setCodigoSeq(c.getInt("id"));
                        nf.setDataEmissao(c.getString("datahoraemissao"));
                        nf.setDataSaida(c.getString("datahoraentradasaida"));
                        nf.setDestCEP(c.getString("destcep"));
                        nf.setDestCidade(c.getString("destcidadenome"));
                        nf.setDestCNPJ(c.getString("destcpf"));
                        nf.setDestEndereco(c.getString("destendereco"));
                        if (!c.getString("destcomplemento").equals("")) {
                            nf.setDestEndereco(nf.getDestEndereco() + " - " + (c.getString("destcomplemento")=="null"?"":c.getString("destcomplemento")));
                        }
                        if (!c.getString("destbairro").equals("")) {
                            nf.setDestEndereco(nf.getDestEndereco() + " - " + c.getString("destbairro"));
                        }
                        nf.setDestIE(c.getString("destie")=="null"?"":c.getString("destie"));
                        nf.setDestRazaoSocial(c.getString("destrazaosocial"));
                        nf.setDestTelefone(c.getString("desttelefone")=="null"?"":c.getString("desttelefone"));
                        nf.setDestUF(c.getString("destuf"));
                        nf.setEmitCEP(c.getString("emitcep"));
                        nf.setEmitCidade(c.getString("emitcidadenome"));
                        nf.setEmitCNPJ(c.getString("emitcnpj"));
                        nf.setEmitEndereco(c.getString("emitendereco"));
                        if (!c.getString("emitcomplemento").equals("")) {
                            nf.setEmitEndereco(nf.getEmitEndereco() + " - " + (c.getString("emitcomplemento")=="null"?"":c.getString("emitcomplemento")));
                        }
                        nf.setEmitIE(c.getString("emitie"));
                        nf.setEmitRazaoSocial(c.getString("emitrazaosocial"));
                        nf.setEmitTelefone(c.getString("emittelefone"));
                        nf.setEmitUF(c.getString("emituf"));
                        nf.setNumNf(c.getInt("nfnumero"));
                        nf.setOperacao(c.getString("descricaooperacao"));
                        nf.setSerie(c.getString("nfserie"));
                        nf.setTipo(c.getString("tipo"));
                        nf.setValorProdutos(c.getDouble("vprod"));
                        nf.setvBCICMS(c.getDouble("vbc"));
                        nf.setvICMS(c.getDouble("vicms"));
                        nf.setvBCICMSST(c.getDouble("vbcst"));
                        nf.setvICMSST(c.getDouble("vst"));
                        nf.setvTotalNF(c.getDouble("vnf"));
                        nf.setvFrete(c.getDouble("vfrete"));
                        nf.setvSeguro(c.getDouble("vseg"));
                        nf.setvDesconto(c.getDouble("vdesc"));
                        nf.setvOutro(c.getDouble("voutro"));
                        nf.setvIpi(c.getDouble("vipi"));
                        nf.setInformacoesAdicionais((c.getString("informacaocomplementar")=="null"?"":c.getString("informacaocomplementar")) + "|" + (c.getString("informacaoadicionalfisco")=="null"?"":c.getString("informacaoadicionalfisco")));
                        nf.setNfmodelo(c.getString("nfmodelo"));
                        nf.setCondicaoPagamento(c.getString("condicao_pagamento"));
                        nf.setvTotTrib(c.getDouble("vTotTrib"));
                        nf.setInfCpl(c.getString("infCpl"));
                        nf.setQrCode(c.getString("qrCode"));
                        nf.setDataHoraAutorizacao(c.getString("datahora_autorizacao"));
                        nf.setProtocolo(c.getString("protocolo"));
                        itensJSON = c.getJSONArray("items");
                        nf.itens = new LinkedList<NotafiscalItem>();
                        for (int j = 0; j < itensJSON.length(); j++) {
                            JSONObject itemJ = itensJSON.getJSONObject(j);
                            NotafiscalItem item = new NotafiscalItem(nf.getCodigoSeq(), nf.getNumNf(), itemJ.getInt("cprod"), itemJ.getDouble("qcom"), itemJ.getString("xprod"), itemJ.getString("ucom"), itemJ.getDouble("vuncom"), 0, itemJ.getDouble("vprod"));
                            item.setCst(itemJ.getString("cst"));
                            if(itemJ.getString("picms")!= "null" ) {
                                item.setAliq(itemJ.getInt("picms"));
                            }
                            if(itemJ.getString("vdesc")!= "null" ) {
                                item.setValorDesconto(itemJ.getDouble("vdesc"));
                            }
                            nf.itens.add(item);
                        }
                        parcelasJSON = c.getJSONArray("parcelas");
                        nf.parcelas = new LinkedList<NotafiscalParcela>();
                        for (int j = 0; j < parcelasJSON.length(); j++) {
                            JSONObject itemJ = parcelasJSON.getJSONObject(j);
                            NotafiscalParcela item = new NotafiscalParcela(nf.getNumNf(), itemJ.getInt("numero"), itemJ.getString("datavencimento"), itemJ.getDouble("valor"));
                            nf.parcelas.add(item);
                        }
                        NotaFiscalImpressao nfI = new NotaFiscalImpressao(nf, NotaFiscalImpressaoActivity.this);
                        if(nf.getNfmodelo()=="55"){
                            this.retBitmapTask = nfI.gerarNotaFiscalBmp();
                        } else {
                            this.retBitmapTask = nfI.gerarNotaFiscalCBmp();
                        }
                        if(this.retBitmapTask == null){
                            return "8Erro ao gerar a imagem da NF";
                        } else {
                            return "2";
                        }
                    }
                    */
                }
                //NotaFiscalImpressao nfI = new NotaFiscalImpressao(dados, NotaFiscalImpressaoActivity.this);
                //this.retBitmapTask = nfI.gerarNotaFiscalBmp(PedidoFragment3.tipoNF, listNF);
                return "0";
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