# Railway Deployment Guide - AKBag Backend with AWS S3

This project has been configured to use AWS S3 for image storage instead of local server storage, making it ready for Railway deployment.

## Environment Variables Required

Add these environment variables to your Railway project:

### Core Application Settings
```
APP_NAME="AKBag Backend"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-railway-domain.railway.app
APP_KEY=base64:your-generated-app-key
```

### Database (Railway will provide these)
```
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=your-db-name
DB_USERNAME=your-db-user
DB_PASSWORD=your-db-password
```

### File Storage (AWS S3)
```
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-aws-access-key-id
AWS_SECRET_ACCESS_KEY=your-aws-secret-access-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-s3-bucket-name
AWS_USE_PATH_STYLE_ENDPOINT=false
AWS_URL=https://your-bucket.s3.us-east-1.amazonaws.com
```

### Session & Cache
```
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

## S3 Bucket Configuration

Ensure your S3 bucket has the following settings:

1. **ACLs Disabled**: Modern S3 buckets have ACLs disabled by default (recommended)
2. **Public Read Access**: Images need to be publicly accessible
3. **CORS Policy** (if serving to web frontend):
```json
[
    {
        "AllowedHeaders": ["*"],
        "AllowedMethods": ["GET", "PUT", "POST", "DELETE"],
        "AllowedOrigins": ["*"],
        "ExposeHeaders": []
    }
]
```

4. **Bucket Policy** for public read:
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "PublicRead",
            "Effect": "Allow",
            "Principal": "*",
            "Action": "s3:GetObject",
            "Resource": "arn:aws:s3:::your-bucket-name/*"
        }
    ]
}
```

**Important**: The application is configured to work with ACL-disabled buckets (AWS default since April 2023).

## Deployment Steps

1. **Connect Repository**: Link your GitHub repository to Railway

2. **Set Environment Variables**: Add all variables listed above in Railway dashboard

3. **Deploy**: Railway will automatically build and deploy

4. **Run Migrations**: After first deployment, run:
   ```bash
   php artisan migrate --force
   ```

5. **Verify Deployment**: Test these endpoints:
   - `GET /api/collections` - Should return collections
   - `GET /admin` - Should show Filament admin panel
   - Upload test image via admin to verify S3 integration

## Post-Deployment Verification

✅ **Image Uploads**: Test uploading images through Filament admin
✅ **Yupoo Import**: Test `php artisan yupoo:import --limit=1`
✅ **API Access**: Verify all API endpoints respond correctly
✅ **S3 Integration**: Check that images are stored in S3 bucket
✅ **Admin Panel**: Ensure Filament admin works correctly

## Image Storage Structure in S3

```
your-bucket/
├── images/           # Regular product images
├── albums/           # Album cover images  
└── featured/         # Featured homepage images
```

## Troubleshooting

### S3 Access Issues
- Verify AWS credentials are correct
- Check bucket exists and is in correct region
- Ensure bucket has public read permissions

### Image Upload Failures
- Check Railway logs for AWS SDK errors
- Verify S3 bucket permissions
- Ensure `league/flysystem-aws-s3-v3` package is installed

### Database Connection
- Railway provides database credentials automatically
- Check Railway dashboard for connection details

## Key Features Ready for Production

✅ **Optimized Yupoo Import System**: Multi-page scraping with S3 storage
✅ **Filament Admin Panel**: Full CRUD for collections, albums, images
✅ **RESTful API**: Complete API with Sanctum authentication
✅ **AWS S3 Integration**: All image storage handled by S3
✅ **Performance Optimized**: Database indexes and bulk operations