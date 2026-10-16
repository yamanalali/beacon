<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## Demo accounts (local only)

Set `DEMO_PASSWORD` in your local `.env`, keep `APP_ENV=local`, then run:

```
php artisan db:seed --class=DemoSeeder
```

This creates one active account per role (emails/roles are in `config/demo.php`) plus a demo enterprise with finance data. The seeder is idempotent and refuses to run outside `APP_ENV=local`. No passwords are stored in the repository: the password is whatever you set in `DEMO_PASSWORD`; the full local credentials list is kept on the developer machine in `Desktop\DEMO-ACCOUNTS.md`.

## Voice draft transaction (local Whisper)

Off by default (`AI_VOICE_ENABLED=false`). When it's on, the user uploads a short voice note. It gets transcribed and parsed into a **suggested** transaction that the user reviews and saves through the normal transaction form. Nothing is ever saved automatically, and the recording is deleted right after transcription, including when transcription fails.

- `AI_STT_DRIVER=whisper` (default) uses the local [whisper.cpp](https://github.com/ggml-org/whisper.cpp) CLI. The audio stays on the server, so it needs no `DataControl` approval.
- `AI_STT_DRIVER=provider` uses the external AI provider. It goes through `DataControl::assertCanShare('speech_to_text', ['voice_recording'])`, which is off by default.
- TODO: BUSINESS DECISION REQUIRED: languages (Arabic MSA + Levantine assumed), the STT provider (local Whisper assumed), and recording retention (immediate deletion assumed).
- whisper.cpp reads WAV/MP3/OGG/FLAC. Browser `webm` recordings may need converting (ffmpeg) or recording as WAV.

```
AI_VOICE_ENABLED=true
AI_STT_DRIVER=whisper
WHISPER_PATH=whisper-cli
WHISPER_MODEL=/full/path/ggml-small.bin
WHISPER_LANGUAGE=ar
```

### Windows install (whisper.cpp)

1. From https://github.com/ggml-org/whisper.cpp/releases, download `whisper-bin-x64.zip` and unzip it to `C:\whisper`. It contains `whisper-cli.exe`.
2. Download a multilingual model (it must not be an `.en` model), e.g. `ggml-small.bin` from https://huggingface.co/ggerganov/whisper.cpp/tree/main, into `C:\whisper\models`.
3. In `.env`, set `WHISPER_PATH="C:\whisper\whisper-cli.exe"` and `WHISPER_MODEL="C:\whisper\models\ggml-small.bin"`, plus `AI_VOICE_ENABLED=true`.
4. Test it with `C:\whisper\whisper-cli.exe -m C:\whisper\models\ggml-small.bin -f sample.wav -l ar -nt`.
5. Run `php artisan config:clear`.

## AI data sharing (section 54)

AI features stay **disabled in production** until `allowed_data` in `config/operations.php` is filled with the approved fields for each processor (`ai_assistant`, `ocr`, `insights`) and the processor is approved (`BEACON_DPA_*_APPROVED`). Every outbound AI call goes through `DataControl::assertCanShare`; when sharing is not approved no provider call is made and the feature falls back to its manual / "AI unavailable" path.

Which fields are approved per processor is a pending business decision (`TODO: BUSINESS DECISION REQUIRED`).

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
