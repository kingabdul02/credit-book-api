<x-mail::message>
# You've Been Nominated as a Legacy Contact

Hello {{ $contact->full_name }},

{{ $contact->user->name }} has nominated you as a legacy contact on {{ config('app.name') }}. In the event of an emergency, you will be able to request access to their account.

To accept this nomination, please verify your email address by clicking the button below:

<x-mail::button :url="$verificationUrl">
Verify Email Address
</x-mail::button>

## Your Access Code

Once verified, you can use this access code to request access to the account:

**{{ $accessCode }}**

Please save this code securely. You will need it to submit an access request.

If you did not expect this, you can safely ignore this email.

Thanks,<br>
The {{ config('app.name') }} Team
</x-mail::message>
