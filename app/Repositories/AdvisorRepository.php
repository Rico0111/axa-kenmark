<?php

declare(strict_types=1);

namespace AxaKenneth\Repositories;

use AxaKenneth\Support\Database;
use PDO;

final class AdvisorRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @return array<string, mixed>|null */
    public function get(): ?array
    {
        $statement = $this->pdo->query('SELECT * FROM advisor_profiles WHERE id = 1 LIMIT 1');
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function update(array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE advisor_profiles SET
                display_name = :display_name,
                title = :title,
                organization = :organization,
                tagline = :tagline,
                bio = :bio,
                email = :email,
                phone = :phone,
                whatsapp = :whatsapp,
                messenger_url = :messenger_url,
                facebook_url = :facebook_url,
                photo_media_id = :photo_media_id,
                photo_url = :photo_url,
                photo_alt = :photo_alt,
                title_verified_at = :title_verified_at,
                title_verified_by = :title_verified_by,
                authorization_verified_at = :authorization_verified_at,
                authorization_verified_by = :authorization_verified_by,
                verification_notes = :verification_notes,
                updated_at = NOW()
             WHERE id = 1'
        );

        $statement->execute([
            ':display_name' => $data['display_name'],
            ':title' => $data['title'],
            ':organization' => $data['organization'],
            ':tagline' => $data['tagline'],
            ':bio' => $data['bio'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':whatsapp' => $data['whatsapp'],
            ':messenger_url' => $data['messenger_url'],
            ':facebook_url' => $data['facebook_url'],
            ':photo_media_id' => $data['photo_media_id'],
            ':photo_url' => $data['photo_url'],
            ':photo_alt' => $data['photo_alt'],
            ':title_verified_at' => $data['title_verified_at'],
            ':title_verified_by' => $data['title_verified_by'],
            ':authorization_verified_at' => $data['authorization_verified_at'],
            ':authorization_verified_by' => $data['authorization_verified_by'],
            ':verification_notes' => $data['verification_notes'],
        ]);
    }
}
