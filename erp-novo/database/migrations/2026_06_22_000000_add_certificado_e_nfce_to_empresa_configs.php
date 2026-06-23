<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C2 — Certificado digital A1 + token NFC-e (CSC) na config da empresa.
 *
 * O .pfx fica em storage PRIVADO (storage/app/private/certificados); aqui só
 * guardamos o caminho + metadados lidos do certificado (validade/CNPJ/titular)
 * e a senha do .pfx ENCRIPTADA. O CSC da NFC-e (id + token) também encriptado.
 * Nada de segredo em texto claro nem em `public/`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_configs', function (Blueprint $t) {
            // Certificado A1.
            $t->string('cert_path')->nullable();           // caminho no disco privado
            $t->text('cert_senha')->nullable();            // encriptada (cast 'encrypted')
            $t->string('cert_cnpj', 14)->nullable();       // CNPJ do titular (lido do cert)
            $t->string('cert_titular')->nullable();        // CN do titular
            $t->timestamp('cert_validade')->nullable();    // notAfter
            $t->timestamp('cert_uploaded_at')->nullable();

            // NFC-e (CSC — Código de Segurança do Contribuinte).
            $t->string('nfce_csc_id', 10)->nullable();
            $t->text('nfce_csc_token')->nullable();        // encriptado
        });
    }

    public function down(): void
    {
        Schema::table('empresa_configs', function (Blueprint $t) {
            $t->dropColumn([
                'cert_path', 'cert_senha', 'cert_cnpj', 'cert_titular',
                'cert_validade', 'cert_uploaded_at', 'nfce_csc_id', 'nfce_csc_token',
            ]);
        });
    }
};
