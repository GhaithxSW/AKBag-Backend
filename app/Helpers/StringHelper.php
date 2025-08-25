<?php

namespace App\Helpers;

trait StringHelper
{
    /**
     * Remove Chinese characters from a string
     */
    protected function removeChineseCharacters(?string $string): string
    {
        if (empty($string)) {
            return '';
        }

        // Remove Chinese characters (Unicode range for Chinese)
        $string = preg_replace('/[\x{4e00}-\x{9fff}]/u', '', $string);

        // Clean up any extra spaces or dashes
        $string = preg_replace('/\s+/', ' ', $string); // Multiple spaces to single space
        $string = preg_replace('/-+/', '-', $string);   // Multiple dashes to single dash
        $string = trim($string, ' -');                 // Trim spaces and dashes

        return $string;
    }

    /**
     * Clean and format a title by removing unwanted characters
     */
    protected function cleanTitle(?string $title): string
    {
        if (empty($title)) {
            return 'Untitled';
        }

        // Remove Chinese characters first
        $title = $this->removeChineseCharacters($title);

        // Remove special characters but keep letters, numbers, spaces, and basic punctuation
        $title = preg_replace('/[^\p{L}\p{N}\s\-\'\&\,\.\!\?]/u', '', $title);

        // Replace multiple spaces with single space
        $title = preg_replace('/\s+/', ' ', $title);

        return trim($title);
    }

    /**
     * Generate a slug from a string, ensuring no Chinese characters
     */
    protected function generateSlug(string $string): string
    {
        $string = $this->cleanTitle($string);

        // Convert to lowercase
        $string = mb_strtolower($string, 'UTF-8');

        // Replace spaces with dashes
        $string = str_replace(' ', '-', $string);

        // Remove any remaining special characters
        $string = preg_replace('/[^a-z0-9\-]/', '', $string);

        // Remove multiple dashes
        $string = preg_replace('/-+/', '-', $string);

        // Trim dashes from start and end
        return trim($string, '-');
    }
}
