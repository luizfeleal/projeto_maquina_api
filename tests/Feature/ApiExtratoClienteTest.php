<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiExtratoClienteTest extends TestCase
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
    public function post_client_extract_with_empty_body_and_error_message()
    {
        // Você precisa acessar o token armazenado corretamente aqui
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '. $this->token,
        ]);

        $response = $this->post('/api/extratoCliente'); // Use $this->post para fazer a solicitação

        $response->assertStatus(400);

        $response->assertJson([
            "errors"=> [
                "extrato_operacao_tipo"=> [
                    "O campo extrato operacao tipo é obrigatório."
                ],
                "extrato_operacao_valor"=> [
                    "O campo extrato operacao valor é obrigatório."
                ],
                "extrato_operacao_status"=> [
                    "O campo extrato operacao status é obrigatório."
                ],
            ]
        ]);
    }

    /** @test*/

    public function post_client_extract_with_correct_body_and_success_message(){
        $data = [
            'extrato_operacao_tipo'   => "Entrada",
            'extrato_operacao_valor'   => "15.5",
            'extrato_operacao_status'   => "Sucesso",
            'extrato_operacao_saldo'   => "15.5",
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->post('/api/extratoCliente', $data);
        $response->assertStatus(201);
        $response->assertJson([
            "message" => "Operação cadastrada com sucesso no extrato!",
            "response" => $data
        ]);
    }


    /** @test*/
    public function get_all_client_extract()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/extratoCliente');
        $response->assertStatus(200);
    }

    /** @test*/
    public function get_client_extract_by_not_found_number_id()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/extratoCliente/9078');
        $response->assertStatus(404);
    }

    /** @test*/
    public function get_client_extract_by_number_id()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/extratoCliente/1');
        $response->assertStatus(200);
    }
    /** @test*/
    public function update_client_extract_with_success()
    {
        $data = [
            'extrato_operacao_status'=> 'Devolução',
            'extrato_operacao_saldo'=> '0.0',
        ];
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->put('/api/extratoCliente/1', $data);

        $response->assertStatus(200);
    }
}
