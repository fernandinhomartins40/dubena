<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AlterSequences extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // $tablesequences = DB::select(DB::raw("select sequence_name from user_sequences"));
        // foreach ($tablesequences as $table) {
        //     if ($table->sequence_name !== 'CIDADES_ID_SEQ' and $table->sequence_name !== 'ESTADOS_ID_SEQ') {
        //         $tablename = str_replace('_ID_SEQ', '', $table->sequence_name);
        //         $tablename = str_replace('_ID_SE', '', $tablename);
        //         $tablename = str_replace('_ID_S', '', $tablename);
        //         $tablename = str_replace('_ID_', '', $tablename);
        //         $tablename = str_replace('_ID', '', $tablename);
        //         $tablename = str_replace('_I', '', $tablename);
        //         if($tablename == 'OAUTH_PERSONAL_ACCESS_CLIENTS_'){
        //             $tablename = 'OAUTH_PERSONAL_ACCESS_CLIENTS';
        //         }
        //         $lastid = DB::select(DB::raw("select id from " . $tablename . " where rowid = (select max(rowid) from " . $tablename . ")"));
        //         $sequenceatual = DB::select(DB::raw('select ' . $table->sequence_name . '.nextval from dual'));

        //         if (isset($lastid[0]->id) and $lastid[0]->id > 0) {
        //             $dif = $lastid[0]->id - $sequenceatual[0]->nextval + 1;
        //             if ($dif === 0) {
        //                 DB::statement('ALTER SEQUENCE ' . $table->sequence_name . ' INCREMENT BY ' . ($dif + 1 ));
        //             } else {
        //                 DB::statement('ALTER SEQUENCE ' . $table->sequence_name . ' INCREMENT BY ' . $dif);
        //             }
        //             $sequenceatual = DB::select(DB::raw('select ' . $table->sequence_name . '.nextval from dual'));
        //             DB::statement('ALTER SEQUENCE ' . $table->sequence_name . ' INCREMENT BY 1');
        //         }
        //     }
        // }
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
