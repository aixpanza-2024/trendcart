<?php
/**
 * Plain-text cleanup for anything people type that other people later see:
 * names, addresses, reviews, shop and product text. Many admin/shop/customer
 * pages show these with innerHTML, so HTML must never be stored in the first
 * place — this is what stops a name like "<img onerror=...>" running script
 * in an admin's browser.
 */
class InputClean {
    /** One line of text: tags and < > removed, whitespace collapsed, length capped. */
    public static function line($value, int $max = 255): string {
        $s = str_replace(['<', '>'], '', strip_tags((string)$value));
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $s)), 0, $max);
    }

    /** Multi-line text (reviews, descriptions): same, but keeps line breaks. */
    public static function text($value, int $max = 5000): string {
        $s = str_replace(['<', '>'], '', strip_tags((string)$value));
        $s = preg_replace('/[^\S\n]+/u', ' ', str_replace("\r\n", "\n", $s));
        return mb_substr(trim(preg_replace("/\n{3,}/", "\n\n", $s)), 0, $max);
    }
}
