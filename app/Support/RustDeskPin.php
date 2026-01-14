<?php

namespace App\Support;

class RustDeskPin
{
    public static function encrypt(string $pinPlain, string $uuidMachine): string
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new \RuntimeException('Extensión sodium no disponible en PHP.');
        }

        $key = self::makeKey($uuidMachine);
        $nonce = str_repeat("\x00", SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); // 24

        $cipher = sodium_crypto_secretbox($pinPlain, $nonce, $key);

        return '00' . base64_encode($cipher);
    }

    public static function decrypt(string $pinEnc, string $uuidMachine): string
    {
        if (!str_starts_with($pinEnc, '00')) {
            throw new \InvalidArgumentException('Versión no soportada (prefijo 00 requerido).');
        }

        $cipher = base64_decode(substr($pinEnc, 2), true);
        if ($cipher === false) {
            throw new \InvalidArgumentException('Base64 inválido.');
        }

        $key = self::makeKey($uuidMachine);
        $nonce = str_repeat("\x00", SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
        if ($plain === false) {
            throw new \RuntimeException('No se pudo descifrar (uuid/key incorrecto o token corrupto).');
        }

        return $plain;
    }

    private static function makeKey(string $uuidMachine): string
    {
        $key = $uuidMachine;

        if (strlen($key) < SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            $key = str_pad($key, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, "\x00");
        } else {
            $key = substr($key, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        }

        return $key;
    }
}
