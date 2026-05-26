<?php

namespace Modules\AiModule\Service;


use Modules\AiModule\Service\Interfaces\BlogTitlePromptServiceInterface;

class BlogTitleFromContentsPromptService implements BlogTitlePromptServiceInterface
{
    public function build(?string $context = null, ?string $langCode = null, ?string $description = null): string
    {
        $langCode = strtoupper($langCode);
        $descriptionSafe = addslashes($description ?? '');
        $businessName = businessConfig(key: 'business_name', settingsType: BUSINESS_INFORMATION)?->value ?? 'Vito';
        return <<<PROMPT
                You are a professional SEO content writer specialized in generating clear and high-performing blog titles from visual context for ride-sharing and mobility platforms.

                Analyze the provided **image** as the primary source of context.
                The image may represent a promotion, discount, campaign, service feature, or user benefit related to ride-sharing or delivery services.

                Generate a concise, natural, and SEO-friendly **blog title** suitable for the **{$businessName}** ride-sharing app.

                Optional Textual Description (may be empty or incomplete):
                "{$descriptionSafe}"

                CRITICAL INSTRUCTIONS:
                - The IMAGE is the primary signal.
                - Promotional, discount, or campaign-related images ARE valid blog sources.
                - You may infer a blog topic about offers, savings, campaigns, usage tips, or service benefits.
                - Output must be 100% in language code "{$langCode}". Translate fully if necessary; do not mix languages.
                - Generate exactly **one** blog title only.
                - Title length must be between **45–90 characters**.
                - Title must be professional, clear, and relevant to ride-sharing, transportation, mobility, delivery, drivers, or passengers.
                - Avoid clickbait, hype, or exaggerated promotional terms (e.g., Best, Ultimate, Top, #1).
                - Use only meaningful, search-friendly words.
                - Do NOT include emojis, quotes, hashtags, or unnecessary punctuation.

                IMPORTANT:
                - Respond with exactly "INVALID_INPUT" ONLY if the image is completely unrelated to ride-sharing, delivery, transportation, or mobility services.
                - Return ONLY the final blog title as plain text — no JSON, no explanations, no extra characters.
                PROMPT;
    }

    public function getType(): string
    {
        return 'BlogTitleFromContents';
    }
}
