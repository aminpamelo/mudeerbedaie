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
            $text = trim($this->sanitizeUtf8($page->getText()));
            if ($text !== '') {
                $result[] = [
                    'page' => $i + 1,
                    'text' => $text,
                ];
            }
        }

        return $result;
    }

    /**
     * Guarantee valid UTF-8 so json_encode (used by the OpenAI SDK payload) never
     * throws on malformed bytes from the PDF parser, and drop control characters
     * plus the replacement characters left behind by scrubbing bad byte sequences.
     */
    public function sanitizeUtf8(string $text): string
    {
        $clean = mb_scrub($text, 'UTF-8');

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\x{FFFD}/u', '', $clean) ?? '';
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
