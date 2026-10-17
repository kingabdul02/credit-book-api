<x-mail::message>
# Two-Factor Authentication Code

Hello {{ $user->name }},

Your CreditBook two-factor authentication verification code is:

<div style="text-align: center; margin: 24px 0;">
    <span style="display: inline-block; font-size: 32px; font-weight: 800; letter-spacing: 6px; padding: 12px 24px; background: #F1F5F9; border-radius: 12px; color: #0F766E;">
        {{ $code }}
    </span>
</div>

This security code will expire in **10 minutes**.

> [!WARNING]
> If you did not initiate this sign-in attempt, please change your login PIN immediately to protect your account.

Thanks,<br>
The {{ config('app.name') }} Team
</x-mail::message>
