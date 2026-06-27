<?php

namespace App\Domain\Seguranca;

/**
 * TOTP (RFC 6238) nativo — sem dependência externa. Compatível com Google
 * Authenticator / Authy / 1Password (SHA1, 6 dígitos, passo de 30s).
 *
 * Mantido pequeno e auto-contido: gera segredo em base32, monta a otpauth:// URI
 * (para QR Code) e verifica o código com tolerância de ±1 janela (clock skew).
 */
class Totp
{
    private const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // base32 (RFC 4648)

    private const DIGITOS = 6;

    private const PASSO = 30;

    /** Gera um segredo base32 aleatório (160 bits = 32 chars base32). */
    public function gerarSecret(int $bytes = 20): string
    {
        $random = random_bytes($bytes);

        return $this->base32Encode($random);
    }

    /** URI otpauth:// para montar o QR Code no app autenticador. */
    public function uri(string $secret, string $conta, string $emissor): string
    {
        $label = rawurlencode($emissor.':'.$conta);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $emissor,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITOS,
            'period' => self::PASSO,
        ]);

        return "otpauth://totp/{$label}?{$params}";
    }

    /** Verifica o código com tolerância de ±$janela passos (clock skew). */
    public function verificar(string $secret, string $codigo, int $janela = 1): bool
    {
        $codigo = preg_replace('/\D/', '', $codigo);
        if (strlen($codigo) !== self::DIGITOS) {
            return false;
        }

        $contador = (int) floor(time() / self::PASSO);
        for ($i = -$janela; $i <= $janela; $i++) {
            if (hash_equals($this->em($secret, $contador + $i), $codigo)) {
                return true;
            }
        }

        return false;
    }

    /** Código TOTP para um contador de tempo específico. */
    public function em(string $secret, int $contador): string
    {
        $chave = $this->base32Decode($secret);
        $bin = pack('N*', 0).pack('N*', $contador); // 64-bit big-endian
        $hash = hash_hmac('sha1', $bin, $chave, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $trunc = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($trunc % (10 ** self::DIGITOS)), self::DIGITOS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $b) {
            $bits .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALFABETO[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $out;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
        $bits = '';
        foreach (str_split($secret) as $c) {
            $bits .= str_pad(decbin(strpos(self::ALFABETO, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }

        return $out;
    }
}
