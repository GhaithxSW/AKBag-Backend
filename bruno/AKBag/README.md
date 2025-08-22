# AKBag API Bruno Collection

This Bruno collection contains all the API endpoints for the AKBag Backend application.

## Setup

1. **Select Environment**: Choose between `Local` or `Production` environment in Bruno
   - **Local**: `http://localhost:8000` (for local development)
   - **Production**: `https://akbag.elev8xr.com` (production server)

2. **Authentication**: 
   - First, use the `Register` or `Login` request in the `Auth` folder
   - The token will be automatically saved to the environment variable `{{token}}`
   - This token is used for authenticated requests

## Collection Structure

### Auth
- **Register**: Create a new user account
- **Login**: Authenticate and get access token
- **Logout**: Invalidate current token
- **Get User**: Get current authenticated user info

### Collections
- **Get All Collections**: List all collections
- **Get Collection**: Get single collection with nested albums
- **Create Collection**: Add new collection (requires auth)
- **Update Collection**: Update existing collection (requires auth)
- **Delete Collection**: Delete collection (requires auth)
- **Get Collection Albums**: List albums in a collection
- **Get Album in Collection**: Get specific album within collection

### Albums
- **Get All Albums**: List all albums
- **Get Album**: Get single album
- **Create Album**: Add new album (requires auth)
- **Update Album**: Update existing album (requires auth)
- **Delete Album**: Delete album (requires auth)
- **Get Album Images**: List images in an album

### Images
- **Get All Images**: List all images
- **Get Image**: Get single image
- **Create Image**: Add new image (requires auth)
- **Update Image**: Update existing image (requires auth)
- **Delete Image**: Delete image (requires auth)

## Usage Flow

1. **Authentication**: Start with `Auth > Login` to get your token
2. **Browse Data**: Use GET requests to explore collections, albums, and images
3. **Modify Data**: Use POST/PUT/DELETE requests (requires authentication)

## Variables

The collection uses these variables:
- `{{baseUrl}}`: API base URL (set by environment)
- `{{token}}`: Authentication token (set automatically by login)
- `{{collectionId}}`: Collection ID for parameterized requests
- `{{albumId}}`: Album ID for parameterized requests
- `{{imageId}}`: Image ID for parameterized requests

## Notes

- Public endpoints (GET requests) don't require authentication
- Create, update, and delete operations require valid authentication token
- Image URLs are constructed as: `{{baseUrl}}/storage/{image_path}`
- All responses are in JSON format