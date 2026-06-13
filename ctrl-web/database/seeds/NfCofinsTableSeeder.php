<?php

use Illuminate\Database\Seeder;
use App\Nfcofins;

class NfCofinsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->checaIds(['codigo' => '01', 'descricao'=>'Operação tributável']);
        $this->checaIds(['codigo' => '02', 'descricao'=>'Operação tributável']);
        $this->checaIds(['codigo' => '03', 'descricao'=>'Operação tributável']);
        $this->checaIds(['codigo' => '04', 'descricao'=>'Operação tributável - Tributação monofásica']);
        $this->checaIds(['codigo' => '05', 'descricao'=>'Operação tributável - Substituição tributária']);
        $this->checaIds(['codigo' => '06', 'descricao'=>'Operação tributável - Alíquota zero']);
        $this->checaIds(['codigo' => '07', 'descricao'=>'Operação isenta da contribuição']);
        $this->checaIds(['codigo' => '08', 'descricao'=>'Operação sem incidência da contribuição']);
        $this->checaIds(['codigo' => '09', 'descricao'=>'Operação com suspensão da contribuição']);
        $this->checaIds(['codigo' => '99', 'descricao'=>'Outras Operações']);
        
    }
    
    function checaIds($attr)
    {
        $row = Nfcofins::where('codigo',$attr['codigo'])->get();
        $cofins = new Nfcofins();
        if (!empty($row)) {
            $cofins->exists = false;
            return Nfcofins::create($attr);
        }
    }
}
