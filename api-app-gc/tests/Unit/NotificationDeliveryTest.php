<?php

namespace Tests\Unit;

use App\User;
use Tests\TestCase;
use Laravel\Passport\Passport;

class NotificationDeliveryTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testSendingNotificationsToDelivery()
    {
        $data = [
            "registration_ids" => "[\"dXfTg8bHZlU:APA91bG32AjmmdBolmxVOjzOg5HKZ1ISdX3ID7OSHW8Fo_38Os3JgJopFbXySyE8nt361hjOMaB26IAnAOCxagf-awtwtkuwfzcbr52tm_lW_DMxPEGtXpI0L0Pdj0p0oHWo1xOd3zo7\",\"dtLRtxYPTGg:APA91bGET-xQYclNSlprW70Eh4E7mMnmWYum2fjIN79e5MnxTvSGtOj7OcEp-6L5b02qx6wxAFgOKTqJmuWB2zfT4JPXLGC34aXUx3LX4yi11iZz67CNZORqAJRvvZd_cJ5Hy5ePjp-y\",\"exs1ntAfgOs:APA91bHarbq9mX1rt5_bUuiDHG9Gw3wz-7VR9HgNw9OXqHrFDZOF7Vjyi93MUFrS32B9-r4cuw8iWaQITjL-Y5g8QFEtq-sK1A89reBwQTNvlhn2q5EE0o_ENZlZd7-F6mUKZWzY9Pyc\",\"eb4UeSenwAI:APA91bFE4qjTBhV8QNcj9vNYl6b59t8k_jyFdylhHnmbS1GRB3Gn2Cm1FJXy0SztA7cehlSiO2EdpFDy-x8yfsKCACq6wjZ4fR25pX66_Hx9hPcMD5gI6LkL7cTVS0_4xBGSDhp1MsVW\",\"frG6viElrCI:APA91bEs9eVIgFyLM5zKgicSJyyJKGKtmMNv7NOKmLj7rY6XiRUi0ZIQbZrEqbAxpoZHsyny9vdnmloLlKe1IuMgCJnE3rAgqRTTKzyHsReIzLLBaVWqCiDROsbgWE6cx98hWz33jbcM\",\"f4bCL5zJfF8:APA91bEXvFi808eOq0szAltqoc_rlyuGO37XvPlGunRZrAf7wDOYusqr0xD-8ZQbarpCPBMPqfN9-GMHsNlSwmWkbn5C5zHg36Ouqj82IbMwDKFpS8IlNMBGv6eUiVBbe5laksJJOQpq\"]",
            "data" => [
                "message" => "Pedido 341877 enviado para entrega.",
                "hasSound" => "1",
                "android_channel_id" => "my_channel_01"
            ],
            "priority" => "high"
        ];

        $user = User::find(2);

        Passport::actingAs($user, ["*"]);

        $post = $this->post("/api/sendNotificationDelivery", $data);

        $post->assertStatus(200);
    }
}
