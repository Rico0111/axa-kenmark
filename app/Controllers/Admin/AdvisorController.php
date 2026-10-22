<?php

declare(strict_types=1);

namespace AxaKenneth\Controllers\Admin;

use AxaKenneth\Repositories\AdvisorRepository;
use AxaKenneth\Repositories\MediaRepository;
use AxaKenneth\Support\AuditLogger;
use AxaKenneth\Support\Flash;
use AxaKenneth\Support\HttpException;
use AxaKenneth\Support\Validator;

final class AdvisorController extends BaseAdminController
{
    public function edit(): void
    {
        $profile = (new AdvisorRepository())->get();
        if ($profile === null) {
            throw new HttpException(404, 'The advisor profile has not been set up. Run the database seeder first.');
        }

        $this->renderForm($profile, [], false);
    }

    public function update(): void
    {
        $repository = new AdvisorRepository();
        $profile = $repository->get();
        if ($profile === null) {
            throw new HttpException(404, 'The advisor profile has not been set up.');
        }

        $admin = \AxaKenneth\Support\Auth::admin();
        if ($admin === null) {
            throw new HttpException(403, 'Your session is no longer valid.');
        }
        $isSuperAdmin = (string) $admin['role'] === 'super_admin';

        $displayName = $this->posted('display_name');
        $title = $this->posted('title');
        $organization = $this->posted('organization');
        $tagline = $this->posted('tagline');
        $bio = $this->posted('bio');
        $email = strtolower($this->posted('email'));
        $phone = $this->posted('phone');
        $whatsapp = $this->posted('whatsapp');
        $messengerUrl = $this->posted('messenger_url');
        $facebookUrl = $this->posted('facebook_url');
        $photoUrl = $this->posted('photo_url');
        $photoAlt = $this->posted('photo_alt');
        $verificationNotes = $this->posted('verification_notes');

        $photoMediaId = null;
        $photoMediaRaw = $this->posted('photo_media_id');
        if ($photoMediaRaw !== '' && $photoMediaRaw !== '0') {
            $validatedId = filter_var($photoMediaRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!is_int($validatedId)) {
                $photoMediaId = -1;
            } else {
                $photoMediaId = $validatedId;
            }
        }

        $errors = Validator::validate([
            'display_name' => $displayName,
            'title' => $title,
            'organization' => $organization,
            'tagline' => $tagline,
            'bio' => $bio,
            'email' => $email,
            'phone' => $phone,
            'whatsapp' => $whatsapp,
            'messenger_url' => $messengerUrl,
            'facebook_url' => $facebookUrl,
            'photo_alt' => $photoAlt,
            'verification_notes' => $verificationNotes,
        ], [
            'display_name' => 'required|max:120',
            'title' => 'max:160',
            'organization' => 'max:160',
            'tagline' => 'max:255',
            'bio' => 'max:5000',
            'email' => 'email|max:190',
            'phone' => 'max:40',
            'whatsapp' => 'max:40',
            'messenger_url' => 'url|max:500',
            'facebook_url' => 'url|max:500',
            'photo_alt' => 'max:190',
            'verification_notes' => 'max:2000',
        ], [
            'display_name' => 'Display name',
            'tagline' => 'Tagline',
            'bio' => 'Biography',
            'photo_alt' => 'Photo alternative text',
            'verification_notes' => 'Verification notes',
        ]);

        if ($photoMediaId === -1) {
            $errors['photo_media_id'] = 'Choose a valid photo from the media library.';
        } elseif ($photoMediaId !== null && (new MediaRepository())->find($photoMediaId) === null) {
            $errors['photo_media_id'] = 'The selected photo no longer exists.';
        }

        if ($photoUrl !== '' && (safe_web_url($photoUrl) === '' || mb_strlen($photoUrl) > 1000)) {
            $errors['photo_url'] = 'Enter a valid http(s) URL or a path beginning with /.';
        }

        if ($photoAlt === '') {
            $photoAlt = $displayName;
        }

        $titleChanged = trim((string) $profile['title']) !== $title;
        $organizationChanged = trim((string) $profile['organization']) !== $organization;

        $titleApproved = false;
        $organizationApproved = false;
        if ($isSuperAdmin) {
            $titleApproved = $this->checkbox('title_verified');
            $organizationApproved = $this->checkbox('authorization_verified');

            if ($titleApproved && $title === '') {
                $errors['title_verified'] = 'Enter a title before marking it verified.';
            }
            if ($organizationApproved && $organization === '') {
                $errors['authorization_verified'] = 'Enter an organization before marking authorization verified.';
            }
        }

        if ($errors !== []) {
            $this->validationFailed();
            $this->renderForm($profile, $_POST, true, $errors, $isSuperAdmin);
            return;
        }

        if ($isSuperAdmin) {
            if ($titleApproved) {
                $titleVerifiedAt = !empty($profile['title_verified_at']) && !$titleChanged
                    ? (string) $profile['title_verified_at']
                    : date('Y-m-d H:i:s');
                $titleVerifiedBy = !empty($profile['title_verified_at']) && !$titleChanged
                    ? $profile['title_verified_by']
                    : (int) $admin['id'];
            } else {
                $titleVerifiedAt = null;
                $titleVerifiedBy = null;
            }

            if ($organizationApproved) {
                $organizationVerifiedAt = !empty($profile['authorization_verified_at']) && !$organizationChanged
                    ? (string) $profile['authorization_verified_at']
                    : date('Y-m-d H:i:s');
                $organizationVerifiedBy = !empty($profile['authorization_verified_at']) && !$organizationChanged
                    ? $profile['authorization_verified_by']
                    : (int) $admin['id'];
            } else {
                $organizationVerifiedAt = null;
                $organizationVerifiedBy = null;
            }
        } else {
            $titleVerifiedAt = $titleChanged ? null : $profile['title_verified_at'];
            $titleVerifiedBy = $titleChanged ? null : $profile['title_verified_by'];
            $organizationVerifiedAt = $organizationChanged ? null : $profile['authorization_verified_at'];
            $organizationVerifiedBy = $organizationChanged ? null : $profile['authorization_verified_by'];
        }

        $repository->update([
            'display_name' => $displayName,
            'title' => $title,
            'organization' => $organization,
            'tagline' => $tagline,
            'bio' => $bio,
            'email' => $email,
            'phone' => $phone,
            'whatsapp' => $whatsapp,
            'messenger_url' => $messengerUrl,
            'facebook_url' => $facebookUrl,
            'photo_media_id' => $photoMediaId,
            'photo_url' => $photoUrl,
            'photo_alt' => $photoAlt,
            'title_verified_at' => $titleVerifiedAt,
            'title_verified_by' => $titleVerifiedBy,
            'authorization_verified_at' => $organizationVerifiedAt,
            'authorization_verified_by' => $organizationVerifiedBy,
            'verification_notes' => $verificationNotes,
        ]);

        AuditLogger::record((int) $admin['id'], 'advisor.updated', 'advisor_profile', 1, [
            'title_verified' => $titleVerifiedAt !== null,
            'authorization_verified' => $organizationVerifiedAt !== null,
            'title_changed' => $titleChanged,
            'organization_changed' => $organizationChanged,
        ]);

        Flash::success('Advisor profile updated.');
        if (!$isSuperAdmin && ($titleChanged && !empty($profile['title_verified_at']) || $organizationChanged && !empty($profile['authorization_verified_at']))) {
            Flash::info('Because the title or organization changed, its previous verification was cleared until a super administrator reviews it.');
        }

        $this->redirect('advisor');
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function renderForm(
        array $profile,
        array $old,
        bool $submitted,
        array $errors = [],
        ?bool $isSuperAdmin = null
    ): void {
        if ($isSuperAdmin === null) {
            $admin = \AxaKenneth\Support\Auth::admin();
            $isSuperAdmin = $admin !== null && (string) $admin['role'] === 'super_admin';
        }

        $this->render('advisor/form', 'advisor', [
            'pageTitle' => 'Advisor profile',
            'profile' => $profile,
            'mediaItems' => (new MediaRepository())->all(500),
            'isSuperAdmin' => $isSuperAdmin,
            'old' => $old,
            'submitted' => $submitted,
            'errors' => $errors,
        ]);
    }
}
