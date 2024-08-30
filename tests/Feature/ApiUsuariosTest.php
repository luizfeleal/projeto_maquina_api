<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiUsuariosTest extends TestCase
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
    public function post_users_with_empty_body_and_error_message()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->post('/api/usuarios');

        $response->assertStatus(400);

        $response->assertJson([
            "errors"=> [
                "id_grupo_acesso"=> [
                    "O campo id grupo acesso é obrigatório."
                ],
                "id_cliente"=> [
                    "O campo id cliente é obrigatório."
                ],
                "usuario_nome"=> [
                        "O campo usuario nome é obrigatório."
                ],
                "usuario_email"=> [
                    "O campo usuario email é obrigatório."
                ],
                "usuario_login"=> [
                    "O campo usuario login é obrigatório."
                ],
                "usuario_senha"=> [
                     "O campo usuario senha é obrigatório."
                ]
            ]
        ]);
    }

    /** @test*/

    public function post_users_with_correct_body_and_success_message(){
        $data = [
            'id_grupo_acesso'   => 1,
            'id_cliente'        => 1,
            'usuario_nome'      => 'Usuário Nome Teste API',
            'usuario_email'     => 'usuario@gmail.com',
            'usuario_login'     => 'usuario1',
            'usuario_senha'     => 'senha1'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->post('/api/usuarios', $data);
        $response->assertStatus(201);
        $response->assertJson([
            "message" => "Usuário cadastrado com sucesso!",
            "response" => $data
        ]);
    }


    /** @test*/
    public function get_all_users()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/usuarios');
        $response->assertStatus(200);
    }

    /** @test*/
    public function get_users_by_not_found_number_id()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/usuarios/9078');
        $response->assertStatus(404);
    }

    /** @test*/
    public function get_users_by_number_id()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->get('/api/usuarios/1');
        $response->assertStatus(200);
    }
    /** @test*/
    public function update_users_with_success()
    {
        $data = [
            'usuario_nome'=> 'Usuário Teste PHPUNIT API',
        ];
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token
        ])->put('/api/usuarios/1', $data);

        $response->assertStatus(200);
    }
}
