<?php
require '/app/bin/runtime.php';
if(getenv('DB_ROOT_PASSWORD')!==false)throw new RuntimeException('Root secret must not reach the web process.');
if($env('DB_USERNAME')==='root')throw new RuntimeException('Runtime must use a dedicated database account.');
try{$pdo->exec('CREATE TABLE synthetic_forbidden_ddl (id INT)');throw new RuntimeException('Runtime DDL was allowed.');}
catch(PDOException$error){if(($error->errorInfo[1]??null)!==1142)throw$error;}
Database::run($pdo,"INSERT INTO AuditLog(actor_id,action,target_type,target_id) VALUES (999,'permission.smoke','test',1)");
Database::run($pdo,"DELETE FROM AuditLog WHERE action='permission.smoke'");
echo"Runtime least-privilege checks passed (4 checks).\n";
