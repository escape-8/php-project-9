<?php

namespace Database;

class DbOperation
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function isNameDuplicate($table, $name): bool
    {
        $sql = "SELECT * FROM $table WHERE name = :name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':name', $name);
        $stmt->execute();
        $result = $stmt->fetch($this->pdo::FETCH_ASSOC);
        if ($result) {
            return true;
        }
        return false;
    }
}
