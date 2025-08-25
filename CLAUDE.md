# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

AKBag Backend is a Laravel-based RESTful API for managing a product catalog system with a three-tier hierarchy: Collections → Albums → Images. It features a specialized Yupoo importer for bulk importing product images and a Filament admin panel for content management.

### Key Features
- RESTful API with Laravel Sanctum authentication
- Yupoo.com scraping and bulk import system
- AWS S3 image storage with automatic migration
- Filament admin panel for content management
- Three-tier content hierarchy (Collections/Albums/Images)

## Development Commands

### Essential Commands
```bash
# Start development server with full stack (server + queue + logs + assets)
composer run dev

# Run tests (clears config first)
composer run test

# Run single test file
php artisan test tests/Feature/YupooImportTest.php

# Run specific test method
php artisan test --filter test_can_import_yupoo_albums

# Code formatting and linting  
./vendor/bin/pint

# Clear all caches
php artisan optimize:clear
```

### Yupoo Import System
```bash
# Import albums from Yupoo
php artisan yupoo:import [--max=50] [--page=1] [--force] [-v]

# Test Yupoo connection
php artisan yupoo:test -v

# Test specific Yupoo components
php artisan yupoo:simple-test
php artisan yupoo:test-connection
php artisan yupoo:test-importer
```

### S3 Image Storage Commands
```bash
# ✅ PRODUCTION-TESTED S3 Migration Workflow:

# 1. Pre-validation (comprehensive S3 readiness check)
php artisan images:validate-s3 --test-upload

# 2. Migration dry-run (safe testing)
php artisan images:migrate-to-s3 --dry-run --batch-size=10

# 3. Production migration (with all safety features)
php artisan images:migrate-to-s3 --batch-size=5 --preserve-local

# 4. Emergency rollback (download back from S3)
php artisan images:rollback-from-s3 --dry-run
php artisan images:rollback-from-s3 --batch-size=10

# Advanced options:
# Skip pre-validation (not recommended for production)
php artisan images:migrate-to-s3 --skip-validation
# Large dataset migration (memory optimized)
php artisan images:migrate-to-s3 --batch-size=2 --preserve-local

# Database inspection
php artisan albums:list
php artisan images:list
```

### Database and Diagnostics
```bash
# Check database connection and structure
php artisan database:check
php artisan database:inspect

# Verify Filament resources
php artisan check:filament
```

## Architecture

### Core Services

**YupooService** (`app/Services/YupooService.php`)
- Web scraper for Yupoo.com album and image import
- Handles concurrent downloads, retry logic, and bulk database operations
- Configurable via `config/yupoo.php`
- Uses Symfony DomCrawler and Guzzle HTTP with connection pooling

**Image Storage System**
- **Current**: AWS S3 storage with public URLs via `Storage::disk('s3')`
- **Migration**: Production-tested commands with comprehensive safety features
- **URL Generation**: `Image::getImageUrlAttribute()` auto-detects storage type and generates appropriate URLs
- **Backward Compatible**: Supports both local and S3 storage seamlessly

### Data Models & Relationships
```
Collection (1:many) → Album (1:many) → Image
```

**Collection**: Top-level grouping (e.g., "Classic Collection")  
**Album**: Product series within collection (e.g., "Executive Briefcases")  
**Image**: Individual product images with S3 storage paths

### Admin Interface
- **Filament v3.3** admin panel at `/admin`
- Resources: `CollectionResource`, `AlbumResource`, `ImageResource`
- Custom relation managers for nested content management

### API Structure
- Base URL: `/api/`
- **Collections**: `/collections`, `/collections/{id}`, `/collections/{id}/albums`
- **Albums**: `/albums`, `/collections/{collectionId}/albums/{albumId}`
- **Images**: `/images`, `/albums/{albumId}/images`
- **Auth**: Laravel Sanctum with `/login`, `/register`, `/logout`

## Configuration Files

### Yupoo Configuration (`config/yupoo.php`)
Critical settings for import performance and behavior:
- `import.concurrent_downloads`: Parallel downloads (default: 5)
- `import.bulk_insert_size`: Database batch size (default: 20)
- `import.request_delay`: Anti-rate-limiting delay
- `storage.images`: S3 storage path pattern

