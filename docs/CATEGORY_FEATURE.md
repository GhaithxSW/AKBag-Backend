# Category Feature Documentation

## Overview
The category system allows you to organize images into logical groups, making it easier to manage and filter your content. This documentation covers the database schema, API endpoints, and how to manage categories through the admin panel.

## Database Schema

### Categories Table
```php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->text('description')->nullable();
    $table->timestamps();
});
```

### Images Table Update
```php
Schema::table('images', function (Blueprint $table) {
    $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
});
```

## Models

### Category Model
```php
class Category extends Model
{
    protected $fillable = ['name', 'description'];

    public function images()
    {
        return $this->hasMany(Image::class);
    }
}
```

### Image Model Update
```php
class Image extends Model
{
    // ... existing code ...
    
    protected $fillable = [
        // ... existing fields ...
        'category_id',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
```

## API Endpoints

### List Categories
```
GET /api/categories
```

### Get Category
```
GET /api/categories/{id}
```

### Create Category
```
POST /api/categories
{
    "name": "Category Name",
    "description": "Category Description"
}
```

### Update Category
```
PUT /api/categories/{id}
{
    "name": "Updated Name",
    "description": "Updated Description"
}
```

### Delete Category
```
DELETE /api/categories/{id}
```

### Update Image Category
```
PATCH /api/images/{id}/category
{
    "category_id": 1
}
```

## Admin Panel

### Managing Categories
1. Navigate to `/admin/categories`
2. Use the "New Category" button to create a new category
3. Click on a category to edit its details
4. Use the delete action to remove a category

### Assigning Categories to Images
1. Navigate to `/admin/images`
2. Click on an image to edit it
3. Select a category from the dropdown
4. Click "Save" to update

## Integration with Yupoo Importer

The Yupoo importer automatically creates categories based on album names. For example:
- Album: "Summer Collection 2023" → Category: "Summer Collection 2023"

You can customize this behavior by modifying the `processAlbum` method in the `YupooService` class.

## Best Practices

1. **Consistent Naming**: Use clear, consistent names for categories
2. **Hierarchy**: Consider using subcategories for better organization
3. **Cleanup**: Regularly review and merge duplicate categories
4. **Default Category**: Always have a default category for uncategorized images

## Troubleshooting

### Common Issues

1. **Missing Category in Dropdown**:
   - Ensure the category exists in the database
   - Check for any visibility conditions in your policies

2. **Category Not Saving**:
   - Verify the category_id is included in the fillable array
   - Check for validation errors in the request

3. **Performance Issues**:
   - Consider adding indexes for frequently queried category fields
   - Use eager loading when fetching images with categories

## API Response Examples

### Successful Response (200 OK)
```json
{
    "id": 1,
    "name": "Summer Collection",
    "description": "Summer 2023 collection",
    "created_at": "2023-07-29T20:00:00.000000Z",
    "updated_at": "2023-07-29T20:00:00.000000Z"
}
```

### Error Response (422 Unprocessable Entity)
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "name": ["The name field is required."]
    }
}
```

## Testing

Run the test suite to verify category functionality:

```bash
php artisan test --filter=CategoryTest
```

## Security Considerations

- Category management is restricted to admin users
- All category operations are logged
- Input is validated and sanitized
- CSRF protection is enabled for web routes
- API endpoints require authentication
