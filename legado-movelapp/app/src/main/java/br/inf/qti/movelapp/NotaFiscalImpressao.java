package br.inf.qti.movelapp;

import android.app.Activity;
import android.bluetooth.BluetoothDevice;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.DashPathEffect;
import android.graphics.Paint;
import android.graphics.Typeface;

import com.google.zxing.BarcodeFormat;
import com.google.zxing.MultiFormatWriter;
import com.google.zxing.WriterException;
import com.google.zxing.common.BitMatrix;
import com.google.zxing.qrcode.QRCodeWriter;

import java.io.IOException;
import java.text.DecimalFormat;
import java.util.Arrays;
import java.util.Formatter;
import java.util.LinkedList;
import java.util.List;
import java.util.Locale;
import java.util.Set;

/**
 * Created by flavio on 20/09/2014.
 */
public class NotaFiscalImpressao {
    private static boolean connected = false;
    private Activity atividade;
    public NotaFiscal NF;
    //Para impressão em Bitmap
    Bitmap mBitmap = null;
    static Bitmap mBitmapLogo = null;
    Bluetooth mBth = new Bluetooth();
    Integer mDensity = 8;
    String alerta;

    public NotaFiscalImpressao(NotaFiscal nf, Activity atividade) {
        this.NF = nf;
        this.atividade = atividade;
    }


    public String imprimirNotaFiscalBmp() {

        try {
            try {
                mBitmapLogo = BitmapFactory.decodeStream(atividade.getApplicationContext().getAssets().open("logo_empresa.png"));
            } catch (IOException e) {
                e.printStackTrace();
            }

            mBitmap = createNotaFiscalBmp();


            if (!checkBth())
                return "9" + alerta;
            if (mBitmap != null) {
                ESCP.ImageToEsc(mBitmap, mBth.Ostream, 8, mDensity);
            }
            closeBth();
            return "2";

        } catch (Exception e) {
            return "9Erro impressão NF BMP: " + e.getMessage();
        }
    }

    public Bitmap gerarNotaFiscalBmp() {

        try {
            try {
                mBitmapLogo = BitmapFactory.decodeStream(atividade.getApplicationContext().getAssets().open("logo_empresa.png"));
            } catch (IOException e) {
                e.printStackTrace();
            }

            mBitmap = createNotaFiscalBmp();

            return mBitmap;

        } catch (Exception e) {
            return null;
        }
    }

    public Bitmap gerarDuplicataBmp() {

        try {
            try {
                mBitmapLogo = BitmapFactory.decodeStream(atividade.getApplicationContext().getAssets().open("logo_empresa.png"));
            } catch (IOException e) {
                e.printStackTrace();
            }

            mBitmap = createDuplicataBmp();

            return mBitmap;

        } catch (Exception e) {
            return null;
        }
    }

    public Bitmap gerarNotaFiscalCBmp() {

        try {
            try {
                mBitmapLogo = BitmapFactory.decodeStream(atividade.getApplicationContext().getAssets().open("logo_empresa.png"));
            } catch (IOException e) {
                e.printStackTrace();
            }

            mBitmap = createNotaFiscalCBmp();

            return mBitmap;

        } catch (Exception e) {
            return null;
        }
    }


    public Bitmap createNotaFiscalBmp()
    {
        int x=0, y=0, w=576, h=0;
        int size_text=32, size_legend=16, size_chave=22, row_width=55, row_height=20;

        //Tamanho do Bitmap
        //-----------------
        h = 0; //as chaves de acesso + a parte onde descreve Danfe Simplificado
            NotaFiscal nf = this.NF;
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
            //dados adicionais
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
        fontLegend.setTypeface(Typeface.createFromAsset(atividade.getApplicationContext().getAssets(), "fonts/unispace rg.ttf"));
        fontLegend.setTextSize(size_legend);


        Paint fontLegendBold = new Paint(Color.BLACK);
        fontLegendBold.setTypeface(Typeface.createFromAsset(atividade.getApplicationContext().getAssets(), "fonts/unispace bd.ttf"));
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
            h += 3 * row_height;
            witdthProd = 32;
            h += (nf.getInformacoesAdicionais().length() / witdthProd) * row_height;
            witdthProd = 24;


            //Comprovante de recebimento
            //==========================================================================
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
            //String valor = String.valueOf(f1.format("%1.2f", Utils.round(nf.getValorProdutos(), 2))).replace(".", ",");
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
            //Chave de acesso do Comprovante de recebimento
            // =======================================================
            String chave = nf.getChaveAcesso();
            //y+=row_height*1;
            x = 10;
            y += 20;
            BarCode.drawBarCode128C(g, chave, x, y, w, 80);
            y += 105;
            g.drawText(chave, x, y, fontChave);
            y += size_chave;
            p.setPathEffect(new DashPathEffect(new float[]{10, 20}, 0));
            g.drawLine(0, y, w, y, p);
            p.setPathEffect(null);
            y += row_height;
            //DESCRIÇÃO DO DANFE
            //==========================================================================
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
            //Chave de acesso do Danfe
            //==========================================================================
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
            //Operação/Datas
            //==========================================================================
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
            int witdthField1 = witdthProd, witdthField2 = 4, witdthField3 = 3, witdthField4 = 7, witdthField5 = 7, witdthField6 = 9, witdthField7 = 4;
            y += row_height;
            //Retangulo
            int y_1 = y;
            //Cabeçalho
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

            //Imposto
            //==========================================================================
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
            //Valores
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

            //Outros Valores
            //==========================================================================
            witdthField1 = 8;
            witdthField2 = 8;
            witdthField3 = 10;
            witdthField4 = 12;
            witdthField5 = 8;
            witdthField6 = 11;
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
            //Valores
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

            //INFORMAÇÔES ADICIONAIS
            //==========================================================================
            campo1 = "DADOS ADICIONAIS";
            x = 10;
            y += row_height * 2;
            g.drawText(campo1, x, y, fontLegendBold);

            witdthField1 = 32;
            witdthField2 = 26;
            y += 5;
            //Retangulo
            y_1 = y;
            x = 5;
            //Descrição de inf adicional maior que o tamanho do campo
            //=================================================
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
                //==================================================
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
            //Dados adicionais
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

    }


