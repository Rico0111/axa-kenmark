<?php

declare(strict_types=1);

namespace AxaKenneth\Support;

use RuntimeException;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], string $layout = 'layouts/public'): void
    {
        $viewFile = Config::get('paths.views') . '/' . $view . '.php';
        $layoutFile = Config::get('paths.views') . '/' . $layout . '.php';

        if (!is_file($viewFile)) {
            throw new RuntimeException('View not found: ' . $view);
        }
        if (!is_file($layoutFile)) {
            throw new RuntimeException('Layout not found: ' . $layout);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        require $layoutFile;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function partial(string $view, array $data = []): void
    {
        $viewFile = Config::get('paths.views') . '/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new RuntimeException('View not found: ' . $view);
        }

        extract($data, EXTR_SKIP);
        require $viewFile;
    }
}
