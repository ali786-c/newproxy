<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Mail\Markdown;

class EmailTemplateService
{
    /**
     * Render a template by its key with provided data.
     *
     * @param string $key
     * @param array $data
     * @return array
     */
    public function render(string $key, array $data = []): array
    {
        $template = EmailTemplate::where('key', $key)->where('is_active', true)->first();

        if (!$template) {
            Log::warning("Email template not found or inactive: {$key}");
            return $this->getFallbackRender($key, $data);
        }

        return $this->renderTemplate($template, $data);
    }

    /**
     * Render a template object with provided data.
     */
    public function renderTemplate(EmailTemplate $template, array $data = []): array
    {
        $subject = $this->replacePlaceholders($template->subject, $data);
        $bodyContent = $this->replacePlaceholders($template->body, $data);

        // Convert Markdown to HTML if format is markdown
        if ($template->format === 'markdown') {
            $htmlBody = Markdown::parse($bodyContent)->toHtml();
        } else {
            // Check if it's already a full HTML or just a fragment
            // If it's HTML format, we assume the user provides what they want.
            $htmlBody = $bodyContent;
        }

        return [
            'subject' => $subject,
            'html'    => $htmlBody,
            'body'    => $bodyContent,
        ];
    }

    /**
     * Securely replace {{dot.notation}} placeholders in a string.
     */
    protected function replacePlaceholders(string $content, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([\w\.]+)\s*\}\}/', function ($matches) use ($data) {
            $key = $matches[1];
            return $this->getNestedValue($data, $key) ?? $matches[0];
        }, $content);
    }

    /**
     * Resolve dot notation values from an array (e.g., 'user.name').
     */
    protected function getNestedValue(array $data, string $key)
    {
        foreach (explode('.', $key) as $segment) {
            if (is_array($data) && array_key_exists($segment, $data)) {
                $data = $data[$segment];
            } else {
                return null;
            }
        }

        return $data;
    }

    /**
     * Basic fallback if template is missing.
     */
    protected function getFallbackRender(string $key, array $data): array
    {
        return [
            'subject' => "System Alert: {$key}",
            'html'    => "<p>Standard system notification for {$key}. Please configure this template in Admin Panel.</p>",
            'body'    => "Standard system notification for {$key}.",
        ];
    }
}