### Environment Variables
**AWS S3 (Required for production):**
```
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
FILESYSTEM_DISK=s3
```

**Yupoo Import (Optional overrides):**
```
YUPOO_BASE_URL=https://297228164.x.yupoo.com
YUPOO_MAX_ALBUMS=50
YUPOO_CONCURRENT_DOWNLOADS=5
```

## Testing & Quality

**Test Framework**: Pest PHP with Laravel plugin
```bash
# Feature tests include API endpoints and Yupoo import
tests/Feature/YupooImportTest.php
tests/Feature/Auth/
tests/Feature/ProfileTest.php

# Unit tests for components
tests/Unit/
```

**Code Style**: Laravel Pint (PHP CS Fixer) - run `./vendor/bin/pint`

## S3 Storage Migration System

**✅ PRODUCTION-TESTED**: Complete migration system with enterprise-grade safety features.

### Three-Command Production Workflow:
1. **`php artisan images:validate-s3 --test-upload`**
   - Validates AWS credentials and bucket connectivity
   - Tests file upload/download operations
   - Checks URL generation and bucket permissions
   - Provides performance estimates and recommendations

2. **`php artisan images:migrate-to-s3 --preserve-local --batch-size=5`**
   - Memory-efficient chunked processing (prevents OOM on large datasets)
   - File integrity verification (size matching after upload)
   - Performance monitoring (logs slow uploads >5s)
   - Optional local file preservation as backup
   - Comprehensive error handling with detailed reporting

3. **`php artisan images:rollback-from-s3 --batch-size=10`**
   - Emergency rollback capability
   - Downloads all images from S3 back to local storage
   - File integrity verification during rollback
   - Manual .env update instruction for complete rollback

### Enterprise Safety Features:
- **Pre-Migration Validation**: 7-step validation process including AWS credential testing
- **Memory Optimization**: Uses `chunk()` instead of `get()` for large datasets
- **File Integrity**: SHA and size verification for every migrated file
- **Performance Monitoring**: Identifies and logs performance bottlenecks
- **Non-Destructive**: Database `image_path` fields unchanged (enables rollback)
- **Batch Processing**: Configurable batch sizes for different server specifications
- **Error Recovery**: Comprehensive exception handling with detailed error messages

### URL Generation System:
- **Smart Detection**: `Image::getImageUrlAttribute()` auto-detects S3 vs local storage
- **URL Format**: Generates proper S3 URLs: `https://bucket.s3.region.amazonaws.com/path`
- **API Integration**: `ImageResource` returns correct URLs for frontend consumption
- **Backward Compatible**: Seamlessly works with both storage types

### Production Requirements:
- **S3 Bucket**: Public read permissions for direct image serving
- **Environment**: `FILESYSTEM_DISK=s3` for new uploads
- **AWS Credentials**: Valid `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`
- **Testing**: Always run validation and dry-run before production migration

## Debugging & Logs

**Yupoo Import Debugging:**
- Debug logs saved to `storage/logs/yupoo_debug_*.html`
- Real-time logging: `php artisan pail --timeout=0`
- Verbose import: `php artisan yupoo:import -vvv`

**S3 Migration Debugging:**
- Migration logs: Check Laravel logs during migration process
- Performance issues: Look for "Slow upload detected" warnings
- Validation failures: Run `php artisan images:validate-s3 --test-upload` for detailed diagnosis
- URL generation issues: Verify `FILESYSTEM_DISK=s3` and AWS credentials

**Common Issues:**
- **S3 permissions**: Check bucket policy allows public read access (`{"Effect":"Allow","Principal":"*","Action":"s3:GetObject"}`)
- **Memory exhaustion**: Use smaller batch sizes (`--batch-size=2`) for large datasets
- **Yupoo rate limiting**: Increase `request_delay` in config
- **Image download failures**: Check concurrent download limits and timeouts
- **URL generation failures**: Ensure S3 credentials are valid and bucket exists