<?php

include 'wp_clone_procedures.php';

try {
  createCloneTableProcedure($db);
  echo 'CloneTableProcedure done...</br>';
  callCloneTables($db, 'staging', 'REPLACE');
  echo "callCloneTables Success";
} catch (PDOException $e) {
  echo $e->getMessage();
}
