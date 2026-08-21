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

import com.lvrenyang.io.BTPrinting;
import com.lvrenyang.io.IOCallBack;
import com.lvrenyang.io.Pos;

public class PrintDuplicataModule extends ReactContextBaseJavaModule {
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

	PrintDuplicataModule(ReactApplicationContext context) {
		super(context);
		reactContext = context;
	}

	@Override
	public String getName() {
		return "PrintDuplicata";
	}

	@Override
	public Map<String, Object> getConstants() {
		final Map<String, Object> constants = new HashMap<>();
		constants.put(DURATION_SHORT_KEY, Toast.LENGTH_SHORT);
		constants.put(DURATION_LONG_KEY, Toast.LENGTH_LONG);
		return constants;
	}

	@ReactMethod
	public void printDuplicata(final ReadableMap pedido, int duration) {
		// Toast.makeText(getReactApplicationContext(), message, duration).show();
		try {
			this.NF = this.gerarPedido(Utils.convertMapToJson(pedido));
			if(this.NF == null){
				Toast.makeText(getReactApplicationContext(), "NF is null", Toast.LENGTH_LONG).show();
				return;
			}
			mBitmap = createDuplicata();
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
				} else {
					Toast.makeText(getReactApplicationContext(), "Bitmap is null", Toast.LENGTH_LONG).show();
				}
			//	closeBth();
			//} 
		} catch (Exception e) {
			Toast.makeText(getReactApplicationContext(), e.getMessage(), duration).show();
		}
	}

	private NotaFiscal gerarPedido(JSONObject c) {
		try {
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

			return nf;
		} catch (Exception e) {
			e.printStackTrace();
			Toast.makeText(getReactApplicationContext(), e.getMessage(), Toast.LENGTH_LONG).show();
			return null;
		} 
	}

	public Bitmap createDuplicata() {
		try {
			int x=0, y=0, w=576, h=0;
			int size_text=32, size_legend=16, size_chave=22, row_width=55, row_height=20;
	
			//Tamanho do Bitmap
			//-----------------
			h = 0; //as chaves de acesso + a parte onde descreve Danfe Simplificado
			NotaFiscal nf = this.NF;
			h+=200;
			h += 4 * row_height; //Impostos
			h += (2 + (nf.getParcelas().size())) * row_height; // dados financeiros
			h += (6 + (nf.getDestRazaoSocial().length() > row_width ? 1 : 0)) * row_height; // dados do comprovante de recebimento
			h += (6 + (nf.getEmitRazaoSocial().length() > (row_width) ? 1 : 0)) * row_height; //Emitente
			h += ((nf.getEmitEndereco().length() > (row_width) ? 1 : 0)) * row_height; //Endereco
			h += ((12 + Utils.formatCEP(nf.getEmitCEP()).length() + nf.getEmitCidade().length() + nf.getEmitUF().length() + Utils.formatFone(nf.getEmitTelefone()).length() > (row_width) ? 1 : 0)) * row_height; //Outros dados emitente
			h += (6 + (nf.getDestRazaoSocial().length() > (row_width) ? 1 : 0)) * row_height; //Destinatário
			h += ((nf.getDestEndereco().length() > (row_width) ? 1 : 0)) * row_height; //Endereco
			h += ((12 + Utils.formatCEP(nf.getDestCEP()).length() + nf.getDestCidade().length() + nf.getDestUF().length() + Utils.formatFone(nf.getDestTelefone()).length() > (row_width) ? 1 : 0)) * row_height; //Outros dados emitente
			//produtos
			h += 2 * row_height;
			int witdthProd = 24;
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
			h = 150; //as chaves de acesso + a parte onde descreve Danfe Simplificado
	
			//h += 1 * row_height; //Impostos
			h += (2 + (nf.getParcelas().size())) * row_height; // dados financeiros
			h += (1 + (nf.getDestRazaoSocial().length() > row_width ? 1 : 0)) * row_height; // dados do comprovante de recebimento
			//h += (3 + (nf.getOperacao().length() > (row_width - 1 - 26) ? 1 : 0)) * row_height; //Natureza Operação
			h += (1 + (nf.getEmitRazaoSocial().length() > (row_width) ? 1 : 0)) * row_height; //Emitente
			h += ((nf.getEmitEndereco().length() > (row_width) ? 1 : 0)) * row_height; //Endereco
			h += ((12 + Utils.formatCEP(nf.getEmitCEP()).length() + nf.getEmitCidade().length() + nf.getEmitUF().length() + Utils.formatFone(nf.getEmitTelefone()).length() > (row_width) ? 1 : 0)) * row_height; //Outros dados emitente
			h += (6 + (nf.getDestRazaoSocial().length() > (row_width) ? 1 : 0)) * row_height; //Destinatário
			h += ((nf.getDestEndereco().length() > (row_width) ? 1 : 0)) * row_height; //Endereco
			h += ((12 + Utils.formatCEP(nf.getDestCEP()).length() + nf.getDestCidade().length() + nf.getDestUF().length() + Utils.formatFone(nf.getDestTelefone()).length() > (row_width) ? 1 : 0)) * row_height; //Outros dados emitente
			//produtos
			h += 2 * row_height;
			witdthProd = 27;
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
			/*
			h += 3 * row_height;
			witdthProd = 32;
			h += (nf.getInformacoesAdicionais().length() / witdthProd) * row_height;
			witdthProd = 24;
			*/
			String campo1 = "";
			String campo2 = "";
			String campo3 = "";
			String campo4 = "";
			String campo5 = "";
			String campo6 = "";
			String campo7 = "";
			String campo8 = "";
			String campo9 = "";
			int linha_adicional = 0;
			Formatter f1 = new Formatter();
			DecimalFormat df = new DecimalFormat("000000");
			String valor = "";
			char[] chars;
	
			//DESCRIÇÃO DO DANFE
			//==========================================================================
			campo1 = "          DUPLICATA REFERENTE A PEDIDO " + df.format(nf.getCodigoSeq());
			campo2 = "               EMISSÃO: " + nf.getDataEmissaoTexto() + " " + nf.getHoraSaidaTexto();;
			y += row_height;
			x = 10;
			g.drawText(campo1, x, y, fontLegendBold);
			y += row_height;
			g.drawText(campo2, x, y, fontLegend);
			//Emitente
			//==========================================================================
			campo1 = "EMITENTE";
			campo2 = nf.getEmitRazaoSocial();
			campo3 = "";
			campo4 = nf.getEmitEndereco();
			campo5 = "";
			campo6 = "CEP " + Utils.formatCEP(nf.getEmitCEP()) + "  " + nf.getEmitCidade() + "-" + nf.getEmitUF() + " Tel " + Utils.formatFone(nf.getEmitTelefone());
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
			//Destinatário
			//==========================================================================
			campo1 = "DESTINATÁRIO";
			campo2 = nf.getDestRazaoSocial();
			campo3 = "";
			campo4 = nf.getDestEndereco();
			campo5 = "";
			campo6 = "CEP " + Utils.formatCEP(nf.getDestCEP()) + "  " + nf.getDestCidade() + "-" + nf.getDestUF() + " Tel " + Utils.formatFone(nf.getDestTelefone());
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
			//Parcelas
			//==========================================================================
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
			//Produtos
			//==========================================================================
			double perc = w / row_width;
			int pos = 0, somapos = 0;
			//int witdthField1 = witdthProd + 2, witdthField2 = 4, witdthField3 = 3 + 2, witdthField4 = 7, witdthField5 = 8, witdthField6 = 9, witdthField7 = 4;
			int witdthField1 = witdthProd, witdthField2 = 4, witdthField3 = 3 + 2, witdthField4 = 7, witdthField5 = 8, witdthField6 = 9 + 2, witdthField7 = 4;
			y += row_height;
			//Retangulo
			int y_1 = y;
			//Cabeçalho
			pos = 0;
			somapos = 0;
			pos = (int) (Utils.round(witdthField1 * perc, 0));
			g.drawRect(somapos, y, pos, y + row_height + 5, p);
			somapos += pos;
			//pos = (int) (Utils.round(witdthField2 * perc, 0));
			//g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
			//somapos += pos;
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
			//pos = (int) (Utils.round(witdthField7 * perc, 0));
			//g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
	
			x = 5;
			y += row_height;
	
			campo1 = "Produto";
			chars = new char[witdthField1 - campo1.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars);
			//campo2 = "CST";
			//chars = new char[witdthField2 - campo2.length()];
			//Arrays.fill(chars, ' ');
			//campo1 = campo1 + campo2 + new String(chars);
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
			//campo2 = " Al";
			//chars = new char[witdthField7 - campo2.length()];
			//Arrays.fill(chars, ' ');
			//campo1 = campo1 + campo2 + new String(chars);
			g.drawText(campo1, x, y, fontLegend);
			//Itens
			x = 5;
			y += 5;
			for (int i = 0; i < nf.getItens().size(); i++) {
				y += row_height;
				NotafiscalItem item = nf.getItens().get(i);
				pos = 0;
				somapos = 0;
				campo1 = item.getDescricao();
				//Descrição de produto maior que o tamanho do campo
				//=================================================
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
				//==================================================
				chars = new char[witdthField1 - campo1.length()];
				Arrays.fill(chars, ' ');
				campo1 = campo1 + new String(chars);
			   // campo2 = "";//item.getCst();
				//chars = new char[witdthField2 - campo2.length()];
				//Arrays.fill(chars, ' ');
				//campo1 = campo1 + campo2 + new String(chars);
				campo2 = item.getUnidade();
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
				//campo2 = ""; // + String.valueOf(item.getAliq());
				//chars = new char[witdthField7 - campo2.length()];
				//Arrays.fill(chars, ' ');
				//campo1 = campo1 + campo2 + new String(chars);
				g.drawText(campo1, x, y, fontLegend);
				//Descrição de produto maior que o tamanho do campo
				//=================================================
				for (int k = 1; k < prods.length; k++) {
					y += row_height;
					g.drawText(prods[k], x, y, fontLegend);
				}
				//=================================================
			}
			g.drawRect(0, y_1, w, y + 10, p);
			//Retangulo por campo
			pos = (int) (Utils.round(witdthField1 * perc, 0));
			g.drawRect(somapos, y_1, pos, y + 10, p);
			somapos += pos;
			//pos = (int) (Utils.round(witdthField2 * perc, 0));
			//g.drawRect(somapos, y_1, pos + somapos, y + 10, p);
			//somapos += pos;
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
			//pos = (int) (Utils.round(witdthField7 * perc, 0));
			//g.drawRect(somapos, y_1, pos + somapos, y + 10, p);
	
			//Imposto
			//==========================================================================
			campo1 = "TOTAIS";
			x = 10;
			y += row_height * 2;
			g.drawText(campo1, x, y, fontLegendBold);
	
			witdthField1 = 19;
			witdthField2 = 19;
			witdthField3 = 19;
			y += 5;
			//Retangulo
			y_1 = y;
			//Cabeçalho
			somapos = 0;
			pos = (int) (Utils.round(witdthField1 * perc, 0)) + 10;
			g.drawRect(somapos, y, pos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField2 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField3 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
	
			x = 5;
			y += row_height;
	
			campo1 = "Valor Produtos";
			chars = new char[witdthField1 - campo1.length()];
			Arrays.fill(chars, ' ');
			campo1 = new String(chars) + campo1;
			campo2 = "valor Desconto";
			chars = new char[witdthField2 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			campo2 = "Valor Líquido";
			chars = new char[witdthField3 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			g.drawText(campo1, x, y, fontLegend);
	
			y += 5;
			//Valores
			somapos = 0;
			pos = (int) (Utils.round(witdthField1 * perc, 0)) + 10;
			g.drawRect(somapos, y, pos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField2 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
			somapos += pos;
			pos = (int) (Utils.round(witdthField3 * perc, 0));
			g.drawRect(somapos, y, pos + somapos, y + row_height + 5, p);
	
			y += row_height;
			campo1 = String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getValorProdutos()).replace(".", ",");
			chars = new char[witdthField1 - campo1.length()];
			Arrays.fill(chars, ' ');
			campo1 = new String(chars) + campo1;
	
			campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getvDesconto()).replace(".", ",");
			chars = new char[witdthField2 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
	
			campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f", nf.getvTotalNF()).replace(".", ",");
			chars = new char[witdthField3 - campo2.length()];
			Arrays.fill(chars, ' ');
			campo1 = campo1 + new String(chars) + campo2;
			g.drawText(campo1, x, y, fontLegend);
			//Comprovante de recebimento
			y+=15;
			//==========================================================================
			campo1 = "RECEBEMOS DE " + nf.getEmitRazaoSocial();
			campo2 = "";
			campo3 = "";
			campo4 = "Identificação/Assinatura";
			campo5 = "____/____/____";
			campo6 = "_______________________________";
			campo7 = nf.getDestRazaoSocial();
			campo8 = "";
			campo9 = "ENTREGADOR";
			linha_adicional = 0;
			if (campo7.length() > row_width) {
				linha_adicional = 1;
				campo8 = campo7.substring(row_width, campo7.length());
				campo7 = campo7.substring(0, row_width);
			}
			g.drawRect(0, y, w, y + (row_height * (12 + linha_adicional)) + 10, p);
			if (campo1.length() > (row_width - 12)) {
				campo2 = campo1.substring((row_width - 12), campo1.length());
				if (campo2.length() > (row_width - 12)) {
					campo3 = campo2.substring((row_width - 12), campo1.length());
					campo2 = campo2.substring(0, (row_width - 12));
				}
				campo1 = campo1.substring(0, (row_width - 12));
			}
			//f1 = new Formatter();
			//String valor = String.valueOf(f1.format("%1.2f", Utils.round(nf.getValorProdutos(), 2))).replace(".", ",");
			valor = String.valueOf(f1.format("%1.2f", Utils.round(nf.getvTotalNF(), 2))).replace(".", ",");
			chars = new char[(row_width - 15) - campo1.length()];
			Arrays.fill(chars, ' ');
			//DecimalFormat df = new DecimalFormat("000000");
			campo1 = campo1 + new String(chars) + "  Pedido: " + df.format(nf.getCodigoSeq());
			y += row_height;
			x = 10;
			g.drawText(campo1, x, y, fontLegend);
			chars = new char[(row_width - 6) - campo2.length()];
			Arrays.fill(chars, ' ');
			chars = new char[row_width - campo3.length() - 7 - valor.length()];
			Arrays.fill(chars, ' ');
			campo3 = campo3 + new String(chars) + " VALOR: " + valor;
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

			chars = new char[row_width - campo6.length()];
			Arrays.fill(chars, ' ');
			campo6 = new String(chars) + campo6;
			y += row_height * 4;
			g.drawText(campo6, x, y, fontLegend);
			chars = new char[row_width - campo9.length()];
			Arrays.fill(chars, ' ');
			campo9 = new String(chars) + campo9;
			y += row_height;
			g.drawText(campo9, x, y, fontLegend);
	
	
	
	
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
