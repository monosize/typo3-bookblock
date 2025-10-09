<?php

declare(strict_types=1);

namespace Monosize\Bookblock\Service;

/**
 * Interface für PDF Converter Services
 */
interface PdfConverterServiceInterface
{
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
    public function process(string $pdfPath, string $imageFolder, int $height, int $width = 0, int $quality = 0, bool $singlepages = true, bool $firstpagesingle = true, bool $lastpagesingle = true): string;

    /**
     * PDF zu Bildern konvertieren
     * 
     * @param string $pdfPath PDF-Datei-Pfad
     * @param array $config Konvertierungs-Konfiguration
     * @return array Array von Bild-URLs
     */
    public function convertPdfToImages(string $pdfPath, array $config = []): array;

    /**
     * PDF-Informationen abrufen (Seitenanzahl, etc.)
     * 
     * @param string $pdfPath PDF-Datei-Pfad
     * @return array PDF-Informationen
     */
    public function getPdfInfo(string $pdfPath): array;
}