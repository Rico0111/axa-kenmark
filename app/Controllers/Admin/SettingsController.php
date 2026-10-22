<?php

declare(strict_types=1);

namespace AxaKenneth\Controllers\Admin;

use AxaKenneth\Repositories\SettingRepository;
use AxaKenneth\Support\AuditLogger;
use AxaKenneth\Support\Auth;
use AxaKenneth\Support\Flash;
use AxaKenneth\Support\HttpException;
use AxaKenneth\Support\Validator;

final class SettingsController extends BaseAdminController
{
    public function edit(): void
    {
        Auth::requireRole(['super_admin']);

        $this->renderForm((new SettingRepository())->all());
    }

    public function update(): void
    {
        Auth::requireRole(['super_admin']);

        $repository = new SettingRepository();
        $existing = $repository->all();
        $data = [
            'site_title' => $this->posted('site_title'),
            'site_description' => $this->posted('site_description'),
            'og_image' => $this->posted('og_image'),
            'contact_response_note' => $this->posted('contact_response_note'),
            'privacy_notice_version' => $this->posted('privacy_notice_version'),
            'privacy_effective_date' => $this->posted('privacy_effective_date'),
        ];

        $errors = Validator::validate($data, [
            'site_title' => 'required|max:200',
            'site_description' => 'required|max:500',
            'og_image' => 'required|max:1000',
            'contact_response_note' => 'max:300',
            'privacy_notice_version' => 'required|max:40',
            'privacy_effective_date' => 'required|max:60',
        ], [
            'site_title' => 'Site title',
            'site_description' => 'Site description',
            'og_image' => 'Social sharing image',
            'contact_response_note' => 'Contact response note',
            'privacy_notice_version' => 'Privacy notice version',
            'privacy_effective_date' => 'Privacy effective date',
        ]);

        if ($data['og_image'] !== '' && safe_web_url($data['og_image']) === '') {
            $errors['og_image'] = 'Use an https URL or a path beginning with /.';
        }
        if ($data['privacy_notice_version'] !== ''
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,39}$/', $data['privacy_notice_version']) !== 1
        ) {
            $errors['privacy_notice_version'] = 'Use 3–40 letters, numbers, dots, hyphens, or underscores.';
        }

        if ($errors !== []) {
            $this->validationFailed();
            $this->renderForm($existing, $data, $errors);
            return;
        }

        $repository->setMany($data);
        $admin = Auth::admin();
        if ($admin !== null) {
            $changed = [];
            foreach (array_keys($data) as $key) {
                if (($existing[$key] ?? '') !== $data[$key]) {
                    $changed[] = $key;
                }
            }
            AuditLogger::record((int) $admin['id'], 'settings.updated', 'settings', null, [
                'changed' => $changed,
            ]);
        }

        if (($existing['privacy_notice_version'] ?? '') !== $data['privacy_notice_version']) {
            Flash::info('The privacy notice version changed. New form submissions will record the new version; existing records keep the version they accepted.');
        }
        Flash::success('Website settings saved.');
        $this->redirect('settings');
    }

    /**
     * @param array<string, string> $settings
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function renderForm(array $settings, array $old = [], array $errors = []): void
    {
        $this->render('settings/form', 'settings', [
            'pageTitle' => 'Website settings',
            'settings' => $settings,
            'old' => $old,
            'errors' => $errors,
            'submitted' => $old !== [],
        ]);
    }
}
