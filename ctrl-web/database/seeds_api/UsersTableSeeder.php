<?php

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            'name'              => 'dubena',
            'email'             => 'dubena@dubena.com',
            'password'          => app('hash')->make('1234'),
            'admin'             => true,
            'erpurl'            => 'localhost/ctrl2',
            'avaliacao'         => 2.5,
            'telefone'          => '99999',
            'erpempresa_id'     => 1
        ]);
    }
}
