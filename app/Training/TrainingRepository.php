<?php
declare(strict_types=1);

namespace Haleef\Training;

use Haleef\Support\Database;

final class TrainingRepository
{
    public function upcoming(string $brandCode = 'haleef-learning'): array
    {
        $statement = Database::connection()->prepare(
            "SELECT t.id AS training_id, t.slug AS training_slug, t.title, t.short_description, t.image_path, t.cpd_available,
                    e.id AS edition_id, e.slug AS edition_slug, e.delivery_mode, e.timezone, e.starts_at, e.ends_at,
                    e.venue_name, e.capacity, e.price, e.currency, e.cpd_points, e.registration_status
             FROM training_editions e
             INNER JOIN trainings t ON t.id = e.training_id
             INNER JOIN brands b ON b.id = t.brand_id
             WHERE b.code = ? AND t.status = 'published' AND e.publication_status = 'published'
             ORDER BY e.starts_at ASC"
        );
        $statement->execute([$brandCode]);
        return array_values(array_filter($statement->fetchAll(), static fn($edition) => !Editorial::ended($edition)));
    }

    public function past(string $brandCode = 'haleef-learning'): array
    {
        $statement = Database::connection()->prepare(
            "SELECT t.id AS training_id, t.slug AS training_slug, t.title, t.short_description, t.image_path,
                    e.id AS edition_id, e.slug AS edition_slug, e.delivery_mode, e.timezone, e.starts_at, e.ends_at,
                    e.venue_name, e.price, e.currency
             FROM training_editions e
             INNER JOIN trainings t ON t.id = e.training_id
             INNER JOIN brands b ON b.id = t.brand_id
             WHERE b.code = ? AND t.status = 'published' AND e.publication_status = 'published'
             ORDER BY e.starts_at DESC"
        );
        $statement->execute([$brandCode]);
        return array_values(array_filter($statement->fetchAll(), [Editorial::class, 'ended']));
    }

    public function findPublished(string $trainingSlug, string $brandCode = 'haleef-learning'): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT t.*, b.code AS brand_code, b.name AS brand_name
             FROM trainings t INNER JOIN brands b ON b.id = t.brand_id
             WHERE b.code = ? AND t.slug = ? AND t.status = 'published' LIMIT 1"
        );
        $statement->execute([$brandCode, $trainingSlug]);
        $training = $statement->fetch();
        if (!$training) {
            return null;
        }

        $editions = Database::connection()->prepare(
            "SELECT id, slug, starts_at, ends_at, timezone, venue_name, venue_address, delivery_mode, price, currency,
                    cpd_points, capacity, registration_status, image_path, features, agenda_asset_id
             FROM training_editions WHERE training_id = ? AND publication_status='published' ORDER BY starts_at ASC"
        );
        $editions->execute([$training['id']]);
        $training['editions'] = $editions->fetchAll();
        return $training;
    }

    public function findOpenEdition(string $editionSlug): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT e.*, t.title AS training_title, t.slug AS training_slug, t.short_description,
                    t.brand_id, b.code AS brand_code, b.name AS brand_name
             FROM training_editions e
             INNER JOIN trainings t ON t.id = e.training_id
             INNER JOIN brands b ON b.id = t.brand_id
             WHERE e.slug = ? AND e.registration_status = 'open' AND e.publication_status = 'published' AND t.status = 'published' LIMIT 1"
        );
        $statement->execute([$editionSlug]);
        $edition = $statement->fetch();
        return $edition && !Editorial::ended($edition) ? $edition : null;
    }
}

