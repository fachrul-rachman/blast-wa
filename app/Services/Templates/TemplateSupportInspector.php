<?php

namespace App\Services\Templates;

class TemplateSupportInspector
{
    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array{supported: bool, body_text: string|null, body_variables: array<int, string>}
     */
    public function inspect(array $components): array
    {
        $bodyText = null;
        $supported = true;

        foreach ($components as $component) {
            $type = strtoupper((string) ($component['type'] ?? ''));

            if ($type === 'BODY') {
                $bodyText = is_string($component['text'] ?? null) ? $component['text'] : null;
            }

            if ($type === 'HEADER' && $this->hasUnsupportedHeader($component)) {
                $supported = false;
            }

            if ($type === 'BUTTONS') {
                $supported = false;
            }

            if (! in_array($type, ['BODY', 'FOOTER', 'HEADER', 'BUTTONS'], true)) {
                $supported = false;
            }
        }

        return [
            'supported' => $supported && filled($bodyText),
            'body_text' => $bodyText,
            'body_variables' => $bodyText === null ? [] : $this->bodyVariables($bodyText, $components),
        ];
    }

    /**
     * @param  array<string, mixed>  $component
     */
    private function hasUnsupportedHeader(array $component): bool
    {
        $format = strtoupper((string) ($component['format'] ?? 'TEXT'));
        $text = (string) ($component['text'] ?? '');

        return $format !== 'TEXT' || preg_match($this->variablePattern(), $text) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function extractBodyVariables(string $bodyText): array
    {
        preg_match_all($this->variablePattern(), $bodyText, $matches);

        $variables = $matches[1];
        $variables = array_values(array_unique($variables));

        return $variables;
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array<int, string>
     */
    private function bodyVariables(string $bodyText, array $components): array
    {
        foreach ($components as $component) {
            if (strtoupper((string) ($component['type'] ?? '')) !== 'BODY') {
                continue;
            }

            $namedParameters = data_get($component, 'example.body_text_named_params');

            if (! is_array($namedParameters)) {
                break;
            }

            $variables = array_values(array_filter(array_map(
                fn (mixed $parameter): ?string => is_array($parameter) && is_string($parameter['param_name'] ?? null)
                    ? $parameter['param_name']
                    : null,
                $namedParameters,
            )));

            if ($variables !== []) {
                return $variables;
            }
        }

        return $this->extractBodyVariables($bodyText);
    }

    private function variablePattern(): string
    {
        return '/{{\s*([A-Za-z0-9_]+)\s*}}/';
    }
}
