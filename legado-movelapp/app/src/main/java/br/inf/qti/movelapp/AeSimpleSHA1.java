package br.inf.qti.movelapp;

/**
 * Created by flavio on 13/06/2014.
 */
import org.apache.commons.codec.digest.DigestUtils;

import java.io.UnsupportedEncodingException;
import java.security.NoSuchAlgorithmException;


public class AeSimpleSHA1 {

    public static String convertToHex(byte[] data) {
        StringBuilder buf = new StringBuilder();
        for (byte b : data) {
            int halfbyte = (b >>> 4) & 0x0F;
            int two_halfs = 0;
            do {
                buf.append((0 <= halfbyte) && (halfbyte <= 9) ? (char) ('0' + halfbyte) : (char) ('a' + (halfbyte - 10)));
                halfbyte = b & 0x0F;
            } while (two_halfs++ < 1);
        }
        return buf.toString();
    }

    public static String MySQLPassword(String text) throws NoSuchAlgorithmException, UnsupportedEncodingException {
        byte[] utf8 = text.getBytes("UTF-8");
        byte[] test = DigestUtils.sha(DigestUtils.sha(utf8));
        //return "*" + convertToHex(test).toUpperCase();
        AeSimpleSHA1 x = new AeSimpleSHA1();
        return "*" + x.convertToHex(test).toUpperCase();
    }
}