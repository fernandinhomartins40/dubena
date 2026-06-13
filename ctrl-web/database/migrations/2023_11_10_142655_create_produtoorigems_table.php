<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProdutoorigemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('produtoorigems', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger("produto_id");
            $table->tinyInteger("indimport");
            $table->tinyInteger("cuforig");
            $table->decimal('porig', 7, 4)->default(0);
            $table->timestamps();

            $table->foreign("produto_id")->references("id")->on("produtos")->onDelete("cascade")->onUpdate("cascade");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('produtoorigems');
    }
}
