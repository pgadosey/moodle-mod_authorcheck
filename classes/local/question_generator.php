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
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU GPL v3 or later
 */
namespace mod_authorcheck\local;

/**
 * Generates comprehension questions from submission text via Moodle's AI subsystem.
 */
class question_generator {

    /** @var int Hard cap on how much source text we send to the model. */
    const MAX_SOURCE_CHARS = 8000;

    /**
     * Generate questions from source text.
     *
     * @param string $sourcetext The extracted submission text.
     * @param int $numquestions Roughly how many questions to request.
     * @param int|null $contextid Context to run the AI action in (defaults to system).
     * @param int|null $userid User the action runs as (defaults to admin).
     * @return array List of question arrays, each with: type, question, options, answer.
     */
    public function generate(string $sourcetext, int $numquestions = 6,
            array $allowedtypes = ['mcq', 'truefalse', 'fillin', 'open'],
            ?int $contextid = null, ?int $userid = null): array {

        if (empty($allowedtypes)) {
            $allowedtypes = ['mcq', 'truefalse', 'fillin', 'open'];
        }

        $sourcetext = trim($sourcetext);
        if ($sourcetext === '') {
            throw new \RuntimeException('Source text is empty; nothing to generate from.');
        }

        // Keep the prompt within a comfortable size for a local 7B model.
        if (\core_text::strlen($sourcetext) > self::MAX_SOURCE_CHARS) {
            $sourcetext = \core_text::substr($sourcetext, 0, self::MAX_SOURCE_CHARS);
        }

        $contextid = $contextid ?? \context_system::instance()->id;
        $userid = $userid ?? get_admin()->id;

        $prompt = $this->build_prompt($sourcetext, $numquestions, $allowedtypes);

        // Build and dispatch the AI action (Moodle 5.0 subsystem API).
        $action = new \core_ai\aiactions\generate_text(
            contextid: $contextid,
            userid: $userid,
            prompttext: $prompt,
        );

        $manager = \core\di::get(\core_ai\manager::class);
        $response = $manager->process_action($action);

        if (!$response->get_success()) {
            $err = method_exists($response, 'get_errormessage') ? $response->get_errormessage() : 'unknown error';
            throw new \RuntimeException('AI action failed: ' . $err);
        }

        $data = $response->get_response_data();
        $raw = $data['generatedcontent'] ?? $data['content'] ?? $data['text'] ?? null;

        if ($raw === null) {
            // Surface the actual structure so we can adjust the key if needed.
            throw new \RuntimeException(
                "Could not find generated text in response. Response data was:\n"
                . var_export($data, true)
            );
        }

        $questions = $this->parse_questions($raw);

        // Drop any type the model returned that wasn't requested.
        $questions = array_values(array_filter($questions,
            function($q) use ($allowedtypes) {
                return in_array($q['type'], $allowedtypes, true);
            }));

        return $questions;
    }

    /**
     * Build a strict prompt that asks for a mixed question set as JSON only.
     *
     * @param string $sourcetext
     * @param int $numquestions
     * @return string
     */
    protected function build_prompt(string $sourcetext, int $numquestions,
            array $allowedtypes): string {

        $typedesc = [
            'mcq'       => '- "mcq": multiple choice, with exactly 4 options and one correct answer',
            'truefalse' => '- "truefalse": a statement that is either true or false',
            'fillin'    => '- "fillin": a short factual gap to complete',
            'open'      => '- "open": an open-ended question about the author\'s reasoning or choices',
        ];
        $lines = [];
        foreach ($allowedtypes as $t) {
            if (isset($typedesc[$t])) {
                $lines[] = $typedesc[$t];
            }
        }
        $typesblock = implode("\n", $lines);
        $typelist = '"' . implode('", "', $allowedtypes) . '"';

        $openrule = in_array('open', $allowedtypes, true)
            ? '- Include at least one "open" question.' . "\n" : '';

        return <<<PROMPT
You are helping verify that a student genuinely understands a document they submitted.

Read the SOURCE TEXT below and write {$numquestions} comprehension questions about its
specific content. Use ONLY these question types:
{$typesblock}

Rules:
- Base every question on specifics in the source text, not general knowledge.
- Use only the question types listed above. Do not use any other type.
{$openrule}- Respond with ONLY a valid JSON array. No markdown, no code fences, no commentary.
- Each element must be an object with these keys:
  - "type": one of {$typelist}
  - "question": the question text (string)
  - "options": array of 4 strings for "mcq", otherwise an empty array []
  - "answer": the correct answer as a string for mcq/fillin, "true" or "false" for
    truefalse, or null for open

SOURCE TEXT:
{$sourcetext}
PROMPT;
    }

    /**
     * Parse the model's raw response into an array of question structures.
     *
     * Defensive against common local-model quirks: markdown fences, a leading
     * sentence before the JSON, trailing prose after it.
     *
     * @param string $raw
     * @return array
     */
    protected function parse_questions(string $raw): array {
        $text = trim($raw);

        // Strip ```json ... ``` or ``` ... ``` fences if present.
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        // Isolate the JSON array: from the first '[' to the last ']'.
        $start = strpos($text, '[');
        $end = strrpos($text, ']');
        if ($start === false || $end === false || $end < $start) {
            throw new \RuntimeException("No JSON array found in AI response:\n" . $raw);
        }
        $json = substr($text, $start, $end - $start + 1);

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException(
                "Failed to parse JSON from AI response (json_error: "
                . json_last_error_msg() . "):\n" . $json
            );
        }

        // Light validation / normalisation so downstream code can trust the shape.
        $questions = [];
        foreach ($decoded as $q) {
            if (!is_array($q) || empty($q['type']) || empty($q['question'])) {
                continue; // Skip malformed entries rather than failing the whole set.
            }
            $questions[] = [
                'type'     => (string) $q['type'],
                'question' => (string) $q['question'],
                'options'  => isset($q['options']) && is_array($q['options']) ? $q['options'] : [],
                'answer'   => array_key_exists('answer', $q) ? $q['answer'] : null,
            ];
        }

        if (empty($questions)) {
            throw new \RuntimeException("Parsed JSON but found no valid questions:\n" . $json);
        }

        return $questions;
    }
}
