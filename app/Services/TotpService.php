<?php

namespace App\Services;

class TotpService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a new 32-character Base32 random secret (160 bits).
     */
    public function generateSecret(int $length = 32): string
    {
        $bytes = random_bytes(20);
        $b32 = $this->base32Encode($bytes);
        return substr($b32, 0, $length);
    }

    /**
     * Generate otpauth:// URI compatible with Google Authenticator, Authy, etc.
     */
    public function getOtpAuthUrl(string $secret, string $account, string $issuer = 'CreditBook'): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ]);

        return "otpauth://totp/{$label}?{$query}";
    }

    /**
     * Get QR Code image URL for easy scanning.
     */
    public function getQrCodeUrl(string $otpAuthUrl): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($otpAuthUrl);
    }

    /**
     * Generate current TOTP code for the given secret at timestamp.
     */
    public function getCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $timeSlice = (int) floor($timestamp / 30);
        return $this->calculateCode($secret, $timeSlice);
    }

    /**
     * Verify a 6-digit TOTP code with time drift window.
     */
    public function verifyCode(string $secret, string $code, int $discrepancy = 1, ?int $timestamp = null): bool
    {
        $code = trim($code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        $timestamp = $timestamp ?? time();
        $currentTimeSlice = (int) floor($timestamp / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculated = $this->calculateCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculated, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate 6-digit code for a specific time slice.
     */
    private function calculateCode(string $secret, int $timeSlice): string
    {
        $secretBytes = $this->base32Decode($secret);

        // Pack 64-bit integer in big-endian format
        $binaryCounter = pack('N*', ($timeSlice >> 32) & 0xFFFFFFFF, $timeSlice & 0xFFFFFFFF);

        $hash = hash_hmac('sha1', $binaryCounter, $secretBytes, true);

        // Dynamic truncation (RFC 4226)
        $offset = ord(substr($hash, -1)) & 0x0F;
        $part = substr($hash, $offset, 4);
        $value = (unpack('N', $part)[1] & 0x7FFFFFFF) % 1000000;

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 Encode binary string.
     */
    public function base32Encode(string $data): string
    {
        if (empty($data)) {
            return '';
        }

        $binaryString = '';
        foreach (str_split($data) as $char) {
            $binaryString .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $fiveBitChunks = str_split($binaryString, 5);
        $base32 = '';

        foreach ($fiveBitChunks as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $base32 .= self::BASE32_CHARS[bindec($chunk)];
        }

        return $base32;
    }

    /**
     * Base32 Decode to binary string.
     */
    public function base32Decode(string $b32): string
    {
        $b32 = strtoupper(trim($b32));
        $b32 = preg_replace('/[^A-Z2-7]/', '', $b32);

        if (empty($b32)) {
            return '';
        }

        $binaryString = '';
        for ($i = 0; $i < strlen($b32); $i++) {
            $char = $b32[$i];
            $pos = strpos(self::BASE32_CHARS, $char);
            if ($pos === false) {
                continue;
            }
            $binaryString .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $eightBitChunks = str_split($binaryString, 8);
        $output = '';

        foreach ($eightBitChunks as $chunk) {
            if (strlen($chunk) === 8) {
                $output .= chr(bindec($chunk));
            }
        }

        return $output;
    }
}
