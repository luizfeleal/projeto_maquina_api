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

        $coordenadas = self::obterCoordenadas($cliente);

        $payload = [
            'name' => self::normalizarTexto($cliente['cliente_nome'] ?? $externalStoreId),
            'external_id' => $externalStoreId,
            'location' => [
                'street_name' => self::normalizarTexto($cliente['cliente_logradouro'] ?? 'Não informado'),
                'street_number' => $cliente['cliente_numero'] ?? 'S/N',
                'city_name' => self::normalizarTexto($cliente['cliente_cidade'] ?? 'Não informado'),
                'state_name' => self::nomeEstado($cliente['cliente_uf'] ?? null),
                'latitude' => $coordenadas['latitude'],
                'longitude' => $coordenadas['longitude'],
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
     * O Mercado Pago exige latitude/longitude reais em `location` — rejeita
     * explicitamente o par (0,0) ("store coordinates ... are invalid") que
     * era usado antes como placeholder. Geocodifica o endereço do cliente,
     * tentando primeiro pelo CEP (BrasilAPI) e depois pelo endereço textual
     * (Nominatim/OpenStreetMap) como reforço — ambos gratuitos, sem chave de
     * API. Lança exceção se nenhum dos dois conseguir localizar coordenadas
     * válidas, em vez de silenciosamente mandar (0,0) de novo.
     */
    private static function obterCoordenadas($cliente): array
    {
        $cep = preg_replace('/\D/', '', $cliente['cliente_cep'] ?? '');

        if (strlen($cep) === 8) {
            $coordenadas = self::coordenadasPorCep($cep);

            if ($coordenadas !== null) {
                return $coordenadas;
            }
        }

        $coordenadas = self::coordenadasPorEndereco($cliente);

        if ($coordenadas !== null) {
            return $coordenadas;
        }

        throw new \Exception('Não foi possível obter as coordenadas geográficas do endereço do cliente (CEP/endereço podem estar incompletos ou incorretos) — o Mercado Pago exige localização válida para criar a Loja.');
    }

    private static function coordenadasPorCep(string $cep): ?array
    {
        try {
            $resposta = Http::timeout(5)->get("https://brasilapi.com.br/api/cep/v2/{$cep}");

            if (!$resposta->successful()) {
                return null;
            }

            $dados = $resposta->json();
            $lat = $dados['location']['coordinates']['latitude'] ?? null;
            $lng = $dados['location']['coordinates']['longitude'] ?? null;

            return self::validarCoordenadas($lat, $lng);
        } catch (\Throwable $e) {
            \Log::warning('Falha ao geocodificar CEP via BrasilAPI: ' . $e->getMessage());
            return null;
        }
    }

    private static function coordenadasPorEndereco($cliente): ?array
    {
        $endereco = trim(implode(', ', array_filter([
            self::normalizarTexto($cliente['cliente_logradouro'] ?? null),
            $cliente['cliente_numero'] ?? null,
            self::normalizarTexto($cliente['cliente_cidade'] ?? null),
            $cliente['cliente_uf'] ?? null,
            'Brasil',
        ])));

        if ($endereco === '') {
            return null;
        }

        try {
            // Nominatim exige um User-Agent identificável (política de uso) e
            // no máximo ~1 req/s -- uso aqui é raro (uma vez por cliente).
            $resposta = Http::withHeaders(['User-Agent' => 'ProjetoMaquina-SwiftPay/1.0'])
                ->timeout(5)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'json',
                    'q' => $endereco,
                    'countrycodes' => 'br',
                    'limit' => 1,
                ]);

            if (!$resposta->successful() || empty($resposta->json())) {
                return null;
            }

            $primeiro = $resposta->json()[0];

            return self::validarCoordenadas($primeiro['lat'] ?? null, $primeiro['lon'] ?? null);
        } catch (\Throwable $e) {
            \Log::warning('Falha ao geocodificar endereço via Nominatim: ' . $e->getMessage());
            return null;
        }
    }

    private static function validarCoordenadas($lat, $lng): ?array
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat === 0.0 && $lng === 0.0) {
            return null;
        }

        return ['latitude' => $lat, 'longitude' => $lng];
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

    /**
     * Corrige texto com acentos em forma "decomposta" (letra base + marca de
     * combinação Unicode, ex.: "e" + ́ em vez do caractere único "é") — chega
     * assim às vezes dependendo da origem do dado (comum vindo de macOS). É
     * visualmente idêntico ao normal, mas a API de Lojas do Mercado Pago
     * valida `city_name` contra uma lista fechada por comparação exata de
     * bytes, e rejeita a forma decomposta mesmo quando o nome está correto
     * (ex.: "Santo André" sendo recusado).
     */
    private static function normalizarTexto(?string $valor): string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return $valor;
        }

        if (class_exists(\Normalizer::class)) {
            $normalizado = \Normalizer::normalize($valor, \Normalizer::FORM_C);

            if ($normalizado !== false) {
                return $normalizado;
            }
        }

        // Fallback sem depender da extensão intl: cobre manualmente as
        // combinações letra+diacrítico mais comuns em português.
        $decompostoParaComposto = [
            "a\u{0301}" => 'á', "a\u{0300}" => 'à', "a\u{0302}" => 'â', "a\u{0303}" => 'ã',
            "e\u{0301}" => 'é', "e\u{0300}" => 'è', "e\u{0302}" => 'ê',
            "i\u{0301}" => 'í', "i\u{0300}" => 'ì',
            "o\u{0301}" => 'ó', "o\u{0300}" => 'ò', "o\u{0302}" => 'ô', "o\u{0303}" => 'õ',
            "u\u{0301}" => 'ú', "u\u{0300}" => 'ù', "u\u{0308}" => 'ü',
            "c\u{0327}" => 'ç',
            "A\u{0301}" => 'Á', "A\u{0300}" => 'À', "A\u{0302}" => 'Â', "A\u{0303}" => 'Ã',
            "E\u{0301}" => 'É', "E\u{0300}" => 'È', "E\u{0302}" => 'Ê',
            "I\u{0301}" => 'Í', "I\u{0300}" => 'Ì',
            "O\u{0301}" => 'Ó', "O\u{0300}" => 'Ò', "O\u{0302}" => 'Ô', "O\u{0303}" => 'Õ',
            "U\u{0301}" => 'Ú', "U\u{0300}" => 'Ù', "U\u{0308}" => 'Ü',
            "C\u{0327}" => 'Ç',
        ];

        return strtr($valor, $decompostoParaComposto);
    }
}
