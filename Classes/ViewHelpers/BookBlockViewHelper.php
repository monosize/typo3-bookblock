<?php

namespace Monosize\Bookblock\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use Monosize\Bookblock\Service\PdfConverterServiceFactory;

class BookBlockViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    /**
     * Initialize arguments
     *
     * @return void
     * @throws \TYPO3Fluid\Fluid\Core\ViewHelper\Exception
     */
    public function initializeArguments()
    {
        $this->registerArgument('pdf', 'string', 'pdf filepath', true);
        $this->registerArgument('settings', 'array', 'Settings', true);
        $this->registerArgument('height', 'int', 'height', false, 800);
        $this->registerArgument('singlepages', 'bool', 'all pages in the pdf are single pages', false, true);
        $this->registerArgument('firstpagesingle', 'bool', 'the first page in the pdf is a single page', false, true);
        $this->registerArgument('lastpagesingle', 'bool', 'the last page in the pdf is a single page', false, true);
        $this->registerArgument('thumbwidth', 'int', 'thumbnail width', false, 300);
        $this->registerArgument('thumbheight', 'int', 'thumbnail height', false, 0);
        $this->registerArgument('thumbnailHeight', 'int', 'thumbnail height (alternative)', false, 100);
        $this->registerArgument('zoomHeight', 'int', 'zoom height', false, 1500);
    }

    /**
     * @return string
     *
     * @param array $arguments
     * @param callable|\Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $pdf = $arguments['pdf'];
        $settings = $arguments['settings'];
        $height = $arguments['height'];
        $singlepages = $arguments['singlepages'];
        $firstpagesingle = $arguments['firstpagesingle'];
        $lastpagesingle = $arguments['lastpagesingle'];
        $thumbwidth = $arguments['thumbwidth'] ?: $arguments['thumbnailHeight'] ?: 300;
        $thumbheight = $arguments['thumbheight'] ?: 0;
        $zoomHeight = $arguments['zoomHeight'] ?: 1500;

        // Verwende Factory um beste verfügbare PDF-Konvertierungs-Service zu erhalten
        $pdfConverterService = PdfConverterServiceFactory::create();
        
        // Convert relative path to absolute path
        if (!file_exists($pdf)) {
            $pdf = GeneralUtility::getFileAbsFileName($pdf);
        }
        
        if (!file_exists($pdf)) {
            throw new \RuntimeException('PDF file not found: ' . $pdf);
        }

        // Create configuration array similar to original
        $config = [
            'height' => $height,
            'thumbnailHeight' => $thumbwidth,
            'zoomHeight' => $zoomHeight,
            'singlepages' => $singlepages,
            'firstpagesingle' => $firstpagesingle,
            'lastpagesingle' => $lastpagesingle,
            'imageFolder' => $settings['imageFolder'] ?? 'fileadmin/processed/bookblock/'
        ];

        return $pdfConverterService->convertPdfToImages($pdf, $config);
    }
}