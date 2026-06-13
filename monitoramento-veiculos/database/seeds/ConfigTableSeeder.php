<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Config;


class ConfigTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
		Config::create([
                    'id' => '1',
                    'urlsistemaweb' => 'http://127.0.0.1/ctrl2/',
		]);
    }
}
