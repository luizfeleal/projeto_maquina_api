<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAcessosTelaTest extends TestCase
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

        $response = $this->post('/api/acessosTela'); // Use $this->post para fazer a solicitação

        $response->assertStatus(400);

        $response->assertJson([
            "errors"=> [
                "id_grupo_acesso"=> [
                    "O campo id grupo acesso é obrigatório."
                ],
                "acesso_tela_viewname"=> [
                    "O campo acesso tela viewname é obrigatório."
                ],
                "acesso_tela_nome"=> [
                    "O campo acesso tela nome é obrigatório."
                ]
            ]
        ]);
    }

    /** @test*/

    public function post_access_groups_with_correct_body_and_success_message(){
        $data = [
            'id_grupo_acesso'   => '1',
            'acesso_tela_viewname' => 'Dashboard',
            'acesso_tela_nome' => 'Dashboard'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->post('/api/acessosTela', $data);
        $response->assertStatus(201);
        $response->assertJson([
            "message" => "Tela de acesso cadastrada com sucesso!",
            "response" => $data
        ]);
    }


    /** @test*/
    public function get_all_access_groups()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/acessosTela');
        $response->assertStatus(200);
    }

    /** @test*/
    public function get_access_groups_by_not_found_number_id()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/acessosTela/9078');
        $response->assertStatus(404);
    }

    /** @test*/
    public function get_access_groups_by_number_id()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/acessosTela/1');
        $response->assertStatus(200);
    }
    /** @test*/
    public function update_access_groups_with_success()
    {
        $data = [
            'acesso_tela_nome'=> 'admin atualizado',
        ];
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->put('/api/acessosTela/1', $data);

        $response->assertStatus(200);
    }
}
