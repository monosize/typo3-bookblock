<?php

declare(strict_types=1);

namespace Monosize\Bookblock\Service;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\Processing\LocalImageProcessor;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;
use Psr\Log\LoggerInterface;

/**
 * PDF Converter Service
 * 
 * Konvertiert PDF-Dateien zu Bildern für BookBlock-Darstellung
 * Basiert auf der ursprünglichen PdfToJpegService-Funktionalität
 * Modernisiert für TYPO3 13.4 mit FAL-Integration
 */
class PdfConverterService implements SingletonInterface, PdfConverterServiceInterface
{
    private LoggerInterface $logger;
    private ResourceFactory $resourceFactory;
    private ProcessedFileRepository $processedFileRepository;
    
    // Original PdfToJpegService properties
    protected string $fileFormat = 'jpeg';
    protected string $fileExtension = 'jpg';
    protected string $imageFilenameSingle = '';
    protected int $maxCount = 0;
    protected int $steps = 1;
    protected string $pathToConvert = '';
    protected string $pathToPdfinfo = '';
    protected string $imgFolder = '';
    protected array $pathinfo = [];
    protected bool $useGraphicsMagick = false;

    public function __construct()
    {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $this->processedFileRepository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
        
        // Initialize convert tools like in original service
        $this->initializeConvert();
    }

    /**
     * Legacy-Methode für Kompatibilität mit ursprünglichem BookBlockViewHelper
     * 
     * @param string $pdfPath Absoluter Pfad zur PDF-Datei
     * @param string $imageFolder Zielordner für Bilder
     * @param int $height Höhe der generierten Bilder
     * @param int $width Breite der generierten Bilder (unused)
     * @param int $quality Qualität (unused)
     * @param bool $singlepages Alle Seiten als Einzelseiten
     * @param bool $firstpagesingle Erste Seite als Einzelseite
     * @param bool $lastpagesingle Letzte Seite als Einzelseite
     * @return string JSON-String mit Bildkonfiguration
     */
    public function process(string $pdfPath, string $imageFolder, int $height, int $width = 0, int $quality = 0, bool $singlepages = true, bool $firstpagesingle = true, bool $lastpagesingle = true): string
    {
        // Verwende die originale Logik für vollständige Kompatibilität
        $images = $this->processOriginal($pdfPath, $imageFolder, $height, 30, 10, $singlepages, $firstpagesingle, $lastpagesingle);
        
        if ($images === false) {
            return json_encode([
                'error' => 'PDF conversion failed',
                'images' => [],
                'singlepages' => $singlepages,
                'firstpagesingle' => $firstpagesingle,
                'lastpagesingle' => $lastpagesingle,
                'totalPages' => 0
            ]);
        }
        
        // Convert file paths to web-accessible URLs
        $webImages = [];
        foreach ($images as $imagePath) {
            // Remove the file system base path and make it web-accessible
            $webPath = str_replace(GeneralUtility::getFileAbsFileName(''), '', $imagePath);
            $webImages[] = [
                'url' => $webPath,
                'path' => $imagePath
            ];
        }
        
        // Format für BookBlock JavaScript
        $bookBlockConfig = [
            'images' => $webImages,
            'singlepages' => $singlepages,
            'firstpagesingle' => $firstpagesingle,
            'lastpagesingle' => $lastpagesingle,
            'totalPages' => count($webImages)
        ];

        return json_encode($bookBlockConfig);
    }

    /**
     * PDF zu Bildern konvertieren
     * 
     * @param string|int $pdfFileReference PDF-Datei (FAL-UID oder Pfad)
     * @param array $config Konvertierungs-Konfiguration
     * @return array Array von Bild-URLs
     */
    public function convertPdfToImages($pdfFileReference, array $config = []): array
    {
        try {
            // PDF-Datei laden
            $pdfFile = $this->getPdfFile($pdfFileReference);
            if (!$pdfFile) {
                $this->logger->error('PDF file not found or invalid', ['reference' => $pdfFileReference]);
                return [];
            }

            // Prüfen ob PDF-Datei bereits konvertiert wurde
            $cacheKey = $this->generateCacheKey($pdfFile, $config);
            $cachedImages = $this->getCachedImages($cacheKey);
            
            if (!empty($cachedImages)) {
                $this->logger->info('Using cached PDF images', ['cacheKey' => $cacheKey]);
                return $cachedImages;
            }

            // PDF konvertieren
            $images = $this->performPdfConversion($pdfFile, $config);
            
            if (!empty($images)) {
                // Ergebnis cachen
                $this->cacheImages($cacheKey, $images);
                $this->logger->info('PDF successfully converted to images', [
                    'pdfFile' => $pdfFile->getName(),
                    'imageCount' => count($images)
                ]);
            }

            return $images;

        } catch (\Exception $e) {
            $this->logger->error('PDF conversion failed', [
                'error' => $e->getMessage(),
                'pdfReference' => $pdfFileReference
            ]);
            return [];
        }
    }

