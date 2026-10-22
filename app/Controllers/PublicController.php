<?php

declare(strict_types=1);

namespace AxaKenneth\Controllers;

use AxaKenneth\Repositories\AdvisorRepository;
use AxaKenneth\Repositories\CategoryRepository;
use AxaKenneth\Repositories\InquiryRepository;
use AxaKenneth\Repositories\MediaRepository;
use AxaKenneth\Repositories\ProductRepository;
use AxaKenneth\Repositories\SettingRepository;
use AxaKenneth\Support\Config;
use AxaKenneth\Support\Flash;
use AxaKenneth\Support\Http;
use AxaKenneth\Support\HttpException;
use AxaKenneth\Support\RateLimiter;
use AxaKenneth\Support\SourceTracker;
use AxaKenneth\Support\Validator;
use AxaKenneth\Support\View;
use PDOException;

final class PublicController
{
    private const SUBMISSION_LIMIT = 5;
    private const SUBMISSION_DECAY_SECONDS = 1800;

    /** @param array{type?:string,old?:array<string,string>,errors?:array<string,string>,status?:int} $formState */
    public function home(array $formState = []): void
    {
        try {
            $profile = (new AdvisorRepository())->get() ?? $this->emptyProfile();
            $categories = (new CategoryRepository())->published();
            $products = (new ProductRepository())->published();
            $settings = (new SettingRepository())->all();
        } catch (PDOException $exception) {
            error_log('Public homepage database failure: ' . $exception->getMessage());
            throw new HttpException(503, 'The website is temporarily unavailable. Please try again shortly.');
        }

        $formType = isset($formState['type']) && array_key_exists((string) $formState['type'], InquiryRepository::TYPE_LABELS)
            ? (string) $formState['type']
            : '';
        $formErrors = isset($formState['errors']) && is_array($formState['errors']) ? $formState['errors'] : [];
        $formOld = isset($formState['old']) && is_array($formState['old']) ? $formState['old'] : [];
        $formStatus = isset($formState['status']) ? (int) $formState['status'] : 200;
        if ($formStatus >= 400) {
            http_response_code($formStatus);
        }

        $privacyVersion = $settings['privacy_notice_version'] ?? (string) Config::get('app.privacy_version');
        $metaTitle = $settings['site_title'] ?? 'Kenneth | AXA Philippines Protection Planning';
        $metaDescription = $settings['site_description']
            ?? 'A personal connection for protection planning, financial goals, and informed decisions.';
        $canonical = Config::appUrl('/');
        $ogImage = $this->absoluteImage($settings['og_image'] ?? '/assets/images/kenneth.jpg');

        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $profile['display_name'] ?? 'Kenneth',
            'url' => $canonical,
            'image' => $this->absoluteImage(advisor_photo_url($profile)),
            'description' => $profile['bio'] ?? '',
        ];
        if (is_verified($profile, 'title') && !empty($profile['title'])) {
            $structuredData['jobTitle'] = $profile['title'];
        }
        if (is_verified($profile, 'authorization') && !empty($profile['organization'])) {
            $structuredData['worksFor'] = [
                '@type' => 'Organization',
                'name' => $profile['organization'],
            ];
        }

