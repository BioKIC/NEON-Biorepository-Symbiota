<?php
include_once($SERVER_ROOT . '/content/lang/header.' . $LANG_TAG . '.php');

$isNeonEditor = false;
if ($IS_ADMIN) $isNeonEditor = true;
elseif (array_key_exists('CollAdmin', $USER_RIGHTS) || array_key_exists('CollEditor', $USER_RIGHTS)) $isNeonEditor = true;
?>

<meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no"/>
<meta name="theme-color" content="#000000"/><link rel="manifest" href="./neon-react/manifest.json"/>
<link rel="shortcut icon" href="./neon-react/favicon.ico?v=201912"/>
<link rel="preconnect" href="https://www.neonscience.org" crossorigin="anonymous"/>
<link rel="stylesheet" data-meta="drupal-fonts" href="./neon-react/assets/css/drupal-fonts.css"/>
<link rel="stylesheet" data-meta="drupal-theme" href="./neon-react/assets/css/drupal-theme.9632c20320a55418c76ed2e12456b01c.min.css"/>
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons"/>
<script src="https://code.jquery.com/jquery-3.5.0.min.js" integrity="sha256-xNzN2a4ltkB44Mc/Jz3pT4iU1cmeR0FkXs4pru/JxaQ=" crossorigin="anonymous"></script>
<script>window.gtmDataLayer=[{page_category:"Core Components"}]</script>
<script>!function(e,t,a,n,g){e[n]=e[n]||[],e[n].push({"gtm.start":(new Date).getTime(),event:"gtm.js"});var m=t.getElementsByTagName(a)[0],r=t.createElement(a),s="&l="+n;r.async=!0,r.src="https://www.googletagmanager.com/gtm.js?id=GTM-K4S83R2"+s,m.parentNode.insertBefore(r,m)}(window,document,"script","gtmDataLayer")</script>
<script>window.NEON_SERVER_DATA="__NEON_SERVER_DATA__"</script>
<script defer="defer" src="./neon-react/static/js/main.js"></script>
<link href="./neon-react/static/css/main.css" rel="stylesheet">



<div id="neon-header"></div>