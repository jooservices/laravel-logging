# Setup

Install dependencies with Composer:

```bash
composer install
```

This package supports PHP 8.5 with Laravel 12.

Publish configuration in Laravel applications:

```bash
php artisan vendor:publish --tag=laravel-logging-config
```

Configure the MongoDB connection used by the package:

```env
ACTIVITY_LOG_CONNECTION=mongodb
ACTIVITY_LOG_COLLECTION=activity_logs
MONGODB_URI=mongodb://localhost:27017
```

Create supported MongoDB indexes:

```bash
php artisan activity-log:indexes
```

Validate the package runtime after configuration:

```bash
php artisan activity-log:doctor
php artisan activity-log:doctor --check-indexes
```

CaptainHook installs through Composer:

```bash
composer run post-install-cmd
```
