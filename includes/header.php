<table id="maintable" cellspacing="0">
	<tr>
		<td id="header" colspan="3" style="background-image:url(<?php echo $CLIENT_ROOT; ?>/images/layout/background_poppy.jpg)">
			<div style="clear:both; width:100%; height:170px; border-bottom:1px solid #000000;">
				<div style="float:left">
					<img src="<?php echo $CLIENT_ROOT; ?>/images/layout/CCH_logo_filled.png" style="margin:0px 30px;width:130px" />
				</div>
				<div style="margin-left: 40px; color: #fff; font-family: 'Mate', serif; letter-spacing: 1px; text-shadow: 0 0 7px rgba(0,0,0,0.5);">
					<div style="margin-top:30px; font-size:60px; line-height:48px;">
						CCH2
					</div>
					<div style="margin-top:20px; font-size:35px; font-style: italic">
						Featuring Data From the California Phenology TCN
					</div>
				</div>
			</div>
			<div id="top_navbar">
				<div id="right_navbarlinks">
					<?php
					if($USER_DISPLAY_NAME){
					?>
						<span style="">
							Welcome <?php echo $USER_DISPLAY_NAME; ?>!
						</span>
						<span style="margin-left:5px;">
							<a href="<?php echo $CLIENT_ROOT; ?>/profile/viewprofile.php">My Profile</a>
						</span>
						<span style="margin-left:5px;">
							<a href="<?php echo $CLIENT_ROOT; ?>/profile/index.php?submit=logout">Logout</a>
						</span>
					<?php
					}
					else{
					?>
						<span style="">
							<a href="<?php echo $CLIENT_ROOT."/profile/index.php?refurl=".$_SERVER['PHP_SELF']."?".$_SERVER['QUERY_STRING']; ?>">
								Log In
							</a>
						</span>
						<span style="margin-left:5px;">
							<a href="<?php echo $CLIENT_ROOT; ?>/profile/newprofile.php">
								New Account
							</a>
						</span>
					<?php
					}
					?>
					<span style="margin-left:5px;margin-right:5px;">
						<a href='<?php echo $CLIENT_ROOT; ?>/sitemap.php'>Sitemap</a>
					</span>

				</div>
				<ul id="hor_dropdown">
					<li>
						<a href="<?php echo $CLIENT_ROOT; ?>/index.php" >Home</a>
					</li>
					<li>
						<a href="<?php echo $CLIENT_ROOT; ?>/collections/index.php" >Search Collections</a>
					</li>
					<li>
						<a href="<?php echo $CLIENT_ROOT; ?>/collections/map/index.php" target="_blank">Map Search</a>
					</li>
					<li>
						<a href="<?php echo $CLIENT_ROOT; ?>/projects/index.php">Checklists</a>
						<ul>
							<li>
								<a href="<?php echo $CLIENT_ROOT; ?>/projects/index.php?pid=3" >California</a>
							</li>
							<li>
								<a href="<?php echo $CLIENT_ROOT; ?>/projects/index.php?pid=1" >National Parks</a>
							</li>
							<li>
								<a href="<?php echo $CLIENT_ROOT; ?>/projects/index.php?pid=2" >UC Reserve Systems</a>
							</li>
						</ul>
					</li>
					<li>
						<a href="<?php echo $CLIENT_ROOT; ?>/imagelib/search.php" >Image Search</a>
					</li>
					<li>
						<a href="<?php echo $CLIENT_ROOT; ?>/imagelib/index.php" >Browse Images</a>
					</li>
					<li>
						<a href="<?php echo $CLIENT_ROOT; ?>/misc/usagepolicy.php">Data Use Policy</a>
					</li>
					<li>
						<a href="http://ucjeps.berkeley.edu/consortium/about.html" target="_blank">About CCH</a>
					</li>
				</ul>
			</div>
		</td>
	</tr>
    <tr>
		<td id='middlecenter'  colspan="3">


