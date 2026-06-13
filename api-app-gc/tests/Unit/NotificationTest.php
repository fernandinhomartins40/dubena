<?php

namespace Tests\Unit;

use App\User;
use Tests\TestCase;
use App\Http\Resources\ApiResources;

class NotificationTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testSendingNotificationForARegistrationId()
    {
        $registration_id = "c-CjWnRpRR-4G6dPKUsyEq:APA91bHDChf_4tfiqzoxXTXSezZPyChJB3LTgS7AxH23P0trSODfrDDJNrcgwYjMGoNKWAKkitgI9J4NNPXFoupvp_jYeu1j58A74r7qW5UFSa89vBm2fNA";
        // $registration_id = "fGBGFt8tQZasC-WBVMNZ3l:APA91bEIdXHEXQSaKMvKKL9mBZPozmHTMHrazYeZ02MGq0UgrJKA2olVtPNMquOYqXNun4Yk5ftNnMWmdAQalUQxPC7gy-5p8s4oSwacKkDn6W7q5a3bySM";
        // $registration_id = "fjmL2a6FRJO4XUI0mugui6:APA91bHFMO8aJvbztWKzh-EUmXQeVLiOLpMM0cfWbWeYpA5D3WB4eu2JOEeXE9pCDo8kmjtmldjGiyu73YfUfKkTcnIbwT6x0JKlLXtXIRL5d6OsPm-P5Zc";


        $user = User::find(2);

        $api = new ApiResources(null, $user);

        $response = $api->notifyDevices("Teste de imagem", "Imagem de teste", $registration_id, [], true, null, "http://qtidevel.ddns.net:8181/ctrl2qti/public/storage/img/notificacoes/811/notification-img.jpg");

        $this->assertEquals($response->error, false);
    }
}
