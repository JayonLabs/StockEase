<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: #2563eb;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }

        .header h2 {
            margin: 0;
            font-size: 20px;
        }

        .content {
            background: #fff;
            padding: 24px;
            border: 1px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 8px 8px;
        }

        .meta {
            background: #f9fafb;
            padding: 16px;
            border-radius: 6px;
            margin: 16px 0;
        }

        .meta p {
            margin: 6px 0;
            font-size: 14px;
        }

        .meta strong {
            color: #374151;
        }

        .message-box {
            background: #f0f4ff;
            border-left: 4px solid #2563eb;
            padding: 16px;
            border-radius: 0 6px 6px 0;
            margin: 16px 0;
            font-size: 15px;
            white-space: pre-line;
        }

        .reply-note {
            background: #ecfdf5;
            border: 1px solid #6ee7b7;
            border-radius: 6px;
            padding: 12px 16px;
            font-size: 13px;
            color: #065f46;
            margin-top: 20px;
        }

        .footer {
            text-align: center;
            color: #9ca3af;
            font-size: 12px;
            margin-top: 24px;
            border-top: 1px solid #f3f4f6;
            padding-top: 16px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>Pertanyaan Baru dari Halaman Kontak</h2>
    </div>
    <div class="content">
        <p>Halo Admin,</p>
        <p>Ada pesan baru masuk melalui halaman kontak <strong>{{ config('app.name') }}</strong>. Detail pengirim:</p>

        <div class="meta">
            <p><strong>Nama:</strong> {{ $senderName }}</p>
            <p><strong>Email:</strong> {{ $senderEmail }}</p>
            <p><strong>Subjek:</strong> {{ $inquirySubject }}</p>
            <p><strong>Waktu:</strong> {{ now()->setTimezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</p>
        </div>

        <p><strong>Isi Pesan:</strong></p>
        <div class="message-box">{{ $body }}</div>

        <div class="reply-note">
            Anda bisa langsung membalas email ini &mdash; balasan akan dikirim ke <strong>{{ $senderEmail }}</strong>.
        </div>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Email ini dikirim otomatis dari sistem.</p>
    </div>
</body>

</html>
