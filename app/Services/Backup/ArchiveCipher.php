<?php

namespace App\Services\Backup;

use RuntimeException;

/** Bounded-memory AES-256-GCM records; authenticated sequence and terminal record. */
final class ArchiveCipher
{
    private const MAGIC = "HARBOR-BACKUP-1\n";

    private const CHUNK = 1048576;

    private function key(): string
    {
        $key = base64_decode((string) config('backup.key'), true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('A dedicated 32-byte backup key is required.');
        }
        $applicationKey = (string) config('app.key');
        $applicationKey = str_starts_with($applicationKey, 'base64:') ? base64_decode(substr($applicationKey, 7), true) : $applicationKey;
        if (is_string($applicationKey) && hash_equals($applicationKey, $key)) {
            throw new RuntimeException('Backup encryption must use a dedicated key distinct from APP_KEY.');
        }

        return $key;
    }

    public function validateKey(): void
    {
        $this->key();
    }

    public function encrypt(string $source, string $destination): void
    {
        $key = $this->key();
        $in = fopen($source, 'rb');
        $out = fopen($destination, 'xb');
        if (! $in || ! $out) {
            throw new RuntimeException('Backup stream unavailable.');
        }
        chmod($destination, 0600);
        try {
            $this->write($out, self::MAGIC);
            $prefix = random_bytes(8);
            $this->write($out, $prefix);
            $sequence = 0;
            do {
                $plain = fread($in, self::CHUNK);
                if ($plain === false) {
                    throw new RuntimeException('Backup stream read failed.');
                }
                $last = $plain === '';
                $length = pack('N', strlen($plain));
                $nonce = $prefix.pack('N', $sequence);
                $aad = self::MAGIC.$nonce.$length;
                $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $aad);
                if ($cipher === false) {
                    throw new RuntimeException('Backup encryption failed.');
                }
                $this->write($out, $length.$tag.$cipher);
                $sequence++;
            } while (! $last);
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    public function decrypt(string $source, string $destination): void
    {
        $key = $this->key();
        $in = fopen($source, 'rb');
        $out = fopen($destination, 'xb');
        if (! $in || ! $out) {
            throw new RuntimeException('Backup stream unavailable.');
        }
        chmod($destination, 0600);
        try {
            if ($this->read($in, strlen(self::MAGIC)) !== self::MAGIC) {
                throw new RuntimeException('Unknown backup format.');
            }
            $prefix = $this->read($in, 8);
            $sequence = 0;
            $total = 0;
            while (true) {
                $header = $this->read($in, 4);
                $length = unpack('N', $header)[1];
                $total += $length;
                if ($length > self::CHUNK || $total > config('backup.max_archive_bytes')) {
                    throw new RuntimeException('Backup size limit exceeded.');
                }
                $tag = $this->read($in, 16);
                $cipher = $this->read($in, $length);
                $nonce = $prefix.pack('N', $sequence++);
                $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, self::MAGIC.$nonce.$header);
                if ($plain === false) {
                    throw new RuntimeException('Backup authentication failed.');
                }
                if ($length === 0) {
                    if (fread($in, 1) !== '') {
                        throw new RuntimeException('Unexpected backup trailing data.');
                    }
                    break;
                }
                $this->write($out, $plain);
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    private function read($stream, int $length): string
    {
        $bytes = '';
        while (strlen($bytes) < $length) {
            $part = fread($stream, $length - strlen($bytes));
            if ($part === false || $part === '') {
                throw new RuntimeException('Truncated backup.');
            }
            $bytes .= $part;
        }

        return $bytes;
    }

    private function write($stream, string $bytes): void
    {
        $offset = 0;
        while ($offset < strlen($bytes)) {
            $written = fwrite($stream, substr($bytes, $offset));
            if (! $written) {
                throw new RuntimeException('Backup stream write failed.');
            }
            $offset += $written;
        }
    }
}
