<?php

namespace Modules\AiModule\Service;


use Modules\AiModule\Service\Interfaces\BlogTitlePromptServiceInterface;

class BlogSeoPromptService implements BlogTitlePromptServiceInterface
{
    public function build(?string $context = null, ?string $langCode = null, ?string $description = null): string
    {
        $langCode = strtoupper($langCode);
        $contextSafe = addslashes($context ?? '');
        $descriptionSafe = addslashes($description ?? '');
        $businessName = businessConfig(key: 'business_name', settingsType: BUSINESS_INFORMATION)?->value ?? 'Vito';
        return <<<PROMPT
            You are a professional SEO copywriter specialized in writing high-converting metadata for blogs in the ride-sharing and mobility industry.

            Generate SEO-optimized **meta_title** and **meta_description** for a blog related to the **{$businessName}** ride-sharing app, based on the following input:

            Blog Topic: "{$contextSafe}"
            Blog Description (optional context): "{$descriptionSafe}"

            CRITICAL INSTRUCTIONS:
            - Output must be 100% in language code "{$langCode}". Translate fully if necessary; do not mix languages.
            - Generate both fields with strong focus on search intent, clarity, and click-through rate.
            - meta_title:
              - Length: 50–60 characters.
              - Must be clear, natural, and relevant to the blog topic.
              - May include the brand name "**{$businessName}**" only if it fits naturally.
              - Do NOT use promotional or exaggerated terms (e.g., Best, Ultimate, #1).
            - meta_description:
              - Length: 140–160 characters.
              - Must accurately summarize the blog topic and encourage clicks without clickbait.
              - Write as a complete, natural sentence.
            - Use only meaningful, search-friendly words.
            - Do NOT include emojis, quotes, hashtags, or unnecessary punctuation.

            IMPORTANT:
            - Accept only valid blog topics related to ride-sharing, transportation, mobility, drivers, passengers, safety, or urban travel.
            - If the input is irrelevant, meaningless, or not suitable for a blog, respond with exactly "INVALID_INPUT".
            - Output must be a single, clean JSON object with exactly these two keys:
              {
                "meta_title": "",
                "meta_description": ""
              }
            - Do NOT include explanations, comments, markdown, or extra characters.
            PROMPT;

    }

    public function getType(): string
    {
        return 'BlogSeo';
    }
}
