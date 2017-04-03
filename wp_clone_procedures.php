<?php

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'test');
define('DB_USER', 'root');
define('DB_PASSWORD', '');

try {
  $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD);
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  echo "Connection failed: " . $e->getMessage();
  exit();
}

function createCloneTableProcedure($db) {
  $db_name = DB_NAME;
  $sql = "DROP PROCEDURE IF EXISTS cloneTables";
  $db->exec($sql) > 0;
  $sql = <<<SQL
CREATE PROCEDURE `cloneTables` (IN prefix varchar(25), IN insertType varchar(15))
BEGIN

DECLARE done INT DEFAULT FALSE;
DECLARE tableName TEXT;
DECLARE curTables CURSOR FOR (
    SELECT TABLE_NAME
    FROM information_schema.TABLES
    WHERE
        TABLE_SCHEMA = "$db_name"
          AND TABLE_NAME NOT LIKE CONCAT( prefix, '_%' )
    ORDER BY TABLE_NAME ASC
);

DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

START TRANSACTION;
OPEN curTables;
table_loop: LOOP
        FETCH curTables INTO tableName;
        IF done THEN
                LEAVE table_loop;
        END IF;

        SET @createTable = CONCAT( "CREATE TABLE IF NOT EXISTS `", prefix, "_", tableName,"` LIKE `", tableName, "`; " );
        PREPARE createTable from @createTable;
        EXECUTE createTable;
        DEALLOCATE PREPARE createTable;

        SET @mergeTable = CONCAT( insertType, " INTO `", prefix, "_", tableName,"` SELECT * FROM `", tableName, "`;" );
        PREPARE mergeTable from @mergeTable;
        EXECUTE mergeTable;
        DEALLOCATE PREPARE mergeTable;

END LOOP;
CLOSE curTables;
COMMIT;
END
SQL;
  $db->exec($sql);
}

function createMergeTableProcedure($db) {
  $db_name = DB_NAME;
  $sql = "DROP PROCEDURE IF EXISTS mergeTables";
  $db->exec($sql);
  $sql = <<<SQL
CREATE PROCEDURE `mergeTables` (IN prefix varchar(25), IN id varchar(25))
BEGIN

DECLARE tableDone INT DEFAULT FALSE;
DECLARE tableName TEXT;
DECLARE targetTableName TEXT;
DECLARE keyPairs varchar(4000);
DECLARE curTables CURSOR FOR (
    SELECT TABLE_NAME
    FROM information_schema.TABLES
    WHERE
        TABLE_SCHEMA = "$db_name"
          AND TABLE_NAME LIKE CONCAT( prefix, '_%' )
    ORDER BY TABLE_NAME ASC
);
DECLARE CONTINUE HANDLER FOR NOT FOUND SET tableDone = TRUE;
START TRANSACTION;

OPEN curTables;
table_loop: LOOP

  FETCH curTables INTO tableName;
  IF tableDone THEN
          LEAVE table_loop;
  END IF;

  SET keyPairs = '';
  SET targetTableName = SUBSTRING(tableName, CHAR_LENGTH(CONCAT(prefix,"_"))+1);

  SET @createTable = CONCAT( "CREATE TABLE IF NOT EXISTS `", targetTableName,
    "` LIKE `", tableName, "`; " );
  PREPARE createTable from @createTable;
  EXECUTE createTable;
  DEALLOCATE PREPARE createTable;

  SET @mergeTable = CONCAT( "INSERT IGNORE INTO `", targetTableName,
    "` SELECT * FROM `", tableName, "`;" );
  PREPARE mergeTable from @mergeTable;
  EXECUTE mergeTable;
  DEALLOCATE PREPARE mergeTable;

  COLUMNBLOCK: BEGIN

    DECLARE columnDone INT DEFAULT FALSE;
    DECLARE columnName TEXT;
    DECLARE curColumns CURSOR FOR (
      SELECT COLUMN_NAME
      FROM information_schema.COLUMNS
      WHERE
        TABLE_SCHEMA = "$db_name" AND TABLE_NAME = tableName
    );
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET columnDone = TRUE;

    OPEN curColumns;
    column_loop: LOOP

      FETCH curColumns INTO columnName;
      IF columnDone THEN
              LEAVE column_loop;
      END IF;

      IF columnName != id THEN
        SET keyPairs = CONCAT( keyPairs , " ", targetTableName, ".", columnName, "=", tableName, ".", columnName);
      END IF;
    END LOOP;
    CLOSE curColumns;

  END COLUMNBLOCK;

  SET @updateTable = CONCAT( "UPDATE `", targetTableName, "` INNER JOIN ", tableName, " ON ", targetTableName, ".", id ,
    " = ", tableName, ".", id, " SET ", keyPairs);
  PREPARE updateTable from @updateTable;
  EXECUTE updateTable;
  DEALLOCATE PREPARE updateTable;

END LOOP;
CLOSE curTables;
COMMIT;
END
SQL;
  $db->exec($sql);
}

function callCloneTables($db, $prefix, $inserType) {
  $sql = "CALL cloneTables('$prefix','$inserType')";
  $db->exec($sql);
}

function callMergeTables($db, $prefix, $key) {
  $sql = "CALL mergeTables('$prefix', '$key')";
  $db->exec($sql);
}

?>
