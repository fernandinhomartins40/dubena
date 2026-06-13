    <?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChequeemitidofinanceiroencontrocontasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('chequeemitidoencontrocontas', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('chequeemitido_id')->nullable();
          $table->unsignedInteger('financeiroparcela_id');
          $table->decimal('valortotal', 8, 2);

          $table->timestamps();

          $table->foreign('chequeemitido_id', 'chqemit_encconta_fk')->references('id')->on('chequeemitidos')->onDelete('cascade');
          $table->foreign('financeiroparcela_id', 'cheqemit_encconta_parc_fk')->references('id')->on('financeiroparcelas');
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
