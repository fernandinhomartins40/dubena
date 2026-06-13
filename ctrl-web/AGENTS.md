# AGENTS.md

## Project Overview

This is a legacy Laravel 5.4 application running on PHP 7.4+, using Oracle Database as the primary database backend and Blade templates for the frontend.

The application includes features related to PDFs, Excel exports/imports, boleto generation, NF-e/SPED handling, barcodes, image processing, websockets, JWT/authentication, and payment integrations.

## Core Stack

- PHP >= 7.4
- Laravel Framework 5.4.\*
- Oracle Database
- Blade frontend
- Laravel Passport 3.x
- PHPUnit 5.7
- Composer-based dependency management

## Important Constraints

This is a legacy Laravel application. Do not assume modern Laravel conventions are available.

Avoid using features introduced after Laravel 5.4 unless already present in the codebase.

Do not introduce dependencies that require newer Laravel, Symfony, PHP, or PHPUnit versions without checking compatibility first.

When modifying code, preserve existing patterns unless there is a clear reason to improve them.

## Database Guidelines

The application uses Oracle via:

- `yajra/laravel-oci8`
- `doctrine/dbal`

When working with database code:

- Do not assume MySQL behavior.
- Be careful with sequences, triggers, schemas, synonyms, and Oracle-specific date handling.
- Avoid MySQL-specific SQL such as `LIMIT`, `AUTO_INCREMENT`, backticks, or `ON DUPLICATE KEY`.
- Prefer Laravel query builder or existing repository/model patterns where possible.
- Validate raw SQL against Oracle syntax.
- Be careful with case sensitivity in table and column names.
- Do not casually rename columns, indexes, constraints, or sequences.

For pagination or limiting rows, use Oracle-compatible approaches already used in the project.

## Laravel 5.4 Guidelines

Laravel 5.4 differs significantly from modern Laravel.

Before changing framework-related code, remember:

- Middleware, service providers, guards, policies, events, jobs, and mailables may follow older conventions.
- Modern helpers or methods may not exist.
- Route model binding behavior may differ from newer Laravel versions.
- Avoid using modern syntax like anonymous migrations unless already supported by the project.
- Do not assume Laravel Mix, Vite, Sanctum, modern factories, or modern notification APIs are available.

## Frontend Guidelines

The frontend uses Blade templates.

When editing views:

- Preserve existing Blade structure and naming conventions.
- Avoid introducing frontend build tooling unless already present.
- Keep JavaScript changes compatible with the existing asset pipeline.
- Do not assume modern SPA tooling is available.
- Escape output unless existing code intentionally renders trusted HTML.
- Be careful with forms generated through `laravelcollective/html`.

## Authentication and API Guidelines

The project uses `laravel/passport` version 3.x and `lcobucci/jwt` 3.3.3.

When changing authentication code:

- Do not upgrade Passport or JWT behavior casually.
- Preserve existing guards, providers, token lifetimes, scopes, and middleware.
- Be careful with OAuth token storage and Oracle compatibility.
- Avoid introducing Sanctum or modern Laravel auth assumptions.

## PDF, Report, and Document Generation

The project uses multiple document-generation libraries:

- `mpdf/mpdf`
- `barryvdh/laravel-dompdf`
- `phpoffice/phpspreadsheet`
- `phpoffice/phppresentation`
- `maatwebsite/excel` 2.1
- `milon/barcode`
- `tecnickcom/tc-lib-barcode`

When modifying exports or generated documents:

- Identify which library the existing feature uses before changing it.
- Do not replace one PDF or Excel library with another unless explicitly requested.
- Preserve output formats, filenames, encodings, layouts, and paper sizes.
- Be careful with memory usage for large reports.
- Test with realistic Oracle data volumes where possible.

## Fiscal, Payment, and Banking Features

The application includes dependencies for Brazilian fiscal, boleto, banking, and payment integrations:

- `eduardokum/laravel-boleto`
- `nfephp-org/sped-nfe`
- `nfephp-org/sped-da`
- `developersrede/erede-php`
- `beccha/ofxparser`

When working in these areas:

- Treat changes as high-risk.
- Preserve numeric precision, dates, document numbers, certificates, signatures, and encoding.
- Do not change fiscal XML structures without validating against the applicable standard.
- Do not refactor payment or boleto generation casually.
- Avoid changing rounding logic unless explicitly required and tested.

## Websocket Guidelines

The project uses:

- `cboden/ratchet`
- `ratchet/pawl`

When changing websocket code:

- Preserve event loop behavior.
- Avoid blocking operations in websocket handlers.
- Be careful with long-running PHP processes and memory leaks.
- Do not assume Laravel queue or broadcasting features behave like modern Laravel versions.

## Testing Guidelines

Development dependencies include:

- PHPUnit ~5.7
- Mockery 0.9.\*
- Faker ~1.4

When writing tests:

- Use PHPUnit 5-compatible syntax.
- Do not use modern PHPUnit features such as attributes.
- Prefer existing test structure and helper methods.
- Mock external services, payments, fiscal APIs, file storage, and Oracle-heavy integrations where practical.
- Add regression tests for bug fixes when feasible.

## Code Style

Follow the existing code style in the repository.

General rules:

- Keep changes small and focused.
- Prefer explicit, readable code over clever abstractions.
- Avoid broad rewrites.
- Avoid upgrading dependencies as part of unrelated changes.
- Do not introduce PHP features unsupported by the deployed runtime.
- Preserve public method signatures unless all callers are updated.
- Be careful with global helpers, facades, and service container bindings.

## Composer Guidelines

Before modifying `composer.json`:

- Check Laravel 5.4 compatibility.
- Check PHP 7.4 compatibility.
- Check transitive dependency constraints.
- Avoid replacing locked legacy packages without a migration plan.
- Do not run broad dependency upgrades unless explicitly requested.

Known important dependencies include:

- `laravel/framework: 5.4.*`
- `yajra/laravel-oci8: 5.4.*`
- `maatwebsite/excel: ~2.1.0`
- `phpunit/phpunit: ~5.7`
- `laravel/passport: ^3.0`
- `guzzlehttp/guzzle: ^6.3`

## File Storage Guidelines

The application uses `league/flysystem` 1.x.

When modifying storage logic:

- Do not assume Flysystem 2.x or 3.x APIs.
- Preserve disk configuration.
- Be careful with file permissions, temporary files, generated PDFs, uploaded images, and cleanup routines.

## Image Handling

The project uses `intervention/image`.

When modifying image features:

- Preserve existing resizing, encoding, quality, and storage behavior.
- Validate uploaded files before processing.
- Avoid loading very large images into memory without safeguards.

## Safe Change Checklist

Before finishing any change, verify:

- Code is compatible with Laravel 5.4.
- Code is compatible with PHP 7.4.
- SQL is Oracle-compatible.
- Blade views still render correctly.
- Existing routes, controllers, and service providers are not broken.
- Fiscal/payment/document-generation behavior is preserved.
- Tests use PHPUnit 5-compatible syntax.
- No unnecessary dependency upgrades were introduced.

## Preferred Agent Behavior

When assisting in this repository:

1. Inspect existing patterns before proposing changes.
2. Prefer minimal patches.
3. Explain any legacy compatibility risks.
4. Flag Oracle-specific assumptions.
5. Avoid modern Laravel shortcuts unless verified.
6. Treat fiscal, payment, boleto, NF-e, and accounting logic as high-risk.
7. Do not perform large refactors unless explicitly requested.
8. When uncertain, preserve existing behavior.

## Commands

Common commands may include:

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan route:list
vendor/bin/phpunit
```

Use project-specific scripts if they exist.

Do not assume modern commands or tooling are available.