    public Bitmap createNotaFiscalCBmp()
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
        h = 1400 + (row_height * nf.getItens().size());
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
        fontLegend.setTypeface(Typeface.createFromAsset(atividade.getApplicationContext().getAssets(), "fonts/unispace rg.ttf"));
        fontLegend.setTextSize(size_legend);


        Paint fontLegendBold = new Paint(Color.BLACK);
        fontLegendBold.setTypeface(Typeface.createFromAsset(atividade.getApplicationContext().getAssets(), "fonts/unispace bd.ttf"));
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
            BitMatrix bitMatrix = writer.encode(nf.getQrCode(), BarcodeFormat.QR_CODE, 512, 512);
            int width = bitMatrix.getWidth();
            int height = bitMatrix.getHeight();
            Bitmap bmp = Bitmap.createBitmap(width, height, Bitmap.Config.RGB_565);
            for (int i = 0; i < width; i++) {
                for (int j = 0; j < height; j++) {
                    bmp.setPixel(i, j, bitMatrix.get(i, j) ? Color.BLACK : Color.WHITE);
                }
            }
            g.drawBitmap(bmp, 20, y, p);
        } catch (WriterException e) {
            e.printStackTrace();
        }

        x = 10;
        y += 520;
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

    public String[][] itensArray(LinkedList<NotafiscalItem> itens){
        String[][] ret = new String[itens.size()][5];
        for(int i=0; i < itens.size(); i++){
            NotafiscalItem item = itens.get(i);
            ret[i][0] = item.getDescricao();
            ret[i][1] = item.getUnidade();
            ret[i][2] = String.format(new Locale("pt", "BR"), "%1$,.0f",item.getQuantidade()).replace(".",",");
            ret[i][3] = String.format(new Locale("pt", "BR"), "%1$,.2f",item.getPreco()).replace(".",",");
            ret[i][4] = String.format(new Locale("pt", "BR"), "%1$,.2f",item.getValorTotal()).replace(".",",");
        }
        return ret;
    }

    public String[] splitInParts(String s, int partLength)
    {
        int len = s.length();

        // Number of parts
        int nparts = (len + partLength - 1) / partLength;
        String parts[] = new String[nparts];

        // Break into parts
        int offset= 0;
        int i = 0;
        while (i < nparts)
        {
            parts[i] = s.substring(offset, Math.min(offset + partLength, len));
            offset += partLength;
            i++;
        }

        return parts;
    }

    public boolean closeBth()
    {
        if (mBth.isConnected())
        {
            return mBth.Close();
        }
        return false;
    }

    public boolean checkBth()
    {
        if (!mBth.isConnected())
        {
            if (!mBth.Enable())
            {
                alerta = "Nao foi possivel habilitar bluetooth, tente habilitar manualmente.";
                return false;
            }
            String mac=null;
            Set<BluetoothDevice> devices = mBth.GetBondedDevices();
            for (BluetoothDevice device : devices)
            {
                if ("MPT-III".equals(device.getName()))
                {
                    mac = device.getAddress();
                }
            }
            if (mac==null)
            {
                alerta = "Nao foi encontrada impressora MPT-III\n\nFaça o pareamento com o disposivo e tente novamente.";
                return false;
            }
            if (!mBth.Open(mac))
            {
                alerta = "Nao foi possivel conectar ao dispositivo ["+mac+"]\n\nLigue ou conecte o dispositivo e tente novamente.";
                return false;
            }
        }
        return true;
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

    public Bitmap createDuplicataBmp()
    {
        int x=0, y=0, w=576, h=0;
        int size_text=32, size_legend=16, size_chave=22, row_width=55, row_height=20;

        //Tamanho do Bitmap
        //-----------------
        h = 0; //as chaves de acesso + a parte onde descreve Danfe Simplificado
        NotaFiscal nf = this.NF;
        h+=260;
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
        fontLegend.setTypeface(Typeface.createFromAsset(atividade.getApplicationContext().getAssets(), "fonts/unispace rg.ttf"));
        fontLegend.setTextSize(size_legend);


        Paint fontLegendBold = new Paint(Color.BLACK);
        fontLegendBold.setTypeface(Typeface.createFromAsset(atividade.getApplicationContext().getAssets(), "fonts/unispace bd.ttf"));
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
        h = 50; //as chaves de acesso + a parte onde descreve Danfe Simplificado

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
            campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f", item.getQuantidade()).replace(".", ",");
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
        linha_adicional = 0;
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




        Bitmap BitmapReturn = Bitmap.createBitmap(BitmapDanfe.getWidth(), h1, Bitmap.Config.RGB_565);
        Canvas g3 = new Canvas(BitmapReturn);

        g3.drawBitmap(BitmapDanfe, 0, 0, p);
        return BitmapReturn;

    }

}
