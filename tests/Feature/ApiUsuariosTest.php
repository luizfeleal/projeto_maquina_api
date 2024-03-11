<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiUsuariosTest extends TestCase
{
        /** @test*/
    public function post_users_with_empty_body_and_message_erros()
    {
        $response = $this->post('/api/usuarios');

        $response->assertStatus(400);
    }


    /**
     * A basic test example for API login endpoint GET request.
     *
     * @return void
     */
    public function testApiGetAllUsers()
    {
        $response = $this->get('/api/usuarios');
        $response->assertStatus(200);
    }

    /** @test*/
    public function get_users_by_not_found_number_id()
    {
        $response = $this->get('/api/usuarios/9078');
        $response_string = json_encode($response);
        $response->assertStatus(404);
    }
}
