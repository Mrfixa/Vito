<?php

namespace Modules\AiModule\Service;


use Modules\AiModule\Service\Interfaces\BlogDescriptionPromptServiceInterface;

class BlogDescriptionPromptService implements BlogDescriptionPromptServiceInterface
{
    public function build(?string $context = null, ?string $langCode = null, ?string $description = null): string
    {
        $langCode = strtoupper($langCode);
        $contextSafe = addslashes($context ?? '');
        $businessName = businessConfig(key: 'business_name', settingsType: BUSINESS_INFORMATION)?->value ?? 'Vito';
        return <<<PROMPT
                        You are a professional content writer and SEO strategist specialized in creating high-quality blog content for ride-sharing, mobility, and transportation platforms.

                        Generate a detailed, engaging, and informative **blog description/content** in **HTML format** for the blog topic "{$contextSafe}", written for the **{$businessName}** ride-sharing app.

                        CRITICAL INSTRUCTIONS:
                                                - Output must be 100% in language code "{$langCode}". Translate fully if necessary; do not mix languages.
                                                - Adapt tone and phrasing naturally for {$langCode} readers.
                                                - Begin with a short introductory paragraph explaining the blog topic, its relevance, and why it matters to riders, drivers, or the transportation ecosystem. Do not start with **{$contextSafe}**
                                                - Follow with multiple paragraphs — each starting with a **bolded subheading** (`<h2>Subheading</h2>:`) that clearly introduces the section’s focus (e.g., safety, convenience, driver experience, urban mobility).
                                                  - Do **not** literally write the word “Title.”
                                                  - Subheadings must be relevant, clear, and reader-friendly.
                                                - Include a **"Key Takeaways:"** or **"Highlights:"** section using `<ul>` or `<ol>` tags.
                                                  - Each `<li>` should present one clear insight, benefit, or important point related to the topic.
                                                  - Write points as natural, complete sentences.
                                                - End with a short concluding paragraph summarizing the topic and reinforcing its value for **{$businessName}** users or the ride-sharing industry.
                                                - Keep tone professional, informative, and trustworthy — avoid hype, exaggeration, or promotional language.

                        IMPORTANT:
                                    - Accept only valid blog topics related to ride-sharing, transportation, mobility, drivers, passengers, safety, technology, or urban travel.
                                    - If the input is **not** a valid blog topic (e.g., random text, code, unrelated content), respond with exactly "INVALID_INPUT".
                                    - Based on "{$contextSafe}", generate an **engaging and relevant blog headline** at the top of the HTML.
                                    - Do **not** include prices, offers, guarantees, or promotional slogans.
                                    - Output must be pure HTML suitable for a CMS or rich text editor.
                                    - Do NOT include <html>, <head>, <body>, <title> tags.
                                    - Do NOT include Markdown code fences or any other wrapper.
                                    - The response must **start with `<` and end with `>`** — any other format will be rejected.
                PROMPT;

    }

    public function getType(): string
    {
        return 'BlogDescription';
    }
}
