<?php

namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;

class DeleteNotificacoes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:delete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes notifications that are a week old.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $count = 0;
        $countMsgs = 0;
        $date = new \DateTime();
        $date = $date->format('d/m/Y H:i:s');
        DB::beginTransaction();
        try {
            $count = DB::table('notificacoes')->whereRaw("created_at <= trunc(sysdate) - 14")->count();
            DB::table('notificacoes')->whereRaw("created_at <= trunc(sysdate) - 14")->delete();
            $countMsgs = DB::table('androidmensagems')->whereRaw("created_at <= trunc(sysdate) - 14")->count();
            DB::table('androidmensagems')->whereRaw("created_at <= trunc(sysdate) - 14")->delete();
        } catch ( \Exception $e ) {
            DB::rollback();
            $error = "Error: " . $e->getMessage() . " Line: " . $e->getLine();
            $this->info($error);
        }
        DB::commit();
        $this->info("Delete of notifications older than a week completed! $count registers removed on: $date");
        $this->info("Delete of messages older than a week completed! $countMsgs registers removed on: $date");
    }
}
