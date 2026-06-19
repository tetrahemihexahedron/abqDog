<?php

declare(strict_types=1);

namespace AbqDog\Handlers;

use AbqDog\Config;
use AbqDog\Database;
use AbqDog\Http;
use AbqDog\Response;

final class DogsHandler
{
    public static function getApproved(): Response
    {
        $statement = Database::connect()->query(
            <<<'SQL'
            SELECT id, dog_name, description, photo_filename, neighborhood, created_at
            FROM dogs
            WHERE status = 'approved'
            ORDER BY created_at DESC, id DESC
            SQL
        );

        $photoUrlBase = Config::dogImageUrlBase();
        $dogs = [];

        foreach ($statement->fetchAll() as $row) {
            $dogs[] = [
                'id' => (int) $row['id'],
                'dog_name' => $row['dog_name'],
                'description' => $row['description'],
                'photo_url' => $photoUrlBase . '/' . $row['photo_filename'],
                'neighborhood' => $row['neighborhood'],
                'created_at' => $row['created_at'],
            ];
        }

        return Http::jsonResponse(['dogs' => $dogs]);
    }
}
