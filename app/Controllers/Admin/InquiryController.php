<?php

declare(strict_types=1);

namespace AxaKenneth\Controllers\Admin;

use AxaKenneth\Repositories\InquiryRepository;
use AxaKenneth\Support\AuditLogger;
use AxaKenneth\Support\Auth;
use AxaKenneth\Support\Flash;
use AxaKenneth\Support\Http;
use AxaKenneth\Support\HttpException;
use AxaKenneth\Support\Validator;

final class InquiryController extends BaseAdminController
{
    public function index(): void
    {
        $repository = new InquiryRepository();
        $search = mb_substr(Http::queryString('q'), 0, 100);
        $type = Http::queryString('type');
        if (!array_key_exists($type, InquiryRepository::TYPE_LABELS)) {
            $type = '';
        }
        $status = Http::queryString('status');
        if (!array_key_exists($status, InquiryRepository::STATUS_LABELS)) {
            $status = '';
        }

        $page = Http::queryInt('page', 1, 1, 100000);
        $perPage = 20;
        $total = $repository->count($search, $type, $status);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        $this->render('inquiries/index', 'inquiries', [
            'pageTitle' => 'Inquiries',
            'inquiries' => $repository->all($search, $type, $status, $perPage, ($page - 1) * $perPage),
            'search' => $search,
            'type' => $type,
            'status' => $status,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'statusCounts' => $repository->statusCounts(),
            'typeCounts' => $repository->typeCounts(),
        ]);
    }

    public function show(): void
    {
        $inquiry = (new InquiryRepository())->find(Http::queryInt('id', 0, 1));
        if ($inquiry === null) {
            throw new HttpException(404, 'Inquiry not found.');
        }

        $this->renderInquiry($inquiry);
    }

    public function update(): void
    {
        $repository = new InquiryRepository();
        $id = Http::queryInt('id', 0, 1);
        $inquiry = $repository->find($id);
        if ($inquiry === null) {
            throw new HttpException(404, 'Inquiry not found.');
        }

        $data = [
            'status' => $this->posted('status'),
            'internal_notes' => $this->posted('internal_notes'),
        ];
        $errors = Validator::validate($data, [
            'status' => 'required|in:new,in_review,contacted,scheduled,closed,spam',
            'internal_notes' => 'max:5000',
        ], [
            'status' => 'Workflow status',
            'internal_notes' => 'Internal notes',
        ]);

        if ($errors !== []) {
            $this->validationFailed();
            $this->renderInquiry($inquiry, $data, $errors);
            return;
        }

        $admin = Auth::admin();
        if ($admin === null) {
            throw new HttpException(403, 'Your session is no longer valid.');
        }

        $repository->updateWorkflow(
            $id,
            $data['status'],
            $data['internal_notes'],
            (int) $admin['id']
        );
        AuditLogger::record((int) $admin['id'], 'inquiry.updated', 'inquiry', $id, [
            'previous_status' => (string) $inquiry['status'],
            'status' => $data['status'],
        ]);

        Flash::success('Inquiry workflow updated.');
        $this->redirect('inquiry-view', ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $inquiry
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function renderInquiry(array $inquiry, array $old = [], array $errors = []): void
    {
        $this->render('inquiries/show', 'inquiries', [
            'pageTitle' => 'Inquiry #' . (int) $inquiry['id'],
            'inquiry' => $inquiry,
            'old' => $old,
            'errors' => $errors,
            'typeLabels' => InquiryRepository::TYPE_LABELS,
            'statusLabels' => InquiryRepository::STATUS_LABELS,
            'preferredContactLabels' => InquiryRepository::PREFERRED_CONTACT_LABELS,
        ]);
    }
}
