# AKBag API Documentation

## Overview
AKBag is a Laravel-based API for managing collections, albums, and images. The API uses Laravel Sanctum for authentication and provides comprehensive CRUD operations with pagination support.

**Base URL:** `http://your-domain.com/api`
**Authentication:** Laravel Sanctum (Bearer Token)

## Table of Contents
- [Authentication](#authentication)
- [Collections](#collections)  
- [Albums](#albums)
- [Images](#images)
- [Pagination](#pagination)
- [Response Format](#response-format)
- [Error Handling](#error-handling)

---

## Authentication

### Register User
**POST** `/register`

Register a new user account.

**Request Body:**
```json
{
    "name": "John Doe",
    "email": "john@example.com", 
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Response (201):**
```json
{
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "email_verified_at": null,
        "is_admin": false,
        "created_at": "2025-01-20T10:00:00.000000Z",
        "updated_at": "2025-01-20T10:00:00.000000Z"
    },
    "token": "1|abc123def456..."
}
```

### Login User
**POST** `/login`

Authenticate user and receive access token.

**Request Body:**
```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

**Response (200):**
```json
{
    "user": {
        "id": 1,
        "name": "John Doe", 
        "email": "john@example.com",
        "email_verified_at": null,
        "is_admin": false,
        "created_at": "2025-01-20T10:00:00.000000Z",
        "updated_at": "2025-01-20T10:00:00.000000Z"
    },
    "token": "1|abc123def456..."
}
```

### Logout User
**POST** `/logout`
**Authentication:** Required

Logout user and revoke access token.

**Response (200):**
```json
{
    "message": "Logged out"
}
```

### Get Current User
**GET** `/user`
**Authentication:** Required

Get current authenticated user information.

**Response (200):**
```json
{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": null,
    "is_admin": false,
    "created_at": "2025-01-20T10:00:00.000000Z",
    "updated_at": "2025-01-20T10:00:00.000000Z"
}
```

---

## Collections

Collections are top-level containers that group related albums together.

### List Collections
**GET** `/collections`

Get paginated list of all collections.

**Query Parameters:**
- `per_page` (integer, optional): Items per page (1-100, default: 15)
- `page` (integer, optional): Page number (default: 1)
- `sort` (string, optional): Sort column (default: "id")
- `order` (string, optional): Sort order "asc" or "desc" (default: "desc")

**Example Request:**
```
GET /api/collections?per_page=10&page=1&sort=name&order=asc
```

**Response (200):**
```json
{
    "data": [
        {
            "id": 1,
            "name": "Summer Collection",
            "description": "Summer fashion items",
            "albums": []
        }
    ],
    "links": {
        "first": "http://example.com/api/collections?page=1",
        "last": "http://example.com/api/collections?page=3",
        "prev": null,
        "next": "http://example.com/api/collections?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 3,
        "per_page": 10,
        "to": 10,
        "total": 25
    }
}
```

### Get Collection
**GET** `/collections/{id}`

Get specific collection with its albums.

**Response (200):**
```json
{
    "id": 1,
    "name": "Summer Collection",
    "description": "Summer fashion items",
    "albums": [
        {
            "id": 1,
            "collection_id": 1,
            "title": "T-Shirts",
            "description": "Summer t-shirts",
            "cover_image": "http://example.com/storage/albums/cover.jpg"
        }
    ]
}
```

### Create Collection
**POST** `/collections`
**Authentication:** Required (Admin only)

Create a new collection.

**Request Body:**
```json
{
    "name": "Winter Collection",
    "description": "Winter fashion items"
}
```

**Response (201):**
```json
{
    "id": 2,
    "name": "Winter Collection", 
    "description": "Winter fashion items",
    "albums": []
}
```

### Update Collection
**PUT/PATCH** `/collections/{id}`
**Authentication:** Required (Admin only)

Update existing collection.

**Request Body:**
```json
{
    "name": "Updated Collection Name",
    "description": "Updated description"
}
```

**Response (200):**
```json
{
    "id": 1,
    "name": "Updated Collection Name",
    "description": "Updated description", 
    "albums": []
}
```

### Delete Collection
**DELETE** `/collections/{id}`
**Authentication:** Required (Admin only)

Delete collection and all associated albums/images.

**Response (204):** No content

### Get Collection Albums
**GET** `/collections/{id}/albums`

Get paginated albums within a specific collection.

**Query Parameters:** Same as pagination parameters

**Response (200):**
```json
{
    "data": [
        {
            "id": 1,
            "collection_id": 1,
            "title": "T-Shirts",
            "description": "Summer t-shirts",
            "cover_image": "http://example.com/storage/albums/cover.jpg"
        }
    ],
    "links": {...},
    "meta": {...}
}
```

### Get Specific Album in Collection
**GET** `/collections/{collectionId}/albums/{albumId}`

Get specific album within a collection, including its images.

**Response (200):**
```json
{
    "id": 1,
    "collection_id": 1,
    "title": "T-Shirts",
    "description": "Summer t-shirts", 
    "cover_image": "http://example.com/storage/albums/cover.jpg",
    "images": [
        {
            "id": 1,
            "album_id": 1,
            "title": "Red T-Shirt",
            "image_url": "http://example.com/storage/images/red-tshirt.jpg"
        }
    ]
}
```

---

## Albums

Albums contain related images within a collection.

### List Albums
**GET** `/albums`

Get paginated list of all albums.

**Query Parameters:** Same as pagination parameters

**Response (200):**
```json
{
    "data": [
        {
            "id": 1,
            "collection_id": 1,
            "title": "T-Shirts",
            "description": "Summer t-shirts",
            "cover_image": "http://example.com/storage/albums/cover.jpg",
            "images": [
                {
                    "id": 1,
                    "album_id": 1,
                    "title": "Red T-Shirt",
                    "image_url": "http://example.com/storage/images/red-tshirt.jpg"
                }
            ]
        }
    ],
    "links": {...},
    "meta": {...}
}
```

### Get Album
**GET** `/albums/{id}`

Get specific album with its images.

**Response (200):**
```json
{
    "id": 1,
    "collection_id": 1,
    "title": "T-Shirts",
    "description": "Summer t-shirts",
    "cover_image": "http://example.com/storage/albums/cover.jpg",
    "images": [
        {
            "id": 1,
            "album_id": 1,
            "title": "Red T-Shirt",
            "image_url": "http://example.com/storage/images/red-tshirt.jpg"
        }
    ]
}
```

### Create Album
**POST** `/albums`
**Authentication:** Required (Admin only)

Create a new album.

**Request Body (multipart/form-data):**
```
collection_id: 1
title: "New Album"
description: "Album description"
cover_image: [file upload, optional]
```

**Response (201):**
```json
{
    "id": 2,
    "collection_id": 1,
    "title": "New Album",
    "description": "Album description",
    "cover_image": "http://example.com/storage/albums/cover.jpg",
    "images": []
}
```

### Update Album
**PUT/PATCH** `/albums/{id}`
**Authentication:** Required (Admin only)

Update existing album.

**Request Body (multipart/form-data):**
```
collection_id: 1
title: "Updated Album"  
description: "Updated description"
cover_image: [file upload, optional]
```

**Response (200):**
```json
{
    "id": 1,
    "collection_id": 1,
    "title": "Updated Album",
    "description": "Updated description",
    "cover_image": "http://example.com/storage/albums/updated-cover.jpg",
    "images": []
}
```

### Delete Album
**DELETE** `/albums/{id}`
**Authentication:** Required (Admin only)

Delete album and all associated images.

**Response (204):** No content

### Get Album Images
**GET** `/albums/{albumId}/images`

Get paginated images within a specific album.

**Query Parameters:** Same as pagination parameters

**Response (200):**
```json
{
    "data": [
        {
            "id": 1,
            "album_id": 1,
            "title": "Red T-Shirt",
            "image_url": "http://example.com/storage/images/red-tshirt.jpg"
        }
    ],
    "links": {...},
    "meta": {...}
}
```

---

## Images

Individual images within albums.

### List Images
**GET** `/images`

Get paginated list of all images.

**Query Parameters:** Same as pagination parameters

**Response (200):**
```json
{
    "data": [
        {
            "id": 1,
            "album_id": 1,
            "title": "Red T-Shirt",
            "image_url": "http://example.com/storage/images/red-tshirt.jpg"
        }
    ],
    "links": {...},
    "meta": {...}
}
```

### Get Image
**GET** `/images/{id}`

Get specific image details.

**Response (200):**
```json
{
    "id": 1,
    "album_id": 1,
    "title": "Red T-Shirt",
    "image_url": "http://example.com/storage/images/red-tshirt.jpg"
}
```

### Create Image
**POST** `/images`
**Authentication:** Required (Admin only)

Upload a new image.

**Request Body (multipart/form-data):**
```
album_id: 1
title: "Blue T-Shirt"
description: "Blue summer t-shirt"
image_path: [file upload, required]
```

**Response (201):**
```json
{
    "id": 2,
    "album_id": 1,
    "title": "Blue T-Shirt",
    "image_url": "http://example.com/storage/images/blue-tshirt.jpg"
}
```

### Update Image
**PUT/PATCH** `/images/{id}`
**Authentication:** Required (Admin only)

Update existing image.

**Request Body (multipart/form-data):**
```
album_id: 1
title: "Updated Image Title"
description: "Updated description"
image_path: [file upload, optional]
```

**Response (200):**
```json
{
    "id": 1,
    "album_id": 1,
    "title": "Updated Image Title",
    "image_url": "http://example.com/storage/images/updated-image.jpg"
}
```

### Delete Image
**DELETE** `/images/{id}`
**Authentication:** Required (Admin only)

Delete image file and database record.

**Response (204):** No content

---

## Pagination

All list endpoints support pagination with the following query parameters:

### Parameters
- **per_page** (integer, optional): Number of items per page
  - Range: 1-100
  - Default: 15
- **page** (integer, optional): Page number to retrieve
  - Minimum: 1
  - Default: 1  
- **sort** (string, optional): Column to sort by
  - Default: "id"
  - Available columns vary by endpoint
- **order** (string, optional): Sort order
  - Values: "asc", "desc"
  - Default: "desc"

### Response Structure
Paginated responses include:
- **data**: Array of requested resources
- **links**: Navigation URLs (first, last, prev, next)
- **meta**: Pagination metadata

### Example
```
GET /api/collections?per_page=5&page=2&sort=name&order=asc
```

---

## Response Format

### Success Response
All successful responses follow this structure:

**Single Resource:**
```json
{
    "id": 1,
    "field1": "value1",
    "field2": "value2"
}
```

**Resource Collection (Paginated):**
```json
{
    "data": [...],
    "links": {
        "first": "url",
        "last": "url", 
        "prev": "url",
        "next": "url"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 10,
        "per_page": 15,
        "to": 15,
        "total": 150
    }
}
```

### HTTP Status Codes
- **200**: OK - Request successful
- **201**: Created - Resource created successfully  
- **204**: No Content - Delete successful
- **400**: Bad Request - Invalid request data
- **401**: Unauthorized - Authentication required
- **403**: Forbidden - Insufficient permissions
- **404**: Not Found - Resource not found
- **422**: Unprocessable Entity - Validation errors
- **500**: Internal Server Error - Server error

---

## Error Handling

### Validation Errors (422)
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": [
            "The email field is required."
        ],
        "password": [
            "The password field is required."
        ]
    }
}
```

### Authentication Errors (401)
```json
{
    "message": "Unauthenticated."
}
```

### Authorization Errors (403)
```json
{
    "message": "Forbidden"
}
```

### Not Found Errors (404)
```json
{
    "message": "Not Found"
}
```

### Login Errors (422)
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": [
            "The provided credentials are incorrect."
        ]
    }
}
```

---

## Authentication Headers

For protected endpoints, include the Bearer token in request headers:

```
Authorization: Bearer 1|abc123def456...
Accept: application/json
Content-Type: application/json
```

For file uploads, use:
```
Authorization: Bearer 1|abc123def456...
Accept: application/json  
Content-Type: multipart/form-data
```

---

## Notes

1. **Admin Permissions**: Create, update, and delete operations require admin privileges (`is_admin: true`)
2. **File Uploads**: Images are stored in `storage/app/public/` and served via Laravel's storage system
3. **Cascade Deletes**: Deleting a collection will delete all associated albums and images
4. **Image URLs**: All image URLs are absolute URLs including the domain
5. **Rate Limiting**: Consider implementing rate limiting for production use
6. **CORS**: Ensure CORS is configured for frontend domain access

---

*Last updated: 2025-01-20*
