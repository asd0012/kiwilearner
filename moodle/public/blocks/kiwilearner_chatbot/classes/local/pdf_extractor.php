<?php
namespace block_kiwilearner_chatbot\local;

defined('MOODLE_INTERNAL') || die();

class pdf_extractor {

    /**
     * Extract text from a PDF file on disk using pdftotext.
     *
     * @param string $pdffilepath Absolute path to PDF
     * @return string Extracted text (trimmed)
     */
    public static function extract_text(string $pdffilepath): string {
        if (!is_readable($pdffilepath)) {
            error_log('KIWI PDF: file not readable: ' . $pdffilepath);
            return '';
        }

        // Ensure pdftotext exists.
        $bin = trim((string)@shell_exec('command -v pdftotext'));
        if ($bin === '') {
            error_log('KIWI PDF: pdftotext not found in container');
            return '';
        }

        $out = tempnam(sys_get_temp_dir(), 'kiwipdf_');
        if ($out === false) {
            error_log('KIWI PDF: tempnam failed for output');
            return '';
        }

        $errfile = tempnam(sys_get_temp_dir(), 'kiwipdf_err_');
        if ($errfile === false) {
            $errfile = '';
        }

        // Build command: pdftotext input.pdf output.txt
        $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($pdffilepath) . ' ' . escapeshellarg($out);
        if ($errfile !== '') {
            $cmd .= ' 2>' . escapeshellarg($errfile);
        } else {
            $cmd .= ' 2>/dev/null';
        }

        @shell_exec($cmd);

        $text = '';
        if (is_readable($out)) {
            $text = (string)(file_get_contents($out) ?: '');
        }

        // Log stderr output (if any).
        if ($errfile !== '' && is_readable($errfile)) {
            $err = trim((string)(file_get_contents($errfile) ?: ''));
            if ($err !== '') {
                error_log('KIWI PDF: pdftotext stderr: ' . $err);
            }
        }

        // Cleanup temp files.
        if (file_exists($out)) {
            @unlink($out);
        }
        if ($errfile !== '' && file_exists($errfile)) {
            @unlink($errfile);
        }

        $text = trim($text);
        error_log('KIWI PDF: extracted chars=' . strlen($text));

        return $text;
    }
}
