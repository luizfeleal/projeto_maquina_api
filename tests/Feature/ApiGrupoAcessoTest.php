<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiGruposAcessoTest extends TestCase
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
    public function post_access_groups_with_empty_body_and_error_message()
    {
        // Você precisa acessar o token armazenado corretamente aqui
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '. $this->token,
        ]);

        $response = $this->post('/api/gruposAcesso'); // Use $this->post para fazer a solicitação

        $response->assertStatus(400);

        $response->assertJson([
            "errors"=> [
                "grupo_acesso_nome"=> [
                    "O campo grupo acesso nome é obrigatório."
                ]
            ]
        ]);
    }

    /** @test*/

    public function post_access_groups_with_correct_body_and_success_message(){
        $data = [
            'grupo_acesso_nome'   => "Admin Teste",
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->post('/api/gruposAcesso', $data);
        $response->assertStatus(201);
        $response->assertJson([
            "message" => "Grupo de acesso cadastrado com sucesso!",
            "response" => $data
        ]);
    }


    /** @test*/
    public function get_all_access_groups()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/gruposAcesso');
        $response->assertStatus(200);
    }

    /** @test*/
    public function get_access_groups_by_not_found_number_id()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/gruposAcesso/9078');
        $response->assertStatus(404);
    }

    /** @test*/
    public function get_access_groups_by_number_id()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/gruposAcesso/1');
        $response->assertStatus(200);
    }
    /** @test*/
    public function update_access_groups_with_success()
    {
        $data = [
            'grupo_acesso_nome'=> 'Teste atualização grupo',
        ];
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->put('/api/gruposAcesso/1', $data);

        $response->assertStatus(200);
    }
}
