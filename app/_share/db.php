<?php

namespace _share;

use ReflectionClass;
use ReflectionProperty;
use PDO;
use Exception;

class db
{

    static function get_table_name(string $class): string
    {
        return new ReflectionClass($class)->getShortName();
    }

    static function get_by_id(string $class, int $id): ?object
    {
        $table_name = db::get_table_name($class);
        $sql = 'SELECT * FROM ' . $table_name . ' WHERE id = :id';
        $args = ['id' => $id];
        $result = db::select($class, $sql, $args);
        return count($result) > 0 ? $result[0] : null;
    }

    static function select(string $class, string $sql, array $args = []): array
    {
        $statement = db::get_db()->prepare($sql);
        $statement->execute($args);
        return $statement->fetchAll(PDO::FETCH_CLASS, $class);
    }

    static function select_one(string $class, string $sql, array $args = []): ?object
    {
        $statement = db::get_db()->prepare($sql);
        $statement->execute($args);
        return $statement->fetchAll(PDO::FETCH_CLASS, $class)[0] ?? null;
    }

    static function delete(string $class, int $id)
    {
        $table_name = db::get_table_name($class);
        $sql = 'DELETE FROM ' . $table_name . ' WHERE id = :id';
        $args = ['id' => $id];
        db::get_db()->prepare($sql)->execute($args);
    }

    static function create_and_update_table(string $class): void
    {
        $reflection_class = new ReflectionClass($class);
        $table_name = $reflection_class->getShortName();
        $sql = 'CREATE TABLE IF NOT EXISTS ' . $table_name . ' (id INTEGER PRIMARY KEY)';
        db::get_db()->prepare($sql)->execute();
        $properties = $reflection_class->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach ($properties as $prop)
        {
            $name = $prop->getName();
            $type = $prop->getType()->getName();
            $sql_type = match ($type)
            {
                "string" => 'TEXT',
                "int" => "INTEGER",
                "float" => "REAL",
                default => throw new Exception("bad type in struct")
            };
            $default_value = match ($sql_type)
            {
                "TEXT" => "''",
                "INTEGER" => "0",
                "REAL" => "0.0",
                default => throw new Exception("bad type in struct")
            };
            $statement = "ALTER TABLE $table_name ADD COLUMN `$name` $sql_type NOT NULL DEFAULT $default_value;";
            try
            {
                db::get_db()->exec($statement);
            } catch (Exception $e)
            {
                # ...
            }
        }
    }

    static function save(object $instance): void
    {
        $class = get_class($instance);
        $table_name = db::get_table_name($class);

        $reflection_class = new ReflectionClass($class);
        $properties = $reflection_class->getProperties(ReflectionProperty::IS_PUBLIC);

        $fields = [];
        $args = [];

        foreach ($properties as $prop)
        {
            $name = $prop->getName();

            if ($name === 'id' || $name === 'created_at')
            {
                continue;
            }

            if (!$prop->isInitialized($instance))
            {
                continue;
            }

            $fields[] = $name;
            $args[$name] = $prop->getValue($instance);
        }

        if ($instance->id <= 0)
        {
            # insert
            $columns = implode(', ', array_map(fn($field) => "`$field`", $fields));
            $params = implode(', ', array_map(fn($field) => ":$field", $fields));

            $sql = "INSERT INTO $table_name ($columns) VALUES ($params)";
            db::get_db()->prepare($sql)->execute($args);

            $instance->id = (int)db::get_db()->lastInsertId();
        }
        else
        {
            # update
            $sets = implode(', ', array_map(fn($field) => "`$field` = :$field", $fields));

            $sql = "UPDATE $table_name SET $sets WHERE id = :id";
            $args['id'] = $instance->id;

            db::get_db()->prepare($sql)->execute($args);
        }
    }

    static function get_db(): PDO
    {
        static $pdo = null;
        if ($pdo !== null) return $pdo;

        $dsn = 'sqlite:' . __DIR__ . '/../../app.sqlite';
        $user = null;
        $pass = null;

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    }


}