        security_headers();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        View::render('public/home', [
            'profile' => $profile,
            'categories' => $categories,
            'products' => $products,
            'settings' => $settings,
            'pageTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'canonical' => $canonical,
            'ogImage' => $ogImage,
            'structuredData' => $structuredData,
            'privacyVersion' => $privacyVersion,
            'formType' => $formType,
            'formErrors' => $formErrors,
            'formOld' => $formOld,
            'robots' => $formStatus >= 400 ? 'noindex,nofollow' : 'index,follow',
        ], 'layouts/public');
    }

    public function submitInquiry(string $type): void
    {
        if (!array_key_exists($type, InquiryRepository::TYPE_LABELS)) {
            throw new HttpException(404, 'The page you requested could not be found.');
        }

        $ip = Http::ip();
        if (RateLimiter::tooManyAttempts('inquiry.submit', $ip, self::SUBMISSION_LIMIT, self::SUBMISSION_DECAY_SECONDS)) {
            header('Retry-After: ' . (string) self::SUBMISSION_DECAY_SECONDS);
            $this->renderSubmissionFailure(
                $type,
                $this->submissionInput($type),
                ['form' => 'Too many messages have been sent from this connection. Please wait about 30 minutes and try again.'],
                429
            );
        }

        RateLimiter::hit('inquiry.submit', $ip, self::SUBMISSION_LIMIT, self::SUBMISSION_DECAY_SECONDS);

        // A hidden field catches automated form-fillers. It is never stored, and
        // the response is deliberately indistinguishable from a real submission.
        if ($this->posted('company_website') !== '') {
            $this->completeSubmission($type);
        }

        $data = $this->submissionInput($type);
        $errors = $this->validateSubmission($type, $data);
        if ($errors !== []) {
            $this->renderSubmissionFailure($type, $data, $errors, 422);
        }

        try {
            $settings = (new SettingRepository())->all();
            $privacyVersion = trim((string) ($settings['privacy_notice_version'] ?? Config::get('app.privacy_version')));
        } catch (PDOException $exception) {
            error_log('Inquiry settings database failure: ' . $exception->getMessage());
            throw new HttpException(503, 'The website is temporarily unavailable. Please try again shortly.');
        }

        (new InquiryRepository())->create([
            'inquiry_type' => $type,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'preferred_contact' => $data['preferred_contact'],
            'topic' => $data['topic'],
            'preferred_time' => $data['preferred_time'],
            'location' => $data['location'],
            'experience' => $data['experience'],
            'message' => $data['message'],
            'privacy_version' => mb_substr($privacyVersion !== '' ? $privacyVersion : 'unknown', 0, 40),
            'source' => SourceTracker::current(),
            'ip_hash' => hash_hmac('sha256', $ip, (string) Config::get('app.key')),
            'user_agent' => Http::userAgent(),
        ]);

        $this->completeSubmission($type);
    }

    private function completeSubmission(string $type): never
    {
        $messages = [
            'general' => 'Thank you. Your message has been received.',
            'consultation' => 'Thank you. Your consultation request has been received.',
            'recruitment' => 'Thank you. Your interest in joining the team has been received.',
        ];

        Flash::success($messages[$type] ?? 'Thank you. Your message has been received.');
        Http::redirect('/#contact-forms');
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function renderSubmissionFailure(string $type, array $old, array $errors, int $status): never
    {
        $this->home([
            'type' => $type,
            'old' => $old,
            'errors' => $errors,
            'status' => $status,
        ]);
        exit;
    }

    /** @return array<string, string> */
    private function submissionInput(string $type): array
    {
        $preferredContact = $this->posted('preferred_contact');
        if (!array_key_exists($preferredContact, InquiryRepository::PREFERRED_CONTACT_LABELS)) {
            $preferredContact = 'email';
        }

        return [
            'name' => $this->posted('name'),
            'email' => $this->posted('email'),
            'phone' => $this->posted('phone'),
            'preferred_contact' => $type === 'general' ? 'email' : $preferredContact,
            'topic' => $this->posted('topic'),
            'preferred_time' => $this->posted('preferred_time'),
            'location' => $this->posted('location'),
            'experience' => $this->posted('experience'),
            'message' => $this->posted('message'),
            'consent' => $this->posted('consent'),
        ];
    }

    /**
     * @param array<string, string> $data
     * @return array<string, string>
     */
    private function validateSubmission(string $type, array $data): array
    {
        $errors = Validator::validate($data, [
            'name' => 'required|max:120',
            'email' => 'required|email|max:190',
            'phone' => 'max:40',
            'preferred_contact' => 'required|in:email,phone,whatsapp',
            'topic' => 'max:120',
            'preferred_time' => 'max:120',
            'location' => 'max:120',
            'experience' => 'max:500',
            'message' => 'required|min:10|max:5000',
            'consent' => 'required|accepted',
        ], [
            'name' => 'Your name',
            'email' => 'Email address',
            'phone' => 'Phone number',
            'preferred_contact' => 'Preferred contact method',
            'topic' => 'Topic',
            'preferred_time' => 'Preferred time',
            'location' => 'Location',
            'experience' => 'Background',
            'message' => 'Message',
            'consent' => 'Privacy consent',
        ]);

        if ($data['phone'] !== '' && preg_match('/^[0-9+().\-\s]{7,40}$/', $data['phone']) !== 1) {
            $errors['phone'] = 'Enter a valid phone number, or leave this field blank.';
        }

        if ($type === 'recruitment' && $data['location'] !== '' && mb_strlen($data['location']) > 120) {
            $errors['location'] = 'Location must be 120 characters or fewer.';
        }

        return $errors;
    }

    private function posted(string $key): string
    {
        return Http::postString($key);
    }

    public function privacy(): void
    {
        try {
            $profile = (new AdvisorRepository())->get() ?? $this->emptyProfile();
            $settings = (new SettingRepository())->all();
        } catch (PDOException $exception) {
            error_log('Privacy page database failure: ' . $exception->getMessage());
            throw new HttpException(503, 'The website is temporarily unavailable. Please try again shortly.');
        }

        $version = $settings['privacy_notice_version'] ?? (string) Config::get('app.privacy_version');
        $effectiveDate = $settings['privacy_effective_date'] ?? '22 October 2026';

        security_headers();
        View::render('public/privacy', [
            'profile' => $profile,
            'settings' => $settings,
            'privacyVersion' => $version,
            'effectiveDate' => $effectiveDate,
            'pageTitle' => 'Privacy Notice · Kenneth | AXA Philippines',
            'metaDescription' => 'How information submitted through Kenneth\'s website is collected, used, and protected.',
            'canonical' => Config::appUrl('privacy'),
            'ogImage' => $this->absoluteImage($settings['og_image'] ?? '/assets/images/kenneth.jpg'),
        ], 'layouts/public');
    }

    public function vcard(): void
    {
        try {
            $profile = (new AdvisorRepository())->get();
        } catch (PDOException $exception) {
            error_log('vCard database failure: ' . $exception->getMessage());
            throw new HttpException(503, 'The contact card is temporarily unavailable.');
        }

        if ($profile === null) {
            throw new HttpException(404, 'The contact card is not available.');
        }

        $escape = static function (string $value): string {
            return str_replace(
                ["\\", "\r\n", "\n", "\r", ';', ','],
                ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'],
                $value
            );
        };

        $name = (string) ($profile['display_name'] ?? 'Kenneth');
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:' . $escape($name),
            'N:;' . $escape($name) . ';;;',
        ];

        if (is_verified($profile, 'authorization') && !empty($profile['organization'])) {
            $lines[] = 'ORG:' . $escape((string) $profile['organization']);
        }
        if (is_verified($profile, 'title') && !empty($profile['title'])) {
            $lines[] = 'TITLE:' . $escape((string) $profile['title']);
        }
        if (!empty($profile['phone'])) {
            $lines[] = 'TEL;TYPE=CELL:' . $escape((string) $profile['phone']);
        }
        if (!empty($profile['email'])) {
            $lines[] = 'EMAIL;TYPE=INTERNET:' . $escape((string) $profile['email']);
        }
        $lines[] = 'URL:' . $escape(Config::appUrl('/'));
        $lines[] = 'END:VCARD';

        security_headers();
        header('Content-Type: text/vcard; charset=utf-8');
        header('Content-Disposition: attachment; filename="Kenneth-AXA-Contact.vcf"');
        header('Cache-Control: no-store, max-age=0');
        echo implode("\r\n", $lines) . "\r\n";
    }

    public function sitemap(): void
    {
        $urls = [
            ['loc' => Config::appUrl('/'), 'priority' => '1.0'],
            ['loc' => Config::appUrl('privacy'), 'priority' => '0.4'],
        ];

        security_headers();
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
        foreach ($urls as $entry) {
            echo '  <url><loc>', htmlspecialchars($entry['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8'), '</loc><priority>', $entry['priority'], '</priority></url>', "\n";
        }
        echo '</urlset>', "\n";
    }

    public function robots(): void
    {
        $base = parse_url((string) Config::get('app.url', ''), PHP_URL_PATH);
        $base = is_string($base) ? rtrim($base, '/') : '';

        security_headers();
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\n";
        echo "Allow: " . ($base !== '' ? $base . '/' : '/') . "\n";
        echo "Disallow: " . ($base !== '' ? $base . '/admin/' : '/admin/') . "\n";
        echo "Disallow: " . ($base !== '' ? $base . '/contact.vcf' : '/contact.vcf') . "\n";
        echo 'Sitemap: ' . Config::appUrl('sitemap.xml') . "\n";
    }

    public function media(): void
    {
        $id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new HttpException(404, 'Image not found.');
        }

        $media = (new MediaRepository())->find((int) $id);
        if ($media === null) {
            throw new HttpException(404, 'Image not found.');
        }

        $storageKey = (string) $media['storage_key'];
        if ($storageKey === '' || basename($storageKey) !== $storageKey || str_contains($storageKey, '..')) {
            throw new HttpException(404, 'Image not found.');
        }

        $basePath = realpath((string) Config::get('media.path'));
        $filePath = realpath((string) Config::get('media.path') . '/' . $storageKey);
        if ($basePath === false || $filePath === false || !str_starts_with($filePath, $basePath . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
            throw new HttpException(404, 'Image not found.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($filePath);
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!is_string($mime) || !in_array($mime, $allowed, true)) {
            throw new HttpException(404, 'Image not found.');
        }

        $etag = '"' . (string) $media['checksum'] . '"';
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            http_response_code(304);
            return;
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($filePath));
        header('Content-Disposition: inline; filename="' . rawurlencode((string) $media['original_name']) . '"');
        header('Cache-Control: public, max-age=604800, immutable');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');
        readfile($filePath);
    }

    /** @return array<string, mixed> */
    private function emptyProfile(): array
    {
        return [
            'display_name' => 'Kenneth',
            'title' => '',
            'organization' => '',
            'tagline' => 'Protection · Purpose · People',
            'bio' => 'Helping individuals and families explore protection options and make informed decisions based on their needs, priorities, and budget.',
            'email' => '',
            'phone' => '',
            'whatsapp' => '',
            'messenger_url' => '',
            'facebook_url' => '',
            'photo_media_id' => null,
            'photo_url' => '/assets/images/kenneth.jpg',
            'photo_alt' => 'Kenneth',
            'title_verified_at' => null,
            'authorization_verified_at' => null,
        ];
    }

    private function absoluteImage(string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return url(ltrim($path, '/'));
    }
}
