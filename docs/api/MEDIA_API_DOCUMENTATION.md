# Media Management API Documentation

## Overview

The Media Management API provides a complete solution for handling image uploads, thumbnail generation, and media management in a multi-tenant e-commerce platform. It follows DDD principles and integrates seamlessly with API Platform.

## Features

- **Image Upload**: Multipart/form-data support for image uploads
- **Thumbnail Generation**: Automatic or async thumbnail generation in multiple sizes
- **Crop Support**: React-image-crop compatible crop area support
- **Multi-tenancy**: Full tenant isolation with security checks
- **URL Resolution**: Automatic content URL generation for images and thumbnails
- **Async Processing**: Optional async thumbnail generation via Symfony Messenger

## Configuration

### Environment Variables

```env
# Enable/disable async thumbnail generation
ASYNC_THUMBNAILS=true

# Messenger transport for async processing
MESSENGER_TRANSPORT_DSN="doctrine://default"
```

### Media Paths Configuration

File: `config/packages/media.yaml`

```yaml
parameters:
    media.storage.local.base_path: '%kernel.project_dir%/public/media/originals'
    media.storage.local.public_prefix: '/media/originals'
    media.thumbnail.local.base_path: '%kernel.project_dir%/public/media/thumbnails'
    media.thumbnail.local.public_prefix: '/media/thumbnails'
```

## API Endpoints

### 1. Upload Image

**Endpoint**: `POST /api/media_images`

**Content-Type**: `multipart/form-data`

**Request Parameters**:
- `file` (required): The image file to upload
- `tenantId` (required): UUID of the tenant
- `ownerType` (required): Type of owner - `product`, `category`, or `user`
- `ownerId` (required): UUID of the owner entity
- `title` (optional): Image title
- `altText` (optional): Alt text for accessibility

**Headers**:
- `X-Tenant-ID`: Tenant UUID for security validation

**Example Request**:
```bash
curl -X POST http://localhost:8000/api/media_images \
  -H "Content-Type: multipart/form-data" \
  -H "X-Tenant-ID: 550e8400-e29b-41d4-a716-446655440000" \
  -F "file=@/path/to/image.jpg" \
  -F "tenantId=550e8400-e29b-41d4-a716-446655440000" \
  -F "ownerType=product" \
  -F "ownerId=660e8400-e29b-41d4-a716-446655440000" \
  -F "title=Product Main Image" \
  -F "altText=High-quality product photo"
```

**Response** (201 Created):
```json
{
  "@context": "/api/contexts/MediaImage",
  "@id": "/api/media_images/123e4567-e89b-12d3-a456-426614174000",
  "@type": "MediaImage",
  "id": "123e4567-e89b-12d3-a456-426614174000",
  "tenantId": "550e8400-e29b-41d4-a716-446655440000",
  "ownerType": "product",
  "ownerId": "660e8400-e29b-41d4-a716-446655440000",
  "title": "Product Main Image",
  "altText": "High-quality product photo",
  "contentUrl": "/media/originals/550e8400.../123e4567.../original.jpg",
  "thumbnails": [
    {
      "sizeLabel": "sm",
      "url": "/media/thumbnails/550e8400.../123e4567.../thumb_sm.jpg",
      "width": 200,
      "height": 200
    },
    {
      "sizeLabel": "md",
      "url": "/media/thumbnails/550e8400.../123e4567.../thumb_md.jpg",
      "width": 600,
      "height": 400
    }
  ]
}
```

### 2. Get Image

**Endpoint**: `GET /api/media_images/{id}`

**Headers**:
- `X-Tenant-ID`: Tenant UUID

**Example Request**:
```bash
curl -X GET http://localhost:8000/api/media_images/123e4567-e89b-12d3-a456-426614174000 \
  -H "X-Tenant-ID: 550e8400-e29b-41d4-a716-446655440000"
```

### 3. List Images

**Endpoint**: `GET /api/media_images`

**Query Parameters**:
- `page` (optional): Page number (default: 1)
- `itemsPerPage` (optional): Items per page (default: 30)

**Headers**:
- `X-Tenant-ID`: Tenant UUID

### 4. Regenerate Thumbnails

**Endpoint**: `PATCH /api/media_images/{id}/regenerate-thumbnails`

**Content-Type**: `application/json`

**Request Body**:
```json
{
  "cropJson": {
    "x": 10,
    "y": 20,
    "width": 200,
    "height": 300
  },
  "sizes": ["md", "lg"]
}
```

**Parameters**:
- `cropJson` (optional): Crop area compatible with react-image-crop
  - `x`: X coordinate of crop start
  - `y`: Y coordinate of crop start
  - `width`: Crop width
  - `height`: Crop height
- `sizes` (optional): Array of size labels to regenerate (`sm`, `md`, `lg`, `xl`)
  - If not provided, all sizes will be regenerated

**Example Request**:
```bash
curl -X PATCH http://localhost:8000/api/media_images/123e4567.../regenerate-thumbnails \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: 550e8400-e29b-41d4-a716-446655440000" \
  -d '{
    "cropJson": {"x": 10, "y": 20, "width": 200, "height": 300},
    "sizes": ["md", "lg"]
  }'
```

### 5. Delete Image

**Endpoint**: `DELETE /api/media_images/{id}`

**Headers**:
- `X-Tenant-ID`: Tenant UUID

**Response**: 204 No Content

**Note**: Physical files are cleaned up asynchronously via message queue

## Thumbnail Sizes

Default thumbnail dimensions:

| Size Label | Dimensions | Use Case |
|------------|------------|----------|
| `sm` | 200x200 | Thumbnails, lists |
| `md` | 600x400 | Product cards |
| `lg` | 1200x800 | Product detail page |
| `xl` | 1600x900 | Hero images, zoom |

