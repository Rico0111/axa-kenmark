<?php

declare(strict_types=1);

namespace AxaKenneth\Controllers\Admin;

use AxaKenneth\Repositories\AdminRepository;
use AxaKenneth\Support\AuditLogger;
use AxaKenneth\Support\Auth;
use AxaKenneth\Support\Database;
use AxaKenneth\Support\Flash;
use AxaKenneth\Support\Http;
use AxaKenneth\Support\HttpException;
use AxaKenneth\Support\Validator;
use PDOException;
use Throwable;

final class AdminAccountController extends BaseAdminController
{
    private const ROLE_LABELS = [
        'super_admin' => 'Super administrator',
        'admin' => 'Administrator',
    ];

    public function index(): void
    {
        Auth::requireRole(['super_admin']);

        $this->render('admins/index', 'admins', [
            'pageTitle' => 'Administrators',
            'admins' => (new AdminRepository())->all(),
            'roleLabels' => self::ROLE_LABELS,
        ]);
    }

    public function create(): void
    {
        Auth::requireRole(['super_admin']);

        $this->renderForm(null);
    }

    public function store(): void
    {
        Auth::requireRole(['super_admin']);

        $data = [
            'name' => $this->posted('name'),
            'email' => strtolower($this->posted('email')),
            'role' => $this->posted('role'),
            'is_active' => $this->checkbox('is_active') ? '1' : '0',
            'password' => $this->posted('password'),
            'password_confirm' => $this->posted('password_confirm'),
        ];
        $errors = $this->validateAccount($data, null, true);

        if ($errors !== []) {
            $this->validationFailed();
            $this->renderForm(null, $this->withoutPasswords($data), $errors);
            return;
        }

        $repository = new AdminRepository();
        try {
            $id = $repository->create(
                $data['name'],
                $data['email'],
                Auth::hashPassword($data['password']),
                $data['role'],
                $data['is_active'] === '1'
            );
        } catch (PDOException $exception) {
            if ($this->isDuplicateEmail($exception)) {
                $errors['email'] = 'Another administrator already uses that email address.';
                $this->validationFailed();
                $this->renderForm(null, $this->withoutPasswords($data), $errors);
                return;
            }
            throw $exception;
        }
        $admin = Auth::admin();
        if ($admin !== null) {
            AuditLogger::record((int) $admin['id'], 'admin.created', 'admin', $id, [
                'role' => $data['role'],
                'active' => $data['is_active'] === '1',
            ]);
        }

        Flash::success('Administrator account created.');
        $this->redirect('admins');
    }

    public function edit(): void
    {
        Auth::requireRole(['super_admin']);

        $target = (new AdminRepository())->findById(Http::queryInt('id', 0, 1));
        if ($target === null) {
            throw new HttpException(404, 'Administrator not found.');
        }

        $this->renderForm($target);
    }

