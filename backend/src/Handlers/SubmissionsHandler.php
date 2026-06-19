<?php

declare(strict_types=1);

namespace AbqDog\Handlers;

use AbqDog\Database;
use AbqDog\Dog;
use AbqDog\Http;
use AbqDog\Logger;
use AbqDog\Response;
use Throwable;

final class SubmissionsHandler
{
    public static function create(): Response
    {
        $dog = Dog::placeholderForSubmission();

        try {
            self::insertDog($dog);
        } catch (Throwable $exception) {
            Logger::error('Failed to save dog submission.', $exception);

            return Http::jsonError('Could not save submission.', 500);
        }

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
            ':photo_filename' => $dog->photo->filename,
            ':owner_name' => $dog->ownerName,
            ':owner_email' => $dog->ownerEmail,
            ':neighborhood' => $dog->neighborhood,
            ':status' => $dog->status,
            ':created_at' => $dog->createdAt,
            ':updated_at' => $dog->updatedAt,
        ]);

        return (int) $pdo->lastInsertId();
    }
}
