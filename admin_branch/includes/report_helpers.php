<?php
/**
 * Shared helper functions for consistent report formatting
 */

// Helper function to get rank abbreviations

// Helper function to format names in title case (capitalize each word)
function formatSentenceCase($name) {
    if (empty($name)) return '';
    return ucwords(strtolower(trim($name)));
}
?>