    public function update(): void
    {
        Auth::requireRole(['super_admin']);

        $repository = new AdminRepository();
        $id = Http::queryInt('id', 0, 1);
        $target = $repository->findById($id);
        if ($target === null) {
            throw new HttpException(404, 'Administrator not found.');
        }

        $data = [
            'name' => $this->posted('name'),
            'email' => strtolower($this->posted('email')),
            'role' => $this->posted('role'),
            'is_active' => $this->checkbox('is_active') ? '1' : '0',
        ];
        $errors = $this->validateAccount($data, $target, false);
        $currentAdmin = Auth::admin();
        if ($currentAdmin === null) {
            throw new HttpException(403, 'Your session is no longer valid.');
        }

        if ((int) $target['id'] === (int) $currentAdmin['id']) {
            if ($data['role'] !== 'super_admin') {
                $errors['role'] = 'You cannot remove your own super-administrator role. Ask another super administrator to make that change.';
            }
            if ($data['is_active'] !== '1') {
                $errors['is_active'] = 'You cannot deactivate your own account.';
            }
        }

        if ($errors !== []) {
            $this->validationFailed();
            $this->renderForm($target, $data, $errors);
            return;
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $lockedTarget = $repository->findByIdForUpdate($id);
            if ($lockedTarget === null) {
                $pdo->rollBack();
                throw new HttpException(404, 'Administrator not found.');
            }

            $removingActiveSuper = (string) $lockedTarget['role'] === 'super_admin'
                && (int) $lockedTarget['is_active'] === 1
                && ($data['role'] !== 'super_admin' || $data['is_active'] !== '1');

            if ($removingActiveSuper && $repository->lockActiveSuperAdministratorCount() <= 1) {
                $pdo->rollBack();
                $errors['role'] = 'At least one active super administrator must remain.';
                $this->validationFailed();
                $this->renderForm($target, $data, $errors);
                return;
            }

            try {
                $repository->updateAccount(
                    $id,
                    $data['name'],
                    $data['email'],
                    $data['role'],
                    $data['is_active'] === '1'
                );
                $pdo->commit();
            } catch (PDOException $exception) {
                if ($this->isDuplicateEmail($exception)) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors['email'] = 'Another administrator already uses that email address.';
                    $this->validationFailed();
                    $this->renderForm($target, $data, $errors);
                    return;
                }
                throw $exception;
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        AuditLogger::record((int) $currentAdmin['id'], 'admin.updated', 'admin', $id, [
            'role' => $data['role'],
            'active' => $data['is_active'] === '1',
        ]);

        Flash::success('Administrator account updated.');
        $this->redirect('admins');
    }

    public function password(): void
    {
        Auth::requireRole(['super_admin']);

        $target = (new AdminRepository())->findById(Http::queryInt('id', 0, 1));
        if ($target === null) {
            throw new HttpException(404, 'Administrator not found.');
        }

        $this->renderPasswordForm($target);
    }

    public function updatePassword(): void
    {
        Auth::requireRole(['super_admin']);

        $repository = new AdminRepository();
        $id = Http::queryInt('id', 0, 1);
        $target = $repository->findById($id);
        if ($target === null) {
            throw new HttpException(404, 'Administrator not found.');
        }

        $data = [
            'password' => $this->posted('password'),
            'password_confirm' => $this->posted('password_confirm'),
        ];
        $errors = Validator::validate($data, [
            'password' => 'required|password',
            'password_confirm' => 'required|same:password',
        ], [
            'password' => 'New password',
            'password_confirm' => 'Password confirmation',
        ]);

        if ($errors !== []) {
            $this->validationFailed();
            $this->renderPasswordForm($target, $errors);
            return;
        }

        // Capture the current account before the password change increments the
        // target's session generation. Re-checking afterward would observe the
        // new version while this session still holds the old one.
        $currentAdmin = Auth::admin();
        if ($currentAdmin === null) {
            throw new HttpException(403, 'Your session is no longer valid.');
        }

        $version = $repository->resetPassword($id, Auth::hashPassword($data['password']));
        AuditLogger::record((int) $currentAdmin['id'], 'admin.password_reset', 'admin', $id);

        if ((int) $currentAdmin['id'] === $id) {
            Auth::syncSessionVersion($version);
            Flash::success('Your password was changed. Other sessions for this account were signed out.');
        } else {
            Flash::success('The password was reset. Existing sessions for that account were signed out.');
        }
        $this->redirect('admins');
    }

    /**
     * @param array<string, string> $data
     * @param array<string, mixed>|null $existing
     * @return array<string, string>
     */
    private function validateAccount(array $data, ?array $existing, bool $passwordRequired): array
    {
        $rules = [
            'name' => 'required|max:120',
            'email' => 'required|email|max:190',
            'role' => 'required|in:super_admin,admin',
        ];
        $labels = [
            'name' => 'Name',
            'email' => 'Email',
            'role' => 'Role',
            'is_active' => 'Account status',
            'password' => 'Password',
            'password_confirm' => 'Password confirmation',
        ];
        if ($passwordRequired) {
            $rules['password'] = 'required|password';
            $rules['password_confirm'] = 'required|same:password';
        }

        $errors = Validator::validate($data, $rules, $labels);
        if (!array_key_exists('email', $errors)
            && (new AdminRepository())->emailExists($data['email'], $existing !== null ? (int) $existing['id'] : null)
        ) {
            $errors['email'] = 'Another administrator already uses that email address.';
        }

        return $errors;
    }

    /** @param array<string, string> $data */
    private function withoutPasswords(array $data): array
    {
        unset($data['password'], $data['password_confirm']);
        return $data;
    }

    private function isDuplicateEmail(PDOException $exception): bool
    {
        return (string) $exception->getCode() === '23000';
    }

    /**
     * @param array<string, mixed>|null $account
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function renderForm(?array $account, array $old = [], array $errors = []): void
    {
        $current = Auth::admin();
        $this->render('admins/form', 'admins', [
            'pageTitle' => $account === null ? 'New administrator' : 'Edit administrator',
            'account' => $account,
            'old' => $old,
            'errors' => $errors,
            'roleLabels' => self::ROLE_LABELS,
            'isSelf' => $account !== null && $current !== null && (int) $account['id'] === (int) $current['id'],
            'submitted' => $old !== [],
        ]);
    }

    /**
     * @param array<string, mixed> $account
     * @param array<string, string> $errors
     */
    private function renderPasswordForm(array $account, array $errors = []): void
    {
        $current = Auth::admin();
        $this->render('admins/password', 'admins', [
            'pageTitle' => 'Reset administrator password',
            'account' => $account,
            'errors' => $errors,
            'isSelf' => $current !== null && (int) $account['id'] === (int) $current['id'],
        ]);
    }
}
