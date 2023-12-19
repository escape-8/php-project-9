<?php

namespace Validator;

class UrlValidator
{
    public function validate(string $url): array
    {
        $errors = [];

        if (empty($url)) {
            $errors['url'] = 'URL не должен быть пустым';
            return $errors;
        }

        $parsedUrl = parse_url($url);

        if ((!isset($parsedUrl['scheme']))) {
            $errors['url'] = 'Некорректный URL';
        }

        if (($parsedUrl['scheme'] !== 'https') && ($parsedUrl['scheme'] !== 'http')) {
            $errors['url'] = 'Некорректный URL';
        }

        if (!isset($parsedUrl['host'])) {
            $errors['url'] = 'Некорректный URL';
        }

        return $errors;
    }
}
