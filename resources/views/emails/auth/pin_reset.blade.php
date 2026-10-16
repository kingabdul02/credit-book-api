<x-mail::message>
# Reset Your CreditBook Login PIN

Hello {{ $user->name }},

We received a request to reset your CreditBook 6-digit login PIN. Use the verification code below to proceed:

<div style="text-align: center; margin: 24px 0;">
    <span style="display: inline-block; font-size: 32px; font-weight: 800; letter-spacing: 6px; padding: 12px 24px; background: #F1F5F9; border-radius: 12px; color: #0F766E;">
        {{ $code }}
    </span>
</div>

This code will expire in **10 minutes**.

> [!WARNING]
> If you did not request a PIN reset, please ignore this email or review your account security immediately.

Thanks,<br>
The {{ config('app.name') }} Team
</x-mail::message>
