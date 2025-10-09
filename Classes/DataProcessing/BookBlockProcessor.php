<?php

declare(strict_types=1);

namespace Monosize\Bookblock\DataProcessing;

use Monosize\Bookblock\Service\PdfConverterService;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Log\LogManager;

/**
 * BookBlock Data Processor
 * 
 * Verarbeitet FlexForm-Daten und bereitet sie für das Template vor
 * Konvertiert PDF-Dateien zu Bildern falls erforderlich
 */
class BookBlockProcessor implements DataProcessorInterface
{
    private LoggerInterface $logger;
    private ResourceFactory $resourceFactory;
    private PdfConverterService $pdfConverterService;

    public function __construct()
    {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $this->pdfConverterService = GeneralUtility::makeInstance(PdfConverterService::class);
    }

    /**
     * Process FlexForm data and prepare configuration for template
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        try {
            // Get FlexForm data
            $flexformData = $processedData['flexform'] ?? [];
            
            // Process configuration
            $config = $this->processConfiguration($flexformData, $cObj);
            
            // Process PDF and generate images if needed
            if (!empty($config['pdfFile'])) {
                $images = $this->processPdfFile($config['pdfFile'], $config);
                $config['images'] = $images;
            }
            
            // Generate unique BookBlock ID
            $config['bookBlockId'] = $this->generateBookBlockId($cObj);
            
            // Store processed config
            $targetVariableName = $processorConfiguration['as'] ?? 'config';
            $processedData[$targetVariableName] = $config;
            
            // Store images separately if requested
            $imagesVariableName = $processorConfiguration['images'] ?? null;
            if ($imagesVariableName && !empty($config['images'])) {
                $processedData[$imagesVariableName] = $config['images'];
            }

            $this->logger->debug('BookBlock data processed successfully', [
                'contentUid' => $cObj->data['uid'] ?? 0,
                'configKeys' => array_keys($config)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('BookBlock data processing failed', [
                'error' => $e->getMessage(),
                'contentUid' => $cObj->data['uid'] ?? 0
            ]);
            
            // Provide default configuration on error
            $processedData[$targetVariableName ?? 'config'] = $this->getDefaultConfiguration();
        }

        return $processedData;
    }

    /**
     * Process FlexForm configuration
     */
    private function processConfiguration(array $flexformData, ContentObjectRenderer $cObj): array
    {
        $config = [];
        
        // Main configuration
        $config['title'] = $this->getFlexFormValue($flexformData, 'title', 'sMain') ?: '';
        $config['description'] = $this->getFlexFormValue($flexformData, 'description', 'sMain') ?: '';
        $config['orientation'] = $this->getFlexFormValue($flexformData, 'orientation', 'sMain') ?: 'vertical';
        $config['pdfFile'] = $this->getFlexFormValue($flexformData, 'pdfFile', 'sMain') ?: '';
        
        // Display settings
        $config['animationSpeed'] = (int)($this->getFlexFormValue($flexformData, 'animationSpeed', 'sDisplay') ?: 600);
        $config['showThumbnails'] = (bool)$this->getFlexFormValue($flexformData, 'showThumbnails', 'sDisplay');
        $config['showPageNumbers'] = (bool)$this->getFlexFormValue($flexformData, 'showPageNumbers', 'sDisplay');
        $config['showPageInfo'] = (bool)$this->getFlexFormValue($flexformData, 'showPageInfo', 'sDisplay');
        $config['showToolbar'] = (bool)$this->getFlexFormValue($flexformData, 'showToolbar', 'sDisplay');
        $config['enableZoom'] = (bool)$this->getFlexFormValue($flexformData, 'enableZoom', 'sDisplay');
        $config['responsive'] = (bool)$this->getFlexFormValue($flexformData, 'responsive', 'sDisplay');
        $config['mobileBreakpoint'] = (int)($this->getFlexFormValue($flexformData, 'mobileBreakpoint', 'sDisplay') ?: 768);
        $config['includeAssets'] = (bool)$this->getFlexFormValue($flexformData, 'includeAssets', 'sDisplay');
        
        // Behavior settings
        $config['autoPlay'] = (bool)$this->getFlexFormValue($flexformData, 'autoPlay', 'sBehavior');
        $config['autoPlayInterval'] = (int)($this->getFlexFormValue($flexformData, 'autoPlayInterval', 'sBehavior') ?: 5000);
        $config['loop'] = (bool)$this->getFlexFormValue($flexformData, 'loop', 'sBehavior');
        $config['keyboardNavigation'] = (bool)$this->getFlexFormValue($flexformData, 'keyboardNavigation', 'sBehavior');
        $config['touchNavigation'] = (bool)$this->getFlexFormValue($flexformData, 'touchNavigation', 'sBehavior');
        
        // PDF settings
        $config['dpi'] = (int)($this->getFlexFormValue($flexformData, 'dpi', 'sPdfSettings') ?: 150);
        $config['imageFormat'] = $this->getFlexFormValue($flexformData, 'imageFormat', 'sPdfSettings') ?: 'jpg';
        $config['imageQuality'] = (int)($this->getFlexFormValue($flexformData, 'imageQuality', 'sPdfSettings') ?: 90);
        $config['maxWidth'] = (int)($this->getFlexFormValue($flexformData, 'maxWidth', 'sPdfSettings') ?: 1200);
        $config['maxHeight'] = (int)($this->getFlexFormValue($flexformData, 'maxHeight', 'sPdfSettings') ?: 1600);
        
        // Apply defaults for unset boolean values
        if (!isset($flexformData['data']['sDisplay']['lDEF']['showPageNumbers'])) {
            $config['showPageNumbers'] = true;
        }
        if (!isset($flexformData['data']['sDisplay']['lDEF']['showPageInfo'])) {
            $config['showPageInfo'] = true;
        }
        if (!isset($flexformData['data']['sDisplay']['lDEF']['showToolbar'])) {
            $config['showToolbar'] = true;
        }
        if (!isset($flexformData['data']['sDisplay']['lDEF']['enableZoom'])) {
            $config['enableZoom'] = true;
        }
        if (!isset($flexformData['data']['sDisplay']['lDEF']['responsive'])) {
            $config['responsive'] = true;
        }
        if (!isset($flexformData['data']['sDisplay']['lDEF']['includeAssets'])) {
            $config['includeAssets'] = true;
        }
        if (!isset($flexformData['data']['sBehavior']['lDEF']['keyboardNavigation'])) {
            $config['keyboardNavigation'] = true;
        }
        if (!isset($flexformData['data']['sBehavior']['lDEF']['touchNavigation'])) {
            $config['touchNavigation'] = true;
        }
        
        return $config;
    }

