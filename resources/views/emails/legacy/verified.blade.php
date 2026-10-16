<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $success ? 'Email Verified' : 'Verification Issue' }} - {{ config('app.name', 'CreditBook') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #10B981;
            --primary-dark: #059669;
            --primary-light: #ECFDF5;
            --bg: #F8FAFC;
            --card-bg: #FFFFFF;
            --text-main: #0F172A;
            --text-muted: #64748B;
            --border: #E2E8F0;
            --code-bg: #F1F5F9;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --primary: #10B981;
                --primary-dark: #34D399;
                --primary-light: rgba(16, 185, 129, 0.15);
                --bg: #0F172A;
                --card-bg: #1E293B;
                --text-main: #F8FAFC;
                --text-muted: #94A3B8;
                --border: #334155;
                --code-bg: #0B1120;
            }
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .container {
            width: 100%;
            max-width: 480px;
        }

        .card {
            background-color: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
            border: 1px solid var(--border);
            padding: 36px 28px;
            text-align: center;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--text-main);
            margin-bottom: 24px;
            letter-spacing: -0.02em;
        }

        .brand-icon {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #10B981, #047857);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 15px;
        }

        .status-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .status-icon.success {
            background-color: var(--primary-light);
            color: var(--primary);
        }

        .status-icon.error {
            background-color: rgba(239, 68, 68, 0.12);
            color: #EF4444;
        }

        .status-icon svg {
            width: 36px;
            height: 36px;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            margin-bottom: 10px;
        }

        .description {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 24px;
        }

        .info-box {
            background-color: var(--code-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 24px;
            text-align: left;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            padding: 6px 0;
        }

        .info-row:not(:last-child) {
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .info-label {
            color: var(--text-muted);
        }

        .info-value {
            font-weight: 600;
            color: var(--text-main);
        }

        .code-container {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(6, 95, 70, 0.08));
            border: 1.5px dashed var(--primary);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .code-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 6px;
        }

        .code-value {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            color: var(--text-main);
            margin-bottom: 12px;
        }

        .copy-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 18px;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .copy-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .copy-btn:active {
            transform: translateY(0);
        }

        .hint {
            font-size: 0.82rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .badge-verified {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: var(--primary-light);
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="brand">
                <div class="brand-icon">C</div>
                <span>{{ config('app.name', 'CreditBook') }}</span>
            </div>

            @if($success)
                <div class="status-icon success">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <h1>Email Verified</h1>
                <p class="description">
                    You have successfully verified your email and accepted nomination as a Legacy Contact.
                </p>

                <div class="info-box">
                    <div class="info-row">
                        <span class="info-label">Nominee</span>
                        <span class="info-value">{{ $contact->full_name }}</span>
                    </div>
                    @if($contact->user)
                    <div class="info-row">
                        <span class="info-label">Account Owner</span>
                        <span class="info-value">{{ $contact->user->name }}</span>
                    </div>
                    @endif
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="badge-verified">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                            Verified
                        </span>
                    </div>
                </div>

                @if($contact->access_code)
                <div class="code-container">
                    <div class="code-label">Your Secure Access Code</div>
                    <div class="code-value" id="accessCodeVal">{{ $contact->access_code }}</div>
                    <button class="copy-btn" id="copyBtn" onclick="copyAccessCode()">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <span id="copyBtnText">Copy Code</span>
                    </button>
                </div>
                @endif

                <p class="hint">
                    Save your access code securely. In an emergency, you can use it in the CreditBook app under <strong>Nominee Access</strong> to request access to the financial ledger.
                </p>

                <script>
                    function copyAccessCode() {
                        const code = document.getElementById('accessCodeVal').innerText.trim();
                        navigator.clipboard.writeText(code).then(() => {
                            const btnText = document.getElementById('copyBtnText');
                            btnText.innerText = 'Copied!';
                            setTimeout(() => {
                                btnText.innerText = 'Copy Code';
                            }, 2500);
                        });
                    }
                </script>
            @else
                <div class="status-icon error">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <h1>Verification Issue</h1>
                <p class="description">
                    {{ $errorMessage ?? 'This verification link is invalid or may have expired.' }}
                </p>

                <p class="hint">
                    If you have already verified your email address, your nominee access is active. Otherwise, please ask the account owner to resend a new verification email from their settings.
                </p>
            @endif
        </div>
    </div>
</body>
</html>
