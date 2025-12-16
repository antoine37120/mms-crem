# Nginx Streaming Configuration (Optional)

For production environments with high traffic, it is recommended to let Nginx serve the media files directly using `X-Accel-Redirect`.

## 1. Configure Laravel Filesystem

Ensure your `config/filesystems.php` has the `diffusion_medias` disk configured to a local path (e.g., `storage/app/diffusion_medias`).

## 2. Nginx Configuration

Add an `internal` location block to your Nginx site configuration. This block allows Nginx to serve files from the protected directory only when instructed by the backend (Laravel).

```nginx
server {
    # ... existing config ...

    # Internal location for diffusion media
    # The alias should point to the absolute path of your diffusion disk
    location /internal_media/ {
        internal;
        alias /var/www/your-project/storage/app/diffusion_medias/;
    }

    # ... existing php handling ...
}
```

## 3. Update MediaController

Modify `app/Http/Controllers/MediaController.php` to return the `X-Accel-Redirect` header instead of the file stream.

```php
// Example for segment serving
public function segment(string $code, string $segment)
{
    // ... verification logic ...

    // Construct the path relative to the Nginx alias
    // Assuming structure: items/CODE/diffusion/SEGMENT
    $relativePath = "/internal_media/items/{$code}/diffusion/{$segment}";

    return response()->make('', 200, [
        'X-Accel-Redirect' => $relativePath,
        'Content-Type' => 'video/MP2T',
    ]);
}
```

This setup ensures that authentication is still handled by Laravel, but the heavy lifting of file transfer is done by Nginx.
