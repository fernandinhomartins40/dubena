package br.inf.qti.nfweb;

import android.widget.Toast;

import com.facebook.react.bridge.NativeModule;
import com.facebook.react.bridge.ReactApplicationContext;
import com.facebook.react.bridge.ReactContext;
import com.facebook.react.bridge.ReactContextBaseJavaModule;
import com.facebook.react.bridge.ReactMethod;
import com.facebook.react.bridge.ReadableArray;
import com.facebook.react.bridge.ReadableMap;
import com.facebook.react.bridge.ReadableType;

import java.io.*;
import java.util.Map;
import java.util.HashMap;
import java.util.Set;
import java.util.Formatter;
import java.util.LinkedList;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import br.inf.qti.nfweb.libs.BarCode;
import br.inf.qti.nfweb.libs.BarI25;
import br.inf.qti.nfweb.libs.Bluetooth;
import br.inf.qti.nfweb.libs.ESCP;

import android.os.Bundle;
import android.app.Activity;
import android.app.AlertDialog;
import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothDevice;
import android.content.Context;
import android.content.DialogInterface;
import android.graphics.Bitmap;
import android.graphics.Bitmap.Config;
import android.graphics.BitmapFactory;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.DashPathEffect;
import android.graphics.Paint;
import android.graphics.Paint.Style;
import android.graphics.Rect;
import android.graphics.Typeface;
import android.view.Menu;
import android.view.MenuItem;
import android.view.MotionEvent;
import android.view.View;
import android.widget.ScrollView;

import com.lvrenyang.io.BTPrinting;
import com.lvrenyang.io.IOCallBack;
import com.lvrenyang.io.Pos;

public class PrintBoletoModule extends ReactContextBaseJavaModule {
	private static ReactApplicationContext reactContext;

	private static final String DURATION_SHORT_KEY = "SHORT";
	private static final String DURATION_LONG_KEY = "LONG";
	private Boleto boleto;
	private static String nomeImpressora = "LEOPARDO PRO MAX-";

	Bluetooth mBth = new Bluetooth();
	DrawView mDrawing;
	Bitmap mBitmap = null;
	static Bitmap mBitmapLogo = null;
	static Bitmap mBitmapBanco = null;
	Integer mDensity = 8;
	private static BTPrinting bt = null;
    private static Pos pos = new Pos();

	PrintBoletoModule(ReactApplicationContext context) {
		super(context);
		reactContext = context;
	}

	@Override
	public String getName() {
		return "PrintBoleto";
	}

	@Override
	public Map<String, Object> getConstants() {
		final Map<String, Object> constants = new HashMap<>();
		constants.put(DURATION_SHORT_KEY, Toast.LENGTH_SHORT);
		constants.put(DURATION_LONG_KEY, Toast.LENGTH_LONG);
		return constants;
	}

	@ReactMethod
	public void printBoleto(final ReadableMap bol, int duration) {
		try {
			//mBitmapLogo = BitmapFactory.decodeStream(getReactApplicationContext().getAssets().open("rtsys.png"));
			this.boleto = this.gerarBoleto(Utils.convertMapToJson(bol));
			if (this.boleto == null) {
				return;
			}
			mBitmapBanco = BitmapFactory.decodeStream(getReactApplicationContext().getAssets().open(String.valueOf(this.boleto.getBanco()) + ".png"));
			mBitmap = createBoleto();
			//if (checkBth()) {
				if (mBitmap != null) {
					Toast.makeText(getReactApplicationContext(), "Imprimindo...", Toast.LENGTH_LONG).show();
					//ESCP.ImageToEsc(mBitmap, mBth.Ostream, 8, mDensity);
					bt = new BTPrinting();
                    bt.PauseHeartBeat();
                    pos.Set(bt);
                    String BTAddress = getBthAddress();
                    if (BTAddress == null)
                        Toast.makeText(getReactApplicationContext(), "Não achou endereço bluetooth", Toast.LENGTH_LONG).show();
                    boolean result = bt.Open(BTAddress);
                    if (!result)
						Toast.makeText(getReactApplicationContext(), "Não conseguiu abrir bluetooth", Toast.LENGTH_LONG).show();
                    bt.ResumeHeartBeat();

                    int nWidth = 576; //384 / 832
                    int nMode = 0;

                    byte recbuf[] = new byte[100];
                    boolean res = pos.POS_QueryStatus(recbuf, 1000);
                    if (res) {
                        pos.POS_PrintBWPic(mBitmap, nWidth, nMode);
                        res = pos.POS_QueryOnline(1000);
                        if (!res) {
                            bt.Close();
							Toast.makeText(getReactApplicationContext(), "Erro 000 ao imprimir", Toast.LENGTH_LONG).show();
                        }
                    } else {
                        bt.Close();
						Toast.makeText(getReactApplicationContext(), "Erro 001 ao imprimir", Toast.LENGTH_LONG).show();
                    }
                    bt.Close();
				}
			//	closeBth();
			//}
		} catch (IOException e) {
			Toast.makeText(getReactApplicationContext(), "Erro " + e.getMessage() + " " + String.valueOf(e.getStackTrace()[0].getLineNumber()), duration).show();
		} catch (Exception e) {
			Toast.makeText(getReactApplicationContext(), "Erro2 " + e.getMessage() + " " + String.valueOf(e.getStackTrace()[0].getLineNumber()), duration).show();
		}
	}

