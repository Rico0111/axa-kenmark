<?php

declare(strict_types=1);

namespace AxaKenneth\Controllers\Admin;

use AxaKenneth\Support\Auth;
use AxaKenneth\Support\Flash;
use AxaKenneth\Support\View;

final class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            header('Location: ' . admin_url('dashboard'), true, 303);
            exit;
        }

        View::render('admin/login', [
            'admin' => null,
            'activeNav' => 'login',
            'pageTitle' => 'Administrator sign-in',
        ], 'layouts/admin');
    }

    public function login(): void
    {
        if (Auth::check()) {
            $this->redirectDashboard();
        }

        $result = Auth::attempt($this->posted('email'), $this->posted('password'));
        if (!$result['ok']) {
            Flash::error($result['message']);
            header('Location: ' . admin_url('login'), true, 303);
            exit;
        }

        Flash::success('Signed in successfully.');
        $this->redirectDashboard();
    }

    public function logout(): void
    {
        Auth::logout();
        Flash::info('You have been signed out.');
        header('Location: ' . admin_url('login'), true, 303);
        exit;
    }

    private function posted(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    private function redirectDashboard(): never
    {
        header('Location: ' . admin_url('dashboard'), true, 303);
        exit;
    }
}
