# Storage & Filesystem

Luminor provides a powerful filesystem abstraction layer for working with local and cloud storage. The storage system offers a unified API for file operations across different storage backends.

## Table of Contents

- [Introduction](#introduction)
- [Configuration](#configuration)
- [Basic Usage](#basic-usage)
- [File Operations](#file-operations)
- [Directory Operations](#directory-operations)
- [File Metadata](#file-metadata)
- [File Streaming](#file-streaming)
- [Visibility](#visibility)
- [Best Practices](#best-practices)

## Introduction

The storage system provides:

- **Unified API** - Same interface for local and cloud storage
- **Multiple Drivers** - Local filesystem (S3, FTP coming soon)
- **File Streaming** - Efficient handling of large files
- **Visibility Control** - Public and private file access
- **URL Generation** - Generate URLs for stored files

## Configuration

Register the storage service provider:

```php
use Luminor\Storage\StorageServiceProvider;

$kernel->registerServiceProvider(new StorageServiceProvider());
```

Configure storage in `config/storage.php`:

```php
return [
    'default' => env('STORAGE_DRIVER', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => __DIR__ . '/../storage/app',
            'url' => env('APP_URL', 'http://localhost') . '/storage',
            'visibility' => 'private',
        ],

        'public' => [
            'driver' => 'local',
            'root' => __DIR__ . '/../storage/app/public',
            'url' => env('APP_URL', 'http://localhost') . '/storage',
            'visibility' => 'public',
        ],

        'uploads' => [
            'driver' => 'local',
            'root' => __DIR__ . '/../storage/app/uploads',
            'url' => env('APP_URL', 'http://localhost') . '/uploads',
            'visibility' => 'private',
        ],
    ],
];
```

Directory structure:

```
storage/
├── app/
│   ├── public/         # Publicly accessible files
│   │   ├── avatars/
│   │   └── images/
│   └── private/        # Private files
│       ├── documents/
│       └── reports/
├── cache/
├── logs/
└── sessions/
```

## Basic Usage

### Getting Storage Instance

```php
use Luminor\Storage\StorageManager;

$storage = $container->get(StorageManager::class);

// Use default disk
$storage->put('file.txt', 'contents');

// Use specific disk
$storage->disk('public')->put('image.jpg', $imageData);
$storage->disk('uploads')->put('document.pdf', $pdfData);
```

### Storing Files

```php
// Store string content
$storage->put('file.txt', 'Hello World');

// Store file from path
$storage->putFile('photos', '/path/to/photo.jpg');

// Store with custom name
$storage->putFileAs('photos', '/path/to/photo.jpg', 'avatar.jpg');

// Store stream
$stream = fopen('/path/to/large-file.zip', 'r');
$storage->putStream('files/archive.zip', $stream);
fclose($stream);
```

### Reading Files

```php
// Get file contents
$contents = $storage->get('file.txt');

// Check if file exists
if ($storage->exists('file.txt')) {
    $contents = $storage->get('file.txt');
}

// Get file stream (for large files)
$stream = $storage->readStream('large-file.zip');
while (!feof($stream)) {
    echo fread($stream, 8192);
}
fclose($stream);
```

### Deleting Files

```php
// Delete a single file
$storage->delete('file.txt');

// Delete multiple files
$storage->delete(['file1.txt', 'file2.txt', 'file3.txt']);

// Delete directory and contents
$storage->deleteDirectory('photos');
```

## File Operations

### put()

Store file contents:

```php
$storage->put('path/to/file.txt', 'contents');

// With visibility
$storage->put('public/avatar.jpg', $imageData, 'public');
```

### get()

Retrieve file contents:

```php
$contents = $storage->get('path/to/file.txt');

// Returns null if file doesn't exist
if ($contents === null) {
    // File not found
}
```

### append()

Append to file:

```php
$storage->append('logs/app.log', "New log entry\n");
```

### prepend()

Prepend to file:

```php
$storage->prepend('file.txt', "First line\n");
```

### copy()

Copy a file:

```php
$storage->copy('old/path.txt', 'new/path.txt');
```

### move()

Move a file:

```php
$storage->move('old/path.txt', 'new/path.txt');
```

### rename()

Rename a file (alias for move):

```php
$storage->rename('old-name.txt', 'new-name.txt');
```

## Directory Operations

### makeDirectory()

Create a directory:

```php
$storage->makeDirectory('photos/2025');
```

### deleteDirectory()

Delete a directory and its contents:

```php
$storage->deleteDirectory('temp');
```

### directories()

List directories:

```php
$directories = $storage->directories('photos');
// Returns: ['photos/2024', 'photos/2025']

// Recursive listing
$allDirectories = $storage->allDirectories('photos');
```

### files()

List files in a directory:

```php
$files = $storage->files('photos');
// Returns: ['photos/image1.jpg', 'photos/image2.jpg']

// Recursive listing
$allFiles = $storage->allFiles('photos');
```

### Example: Organize Uploads

```php
class FileOrganizer
{
    public function __construct(
        private StorageManager $storage
    ) {}

    public function organizeByDate(string $directory): void
    {
        $files = $this->storage->disk('uploads')->files($directory);

        foreach ($files as $file) {
            $timestamp = $this->storage->lastModified($file);
            $date = date('Y/m/d', $timestamp);
            $newPath = "{$directory}/{$date}/" . basename($file);

            $this->storage->move($file, $newPath);
        }
    }

    public function cleanupOldFiles(string $directory, int $days = 30): void
    {
        $files = $this->storage->allFiles($directory);
        $cutoff = time() - ($days * 86400);

        foreach ($files as $file) {
            if ($this->storage->lastModified($file) < $cutoff) {
                $this->storage->delete($file);
            }
        }
    }
}
```

## File Metadata

### exists()

Check if file exists:

```php
if ($storage->exists('file.txt')) {
    // File exists
}

// Check if file is missing
if ($storage->missing('file.txt')) {
    // File doesn't exist
}
```

### size()

Get file size in bytes:

```php
$bytes = $storage->size('file.txt');
$kilobytes = $bytes / 1024;
$megabytes = $kilobytes / 1024;
```

### lastModified()

Get last modified timestamp:

```php
$timestamp = $storage->lastModified('file.txt');
$date = date('Y-m-d H:i:s', $timestamp);
```

### mimeType()

Get MIME type:

```php
$mimeType = $storage->mimeType('image.jpg');
// Returns: 'image/jpeg'
```

### Example: File Information

```php
class FileInfo
{
    public function getInfo(string $path): array
    {
        if (!$this->storage->exists($path)) {
            return null;
        }

        return [
            'path' => $path,
            'size' => $this->storage->size($path),
            'size_human' => $this->formatBytes($this->storage->size($path)),
            'mime_type' => $this->storage->mimeType($path),
            'last_modified' => date('Y-m-d H:i:s', $this->storage->lastModified($path)),
            'url' => $this->storage->url($path),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . ' ' . $units[$index];
    }
}
```

## File Streaming

For large files, use streaming to avoid memory issues:

### readStream()

Read file as stream:

```php
$stream = $storage->readStream('large-file.zip');

header('Content-Type: application/zip');
header('Content-Length: ' . $storage->size('large-file.zip'));

while (!feof($stream)) {
    echo fread($stream, 8192);
}

fclose($stream);
```

### putStream()

Write stream to file:

```php
$source = fopen('/path/to/large-file.zip', 'r');
$storage->putStream('backups/archive.zip', $source);
fclose($source);
```

### Example: Download Large File

```php
class FileDownloader
{
    public function download(string $path): Response
    {
        if (!$this->storage->exists($path)) {
            return new Response('File not found', 404);
        }

        $stream = $this->storage->readStream($path);
        $size = $this->storage->size($path);
        $mimeType = $this->storage->mimeType($path);
        $filename = basename($path);

        return new StreamedResponse(
            function() use ($stream) {
                while (!feof($stream)) {
                    echo fread($stream, 8192);
                    flush();
                }
                fclose($stream);
            },
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Length' => $size,
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]
        );
    }
}
```

## Visibility

Control file visibility (public/private):

### setVisibility()

```php
// Make file public
$storage->setVisibility('avatar.jpg', 'public');

// Make file private
$storage->setVisibility('document.pdf', 'private');
```

### getVisibility()

```php
$visibility = $storage->getVisibility('file.txt');
// Returns: 'public' or 'private'
```

### Public vs Private

**Public files:**
- Accessible via URL
- Can be served directly by web server
- Example: avatars, product images

**Private files:**
- Not directly accessible
- Must be served through application
- Example: user documents, invoices

### Example: Public/Private Files

```php
class AvatarService
{
    public function upload(UploadedFile $file, User $user): string
    {
        $filename = $user->getId() . '.' . $file->getExtension();

        // Store as public file
        $this->storage->disk('public')->put(
            "avatars/{$filename}",
            file_get_contents($file->getPath()),
            'public'
        );

        // Generate public URL
        return $this->storage->disk('public')->url("avatars/{$filename}");
    }
}

class InvoiceService
{
    public function generate(Order $order): string
    {
        $pdf = $this->generatePdf($order);
        $filename = "invoice-{$order->getId()}.pdf";

        // Store as private file
        $this->storage->disk('local')->put(
            "invoices/{$filename}",
            $pdf,
            'private'
        );

        return "invoices/{$filename}";
    }

    public function download(string $path, User $user): Response
    {
        // Check authorization
        if (!$this->canDownload($path, $user)) {
            return new Response('Unauthorized', 403);
        }

        // Serve private file
        return $this->fileDownloader->download($path);
    }
}
```

## URL Generation

Generate URLs for stored files:

```php
// Get URL for public file
$url = $storage->disk('public')->url('avatars/user-1.jpg');
// Returns: http://localhost/storage/avatars/user-1.jpg

// For temporary access to private files (future feature)
$temporaryUrl = $storage->temporaryUrl('document.pdf', now()->addHours(1));
```

## Best Practices

### 1. Organize Files by Type

```php
storage/
├── app/
│   ├── avatars/
│   ├── documents/
│   ├── exports/
│   ├── imports/
│   ├── invoices/
│   └── temp/
```

```php
$storage->put('avatars/user-1.jpg', $avatar);
$storage->put('documents/report-2025.pdf', $pdf);
$storage->put('exports/users-export.csv', $csv);
```

### 2. Use Meaningful File Names

```php
// Good
$filename = sprintf(
    'invoice-%s-%s.pdf',
    $order->getId(),
    date('Y-m-d')
);
$storage->put("invoices/{$filename}", $pdf);

// Bad
$storage->put('file123.pdf', $pdf);
```

### 3. Validate File Uploads

```php
class FileUploadService
{
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif'];
    private const MAX_SIZE = 5 * 1024 * 1024; // 5MB

    public function upload(UploadedFile $file): string
    {
        // Validate MIME type
        if (!in_array($file->getMimeType(), self::ALLOWED_TYPES)) {
            throw new ValidationException('Invalid file type');
        }

        // Validate size
        if ($file->getSize() > self::MAX_SIZE) {
            throw new ValidationException('File too large');
        }

        // Generate safe filename
        $filename = $this->generateSafeFilename($file);

        // Store file
        $this->storage->put($filename, file_get_contents($file->getPath()));

        return $filename;
    }

    private function generateSafeFilename(UploadedFile $file): string
    {
        $extension = $file->getExtension();
        $hash = hash('sha256', uniqid() . time());

        return "uploads/{$hash}.{$extension}";
    }
}
```

### 4. Clean Up Temporary Files

```php
class TempFileCleanup
{
    public function cleanup(): void
    {
        $files = $this->storage->allFiles('temp');
        $cutoff = time() - (24 * 3600); // 24 hours

        foreach ($files as $file) {
            if ($this->storage->lastModified($file) < $cutoff) {
                $this->storage->delete($file);
            }
        }
    }
}

// Run as scheduled task
$scheduler->daily(fn() => $cleanup->cleanup());
```

### 5. Use Streams for Large Files

```php
// Bad - loads entire file into memory
$contents = file_get_contents('/path/to/large-file.zip');
$storage->put('backups/archive.zip', $contents);

// Good - streams file
$stream = fopen('/path/to/large-file.zip', 'r');
$storage->putStream('backups/archive.zip', $stream);
fclose($stream);
```

### 6. Handle Missing Files Gracefully

```php
public function getAvatar(User $user): string
{
    $path = "avatars/user-{$user->getId()}.jpg";

    if (!$this->storage->exists($path)) {
        return $this->getDefaultAvatar();
    }

    return $this->storage->url($path);
}
```

### 7. Set Correct Visibility

```php
// Public files
$storage->disk('public')->put('images/logo.png', $image, 'public');

// Private files
$storage->disk('local')->put('documents/report.pdf', $pdf, 'private');
```

### 8. Use Atomic Operations

```php
// Write to temporary file first
$tempPath = 'temp/' . uniqid() . '.tmp';
$storage->put($tempPath, $contents);

// Then move to final location
$storage->move($tempPath, 'final/path.txt');
```

### 9. Implement File Versioning

```php
class VersionedFileStorage
{
    public function put(string $path, string $contents): void
    {
        // Keep backup of existing file
        if ($this->storage->exists($path)) {
            $version = date('YmdHis');
            $backup = "{$path}.{$version}.bak";
            $this->storage->copy($path, $backup);
        }

        $this->storage->put($path, $contents);
    }

    public function restore(string $path, string $version): void
    {
        $backup = "{$path}.{$version}.bak";

        if ($this->storage->exists($backup)) {
            $this->storage->copy($backup, $path);
        }
    }
}
```

### 10. Monitor Disk Usage

```php
class DiskMonitor
{
    public function getUsage(string $disk = 'local'): array
    {
        $files = $this->storage->disk($disk)->allFiles('');
        $totalSize = 0;

        foreach ($files as $file) {
            $totalSize += $this->storage->size($file);
        }

        return [
            'files' => count($files),
            'size' => $totalSize,
            'size_human' => $this->formatBytes($totalSize),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . ' ' . $units[$index];
    }
}
```

## Common Use Cases

### File Upload Handler

```php
class FileUploadHandler
{
    public function handle(UploadedFile $file, string $directory): string
    {
        // Validate file
        $this->validate($file);

        // Generate unique filename
        $filename = $this->generateFilename($file);
        $path = "{$directory}/{$filename}";

        // Store file
        $this->storage->put(
            $path,
            file_get_contents($file->getPath()),
            'public'
        );

        return $path;
    }

    private function validate(UploadedFile $file): void
    {
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new ValidationException('File too large');
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file->getMimeType(), $allowedTypes)) {
            throw new ValidationException('Invalid file type');
        }
    }

    private function generateFilename(UploadedFile $file): string
    {
        return uniqid() . '-' . time() . '.' . $file->getExtension();
    }
}
```

### Image Storage

```php
class ImageStorage
{
    public function store(UploadedFile $image, array $sizes = []): array
    {
        $filename = $this->generateFilename($image);
        $paths = [];

        // Store original
        $paths['original'] = "images/originals/{$filename}";
        $this->storage->put($paths['original'], file_get_contents($image->getPath()));

        // Store resized versions
        foreach ($sizes as $size => [$width, $height]) {
            $resized = $this->resize($image, $width, $height);
            $paths[$size] = "images/{$size}/{$filename}";
            $this->storage->put($paths[$size], $resized);
        }

        return $paths;
    }

    private function resize(UploadedFile $image, int $width, int $height): string
    {
        // Image resizing logic (using GD, Imagick, etc.)
    }
}

// Usage
$paths = $imageStorage->store($uploadedFile, [
    'thumbnail' => [150, 150],
    'medium' => [600, 600],
    'large' => [1200, 1200],
]);
```

### Export File Generation

```php
class ReportExporter
{
    public function export(array $data, string $format = 'csv'): string
    {
        $filename = "export-" . date('Y-m-d-His') . ".{$format}";
        $path = "exports/{$filename}";

        // Generate file content
        $content = $this->generate($data, $format);

        // Store file
        $this->storage->disk('local')->put($path, $content, 'private');

        // Schedule cleanup after 24 hours
        $this->scheduleCleanup($path);

        return $path;
    }

    private function scheduleCleanup(string $path): void
    {
        // Queue a job to delete the file after 24 hours
        dispatch(new DeleteFileJob($path))->delay(now()->addDay());
    }
}
```

## Future Enhancements

The following features are planned for future releases:

- **S3 Driver** - Amazon S3 cloud storage
- **FTP/SFTP Driver** - FTP server integration
- **Temporary URLs** - Time-limited access to private files
- **File Metadata** - Custom metadata storage
- **Cloud Storage** - Google Cloud, Azure support
- **Image Processing** - Built-in image manipulation

## See Also

- [Cache](13-cache.md) - Caching uploaded file metadata
- [Validation](18-validation.md) - Validating file uploads
- [HTTP Layer](05-http-layer.md) - Handling file uploads
- [Security](16-security.md) - Secure file handling
