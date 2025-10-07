# AKBag - Technical Documentation

## Table of Contents
1. [Project Overview](#project-overview)
2. [System Architecture](#system-architecture)
3. [Technology Stack](#technology-stack)
4. [Database Schema](#database-schema)
5. [API Documentation](#api-documentation)
6. [Yupoo Import System](#yupoo-import-system)
7. [Filament Admin Panel](#filament-admin-panel)
8. [File Storage & AWS S3](#file-storage--aws-s3)
9. [Development Guide](#development-guide)
10. [Deployment Information](#deployment-information)
11. [Testing](#testing)
12. [Configuration](#configuration)
13. [Security](#security)
14. [Monitoring & Logging](#monitoring--logging)

---

## Project Overview

**AKBag** is a full-stack e-commerce platform for managing and displaying product catalogs. The system features:

- **Backend API**: Laravel 12 RESTful API with Sanctum authentication
- **Admin Dashboard**: Filament 3.3 admin panel for content management
- **Web Scraping**: Automated Yupoo product import system with advanced optimization
- **Cloud Storage**: AWS S3 integration for scalable image storage
- **Frontend**: React-based customer-facing website (separate repository)

### Key Features
- Multi-level product hierarchy (Collections → Albums → Images)
- Automated web scraping with concurrent downloads and bulk operations
- Real-time progress tracking for imports
- RESTful API with public read-only and protected admin endpoints
- Featured images/products management
- Comprehensive admin panel with CRUD operations

---

## System Architecture

### High-Level Architecture
```
┌─────────────────────────────────────────────────────────┐
│                    Frontend (React)                      │
│              https://akbags.elev8xr.com/                │
└─────────────────────────────────────────────────────────┘
                            │
                            │ REST API
                            ▼
┌─────────────────────────────────────────────────────────┐
│            Backend (Laravel 12 + Filament)               │
│            https://akbag.elev8xr.com/admin/             │
│                                                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐ │
│  │   REST API   │  │    Admin     │  │    Yupoo     │ │
│  │ (Sanctum)    │  │  Dashboard   │  │   Importer   │ │
│  └──────────────┘  └──────────────┘  └──────────────┘ │
└─────────────────────────────────────────────────────────┘
                    │               │
                    │               │
            ┌───────┴───────┐      │
            ▼               ▼      ▼
    ┌────────────┐  ┌────────────────┐
    │   SQLite   │  │   AWS S3       │
    │  Database  │  │   Storage      │
    └────────────┘  └────────────────┘
```

### Core Components

#### 1. Models & Relationships
```
Collection (1) ──→ (M) Album (1) ──→ (M) Image
                                            │
                                            └──→ original_url (for duplicate detection)
```

- **Collection**: Top-level grouping (e.g., "Classic Collection", "Premium Collection")
- **Album**: Product grouping with cover image
- **Image**: Individual product images with metadata
- **FeaturedImage**: Standalone featured products for homepage/promotions

#### 2. Services
- **YupooService**: Core web scraping service with advanced optimizations
  - Multi-page support (imports ALL pages, not just first page)
  - Concurrent batch downloads (5 simultaneous downloads)
  - Smart duplicate detection with O(1) lookup caching
  - Bulk database operations (20 records per batch)
  - Automatic retry with exponential backoff

#### 3. Controllers (API)
- `AlbumController`: Album CRUD and nested routes
- `CollectionController`: Collection CRUD and nested routes
- `ImageController`: Image CRUD operations
- `FeaturedImageController`: Featured products management
- `AuthController`: Registration, login, logout (Sanctum)

#### 4. Admin Panel (Filament)
- `AlbumResource`: Album management with relationship manager
- `CollectionResource`: Collection management
- `ImageResource`: Image management
- `FeaturedImageResource`: Featured images management

---

## Technology Stack

### Backend
| Technology | Version | Purpose |
|-----------|---------|---------|
| PHP | 8.2+ | Runtime environment |
| Laravel | 12.0 | Web application framework |
| Laravel Sanctum | 4.0 | API authentication |
| Filament | 3.3 | Admin panel |
| Symfony DomCrawler | 7.3 | HTML parsing for web scraping |
| Guzzle HTTP | - | HTTP client for async operations |
| Pest PHP | 3.8 | Testing framework |

### Database & Storage
- **SQLite**: Local development & testing
- **AWS S3**: Production file storage
- **Queue**: Database-based queue system

### Development Tools
- **Laravel Pint**: Code formatting/linting
- **Laravel Pail**: Real-time log monitoring
- **Composer**: PHP dependency management
- **Bruno**: API testing collection

### Server Requirements
- PHP 8.2 or higher
- Extensions: `ext-intl`, `ext-zip`
- Composer
- SQLite (development) or MySQL/PostgreSQL (production)

---

## Database Schema

### Tables Overview

#### collections
| Column | Type | Attributes |
|--------|------|-----------|
| id | bigint | Primary Key |
| name | string | Required |
| description | text | Nullable |
| cover_image | string | Nullable, S3 path |
| created_at | timestamp | - |
| updated_at | timestamp | - |

**Relationships**: `hasMany(Album)`

#### albums
| Column | Type | Attributes |
|--------|------|-----------|
| id | bigint | Primary Key |
| collection_id | bigint | Foreign Key → collections |
| title | string | Required |
| description | text | Nullable |
| cover_image | string | Nullable, S3 path |
| created_at | timestamp | - |
| updated_at | timestamp | - |

**Indexes**:
- `collection_id` (for collection lookup)

**Relationships**:
- `belongsTo(Collection)`
- `hasMany(Image)`

#### images
| Column | Type | Attributes |
|--------|------|-----------|
| id | bigint | Primary Key |
| album_id | bigint | Foreign Key → albums |
| title | string | Required |
| image_path | string | Required, S3 path |
| description | text | Nullable |
| original_url | string | Nullable, for duplicate detection |
| created_at | timestamp | - |
| updated_at | timestamp | - |

**Indexes**:
- `album_id` (for album lookup)
- `original_url` (for duplicate detection during imports)

**Relationships**: `belongsTo(Album)`

#### featured_images
| Column | Type | Attributes |
|--------|------|-----------|
| id | bigint | Primary Key |
| title | string | Required |
| image_path | string | Required, S3 path |
| description | text | Nullable |
| created_at | timestamp | - |
| updated_at | timestamp | - |

#### users
| Column | Type | Attributes |
|--------|------|-----------|
| id | bigint | Primary Key |
| name | string | Required |
| email | string | Unique, Required |
| email_verified_at | timestamp | Nullable |
| password | string | Required (hashed) |
| is_admin | boolean | Default: false |
| remember_token | string | Nullable |
| created_at | timestamp | - |
| updated_at | timestamp | - |

---

## API Documentation

### Base URL
- **Production**: `https://akbag.elev8xr.com/api`
- **Local Development**: `http://localhost:8000/api`

### Authentication
API uses Laravel Sanctum for token-based authentication.

#### Register
```http
POST /api/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

#### Login
```http
POST /api/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  },
  "token": "1|abcdef..."
}
```

#### Logout
```http
POST /api/logout
Authorization: Bearer {token}
```

### Public Endpoints (Read-Only)

#### Collections

**List all collections**
```http
GET /api/collections
```

**Get single collection**
```http
GET /api/collections/{id}
```

**Get albums in collection**
```http
GET /api/collections/{id}/albums
```

**Get specific album in collection**
```http
GET /api/collections/{collectionId}/albums/{albumId}
```

#### Albums

**List all albums**
```http
GET /api/albums
```

**Get single album**
```http
GET /api/albums/{id}
```

**Get images in album**
```http
GET /api/albums/{albumId}/images
```

#### Images

**List all images**
```http
GET /api/images
```

**Get single image**
```http
GET /api/images/{id}
```

#### Featured Images

**List featured images**
```http
GET /api/featured-images
```

**Get single featured image**
```http
GET /api/featured-images/{id}
```

### Protected Endpoints (Admin Only)

All protected endpoints require:
```http
Authorization: Bearer {token}
```

#### Collections (Admin)

**Create collection**
```http
POST /api/collections
Content-Type: application/json

{
  "name": "New Collection",
  "description": "Collection description"
}
```

**Update collection**
```http
PUT /api/collections/{id}
Content-Type: application/json

{
  "name": "Updated Collection",
  "description": "Updated description"
}
```

**Delete collection**
```http
DELETE /api/collections/{id}
```

#### Albums (Admin)

**Create album**
```http
POST /api/albums
Content-Type: application/json

{
  "collection_id": 1,
  "title": "New Album",
  "description": "Album description"
}
```

**Update album**
```http
PUT /api/albums/{id}
Content-Type: application/json

{
  "title": "Updated Album",
  "description": "Updated description"
}
```

**Delete album**
```http
DELETE /api/albums/{id}
```

#### Images (Admin)

**Create image**
```http
POST /api/images
Content-Type: application/json

{
  "album_id": 1,
  "title": "Product Image",
  "description": "Image description"
}
```

**Update image**
```http
PUT /api/images/{id}
Content-Type: application/json

{
  "title": "Updated Image",
  "description": "Updated description"
}
```

**Delete image**
```http
DELETE /api/images/{id}
```

#### Featured Images (Admin)

Similar CRUD operations available for featured images.

### Response Format

All API responses follow Laravel's standard format:

**Success Response:**
```json
{
  "data": {
    "id": 1,
    "name": "Collection Name",
    "description": "Description",
    "cover_image_url": "https://bucket.s3.amazonaws.com/path/to/image.jpg"
  }
}
```

**Error Response:**
```json
{
  "message": "Error message",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

---

## Yupoo Import System

The **Yupoo Import System** is the core feature of AKBag, designed to automatically scrape and import product catalogs from Yupoo photo hosting.

### Overview

**Source**: `https://297228164.x.yupoo.com`

The importer features:
- **Multi-page support**: Imports ALL pages from albums (typically 6x more images)
- **Concurrent downloads**: 5 simultaneous image downloads
- **Bulk operations**: 20 records per database batch
- **Smart caching**: O(1) duplicate detection
- **Progress tracking**: Real-time import progress
- **Error resilience**: Exponential backoff retry strategy

### Architecture

```
┌──────────────────────────────────────────────────────────┐
│           ImportYupooAlbums Command                       │
│         (CLI with progress indicators)                    │
└──────────────────────────────────────────────────────────┘
                        │
                        ▼
┌──────────────────────────────────────────────────────────┐
│              YupooService                                 │
│                                                           │
│  ┌────────────────────────────────────────────────────┐ │
│  │  1. Fetch Album List (Multi-page)                  │ │
│  │     - Parse paginated album listings               │ │
│  │     - Extract album URLs and metadata              │ │
│  └────────────────────────────────────────────────────┘ │
│                        │                                  │
│                        ▼                                  │
│  ┌────────────────────────────────────────────────────┐ │
│  │  2. Process Each Album                             │ │
│  │     - Fetch ALL pages from album (not just page 1) │ │
│  │     - Extract image URLs and metadata              │ │
│  │     - Download cover image to S3                   │ │
│  └────────────────────────────────────────────────────┘ │
│                        │                                  │
│                        ▼                                  │
│  ┌────────────────────────────────────────────────────┐ │
│  │  3. Batch Image Processing                         │ │
│  │     - Concurrent downloads (5 at once)             │ │
│  │     - O(1) duplicate check via cache               │ │
│  │     - Upload to S3                                 │ │
│  │     - Bulk database insert (20 records)            │ │
│  └────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────┘
```

### Configuration

File: `config/yupoo.php`

**Key Settings:**
```php
'base_url' => 'https://297228164.x.yupoo.com',

'import' => [
    'max_albums' => 50,                    // 0 for no limit
    'albums_per_page' => 20,               // Albums fetched per page
    'request_delay' => 1,                  // Seconds between requests
    'image_download_delay' => 100000,      // Microseconds (100ms)
    'batch_size' => 8,                     // Images per batch
    'concurrent_downloads' => 5,            // Simultaneous downloads
    'bulk_insert_size' => 20,              // Records per bulk insert
    'max_pages_per_album' => 50,           // Max pages to fetch per album
    'max_empty_pages' => 3,                // Stop after N empty pages
],

'http' => [
    'timeout' => 30,                       // Request timeout
    'connect_timeout' => 10,               // Connection timeout
    'verify' => false,                     // SSL verification
    'retry_times' => 3,                    // Retry attempts
    'retry_sleep' => 1000,                 // Milliseconds between retries
],
```

**Environment Variables:**
```env
YUPOO_BASE_URL=https://297228164.x.yupoo.com
YUPOO_MAX_ALBUMS=50
YUPOO_CONCURRENT_DOWNLOADS=5
YUPOO_BATCH_SIZE=8
YUPOO_REQUEST_DELAY=1
YUPOO_MAX_PAGES_PER_ALBUM=50
```

### Usage

#### Basic Import
```bash
php artisan yupoo:import
```

#### Import with Options
```bash
# Import with debugging
php artisan yupoo:import --debug

# Import limited number of albums
php artisan yupoo:import --limit=10

# Custom URL with timeout wrapper
timeout 120 php artisan yupoo:import --limit=5 --debug
```

### Performance Optimizations

#### 1. Multi-Page Album Support (6x Improvement)
**Before**: Only imported first page of each album
**After**: Automatically detects and imports ALL pages

```php
// Automatically processes all pages:
// - Page 1: 40 images
// - Page 2: 40 images
// - Page 3: 40 images
// Total: 120 images (vs 40 before)
```

#### 2. Concurrent Downloads (5x Speedup)
**Before**: Sequential downloads (1 at a time)
**After**: 5 simultaneous downloads using Guzzle async

```php
// Downloads 5 images simultaneously
$promises = [];
foreach ($batch as $item) {
    $promises[] = $this->asyncClient->getAsync($url);
}
$results = Utils::settle($promises)->wait();
```

#### 3. Smart Duplicate Detection (100x Faster)
**Before**: Database query per image (`SELECT WHERE original_url = ?`)
**After**: In-memory cache with O(1) lookup

```php
// Load all existing URLs once
$this->existingImageUrls = Image::pluck('original_url', 'original_url')->toArray();

// O(1) lookup
if (isset($this->existingImageUrls[$imageUrl])) {
    continue; // Skip duplicate
}
```

#### 4. Bulk Database Operations (20x Reduction in Queries)
**Before**: Individual INSERT per image
**After**: Batch insert 20 records at once

```php
// Accumulate records
$this->imageBatch[] = $imageData;

// Insert when batch full
if (count($this->imageBatch) >= 20) {
    Image::insert($this->imageBatch);
    $this->imageBatch = [];
}
```

### Progress Tracking

Real-time progress indicators:
```
📁 Albums: [████████████████░░░░] 72/100 (72%) Processing: Classic Bags
🖼️  Images: [████████████████████] 1250/1250 (100%)
⬇️  Download: [████████████████████] 800/800 (100%)

=== Import Summary ===
Total time: 245.7s
Total albums processed: 100
Albums imported: 95
Albums skipped: 5
Images imported: 1250
Images skipped: 320
Performance: 5.09 images/second
```

### Error Handling

**Exponential Backoff Retry:**
```php
retry(3, function() {
    // HTTP request
}, 1000); // 1 second initial delay
```

**Error Types:**
- Network timeouts (retry)
- Invalid HTML structure (skip, log)
- Duplicate images (skip silently)
- Storage failures (retry, then fail)

**Logging:**
```bash
# View logs in real-time
php artisan pail

# Check log files
storage/logs/laravel.log
```

### Performance Metrics

**Typical Import (50 albums):**
- Total images: ~2,000
- Total time: ~4-6 minutes
- Speed: ~6-8 images/second
- Network: ~100-150 HTTP requests
- Database: ~100 bulk inserts (vs 2,000 individual inserts)

---

## Filament Admin Panel

### Access
- **URL**: `https://akbag.elev8xr.com/admin/`
- **Credentials**: Contact project administrator for access credentials

### Resources

#### 1. Collections Resource
**Path**: `app/Filament/Resources/CollectionResource.php`

**Features:**
- List view with search and filters
- Create/Edit forms with:
  - Name (required)
  - Description (rich text editor)
  - Cover image upload (S3)
- Relationship manager for albums
- Deletion protection (prevents deletion if albums exist)

**Code Reference:** `CollectionResource.php:14-25`

#### 2. Albums Resource
**Path**: `app/Filament/Resources/AlbumResource.php`

**Features:**
- Collection selection dropdown
- Cover image upload
- Relationship manager for images
- Bulk actions
- Preview modal for images

#### 3. Images Resource
**Path**: `app/Filament/Resources/ImageResource.php`

**Features:**
- Album selection dropdown
- Image upload widget
- Image preview in table
- Bulk delete
- Original URL tracking (for Yupoo imports)

#### 4. Featured Images Resource
**Path**: `app/Filament/Resources/FeaturedImageResource.php`

**Features:**
- Standalone featured product management
- Image upload
- Title and description
- Used for homepage carousels/promotions

### Customizations

**Image Upload Widget:**
```php
FileUpload::make('cover_image')
    ->image()
    ->disk('s3')
    ->directory('albums/covers')
    ->visibility('public')
```

**Relationship Manager:**
```php
RelationManager::make('albums')
    ->relationship('albums')
    ->inverseRelationship('collection')
```

### User Management

**Admin Users:**
- `is_admin` column determines admin access
- Only admins can access Filament panel
- Authentication handled by `FilamentUser` interface

**Creating Admin User:**
```php
User::create([
    'name' => 'Admin',
    'email' => 'admin@admin.com',
    'password' => bcrypt('password'),
    'is_admin' => true,
]);
```

---

## File Storage & AWS S3

### Configuration

File: `config/filesystems.php`

```php
'default' => env('FILESYSTEM_DISK', 's3'),

'disks' => [
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
    ],
],
```

### Environment Variables

```env
FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name
AWS_URL=https://your-bucket.s3.us-east-1.amazonaws.com
AWS_USE_PATH_STYLE_ENDPOINT=false
```

### Storage Structure

```
s3://your-bucket/
├── albums/
│   └── covers/
│       ├── cover_001.jpg
│       ├── cover_002.jpg
│       └── ...
├── images/
│   ├── product_001.jpg
│   ├── product_002.jpg
│   └── ...
└── featured/
    ├── featured_001.jpg
    └── ...
```

### Usage in Models

**Collection Model:**
```php
public function getCoverImageUrlAttribute()
{
    return $this->cover_image
        ? Storage::disk('s3')->url($this->cover_image)
        : null;
}
```

**Album Model:**
```php
public function getCoverImageUrlAttribute()
{
    return $this->cover_image
        ? Storage::disk('s3')->url($this->cover_image)
        : null;
}
```

**Image Model:**
```php
public function getImageUrlAttribute()
{
    return $this->image_path
        ? Storage::disk('s3')->url($this->image_path)
        : null;
}
```

### Local Development

For local development without S3:

```env
FILESYSTEM_DISK=local
```

Files stored in `storage/app/private/`

---

## Development Guide

### Prerequisites
- PHP 8.2+
- Composer
- SQLite (or MySQL/PostgreSQL)
- Git

### Installation

1. **Clone Repository**
```bash
git clone <repository-url>
cd AKBag-Backend
```

2. **Install Dependencies**
```bash
composer install
```

3. **Environment Setup**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Database Setup**
```bash
# Create SQLite database
touch database/database.sqlite

# Run migrations
php artisan migrate

# (Optional) Seed database
php artisan db:seed
```

5. **Create Admin User**
```bash
php artisan tinker
>>> User::create(['name' => 'Admin', 'email' => 'admin@admin.com', 'password' => bcrypt('password'), 'is_admin' => true]);
```

6. **Storage Link**
```bash
php artisan storage:link
```

### Development Server

**Option 1: Basic**
```bash
php artisan serve
```
Access at: `http://localhost:8000`

**Option 2: Full Development Environment**
```bash
composer run dev
```
Runs concurrently:
- Server: `php artisan serve`
- Queue: `php artisan queue:listen`
- Logs: `php artisan pail`
- Vite: `npm run dev` (if frontend assets exist)

### Essential Commands

**Development:**
```bash
# Start server
php artisan serve

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Code formatting
php artisan pint
# Or: ./vendor/bin/pint
```

**Database:**
```bash
# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Fresh migration with seed
php artisan migrate:fresh --seed

# Check database connection
php artisan yupoo:check-db

# Inspect database
php artisan yupoo:inspect-db
```

**Yupoo Import:**
```bash
# Basic import
php artisan yupoo:import

# Import with debugging
php artisan yupoo:import --debug

# Import limited albums
php artisan yupoo:import --limit=5

# Test connection
php artisan yupoo:test-connection
php artisan yupoo:test-service
```

**Testing:**
```bash
# Run all tests
php artisan test
# Or: composer run test

# Run with verbose output
php artisan test --verbose

# Run specific test file
php artisan test tests/Feature/YupooImportTest.php
```

**Monitoring:**
```bash
# Real-time log monitoring
php artisan pail
```

### Code Style

**Laravel Pint:**
```bash
# Format all files
php artisan pint

# Check without fixing
php artisan pint --test

# Format specific directory
php artisan pint app/Services
```

### Git Workflow

**Branches:**
- `main`: Production-ready code
- Feature branches: `feature/feature-name`
- Bugfix branches: `bugfix/issue-description`

**Commit Convention:**
```
feat: Add featured images functionality
fix: Resolve duplicate image detection
refactor: Optimize Yupoo import performance
docs: Update API documentation
test: Add Yupoo import tests
```

---

## Deployment Information

### Production Environment

**Hosting**: Contabo VPS

**Frontend**: https://akbags.elev8xr.com/
**Backend/Admin**: https://akbag.elev8xr.com/admin/

### VPS Access

**Server Details:**
- Provider: Contabo VPS
- Contact project administrator for account credentials

**SSH Access:**
```bash
ssh root@<vps-ip-address>
# Credentials available in secure credential management system
```

### Deployment Process

1. **SSH into VPS**
```bash
ssh root@<vps-ip-address>
```

2. **Navigate to Project**
```bash
cd /path/to/AKBag-Backend
```

3. **Pull Latest Changes**
```bash
git pull origin main
```

4. **Update Dependencies**
```bash
composer install --no-dev --optimize-autoloader
```

5. **Run Migrations**
```bash
php artisan migrate --force
```

6. **Clear & Cache**
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

7. **Restart Services**
```bash
# Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# Restart Nginx
sudo systemctl restart nginx

# Restart queue workers (if using)
php artisan queue:restart
```

### Environment Configuration (Production)

**Key Environment Variables:**
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://akbag.elev8xr.com

DB_CONNECTION=sqlite
# Or use MySQL/PostgreSQL for production

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<production-key>
AWS_SECRET_ACCESS_KEY=<production-secret>
AWS_BUCKET=<production-bucket>
AWS_URL=<production-url>

QUEUE_CONNECTION=database
```

### Server Requirements

**Web Server**: Nginx or Apache
**PHP**: 8.2+
**Extensions**:
- ext-intl
- ext-zip
- ext-sqlite3 (or ext-mysql/ext-pgsql)

**Nginx Configuration:**
```nginx
server {
    listen 80;
    server_name akbag.elev8xr.com;
    root /var/www/AKBag-Backend/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### SSL Certificate (Let's Encrypt)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d akbag.elev8xr.com

# Auto-renewal (already configured)
sudo certbot renew --dry-run
```

### Queue Workers (Optional)

**Setup Supervisor:**
```ini
[program:akbag-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/AKBag-Backend/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/AKBag-Backend/storage/logs/worker.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start akbag-worker:*
```

### Monitoring

**Check Application Status:**
```bash
php artisan about
```

**Monitor Logs:**
```bash
tail -f storage/logs/laravel.log
```

**Check Queue:**
```bash
php artisan queue:monitor
```

---

## Testing

### Test Framework

**Pest PHP** (Laravel's modern testing framework)

### Test Structure

```
tests/
├── Feature/          # Feature/integration tests
│   ├── YupooImportTest.php
│   ├── AlbumTest.php
│   └── ...
├── Unit/             # Unit tests
│   └── ...
└── Pest.php          # Pest configuration
```

### Running Tests

**All Tests:**
```bash
php artisan test
# Or
composer run test
```

**Verbose Output:**
```bash
php artisan test --verbose
```

**Specific Test File:**
```bash
php artisan test tests/Feature/YupooImportTest.php
```

**Specific Test:**
```bash
php artisan test --filter=test_yupoo_import
```

**With Coverage:**
```bash
php artisan test --coverage
```

### Writing Tests

**Example Feature Test:**
```php
<?php

use App\Models\Collection;
use App\Models\Album;

test('can create album in collection', function () {
    $collection = Collection::factory()->create();

    $album = Album::create([
        'collection_id' => $collection->id,
        'title' => 'Test Album',
        'description' => 'Test Description',
    ]);

    expect($album)->toBeInstanceOf(Album::class)
        ->and($album->collection_id)->toBe($collection->id);
});
```

**API Test Example:**
```php
test('can fetch collections via API', function () {
    Collection::factory()->count(3)->create();

    $response = $this->getJson('/api/collections');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});
```

### Test Database

Tests use in-memory SQLite by default:

**phpunit.xml / pest.xml:**
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

---

## Configuration

### Core Configuration Files

#### config/yupoo.php
Yupoo import system configuration (see [Yupoo Import System](#yupoo-import-system))

#### config/filesystems.php
File storage configuration (S3, local) (see [File Storage & AWS S3](#file-storage--aws-s3))

#### config/sanctum.php
API authentication configuration

```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
    env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
))),
```

#### config/cors.php
Cross-Origin Resource Sharing configuration

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods' => ['*'],
'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],
'allowed_origins_patterns' => [],
'allowed_headers' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => true,
```

### Environment Variables

**Complete .env Reference:**
```env
# Application
APP_NAME=AKBag
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://akbag.elev8xr.com

# Database
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/database.sqlite

# Session & Cache
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# Storage
FILESYSTEM_DISK=s3

# AWS S3
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket
AWS_URL=https://your-bucket.s3.amazonaws.com

# Yupoo
YUPOO_BASE_URL=https://297228164.x.yupoo.com
YUPOO_MAX_ALBUMS=50
YUPOO_CONCURRENT_DOWNLOADS=5
YUPOO_BATCH_SIZE=8
YUPOO_REQUEST_DELAY=1

# Frontend CORS
FRONTEND_URL=https://akbags.elev8xr.com
```

---

## Security

### Authentication & Authorization

**API Authentication**: Laravel Sanctum (token-based)
**Admin Panel**: Session-based (Filament)

**Admin Middleware:**
```php
// Only users with is_admin = true can access
FilamentPanelProvider::middleware(['auth'])
```

### Password Security

**Hashing**: bcrypt (Laravel default)
**Minimum Length**: Not enforced (recommended: 8+ characters)

**Creating Secure Admin:**
```php
User::create([
    'email' => 'admin@example.com',
    'password' => bcrypt(Str::random(16)),
    'is_admin' => true,
]);
```

### CORS Configuration

**Allowed Origins:**
```php
'allowed_origins' => [
    'https://akbags.elev8xr.com',
    'http://localhost:3000', // Development only
],
```

### SQL Injection Prevention

Laravel's Eloquent ORM and Query Builder automatically escape values:

```php
// Safe - uses parameter binding
Album::where('collection_id', $id)->get();

// Safe - Eloquent mass assignment protection
Album::create($request->validated());
```

### XSS Prevention

**Blade Templates**: Automatic escaping
```php
{{ $user->name }} // Escaped
{!! $html !!}     // Raw (use carefully)
```

**API Responses**: JSON encoding (automatic escaping)

### File Upload Security

**Validation:**
```php
FileUpload::make('cover_image')
    ->image()                    // Only images
    ->maxSize(10240)             // Max 10MB
    ->acceptedFileTypes(['image/jpeg', 'image/png'])
```

**Storage:**
- Uploaded files stored on S3 (isolated from application)
- No direct file execution risk

### Environment Variables

**Never commit:**
- `.env` file
- AWS credentials
- Database passwords
- API keys

**Secure Storage:**
- Use `.env.example` as template
- Store secrets in environment variables
- Use secrets management service (AWS Secrets Manager, etc.)

### Rate Limiting

**API Rate Limiting:**
```php
// routes/api.php
Route::middleware(['throttle:api'])->group(function () {
    // API routes
});
```

**Default**: 60 requests per minute per IP

### HTTPS Enforcement

**Production Middleware:**
```php
// app/Http/Middleware/TrustProxies.php
protected $headers = Request::HEADER_X_FORWARDED_ALL;
```

**Nginx Configuration:**
```nginx
# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name akbag.elev8xr.com;
    return 301 https://$server_name$request_uri;
}
```

---

## Monitoring & Logging

### Logging Configuration

**Default Channel**: Stack
**Driver**: Single file

**config/logging.php:**
```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single'],
    ],
    'single' => [
        'driver' => 'single',
        'path' => storage_path('logs/laravel.log'),
        'level' => 'debug',
    ],
],
```

### Log Files

**Location**: `storage/logs/laravel.log`

**Log Levels:**
- `emergency`: System is unusable
- `alert`: Action must be taken immediately
- `critical`: Critical conditions
- `error`: Error conditions
- `warning`: Warning conditions
- `notice`: Normal but significant condition
- `info`: Informational messages
- `debug`: Debug-level messages

### Real-Time Monitoring

**Laravel Pail:**
```bash
php artisan pail
```

**Filter by Level:**
```bash
php artisan pail --filter=error
```

**Timeout:**
```bash
php artisan pail --timeout=0  # Never timeout
```

### Performance Monitoring

**Application Insights:**
```bash
php artisan about
```

Shows:
- Laravel version
- PHP version
- Environment
- Debug mode
- Cache driver
- Database connection
- Queue connection

### Yupoo Import Monitoring

**Progress Tracking:**
- Real-time console progress bars
- Performance metrics (images/second)
- Error summaries

**Debug Mode:**
```bash
php artisan yupoo:import --debug
```

Logs:
- Every HTTP request
- Parsing details
- Duplicate detection
- Database operations
- Error stack traces

### Error Tracking

**Production Recommendations:**
1. **Sentry**: Real-time error tracking
2. **Bugsnag**: Error monitoring
3. **Rollbar**: Full-stack error tracking

**Integration:**
```bash
composer require sentry/sentry-laravel
```

```env
SENTRY_LARAVEL_DSN=https://...
```

### Queue Monitoring

**Check Queue Status:**
```bash
php artisan queue:monitor
```

**Failed Jobs:**
```bash
# List failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry {id}

# Retry all failed jobs
php artisan queue:retry all
```

### Health Checks

**Database Connection:**
```bash
php artisan yupoo:check-db
```

**Storage Check:**
```bash
php artisan storage:link
```

**Optimize Status:**
```bash
php artisan optimize:status
```

---

## Appendix

### Project Structure

```
AKBag-Backend/
├── app/
│   ├── Console/
│   │   └── Commands/         # Artisan commands
│   │       ├── ImportYupooAlbums.php
│   │       ├── TestYupooConnection.php
│   │       └── ...
│   ├── Filament/
│   │   └── Resources/        # Admin panel resources
│   │       ├── AlbumResource.php
│   │       ├── CollectionResource.php
│   │       ├── ImageResource.php
│   │       └── FeaturedImageResource.php
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/          # API controllers
│   │           ├── AlbumController.php
│   │           ├── CollectionController.php
│   │           ├── ImageController.php
│   │           └── AuthController.php
│   ├── Models/               # Eloquent models
│   │   ├── Album.php
│   │   ├── Collection.php
│   │   ├── Image.php
│   │   ├── FeaturedImage.php
│   │   └── User.php
│   └── Services/             # Business logic
│       └── YupooService.php  # Core web scraping service
├── config/                   # Configuration files
│   ├── yupoo.php
│   ├── filesystems.php
│   ├── sanctum.php
│   └── ...
├── database/
│   ├── migrations/           # Database migrations
│   ├── seeders/              # Database seeders
│   └── factories/            # Model factories
├── routes/
│   ├── api.php               # API routes
│   ├── web.php               # Web routes
│   └── console.php           # Console routes
├── storage/
│   ├── app/                  # Application storage
│   └── logs/                 # Log files
├── tests/
│   ├── Feature/              # Feature tests
│   └── Unit/                 # Unit tests
├── .env.example              # Environment template
├── composer.json             # PHP dependencies
├── CLAUDE.md                 # Developer guide
└── TECHNICAL_DOCUMENTATION.md # This file
```

### Useful Resources

**Documentation:**
- [Laravel Documentation](https://laravel.com/docs)
- [Filament Documentation](https://filamentphp.com/docs)
- [Laravel Sanctum](https://laravel.com/docs/sanctum)
- [Pest PHP](https://pestphp.com)

**Repositories:**
- Backend: (current repository)
- Frontend: (separate repository)

**Third-Party Services:**
- AWS S3: [S3 Documentation](https://docs.aws.amazon.com/s3/)
- Contabo VPS: [Contabo](https://contabo.com)

### Common Issues & Solutions

#### Issue: Yupoo Import Fails
**Solution:**
1. Check network connectivity
2. Verify Yupoo URL is accessible
3. Enable debug mode: `--debug`
4. Check logs: `storage/logs/laravel.log`

#### Issue: Images Not Displaying
**Solution:**
1. Verify S3 credentials in `.env`
2. Check S3 bucket permissions (public read)
3. Verify `AWS_URL` is correct
4. Check CORS configuration on S3 bucket

#### Issue: Admin Panel 403 Error
**Solution:**
1. Verify user has `is_admin = true`
2. Clear cache: `php artisan cache:clear`
3. Check session driver: `SESSION_DRIVER=database`

#### Issue: API CORS Errors
**Solution:**
1. Add frontend URL to `SANCTUM_STATEFUL_DOMAINS`
2. Update `config/cors.php` allowed origins
3. Clear config: `php artisan config:clear`

#### Issue: Queue Jobs Not Processing
**Solution:**
1. Run queue worker: `php artisan queue:work`
2. Check failed jobs: `php artisan queue:failed`
3. Restart workers: `php artisan queue:restart`

### Contact & Support

**Project Maintainer**: (Add contact information)
**Repository Issues**: (Add GitHub issues URL)
**Documentation Updates**: Submit PR to main branch

---

**Last Updated**: January 2025
**Version**: 1.0.0
**Laravel Version**: 12.0
**PHP Version**: 8.2+
