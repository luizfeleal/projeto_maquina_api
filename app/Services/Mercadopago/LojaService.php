<?php

namespace App\Services\Mercadopago;

use App\Models\Clientes;
use App\Models\MercadopagoLoja;
use Illuminate\Support\Facades\Http;

class LojaService
{
    /**
     * O SDK oficial (mercadopago/dx-php) não expõe cliente para a API de Lojas,
     * por isso a chamada é feita direto via HTTP, seguindo a documentação
     * (POST /users/{user_id}/stores).
     */
    private const BASE_URL = 'https://api.mercadopago.com';

    /**
     * Retorna a Loja já cadastrada do cliente ou cria uma nova no Mercado Pago
     * na primeira vez (mesmo espírito do fluxo de chave Pix da Efí: criação
     * automática e reaproveitada nas próximas chamadas).
     */
    public static function criarOuObterLoja(int $idCliente, string $accessToken): MercadopagoLoja
    {
        $lojaExistente = MercadopagoLoja::where('id_cliente', $idCliente)->first();

        if ($lojaExistente) {
            return $lojaExistente;
        }

        $mpUserId = ContaService::obterUserId($accessToken);

        $cliente = Clientes::findOrFail($idCliente);

        $externalStoreId = 'CLI' . $idCliente;

        $payload = [
            'name' => $cliente['cliente_nome'] ?? $externalStoreId,
            'external_id' => $externalStoreId,
            'location' => [
                'street_name' => $cliente['cliente_logradouro'] ?? 'Não informado',
                'street_number' => $cliente['cliente_numero'] ?? 'S/N',
                'city_name' => $cliente['cliente_cidade'] ?? 'Não informado',
                'state_name' => self::nomeEstado($cliente['cliente_uf'] ?? null),
                'latitude' => 0,
                'longitude' => 0,
            ],
        ];

        $resposta = Http::withToken($accessToken)
            ->post(self::BASE_URL . '/users/' . $mpUserId . '/stores', $payload);

        \Log::info('Criação de Loja Mercado Pago -----------------');
        \Log::info($resposta->body());

        if ($resposta->failed()) {
            throw new \Exception('Falha ao criar a Loja no Mercado Pago: ' . $resposta->body());
        }

        $dados = $resposta->json();

        $loja = new MercadopagoLoja();
        $loja->fill([
            'id_cliente' => $idCliente,
            'mp_user_id' => $mpUserId,
            'mp_store_id' => (string) $dados['id'],
            'external_store_id' => $externalStoreId,
        ]);
        $loja->save();

        return $loja;
    }

    /**
     * `clientes.cliente_uf` guarda a sigla (ex.: "SP"), mas a API de Lojas do
     * Mercado Pago exige o nome completo do estado por extenso em
     * `location.state_name` (erro: "location.state_name was invalid").
     */
    private static function nomeEstado(?string $uf): string
    {
        $estados = [
            'AC' => 'Acre',
            'AL' => 'Alagoas',
            'AP' => 'Amapá',
            'AM' => 'Amazonas',
            'BA' => 'Bahia',
            'CE' => 'Ceará',
            'DF' => 'Distrito Federal',
            'ES' => 'Espírito Santo',
            'GO' => 'Goiás',
            'MA' => 'Maranhão',
            'MT' => 'Mato Grosso',
            'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais',
            'PA' => 'Pará',
            'PB' => 'Paraíba',
            'PR' => 'Paraná',
            'PE' => 'Pernambuco',
            'PI' => 'Piauí',
            'RJ' => 'Rio de Janeiro',
            'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul',
            'RO' => 'Rondônia',
            'RR' => 'Roraima',
            'SC' => 'Santa Catarina',
            'SP' => 'São Paulo',
            'SE' => 'Sergipe',
            'TO' => 'Tocantins',
        ];

        $uf = strtoupper(trim((string) $uf));

        return $estados[$uf] ?? 'São Paulo';
    }
}
