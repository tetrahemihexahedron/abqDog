<?php

declare(strict_types=1);

namespace AbqDog\Handlers;

use AbqDog\Database;
use AbqDog\Dog;
use AbqDog\Http;
use AbqDog\Response;

final class SubmissionsHandler
{
    public static function create(): Response
    {
        $now = self::utcTimestamp();
        $dog = new Dog(
            'Placeholder Dog',
            'Placeholder description until submission validation is implemented.',
            'placeholder.jpg',
            'Placeholder Owner',
            'placeholder-owner@example.test',
            null,
            'pending',
            $now,
            $now,
        );

        self::insertDog($dog);

        return Http::jsonResponse([
            'ok' => true,
            'message' => 'Submission received.',
        ], 201);
    }

    private static function insertDog(Dog $dog): int
    {
        $pdo = Database::connect();
        $statement = $pdo->prepare(
            <<<'SQL'
            INSERT INTO dogs (
                dog_name,
                description,
                photo_filename,
                owner_name,
                owner_email,
                neighborhood,
                status,
                created_at,
                updated_at
            ) VALUES (
                :dog_name,
                :description,
                :photo_filename,
                :owner_name,
                :owner_email,
                :neighborhood,
                :status,
                :created_at,
                :updated_at
            )
            SQL
        );

        $statement->execute([
            ':dog_name' => $dog->dogName,
            ':description' => $dog->description,
            ':photo_filename' => $dog->photoFilename,
            ':owner_name' => $dog->ownerName,
            ':owner_email' => $dog->ownerEmail,
            ':neighborhood' => $dog->neighborhood,
            ':status' => $dog->status,
            ':created_at' => $dog->createdAt,
            ':updated_at' => $dog->updatedAt,
        ]);

        return (int) $pdo->lastInsertId();
    }

    private static function utcTimestamp(): string
    {
        return gmdate('Y-m-d\\TH:i:s\\Z');
    }
}