    /**
     * Get FlexForm field value
     */
    private function getFlexFormValue(array $flexformData, string $fieldName, string $sheetName): ?string
    {
        return $flexformData['data'][$sheetName]['lDEF'][$fieldName]['vDEF'] ?? null;
    }

    /**
     * Process PDF file and convert to images
     */
    private function processPdfFile(string $pdfFileReference, array $config): array
    {
        if (empty($pdfFileReference)) {
            return [];
        }

        try {
            // PDF-Konvertierungs-Konfiguration erstellen
            $conversionConfig = [
                'dpi' => $config['dpi'],
                'format' => $config['imageFormat'],
                'quality' => $config['imageQuality'],
                'maxWidth' => $config['maxWidth'],
                'maxHeight' => $config['maxHeight'],
                'backgroundColor' => 'white'
            ];

            // PDF zu Bildern konvertieren
            $images = $this->pdfConverterService->convertPdfToImages($pdfFileReference, $conversionConfig);
            
            $this->logger->info('PDF converted to images', [
                'pdfFile' => $pdfFileReference,
                'imageCount' => count($images),
                'config' => $conversionConfig
            ]);

            return $images;

        } catch (\Exception $e) {
            $this->logger->error('Failed to process PDF file', [
                'pdfFile' => $pdfFileReference,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Generate unique BookBlock ID
     */
    private function generateBookBlockId(ContentObjectRenderer $cObj): string
    {
        $uid = $cObj->data['uid'] ?? 0;
        $pid = $cObj->data['pid'] ?? 0;
        
        return 'bookblock-' . $uid . '-' . $pid . '-' . time();
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfiguration(): array
    {
        return [
            'title' => '',
            'description' => '',
            'orientation' => 'vertical',
            'animationSpeed' => 600,
            'showThumbnails' => false,
            'showPageNumbers' => true,
            'showPageInfo' => true,
            'showToolbar' => true,
            'enableZoom' => true,
            'responsive' => true,
            'mobileBreakpoint' => 768,
            'includeAssets' => true,
            'autoPlay' => false,
            'autoPlayInterval' => 5000,
            'loop' => false,
            'keyboardNavigation' => true,
            'touchNavigation' => true,
            'dpi' => 150,
            'imageFormat' => 'jpg',
            'imageQuality' => 90,
            'maxWidth' => 1200,
            'maxHeight' => 1600,
            'pdfFile' => '',
            'images' => [],
            'bookBlockId' => 'bookblock-' . time()
        ];
    }
}