<?php

$serverName = '';
$username = '';
$password = '';
$dbName = '';

try {
    $db = new PDO("mysql:host=$serverName;dbname=$dbName", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit();
}

try {
    createCloneTableProcedure($db);
    callCloneTables($db, 'prefix', 'REPLACE');
    createMergeTableProcedure($db);
    callMergeTables($db, 'prefix', 'INSERT IGNORE');
    echo "Success";
} catch(PDOException $e) {
    echo $e->getMessage();
}

function createCloneTableProcedure($db){
    $sql = "DROP PROCEDURE IF EXISTS cloneTables";
    $db->exec($sql);
    $sql = "
        CREATE PROCEDURE cloneTables(IN prefix varchar(25), IN insertType varchar(15))
        BEGIN

        DECLARE done INT DEFAULT FALSE;
        DECLARE tableName TEXT;
        DECLARE curTables CURSOR FOR (
            SELECT table_name
            FROM information_schema.tables
            WHERE
                table_schema != 'information_schema'
                AND table_name NOT LIKE CONCAT( prefix, '_%' )
            ORDER BY table_name ASC
        );

        DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

        START TRANSACTION;
        OPEN curTables;
        table_loop: LOOP
                FETCH curTables INTO tableName;
                IF done THEN
                        LEAVE table_loop;
                END IF;

                SET @createTable = CONCAT( \"CREATE TABLE IF NOT EXISTS `\", prefix, \"_\", tableName,\"` LIKE `\", tableName, \"`; \" );
                PREPARE createTable from @createTable;
                EXECUTE createTable;
                DEALLOCATE PREPARE createTable;

                SET @mergeTable = CONCAT( insertType, \" INTO `\", prefix, \"_\", tableName,\"` SELECT * FROM `\", tableName, \"`;\" );
                PREPARE mergeTable from @mergeTable;
                EXECUTE mergeTable;
                DEALLOCATE PREPARE mergeTable;

        END LOOP;
        CLOSE curTables;
        COMMIT;
        END";
    $db->exec($sql);
}

function createMergeTableProcedure($db){
    $sql = "DROP PROCEDURE IF EXISTS mergeTables";
    $db->exec($sql);
    $sql = "
        CREATE PROCEDURE mergeTables(IN prefix varchar(25), IN insertType varchar(15))
        BEGIN
        DECLARE done INT DEFAULT FALSE;
        DECLARE tableName TEXT;
        DECLARE curTables CURSOR FOR (
            SELECT table_name
            FROM information_schema.tables
            WHERE
                table_schema != 'information_schema'
                AND table_name LIKE CONCAT( prefix, '_%' )
            ORDER BY table_name
        );

        DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
        START TRANSACTION;
        OPEN curTables;
        table_loop: LOOP
                FETCH curTables INTO tableName;
                IF done THEN
                        LEAVE table_loop;
                END IF;

                SET @createTable = CONCAT( \"CREATE TABLE IF NOT EXISTS `\", SUBSTRING( tableName, CHAR_LENGTH(prefix) ), \"` LIKE `\", tableName, \"`; \" );
                PREPARE createTable from @createTable;
                EXECUTE createTable;
                DEALLOCATE PREPARE createTable;

                SET @mergeTable = CONCAT( insertType, \" INTO `\", SUBSTRING( tableName, CHAR_LENGTH(prefix) ), \"` SELECT * FROM `\", tableName, \"`;\" );
                PREPARE mergeTable from @mergeTable;
                EXECUTE mergeTable;
                DEALLOCATE PREPARE mergeTable;

        END LOOP;
        CLOSE curTables;
        COMMIT;
        END";
    $db->exec($sql);
}

function callCloneTables($db, $prefix, $inserTyep){
    $sql = "CALL cloneTables('$prefix', '$inserTyep')";
    $db->exec($sql);
}

function callMergeTables($db, $prefix, $inserTyep){
    $sql = "CALL mergeTables('$prefix', '$inserTyep')";
    $db->exec($sql);
}

?>
