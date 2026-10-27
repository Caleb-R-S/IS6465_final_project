<?php

declare(strict_types=1);

namespace Biomat;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $databasePath = getenv('BIOMAT_DB_PATH') ?: dirname(__DIR__) . '/data/biomat.sqlite';
        $directory = dirname($databasePath);

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('The database directory could not be created.');
        }

        self::$connection = new PDO('sqlite:' . $databasePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        self::$connection->exec('PRAGMA foreign_keys = ON');
        self::$connection->exec('PRAGMA busy_timeout = 5000');
        self::$connection->exec('PRAGMA journal_mode = WAL');

        self::initialize(self::$connection);

        return self::$connection;
    }

    private static function initialize(PDO $database): void
    {
        $database->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL COLLATE NOCASE UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL CHECK (role IN ("donor", "personnel", "admin")),
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $database->exec(
            'CREATE TABLE IF NOT EXISTS donors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER UNIQUE REFERENCES users(id) ON DELETE SET NULL,
                first_name TEXT NOT NULL,
                last_name TEXT NOT NULL,
                date_of_birth TEXT NOT NULL,
                ssn_hash TEXT,
                email TEXT NOT NULL,
                phone TEXT NOT NULL,
                address_line TEXT NOT NULL,
                city TEXT NOT NULL,
                state TEXT NOT NULL,
                postal_code TEXT NOT NULL,
                emergency_contact_name TEXT NOT NULL DEFAULT "",
                emergency_contact_phone TEXT NOT NULL DEFAULT "",
                emergency_contact_email TEXT NOT NULL DEFAULT "",
                blood_type TEXT NOT NULL DEFAULT "",
                weight_lbs REAL,
                height_inches REAL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $database->exec(
            'CREATE TABLE IF NOT EXISTS donation_centers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                address_line TEXT NOT NULL,
                city TEXT NOT NULL,
                state TEXT NOT NULL,
                postal_code TEXT NOT NULL,
                phone TEXT NOT NULL,
                active INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0, 1))
            )'
        );
        $database->exec(
            'CREATE TABLE IF NOT EXISTS medical_personnel (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER UNIQUE REFERENCES users(id) ON DELETE SET NULL,
                first_name TEXT NOT NULL,
                last_name TEXT NOT NULL,
                title TEXT NOT NULL,
                personnel_type TEXT NOT NULL CHECK (personnel_type IN ("doctor", "nurse", "staff"))
            )'
        );
        $database->exec(
            'CREATE TABLE IF NOT EXISTS donations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                donor_id INTEGER NOT NULL REFERENCES donors(id) ON DELETE CASCADE,
                center_id INTEGER NOT NULL REFERENCES donation_centers(id) ON DELETE RESTRICT,
                doctor_id INTEGER REFERENCES medical_personnel(id) ON DELETE SET NULL,
                nurse_id INTEGER REFERENCES medical_personnel(id) ON DELETE SET NULL,
                blood_type TEXT NOT NULL,
                amount_ml REAL NOT NULL CHECK (amount_ml > 0),
                donated_at TEXT NOT NULL
            )'
        );
        $database->exec(
            'CREATE TABLE IF NOT EXISTS appointments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                donor_id INTEGER NOT NULL REFERENCES donors(id) ON DELETE CASCADE,
                center_id INTEGER NOT NULL REFERENCES donation_centers(id) ON DELETE RESTRICT,
                donation_id INTEGER REFERENCES donations(id) ON DELETE SET NULL,
                starts_at TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "booked"
                    CHECK (status IN ("booked", "cancelled", "completed"))
            )'
        );
        $database->exec(
            'CREATE TABLE IF NOT EXISTS compensations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                donation_id INTEGER NOT NULL UNIQUE REFERENCES donations(id) ON DELETE CASCADE,
                amount_cents INTEGER NOT NULL CHECK (amount_cents >= 0),
                paid_at TEXT NOT NULL
            )'
        );
        $database->exec('CREATE INDEX IF NOT EXISTS donors_user_idx ON donors(user_id)');
        $database->exec('CREATE INDEX IF NOT EXISTS appointments_donor_idx ON appointments(donor_id, starts_at)');
        $database->exec('CREATE INDEX IF NOT EXISTS donations_date_idx ON donations(donated_at)');
        $database->exec('CREATE INDEX IF NOT EXISTS donations_center_idx ON donations(center_id)');

        $insertCenter = $database->prepare(
            'INSERT OR IGNORE INTO donation_centers
                (name, address_line, city, state, postal_code, phone)
             VALUES (:name, :address, :city, :state, :postal_code, :phone)'
        );

        foreach ([
            ['Biomat USA - Salt Lake City', '100 South Main Street', 'Salt Lake City', 'UT', '84101', '(801) 555-0101'],
            ['Biomat USA - West Valley City', '2500 South Redwood Road', 'West Valley City', 'UT', '84119', '(801) 555-0102'],
            ['Biomat USA - Ogden', '1750 Washington Boulevard', 'Ogden', 'UT', '84404', '(801) 555-0103'],
        ] as $center) {
            $insertCenter->execute([
                'name' => $center[0],
                'address' => $center[1],
                'city' => $center[2],
                'state' => $center[3],
                'postal_code' => $center[4],
                'phone' => $center[5],
            ]);
        }
    }
}
