<?php

namespace App\Services\Efi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ConversorArquivoService
{


    public static function converterCertificadoEfi($file, string $directory, $id_cliente)
    {
	// Verifica se o arquivo é válido
        if (!$file) {
            return ['error' => 'Arquivo não fornecido', 'caminho_certificado' => null, 'status' => 400];
        }

        // Obtém o caminho absoluto do arquivo .p12
        $caminhoP12 = $file->getRealPath();

        // Define o caminho para o arquivo de saída .pem
        $pemPath = storage_path("app/{$directory}/certificate{$id_cliente}.pem");
        $pemPathSave = "{$directory}/certificate{$id_cliente}.pem";

        // Define a senha do arquivo .p12 (se houver)
        $p12Password = '';  // Ajuste a senha se necessário

        // Monta o comando OpenSSL
        $comando = "openssl pkcs12 -in {$caminhoP12} -out {$pemPath} -nodes -passin pass:{$p12Password}";

        // Executa o comando usando a classe Process do Symfony
        $process = Process::fromShellCommandline($comando);
        $process->run();

        // Verifica se o comando foi executado com sucesso
        if (!$process->isSuccessful()) {
            // Trata o erro, se houver
            return ['error' => 'Falha ao executar o comando OpenSSL', 'output' => $process->getErrorOutput(), 'status' => 500];
        }

        // Verifica se o arquivo PEM foi salvo com sucesso
        if (!file_exists($pemPath)) {
            return ['error' => 'Falha ao salvar o arquivo PEM', 'caminho_certificado' => null, 'status' => 500];
        }

        // Retorna o sucesso
        return ['success' => 'Conversão concluída com sucesso', 'caminho_certificado' => $pemPathSave, 'status' => 200];
                
    }
}
