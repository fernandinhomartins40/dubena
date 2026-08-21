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

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.List;
import java.io.*;
import java.util.Map;
import java.util.HashMap;
import java.util.Set;
import java.util.Arrays;
import java.text.DecimalFormat;
import java.util.Locale;
import java.util.Set;
import java.util.Formatter;
import java.util.LinkedList;

import br.inf.qti.nfweb.libs.BarCode;
import br.inf.qti.nfweb.libs.BarI25;
import br.inf.qti.nfweb.libs.Bluetooth;
import br.inf.qti.nfweb.libs.ESCP;

import com.google.zxing.BarcodeFormat;
import com.google.zxing.MultiFormatWriter;
import com.google.zxing.WriterException;
import com.google.zxing.common.BitMatrix;
import com.google.zxing.qrcode.QRCodeWriter;

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

//import inputservice.NfePrinter.ReceiptPrinterA7;
//A7 Light
import com.lvrenyang.io.BTPrinting;
import com.lvrenyang.io.IOCallBack;
import com.lvrenyang.io.Pos;

public class PrintDanfeModule extends ReactContextBaseJavaModule {
	private static ReactApplicationContext reactContext;
	private static final String DURATION_SHORT_KEY = "SHORT";
	private static final String DURATION_LONG_KEY = "LONG";
	private NotaFiscal NF;
	private static String nomeImpressora = "LEOPARDO PRO MAX-";

	Bluetooth mBth = new Bluetooth();
	DrawView mDrawing;
	Bitmap mBitmap = null;
	static Bitmap mBitmapLogo = null;
	static Bitmap mBitmapBanco = null;
	Integer mDensity = 8;
	private static BTPrinting bt = null;
    private static Pos pos = new Pos();

	PrintDanfeModule(ReactApplicationContext context) {
		super(context);
		reactContext = context;
	}

	@Override
	public String getName() {
		return "PrintDanfe";
	}

	@Override
	public Map<String, Object> getConstants() {
		final Map<String, Object> constants = new HashMap<>();
		constants.put(DURATION_SHORT_KEY, Toast.LENGTH_SHORT);
		constants.put(DURATION_LONG_KEY, Toast.LENGTH_LONG);
		return constants;
	}

	@ReactMethod
	public void printDanfe(final ReadableMap nfe, int duration) {
		// Toast.makeText(getReactApplicationContext(), message, duration).show();
		try {
			mBitmapLogo = BitmapFactory.decodeStream(getReactApplicationContext().getAssets().open("rtsys.png"));
			mBitmapBanco = BitmapFactory.decodeStream(getReactApplicationContext().getAssets().open("santander.png"));
			this.NF = this.gerarNotafiscal(Utils.convertMapToJson(nfe));
			if(this.NF == null){
				return;
			}
			Toast.makeText(getReactApplicationContext(), this.NF.getNfmodelo(), Toast.LENGTH_LONG).show();
			if(this.NF.getNfmodelo().equals("55")){
				mBitmap = createDanfe();
			} else {
				mBitmap = createDanfeC();
			}
			//mBitmap = createDanfe();
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
				//closeBth();
			//}
		} catch (IOException e) {
			Toast.makeText(getReactApplicationContext(), e.getMessage(), duration).show();
		} catch (Exception e) {
			Toast.makeText(getReactApplicationContext(), e.getMessage(), duration).show();
		}
	}

	private NotaFiscal gerarNotafiscal(JSONObject c) {
		try {
			NotaFiscal nf = new NotaFiscal();
			if (c.getInt("nfsituacao_id") != 100) {
				throw new Exception("NF não autorizada: " + c.getString("nfsituacao_id"));
			}
			if (c.getInt("nfmodelo") != 55 && c.getInt("nfmodelo") != 65) {
				throw new Exception("modelo de NF não suportado");
			}
			nf.setChaveAcesso(c.getString("chaveacesso"));
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
			nf.setNfmodelo(c.getString("nfmodelo"));
			nf.setCondicaoPagamento(c.getString("condicao_pagamento"));
			nf.setvTotTrib(c.getDouble("vTotTrib"));
			nf.setInfCpl(c.getString("infCpl"));
			nf.setQrCode(c.getString("qrCode"));
			nf.setDataHoraAutorizacao(c.getString("datahora_autorizacao"));
			nf.setProtocolo(c.getString("protocolo"));
			nf.setInformacoesAdicionais(
					(c.getString("informacaocomplementar") == "null" ? "" : c.getString("informacaocomplementar")) + "|"
							+ (c.getString("informacaoadicionalfisco") == "null" ? ""
									: c.getString("informacaoadicionalfisco")));
			
			JSONArray itensJSON = c.getJSONArray("items");
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
			JSONArray parcelasJSON = new JSONArray(c.getString("parcelas"));

			nf.parcelas = new LinkedList<NotafiscalParcela>();
			for (int j = 0; j < parcelasJSON.length(); j++) {
				JSONObject itemJ = parcelasJSON.getJSONObject(j);
				NotafiscalParcela item = new NotafiscalParcela(nf.getNumNf(), itemJ.getInt("numero"),
						itemJ.getString("datavencimento"), itemJ.getDouble("valor"));
				nf.parcelas.add(item);
			}
			return nf;
		} catch (Exception e) {
			e.printStackTrace();
			Toast.makeText(getReactApplicationContext(), e.getMessage(), Toast.LENGTH_LONG).show();
			return null;
		} 
	}

	public Bitmap createDanfe() {
		try {
			int x = 0, y = 0, w = 576, h = 0;
			int size_text = 32, size_legend = 16, size_chave = 22, row_width = 55, row_height = 20;

			// Tamanho do Bitmap
			// -----------------
			h = 0; // as chaves de acesso + a parte onde descreve Danfe Simplificado
			NotaFiscal nf = this.NF;
			h += 460;
			h += 8 * row_height; // Impostos
			h += (2 + (nf.getParcelas().size())) * row_height; // dados financeiros
			h += (8 + (nf.getDestRazaoSocial().length() > row_width ? 1 : 0)) * row_height; // dados do comprovante de
																							// recebimento
			h += (3 + (nf.getOperacao().length() > (row_width - 1 - 26) ? 1 : 0)) * row_height; // Natureza Operação
			h += (6 + (nf.getEmitRazaoSocial().length() > (row_width) ? 1 : 0)) * row_height; // Emitente
			h += ((nf.getEmitEndereco().length() > (row_width) ? 1 : 0)) * row_height; // Endereco
			h += ((12 + Utils.formatCEP(nf.getEmitCEP()).length() + nf.getEmitCidade().length()
					+ nf.getEmitUF().length() + Utils.formatFone(nf.getEmitTelefone()).length() > (row_width) ? 1 : 0))
					* row_height; // Outros dados
									// emitente
			h += (6 + (nf.getDestRazaoSocial().length() > (row_width) ? 1 : 0)) * row_height; // Destinatário
			h += ((nf.getDestEndereco().length() > (row_width) ? 1 : 0)) * row_height; // Endereco
			h += ((12 + Utils.formatCEP(nf.getDestCEP()).length() + nf.getDestCidade().length()
					+ nf.getDestUF().length() + Utils.formatFone(nf.getDestTelefone()).length() > (row_width) ? 1 : 0))
					* row_height; // Outros dados
									// emitente
			// produtos
			h += 2 * row_height;
			int witdthProd = 24;
			for (int i = 0; i < nf.getItens().size(); i++) {
				// y+=row_height;
				NotafiscalItem item = nf.getItens().get(i);
				int witdthField1_1 = witdthProd - 5;
				int quantprod = 1;
				if (item.getDescricao().length() > (witdthField1_1 - 1)) {
					quantprod = 2;
				}
				h += quantprod * row_height;
			}
			// dados adicionais
			h += 3 * row_height;
			witdthProd = 32;
			h += (nf.getInformacoesAdicionais().length() / witdthProd) * row_height;

			int h1 = h;
			Bitmap BitmapDanfe = Bitmap.createBitmap(w, h, Bitmap.Config.RGB_565);
			Canvas g = new Canvas(BitmapDanfe);

			Paint fontTitleBold = new Paint(Color.BLACK);
			fontTitleBold.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.BOLD));
			fontTitleBold.setTextSize((int) (size_text * 1.2));