    /**
     * PDF-Datei aus Referenz laden
     */
    private function getPdfFile($pdfFileReference): ?FileInterface
    {
        try {
            if (is_numeric($pdfFileReference)) {
                // FAL-UID
                return $this->resourceFactory->getFileObject((int)$pdfFileReference);
            } elseif (is_string($pdfFileReference)) {
                // Pfad zur Datei
                return $this->resourceFactory->getFileObjectFromCombinedIdentifier($pdfFileReference);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Could not load PDF file', [
                'reference' => $pdfFileReference,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Tatsächliche PDF-Konvertierung durchführen
     */
    private function performPdfConversion(FileInterface $pdfFile, array $config): array
    {
        $images = [];
        
        // Standard-Konfiguration
        $defaultConfig = [
            'dpi' => 150,
            'format' => 'jpg',
            'quality' => 90,
            'maxWidth' => 1200,
            'maxHeight' => 1600,
            'backgroundColor' => 'white'
        ];
        
        $config = array_merge($defaultConfig, $config);

        try {
            // ImageMagick/GraphicsMagick für PDF-Konvertierung verwenden
            if ($this->hasImageMagickSupport()) {
                $images = $this->convertWithImageMagick($pdfFile, $config);
            } elseif ($this->hasGhostscriptSupport()) {
                $images = $this->convertWithGhostscript($pdfFile, $config);
            } else {
                throw new \RuntimeException('No PDF conversion tool available (ImageMagick or Ghostscript required)');
            }

        } catch (\Exception $e) {
            $this->logger->error('PDF conversion tool failed', [
                'error' => $e->getMessage(),
                'file' => $pdfFile->getName()
            ]);
            throw $e;
        }

        return $images;
    }

    /**
     * PDF mit ImageMagick konvertieren
     */
    private function convertWithImageMagick(FileInterface $pdfFile, array $config): array
    {
        $images = [];
        $pdfPath = $pdfFile->getForLocalProcessing();
        
        // Temporäres Verzeichnis für Bilder
        $tempDir = GeneralUtility::tempnam('bookblock_pdf_', '');
        unlink($tempDir);
        mkdir($tempDir);
        
        try {
            // ImageMagick-Befehl zusammenstellen
            $outputPattern = $tempDir . '/page_%03d.' . $config['format'];
            $command = sprintf(
                'convert -density %d "%s" -background %s -flatten -quality %d -resize %dx%d> "%s"',
                $config['dpi'],
                $pdfPath,
                $config['backgroundColor'],
                $config['quality'],
                $config['maxWidth'],
                $config['maxHeight'],
                $outputPattern
            );

            // Befehl ausführen
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                throw new \RuntimeException('ImageMagick conversion failed: ' . implode("\n", $output));
            }

            // Generierte Bilder sammeln und in TYPO3 FAL importieren
            $generatedFiles = glob($tempDir . '/*.' . $config['format']);
            sort($generatedFiles); // Sicherstellen dass Seiten in richtiger Reihenfolge sind

            foreach ($generatedFiles as $imagePath) {
                $processedImage = $this->importImageToFal($imagePath, $pdfFile);
                if ($processedImage) {
                    $images[] = [
                        'url' => $processedImage->getPublicUrl(),
                        'width' => $processedImage->getProperty('width'),
                        'height' => $processedImage->getProperty('height'),
                        'page' => count($images) + 1
                    ];
                }
            }

        } finally {
            // Temporäre Dateien aufräumen
            GeneralUtility::rmdir($tempDir, true);
        }

        return $images;
    }

    /**
     * PDF mit Ghostscript konvertieren (Fallback)
     */
    private function convertWithGhostscript(FileInterface $pdfFile, array $config): array
    {
        // Ghostscript-Implementation als Fallback
        // Vereinfachte Implementation - in Produktionsumgebung erweitern
        
        $this->logger->info('Using Ghostscript for PDF conversion (fallback method)');
        
        // Hier würde die Ghostscript-Implementierung folgen
        // Für den Moment werfen wir eine Exception um ImageMagick zu forcieren
        throw new \RuntimeException('Ghostscript conversion not yet implemented');
    }

    /**
     * Bild in TYPO3 FAL importieren
     */
    private function importImageToFal(string $imagePath, FileInterface $originalFile): ?FileInterface
    {
        try {
            // Ziel-Storage und -Ordner ermitteln
            $storage = $originalFile->getStorage();
            $targetFolder = $storage->getFolder('bookblock_cache/');
            
            if (!$targetFolder->hasFolder('bookblock_cache')) {
                $targetFolder = $storage->createFolder('bookblock_cache');
            }

            // Eindeutigen Dateinamen generieren
            $fileName = 'pdf_' . $originalFile->getUid() . '_page_' . (time() % 10000) . '_' . basename($imagePath);
            
            // Datei in FAL-Storage kopieren
            $importedFile = $storage->addFile($imagePath, $targetFolder, $fileName);
            
            return $importedFile;

        } catch (\Exception $e) {
            $this->logger->error('Failed to import image to FAL', [
                'imagePath' => $imagePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * ImageMagick-Unterstützung prüfen
     */
    private function hasImageMagickSupport(): bool
    {
        $output = [];
        $returnVar = 0;
        exec('convert --version 2>&1', $output, $returnVar);
        
        return $returnVar === 0 && stripos(implode(' ', $output), 'imagemagick') !== false;
    }

    /**
     * Ghostscript-Unterstützung prüfen
     */
    private function hasGhostscriptSupport(): bool
    {
        $output = [];
        $returnVar = 0;
        exec('gs --version 2>&1', $output, $returnVar);
        
        return $returnVar === 0;
    }

    /**
     * Cache-Key für PDF-Konvertierung generieren
     */
    private function generateCacheKey(FileInterface $pdfFile, array $config): string
    {
        $keyData = [
            'file_uid' => $pdfFile->getUid(),
            'file_sha1' => $pdfFile->getSha1(),
            'config' => $config
        ];
        
        return 'bookblock_pdf_' . md5(serialize($keyData));
    }

    /**
     * Gecachte Bilder abrufen
     */
    private function getCachedImages(string $cacheKey): array
    {
        // TYPO3 Caching Framework verwenden
        $cache = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('runtime');
        
        return $cache->get($cacheKey) ?: [];
    }

    /**
     * Bilder im Cache speichern
     */
    private function cacheImages(string $cacheKey, array $images): void
    {
        $cache = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('runtime');
        
        // 24 Stunden cachen
        $cache->set($cacheKey, $images, [], 86400);
    }

    /**
     * PDF-Informationen abrufen (Seitenanzahl, etc.)
     */
    public function getPdfInfo(FileInterface $pdfFile): array
    {
        try {
            $pdfPath = $pdfFile->getForLocalProcessing();
            
            // PDF-Informationen mit pdfinfo abrufen (falls verfügbar)
            if ($this->hasPdfinfoSupport()) {
                return $this->getPdfInfoWithPdfinfo($pdfPath);
            }
            
            // Fallback: Basic-Informationen
            return [
                'pages' => 1,
                'title' => $pdfFile->getName(),
                'size' => $pdfFile->getSize()
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get PDF info', [
                'error' => $e->getMessage(),
                'file' => $pdfFile->getName()
            ]);
            
            return [];
        }
    }

    /**
     * PDF-Informationen mit pdfinfo abrufen
     */
    private function getPdfInfoWithPdfinfo(string $pdfPath): array
    {
        $output = [];
        $command = sprintf('pdfinfo "%s"', $pdfPath);
        exec($command, $output);
        
        $info = [];
        foreach ($output as $line) {
            if (preg_match('/^(\w+):\s*(.+)$/', $line, $matches)) {
                $key = strtolower($matches[1]);
                $value = trim($matches[2]);
                
                if ($key === 'pages') {
                    $info['pages'] = (int)$value;
                } else {
                    $info[$key] = $value;
                }
            }
        }
        
        return $info;
    }

    /**
     * pdfinfo-Tool-Unterstützung prüfen
     */
    private function hasPdfinfoSupport(): bool
    {
        $output = [];
        $returnVar = 0;
        exec('pdfinfo -v 2>&1', $output, $returnVar);
        
        return $returnVar === 0 || $returnVar === 99; // pdfinfo returns 99 for version info
    }
    
    // ===========================================
    // Original PdfToJpegService methods
    // ===========================================
    
    /**
     * Initialize Convert (from original PdfToJpegService)
     */
    public function initializeConvert(): void
    {
        $this->useGraphicsMagick = false;
        // search for convert
        if (empty($this->pathToConvert)) {
            if (@file_exists('/usr/bin/convert')) {
                $this->pathToConvert = '/usr/bin/';
            } elseif (@file_exists('/opt/local/bin/convert')) {
                $this->pathToConvert = '/opt/local/bin/';
            } elseif (@file_exists('/bin/convert')) {
                $this->pathToConvert = '/bin/';
            }
        }
        if (empty($this->pathToConvert)) {
            $this->useGraphicsMagick = true;
            if (@file_exists('/usr/bin/gm')) {
                $this->pathToConvert = '/usr/bin/';
            } elseif (@file_exists('/opt/local/bin/gm')) {
                $this->pathToConvert = '/opt/local/bin/';
            } elseif (@file_exists('/bin/gm')) {
                $this->pathToConvert = '/bin/';
            }
        }
        // search for pdfinfo
        if (empty($this->pathToPdfinfo)) {
            if (@file_exists('/usr/bin/pdfinfo')) {
                $this->pathToPdfinfo = '/usr/bin/';
            } elseif (@file_exists('/opt/local/bin/pdfinfo')) {
                $this->pathToPdfinfo = '/opt/local/bin/';
            } elseif (@file_exists('/bin/pdfinfo')) {
                $this->pathToPdfinfo = '/bin/';
            }
        }
    }
    
    /**
     * Original process method signature für vollständige Kompatibilität
     */
    public function processOriginal(string $file, string $imgFolder, int $height = 800, int $maxPages = 30, int $maxPercent = 10, bool $singlePages = false, bool $firstPageSingle = false, bool $lastPageSingle = false): array|bool
    {
        if (empty($this->pathToConvert)) {
            throw new \RuntimeException('Path to imagemagick convert or graphics magick gm not set!', 1453738794);
        }
        
        $this->imgFolder = $imgFolder;
        $this->pathinfo = pathinfo($file);

        if (strtolower($this->pathinfo['extension']) !== 'pdf') {
            return false;
        }
        
        if ($singlePages === true) {
            $lastPageSingle = false;
            $firstPageSingle = false;
        }
        
        $imageFilename = $this->prepareExportFilename($height);
        $files = [];
        
        if ($file !== '' && file_exists($file)) {
            $convertInfoFile = $imageFilename . '-convert.info';
            
            // Check if images of the pages exists
            if (file_exists($convertInfoFile)) {
                $convertInfo = file_get_contents($convertInfoFile);
                if ($convertInfo !== false) {
                    $convertInfo = unserialize($convertInfo);
                    if ($this->checkConvertInfo($convertInfo, filemtime($file), $singlePages, $firstPageSingle, $lastPageSingle)) {
                        // get all images
                        return $this->getImageFiles($imageFilename);
                    }
                }
                unlink($convertInfoFile);
                // delete all images
                $this->deleteOldFiles($imageFilename);
            }

            $pageCount = $this->numberOfPdfPages($file);

            // Generating the page images
            if ($pageCount) {
                $this->initialize($pageCount, $maxPages, $maxPercent, $imageFilename);
                $pages = $this->getPagesToProcess($this->maxCount, $this->steps);
                $files = $this->convertOriginal($file, $imageFilename, $height, $pages, $singlePages, $firstPageSingle, $lastPageSingle);
                
                file_put_contents($convertInfoFile, serialize([
                    'time'            => filemtime($file),
                    'singlePages'     => $singlePages,
                    'firstPageSingle' => $firstPageSingle,
                    'lastPageSingle'  => $lastPageSingle,
                ]));
            }
        }

        return $files;
    }
    
    /**
     * Check convert info (from original)
     */
    public function checkConvertInfo(array $convertInfo, int $modificationTime, bool $singlePage, bool $firstPageSingle, bool $lastPageSingle): bool
    {
        if (!is_array($convertInfo)) {
            return false;
        }
        if ($convertInfo['time'] !== $modificationTime) {
            return false;
        }
        if ($convertInfo['singlePages'] !== $singlePage) {
            return false;
        }
        if ($convertInfo['singlePages'] === false) {
            if ($convertInfo['firstPageSingle'] !== $firstPageSingle) {
                return false;
            }
            if ($convertInfo['lastPageSingle'] !== $lastPageSingle) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Original convert method
     */
    public function convertOriginal(string $file, string $imageFilename, int $height, array $pages, bool $singlePages = true, bool $firstPageSingle = false, bool $lastPageSingle = false): array
    {
        $files = [];
        
        if (!$singlePages) {
            if ($firstPageSingle) {
                $firstPage = array_shift($pages);
                if ($this->useGraphicsMagick) {
                    $cmd = escapeshellcmd($this->pathToConvert . 'gm convert -define pdf:fit-to-page=true -define pdf:use-cropbox=true -define pdf:use-trimbox=true -density 150 -colorspace RGB -scale x' . $height . ' -background white ') .
                        (' "' . addslashes($file . '[' . $firstPage . ']') . '" "' . addslashes(GeneralUtility::getFileAbsFileName('') . $this->imageFilenameSingle . '-%d.' . $this->fileExtension) . '"');
                } else {
                    $cmd = escapeshellcmd($this->pathToConvert . 'convert -define pdf:fit-to-page=true -define pdf:use-cropbox=true -define pdf:use-trimbox=true -density 150 -colorspace sRGB -scale x' . $height . ' -background white -alpha remove ') .
                        (' "' . addslashes($file . '[' . $firstPage . ']') . '" "' . addslashes(GeneralUtility::getFileAbsFileName('') . $this->imageFilenameSingle . '-%d.' . $this->fileExtension) . '"');
                }
                exec($cmd, $response, $ret);
            }
            
            if ($lastPageSingle) {
                $lastPage = array_pop($pages);
                $pagenumber = count($pages) * 2 + 1;
                if ($this->useGraphicsMagick) {
                    $cmd = escapeshellcmd($this->pathToConvert . 'gm convert -define pdf:fit-to-page=true -define pdf:use-cropbox=true -define pdf:use-trimbox=true -density 150 -colorspace RGB -scale x' . $height . ' -background white +adjoin ') .
                        (' "' . addslashes($file . '[' . $lastPage . ']') . '" -scene ' . $pagenumber . ' "' . addslashes(GeneralUtility::getFileAbsFileName('') . $this->imageFilenameSingle . '-%d.' . $this->fileExtension) . '"');
                } else {
                    $cmd = escapeshellcmd($this->pathToConvert . 'convert -define pdf:fit-to-page=true -define pdf:use-cropbox=true -define pdf:use-trimbox=true -density 150 -colorspace sRGB -scale x' . $height . ' -background white -alpha remove ') .
                        (' "' . addslashes($file . '[' . $lastPage . ']') . '" -scene ' . $pagenumber . ' "' . addslashes(GeneralUtility::getFileAbsFileName('') . $this->imageFilenameSingle . '-%d.' . $this->fileExtension) . '"');
                }
                exec($cmd, $response, $ret);
            }
            
            if ($this->useGraphicsMagick) {
                $cmd = escapeshellcmd($this->pathToConvert . 'gm convert -define pdf:fit-to-page=true -define pdf:use-cropbox=true -define pdf:use-trimbox=true -density 150 -colorspace RGB -scale x' . $height . ' -background white -crop 50%x100% +repage +adjoin ') .
                    (' "' . addslashes($file . '[' . reset($pages) . '-' . end($pages) . ']') . '" -scene ' . ($firstPageSingle ? '1' : '0') . ' "' . addslashes(GeneralUtility::getFileAbsFileName('') . $this->imageFilenameSingle . '-%d.' . $this->fileExtension) . '"');
            } else {
                $cmd = escapeshellcmd($this->pathToConvert . 'convert -define pdf:fit-to-page=true -define pdf:use-cropbox=true -define pdf:use-trimbox=true -density 150 -colorspace sRGB -scale x' . $height . ' -background white -alpha remove -crop 50%x100% +repage ') .
                    (' "' . addslashes($file . '[' . reset($pages) . '-' . end($pages) . ']') . '" -scene ' . ($firstPageSingle ? '1' : '0') . ' "' . addslashes(GeneralUtility::getFileAbsFileName('') . $this->imageFilenameSingle . '-%d.' . $this->fileExtension) . '"');
            }
            exec($cmd, $response, $ret);
        } else {
            if ($this->useGraphicsMagick) {
                $cmd = escapeshellcmd($this->pathToConvert . 'gm convert -define pdf:fit-to-page=true -define pdf:use-cropbox=true -define pdf:use-trimbox=true -density 150 -colorspace RGB -scale x' . $height . ' -background white +adjoin ') .
                    (' "' . addslashes($file . '[' . reset($pages) . '-' . end($pages) . ']') . '" "' . addslashes(GeneralUtility::getFileAbsFileName('') . $this->imageFilenameSingle . '-%d.' . $this->fileExtension) . '"');
            } else {
                $cmd = escapeshellcmd($this->pathToConvert . 'convert -define pdf:fit-to-page=true -define pdf:use-cropbox=true -define pdf:use-trimbox=true -density 150 -colorspace sRGB -scale x' . $height . ' -background white -alpha remove ') .
                    (' "' . addslashes($file . '[' . reset($pages) . '-' . end($pages) . ']') . '" -set filename:page "%[fx:t+i]" "' . addslashes(GeneralUtility::getFileAbsFileName('') . $this->imageFilenameSingle . '-%[filename:page].' . $this->fileExtension) . '"');
            }
            exec($cmd, $response, $ret);
        }
        
        if ($ret === 0) {
            $files = $this->getImageFiles($imageFilename);
        }
        
        return $files;
    }
    
    /**
     * Prepare export filename (from original)
     */
    public function prepareExportFilename(int $height = 0): string
    {
        return str_replace(' ', '-', $this->imgFolder . $this->pathinfo['filename'] . '_page' . ($height ? '-' . $height : ''));
    }
    
    /**
     * Get image files (from original)
     */
    public function getImageFiles(string $imageFilename): array
    {
        $files = [];
        $i = 0;
        while (true) {
            $jpgFile = $imageFilename . '-' . $i . '.jpg';
            if (file_exists(GeneralUtility::getFileAbsFileName('') . $jpgFile)) {
                $files[] = $jpgFile;
                $i++;
            } else {
                break;
            }
        }
        return $files;
    }
    
    /**
     * Delete old files (from original)
     */
    public function deleteOldFiles(string $imageFilename): void
    {
        $i = 0;
        while (true) {
            $jpgFile = $imageFilename . '-' . $i . '.jpg';
            $fullPath = GeneralUtility::getFileAbsFileName('') . $jpgFile;
            if (file_exists($fullPath)) {
                unlink($fullPath);
                $i++;
            } else {
                break;
            }
        }
    }
    
    /**
     * Initialize processing parameters (from original)
     */
    public function initialize(int $pageCount, int $maxPages, int $maxPercent, string $imageFilename): void
    {
        if ($maxPages > 0) {
            if ($maxPercent > 0) {
                $this->maxCount = min((int)round($pageCount * $maxPercent / 100), $maxPages);
            } else {
                $this->maxCount = min($pageCount, $maxPages);
            }
        } else {
            if ($maxPercent > 0) {
                $this->maxCount = (int)round($pageCount * $maxPercent / 100);
            } else {
                $this->maxCount = $pageCount;
            }
        }
        
        $this->steps = 1;
        $this->fileFormat = 'jpeg';
        $this->fileExtension = 'jpg';
        $this->imageFilenameSingle = $imageFilename;
    }
    
    /**
     * Get pages to process (from original)
     */
    public function getPagesToProcess(int $maxCount, int $steps): array
    {
        $pages = [];
        for ($i = 0; $i < $maxCount; $i += $steps) {
            $pages[] = $i;
        }
        return $pages;
    }
    
    /**
     * Get number of PDF pages using pdfinfo (from original)
     */
    public function numberOfPdfPages(string $pdfFile): int
    {
        $cmd = $this->pathToPdfinfo . 'pdfinfo';
        exec("$cmd \"$pdfFile\"", $output);
        
        $pagecount = 0;
        foreach ($output as $op) {
            if (preg_match('/Pages:\\s*(\\d+)/i', $op, $matches) === 1) {
                $pagecount = (int)$matches[1];
                break;
            }
        }
        
        return $pagecount;
    }
}