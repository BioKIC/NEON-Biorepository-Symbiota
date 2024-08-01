<?php
/*
 * Customize styling by adding or modifying CSS file links below
 * Default styling for individual page is defined within /css/symb/
 * Individual styling can be customized by:
 *     1) Uncommenting the $CUSTOM_CSS_PATH variable below
 *     2) Copying individual CCS file to the /css/symb/custom directory
 *     3) Modifying the sytle definiation within the file
 */

$CUSTOM_CSS_PATH = '/css/symb/custom';
?>

<!--neon react links-->
<!--React last updated: 8/1/2024, 1:35:44 PM-->
<meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no"/><meta name="theme-color" content="#000000"/><link rel="manifest" href="<?php echo $CLIENT_ROOT; ?>/neon-react/manifest.json"/><link rel="shortcut icon" href="<?php echo $CLIENT_ROOT; ?>/neon-react/favicon.ico?v=201912"/><link rel="preconnect" href="https://www.neonscience.org" crossorigin="anonymous"/><link rel="stylesheet" data-meta="drupal-fonts" href="<?php echo $CLIENT_ROOT; ?>/neon-react/assets/css/drupal-fonts.css"/><link rel="stylesheet" data-meta="drupal-theme" href="<?php echo $CLIENT_ROOT; ?>/neon-react/assets/css/drupal-theme.9632c20320a55418c76ed2e12456b01c.min.css"/><link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons"/><script src="https://code.jquery.com/jquery-3.5.0.min.js" integrity="sha256-xNzN2a4ltkB44Mc/Jz3pT4iU1cmeR0FkXs4pru/JxaQ=" crossorigin="anonymous"></script><script>window.gtmDataLayer=[{page_category:"Core Components"}]</script><script>!function(e,t,a,n,g){e[n]=e[n]||[],e[n].push({"gtm.start":(new Date).getTime(),event:"gtm.js"});var m=t.getElementsByTagName(a)[0],r=t.createElement(a),s="&l="+n;r.async=!0,r.src="https://www.googletagmanager.com/gtm.js?id=GTM-K4S83R2"+s,m.parentNode.insertBefore(r,m)}(window,document,"script","gtmDataLayer")</script><script>window.NEON_SERVER_DATA="__NEON_SERVER_DATA__"</script><link href="<?php echo $CLIENT_ROOT; ?>/neon-react/static/css/main.dfee6011.css" rel="stylesheet">
<!--end of neon react links-->

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Create biorepo-page div
        // A page must have the innertext div
        var biorepoPage = document.createElement("div");
        biorepoPage.id = "biorepo-page";
        
        var innerText = document.getElementById("innertext");
        if (innerText) {
            innerText.parentNode.insertBefore(biorepoPage, innerText);
        }
        
        //javascript code created by React
        var reactScript = document.createElement('script');
        reactScript.src = '<?php echo $CLIENT_ROOT; ?>/neon-react/static/js/main.1ea125cc.js';
        reactScript.defer = true;
        
        reactScript.onload = function() {
            // To move innertext into neon-page.content
            var innerTextDiv = document.getElementById('innertext');
            var targetDiv = document.querySelector('div[data-selenium="neon-page.content"]');
            targetDiv.appendChild(innerTextDiv);
            
            //remove old header, footer and breadcrumbs
            var navBar = document.getElementById("top_navbar");
            if (navBar) {
                navBar.parentNode.removeChild(navBar);
            }
            var mainHeader = document.getElementById("main-header");
            if (mainHeader) {
                mainHeader.parentNode.removeChild(mainHeader);
            }
            
            var mainFooter = document.getElementById("main-footer");
            if (mainFooter) {
                mainFooter.parentNode.removeChild(mainFooter);
            }
            
            var mainFooter = document.getElementById("main-footer");
            if (mainFooter) {
                mainFooter.parentNode.removeChild(mainFooter);
            }
            
            //document.querySelector('.navpath').remove();

            // Edit footer
            const footerMessageDiv = document.querySelector('.footer-bottom__message');
          
            const wrapperDiv = document.createElement('div');
            wrapperDiv.style.display = 'flex';
            wrapperDiv.style.justifyContent = 'space-between';
            wrapperDiv.style.width = '100%';
          
            const newParagraph1 = document.createElement('p');
          
            const textBeforeLink = document.createTextNode('Site powered by ');
            const symbiotaLink = document.createElement('a');
            symbiotaLink.href = 'https://symbiota.org/';
            symbiotaLink.textContent = 'Symbiota';
            const textAfterLink = document.createTextNode(' | ');
          
            const googleAnalytics = document.createElement('i');
            googleAnalytics.textContent = 'This site uses Google Analytics';
          
            newParagraph1.appendChild(textBeforeLink);
            newParagraph1.appendChild(symbiotaLink);
            newParagraph1.appendChild(textAfterLink);
            newParagraph1.appendChild(googleAnalytics);
    
            const reportDiv = document.createElement('div');
            reportDiv.style.display = 'flex';
            reportDiv.style.justifyContent = 'flex-end'; // Align to the right
            reportDiv.style.alignItems = 'center';
            reportDiv.style.flex = '1';
    
            const githubLink = document.createElement('a');
            githubLink.href = 'https://github.com/BioKIC/NEON-Biorepository/issues';
            githubLink.style.textDecoration = 'none';
            githubLink.style.color = 'inherit';
            githubLink.style.display = 'flex';
            githubLink.style.alignItems = 'center';
          
            const githubLogo = document.createElement('img');
            githubLogo.src = '/neon/portal/images/icons/github-mark-white.svg'; // Inverted GitHub logo URL
            githubLogo.alt = 'GitHub Logo';
            githubLogo.style.width = '16px';
            githubLogo.style.height = '16px';
            githubLogo.style.marginRight = '5px';
          
            const reportText = document.createTextNode('Report a problem');
          
            githubLink.appendChild(githubLogo);
            githubLink.appendChild(reportText);
          
            reportDiv.appendChild(githubLink);
          
            wrapperDiv.appendChild(newParagraph1);
            wrapperDiv.appendChild(reportDiv);
    
            footerMessageDiv.appendChild(wrapperDiv);
            
            //footer logos
            const footerLogoDiv = document.querySelector('.footer-top__logo');
            
            function createImage(src, height, width) {
                const img = document.createElement('img');
                img.src = src;
                img.height = height;
                img.width = width;
                return img;
            }
           
            const newImage1 = createImage('/neon/portal/images/layout/logo_symbiota.png', 60, 167);
            const newImage2 = createImage('/neon/portal/images/layout/logo-asu-biokic.jpg', 60, 167);
            
            footerLogoDiv.appendChild(newImage1);
            footerLogoDiv.appendChild(newImage2);
        };
    
    document.body.appendChild(reactScript);
   
    });
