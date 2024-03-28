<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiMaquinasTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();

        // Dados de autenticação
        $credentials = [
            'email' => 'felipelearaujo@gmail.com', // Substitua pelo email do usuário
            'password' => '123456' // Substitua pela senha do usuário
        ];

        // Faz uma solicitação POST para obter o token
        $response = $this->post('/api/auth/login', $credentials);

        // Extrai o token da resposta
        $token = $response->json('access_token');

        // Armazena o token para uso nos testes
        $this->token = $token;
    }
    /** @test*/
    public function post_machine_with_empty_body_and_error_message()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->post('/api/maquinas');

        $response->assertStatus(400);

        $response->assertJson([
            "errors"=> [
                "maquina_referencia"=> [
                    "O campo maquina referencia é obrigatório."
                ],
                "maquina_nome"=> [
                    "O campo maquina nome é obrigatório."
                ],
                "maquina_status"=> [
                    "O campo maquina status é obrigatório."
                ]
            ]
        ]);
    }

    /** @test*/

    public function post_machine_with_correct_body_and_success_message(){
        $data = [
            'maquina_referencia'   => "Teste máquina referencia pHpunit",
            'maquina_nome'   => "Teste máquina nome pHpunit",
            'maquina_status'        => 0,
            'id_local' => 1,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->post('/api/maquinas', $data);
        $response->assertStatus(201);
        $response->assertJson([
            "message" => "Máquina cadastrada com sucesso!",
            "response" => $data
        ]);
    }


    /** @test*/
    public function get_all_machines()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/maquinas');
        $response->assertStatus(200);
    }

    /** @test*/
    public function get_machines_by_not_found_number_id()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/maquinas/9078');
        $response->assertStatus(404);
    }

    /** @test*/
    public function get_machines_by_number_id()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/maquinas/1');
        $response->assertStatus(200);
    }
    /** @test*/
    public function update_machine_with_success()
    {
        $data = [
            'maquina_nome'=> 'Máquina Teste atualizar PHPUNIT API',
        ];
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->put('/api/maquinas/1', $data);

        $response->assertStatus(200);
    }
}
