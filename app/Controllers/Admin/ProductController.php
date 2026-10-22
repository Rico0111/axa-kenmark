<?php

declare(strict_types=1);

namespace AxaKenneth\Controllers\Admin;

use AxaKenneth\Repositories\CategoryRepository;
use AxaKenneth\Repositories\MediaRepository;
use AxaKenneth\Repositories\ProductRepository;
use AxaKenneth\Support\AuditLogger;
use AxaKenneth\Support\Auth;
use AxaKenneth\Support\Flash;
use AxaKenneth\Support\Http;
use AxaKenneth\Support\HttpException;
use AxaKenneth\Support\Str;
use AxaKenneth\Support\Validator;

final class ProductController extends BaseAdminController
{
    public function index(): void
    {
        $repository = new ProductRepository();
        $search = mb_substr(Http::queryString('q'), 0, 100);
        $status = Http::queryString('status');
        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            $status = '';
        }

        $page = Http::queryInt('page', 1, 1, 100000);
        $perPage = 20;
        $total = $repository->count($search, $status);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        $this->render('products/index', 'products', [
            'pageTitle' => 'Products',
            'products' => $repository->all($search, $status, $perPage, ($page - 1) * $perPage),
            'search' => $search,
            'status' => $status,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'statusCounts' => $repository->statusCounts(),
            'unverifiedCount' => $repository->unverifiedPublishedCount(),
        ]);
    }

    public function create(): void
    {
        $this->renderForm(null, [], false);
    }

    public function store(): void
    {
        $repository = new ProductRepository();
        $admin = Auth::admin();
        if ($admin === null) {
            throw new HttpException(403, 'Your session is no longer valid.');
        }
        $isSuperAdmin = (string) $admin['role'] === 'super_admin';

        $data = $this->input();
        $errors = $this->validate($data, null, $isSuperAdmin);

        if ($errors !== []) {
            $this->validationFailed();
            $this->renderForm(null, $data, true, $errors);
            return;
        }

        $data['slug'] = Str::uniqueSlug(
            $data['slug'] !== '' ? $data['slug'] : $data['name'],
            static fn (string $slug): bool => $repository->slugExists($slug)
        );

        $verification = $this->verificationState($data, null, $isSuperAdmin, (int) $admin['id']);
        $data = array_merge($data, $verification);

        $id = $repository->create($data);
        AuditLogger::record((int) $admin['id'], 'product.created', 'product', $id, [
            'status' => $data['status'],
            'verification_status' => $data['verification_status'],
        ]);

        Flash::success('Product created. Unverified details remain hidden on the public website.');
        $this->redirect('products');
    }

    public function edit(): void
    {
        $product = (new ProductRepository())->find(Http::queryInt('id', 0, 1));
        if ($product === null) {
            throw new HttpException(404, 'Product not found.');
        }

        $this->renderForm($product, [], false);
    }

    public function update(): void
    {
        $repository = new ProductRepository();
        $id = Http::queryInt('id', 0, 1);
        $product = $repository->find($id);
        if ($product === null) {
            throw new HttpException(404, 'Product not found.');
        }

        $admin = Auth::admin();
        if ($admin === null) {
            throw new HttpException(403, 'Your session is no longer valid.');
        }
        $isSuperAdmin = (string) $admin['role'] === 'super_admin';

        $data = $this->input();
        $errors = $this->validate($data, $product, $isSuperAdmin);

        if ($errors !== []) {
            $this->validationFailed();
            $this->renderForm($product, $data, true, $errors);
            return;
        }

        $data['slug'] = Str::uniqueSlug(
            $data['slug'] !== '' ? $data['slug'] : $data['name'],
            static fn (string $slug): bool => $repository->slugExists($slug, $id)
        );

        $contentChanged = $this->contentChanged($product, $data);
        $verification = $this->verificationState($data, $product, $isSuperAdmin, (int) $admin['id']);
        $data = array_merge($data, $verification);

        $repository->update($id, $data);
        AuditLogger::record((int) $admin['id'], 'product.updated', 'product', $id, [
            'status' => $data['status'],
            'verification_status' => $data['verification_status'],
            'content_changed' => $contentChanged,
        ]);

        if (!$isSuperAdmin && $contentChanged && (string) $product['verification_status'] === 'verified') {
            Flash::info('The product content changed, so its previous verification was cleared. A super administrator must review it again.');
        }

        Flash::success('Product updated.');
        $this->redirect('products');
    }

    public function delete(): void
    {
        $repository = new ProductRepository();
        $id = Http::queryInt('id', 0, 1);
        $product = $repository->find($id);
        if ($product === null) {
            throw new HttpException(404, 'Product not found.');
        }

        $repository->delete($id);
        $admin = Auth::admin();
        if ($admin !== null) {
            AuditLogger::record((int) $admin['id'], 'product.deleted', 'product', $id, [
                'name' => (string) $product['name'],
            ]);
        }

        Flash::success('Product deleted.');
        $this->redirect('products');
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        return [
            'category_id' => $this->posted('category_id'),
            'name' => $this->posted('name'),
            'slug' => $this->posted('slug'),
            'product_type' => $this->posted('product_type'),
            'summary' => $this->posted('summary'),
            'official_url' => $this->posted('official_url'),
            'image_media_id' => $this->posted('image_media_id'),
            'image_url' => $this->posted('image_url'),
            'image_alt' => $this->posted('image_alt'),
            'sort_order' => $this->posted('sort_order') !== '' ? $this->posted('sort_order') : '0',
            'status' => $this->posted('status'),
            'verification_status' => $this->posted('verification_status'),
            'verification_notes' => $this->posted('verification_notes'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $existing
     * @return array<string, string>
     */
    private function validate(array $data, ?array $existing, bool $isSuperAdmin): array
    {
        $errors = Validator::validate($data, [
            'name' => 'required|max:160',
            'product_type' => 'required|max:120',
            'summary' => 'max:10000',
            'official_url' => 'url|max:500',
            'image_url' => 'url|max:1000',
            'image_alt' => 'max:190',
            'sort_order' => 'int',
            'status' => 'required|in:draft,published,archived',
        ], [
            'name' => 'Product name',
            'product_type' => 'Product type',
            'summary' => 'Summary',
            'official_url' => 'Official URL',
            'image_url' => 'Image URL',
            'image_alt' => 'Image alternative text',
            'sort_order' => 'Sort order',
            'status' => 'Publish status',
        ]);

        $categoryId = filter_var($data['category_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($categoryId) || (new CategoryRepository())->find($categoryId) === null) {
            $errors['category_id'] = 'Choose a valid service category.';
        }

        $mediaIdRaw = (string) $data['image_media_id'];
        if ($mediaIdRaw !== '' && $mediaIdRaw !== '0') {
            $mediaId = filter_var($mediaIdRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!is_int($mediaId) || (new MediaRepository())->find($mediaId) === null) {
                $errors['image_media_id'] = 'Choose a valid image from the media library.';
            }
        }

        if ($isSuperAdmin) {
            $verificationErrors = Validator::validate($data, [
                'verification_status' => 'required|in:unverified,verified,rejected',
                'verification_notes' => 'max:2000',
            ], [
                'verification_status' => 'Verification status',
                'verification_notes' => 'Verification notes',
            ]);
            $errors = array_merge($errors, $verificationErrors);
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $existing
     * @return array{verification_status:string,verified_at:?string,verified_by:?int,verification_notes:?string}
     */
    private function verificationState(array $data, ?array $existing, bool $isSuperAdmin, int $adminId): array
    {
        if (!$isSuperAdmin) {
            if ($existing === null || $this->contentChanged($existing, $data)) {
                return [
                    'verification_status' => 'unverified',
                    'verified_at' => null,
                    'verified_by' => null,
                    'verification_notes' => $existing === null ? null : $existing['verification_notes'],
                ];
            }

            return [
                'verification_status' => (string) $existing['verification_status'],
                'verified_at' => $existing['verified_at'],
                'verified_by' => $existing['verified_by'],
                'verification_notes' => $existing['verification_notes'],
            ];
        }

        $status = in_array($data['verification_status'], ['unverified', 'verified', 'rejected'], true)
            ? (string) $data['verification_status']
            : 'unverified';
        $notes = trim((string) $data['verification_notes']);
        $notes = $notes !== '' ? $notes : null;

        if ($status !== 'verified') {
            return [
                'verification_status' => $status,
                'verified_at' => null,
                'verified_by' => null,
                'verification_notes' => $notes,
            ];
        }

        $preserve = $existing !== null
            && (string) $existing['verification_status'] === 'verified'
            && !$this->contentChanged($existing, $data);

        return [
            'verification_status' => 'verified',
            'verified_at' => $preserve ? (string) $existing['verified_at'] : date('Y-m-d H:i:s'),
            'verified_by' => $preserve ? $existing['verified_by'] : $adminId,
            'verification_notes' => $notes,
        ];
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $data
     */
    private function contentChanged(array $existing, array $data): bool
    {
        $fields = ['name', 'product_type', 'summary', 'official_url', 'image_url', 'image_alt'];
        foreach ($fields as $field) {
            $before = trim((string) ($existing[$field] ?? ''));
            $after = trim((string) ($data[$field] ?? ''));
            if ($before !== $after) {
                return true;
            }
        }

        $beforeMedia = !empty($existing['image_media_id']) ? (string) (int) $existing['image_media_id'] : '';
        $afterMedia = isset($data['image_media_id']) && (int) $data['image_media_id'] > 0
            ? (string) (int) $data['image_media_id']
            : '';
        if ($beforeMedia !== $afterMedia) {
            return true;
        }

        return (int) ($existing['category_id'] ?? 0) !== (int) ($data['category_id'] ?? 0);
    }

    /**
     * @param array<string, mixed>|null $product
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function renderForm(?array $product, array $old, bool $submitted, array $errors = []): void
    {
        $admin = Auth::admin();
        $this->render('products/form', 'products', [
            'pageTitle' => $product === null ? 'New product' : 'Edit product',
            'product' => $product,
            'categories' => (new CategoryRepository())->all('', 500),
            'mediaItems' => (new MediaRepository())->all(500),
            'isSuperAdmin' => $admin !== null && (string) $admin['role'] === 'super_admin',
            'old' => $old,
            'submitted' => $submitted,
            'errors' => $errors,
        ]);
    }
}
