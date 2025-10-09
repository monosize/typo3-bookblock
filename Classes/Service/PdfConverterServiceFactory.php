<?php

declare(strict_types=1);

namespace Monosize\Bookblock\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;
use Psr\Log\LoggerInterface;

/**
 * Factory für PdfConverter Services
 * 
 * Wählt automatisch zwischen moderner (spatie/pdf-to-image) und 
 * legacy (ImageMagick) Implementierung basierend auf verfügbaren Dependencies
 */
class PdfConverterServiceFactory
{
    private static ?object $instance = null;
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * Get the best available PDF converter service
     */
    public static function create(): PdfConverterServiceInterface
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $factory = GeneralUtility::makeInstance(self::class);
        self::$instance = $factory->createService();
        
        return self::$instance;
    }

    /**
     * Create the most appropriate PDF converter service
     */
    private function createService(): PdfConverterServiceInterface
    {
        // Check if spatie/pdf-to-image is available
        if (class_exists(\Spatie\PdfToImage\Pdf::class)) {
            $this->logger->info('Using ModernPdfConverterService with spatie/pdf-to-image');
            return GeneralUtility::makeInstance(ModernPdfConverterService::class);
        }

        // Check if ImageMagick is available for legacy service
        if ($this->hasImageMagickSupport() || $this->hasGraphicsMagickSupport()) {
            $this->logger->info('Using legacy PdfConverterService with ImageMagick/GraphicsMagick');
            return GeneralUtility::makeInstance(PdfConverterService::class);
        }

        // Fallback: Create a minimal service that shows appropriate error
        $this->logger->warning('No PDF conversion tools available, using fallback service');
        return GeneralUtility::makeInstance(FallbackPdfConverterService::class);
    }

    /**
     * Check if ImageMagick is available
     */
    private function hasImageMagickSupport(): bool
    {
        $output = [];
        $returnVar = 0;
        exec('convert --version 2>&1', $output, $returnVar);
        
        return $returnVar === 0 && stripos(implode(' ', $output), 'imagemagick') !== false;
    }

    /**
     * Check if GraphicsMagick is available
     */
    private function hasGraphicsMagickSupport(): bool
    {
        $output = [];
        $returnVar = 0;
        exec('gm version 2>&1', $output, $returnVar);
        
        return $returnVar === 0;
    }

    /**
     * Get information about available PDF conversion methods
     */
    public function getAvailableMethods(): array
    {
        $methods = [];

        if (class_exists(\Spatie\PdfToImage\Pdf::class)) {
            $methods[] = [
                'name' => 'spatie/pdf-to-image',
                'type' => 'modern',
                'status' => 'available',
                'description' => 'Modern PHP library for PDF to image conversion'
            ];
        }

        if ($this->hasImageMagickSupport()) {
            $methods[] = [
                'name' => 'ImageMagick',
                'type' => 'legacy',
                'status' => 'available',
                'description' => 'Traditional ImageMagick binary for image conversion'
            ];
        }

        if ($this->hasGraphicsMagickSupport()) {
            $methods[] = [
                'name' => 'GraphicsMagick',
                'type' => 'legacy',
                'status' => 'available',
                'description' => 'GraphicsMagick fork of ImageMagick'
            ];
        }

        if (empty($methods)) {
            $methods[] = [
                'name' => 'None',
                'type' => 'fallback',
                'status' => 'error',
                'description' => 'No PDF conversion tools available'
            ];
        }

        return $methods;
    }

    /**
     * Reset instance (for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}