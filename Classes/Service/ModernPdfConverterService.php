<?php

declare(strict_types=1);

namespace Monosize\Bookblock\Service;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Storage\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;
use Psr\Log\LoggerInterface;
use Spatie\PdfToImage\Pdf;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Modern PDF Converter Service using spatie/pdf-to-image
 * 
 * Moderne Alternative zum ursprünglichen PdfToJpegService
 * Verwendet Composer Package statt manuelle ImageMagick-Integration
 */
class ModernPdfConverterService implements SingletonInterface, PdfConverterServiceInterface
{
    private LoggerInterface $logger;
    private ResourceFactory $resourceFactory;
    private FrontendInterface $cache;

    public function __construct()
    {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $this->cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('bookblock');
    }

    /**
     * Legacy-Methode für Kompatibilität mit ursprünglichem BookBlockViewHelper
     */
    public function process(string $pdfPath, string $imageFolder, int $height, int $width = 0, int $quality = 0, bool $singlepages = true, bool $firstpagesingle = true, bool $lastpagesingle = true): string
    {
        try {
            $config = [
                'height' => $height,
                'quality' => $quality ?: 90,
                'imageFolder' => $imageFolder,
                'singlepages' => $singlepages,
                'firstpagesingle' => $firstpagesingle,
                'lastpagesingle' => $lastpagesingle,
                'dpi' => 150,
                'format' => 'jpg'
            ];

            $images = $this->convertPdfToImages($pdfPath, $config);
            
            // Format für BookBlock JavaScript
            $bookBlockConfig = [
                'images' => $images,
                'singlepages' => $singlepages,
                'firstpagesingle' => $firstpagesingle,
                'lastpagesingle' => $lastpagesingle,
                'totalPages' => count($images)
            ];

            return json_encode($bookBlockConfig);

        } catch (\Exception $e) {
            $this->logger->error('PDF conversion failed', [
                'pdfPath' => $pdfPath,
                'error' => $e->getMessage()
            ]);

            return json_encode([
                'error' => 'PDF conversion failed: ' . $e->getMessage(),
                'images' => [],
                'singlepages' => $singlepages,
                'firstpagesingle' => $firstpagesingle,
                'lastpagesingle' => $lastpagesingle,
                'totalPages' => 0
            ]);
        }
    }

