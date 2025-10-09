<?php

declare(strict_types=1);

namespace Monosize\Bookblock\Service;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;
use Psr\Log\LoggerInterface;

/**
 * Fallback PDF Converter Service
 * 
 * Wird verwendet wenn keine PDF-Konvertierungs-Tools verfügbar sind
 * Bietet aussagekräftige Fehlermeldungen und Installationshinweise
 */
class FallbackPdfConverterService implements SingletonInterface, PdfConverterServiceInterface
{
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * Legacy-Methode - gibt Fehler zurück
     */
    public function process(string $pdfPath, string $imageFolder, int $height, int $width = 0, int $quality = 0, bool $singlepages = true, bool $firstpagesingle = true, bool $lastpagesingle = true): string
    {
        $this->logger->error('PDF conversion attempted but no tools available', [
            'pdfPath' => $pdfPath
        ]);

        return json_encode([
            'error' => 'PDF conversion not available',
            'message' => 'No PDF conversion tools are installed. Please install spatie/pdf-to-image via Composer or ensure ImageMagick/GraphicsMagick is available.',
            'instructions' => [
                'Install spatie/pdf-to-image: composer require spatie/pdf-to-image',
                'Or install ImageMagick: apt-get install imagemagick (Ubuntu/Debian)',
                'Or install GraphicsMagick: apt-get install graphicsmagick (Ubuntu/Debian)'
            ],
            'images' => [],
            'singlepages' => $singlepages,
            'firstpagesingle' => $firstpagesingle,
            'lastpagesingle' => $lastpagesingle,
            'totalPages' => 0
        ]);
    }

    /**
     * PDF zu Bildern konvertieren - gibt leeres Array zurück
     */
    public function convertPdfToImages(string $pdfPath, array $config = []): array
    {
        $this->logger->warning('PDF conversion requested but no tools available', [
            'pdfPath' => $pdfPath,
            'config' => $config
        ]);

        return [];
    }

    /**
     * PDF-Informationen abrufen - gibt Basis-Informationen zurück
     */
    public function getPdfInfo(string $pdfPath): array
    {
        $this->logger->info('PDF info requested, returning basic file info only', [
            'pdfPath' => $pdfPath
        ]);

        if (!file_exists($pdfPath)) {
            return [
                'error' => 'PDF file not found',
                'pages' => 0,
                'file_size' => 0,
                'file_name' => basename($pdfPath)
            ];
        }

        return [
            'pages' => 'unknown',
            'file_size' => filesize($pdfPath),
            'file_name' => basename($pdfPath),
            'modified' => filemtime($pdfPath),
            'warning' => 'Cannot determine page count - no PDF processing tools available',
            'instructions' => 'Install spatie/pdf-to-image or ImageMagick for full PDF processing'
        ];
    }

    /**
     * Get installation instructions for PDF processing tools
     */
    public function getInstallationInstructions(): array
    {
        return [
            'composer' => [
                'description' => 'Install modern PHP-based PDF processing (recommended)',
                'command' => 'composer require spatie/pdf-to-image',
                'requirements' => 'Requires Imagick or GD PHP extension'
            ],
            'imagemagick' => [
                'description' => 'Install ImageMagick system package',
                'ubuntu_debian' => 'apt-get install imagemagick',
                'centos_rhel' => 'yum install ImageMagick',
                'macos' => 'brew install imagemagick'
            ],
            'graphicsmagick' => [
                'description' => 'Install GraphicsMagick system package (alternative)',
                'ubuntu_debian' => 'apt-get install graphicsmagick',
                'centos_rhel' => 'yum install GraphicsMagick',
                'macos' => 'brew install graphicsmagick'
            ]
        ];
    }
}