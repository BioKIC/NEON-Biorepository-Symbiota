<?php
if(!isset($collData) && isset($this->collArr)) $collData = $this->collArr;
?>
<?php echo $collData['collectionname']; ?>. Occurrence dataset (ID: <?php echo $collData['recordid'] ?>) <?php echo $collData['dwcaurl']; ?> accessed via the <?php echo ($DEFAULT_TITLE) ? $DEFAULT_TITLE : "Custom title for the portal"; ?> Portal, <?php echo $SERVER_HOST . $CLIENT_ROOT; ?>, <?php echo date('Y-m-d'); ?>).