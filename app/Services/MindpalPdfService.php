<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

class MindpalPdfService
{
    private const CHUNK_SIZE = 500;

    private const CHUNK_OVERLAP = 50;

    public function extractText(string $filePath): array
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($filePath);
        $pages = $pdf->getPages();

        $result = [];
        foreach ($pages as $i => $page) {
            $text = $page->getText();
            if (trim($text)) {
                $result[] = [
                    'page' => $i + 1,
                    'text' => trim($text),
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array{page: int, text: string}>  $pages
     * @return array<int, array{content: string, page_number: int, chunk_index: int, token_count: int}>
     */
    public function chunkPages(array $pages): array
    {
        $chunks = [];
        $index = 0;

        foreach ($pages as $pageData) {
            $words = preg_split('/\s+/', $pageData['text']);
            $totalWords = count($words);

            if ($totalWords <= self::CHUNK_SIZE) {
                $chunks[] = [
                    'content' => $pageData['text'],
                    'page_number' => $pageData['page'],
                    'chunk_index' => $index++,
                    'token_count' => $totalWords,
                ];

                continue;
            }

            $pos = 0;
            while ($pos < $totalWords) {
                $chunkWords = array_slice($words, $pos, self::CHUNK_SIZE);
                $chunks[] = [
                    'content' => implode(' ', $chunkWords),
                    'page_number' => $pageData['page'],
                    'chunk_index' => $index++,
                    'token_count' => count($chunkWords),
                ];
                $pos += self::CHUNK_SIZE - self::CHUNK_OVERLAP;
            }
        }

        return $chunks;
    }

    public function getPageCount(string $filePath): int
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($filePath);

        return count($pdf->getPages());
    }
}
