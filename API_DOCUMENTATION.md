# AKBag Backend API Documentation

## Overview
AKBag Backend is a Laravel-based RESTful API designed to manage and serve a product catalog system. It provides endpoints to handle collections, albums, and images in a hierarchical structure.

## Base URL
```
https://akbag.elev8xr.com/api
```

## Authentication
- Uses Laravel Sanctum for API authentication
- Protected routes require a valid authentication token
- Token can be obtained through the `/login` endpoint

## Data Models

### Collection
Represents a group of albums (e.g., "Classic Collection")

**Fields:**
- `id` (integer)
- `name` (string)
- `description` (text, nullable)
- `slug` (string)
- `created_at` (timestamp)
- `updated_at` (timestamp)

### Album
Represents a group of related images (e.g., "Executive Series")

**Fields:**
- `id` (integer)
- `collection_id` (integer, foreign key)
- `title` (string)
- `description` (text, nullable)
- `cover_image` (string, nullable)
- `slug` (string)
- `created_at` (timestamp)
- `updated_at` (timestamp)

### Image
Represents individual product images

**Fields:**
- `id` (integer)
- `album_id` (integer, foreign key)
- `title` (string)
- `category` (string, nullable)
- `image_path` (string)
- `created_at` (timestamp)
- `updated_at` (timestamp)

## API Endpoints

### Collections

#### Get All Collections
```
GET /collections
```
**Response:**
```json
[
  {
    "id": 1,
    "name": "Classic Collection",
    "description": "Timeless designs for the modern professional",
    "slug": "classic-collection"
  }
]
```

#### Get Single Collection
```
GET /collections/{id}
```
**Response:**
```json
{
  "id": 1,
  "name": "Classic Collection",
  "description": "Timeless designs for the modern professional",
  "slug": "classic-collection",
  "albums": [
    {
      "id": 1,
      "title": "Executive Series",
      "description": "...",
      "slug": "executive-series",
      "collection_id": 1
    }
  ]
}
```

#### Get Albums in Collection
```
GET /collections/{id}/albums
```
**Response:**
```json
[
  {
    "id": 1,
    "title": "Executive Series",
    "description": "...",
    "slug": "executive-series",
    "collection_id": 1
  }
]
```

### Albums

#### Get All Albums
```
GET /albums
```
**Response:**
```json
[
  {
    "id": 1,
    "title": "Executive Series",
    "description": "...",
    "slug": "executive-series",
    "collection_id": 1,
    "images": [
      {
        "id": 1,
        "title": "Executive Briefcase - Black",
        "image_path": "images/filename.jpg"
      }
    ]
  }
]
```

#### Get Album Details
```
GET /collections/{collectionId}/albums/{albumId}
```
**Response:**
```json
{
  "id": 1,
  "title": "Executive Series",
  "description": "...",
  "slug": "executive-series",
  "collection_id": 1,
  "images": [
    {
      "id": 1,
      "title": "Executive Briefcase - Black",
      "image_path": "images/filename.jpg"
    }
  ]
}
```

### Images

#### Get Album Images
```
GET /albums/{albumId}/images
```
**Response:**
```json
[
  {
    "id": 1,
    "title": "Executive Briefcase - Black",
    "category": "briefcase",
    "image_path": "images/filename.jpg",
    "album_id": 1
  }
]
```

## Error Responses

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 404 Not Found
```json
{
  "message": "No query results for model [App\\Models\\Collection] {id}"
}
```

## Technical Stack
- **Backend**: PHP 8.x, Laravel
- **Authentication**: Laravel Sanctum
- **Database**: MySQL/PostgreSQL
- **API Type**: RESTful JSON API

## Development

### Requirements
- PHP 8.0 or higher
- Composer
- MySQL/PostgreSQL
- Node.js & NPM (for frontend assets)

### Installation
1. Clone the repository
2. Run `composer install`
3. Copy `.env.example` to `.env` and configure your environment
4. Generate application key: `php artisan key:generate`
5. Run migrations: `php artisan migrate`
6. Start the development server: `php artisan serve`

## License
This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