</script>
<!--end-->


<link href="<?php echo $CLIENT_ROOT; ?>/css/v202209/jquery-ui.css" type="text/css" rel="stylesheet">
<!-- UNIVERSAL CSS –––––––––––––––––––––––––––––––––––––––––––––––––– -->
<link rel="stylesheet" href="<?php echo $CLIENT_ROOT; ?>/css/normalize.css">
<!--<link rel="stylesheet" href="<?php echo $CLIENT_ROOT; ?>/css/skeleton.css">-->
<link rel="stylesheet" href="<?php echo $CLIENT_ROOT; ?>/css/base.css?ver=1" type="text/css">
<link rel="stylesheet" href="<?php echo $CLIENT_ROOT; ?>/css/neon.css?ver=3">
<!--<link rel="stylesheet" href="<?php echo $CLIENT_ROOT; ?>/css/main.css?ver=4" type="text/css">-->

<script type="text/javascript" src="<?php echo $CLIENT_ROOT; ?>/js/symb/lang.js"></script>
<script type="text/javascript">
	//Uncomment following line to support toggling of database content containing DIVs with lang classes in form of: <div class="lang en">Content in English</div><div class="lang es">Content in Spanish</div>
	//setLanguageDiv();
</script>
<?php
if ($USERNAME != 0) {
    echo "logged in as " . $USERNAME . ' <a href="http://localhost/neon/profile/index.php?submit=logout">Log out</a>';
} else {
    echo 'logged out <a href="http://localhost/neon/profile/index.php">Log in</a>';
}
?>
