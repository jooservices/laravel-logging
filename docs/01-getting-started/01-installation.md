# Installation

```bash
composer require jooservices/laravel-logging
php artisan vendor:publish --tag=laravel-logging-config
php artisan activity-log:indexes
php artisan activity-log:doctor
```

Requirements: PHP 8.5+, Laravel 12/13, MongoDB via `mongodb/laravel-mongodb`,
`jooservices/dto` ^3, `jooservices/laravel-repository` ^4.
