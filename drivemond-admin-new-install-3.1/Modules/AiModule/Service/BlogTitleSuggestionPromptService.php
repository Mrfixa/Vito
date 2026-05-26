<?php

namespace Modules\AiModule\Service;

use Modules\AiModule\Service\Interfaces\BlogTitleSuggestionPromptServiceInterface;

class BlogTitleSuggestionPromptService implements BlogTitleSuggestionPromptServiceInterface
{

    public function build(?string $context = null, ?string $langCode = null, ?string $description = null): string
    {
        $langCode = strtoupper($langCode);
        $keywordsText = $context;
        if (is_array($context)) {
            $keywordsText = implode(' ', $context);
        }
        $businessName = businessConfig(key: 'business_name', settingsType: BUSINESS_INFORMATION)?->value ?? 'Vito';

        return <<<PROMPT
            You are a professional SEO content strategist specialized in generating high-quality blog title suggestions for ride-sharing and mobility platforms.

            Generate multiple engaging **blog title suggestions** for the **{$businessName}** ride-sharing app using the following keywords or topic:

            Keywords / Topic: "{$keywordsText}"

            CRITICAL INSTRUCTIONS:
            - Output must be 100% in language code "{$langCode}". Translate fully if necessary; do not mix languages.
            - Generate exactly **4 unique blog titles**.
            - Each title must be clear, natural, professional, and SEO-friendly.
            - Optimize titles for ride-sharing, transportation, mobility, drivers, passengers, safety, or urban travel topics.
            - Keep each title concise (45–90 characters).
            - Avoid clickbait, exaggeration, or promotional terms (e.g., Best, Ultimate, Top, #1).
            - Use only meaningful, search-friendly words.
            - Do NOT use emojis, hashtags, quotes, or unnecessary punctuation.

            IMPORTANT:
            - Accept only valid and meaningful keywords related to ride-sharing, transportation, mobility, or app-based travel.
            - If the keywords are irrelevant, meaningless, or not suitable for blog content, respond with exactly "INVALID_INPUT".
            - Output must be a **single, clean JSON object** in the following format:
              {
                "titles": [
                  "Title 1",
                  "Title 2",
                  "Title 3",
                  "Title 4"
                ]
              }
            - Do NOT include explanations, comments, markdown, or extra characters.
            PROMPT;
    }

    public function getType(): string
    {
        return "BlogTitleSuggestion";
    }
}