    /**
     * Convert PDF to images using spatie/pdf-to-image
     */
    public function convertPdfToImages(string $pdfPath, array $config = []): array
    {
        $cacheKey = $this->generateCacheKey($pdfPath, $config);
        
        // Check cache first
        if ($this->cache->has($cacheKey)) {
            $cachedResult = $this->cache->get($cacheKey);
            if ($cachedResult) {
                $this->logger->info('Using cached PDF conversion result', ['cacheKey' => $cacheKey]);
                return $cachedResult;
            }
        }

        $images = [];
        
        try {
            // Verify PDF file exists and is readable
            if (!file_exists($pdfPath) || !is_readable($pdfPath)) {
                throw new \RuntimeException("PDF file not found or not readable: {$pdfPath}");
            }

            // Create Spatie PDF instance
            $pdf = new Pdf($pdfPath);
            
            // Set conversion parameters
            $dpi = $config['dpi'] ?? 150;
            $quality = $config['quality'] ?? 90;
            $format = $config['format'] ?? 'jpg';
            $height = $config['height'] ?? 1000;
            
            // Configure output settings
            $pdf->setResolution($dpi)
                ->setQuality($quality)
                ->setFormat($format);

            // Create output directory
            $outputDir = $this->createOutputDirectory($config['imageFolder'] ?? 'fileadmin/processed/bookblock/');
            
            $pageCount = $pdf->getNumberOfPages();
            $this->logger->info("Processing PDF with {$pageCount} pages", ['pdfPath' => $pdfPath]);
            
            // Convert each page
            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $filename = $this->generateImageFilename($pdfPath, $pageNumber, $format, $height);
                $outputPath = $outputDir . '/' . $filename;
                
                // Convert page to image
                $pdf->setPage($pageNumber)
                    ->saveImage($outputPath);
                
                // Resize if height is specified (using GD/Imagick)
                if ($height > 0) {
                    $this->resizeImage($outputPath, $height);
                }
                
                $webPath = $this->getWebPath($outputPath);
                $imageSize = getimagesize($outputPath);
                
                $images[] = [
                    'url' => $webPath,
                    'path' => $outputPath,
                    'page' => $pageNumber,
                    'width' => $imageSize[0] ?? 0,
                    'height' => $imageSize[1] ?? 0,
                    'size' => filesize($outputPath)
                ];
                
                $this->logger->debug("Converted page {$pageNumber}", [
                    'outputPath' => $outputPath,
                    'webPath' => $webPath
                ]);
            }
            
            // Cache result for 24 hours
            $this->cache->set($cacheKey, $images, [], 86400);
            
            $this->logger->info("Successfully converted PDF to {$pageCount} images", [
                'pdfPath' => $pdfPath,
                'imageCount' => count($images)
            ]);
            
            return $images;
            
        } catch (\Exception $e) {
            $this->logger->error('PDF conversion failed', [
                'pdfPath' => $pdfPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \RuntimeException("PDF conversion failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create output directory if it doesn't exist
     */
    private function createOutputDirectory(string $imageFolder): string
    {
        $outputDir = GeneralUtility::getFileAbsFileName($imageFolder);
        
        if (!file_exists($outputDir)) {
            if (!mkdir($outputDir, 0755, true)) {
                throw new \RuntimeException("Cannot create output directory: {$outputDir}");
            }
        }
        
        if (!is_writable($outputDir)) {
            throw new \RuntimeException("Output directory is not writable: {$outputDir}");
        }
        
        return $outputDir;
    }

    /**
     * Generate unique filename for converted image
     */
    private function generateImageFilename(string $pdfPath, int $pageNumber, string $format, int $height): string
    {
        $baseName = pathinfo($pdfPath, PATHINFO_FILENAME);
        $hash = substr(md5($pdfPath . filemtime($pdfPath)), 0, 8);
        
        return "{$baseName}_page_{$pageNumber}_{$height}px_{$hash}.{$format}";
    }

    /**
     * Resize image to specified height while maintaining aspect ratio
     */
    private function resizeImage(string $imagePath, int $targetHeight): void
    {
        $imageSize = getimagesize($imagePath);
        if (!$imageSize) {
            return;
        }
        
        $currentWidth = $imageSize[0];
        $currentHeight = $imageSize[1];
        
        // Calculate new dimensions maintaining aspect ratio
        $aspectRatio = $currentWidth / $currentHeight;
        $newHeight = $targetHeight;
        $newWidth = (int)($targetHeight * $aspectRatio);
        
        // Only resize if necessary
        if ($currentHeight <= $targetHeight) {
            return;
        }
        
        // Create new image
        $sourceImage = imagecreatefromjpeg($imagePath);
        if (!$sourceImage) {
            return;
        }
        
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG
        $mimeType = $imageSize['mime'] ?? '';
        if ($mimeType === 'image/png') {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefill($resizedImage, 0, 0, $transparent);
        }
        
        // Resize image
        imagecopyresampled(
            $resizedImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $currentWidth, $currentHeight
        );
        
        // Save resized image
        imagejpeg($resizedImage, $imagePath, 90);
        
        // Cleanup
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
    }

    /**
     * Convert file system path to web accessible path
     */
    private function getWebPath(string $filePath): string
    {
        $publicPath = GeneralUtility::getFileAbsFileName('');
        $webPath = str_replace($publicPath, '', $filePath);
        
        // Ensure web path starts with /
        return '/' . ltrim($webPath, '/');
    }

    /**
     * Generate cache key for PDF conversion
     */
    private function generateCacheKey(string $pdfPath, array $config): string
    {
        $keyData = [
            'file_path' => $pdfPath,
            'file_mtime' => filemtime($pdfPath),
            'config' => $config
        ];
        
        return 'bookblock_pdf_' . md5(serialize($keyData));
    }

    /**
     * Get PDF information (page count, dimensions, etc.)
     */
    public function getPdfInfo(string $pdfPath): array
    {
        try {
            if (!file_exists($pdfPath)) {
                throw new \RuntimeException("PDF file not found: {$pdfPath}");
            }
            
            $pdf = new Pdf($pdfPath);
            
            return [
                'pages' => $pdf->getNumberOfPages(),
                'file_size' => filesize($pdfPath),
                'file_name' => basename($pdfPath),
                'modified' => filemtime($pdfPath),
                'format' => 'PDF'
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get PDF info', [
                'pdfPath' => $pdfPath,
                'error' => $e->getMessage()
            ]);
            
            return [
                'pages' => 0,
                'file_size' => 0,
                'file_name' => basename($pdfPath),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Clean up old cached images
     */
    public function cleanupOldImages(int $maxAgeSeconds = 86400): int
    {
        $outputDirs = [
            GeneralUtility::getFileAbsFileName('fileadmin/processed/bookblock/'),
            GeneralUtility::getFileAbsFileName('typo3temp/assets/bookblock/')
        ];
        
        $deletedCount = 0;
        $cutoffTime = time() - $maxAgeSeconds;
        
        foreach ($outputDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            
            $files = glob($dir . '*.{jpg,jpeg,png}', GLOB_BRACE);
            foreach ($files as $file) {
                if (filemtime($file) < $cutoffTime) {
                    if (unlink($file)) {
                        $deletedCount++;
                    }
                }
            }
        }
        
        $this->logger->info("Cleaned up {$deletedCount} old image files");
        
        return $deletedCount;
    }
}