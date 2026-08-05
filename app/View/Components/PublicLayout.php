<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

class PublicLayout extends Component
{
    /** Search engines truncate meta descriptions past roughly this length. */
    private const int DESCRIPTION_LIMIT = 160;

    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $canonical = null,
        public bool $noindex = false
    ) {
        //
    }

    public function render(): View
    {
        return view('layouts.public');
    }

    /**
     * Page title with the app name as a suffix, so every public
     * page has a distinct, self-describing title in search results.
     */
    public function pageTitle(): string
    {
        $appName = (string) config('app.name', 'Starboard');

        if ($this->title === null || $this->title === '') {
            return $appName.' - '.__('messages.filter_list.public.lists');
        }

        return $this->title.' - '.$appName.' '.__('messages.filter_list.public.lists');
    }

    public function metaDescription(): string
    {
        $description = $this->description !== null && $this->description !== ''
            ? $this->description
            : __('messages.filter_list.public.default_description');

        return Str::limit($description, self::DESCRIPTION_LIMIT);
    }

    public function canonicalUrl(): string
    {
        return $this->canonical ?? url()->current();
    }
}
