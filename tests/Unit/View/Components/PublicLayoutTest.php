<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\PublicLayout;
use Tests\TestCase;

class PublicLayoutTest extends TestCase
{
    public function test_page_title_suffixes_the_app_name_and_the_lists_label(): void
    {
        $component = new PublicLayout(title: 'My Tech Creators');

        $this->assertSame(
            'My Tech Creators - '.config('app.name').' '.__('messages.filter_list.public.lists'),
            $component->pageTitle()
        );
    }

    public function test_page_title_falls_back_to_the_app_name_when_no_title_is_given(): void
    {
        $expected = config('app.name').' - '.__('messages.filter_list.public.lists');

        $this->assertSame($expected, (new PublicLayout)->pageTitle());
        $this->assertSame($expected, new PublicLayout(title: '')->pageTitle());
    }

    public function test_meta_description_falls_back_to_the_default_and_is_truncated(): void
    {
        $default = __('messages.filter_list.public.default_description');

        $this->assertSame($default, (new PublicLayout)->metaDescription());
        $this->assertSame($default, new PublicLayout(description: '')->metaDescription());
        $this->assertSame('Short one.', new PublicLayout(description: 'Short one.')->metaDescription());

        $long = new PublicLayout(description: str_repeat('a', 300))->metaDescription();

        $this->assertSame(str_repeat('a', 160).'...', $long);
    }

    public function test_canonical_url_defaults_to_the_current_url(): void
    {
        $this->assertSame(
            'https://example.test/list/Hash12345678',
            new PublicLayout(canonical: 'https://example.test/list/Hash12345678')->canonicalUrl()
        );
        $this->assertSame(url()->current(), (new PublicLayout)->canonicalUrl());
    }

    public function test_render_returns_the_public_layout_view(): void
    {
        $this->assertSame('layouts.public', (new PublicLayout)->render()->name());
    }
}
