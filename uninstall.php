<?php

	// If uninstall not called from WordPress exit

	if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	   
		exit();
	
	}
	
	// Delete settings page options from options table
	series_log("uninstall plugin and delete options");
	delete_option( SERIES . '_options' );
	
?>