## Validation Rules

### File Validation
- **Max Size**: 10MB
- **Allowed MIME Types**:
  - `image/jpeg`
  - `image/png`
  - `image/webp`
  - `image/avif`
  - `image/gif`

### Crop Area Validation
- Coordinates must be non-negative
- Width and height must be positive
- Crop area must not exceed image bounds
- Validates against original image dimensions

### Owner Type Validation
- Must be one of: `product`, `category`, `user`
- Category and User entities enforce single image constraint
- Product entities support multiple images

## Error Responses

### 400 Bad Request
```json
{
  "@context": "/api/contexts/Error",
  "@type": "hydra:Error",
  "hydra:title": "An error occurred",
  "hydra:description": "Uploaded file is required."
}
```

### 403 Forbidden
```json
{
  "@context": "/api/contexts/Error",
  "@type": "hydra:Error",
  "hydra:title": "An error occurred",
  "hydra:description": "You do not have permission to manage media for this tenant."
}
```

### 404 Not Found
```json
{
  "@context": "/api/contexts/Error",
  "@type": "hydra:Error",
  "hydra:title": "An error occurred",
  "hydra:description": "Image not found."
}
```

### 422 Unprocessable Entity (Validation Error)
```json
{
  "@context": "/api/contexts/ConstraintViolationList",
  "@type": "ConstraintViolationList",
  "hydra:title": "An error occurred",
  "violations": [
    {
      "propertyPath": "file",
      "message": "The file is too large (15.2 MB). Allowed maximum size is 10 MB."
    }
  ]
}
```

## Async Processing

When `ASYNC_THUMBNAILS=true`, thumbnail generation happens in the background:

1. Image is saved immediately with original file
2. Response returns without thumbnails
3. Thumbnails are generated via Messenger queue
4. Subsequent GET requests will include generated thumbnails

### Running the Message Consumer

```bash
# Process media queue
php bin/console messenger:consume media_async -vv

# Process all queues
php bin/console messenger:consume async media_async -vv
```

## Integration with Frontend

### React Image Upload Component

```jsx
const uploadImage = async (file, ownerId, ownerType) => {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('tenantId', tenantId);
  formData.append('ownerType', ownerType);
  formData.append('ownerId', ownerId);
  formData.append('title', file.name);

  const response = await fetch('/api/media_images', {
    method: 'POST',
    headers: {
      'X-Tenant-ID': tenantId
    },
    body: formData
  });

  return response.json();
};
```

### React Image Crop Integration

```jsx
import ReactCrop from 'react-image-crop';

const handleCropComplete = async (crop, imageId) => {
  const cropJson = {
    x: crop.x,
    y: crop.y,
    width: crop.width,
    height: crop.height
  };

  const response = await fetch(`/api/media_images/${imageId}/regenerate-thumbnails`, {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/json',
      'X-Tenant-ID': tenantId
    },
    body: JSON.stringify({ cropJson, sizes: ['md', 'lg'] })
  });

  return response.json();
};
```

## Security Considerations

1. **Tenant Isolation**: All operations are scoped to the tenant specified in `X-Tenant-ID` header
2. **Permission Checks**: `ImageSecurityPolicy` validates user permissions for each operation
3. **File Type Validation**: Only allowed image MIME types are accepted
4. **Size Limits**: Files over 10MB are rejected
5. **Path Traversal Protection**: All file paths are sanitized and validated

## Performance Optimization

1. **Async Processing**: Enable `ASYNC_THUMBNAILS=true` for non-blocking uploads
2. **CDN Integration**: Serve media files through CDN with long cache headers
3. **Image Optimization**: Consider adding WebP support for modern browsers
4. **Lazy Loading**: Load thumbnails on demand using appropriate size

## Nginx Configuration

Add to your Nginx configuration to serve static media files:

```nginx
location /media {
    alias /var/www/new_ecom/backend/public/media;
    expires 30d;
    add_header Cache-Control "public, immutable";
    add_header X-Content-Type-Options "nosniff";

    # Security headers
    location ~* \.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$ {
        return 403;
    }
}
```

## Troubleshooting

### Common Issues

1. **Upload fails with 413 Request Entity Too Large**
   - Increase `client_max_body_size` in Nginx
   - Increase `upload_max_filesize` and `post_max_size` in PHP

2. **Thumbnails not generating**
   - Check if GD or ImageMagick is installed
   - Verify message consumer is running for async mode
   - Check logs for generation errors

3. **Permission denied errors**
   - Ensure web server user has write permissions to media directories
   - Check directory ownership and permissions

### Debug Commands

```bash
# Check message queue status
php bin/console messenger:failed:show

# Retry failed messages
php bin/console messenger:failed:retry

# Clear failed messages
php bin/console messenger:failed:remove

# Test image upload manually
php bin/console app:test:image-upload --tenant=550e8400... --product=660e8400...
```

## Migration from Existing System

If migrating from an existing media system:

1. Create migration script to import existing images
2. Generate thumbnails in batch using command:
   ```bash
   php bin/console app:media:regenerate-thumbnails --tenant=all
   ```
3. Update entity references to use new image IDs
4. Set up redirects from old URLs to new structure

## Future Enhancements

Planned improvements for future releases:

- [ ] WebP and AVIF format support
- [ ] Smart cropping with face detection
- [ ] Bulk upload support
- [ ] Image optimization pipeline
- [ ] S3/CloudFront integration
- [ ] Video file support
- [ ] PDF thumbnail generation
- [ ] Watermark support
- [ ] Image transformation API (resize, rotate, filters)