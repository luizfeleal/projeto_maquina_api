<?php

namespace App\Services\Efi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConversorArquivoService
{


    public static function converterCertificadoEfi($file, string $directory, $id_cliente)
    {

        if(!$file){
            return false;
        }

        $tempP12Path = $file->getRealPath();

        $p12Password = '';
        $pemPassword = '';

        $p12Content = file_get_contents($tempP12Path);

        $p12CertData = [];

        if(openssl_pkcs12_read($p12Content, $p12CertData, $p12Password)){
            $privateKeyPem = '';
            openssl_pkey_export($p12CertData['pkey'], $privateKeyPem, $pemPassword);

            // Exportar o certificado para o formato .pem
            $certPem = '';
            openssl_x509_export($p12CertData['cert'], $certPem);

            // Exportar a cadeia de certificados (caso exista)
            $caCertsPem = '';
            if (!empty($p12CertData['extracerts'])) {
                foreach ($p12CertData['extracerts'] as $caCert) {
                    openssl_x509_export($caCert, $caCertsPem);
                }
            }

            // Salvar os arquivos .pem em um local desejado (por exemplo, no storage/app)
            $pathCertificado = "{$directory}/certificate{$id_cliente}.pem";
            Storage::put('private_key.pem', $privateKeyPem);
            Storage::put($pathCertificado, $certPem);
            Storage::put('ca_certificates.pem', $caCertsPem);

            return ['success' => 'Conversão concluída com sucesso', 'caminho_certificado' => $pathCertificado, 'status' => 200];
        }else {
            return ['error' => 'Falha ao ler o arquivo .p12', 'caminho_certificado' => null, 'status' => 500];
        }
        
    }
}
