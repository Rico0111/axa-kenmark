<?php

/**
 * Initial, idempotent seed data.
 *
 * Existing rows are never overwritten, so re-running the seeder after an
 * administrator has edited content is safe. The five existing products keep
 * their details in the database but remain marked "unverified"; the public
 * website masks those details until an administrator explicitly verifies them.
 *
 * @var PDO $pdo
 */

declare(strict_types=1);

$pdo->beginTransaction();

try {
    $categoryRows = [
        [
            'name' => 'Life & Family Protection',
            'slug' => 'life-family-protection',
            'icon' => '♡',
            'summary' => 'Explore life insurance options designed to help provide financial support for loved ones, subject to policy terms.',
            'description' => 'Life insurance can help protect the people who depend on you. Review needs, beneficiaries, policy terms, and exclusions with an authorized advisor before making a decision.',
            'sort_order' => 10,
            'is_published' => 1,
        ],
        [
            'name' => 'Health & Critical Illness',
            'slug' => 'health-critical-illness',
            'icon' => '✚',
            'summary' => 'Review plans that may provide benefits for specified illnesses or healthcare needs, according to the policy.',
            'description' => 'Health and critical illness plans vary in coverage, waiting periods, exclusions, and benefit limits. Always review the current official policy documents.',
            'sort_order' => 20,
            'is_published' => 1,
        ],
        [
            'name' => 'Savings & Investments',
            'slug' => 'savings-investments',
            'icon' => '↗',
            'summary' => 'Discuss plans connected to savings goals or investment funds, including the risks and charges that may apply.',
            'description' => 'Savings and investment-linked products involve charges and risk. Fund values may rise or fall, and past performance does not guarantee future results.',
            'sort_order' => 30,
            'is_published' => 1,
        ],
        [
            'name' => 'Income & Asset Protection',
            'slug' => 'income-asset-protection',
            'icon' => '⌂',
            'summary' => 'Explore available options for income protection and cover for eligible assets such as cars or homes.',
            'description' => 'Income and asset protection options depend on eligibility, asset condition, policy limits, and exclusions. Ask for the current official terms.',
            'sort_order' => 40,
            'is_published' => 1,
        ],
    ];

    $findCategory = $pdo->prepare('SELECT id FROM service_categories WHERE slug = :slug LIMIT 1');
    $insertCategory = $pdo->prepare(
        'INSERT INTO service_categories
            (name, slug, icon, summary, description, sort_order, is_published, created_at, updated_at)
         VALUES
            (:name, :slug, :icon, :summary, :description, :sort_order, :is_published, NOW(), NOW())'
    );

    $categoryIds = [];
    foreach ($categoryRows as $category) {
        $findCategory->execute([':slug' => $category['slug']]);
        $existingId = $findCategory->fetchColumn();
        if ($existingId !== false) {
            $categoryIds[$category['slug']] = (int) $existingId;
            continue;
        }

        $insertCategory->execute([
            ':name' => $category['name'],
            ':slug' => $category['slug'],
            ':icon' => $category['icon'],
            ':summary' => $category['summary'],
            ':description' => $category['description'],
            ':sort_order' => $category['sort_order'],
            ':is_published' => $category['is_published'],
        ]);
        $categoryIds[$category['slug']] = (int) $pdo->lastInsertId();
    }

    $products = [
        [
            'category' => 'health-critical-illness',
            'name' => 'Health Max',
            'slug' => 'health-max',
            'product_type' => 'Health & Critical Illness',
            'summary' => 'AXA describes Health Max as a critical illness plan with specified major and minor illness benefits and life insurance coverage, subject to policy terms.',
            'official_url' => 'https://www.axa.com.ph/products/health-and-critical-illness/health-max',
            'image_url' => 'https://images.unsplash.com/photo-1511895426328-dc8714191300?auto=format&fit=crop&w=1400&q=85',
            'image_alt' => 'A family spending time together',
            'sort_order' => 10,
        ],
        [
            'category' => 'health-critical-illness',
            'name' => 'Health Start',
            'slug' => 'health-start',
            'product_type' => 'Health & Critical Illness',
            'summary' => 'A critical illness plan focused on specified serious illnesses and their early-stage conditions. Check the current brochure for covered conditions and exclusions.',
            'official_url' => 'https://www.axa.com.ph/products/health-and-critical-illness/health-start',
            'image_url' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=1400&q=85',
            'image_alt' => 'A healthcare professional with a patient',
            'sort_order' => 20,
        ],
        [
            'category' => 'income-asset-protection',
            'name' => 'FlexiProtect',
            'slug' => 'flexiprotect',
            'product_type' => 'Income Protection',
            'summary' => 'AXA lists FlexiProtect as a five-year protection product. Ask Kenneth to explain the available coverage, eligibility, and payment options.',
            'official_url' => 'https://www.axa.com.ph/income-protection/flexi-protect',
            'image_url' => 'https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=1400&q=85',
            'image_alt' => 'Professionals discussing financial goals',
            'sort_order' => 30,
        ],
        [
            'category' => 'savings-investments',
            'name' => 'Asset Master',
            'slug' => 'asset-master',
            'product_type' => 'Savings & Investments',
            'summary' => 'A single-pay insurance and investment plan. Investment-related values and fund performance may vary; review charges, risks, and policy conditions carefully.',
            'official_url' => 'https://www.axa.com.ph/products/savings-and-investments/asset-master/',
            'image_url' => 'https://images.unsplash.com/photo-1516589178581-6cd7833ae3b2?auto=format&fit=crop&w=1400&q=85',
            'image_alt' => 'An older couple enjoying time together',
            'sort_order' => 40,
        ],
        [
            'category' => 'health-critical-illness',
            'name' => 'Global Health Access',
            'slug' => 'global-health-access',
            'product_type' => 'Healthcare',
            'summary' => "AXA's international healthcare plan. Ask about eligibility, limits, geographic scope, provider access, exclusions, and the current benefit schedule.",
            'official_url' => 'https://www.axa.com.ph/products/health-and-critical-illness/global-health-access',
            'image_url' => 'https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=1400&q=85',
            'image_alt' => 'A professional advisor meeting with clients',
            'sort_order' => 50,
        ],
    ];

    $findProduct = $pdo->prepare('SELECT id FROM products WHERE slug = :slug LIMIT 1');
    $insertProduct = $pdo->prepare(
        "INSERT INTO products
            (category_id, name, slug, product_type, summary, official_url, image_url, image_alt,
             sort_order, status, verification_status, verified_at, verified_by, verification_notes,
             created_at, updated_at)
         VALUES
            (:category_id, :name, :slug, :product_type, :summary, :official_url, :image_url, :image_alt,
             :sort_order, 'published', 'unverified', NULL, NULL, NULL, NOW(), NOW())"
    );

    foreach ($products as $product) {
        $findProduct->execute([':slug' => $product['slug']]);
        if ($findProduct->fetchColumn() !== false) {
            continue;
        }

        $insertProduct->execute([
            ':category_id' => $categoryIds[$product['category']],
            ':name' => $product['name'],
            ':slug' => $product['slug'],
            ':product_type' => $product['product_type'],
            ':summary' => $product['summary'],
            ':official_url' => $product['official_url'],
            ':image_url' => $product['image_url'],
            ':image_alt' => $product['image_alt'],
            ':sort_order' => $product['sort_order'],
        ]);
    }

    $advisor = $pdo->prepare(
        "INSERT IGNORE INTO advisor_profiles
            (id, display_name, title, organization, tagline, bio, email, phone, whatsapp,
             messenger_url, facebook_url, photo_media_id, photo_url, photo_alt,
             title_verified_at, title_verified_by, authorization_verified_at, authorization_verified_by,
             verification_notes, created_at, updated_at)
         VALUES
            (1, :display_name, :title, :organization, :tagline, :bio, :email, :phone, :whatsapp,
             :messenger_url, :facebook_url, NULL, :photo_url, :photo_alt,
             NULL, NULL, NULL, NULL, NULL, NOW(), NOW())"
    );
    $advisor->execute([
        ':display_name' => 'Kenneth',
        ':title' => 'Rookie Unit Head',
        ':organization' => 'AXA Philippines',
        ':tagline' => 'Protection · Purpose · People',
        ':bio' => 'Helping individuals and families explore protection options and make informed decisions based on their needs, priorities, and budget.',
        ':email' => 'Kenmarklayug@gmail.com',
        ':phone' => '+639493957621',
        ':whatsapp' => '+639493957621',
        ':messenger_url' => 'https://www.facebook.com/kenmark.layug316/about',
        ':facebook_url' => 'https://www.facebook.com/kenmark.layug316/about',
        ':photo_url' => '/assets/images/kenneth.jpg',
        ':photo_alt' => 'Kenneth',
    ]);

    $settings = [
        'privacy_notice_version' => '2026-10-22',
        'privacy_effective_date' => '22 October 2026',
        'site_title' => 'Kenneth | AXA Philippines Protection Planning',
        'site_description' => 'A personal connection for protection planning, financial goals, and informed decisions. Product details are subject to verification and official terms.',
        'og_image' => '/assets/images/kenneth.jpg',
        'contact_response_note' => 'Kenneth aims to respond within two business days.',
    ];
    $insertSetting = $pdo->prepare(
        'INSERT INTO site_settings (setting_key, setting_value, updated_at)
         VALUES (:setting_key, :setting_value, NOW())
         ON DUPLICATE KEY UPDATE setting_value = setting_value'
    );
    foreach ($settings as $key => $value) {
        $insertSetting->execute([':setting_key' => $key, ':setting_value' => $value]);
    }

    $pdo->commit();
    fwrite(STDOUT, "Seed data applied (existing rows preserved).\n");
} catch (Throwable $exception) {
    $pdo->rollBack();
    throw $exception;
}
