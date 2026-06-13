<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AlterSequencesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
    	$tablesequences = DB::select(DB::raw("select sequence_name from user_sequences"));
    	foreach ($tablesequences as $table) {
    		if ($table->sequence_name !== 'CIDADES_ID_SEQ' and $table->sequence_name !== 'ESTADOS_ID_SEQ') {
    			$tablename = str_replace('_ID_SEQ', '', $table->sequence_name);
    			$tablename = str_replace('_ID_SE', '', $tablename);
    			$tablename = str_replace('_ID_S', '', $tablename);
    			$tablename = str_replace('_ID_', '', $tablename);
    			$tablename = str_replace('_ID', '', $tablename);

    			if($tablename == 'OAUTH_PERSONAL_ACCESS_CLIENTS_')
    				$tablename = 'OAUTH_PERSONAL_ACCESS_CLIENTS';

    			if($tablename == 'CHEQUERECEBIDOENCONTROCONTAS_I')
    				$tablename = 'CHEQUERECEBIDOENCONTROCONTAS';

    			if($tablename == 'CHEQUERECEBIDOTRANSFERENCIAS_I')
    				$tablename = 'CHEQUERECEBIDOTRANSFERENCIAS';
    			
    			if($tablename !== 'MIGRATIONS'){
    				$lastid = DB::select(DB::raw("select id from " . $tablename . " where id = (select max(id) from " . $tablename . ")"));
    				$sequenceatual = DB::select(DB::raw('select ' . $table->sequence_name . '.nextval from dual'));

    				if (isset($lastid[0]->id) and $lastid[0]->id > 0) {
    					$dif = $lastid[0]->id - $sequenceatual[0]->nextval + 1;
    					if ($dif === 0) {
    						DB::statement('ALTER SEQUENCE ' . $table->sequence_name . ' INCREMENT BY ' . ($dif + 1 ));
    					} else {
    						DB::statement('ALTER SEQUENCE ' . $table->sequence_name . ' INCREMENT BY ' . $dif);
    					}
    					$sequenceatual = DB::select(DB::raw('select ' . $table->sequence_name . '.nextval from dual'));
    					DB::statement('ALTER SEQUENCE ' . $table->sequence_name . ' INCREMENT BY 1');
    				}
    			}
    		}
    	}
    }
}
