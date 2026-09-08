<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Text extraction from submitted files.
 *
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_authorcheck\local;

/**
 * Extracts plain text from supported file types.
 *
 * Dispatches by mimetype: PDF via `pdftotext`, DOCX via `pandoc`, and
 * plain text is read directly. Anything else throws.
 *
 * The command-line tools (poppler-utils, pandoc) must be installed on the
 * server. On the production Moodle this is an infrastructure dependency to
 * declare to IT; locally we installed them into the container.
 */
class text_extractor {
    /**
     * Extract text from a Moodle stored_file (e.g. an assignment submission).
     *
     * Copies the stored file to a temporary path first, because the CLI
     * tools need a real filesystem path to read from.
     *
     * @param \stored_file $file
     * @return string The extracted plain text (trimmed).
     */
    public function extract(\stored_file $file): string {
        // Prefer mimetype derived from the filename extension; stored
        // mimetypes on assignment files are sometimes generic.
        $extmap = [
            'pdf'  => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt'  => 'text/plain',
        ];
        $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        $mimetype = $extmap[$ext] ?? $file->get_mimetype();

        $tempdir = make_request_directory();
        $temppath = $tempdir . '/' . $file->get_filename();
        $file->copy_content_to($temppath);

        try {
            return $this->extract_from_path($temppath, $mimetype);
        } finally {
            if (file_exists($temppath)) {
                unlink($temppath);
            }
        }
    }

    /**
     * Extract text from a file on disk, given its mimetype.
     *
     * @param string $path Absolute path to the file.
     * @param string $mimetype
     * @return string
     */
    public function extract_from_path(string $path, string $mimetype): string {
        switch ($mimetype) {
            case 'application/pdf':
                return $this->run_pdftotext($path);

            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                return $this->run_pandoc($path);

            case 'text/plain':
                $content = file_get_contents($path);
                return $content === false ? '' : trim($content);

            default:
                // For now a plain exception; convert to moodle_exception + a
                // lang string once we wire this into the UI.
                throw new \RuntimeException("Unsupported file type: {$mimetype}");
        }
    }

    /**
     * Run pdftotext and return its stdout.
     *
     * @param string $path
     * @return string
     */
    protected function run_pdftotext(string $path): string {
        // Options: -q quiet, -nopgbrk removes page-break chars, '-' writes to stdout.
        $cmd = 'pdftotext -q -nopgbrk ' . escapeshellarg($path) . ' -';
        return $this->run_command($cmd);
    }

    /**
     * Run pandoc to convert a DOCX to plain text.
     *
     * @param string $path
     * @return string
     */
    protected function run_pandoc(string $path): string {
        $cmd = 'pandoc -f docx -t plain ' . escapeshellarg($path);
        return $this->run_command($cmd);
    }

    /**
     * Execute a shell command and return trimmed stdout, throwing on failure.
     *
     * @param string $cmd
     * @return string
     */
    protected function run_command(string $cmd): string {
        $output = [];
        $returnvar = 0;
        exec($cmd . ' 2>/dev/null', $output, $returnvar);

        if ($returnvar !== 0) {
            throw new \RuntimeException("Extraction command failed (exit {$returnvar}): {$cmd}");
        }

        return trim(implode("\n", $output));
    }
}
