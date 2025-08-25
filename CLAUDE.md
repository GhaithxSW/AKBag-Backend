# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Essential Commands

### Development
```bash
# Start development server
php artisan serve

# Run the full development environment (includes server, queue, logs, and vite)
composer run dev

# Run tests
composer run test
# OR
php artisan test

# Run code formatting/linting
php artisan pint

# Check database connection
php artisan yupoo:check-db

# Clear various caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Database Operations
```bash
# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Fresh migration with seeding
php artisan migrate:fresh --seed

# Check database connection and tables
php artisan yupoo:inspect-db
```

### Yupoo Import System (Core Feature)
```bash
# Import albums from Yupoo (optimized with multi-page support)
php artisan yupoo:import

# Import with debugging and progress tracking
php artisan yupoo:import --debug

# Import limited number of albums
php artisan yupoo:import --limit=5

# Test Yupoo connection and functionality
php artisan yupoo:test-connection
php artisan yupoo:test-service
```

### Testing
```bash
# Run all tests
php artisan test

# Run tests with verbose output
php artisan test --verbose

# Run specific test files
php artisan test tests/Feature/YupooImportTest.php
```

## Architecture Overview

### Core Models & Relationships
- **Collections** → **Albums** → **Images** (hierarchical structure)
- `Collection` model: Groups of albums (e.g., "Classic Collection")
- `Album` model: Groups of related images with cover image support
- `Image` model: Individual product images with metadata and original_url tracking

### Key Services
- **YupooService**: Heavily optimized web scraping service for importing from Yupoo
  - Multi-page support (imports ALL pages, not just first page)
  - Concurrent batch downloads (5 simultaneous downloads)
  - Smart duplicate detection with O(1) lookup caching
  - Bulk database operations (20 records per batch)
  - Automatic retry with exponential backoff
  - Real-time progress tracking

### API Structure
- RESTful API using Laravel Sanctum authentication
- Bruno API collection in `/bruno/` folder for testing
- Nested resource endpoints: `/api/collections/{id}/albums/{id}/images`
- Filament admin panel at `/admin`

### Database Design
- Optimized indexes for Yupoo import performance (see migration `2025_08_22_173139_add_indexes_for_yupoo_optimization.php`)
- `original_url` column on images table for duplicate detection
- Foreign key relationships with cascading deletes

## Key Configuration Files

### Yupoo Import Configuration
- `config/yupoo.php` - Performance-tuned configuration with environment variable support
- Key settings: concurrent_downloads, batch_size, request delays, multi-page limits
- Environment variables (all optional):
  - `YUPOO_CONCURRENT_DOWNLOADS=5`
  - `YUPOO_BATCH_SIZE=8`
  - `YUPOO_REQUEST_DELAY=1`
  - `YUPOO_MAX_PAGES_PER_ALBUM=50`

### Filament Admin Panel
- Admin authentication via custom controller
- Resources for Collections, Albums, Images with full CRUD
- Located in `app/Filament/Resources/`

## Critical Implementation Details

### Yupoo Import Performance Optimizations
The Yupoo import system has been heavily optimized and is the core feature of this application:

1. **Multi-page Support**: Automatically detects and processes ALL pages in an album (typically 6x more images than single-page import)

2. **Concurrent Downloads**: Uses Guzzle async HTTP client to download 5 images simultaneously instead of sequentially

3. **Smart Duplicate Detection**: Loads all existing image URLs into memory once for O(1) lookup time instead of individual database queries

4. **Bulk Database Operations**: Inserts 20 records at a time instead of individual INSERT statements

5. **Error Resilience**: Exponential backoff retry strategy with proper error classification

### File Storage
- Images stored in `storage/app/private/albums/images/`
- Cover images in `storage/app/private/albums/covers/`
- Public access via storage disk configuration

### Testing Framework
- Uses Pest PHP testing framework
- Feature tests in `tests/Feature/`
- Specific Yupoo import tests available

## Common Development Tasks

### Adding New Yupoo Import Features
1. Modify `YupooService.php` for core logic
2. Update `ImportYupooAlbums.php` command for CLI interface
3. Add configuration to `config/yupoo.php`
4. Run tests with `php artisan test tests/Feature/YupooImportTest.php`

### Database Changes
1. Create migration: `php artisan make:migration migration_name`
2. Always add indexes for performance-critical columns
3. Test migration with `php artisan migrate` and rollback with `php artisan migrate:rollback`

### API Development
1. Controllers in `app/Http/Controllers/Api/`
2. Use API resources for response formatting
3. Test with Bruno collection in `/bruno/AKBag/`
4. Authentication via Laravel Sanctum

## Production Considerations

### Performance
- The Yupoo import system is optimized for production use with proper rate limiting
- Database indexes are crucial for large datasets
- Consider queue workers for large import operations

### Monitoring
- Laravel logs in `storage/logs/laravel.log`
- Yupoo service has extensive logging with configurable debug levels
- Use `php artisan pail` for real-time log monitoring

### Security
- API authentication via Sanctum tokens
- Filament admin requires authentication
- File uploads stored privately by default