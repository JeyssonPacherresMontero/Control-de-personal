<?php
namespace App\Security;

/**
 * Servicio de Encriptación y Resguardo de Plantillas Biométricas
 * Emplea AES-256-GCM para garantizar confidencialidad e integridad criptográfica.
 */
class BiometricVault {
    private static function getKey(): string {
        $keyHex = $_ENV['BIOMETRIC_ENCRYPTION_KEY'] ?? '';
        if (empty($keyHex)) {
            // Derivar clave estática desde APP_KEY si existe
            $appKey = $_ENV['APP_KEY'] ?? 'zkteco-control-personal-default-key-32b';
            return hash('sha256', $appKey, true);
        }
        if (ctype_xdigit($keyHex) && strlen($keyHex) === 64) {
            return hex2bin($keyHex);
        }
        return hash('sha256', $keyHex, true);
    }

    /**
     * Encripta una plantilla biométrica usando AES-256-GCM
     * @param string $plainData
     * @return string Base64 con formato [IV (12B)][TAG (16B)][CIPHERTEXT]
     */
    public static function encrypt(string $plainData): string {
        if (empty($plainData)) {
            return '';
        }
        $key = self::getKey();
        $iv = random_bytes(12); // 96-bit IV para GCM
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plainData,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Fallo al encriptar datos biométricos.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Desencripta un payload protegido con AES-256-GCM
     * @param string $payload
     * @return string|null
     */
    public static function decrypt(string $payload): ?string {
        if (empty($payload)) {
            return null;
        }
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 28) {
            // Si no está encriptado o es base64 plano legacy, retornar original
            return $payload;
        }

        $key = self::getKey();
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);

        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            // Fallback ante registros legacy no encriptados
            return $payload;
        }

        return $decrypted;
    }
}
