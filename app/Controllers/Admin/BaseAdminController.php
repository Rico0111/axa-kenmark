<?php

declare(strict_types=1);

namespace AxaKenneth\Controllers\Admin;

use AxaKenneth\Repositories\InquiryRepository;
use AxaKenneth\Support\Auth;
use AxaKenneth\Support\View;

abstract class BaseAdminController
{
    /** @param array<string, mixed> $data */
    protected function render(string $view, string $activeNav, array $data = []): void
    {
        View::render(
            'admin/' . $view,
            array_merge([
                'admin' => Auth::admin(),
                'activeNav' => $activeNav,
                'newInquiryCount' => (new InquiryRepository())->newCount(),
            ], $data),
            'layouts/admin'
        );
    }

    protected function posted(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    protected function checkbox(string $key): bool
    {
        $value = $_POST[$key] ?? '0';
        return is_string($value) && in_array($value, ['1', 'on', 'true'], true);
    }

    /** @param array<string, scalar> $params */
    protected function redirect(string $route, array $params = []): never
    {
        header('Location: ' . admin_url($route, $params), true, 303);
        exit;
    }

    protected function validationFailed(): void
    {
        http_response_code(422);
    }
}
