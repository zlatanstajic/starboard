<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

abstract class Controller
{
    /**
     * Page name to be redirected to by default.
     * If not set, 'dashboard' is used.
     */
    protected string $pageName = 'dashboard';

    /**
     * Handles view rendering.
     */
    public function handleView(
        array $data,
        string $pageName = ''
    ): View {
        return view($this->pageName($pageName, true), $data);
    }

    /**
     * Handles redirect.
     */
    protected function handleRedirect(
        ?Request $request = null,
        string $pageName = ''
    ): RedirectResponse {
        return to_route($this->pageName($pageName), $request?->query() ?? '');
    }

    /**
     * Handles exception.
     */
    protected function handleException(
        Exception $e,
        string $title = ''
    ): void {
        if (empty($title)) {
            $title = __('messages.default.failed');
        }

        Alert::error($title, $e->getMessage());
    }

    /**
     * Returns page name.
     */
    private function pageName(
        string $pageName = '',
        bool $isView = false
    ): string {
        $currentPageName = $pageName !== '' ? $pageName : $this->pageName;

        if ($isView) {
            return explode('.', $currentPageName)[0];
        }

        return $currentPageName;
    }
}
