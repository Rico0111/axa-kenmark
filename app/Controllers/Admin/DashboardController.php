<?php

declare(strict_types=1);

namespace AxaKenneth\Controllers\Admin;

use AxaKenneth\Repositories\AdvisorRepository;
use AxaKenneth\Repositories\CategoryRepository;
use AxaKenneth\Repositories\InquiryRepository;
use AxaKenneth\Repositories\MediaRepository;
use AxaKenneth\Repositories\ProductRepository;

final class DashboardController extends BaseAdminController
{
    public function index(): void
    {
        $products = new ProductRepository();
        $categories = new CategoryRepository();
        $media = new MediaRepository();
        $inquiries = new InquiryRepository();
        $advisor = (new AdvisorRepository())->get();
        $inquiryStatusCounts = $inquiries->statusCounts();

        $this->render('dashboard', 'dashboard', [
            'pageTitle' => 'Dashboard',
            'newInquiryCount' => $inquiryStatusCounts['new'] ?? 0,
            'totalInquiryCount' => array_sum($inquiryStatusCounts),
            'inquiryStatusCounts' => $inquiryStatusCounts,
            'recentInquiries' => $inquiries->recent(5),
            'productCounts' => $products->statusCounts(),
            'unverifiedCount' => $products->unverifiedPublishedCount(),
            'recentProducts' => $products->recent(6),
            'categoryCount' => $categories->count(),
            'mediaCount' => $media->count(),
            'advisor' => $advisor,
            'titleVerified' => $advisor !== null && is_verified($advisor, 'title'),
            'organizationVerified' => $advisor !== null && is_verified($advisor, 'authorization'),
        ]);
    }
}
