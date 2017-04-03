<?php

include 'wp_clone_procedures.php';

try {
  createMergeTableProcedure($db);
  echo 'createMergeTableProcedure done...</br>';
  callMergeTables($db, 'staging', 'id');
  echo 'callMergeTables Success...</br>';
} catch (PDOException $e) {
  echo $e->getMessage();
}