			Paint fontText = new Paint(Color.BLACK);
			fontText.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.NORMAL));
			fontText.setTextSize((int) (size_text));

			Paint fontTextBold = new Paint(Color.BLACK);
			fontTextBold.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.BOLD));
			fontTextBold.setTextSize((int) (size_text));

			Paint fontLegend = new Paint(Color.BLACK);
			fontLegend.setTypeface(
					Typeface.createFromAsset(getReactApplicationContext().getAssets(), "fonts/unispace rg.ttf"));
			fontLegend.setTextSize(size_legend);

			Paint fontLegendBold = new Paint(Color.BLACK);
			fontLegendBold.setTypeface(
					Typeface.createFromAsset(getReactApplicationContext().getAssets(), "fonts/unispace bd.ttf"));
			fontLegendBold.setTextSize(size_legend);

			Paint fontChave = new Paint(Color.BLACK);
			fontChave.setTypeface(Typeface.create(Typeface.SANS_SERIF, Typeface.NORMAL));
			fontChave.setTextSize(size_chave);

			Paint fontChaveBold = new Paint(Color.BLACK);
			fontChaveBold.setTypeface(Typeface.create(Typeface.SANS_SERIF, Typeface.BOLD));
			fontChaveBold.setTextSize(size_chave);

			Paint p = new Paint(Color.RED);
			p.setStyle(Paint.Style.STROKE);
			p.setStrokeWidth(2);

			g.drawColor(Color.WHITE);

			x = 0;
			y = 0;
			w = 576;
			h = 1500;
			size_text = 32;
			size_legend = 16;
			size_chave = 22;
			row_width = 55;
			row_height = 20;
			h = 450; // as chaves de acesso + a parte onde descreve Danfe Simplificado

			h += 8 * row_height; // Impostos
			h += (2 + (nf.getParcelas().size())) * row_height; // dados financeiros
			h += (8 + (nf.getDestRazaoSocial().length() > row_width ? 1 : 0)) * row_height; // dados do comprovante de
																							// recebimento
			h += (3 + (nf.getOperacao().length() > (row_width - 1 - 26) ? 1 : 0)) * row_height; // Natureza Operação
			h += (6 + (nf.getEmitRazaoSocial().length() > (row_width) ? 1 : 0)) * row_height; // Emitente
			h += ((nf.getEmitEndereco().length() > (row_width) ? 1 : 0)) * row_height; // Endereco
			h += ((12 + Utils.formatCEP(nf.getEmitCEP()).length() + nf.getEmitCidade().length()
					+ nf.getEmitUF().length() + Utils.formatFone(nf.getEmitTelefone()).length() > (row_width) ? 1 : 0))
					* row_height; // Outros dados
									// emitente
			h += (6 + (nf.getDestRazaoSocial().length() > (row_width) ? 1 : 0)) * row_height; // Destinatário
			h += ((nf.getDestEndereco().length() > (row_width) ? 1 : 0)) * row_height; // Endereco
			h += ((12 + Utils.formatCEP(nf.getDestCEP()).length() + nf.getDestCidade().length()
					+ nf.getDestUF().length() + Utils.formatFone(nf.getDestTelefone()).length() > (row_width) ? 1 : 0))
					* row_height; // Outros dados
									// emitente
			// produtos
			h += 2 * row_height;
			witdthProd = 24;
			for (int i = 0; i < nf.getItens().size(); i++) {
				// y+=row_height;
				NotafiscalItem item = nf.getItens().get(i);
				int witdthField1_1 = witdthProd - 5;
				int quantprod = 1;
				if (item.getDescricao().length() > (witdthField1_1 - 1)) {
					quantprod = 2;
				}
				h += quantprod * row_height;
			}
			// dados adicionais
			h += 3 * row_height;
			witdthProd = 32;
			h += (nf.getInformacoesAdicionais().length() / witdthProd) * row_height;
			witdthProd = 24;

			// Comprovante de recebimento
			// ==========================================================================
			String campo1 = "RECEBEMOS DE " + nf.getEmitRazaoSocial();
			String campo2 = "";
			String campo3 = "";
			String campo4 = "Identificação/Assinatura";
			String campo5 = "____/____/____";
			String campo6 = "_______________________________";
			String campo7 = nf.getDestRazaoSocial();
			String campo8 = "";
			int linha_adicional = 0;
			if (campo7.length() > row_width) {
				linha_adicional = 1;
				campo8 = campo7.substring(row_width, campo7.length());
				campo7 = campo7.substring(0, row_width);
			}
			g.drawRect(0, y, w, y + (row_height * (8 + linha_adicional)) + 10, p);
			if (campo1.length() > (row_width - 12)) {
				campo2 = campo1.substring((row_width - 12), campo1.length());
				if (campo2.length() > (row_width - 12)) {
					campo3 = campo2.substring((row_width - 12), campo1.length());
					campo2 = campo2.substring(0, (row_width - 12));
				}
				campo1 = campo1.substring(0, (row_width - 12));
			}
			Formatter f1 = new Formatter();
			// String valor = String.valueOf(f1.format("%1.2f",
			// Utils.round(nf.getValorProdutos(), 2))).replace(".", ",");
			String valor = String.valueOf(f1.format("%1.2f", Utils.round(nf.getvTotalNF(), 2))).replace(".", ",");
			char[] chars = new char[(row_width - 11) - campo1.length()];
			Arrays.fill(chars, ' ');
			DecimalFormat df = new DecimalFormat("000000");
			campo1 = campo1 + new String(chars) + "NFe: " + df.format(nf.getNumNf());
			y += row_height;
			x = 10;
			g.drawText(campo1, x, y, fontLegend);
			chars = new char[(row_width - 7) - campo2.length()];
			Arrays.fill(chars, ' ');
			campo2 = campo2 + new String(chars) + "SERIE " + nf.getSerie();
			y += row_height;
			g.drawText(campo2, x, y, fontLegend);
			chars = new char[row_width - campo3.length() - 7 - valor.length()];
			Arrays.fill(chars, ' ');
			campo3 = campo3 + new String(chars) + "VALOR: " + valor;
			y += row_height;
			g.drawText(campo3, x, y, fontLegend);
			chars = new char[row_width - campo4.length()];
			Arrays.fill(chars, ' ');
			campo4 = new String(chars) + campo4;
			y += row_height;
			g.drawText(campo4, x, y, fontLegend);
			chars = new char[row_width - campo5.length() - campo6.length()];
			Arrays.fill(chars, ' ');
			campo5 = campo5 + new String(chars) + campo6;
			y += row_height * 3;
			g.drawText(campo5, x, y, fontLegend);
			chars = new char[row_width - campo7.length()];
			Arrays.fill(chars, ' ');
			campo7 = new String(chars) + campo7;
			y += row_height;
			g.drawText(campo7, x, y, fontLegend);
			if (campo8.length() > 0) {
				y += row_height;
				g.drawText(campo8, x, y, fontLegend);
			}
			// Chave de acesso do Comprovante de recebimento
			// =======================================================
			String chave = nf.getChaveAcesso();
			// y+=row_height*1;
			x = 10;
			y += 20;
			BarCode.drawBarCode128C(g, chave, x, y, w, 80);
			y += 105;
			g.drawText(chave, x, y, fontChave);
			y += size_chave;
			p.setPathEffect(new DashPathEffect(new float[] { 10, 20 }, 0));
			g.drawLine(0, y, w, y, p);
			p.setPathEffect(null);
			y += row_height;
			// DESCRIÇÃO DO DANFE
			// ==========================================================================
			campo1 = "      DANFE SIMPLIFICADO";
			campo2 = "1-SAIDA";
			campo3 = "     Documento Auxiliar da";
			campo4 = "";
			String linha5 = "     Nota Fiscal Eletrônica";
			chars = new char[row_width - 3 - campo2.length()];
			Arrays.fill(chars, ' ');
			y += row_height;
			x = 10;
			g.drawText(new String(chars) + campo2, x, y, fontLegend);
			g.drawText(campo1, x, y, fontLegendBold);

			df = new DecimalFormat("000000");
			campo4 = "NFe: " + df.format(nf.getNumNf());
			chars = new char[row_width - 3 - campo3.length() - campo4.length()];
			Arrays.fill(chars, ' ');
			y += row_height;
			x = 10;
			g.drawText(campo3 + new String(chars) + campo4, x, y, fontLegend);
			campo7 = "SERIE " + nf.getSerie();
			chars = new char[row_width - 3 - linha5.length() - campo7.length()];
			Arrays.fill(chars, ' ');
			y += row_height;
			g.drawText(linha5 + new String(chars) + campo7, x, y, fontLegend);
			// Chave de acesso do Danfe
			// ==========================================================================
			y += row_height * 1;
			g.drawRect(0, y, w, y + size_chave + size_text, p);
			x = 10;
			y += size_chave;
			g.drawText("CHAVE DE ACESSO", x, y, fontChaveBold);
			y += size_chave;
			g.drawText(chave, x, y, fontChave);
			y += 20;
			BarCode.drawBarCode128C(g, chave, x, y, w, 80);
			y += 105 - row_height;
			// Operação/Datas
			// ==========================================================================
			campo1 = "Nat. Op:" + nf.getOperacao();
			campo2 = "EMISSÃO ";
			campo3 = nf.getDataEmissaoTexto();
			campo4 = "Hora: " + nf.getHoraSaidaTexto();
			campo5 = "SAÍDA ";
			campo6 = nf.getDataSaidaTexto();
			campo7 = "";
			int linhas = 2;
			if (campo1.length() > (row_width - 1 - campo2.length() - campo3.length())) {
				campo7 = campo1.substring((row_width - 1 - campo2.length() - campo3.length()), campo1.length());
				campo1 = campo1.substring(0, (row_width - 1 - campo2.length() - campo3.length()));
				linhas = 3;
			}
			g.drawRect(0, y, w, y + (row_height * linhas) + 10, p);
			x = 10;
			y += row_height;
			chars = new char[row_width - campo3.length()];
			Arrays.fill(chars, ' ');
			g.drawText(new String(chars) + campo3, x, y, fontLegend);
			chars = new char[row_width - campo2.length() - campo3.length()];
			Arrays.fill(chars, ' ');
			g.drawText(new String(chars) + campo2, x, y, fontLegendBold);
			g.drawText(campo1, x, y, fontLegend);

			if (campo7.length() > 0) {
				y += row_height;
				x = 10;
				g.drawText(campo7, x, y, fontLegend);
			}

			y += row_height;
			x = 10;
			chars = new char[row_width - campo6.length()];
			Arrays.fill(chars, ' ');
			g.drawText(new String(chars) + campo6, x, y, fontLegend);
			chars = new char[row_width - campo5.length() - campo6.length()];
			Arrays.fill(chars, ' ');
			g.drawText(new String(chars) + campo5, x, y, fontLegendBold);
			g.drawText(campo4, x, y, fontLegend);
			// Emitente
			// ==========================================================================
			campo1 = "EMITENTE";
			campo2 = nf.getEmitRazaoSocial();
			campo3 = "";
			campo4 = nf.getEmitEndereco();
			campo5 = "";
			campo6 = "CEP " + Utils.formatCEP(nf.getEmitCEP()) + "  " + nf.getEmitCidade() + "-" + nf.getEmitUF()
					+ " Tel " + Utils.formatFone(nf.getEmitTelefone());
			campo7 = "";
			campo8 = "CNPJ " + Utils.formatCNPJCPF(nf.getEmitCNPJ()) + " IE " + nf.getEmitIE();

			linha_adicional = 0;
			if (campo2.length() > row_width) {
				linha_adicional += 1;
				campo3 = campo2.substring(row_width, campo2.length());
				campo2 = campo2.substring(0, row_width);
			}
			if (campo4.length() > row_width) {
				linha_adicional += 1;
				campo5 = campo4.substring(row_width, campo4.length());
				campo4 = campo4.substring(0, row_width);
			}
			if (campo6.length() > row_width) {
				linha_adicional += 1;
				campo7 = campo6.substring(row_width, campo6.length());
				campo6 = campo6.substring(0, row_width);
			}
			y += row_height;
			g.drawRect(0, y, w, y + (row_height * (5 + linha_adicional)) + 10, p);
			x = 10;
			y += row_height;
			g.drawText(campo1, x, y, fontLegendBold);
			y += row_height;
			g.drawText(campo2, x, y, fontLegend);
			if (campo3.length() > 0) {
				y += row_height;
				g.drawText(campo3, x, y, fontLegend);
			}
			y += row_height;
			g.drawText(campo4, x, y, fontLegend);
			if (campo5.length() > 0) {
				y += row_height;
				g.drawText(campo5, x, y, fontLegend);
			}
			y += row_height;
			g.drawText(campo6, x, y, fontLegend);
			if (campo7.length() > 0) {
				y += row_height;
				g.drawText(campo7, x, y, fontLegend);
			}
			y += row_height;
			g.drawText(campo8, x, y, fontLegend);
			// Destinatário
			// ==========================================================================
			campo1 = "DESTINATÁRIO";
			campo2 = nf.getDestRazaoSocial();
			campo3 = "";
			campo4 = nf.getDestEndereco();
			campo5 = "";
			campo6 = "CEP " + Utils.formatCEP(nf.getDestCEP()) + "  " + nf.getDestCidade() + "-" + nf.getDestUF()
					+ " Tel " + Utils.formatFone(nf.getDestTelefone());
			campo7 = "";
			campo8 = "CNPJ/CPF " + Utils.formatCNPJCPF(nf.getDestCNPJ()) + " IE " + nf.getDestIE();

			linha_adicional = 0;
			if (campo2.length() > row_width) {
				linha_adicional += 1;
				campo3 = campo2.substring(row_width, campo2.length());
				campo2 = campo2.substring(0, row_width);
			}
			if (campo4.length() > row_width) {
				linha_adicional += 1;
				campo5 = campo4.substring(row_width, campo4.length());
				campo4 = campo4.substring(0, row_width);
			}
			if (campo6.length() > row_width) {
				linha_adicional += 1;
				campo7 = campo6.substring(row_width, campo6.length());
				campo6 = campo6.substring(0, row_width);
			}
			y += row_height;
			g.drawRect(0, y, w, y + (row_height * (5 + linha_adicional)) + 10, p);
			x = 10;
			y += row_height;
			g.drawText(campo1, x, y, fontLegendBold);
			y += row_height;
			g.drawText(campo2, x, y, fontLegend);
			if (campo3.length() > 0) {
				y += row_height;
				g.drawText(campo3, x, y, fontLegend);
			}
			y += row_height;
			g.drawText(campo4, x, y, fontLegend);
			if (campo5.length() > 0) {
				y += row_height;
				g.drawText(campo5, x, y, fontLegend);
			}
			y += row_height;
			g.drawText(campo6, x, y, fontLegend);
			if (campo7.length() > 0) {
				y += row_height;
				g.drawText(campo7, x, y, fontLegend);
			}
			y += row_height;
			g.drawText(campo8, x, y, fontLegend);
			// Parcelas
			// ==========================================================================
			y += row_height;
			g.drawRect(0, y, w, y + (row_height * (nf.getParcelas().size() + 1)) + 10, p);
			campo1 = "DADOS FINANCEIROS";
			x = 10;
			y += row_height;
			g.drawText(campo1, x, y, fontLegendBold);

			for (int i = 0; i < nf.getParcelas().size(); i++) {
				NotafiscalParcela item = nf.getParcelas().get(i);
				campo2 = "DOCTO: " + item.getId() + "-" + item.getCodPedido();
				campo3 = "VENCIMENTO: " + item.getVencimentoTexto();
				campo4 = "VALOR: ";
				campo5 = String.format(new Locale("pt", "BR"), "%1$,.2f", item.getValor()).replace(".", ",");
				chars = new char[10 - campo5.length()];
				Arrays.fill(chars, ' ');
				campo5 = new String(chars) + campo5;
				y += row_height;
				g.drawText(campo2, x, y, fontLegend);
				chars = new char[15];
				Arrays.fill(chars, ' ');
				g.drawText(new String(chars) + campo3, x, y, fontLegend);
				chars = new char[39];
				Arrays.fill(chars, ' ');
				g.drawText(new String(chars) + campo4 + campo5, x, y, fontLegend);
			}
			// Produtos
			// ==========================================================================
			double perc = w / row_width;
			int pos = 0, somapos = 0;
			int witdthField1 = witdthProd, witdthField2 = 4, witdthField3 = 3, witdthField4 = 7, witdthField5 = 7,
					witdthField6 = 9, witdthField7 = 4;
			y += row_height;
			// Retangulo
			int y_1 = y;
			// Cabeçalho
			pos = 0;
			somapos = 0;
			pos = (int) (Utils.round(witdthField1 * perc, 0));
			g.drawRect(somapos, y, pos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField2 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField3 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField4 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField5 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField6 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField7 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);

			x = 5;
			y += row_height;

			campo1 = "Produto";
			chars = new char[witdthField1 - campo1.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars);
			campo2 = "CST";
			chars = new char[witdthField2 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + campo2 + new String(chars);
			campo2 = "Un";
			chars = new char[witdthField3 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + campo2 + new String(chars);
			campo2 = "Qtde";
			chars = new char[witdthField4 - campo2.length() - 1];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = "Vl.Un.";
			chars = new char[witdthField5 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = "Total";
			chars = new char[witdthField6 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = " Al";
			chars = new char[witdthField7 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + campo2 + new String(chars);
			g.drawText(campo1, x, y, fontLegend);
			// Itens
			x = 5;
			y += 5;
			for (int i = 0; i < nf.getItens().size(); i++) {
				y += row_height;
				NotafiscalItem item = nf.getItens().get(i);
				pos = 0;
				somapos = 0;
				campo1 = item.getDescricao();
				// Descrição de produto maior que o tamanho do campo
				// =================================================
				int witdthField1_1 = witdthField1 - 5;
				int quantprod = (int) (campo1.length() / witdthField1_1);
				if ((campo1.length() % (witdthField1_1 - 1) > 0) || ((campo1.length() / (witdthField1_1 - 1)) > 1)) {
					quantprod += 1;
				}
				String[] prods = new String[quantprod];
				for (int j = 0; j < prods.length; j++) {
					if (j == prods.length - 1) {
						prods[j] = "   " + campo1.substring(j * witdthField1_1, campo1.length()).trim();
					} else {
						prods[j] = "   " + campo1.substring(j * witdthField1_1, (j + 1) * witdthField1_1).trim();
					}
				}
				if (quantprod > 1) {
					campo1 = " " + String.valueOf(i + 1) + " " + campo1.substring(0, witdthField1_1);
				} else {
					campo1 = " " + String.valueOf(i + 1) + " " + campo1;
				}
				// ==================================================
				chars = new char[witdthField1 - campo1.length()];
				Arrays.fill(chars, ' ');
				campo1 = campo1 + new String(chars);
				campo2 = item.getCst();
				chars = new char[witdthField2 - campo2.length()];
				Arrays.fill(chars, ' ');
				campo1 = campo1 + campo2 + new String(chars);
				campo2 = item.getUnidade();
				chars = new char[witdthField3 - campo2.length()];
				Arrays.fill(chars, ' ');
				campo1 = campo1 + campo2 + new String(chars);
				campo2 = String.format(new Locale("pt", "BR"), "%1$,.3f", item.getQuantidade()).replace(".", ",");
				if ((witdthField4 - campo2.length() - 1) > 0)
					chars = new char[witdthField4 - campo2.length() - 1];
				else
					chars = new char[0];
				Arrays.fill(chars, ' ');
				campo1 = campo1 + new String(chars) + campo2;
				campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f", item.getPreco()).replace(".", ",");
				if (witdthField5 - campo2.length() > 0)
					chars = new char[witdthField5 - campo2.length()];
				else
					chars = new char[0];
				Arrays.fill(chars, ' ');
				campo1 = campo1 + new String(chars) + campo2;
				campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f", item.getValorTotal()).replace(".", ",");
				if (witdthField6 - campo2.length() > 0)
					chars = new char[witdthField6 - campo2.length()];
				else
					chars = new char[0];
				Arrays.fill(chars, ' ');
				campo1 = campo1 + new String(chars) + campo2;
				campo2 = " " + String.valueOf(item.getAliq());
				chars = new char[witdthField7 - campo2.length()];
				Arrays.fill(chars, ' ');
				campo1 = campo1 + campo2 + new String(chars);
				g.drawText(campo1, x, y, fontLegend);
				// Descrição de produto maior que o tamanho do campo
				// =================================================
				for (int k = 1; k < prods.length; k++) {
					y += row_height;
					g.drawText(prods[k], x, y, fontLegend);
				}
				// =================================================
			}
			g.drawRect(0, y_1, w, y + 10, p);
			// Retangulo por campo
			pos = (int) (Utils.round(witdthField1 * perc, 0));
			g.drawRect(somapos, y_1, pos, y + 10, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField2 * perc, 0));
			g.drawRect(somapos, y_1, pos + somapos, y + 10, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField3 * perc, 0));
			g.drawRect(somapos, y_1, pos + somapos, y + 10, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField4 * perc, 0));
			g.drawRect(somapos, y_1, pos + somapos, y + 10, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField5 * perc, 0));
			g.drawRect(somapos, y_1, pos + somapos, y + 10, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField6 * perc, 0));
			g.drawRect(somapos, y_1, pos + somapos, y + 10, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField7 * perc, 0));
			g.drawRect(somapos, y_1, pos + somapos, y + 10, p);

			// Imposto
			// ==========================================================================
			campo1 = "CÁLCULO DO IMPOSTO";
			x = 10;
			y += row_height * 2;
			g.drawText(campo1, x, y, fontLegendBold);

			witdthField1 = 11;
			witdthField2 = 10;
			witdthField3 = 11;
			witdthField4 = 12;
			witdthField5 = 13;
			y += 5;
			// Retangulo
			y_1 = y;
			// Cabeçalho
			somapos = 0;
			pos = (int) (Utils.round(witdthField1 * perc, 0)) + 10;
			g.drawRect(somapos, y, pos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField2 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField3 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField4 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField5 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);

			x = 5;
			y += row_height;

			campo1 = "B Calc ICMS";
			chars = new char[witdthField1 - campo1.length()];
			Arrays.fill(chars, ' ');
			campo1 = new String(chars) + campo1;
			campo2 = "Vl ICMS";
			chars = new char[witdthField2 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = "B ICMS ST";
			chars = new char[witdthField3 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = "VL ICMS SUB";
			chars = new char[witdthField4 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = "Total Prods";
			chars = new char[witdthField5 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			g.drawText(campo1, x, y, fontLegend);
			// Valores
			y += row_height + 5;
			campo1 = String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getvBCICMS()).replace(".", ",");
			;
			chars = new char[witdthField1 - campo1.length()];
			Arrays.fill(chars, ' ');
			campo1 = new String(chars) + campo1;
			campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getvICMS()).replace(".", ",");
			;
			;
			chars = new char[witdthField2 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getvBCICMSST()).replace(".", ",");
			;
			;
			chars = new char[witdthField3 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getvICMSST()).replace(".", ",");
			;
			;
			chars = new char[witdthField4 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getValorProdutos()).replace(".", ",");
			;
			;
			chars = new char[witdthField5 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			g.drawText(campo1, x, y, fontLegend);

			x = 5;
			y += 5;
			g.drawRect(0, y_1, w, y + 5, p);
			somapos = 0;
			pos = (int) (Utils.round(witdthField1 * perc, 0)) + 10;
			g.drawRect(somapos, y_1, pos, y + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField2 * perc, 0));
			g.drawRect(somapos, y_1, pos + somapos, y + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField3 * perc, 0));
			g.drawRect(somapos, y_1, pos + somapos, y + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField4 * perc, 0));
			g.drawRect(somapos, y_1, pos + somapos, y + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField5 * perc, 0));
			g.drawRect(somapos, y_1, pos + somapos, y + 5, p);

			// Outros Valores
			// ==========================================================================
			witdthField1 = 8;
			witdthField2 = 8;
			witdthField3 = 10;
			witdthField4 = 12;
			witdthField5 = 8;
			witdthField6 = 11;
			y += 5;
			// Retangulo
			y_1 = y;
			// Cabeçalho
			somapos = 0;
			pos = (int) (Utils.round(witdthField1 * perc, 0)) + 10;
			g.drawRect(somapos, y, pos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField2 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField3 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField4 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField5 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField6 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);

			x = 5;
			y += row_height;

			campo1 = "FRETE";
			chars = new char[witdthField1 - campo1.length()];
			Arrays.fill(chars, ' ');
			campo1 = new String(chars) + campo1;
			campo2 = "SEGURO";
			chars = new char[witdthField2 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = "DESCONTO";
			chars = new char[witdthField3 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = "OUTRAS DESP";
			chars = new char[witdthField4 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = "IPI";
			chars = new char[witdthField5 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = "TOTAL NF";
			chars = new char[witdthField6 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			g.drawText(campo1, x, y, fontLegend);
			// Valores
			y += row_height + 5;
			campo1 = String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getvFrete()).replace(".", ",");
			;
			chars = new char[witdthField1 - campo1.length()];
			Arrays.fill(chars, ' ');
			campo1 = new String(chars) + campo1;
			campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getvSeguro()).replace(".", ",");
			;
			;
			chars = new char[witdthField2 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getvDesconto()).replace(".", ",");
			;
			;
			chars = new char[witdthField3 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getvOutro()).replace(".", ",");
			;
			;
			chars = new char[witdthField4 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getvIpi()).replace(".", ",");
			;
			;
			chars = new char[witdthField5 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getvTotalNF()).replace(".", ",");
			;
			;
			chars = new char[witdthField6 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			g.drawText(campo1, x, y, fontLegend);

			x = 5;
			y += 5;
			g.drawRect(0, y_1, w, y + 5, p);
			somapos = 0;
			pos = (int) (Utils.round(witdthField1 * perc, 0)) + 10;
			g.drawRect(somapos, y_1, pos, y + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField2 * perc, 0));
			g.drawRect(somapos, y_1, pos + somapos, y + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField3 * perc, 0));
			g.drawRect(somapos, y_1, pos + somapos, y + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField4 * perc, 0));
			g.drawRect(somapos, y_1, pos + somapos, y + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField5 * perc, 0));
			g.drawRect(somapos, y_1, pos + somapos, y + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField6 * perc, 0));
			g.drawRect(somapos, y_1, pos + somapos, y + 5, p);

			// INFORMAÇÔES ADICIONAIS
			// ==========================================================================
			campo1 = "DADOS ADICIONAIS";
			x = 10;
			y += row_height * 2;
			g.drawText(campo1, x, y, fontLegendBold);

			witdthField1 = 32;
			witdthField2 = 26;
			y += 5;
			// Retangulo
			y_1 = y;
			x = 5;
			// Descrição de inf adicional maior que o tamanho do campo
			// =================================================
			String[] msgs = nf.getInformacoesAdicionais().split("\\|");
			int countLine = 0;

			for (int i = 0; i < msgs.length; i++) {
				campo1 = msgs[i];

				int quantprod = (int) (campo1.length() / witdthField1);
				if (campo1.length() % (witdthField1) > 0) {
					quantprod += 1;
				}
				String[] prods = new String[quantprod];

				for (int j = 0; j < prods.length; j++) {
					if (j == prods.length - 1) {
						prods[j] = campo1.substring(j * witdthField1, campo1.length()).trim();
					} else {
						prods[j] = campo1.substring(j * witdthField1, (j + 1) * witdthField1).trim();
					}
				}
				if (quantprod > 1) {
					campo1 = campo1.substring(0, witdthField1);
				}
				// ==================================================
				if (i == 0) {
					chars = new char[witdthField1 - campo1.length()];
					Arrays.fill(chars, ' ');
					campo1 = campo1 + new String(chars);
					campo2 = "    RESERVADO AO FISCO";
					campo1 = campo1 + campo2;
				}
				countLine++;
				y += row_height;
				g.drawText(campo1, x, y, fontLegend);

				for (int k = 1; k < prods.length; k++) {
					countLine++;
					y += row_height;
					g.drawText(prods[k], x, y, fontLegend);
				}
			}
			if (countLine < 5) {
				y += row_height * (5 - countLine);
			}

			y += 5;
			// Dados adicionais
			g.drawRect(0, y_1, w, y + 5, p);
			somapos = 0;
			pos = (int) (Utils.round(witdthField1 * perc, 0)) + 10;
			g.drawRect(somapos, y_1, pos, y + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField2 * perc, 0));
			g.drawRect(somapos, y_1, pos + somapos, y + 5, p);
			Bitmap BitmapReturn = Bitmap.createBitmap(BitmapDanfe.getWidth(), h1, Bitmap.Config.RGB_565);
			Canvas g3 = new Canvas(BitmapReturn);

			g3.drawBitmap(BitmapDanfe, 0, 0, p);
			return BitmapReturn;
		} catch (Exception e) {
			e.printStackTrace();
			Toast.makeText(getReactApplicationContext(), e.getMessage(), Toast.LENGTH_LONG).show();
			return null;
		}
	}

	public Bitmap createDanfeC()
    {
        int x=0, y=0, w=576, h=0;
        int size_text=32, size_legend=16, size_chave=22, row_width=55, row_height=20;

        //Tamanho do Bitmap
        //-----------------
        h = 0; //as chaves de acesso + a parte onde descreve Danfe Simplificado
        NotaFiscal nf = this.NF;
        /*
        h+=460;
        h += 8 * row_height; //Impostos
        h += (2 + (nf.getParcelas().size())) * row_height; // dados financeiros
        h += (8 + (nf.getDestRazaoSocial().length() > row_width ? 1 : 0)) * row_height; // dados do comprovante de recebimento
        h += (3 + (nf.getOperacao().length() > (row_width - 1 - 26) ? 1 : 0)) * row_height; //Natureza Operação
        h += (6 + (nf.getEmitRazaoSocial().length() > (row_width) ? 1 : 0)) * row_height; //Emitente
        h += ((nf.getEmitEndereco().length() > (row_width) ? 1 : 0)) * row_height; //Endereco
        h += ((12 + Utils.formatCEP(nf.getEmitCEP()).length() + nf.getEmitCidade().length() + nf.getEmitUF().length() + Utils.formatFone(nf.getEmitTelefone()).length() > (row_width) ? 1 : 0)) * row_height; //Outros dados emitente
        h += (6 + (nf.getDestRazaoSocial().length() > (row_width) ? 1 : 0)) * row_height; //Destinatário
        h += ((nf.getDestEndereco().length() > (row_width) ? 1 : 0)) * row_height; //Endereco
        h += ((12 + Utils.formatCEP(nf.getDestCEP()).length() + nf.getDestCidade().length() + nf.getDestUF().length() + Utils.formatFone(nf.getDestTelefone()).length() > (row_width) ? 1 : 0)) * row_height; //Outros dados emitente
        //produtos
        h += 2 * row_height;
        int witdthProd = 25;
        for (int i = 0; i < nf.getItens().size(); i++) {
            //y+=row_height;
            NotafiscalItem item = nf.getItens().get(i);
            int witdthField1_1 = witdthProd - 1;
            int quantprod = 1;
            if (item.getDescricao().length() > (witdthField1_1 - 1)) {
                quantprod = 2;
            }
            h += quantprod * row_height;
        }
        //dados adicionais
        h += 3 * row_height;
        witdthProd = 32;
        h += (nf.getInformacoesAdicionais().length() / witdthProd) * row_height;
        */
        int witdthProd = 25;
        h = 1300 + (row_height * nf.getItens().size());
        int h1 = h;
        Bitmap BitmapDanfe = Bitmap.createBitmap(w, h, Bitmap.Config.RGB_565);
        Canvas g = new Canvas(BitmapDanfe);

        Paint fontTitleBold = new Paint(Color.BLACK);
        fontTitleBold.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.BOLD));
        fontTitleBold.setTextSize((int) (size_text * 1.2));

        Paint fontText = new Paint(Color.BLACK);
        fontText.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.NORMAL));
        fontText.setTextSize((int) (size_text));

        Paint fontTextSmall = new Paint(Color.BLACK);
        fontTextSmall.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.NORMAL));
        fontTextSmall.setTextSize((int) (15));

        Paint fontTextSmallBold = new Paint(Color.BLACK);
        fontTextSmallBold.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.BOLD));
        fontTextSmallBold.setTextSize((int) (15));

        Paint fontTextBold = new Paint(Color.BLACK);
        fontTextBold.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.BOLD));
        fontTextBold.setTextSize((int) (size_text));


        Paint fontLegend = new Paint(Color.BLACK);
        fontLegend.setTypeface(Typeface.createFromAsset(getReactApplicationContext().getAssets(), "fonts/unispace rg.ttf"));
        fontLegend.setTextSize(size_legend);


        Paint fontLegendBold = new Paint(Color.BLACK);
        fontLegendBold.setTypeface(Typeface.createFromAsset(getReactApplicationContext().getAssets(), "fonts/unispace bd.ttf"));
        fontLegendBold.setTextSize(size_legend);


        Paint fontChave = new Paint(Color.BLACK);
        fontChave.setTypeface(Typeface.create(Typeface.SANS_SERIF, Typeface.NORMAL));
        fontChave.setTextSize(size_chave);

        Paint fontChaveBold = new Paint(Color.BLACK);
        fontChaveBold.setTypeface(Typeface.create(Typeface.SANS_SERIF, Typeface.BOLD));
        fontChaveBold.setTextSize(size_chave);

        Paint p = new Paint(Color.RED);
        p.setStyle(Paint.Style.STROKE);
        p.setStrokeWidth(2);

        g.drawColor(Color.WHITE);

        x=0; y=0; w=576; h=1500;
        size_text=32; size_legend=16; size_chave=22; row_width=55; row_height=20;


        h = 450; //as chaves de acesso + a parte onde descreve Danfe Simplificado

        h += 8 * row_height; //Impostos
        h += (2 + (nf.getParcelas().size())) * row_height; // dados financeiros
        h += (8 + (nf.getDestRazaoSocial().length() > row_width ? 1 : 0)) * row_height; // dados do comprovante de recebimento
        h += (3 + (nf.getOperacao().length() > (row_width - 1 - 26) ? 1 : 0)) * row_height; //Natureza Operação
        h += (6 + (nf.getEmitRazaoSocial().length() > (row_width) ? 1 : 0)) * row_height; //Emitente
        h += ((nf.getEmitEndereco().length() > (row_width) ? 1 : 0)) * row_height; //Endereco
        h += ((12 + Utils.formatCEP(nf.getEmitCEP()).length() + nf.getEmitCidade().length() + nf.getEmitUF().length() + Utils.formatFone(nf.getEmitTelefone()).length() > (row_width) ? 1 : 0)) * row_height; //Outros dados emitente
        h += (6 + (nf.getDestRazaoSocial().length() > (row_width) ? 1 : 0)) * row_height; //Destinatário
        h += ((nf.getDestEndereco().length() > (row_width) ? 1 : 0)) * row_height; //Endereco
        h += ((12 + Utils.formatCEP(nf.getDestCEP()).length() + nf.getDestCidade().length() + nf.getDestUF().length() + Utils.formatFone(nf.getDestTelefone()).length() > (row_width) ? 1 : 0)) * row_height; //Outros dados emitente
        //produtos
        h += 2 * row_height;
        witdthProd = 24;
        for (int i = 0; i < nf.getItens().size(); i++) {
            //y+=row_height;
            NotafiscalItem item = nf.getItens().get(i);
            int witdthField1_1 = witdthProd - 5;
            int quantprod = 1;
            if (item.getDescricao().length() > (witdthField1_1 - 1)) {
                quantprod = 2;
            }
            h += quantprod * row_height;
        }
        //dados adicionais
        h += 3 * row_height;
        witdthProd = 32;
        h += (nf.getInformacoesAdicionais().length() / witdthProd) * row_height;
        witdthProd = 25;


        String campo1, campo2, campo3, campo4, campo5, campo6, campo7, campo8, valor;
        int linha_adicional = 0;
        Formatter f1 = new Formatter();
        char[] chars;
        DecimalFormat df;
        //Emitente
        //==========================================================================
        campo1 = nf.getEmitRazaoSocial();
        campo2 = "";
        campo3 = "CNPJ: " + Utils.formatCNPJCPF(nf.getEmitCNPJ());
        campo4 = "IE: " + nf.getEmitIE();
        campo5 = nf.getEmitEndereco() + ". CEP " + Utils.formatCEP(nf.getEmitCEP());
        campo6 = "";
        campo7 = nf.getEmitCidade() + "-" + nf.getEmitUF() + " - " + Utils.formatFone(nf.getEmitTelefone());
        campo8 = "";

        linha_adicional = 0;
        if (campo1.length() > row_width) {
            linha_adicional += 1;
            campo2 = campo1.substring(row_width, campo1.length());
            campo1 = campo1.substring(0, row_width);
        }
        if (campo5.length() > row_width) {
            linha_adicional += 1;
            campo6 = campo5.substring(row_width, campo5.length());
            campo5 = campo5.substring(0, row_width);
        }
        if (campo7.length() > row_width) {
            linha_adicional += 1;
            campo8 = campo7.substring(row_width, campo7.length());
            campo7 = campo7.substring(0, row_width);
        }
        y += row_height;
        x = 10;
        y += row_height;
        g.drawText(campo1, x, y, fontLegend);
        if (campo2.length() > 0) {
            y += row_height;
            g.drawText(campo2, x, y, fontLegend);
        }
        y += row_height;
        g.drawText(campo3, x, y, fontLegend);
        y += row_height;
        g.drawText(campo4, x, y, fontLegend);
        y += row_height;
        g.drawText(campo5, x, y, fontLegend);
        if (campo6.length() > 0) {
            y += row_height;
            g.drawText(campo6, x, y, fontLegend);
        }
        y += row_height;
        g.drawText(campo7, x, y, fontLegend);
        if (campo8.length() > 0) {
            y += row_height;
            g.drawText(campo8, x, y, fontLegend);
        }
        y += row_height;

        //DESCRIÇÃO DO DANFE
        //==========================================================================
        campo1 = "DANFE NFC-e";
        campo2 = "Documento Auxiliar da Nota Fiscal de Consumidor Eletrônica";
        campo3 = "NFC-e não permite aproveitamento de crédito de ICMS";
        x = 0;
        y += row_height;
        g.drawText(getLinha(campo1, 55, "C"), x, y, fontLegendBold);
        y += row_height;
        g.drawText(getLinha(campo2, 63, "C"), x, y, fontTextSmallBold);
        y += row_height;
        g.drawText(getLinha(campo3, 63, "C"), x, y, fontTextSmall);




        //Produtos
        //==========================================================================
        double perc = w / row_width;
        int pos = 0, somapos = 0;
        int witdthField1 = 4, witdthField2 = witdthProd, witdthField3 = 3, witdthField4 = 8, witdthField5 = 7, witdthField6 = 9;
        y += row_height;
        //Retangulo
        int y_1 = y;
        //Cabeçalho
        pos = 0;
        somapos = 0;

        x = 10;
        y += row_height;

        campo1 = "Cód";
        chars = new char[witdthField1 - campo1.length()];
        Arrays.fill(chars, ' ');
        campo1 = campo1 + new String(chars);
        campo2 = "Descrição";
        chars = new char[witdthField2 - campo2.length()];
        Arrays.fill(chars, ' ');
        campo1 = campo1 + campo2 + new String(chars);
        campo2 = "Un";
        chars = new char[witdthField3 - campo2.length()];
        Arrays.fill(chars, ' ');
        campo1 = campo1 + campo2 + new String(chars);
        campo2 = "Qtde";
        chars = new char[witdthField4 - campo2.length() - 1];
        Arrays.fill(chars, ' ');
        campo1 = campo1 + new String(chars) + campo2;
        campo2 = "Vl.Un.";
        chars = new char[witdthField5 - campo2.length()];
        Arrays.fill(chars, ' ');
        campo1 = campo1 + new String(chars) + campo2;
        campo2 = "Total";
        chars = new char[witdthField6 - campo2.length()];
        Arrays.fill(chars, ' ');
        campo1 = campo1 + new String(chars) + campo2;
        g.drawText(campo1, x, y, fontLegendBold);
        //Itens
        x = 10;
        y += 5;
        double qttotal = 0;
        double valortotal = 0;
        for (int i = 0; i < nf.getItens().size(); i++) {
            y += row_height;
            NotafiscalItem item = nf.getItens().get(i);
            qttotal += item.quantidade;
            valortotal += item.valor_total;
            pos = 0;
            somapos = 0;
            campo1 = String.valueOf(item.getCodProduto());
            chars = new char[witdthField1 - campo1.length()];
            Arrays.fill(chars, ' ');
            campo1 = campo1 + new String(chars);




            campo2 = item.getDescricao();
            //Descrição de produto maior que o tamanho do campo
            //=================================================
            int witdthField2_1 = witdthField2 - 1;
            int quantprod = (int) (campo2.length() / witdthField2_1);
            if ((campo2.length() % (witdthField2_1 - 1) > 0) || ((campo2.length() / (witdthField2_1 - 1)) > 1)) {
                quantprod += 1;
            }
            String[] prods = new String[quantprod];
            for (int j = 0; j < prods.length; j++) {
                if (j == prods.length - 1) {
                    prods[j] = "   " + campo2.substring(j * witdthField2_1, campo2.length()).trim();
                } else {
                    prods[j] = "   " + campo2.substring(j * witdthField2_1, (j + 1) * witdthField2_1).trim();
                }
            }
            if (quantprod > 1) {
                campo2 = campo2.substring(0, witdthField2_1);
            }
            //==================================================
            chars = new char[witdthField2 - campo2.length()];
            Arrays.fill(chars, ' ');
            campo2 = campo2 + new String(chars);
            campo1 = campo1 + campo2;

            campo2 = item.getUnidade().substring(0,2);
            chars = new char[witdthField3 - campo2.length()];
            Arrays.fill(chars, ' ');
            campo1 = campo1 + campo2 + new String(chars);
            campo2 = String.format(new Locale("pt", "BR"), "%1$,.1f", item.getQuantidade()).replace(".", ",");
            if ((witdthField4 - campo2.length() - 1) > 0)
                chars = new char[witdthField4 - campo2.length() - 1];
            else
                chars = new char[0];
            Arrays.fill(chars, ' ');
            campo1 = campo1 + new String(chars) + campo2;
            campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f", item.getPreco()).replace(".", ",");
            if (witdthField5 - campo2.length() > 0)
                chars = new char[witdthField5 - campo2.length()];
            else
                chars = new char[0];
            Arrays.fill(chars, ' ');
            campo1 = campo1 + new String(chars) + campo2;
            campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f", item.getValorTotal()).replace(".", ",");
            if (witdthField6 - campo2.length() > 0)
                chars = new char[witdthField6 - campo2.length()];
            else
                chars = new char[0];
            Arrays.fill(chars, ' ');
            campo1 = campo1 + new String(chars) + campo2;
            g.drawText(campo1, x, y, fontLegend);
            //Descrição de produto maior que o tamanho do campo
            //=================================================
            for (int k = 1; k < prods.length; k++) {
                y += row_height;
                g.drawText(" " + prods[k], x+witdthField1+1, y, fontLegend);
            }
            //=================================================
        }
        campo2 = String.format(new Locale("pt", "BR"), "%1$,.1f", qttotal).replace(".", ",");
        campo1 = getLinha("Qtd. Total de Itens", 40, "L") + getLinha(campo2, 15, "R");
        y += row_height;
        x = 10;
        g.drawText(campo1, x, y, fontLegendBold);
        campo2 = "R$ " + String.format(new Locale("pt", "BR"), "%1$,.2f", valortotal).replace(".", ",");
        campo1 = getLinha("Total de Produtos", 40, "L") + getLinha(campo2, 15, "R");
        y += row_height;
        g.drawText(campo1, x, y, fontLegendBold);
        campo2 = "R$ " + String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getvDesconto()).replace(".", ",");
        campo1 = getLinha("Descontos", 40, "L") + getLinha(campo2, 15, "R");
        y += row_height;
        g.drawText(campo1, x, y, fontLegendBold);
        campo2 = "R$ " + String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getvFrete()).replace(".", ",");
        campo1 = getLinha("Frete", 40, "L") + getLinha(campo2, 15, "R");
        y += row_height;
        g.drawText(campo1, x, y, fontLegendBold);
        campo2 = "R$ " + String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getvTotalNF()).replace(".", ",");
        campo1 = getLinha("Total", 40, "L") + getLinha(campo2, 15, "R");
        y += row_height;
        g.drawText(campo1, x, y, fontLegendBold);
        campo2 = "R$ " + String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getvTotTrib()).replace(".", ",");
        campo1 = getLinha("Informação dos Tributos Totais Incidentes", 41, "L") + getLinha(campo2, 14, "R");
        y += row_height;
        g.drawText(campo1, x, y, fontLegendBold);
        campo1 = getLinha("Forma de Pagamento", 25, "L") + getLinha("Valor pago", 30, "R");
        y += row_height;
        g.drawText(campo1, x, y, fontLegendBold);
        campo2 = "R$ " + String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getvTotalNF()).replace(".", ",");
        campo1 = getLinha(nf.getCondicaoPagamento(), 25, "L") + getLinha(campo2, 30, "R");
        y += row_height;
        g.drawText(campo1, x, y, fontLegendBold);
        //==========================================================================
        campo1 = "ÁREA DE MENSAGEM FISCAL";
        x = 10;
        y += row_height;
        g.drawText(getLinha(campo1, 56, "C"), x, y, fontLegendBold);
        campo1 = "Número " + String.valueOf(nf.getNumNf()) + " Série " + String.valueOf(nf.getSerie()) + " " + nf.getDataSaidaTexto() + " " + nf.getHoraSaidaTexto() + " - Via Consumidor";
        y += row_height*2;
        g.drawText(getLinha(campo1, 56, "C"), x, y, fontLegend);
        campo1 = "Consulte pela Chave de Acesso em http://www.fazenda.pr.gov.br";
        y += row_height*2;
        g.drawText(getLinha(campo1, 62, "C"), x, y, fontTextSmall);
        y += row_height*2;
        g.drawText(getLinha("CHAVE DE ACESSO", 56, "C"), x, y, fontLegendBold);
        y += row_height;
        g.drawText(getLinha(nf.getChaveAcesso(), 56, "C"), x, y, fontLegend);
        y += row_height*2;
        g.drawText(getLinha("CONSUMIDOR", 56, "C"), x, y, fontLegendBold);
        if(nf.getDestRazaoSocial().trim().equals("CONSUMIDOR FINAL")){
            y += row_height;
            g.drawText(getLinha("Consumidor não identificado", 56, "C"), x, y, fontLegend);
        } else {
            campo1 = nf.getDestRazaoSocial() + " - CPF: " + nf.getDestCNPJ();
            String newStr  = campo1.replaceAll("(.{55})", "$1|");
            String[] cliente = newStr.split("\\|");
            for(int i=0;i<cliente.length;i++) {
                y += row_height;
                g.drawText(getLinha(cliente[i], 55, "C"), x, y, fontLegend);
            }
            campo1 = nf.getDestEndereco() + ". CEP:" + nf.getDestCEP();
            newStr  = campo1.replaceAll("(.{55})", "$1|");
            cliente = newStr.split("\\|");

            for(int i=0;i<cliente.length;i++) {
                y += row_height;
                g.drawText(getLinha(cliente[i], 55, "C"), x, y, fontLegend);
            }
            campo1 = nf.getDestCidade() + "-" + nf.getDestUF();
            newStr  = campo1.replaceAll("(.{55})", "$1|");
            cliente = newStr.split("\\|");
            for(int i=0;i<cliente.length;i++) {
                y += row_height;
                g.drawText(getLinha(cliente[i], 55, "C"), x, y, fontLegend);
            }
        }
        //
        // QR CODE
        //
        QRCodeWriter writer = new QRCodeWriter();
        try {
            BitMatrix bitMatrix = writer.encode(nf.getQrCode(), BarcodeFormat.QR_CODE, 384, 384);
            int width = bitMatrix.getWidth();
            int height = bitMatrix.getHeight();
            Bitmap bmp = Bitmap.createBitmap(width, height, Bitmap.Config.RGB_565);
            for (int i = 0; i < width; i++) {
                for (int j = 0; j < height; j++) {
                    bmp.setPixel(i, j, bitMatrix.get(i, j) ? Color.BLACK : Color.WHITE);
                }
            }
            g.drawBitmap(bmp, 90, y, p);
        } catch (WriterException e) {
            e.printStackTrace();
        }

        x = 10;
        y += 400;
        campo1 = "Protocolo de Autorização: " + nf.getProtocolo();
        g.drawText(getLinha(campo1, 55, "C"), x, y, fontLegend);
        y += row_height;
        campo1 = nf.getDataHoraAutorizacao();
        g.drawText(getLinha(campo1, 55, "C"), x, y, fontLegend);
        y += row_height;
        campo1 = "INFORMAÇÃO ADICIONAL";
        g.drawText(getLinha(campo1, 55, "C"), x, y, fontLegendBold);

        campo1 = "Inf. Contribuinte: " + nf.getInfCpl();
        String newStr  = campo1.replaceAll("(.{55})", "$1|");
        String[] info = newStr.split("\\|");

        for(int i=0;i<info.length;i++) {
            y += row_height;
            g.drawText(getLinha(info[i], 55, "C"), x, y, fontLegend);
        }

        Bitmap BitmapReturn = Bitmap.createBitmap(BitmapDanfe.getWidth(), h1, Bitmap.Config.RGB_565);
        Canvas g3 = new Canvas(BitmapReturn);

        g3.drawBitmap(BitmapDanfe, 0, 0, p);
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
				Toast.makeText(getReactApplicationContext(),
						"device.getName()", Toast.LENGTH_SHORT).show();
				if ("MPT-III".equals(device.getName()) || "Leopardo Pro Max-".equals(device.getName())) {
					mac = device.getAddress();
				}
			}
			if (mac == null) {
				Toast.makeText(getReactApplicationContext(),
						"Nao foi encontrada MPT-III ou Leopardo Pro Max-\n\nFaça o pareamento com o disposivo e tente novamente.",
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
