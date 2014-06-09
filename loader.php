<?php
/*
Plugin Name: CC Group Activity Aggregation
Description: Allows child group activity items to be syndicated to parent group
Version: 0.1.0
Requires at least: 3.3
Tested up to: 3.5
License: GPL3
Author: David Cavins
*/
// Contents:
// 	1. Aggregate group activity streams 
//////////////////////
//////////////////////

/**
 * CC Group Activity Aggregation
 *
 * @package 	CC Group Activity Aggregation
 * @author    	David Cavins
 * @license   	GPL-2.0+
 * @copyright 	2014 Community Commons
 */

// set our version here
define( 'BPGH_ACTIVITY_AGGREGATION_VERSION', '0.1.0' );

/**
 * Creates instance of BP_Groups_Hierarchy_Activity_Aggregation
 * This is where most of the running gears are.
 *
 * @package CC Group Activity Aggregation
 * @since 0.1.0
 */
function bpgh_activity_aggregation_class_init(){
	// Get the class fired up
	if ( defined( 'BP_GROUP_HIERARCHY_IS_INSTALLED' ) ){
		require( dirname( __FILE__ ) . '/class-cc-group-activity-aggregation.php' );
		add_action( 'bp_include', array( 'BP_Groups_Hierarchy_Activity_Aggregation', 'get_instance' ), 31 );
	}
}
add_action( 'bp_include', 'bpgh_activity_aggregation_class_init' );