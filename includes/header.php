<?php
include_once($SERVER_ROOT . '/content/lang/header.' . $LANG_TAG . '.php');

$isNeonEditor = false;
if ($IS_ADMIN) $isNeonEditor = true;
elseif (array_key_exists('CollAdmin', $USER_RIGHTS) || array_key_exists('CollEditor', $USER_RIGHTS)) $isNeonEditor = true;
?>

<div id="neon-header"></div>