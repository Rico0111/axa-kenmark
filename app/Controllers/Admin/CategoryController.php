<?php

declare(strict_types=1);

namespace AxaKenneth\Controllers\Admin;

use AxaKenneth\Repositories\CategoryRepository;
use AxaKenneth\Support\AuditLogger;
use AxaKenneth\Support\Auth;
use AxaKenneth\Support\Flash;
use AxaKenneth\Support\Http;
use AxaKenneth\Support\HttpException;
use AxaKenneth\Support\Str;
use AxaKenneth\Support\Validator;
use PDOException;

final class CategoryController extends BaseAdminController
{
    public function index(): void
    {
        $repository = new CategoryRepository();
        $search = mb_substr(Http::queryString('q'), 0, 100);
        $page = Http::queryInt('page', 1, 1, 100000);
        $perPage = 20;
        $total = $repository->count($search);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        $this->render('categories/index', 'categories', [
            'pageTitle' => 'Service categories',
            'categories' => $repository->all($search, $perPage, ($page - 1) * $perPage),
            'search' => $search,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    public function create(): void
    {
        $this->renderForm(null, [], false);
    }

    public function store(): void
    {
        $repository = new CategoryRepository();
        $data = $this->input();
        $errors = $this->validate($data);

        if ($errors !== []) {
            $this->validationFailed();
            $this->renderForm(null, $data, true, $errors);
            return;
        }

        $data['slug'] = Str::uniqueSlug(
            $data['slug'] !== '' ? $data['slug'] : $data['name'],
            static fn (string $slug): bool => $repository->slugExists($slug)
        );

        $id = $repository->create($data);
        $admin = Auth::admin();
        if ($admin !== null) {
            AuditLogger::record((int) $admin['id'], 'category.created', 'service_category', $id, [
                'is_published' => $data['is_published'],
            ]);
        }

        Flash::success('Service category created.');
        $this->redirect('categories');
    }

    public function edit(): void
    {
        $category = (new CategoryRepository())->find(Http::queryInt('id', 0, 1));
        if ($category === null) {
            throw new HttpException(404, 'Service category not found.');
        }

        $this->renderForm($category, [], false);
    }

    public function update(): void
    {
        $repository = new CategoryRepository();
        $id = Http::queryInt('id', 0, 1);
        $category = $repository->find($id);
        if ($category === null) {
            throw new HttpException(404, 'Service category not found.');
        }

        $data = $this->input();
        $errors = $this->validate($data);

        if ($errors !== []) {
            $this->validationFailed();
            $this->renderForm($category, $data, true, $errors);
            return;
        }

        $data['slug'] = Str::uniqueSlug(
            $data['slug'] !== '' ? $data['slug'] : $data['name'],
            static fn (string $slug): bool => $repository->slugExists($slug, $id)
        );

        $repository->update($id, $data);
        $admin = Auth::admin();
        if ($admin !== null) {
            AuditLogger::record((int) $admin['id'], 'category.updated', 'service_category', $id, [
                'is_published' => $data['is_published'],
            ]);
        }

        Flash::success('Service category updated.');
        $this->redirect('categories');
    }

    public function delete(): void
    {
        $repository = new CategoryRepository();
        $id = Http::queryInt('id', 0, 1);
        $category = $repository->find($id);
        if ($category === null) {
            throw new HttpException(404, 'Service category not found.');
        }

        $productCount = $repository->productCount($id);
        if ($productCount > 0) {
            Flash::error('This category still has ' . $productCount . ' product(s). Move or delete those products first.');
            $this->redirect('categories');
        }

        try {
            $repository->delete($id);
        } catch (PDOException $exception) {
            error_log('Category delete failed: ' . $exception->getMessage());
            Flash::error('The category could not be deleted because it is still in use.');
            $this->redirect('categories');
        }

        $admin = Auth::admin();
        if ($admin !== null) {
            AuditLogger::record((int) $admin['id'], 'category.deleted', 'service_category', $id, [
                'name' => (string) $category['name'],
            ]);
        }

        Flash::success('Service category deleted.');
        $this->redirect('categories');
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        return [
            'name' => $this->posted('name'),
            'slug' => $this->posted('slug'),
            'icon' => $this->posted('icon') !== '' ? $this->posted('icon') : '•',
            'summary' => $this->posted('summary'),
            'description' => $this->posted('description'),
            'sort_order' => $this->posted('sort_order') !== '' ? $this->posted('sort_order') : '0',
            'is_published' => $this->checkbox('is_published') ? 1 : 0,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function validate(array $data): array
    {
        return Validator::validate($data, [
            'name' => 'required|max:120',
            'icon' => 'max:16',
            'summary' => 'max:500',
            'description' => 'max:10000',
            'sort_order' => 'int',
        ], [
            'name' => 'Category name',
            'summary' => 'Category summary',
            'description' => 'Category description',
            'sort_order' => 'Sort order',
        ]);
    }

    /**
     * @param array<string, mixed>|null $category
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function renderForm(?array $category, array $old, bool $submitted, array $errors = []): void
    {
        $this->render('categories/form', 'categories', [
            'pageTitle' => $category === null ? 'New service category' : 'Edit service category',
            'category' => $category,
            'old' => $old,
            'submitted' => $submitted,
            'errors' => $errors,
        ]);
    }
}