	private Boleto gerarBoleto(JSONObject c) {
		try {
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
			bol.setPagadorNome(pag.getString("nome_documento"));
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
			bol.setLinhaDigitavel(c.getString("linhaDigitavel"));
			bol.setCodigoBarras(c.getString("codigoBarras"));
			bol.setBanco(c.getString("banco"));
			bol.setBancoDv(c.getString("bancoDv"));

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
			return bol;
		} catch (Exception e) {
			e.printStackTrace();
			Toast.makeText(getReactApplicationContext(), e.getMessage(), Toast.LENGTH_LONG).show();
			return null;
		}
	}

	public Bitmap createBoleto() {
        Boleto bol = this.boleto;
        int size_text=20, size_legend=22, size_text2=18;

        Paint fontTitleBold = new Paint(Color.BLACK);
        fontTitleBold.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.BOLD));
        fontTitleBold.setTextSize((int)(size_text*1.2));

        Paint fontText = new Paint(Color.BLACK);
        fontText.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.NORMAL));
        fontText.setTextSize((int)(size_text));

        Paint fontText2 = new Paint(Color.BLACK);
        fontText2.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.NORMAL));
        fontText2.setTextSize((int)(size_text2));

        Paint fontTextBold = new Paint(Color.BLACK);
        fontTextBold.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.BOLD));
        fontTextBold.setTextSize((int)(size_text));

        Paint fontLegend = new Paint(Color.BLACK);
        fontLegend.setTypeface(Typeface.create(Typeface.MONOSPACE,Typeface.NORMAL));
        fontLegend.setTextSize(size_legend);

        Paint fontLegendBold = new Paint(Color.BLACK);
        fontLegendBold.setTypeface(Typeface.create(Typeface.MONOSPACE,Typeface.BOLD));
        fontLegendBold.setTextSize(size_legend);

        Paint p = new Paint(Color.RED);
        p.setStyle(Paint.Style.STROKE);
        p.setStrokeWidth(2);
        int x=0, y=0, w=576, h=w*5;
        int ay = y;
        int W=1700, H=576;
        Bitmap BitmapBoleto = Bitmap.createBitmap(H, W, Config.RGB_565);
        Canvas g2 = new Canvas(BitmapBoleto);
        g2.drawColor(Color.WHITE);

        g2.rotate(90,H/2,H/2);
        if (mBitmapBanco!=null)
        {
            g2.drawBitmap(mBitmapBanco, x, y, p);
        }

        g2.drawLine(0, 50, W-10, 50, p);
        //g2.drawRect(0, 50, W, H-100, p);

        String linha1, linha2;
        int tamL = 23, nlinha = 50, espaco = 4;
        //Linha1 - banco + linha digitável
        linha1 =  "  " + bol.getBanco() + "-" + bol.getBancoDv()  + "     " + bol.getLinhaDigitavel();
        g2.drawText(linha1, 250, 40, fontTextBold);
        g2.drawLine(250, 0, 250, nlinha, p);
        g2.drawLine(370, 0, 370, nlinha, p);
        //Linha2/3 - local de pagamento
        linha1 = getLinha("Local de Pagamento    " + bol.getLocalPagamento().get(0), 100, "L") + repeat(" ", 7) + repeat(" ", 5) + getLinha("Vencimento", 20, "L");
        linha2 = getLinha("                      " + (bol.getLocalPagamento().size()>1?bol.getLocalPagamento().get(1):""), 100, "L") + repeat(" ", 7) + repeat(" ", 5) + getLinha(bol.getVencimento(), 34 , "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        nlinha += tamL;
        g2.drawText(linha2, 20, nlinha, fontText2);
        g2.drawLine(0, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linha que separa todas na vertical
        g2.drawLine(1200, 50+espaco, 1200, 50+(tamL*13)+espaco, p);
        //Linha 4/5 cedente
        linha1 = getLinha("Beneficiário", 100, "L") + repeat(" ", 7) + repeat(" ", 5) + "Agência/Código Beneficiário";
        linha2 = getLinha(bol.getBenefNome() + " / CNPJ: " + bol.getBenefDocumento(), 100, "L") + repeat(" ", 7) + repeat(" ", 5) + getLinha(bol.getAgencia() + "/" + bol.getCedente(), 34, "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        nlinha += tamL;
        g2.drawText(linha2, 20, nlinha, fontText2);
        g2.drawLine(x, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linha 6/7
        linha1 = getLinha("Data do Documento", 27, "C") + getLinha("Nº do Documento", 20, "C") + getLinha("Espécie Doc", 16, "C") + getLinha("Aceite", 10, "C") + getLinha("Data de Processamento", 27, "C") + repeat(" ", 7) + repeat(" ", 5) + "Carteira/Nosso Número";
        linha2 = getLinha(bol.getDataDocumento(), 27, "C") + getLinha(bol.getDocumento(), 20, "C") + getLinha(bol.getEspecie(), 16, "C") + getLinha(bol.getAceite(), 10, "C") + getLinha(bol.getDataDocumento(), 27, "C") + repeat(" ", 7) + repeat(" ", 5) + getLinha(bol.getNossoNumero() + "-" + bol.getDvNossoNumero(), 34, "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        nlinha += tamL;
        g2.drawText(linha2, 20, nlinha, fontText2);
        g2.drawLine(0, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linhas de separação vertical
        g2.drawLine(315, nlinha-(tamL*2)+espaco, 315, nlinha+espaco, p);
        g2.drawLine(540, nlinha-(tamL*2)+espaco, 540, nlinha+espaco, p);
        g2.drawLine(700, nlinha-(tamL*2)+espaco, 700, nlinha+espaco, p);
        g2.drawLine(820, nlinha-(tamL*2)+espaco, 820, nlinha+espaco, p);
        //Linha 8/9
        Formatter f1 = new Formatter();
        String valor = String.valueOf(f1.format("%1.2f", Utils.round(bol.getValor(), 2))).replace(".", ",");
        linha1 = getLinha("Uso do Banco", 27, "C") +      getLinha("Carteira", 15, "C") + getLinha("Espécie", 14, "C") + getLinha("Quantidade", 16, "C") + getLinha("(x) Valor", 28, "C") + repeat(" ", 7) + repeat(" ", 5) + "(=) Valor Documento";
        linha2 = getLinha("", 27, "C") + getLinha(bol.getCarteira(), 15, "C") + getLinha("R$", 14, "C") + getLinha("", 16, "C") + getLinha("", 28, "R") + repeat(" ", 7) + repeat(" ", 5) + getLinha(valor, 34,"R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        nlinha += tamL;
        g2.drawText(linha2, 20, nlinha, fontText2);
        g2.drawLine(x, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linhas de separação vertical
        g2.drawLine(315, nlinha-(tamL*2)+espaco, 315, nlinha+espaco, p);
        g2.drawLine(490, nlinha-(tamL*2)+espaco, 490, nlinha+espaco, p);
        g2.drawLine(630, nlinha-(tamL*2)+espaco, 630, nlinha+espaco, p);
        g2.drawLine(820, nlinha-(tamL*2)+espaco, 820, nlinha+espaco, p);
        //Linha 1 das instruções
        linha1 = getLinha("Instruções de Responsabilidade do beneficiário. Qualquer dúvida sobre esse boleto, contacte o beneficiário", 107, "L") + repeat(" ", 5) + getLinha("(-) Desconto/Abatimentos", 24, "L") + getLinha("", 10, "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        g2.drawLine(1200, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linha 2 das instruções
        linha1 = getLinha("", 107, "L") + repeat(" ", 5) + getLinha("(-) Outras Deduções", 24, "L") + getLinha("", 10, "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        g2.drawLine(1200, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linha 3 das instruções
        String inst = "";
        if(bol.getInstrucoes().size() > 0){
            inst = bol.getInstrucoes().get(0);
        }
        linha1 = getLinha(inst, 107, "L") + repeat(" ", 5) + getLinha("(+) Mora/Multa", 24, "L") + getLinha("", 10, "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        g2.drawLine(1200, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linha 4 das instruções
        inst = "";
        if(bol.getInstrucoes().size() > 1){
            inst = bol.getInstrucoes().get(1);
        }
        linha1 = getLinha(inst, 107, "L") + repeat(" ", 5) + getLinha("(+) Outros Acréscimos", 24, "L") + getLinha("", 10, "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        g2.drawLine(1200, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linha 4 das instruções
        inst = "";
        if(bol.getInstrucoes().size() > 2){
            inst = bol.getInstrucoes().get(2);
        }
        linha1 = getLinha(inst, 107, "L") + repeat(" ", 5) + getLinha("(=) Valor Cobrado", 24, "L") + getLinha("", 10, "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        g2.drawLine(20, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linhas do Pagador (sacado)
        linha1 = bol.getPagadorNome();
        nlinha += tamL;
        g2.drawText("Pagador", 20, nlinha, fontText2);
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        nlinha += tamL;
        g2.drawText(bol.getPagadorEndereco() + " - CEP " + bol.getPagadorCep(), 20, nlinha, fontText2);
		nlinha += tamL;
		linha1 = getLinha(bol.getPagadorBairro() + " - " + bol.getPagadorCidade() + "/" + bol.getPagadorUf(), 107, "L");
		linha1 += repeat(" ", 5) + "Cód. Baixa";
		g2.drawText(linha1, 20, nlinha, fontText2);
		g2.drawLine(x, nlinha+espaco, W-10, nlinha+espaco, p);
		g2.drawLine(1200, nlinha-(tamL)+espaco, 1200, nlinha+espaco, p);		

		linha1 = getLinha("Sacador/Avalista", 99, "L") + repeat(" ", 5) + getLinha("Autenticação Mecânica-Ficha de Compensação", 42, "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        BarI25 b25 = new BarI25();
        Bitmap i25 = b25.createI25(bol.getCodigoBarras());
        g2.drawBitmap(i25, 20, H-97, p);

        x=0; y=ay;

        x=0; y+=2000;

        h = (int)(y / 64);
        h = ((h+10) * 64);

        Bitmap BitmapReturn = Bitmap.createBitmap(BitmapBoleto.getWidth(), W, Config.RGB_565);
        Canvas g3 = new Canvas(BitmapReturn);

        g3.drawBitmap(BitmapBoleto, 0, 0, p);

        return BitmapReturn;

	}

	public String getLinha(String linha, int tamanho, String align){
        String retorno = linha;
        if(linha.length() > tamanho){
            retorno = linha.substring(0,tamanho);
        } else {
            String espacos = repeat(" ", tamanho - linha.length());
            if(align == "R"){
                retorno = espacos + linha;
            } else if(align == "L"){
                retorno = linha + espacos;
            } else {
                int metade = Math.round(espacos.length()/2);
                retorno = repeat(" ", metade) + linha + repeat(" ", espacos.length() - metade);
            }
        }
        return retorno;
    }

    public String repeat(String val, int count){
        StringBuilder buf = new StringBuilder(val.length() * count);
        while (count-- > 0) {
            buf.append(val);
        }
        return buf.toString();
    }


	public boolean closeBth() {
		if (mBth.isConnected()) {
			return mBth.Close();
		}
		return false;
	}

	public boolean checkBth() {
		if (!mBth.isConnected()) {
			if (!mBth.Enable()) {
				Toast.makeText(getReactApplicationContext(),
						"Não foi possível habilitar Bluetooth, favor habilitar manualmente.", Toast.LENGTH_LONG).show();
				return false;
			}
			String mac = null;
			Set<BluetoothDevice> devices = mBth.GetBondedDevices();
			for (BluetoothDevice device : devices) {
				if ("MPT-III".equals(device.getName())) {
					mac = device.getAddress();
				}
			}
			if (mac == null) {
				Toast.makeText(getReactApplicationContext(),
						"Nao foi encontrada MPT-III\n\nFaça o pareamento com o disposivo e tente novamente.",
						Toast.LENGTH_LONG).show();
				return false;
			}
			if (!mBth.Open(mac)) {
				Toast.makeText(getReactApplicationContext(), "Nao foi possivel conectar ao dispositivo [" + mac
						+ "]\n\nLigue ou conecte o dispositivo e tente novamente.", Toast.LENGTH_LONG).show();
				return false;
			}
		}
		return true;
	}

	public String getBthAddress() {
		BluetoothAdapter mBluetoothAdapter = BluetoothAdapter
				.getDefaultAdapter();
		if (mBluetoothAdapter == null) {
			// Device does not support Bluetooth
			return null;
		}

		Set<BluetoothDevice> pairedDevices = mBluetoothAdapter
				.getBondedDevices();
		// If there are paired devices
		if (pairedDevices.size() > 0) {
			// Loop through paired devices
			for (BluetoothDevice device : pairedDevices) {
				if (device.getName().toUpperCase().equals(nomeImpressora.toUpperCase())) {
					return device.getAddress();
				}
			}
		}
		return null;
	}



	private class DrawView extends View {
		private boolean move = false;
		private int X = 0, Y = 0, iX = 0, iY = 0;

		public DrawView(Context context) {
			super(context);
			this.setBackgroundResource(Color.BLACK);
		}

		@Override

		public boolean onTouchEvent(final MotionEvent event) {
			boolean handled = false;
			int xTouch;
			int yTouch;
			int pointerId;
			int actionIndex = event.getActionIndex();

			switch (event.getActionMasked()) {
				case MotionEvent.ACTION_DOWN:
					xTouch = (int) event.getX(0);
					yTouch = (int) event.getY(0);

					iX = (xTouch - X);
					iY = (yTouch - Y);

					invalidate();
					handled = true;
					move = true;
					break;

				case MotionEvent.ACTION_POINTER_DOWN:
					pointerId = event.getPointerId(actionIndex);

					xTouch = (int) event.getX(actionIndex);
					yTouch = (int) event.getY(actionIndex);

					iX = (xTouch - X);
					iY = (yTouch - Y);

					invalidate();
					handled = true;
					move = true;
					break;

				case MotionEvent.ACTION_MOVE:
					final int pointerCount = event.getPointerCount();

					for (actionIndex = 0; actionIndex < pointerCount; actionIndex++) {
						pointerId = event.getPointerId(actionIndex);

						xTouch = (int) event.getX(actionIndex);
						yTouch = (int) event.getY(actionIndex);

						if (move) {
							X = (xTouch - iX);
							Y = (yTouch - iY);
						}
					}
					invalidate();
					handled = true;
					break;

				case MotionEvent.ACTION_UP:
					move = false;
					invalidate();
					handled = true;
					break;

				case MotionEvent.ACTION_POINTER_UP:
					move = false;
					pointerId = event.getPointerId(actionIndex);
					invalidate();
					handled = true;
					break;

				case MotionEvent.ACTION_CANCEL:
					move = false;
					handled = true;
					break;

				default:
					break;
			}

			return super.onTouchEvent(event) || handled;
		}

		protected void onDraw(Canvas canvas) {
			if (mBitmap != null) {
				Paint myPaint = new Paint();
				myPaint.setColor(Color.BLACK);

				boolean resize = false;
				if (!resize) {
					canvas.drawBitmap(mBitmap, X, Y, myPaint);
				} else {
					int ih = mBitmap.getHeight();
					int iw = mBitmap.getWidth();
					int mh = getHeight();
					float fat = (ih / mh);
					int mw = (int) ((iw * mh) / ih);
					canvas.drawBitmap(mBitmap, new Rect(0, 0, iw, ih), new Rect(0, 0, mw, mh), myPaint);
				}
			}

		}
	}

}
