<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            line-height: 1.6;
            color: #1a1a2e;
            background-color: #f0f2f8;
        }
        .wrapper {
            padding: 32px 16px;
            background-color: #f0f2f8;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(99, 102, 241, 0.12);
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 60%, #a855f7 100%);
            padding: 40px 32px;
            text-align: center;
        }
        .header-logo {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.8);
            margin-bottom: 16px;
        }
        .header-icon {
            font-size: 48px;
            margin-bottom: 12px;
            display: block;
        }
        .header-title {
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
        }
        .header-sub {
            font-size: 14px;
            color: rgba(255,255,255,0.75);
        }

        /* Content */
        .content {
            padding: 36px 32px;
        }
        .greeting {
            font-size: 16px;
            color: #4b5563;
            margin-bottom: 24px;
        }
        .greeting strong {
            color: #1a1a2e;
        }

        /* Type badge */
        .badge {
            display: inline-block;
            background: linear-gradient(135deg, #ede9fe, #e0e7ff);
            color: #6366f1;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        /* Message card */
        .message-card {
            background: #f8faff;
            border: 1px solid #e0e7ff;
            border-left: 4px solid #6366f1;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 28px;
        }
        .message-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 10px;
        }
        .message-body {
            font-size: 14px;
            color: #4b5563;
            line-height: 1.7;
        }

        /* CTA Button */
        .cta-container {
            text-align: center;
            margin: 28px 0;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 36px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
        }

        .divider {
            height: 1px;
            background: #e5e7eb;
            margin: 24px 0;
        }

        .helper-text {
            font-size: 13px;
            color: #9ca3af;
            text-align: center;
        }

        /* Footer */
        .footer {
            background: #f8faff;
            border-top: 1px solid #e0e7ff;
            padding: 24px 32px;
            text-align: center;
        }
        .footer-brand {
            font-size: 16px;
            font-weight: 700;
            color: #6366f1;
            margin-bottom: 8px;
        }
        .footer-text {
            font-size: 12px;
            color: #9ca3af;
            line-height: 1.6;
        }
        .footer-link {
            color: #6366f1;
            text-decoration: none;
        }
        .footer-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">

            <!-- Header -->
            <div class="header">
                <div class="header-logo">Prakerin Platform</div>
                <span class="header-icon">📬</span>
                <div class="header-title">Ada Notifikasi Baru!</div>
                <div class="header-sub">Kamu punya pembaruan di Prakerin</div>
            </div>

            <!-- Content -->
            <div class="content">
                <p class="greeting">Halo, <strong>{{ $userName }}</strong>!</p>

                <div class="badge">{{ $type }}</div>

                <div class="message-card">
                    <div class="message-title">{{ $title }}</div>
                    <div class="message-body">
                        {{ Str::limit($content, 300) }}
                        @if(strlen($content) > 300)
                            <br><em>...baca selengkapnya di aplikasi.</em>
                        @endif
                    </div>
                </div>

                @if(!empty($actionUrl))
                    <div class="cta-container">
                        <a href="{{ $actionUrl }}" class="cta-button">
                            Lihat di Prakerin →
                        </a>
                    </div>
                @endif

                <div class="divider"></div>

                <p class="helper-text">
                    Jangan lewatkan informasi penting terkait magang kamu.<br>
                    Masuk ke Prakerin untuk melihat detail lengkap.
                </p>
            </div>

            <!-- Footer -->
            <div class="footer">
                <div class="footer-brand">🎓 Prakerin</div>
                <p class="footer-text">
                    © {{ date('Y') }} Prakerin Platform. Hak cipta dilindungi.<br>
                    Kamu menerima email ini karena notifikasi email diaktifkan di akunmu.<br>
                    <a href="{{ config('app.frontend_url', 'http://localhost:3000') }}/dashboard/profile" class="footer-link">
                        Kelola preferensi notifikasi
                    </a>
                </p>
            </div>

        </div>
    </div>
</body>
</html>
