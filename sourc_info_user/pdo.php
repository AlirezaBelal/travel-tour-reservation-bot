<?php

class Log
{
    private $logPath;

    public function __construct()
    {
        $this->logPath = dirname(__FILE__) . '/logs/';
    }

    public function write($message, $fileSalt)
    {
        $date = new DateTime();
        $logFileName = $this->generateLogFileName($date, $fileSalt);
        $logFilePath = $this->logPath . $logFileName;

        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0777, true);
        }

        $logContent = "Time : " . $date->format('H:i:s') . "\r\n" . $message . "\r\n";
        file_put_contents($logFilePath, $logContent, FILE_APPEND);
    }

    private function generateLogFileName(DateTime $date, $fileSalt)
    {
        return $date->format('Y-m-d') . "-" . md5($date->format('Y-m-d') . $fileSalt) . ".txt";
    }
}

class DB
{
    private $pdo;
    private $log;
    private $queryCount = 0;

    public function __construct($host, $dbName, $dbUser, $dbPassword, $dbPort = 3306)
    {
        $this->log = new Log();

        try {
            $dsn = "mysql:dbname=$dbName;host=$host;port=$dbPort;charset=utf8";
            $options = [
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ];

            $this->pdo = new PDO($dsn, $dbUser, $dbPassword, $options);
        } catch (PDOException $e) {
            $this->handleException($e->getMessage());
        }
    }

    public function query($query, $params = null, $fetchMode = PDO::FETCH_ASSOC)
    {
        try {
            $this->queryCount++;
            $stmt = $this->prepareQuery($query, $params);
            $stmt->execute();
            $statementType = strtolower(explode(" ", $query)[0]);

            if ($statementType === 'select' || $statementType === 'show') {
                return $stmt->fetchAll($fetchMode);
            } elseif ($statementType === 'insert' || $statementType === 'update' || $statementType === 'delete') {
                return $stmt->rowCount();
            } else {
                return null;
            }
        } catch (PDOException $e) {
            $this->handleException($e->getMessage(), $this->prepareQuery($query, $params));
        }
    }

    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    // Add more methods like 'column', 'row', and 'single' if needed

    private function prepareQuery($query, $params = [])
    {
        $stmt = $this->pdo->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue(is_int($key) ? ++$key : ':' . $key, $value);
        }

        return $stmt;
    }

    private function handleException($message, $sql = '')
    {
        $exception = 'Unhandled Exception. <br />';
        $exception .= $message;
        $exception .= "<br /> You can find the error back in the log.";

        if (!empty($sql)) {
            $message .= "\r\nRaw SQL : " . $sql;
        }

        $this->log->write($message, md5($message)); // Using a simpler salt for the log file name
        header("HTTP/1.1 500 Internal Server Error");
        header("Status: 500 Internal Server Error");
        echo $exception;
        exit();
    }
}
