<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\User;

class UserTableSeeder extends Seeder {

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        User::create([
            'id' => '1',
            'name' => 'Flávio Ono',
            'email' => 'flavio',
            'password' => bcrypt('1234'),
            'empresa_id' => '1',
            'ativo' => '1',
        ]);


        User::create([
            'id' => '2',
            'name' => 'Jeferson',
            'email' => 'jeferson',
            'password' => bcrypt('1234'),
            'empresa_id' => '1',
            'ativo' => '1',
        ]);

        User::create([
            'id' => '3',
            'name' => 'Wanderlei',
            'email' => 'wanderlei',
            'password' => bcrypt('1234'),
            'empresa_id' => '1',
            'ativo' => '1',
        ]);

        User::create([
            'id' => '4',
            'name' => 'Lucas Veque',
            'email' => 'lucas',
            'password' => bcrypt('1234'),
            'empresa_id' => '1',
            'ativo' => '1',
        ]);

        DB::table('empresa_user')->insert(['empresa_id' => '1', 'user_id' => '1']);
        DB::table('empresa_user')->insert(['empresa_id' => '1', 'user_id' => '2']);
        DB::table('empresa_user')->insert(['empresa_id' => '1', 'user_id' => '3']);
        DB::table('empresa_user')->insert(['empresa_id' => '1', 'user_id' => '4']);
    }

}
