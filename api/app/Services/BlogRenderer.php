<?php

namespace App\Services;

class BlogRenderer
{
    /**
     * Render the entire blog post as high-quality Tailwind HTML.
     */
    public function render(array $data, ?string $imageUrl = null): string
    {
        $html = '<div class="blog-content space-y-8 text-neutral-800 leading-relaxed">';

        // 0. Featured Image
        if ($imageUrl) {
            $html .= $this->renderFeaturedImage($imageUrl, $data['title'] ?? 'Blog Image');
        }

        // 1. Hero Section (Hook + Intro)
        $html .= $this->renderHero($data['hook'] ?? '', $data['intro'] ?? '');

        // 2. Key Takeaways Card
        if (!empty($data['takeaways'])) {
            $html .= $this->renderKeyTakeaways($data['takeaways']);
        }

        // 3. Body Sections
        if (!empty($data['sections'])) {
            foreach ($data['sections'] as $section) {
                $html .= $this->renderSection($section['heading'], $section['content']);
            }
        }

        // 4. FAQ Section
        if (!empty($data['faqs'])) {
            $html .= $this->renderFaq($data['faqs']);
        }

        // 5. CTA Block
        if (!empty($data['cta'])) {
            $html .= $this->renderCTA($data['cta']);
        }

        $html .= '</div>';

        return $html;
    }

    protected function renderFeaturedImage(string $url, string $alt): string
    {
        return <<<HTML
<div className="blog-featured-image mb-8 overflow-hidden rounded-2xl shadow-lg border border-neutral-100">
    <img src="{$url}" alt="{$alt}" class="w-full h-auto object-cover max-h-[500px]" />
</div>
HTML;
    }

    protected function renderHero(string $hook, string $intro): string
    {
        return <<<HTML
<div class="blog-hero mb-12">
    <p class="text-xl font-medium text-blue-600 mb-4 italic leading-snug">"{$hook}"</p>
    <div class="text-lg text-neutral-600 first-letter:text-5xl first-letter:font-bold first-letter:mr-3 first-letter:float-left">
        {$intro}
    </div>
</div>
HTML;
    }

    protected function renderKeyTakeaways(array $takeaways): string
    {
        $items = '';
        foreach ($takeaways as $item) {
            $items .= "<li class='flex items-start mb-2'><span class='text-blue-500 mr-2'>✔</span><span>{$item}</span></li>";
        }

        return <<<HTML
<div class="bg-slate-50 border-l-4 border-blue-500 rounded-xl p-6 my-8 shadow-sm">
    <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        Key Takeaways
    </h3>
    <ul class="text-slate-700 list-none p-0">
        {$items}
    </ul>
</div>
HTML;
    }

    protected function renderSection(string $heading, string $content): string
    {
        return <<<HTML
<section class="blog-section py-4">
    <h2 class="text-2xl font-bold text-neutral-900 mb-4 tracking-tight border-b border-neutral-100 pb-2">{$heading}</h2>
    <div class="prose prose-neutral max-w-none text-neutral-600">
        <p>{$content}</p>
    </div>
</section>
HTML;
    }

    protected function renderFaq(array $faqs): string
    {
        $items = '';
        foreach ($faqs as $faq) {
            $items .= <<<HTML
<div class="mb-6 group">
    <h4 class="text-md font-bold text-neutral-800 mb-2 flex items-center group-hover:text-blue-600 transition-colors">
        <span class="bg-blue-100 text-blue-700 w-6 h-6 rounded-full flex items-center justify-center text-xs mr-2">Q</span>
        {$faq['q']}
    </h4>
    <p class="text-neutral-600 pl-8 border-l-2 border-neutral-50 ml-3">{$faq['a']}</p>
</div>
HTML;
        }

        return <<<HTML
<div class="bg-white border border-neutral-200 rounded-2xl p-8 my-12">
    <h3 class="text-xl font-bold text-neutral-900 mb-8">Frequently Asked Questions</h3>
    <div class="faq-list">
        {$items}
    </div>
</div>
HTML;
    }

    protected function renderCTA(string $cta): string
    {
        return <<<HTML
<div class="bg-gradient-to-r from-blue-600 to-indigo-800 rounded-2xl p-8 text-white text-center my-12 shadow-xl shadow-blue-900/10">
    <h3 class="text-2xl font-bold mb-4">Ready to level up your proxy game?</h3>
    <p class="text-blue-100 mb-6 max-w-xl mx-auto">{$cta}</p>
    <a href="/dashboard" class="inline-block bg-white text-blue-700 font-bold px-8 py-3 rounded-full hover:bg-blue-50 transition-all transform hover:scale-105 active:scale-95">
        Get Started Now
    </a>
</div>
HTML;
    }
}
