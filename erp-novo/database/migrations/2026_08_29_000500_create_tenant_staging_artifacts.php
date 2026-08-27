<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F1-09: dados temporarios somente em staging catalogado, com TTL e owner. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_staging_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_account_id')->constrained('tenant_accounts')->restrictOnDelete();
            $table->string('owner', 120);
            $table->string('purpose', 120);
            $table->json('payload');
            $table->timestamp('expires_at')->index();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_account_id', 'owner']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_staging_artifacts');
    }
};
