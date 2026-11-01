<?php
$uba_test_page_id = (int) getenv( 'UBA_TEST_PAGE_ID' );
if ( 'draft' !== get_post_status( $uba_test_page_id ) ) {
	exit( "ABORT: set UBA_TEST_PAGE_ID to the ID of an empty draft page.\n" );
}
define( 'PAGE_ID', $uba_test_page_id );
