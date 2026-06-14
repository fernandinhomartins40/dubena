<?php
namespace Tests\Fluxo;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
class SmokeUnificacaoTest extends TestCase
{
    public function test_erp_e_monitora(){
        // ERP login (admin) + módulo
        \Artisan::call('db:seed', ['--class'=>'DeployAdminSeeder','--force'=>true]);
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->post('/handleLogin',['email'=>env('ADMIN_SEED_EMAIL','admin'),'password'=>env('ADMIN_SEED_PASSWORD','admin1234'),'ativo'=>1]);
        $cli=$this->get('/cliente');
        fwrite(STDERR,"\nERP /cliente = ".$cli->getStatusCode());

        // Monitora login + home (em nova sessão)
        $now=now(); $c=DB::connection('monitora');
        if($c->table('empresas_grupos')->where('id',1)->doesntExist())
            $c->table('empresas_grupos')->insert(['id'=>1,'descricao'=>'G','ativo'=>1,'created_at'=>$now,'updated_at'=>$now]);
        if($c->table('empresas')->where('id',1)->doesntExist())
            $c->table('empresas')->insert(['id'=>1,'grupo_id'=>1,'razao_social'=>'E','nome_informal'=>'E','ativo'=>1,'created_at'=>$now,'updated_at'=>$now]);
        $uid=$c->table('users')->where('email','mon@x')->value('id');
        if(!$uid) $uid=$c->table('users')->insertGetId(['name'=>'Mon','email'=>'mon@x','password'=>bcrypt('mon12345'),'empresa_id'=>1,'created_at'=>$now,'updated_at'=>$now]);
        if($c->table('empresa_user')->where('user_id',$uid)->doesntExist())
            $c->table('empresa_user')->insert(['empresa_id'=>1,'user_id'=>$uid]);
        $r=$this->followingRedirects()->post('/monitora/handleLogin',['email'=>'mon@x','password'=>'mon12345']);
        fwrite(STDERR,"\nMonitora home = ".$r->getStatusCode());

        $this->assertNotEquals(500,$cli->getStatusCode());
        $this->assertNotEquals(500,$r->getStatusCode());
    }
}
