<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Institutional Repository File Storage (Cloudflare R2)

### Background

Institutional repository files (PDFs/eBooks served at `/api/repository/{id}/read`, `/inline`, `/download`) were originally stored on the production server's local disk (`storage/app/private/repositories`). This caused files to work for a short period after upload and then start 404ing, even though the database row for the item was intact. Root cause: the app is deployed to shared hosting via zip re-upload, and locally-stored files are not tracked in git — a redeploy (or hosting-side cleanup/quota limits) overwrites or wipes anything uploaded after the last zip was built. The database only stores the `file_path` string; it has no bearing on whether the physical file still exists on disk.

Fix: repository files are now stored on **Cloudflare R2** (S3-compatible object storage) instead of the local disk, decoupling file lifecycle from server deploys entirely.

### What changed in code

- Installed `league/flysystem-aws-s3-v3` (required for Laravel's S3-compatible driver).
- `App\Http\Controllers\Api\InstitutionalRepositoryController` now reads/writes/deletes files via `Storage::disk('s3')` instead of `Storage::disk('local')`.
- `read()`, `inline()`, and `download()` now stream the file from R2 (`->response()`, `->get()`, `->download()`) instead of resolving a local filesystem path — object storage has no real local path.
- `cover_image` uploads are unaffected — those remain on the local `public` disk.

### One-time setup (per environment)

1. In the Cloudflare dashboard: **R2 → create a bucket**, then **R2 → Manage API Tokens → create a token** with read/write access to that bucket. This gives you an Access Key ID, Secret Access Key, and your Account ID.
2. Set the following in that environment's `.env` (see `.env.example` for placeholders):

   ```
   AWS_ACCESS_KEY_ID=<your key>
   AWS_SECRET_ACCESS_KEY=<your secret>
   AWS_DEFAULT_REGION=auto
   AWS_BUCKET=<your bucket name>
   AWS_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
   AWS_USE_PATH_STYLE_ENDPOINT=true
   ```

   `AWS_USE_PATH_STYLE_ENDPOINT=true` is a hard requirement for R2, not optional — R2 doesn't support virtual-hosted-style bucket addressing the way AWS S3 does.

3. Deploy the code, then run `php artisan config:clear` (production caches config, so new env vars won't be picked up until cleared).
4. Run `php artisan repository:migrate-to-r2` once to copy any files still present on the server's local disk to R2 before fully cutting over — anything already wiped can't be recovered by this command, only what's still there.

### Migration command reference

`php artisan repository:migrate-to-r2` — iterates every `institutional_repositories` row with a `file_path`, and for each one:
- Skips it if the file already exists on R2.
- Copies it from the local disk to R2 if found locally.
- Warns and skips if the file exists on neither disk (already lost).

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

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
