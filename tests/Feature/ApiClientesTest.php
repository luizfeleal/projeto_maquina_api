<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiClientesTest extends TestCase
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
    public function post_clients_with_empty_body_and_error_message()
    {
        // Você precisa acessar o token armazenado corretamente aqui
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '. $this->token,
        ]);

        $response = $this->post('/api/clientes'); // Use $this->post para fazer a solicitação

        $response->assertStatus(400);

        $response->assertJson([
            "errors"=> [
                "cliente_nome"=> [
                    "O campo cliente nome é obrigatório."
                ],
                "cliente_celular"=> [
                    "O campo cliente celular é obrigatório."
                ],
                "cliente_email"=> [
                    "O campo cliente email é obrigatório."
                ],
                "cliente_cpf_cnpj"=> [
                    "O campo cliente cpf cnpj é obrigatório."
                ]
            ]
        ]);
    }

    /** @test*/

    public function post_clients_with_correct_body_and_success_message(){
        $data = [
            'cliente_nome'   => "Teste cliente nome pHpunit",
            'cliente_celular'        => "21964183013",
            'cliente_email'      => 'testephpunit@gmail.com',
            'cliente_cpf_cnpj'     => '15798684792',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->post('/api/clientes', $data);
        $response->assertStatus(201);
        $response->assertJson([
            "message" => "Cliente cadastrado com sucesso!",
            "response" => $data
        ]);
    }


    /** @test*/
    public function get_all_clients()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/clientes');
        $response->assertStatus(200);
    }

    /** @test*/
    public function get_clients_by_not_found_number_id()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/clientes/9078');
        $response->assertStatus(404);
    }

    /** @test*/
    public function get_clients_by_number_id()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/clientes/1');
        $response->assertStatus(200);
    }
    /** @test*/
    public function update_clients_with_success()
    {
        $data = [
            'cliente_nome'=> 'Usuário Teste atualizar PHPUNIT API',
        ];
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->put('/api/clientes/1', $data);

        $response->assertStatus(200);
    }
}
