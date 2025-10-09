# BookBlock Extension for TYPO3 13.4

Interactive PDF document viewer with 3D page turning or flat gallery view.

## Features

- **PDF to Image Conversion**: Automatic conversion of PDF pages to images
- **Two Display Modes**: 
  - 3D BookBlock with page turning animation
  - Flat gallery view (LightBox style)
- **Modern JavaScript**: Stimulus controllers for interactive behavior
- **Responsive Design**: Works on desktop, tablet, and mobile devices
- **Accessibility**: WCAG-compliant with keyboard navigation
- **Performance**: Caching and lazy loading support
- **Build System Integration**: Automatically integrates with template extension assets

## Installation

### 1. Install the Extension

```bash
# Via Composer (recommended)
composer require monosize/typo3-bookblock

# Or manually place in custom/extensions/bookblock/
```

### 2. Install PDF Processing Dependencies

BookBlock requires a PDF processing library. Choose one of the following options:

#### Option A: spatie/pdf-to-image (Recommended)

```bash
composer require spatie/pdf-to-image
```

**Requirements:**
- PHP Imagick extension: `apt-get install php-imagick`
- Or PHP GD extension: `apt-get install php-gd`
- ImageMagick system package: `apt-get install imagemagick`

#### Option B: ImageMagick/GraphicsMagick (Legacy)

```bash
# Ubuntu/Debian
apt-get install imagemagick
# or
apt-get install graphicsmagick

# CentOS/RHEL
yum install ImageMagick
# or  
yum install GraphicsMagick

# macOS
brew install imagemagick
# or
brew install graphicsmagick
```

### 3. Activate Extension

```bash
# Via TYPO3 CLI
./vendor/bin/typo3 extension:activate bookblock

# Or activate in TYPO3 Backend: Admin Tools > Extensions
```

### 4. Clear Caches

```bash
./vendor/bin/typo3 cache:flush
```

## Usage

### 1. Add BookBlock Content Element

1. Create/edit a page in TYPO3 Backend
2. Add new content element
3. Choose "BookBlock" from "Special Elements"
4. Configure the BookBlock settings

### 2. Configuration Options

#### Basic Settings

- **PDF File**: Upload or select a PDF file
- **Display Mode**: Choose between 3D BookBlock or flat gallery
- **Image Height**: Height of generated images (default: 1000px)
- **Thumbnail Height**: Height for thumbnail navigation (default: 100px)  
- **Zoom Height**: Height for zoom view (default: 1500px)

#### Page Behavior

- **Single Pages**: Display all pages individually
- **First Page Single**: First page as single page (for book covers)
- **Last Page Single**: Last page as single page (for back covers)

#### Features

- **Enable Zoom**: Allow users to zoom images
- **Show Thumbnails**: Display thumbnail navigation
- **Animation Speed**: Page turning animation duration (ms)

#### Advanced Options

- **Preload Images**: Load all images in background
- **Lazy Loading**: Load images only when needed
- **Keyboard Navigation**: Enable arrow key navigation
- **Alt Text**: Description for screen readers
- **CSS Classes**: Additional CSS classes

## PDF Processing

BookBlock automatically detects and uses the best available PDF processing method:

1. **spatie/pdf-to-image** (preferred): Modern PHP library with excellent error handling
2. **ImageMagick**: Traditional system-level image processing
3. **GraphicsMagick**: Alternative to ImageMagick
4. **Fallback**: Shows helpful error messages when no tools are available

### Checking Available Methods

You can check which PDF processing methods are available:

```php
use Monosize\Bookblock\Service\PdfConverterServiceFactory;

$factory = new PdfConverterServiceFactory();
$methods = $factory->getAvailableMethods();
```

## Performance

### Caching

- Converted images are cached to avoid repeated processing
- Cache lifetime: 24 hours (configurable)
- Automatic cleanup of old cached images

### Optimization Tips

- Use appropriate image heights (1000-1500px for main view)
- Enable lazy loading for large PDFs
- Consider preloading for small PDFs (< 20 pages)
- Use thumbnail navigation for PDFs with many pages

## Build System Integration

BookBlock automatically integrates with the template extension build system:

- SCSS files are compiled into main CSS bundle
- JavaScript files are bundled with Stimulus controllers
- Automatic asset detection and compilation
- Support for separate chunks and lazy loading

## Troubleshooting

### PDF Conversion Issues

```bash
# Check if ImageMagick is working
convert --version

# Check PHP extensions
php -m | grep -i imagick
php -m | grep -i gd

# Test spatie/pdf-to-image
composer show spatie/pdf-to-image
```

### Common Issues

1. **"No PDF conversion tools available"**
   - Install spatie/pdf-to-image: `composer require spatie/pdf-to-image`
   - Or install ImageMagick: `apt-get install imagemagick php-imagick`

2. **"Permission denied" errors**
   - Ensure web server has write permissions to `fileadmin/processed/`
   - Check that `typo3temp/` is writable

3. **Large PDF files timing out**
   - Increase PHP `max_execution_time`
   - Consider splitting large PDFs into smaller sections

4. **Poor image quality**
   - Increase DPI setting (default: 150)
   - Adjust image height settings
   - Check ImageMagick quality settings

### Debug Mode

Enable debug logging in TYPO3 to see detailed PDF processing information:

```php
$GLOBALS['TYPO3_CONF_VARS']['LOG']['Monosize']['Bookblock']['writerConfiguration'] = [
    \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
        \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
            'logFile' => 'typo3temp/logs/bookblock.log'
        ]
    ]
];
```

## Development

### Requirements

- TYPO3 13.4+
- PHP 8.1+
- Node.js for asset compilation

### Building Assets

```bash
# In template extension root
yarn build --component=scss,js --domain=default

# With force rebuild
yarn build --component=scss,js --force
```

## License

GPL-2.0-or-later

## Support

For issues and support, please create an issue in the project repository.