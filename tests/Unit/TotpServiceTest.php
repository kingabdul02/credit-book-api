<?php

namespace Tests\Unit;

use App\Services\TotpService;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    private TotpService $totpService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totpService = new TotpService();
    }

    public function test_generates_valid_base32_secret(): void
    {
        $secret = $this->totpService->generateSecret(32);
        $this->assertEquals(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function test_generates_and_verifies_totp_code_successfully(): void
    {
        $secret = $this->totpService->generateSecret();
        $code = $this->totpService->getCode($secret);

        $this->assertEquals(6, strlen($code));
        $this->assertTrue(ctype_digit($code));
        $this->assertTrue($this->totpService->verifyCode($secret, $code));
    }

    public function test_rejects_incorrect_code(): void
    {
        $secret = $this->totpService->generateSecret();
        $code = $this->totpService->getCode($secret);
        
        $wrongCode = str_pad((string) (((int) $code + 12345) % 1000000), 6, '0', STR_PAD_LEFT);
        $this->assertFalse($this->totpService->verifyCode($secret, $wrongCode, 0));
    }

    public function test_generates_correct_otpauth_url(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $url = $this->totpService->getOtpAuthUrl($secret, '+2348012345678', 'CreditBook');

        $this->assertStringStartsWith('otpauth://totp/CreditBook:%2B2348012345678?', $url);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $url);
        $this->assertStringContainsString('issuer=CreditBook', $url);
    }
}
