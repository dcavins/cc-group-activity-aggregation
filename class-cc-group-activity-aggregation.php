<?php
/**
 * BP_Groups_Hierarchy_Activity_Aggregation
 *
 * @package CC Group Activity Aggregation
 * @author    David Cavins
 * @license   GPL-2.0+
 * @copyright 2014 Community Commons
 */
/*
--------------------------------------------------------------------------------
BP_Groups_Hierarchy_Activity_Aggregation Class
--------------------------------------------------------------------------------
*/

class BP_Groups_Hierarchy_Activity_Aggregation {

	/**
	* properties
	*/
	// array of subgroups is built during recursion
	public $subgroup_ids = array();

	/**
	 * Instance of this class.
	 *
	 * @since    0.1.0
	 *
	 * @var      object
	 */
	protected static $instance = null;


	/**
	* @description: initialises this object
	* @return object
	*/
	function __construct() {

		// use translation
		// add_action( 'plugins_loaded', array( $this, 'translation' ) );

		// Add a meta box to the group's "admin>settings" tab.
   		// We're also using BP_Group_Extension's admin_screen method to add this meta box to the WP-admin group edit
        // add_filter( 'groups_custom_group_fields_editable', array( $this, 'meta_form_markup' ) );
   		// This is a hook I've added in the general "CC Group Meta" plugin
        add_action( 'cc_group_meta_details_form_before_channels', array( $this, 'meta_form_markup' ) );

        // Catch the saving of the meta form, fired when create>settings pane is saved or admin>settings is saved 
		// add_action( 'groups_group_details_edited', array( $this, 'meta_form_save') );
		// add_action( 'groups_created_group', array( $this, 'meta_form_save' ) );
		// This is a hook I've added in the general "CC Group Meta" plugin
		add_action( 'cc_group_meta_details_form_save', array( $this, 'meta_form_save' ) );

		add_filter( 'bp_ajax_querystring', array( $this, 'activity_aggregation' ), 90, 2 );


		// --<
		return $this;

	}

	/**
	* @description: loads translation, if present
	* @todo:
	*
	*/
	function translation() {

		// only use, if we have it...
		if( function_exists('load_plugin_textdomain') ) {

			// enable translations
			load_plugin_textdomain(

			// unique name
			'bp-group-hierarchy-propagate',

			// deprecated argument
			false,

			// relative path to directory containing translation files
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'

		);

		}

	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     0.1.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 *  Renders extra fields on form when creating a group and when editing group details
	 * 	Used by BP_Groups_Hierarchy_Activity_Aggregation_Group_Extension::admin_screen()
  	 *  @param  	int $group_id
	 *  @return 	string html markup
	 *  @since    	0.1.0
	 */
	public function meta_form_markup( $group_id = 0 ) {
		if ( ! current_user_can( 'delete_others_pages' ) )
			return;

		$group_id = $group_id ? $group_id : bp_get_current_group_id();
		?>		
			<p><label for="group_use_aggregated_activity"><input type="checkbox" id="group_use_aggregated_activity" name="group_use_aggregated_activity" <?php checked( groups_get_groupmeta( $group_id, 'group_use_aggregated_activity' ), 1 ); ?> /> Include child group activity in this group&rsquo;s activity stream.</label></p>
	<?php 

	}

	/**
	 *  Saves the input from our extra meta fields
 	 * 	Used by BP_Groups_Hierarchy_Activity_Aggregation_Group_Extension::admin_screen_save()
 	 *  @param  	int $group_id
	 *  @return 	void
	 *  @since    	0.1.0
	 */
	public function meta_form_save( $group_id = 0 ) {
		$group_id = $group_id ? $group_id : bp_get_current_group_id();

		$meta = array(
			// Checkboxes
			'group_use_aggregated_activity' => isset( $_POST['group_use_aggregated_activity'] ),
		);

		foreach ( $meta as $meta_key => $new_meta_value ) {

			/* Get the meta value of the custom field key. */
			$meta_value = groups_get_groupmeta( $group_id, $meta_key, true );

			/* If there is no new meta value but an old value exists, delete it. */
			if ( '' == $new_meta_value && $meta_value )
				groups_delete_groupmeta( $group_id, $meta_key, $meta_value );

			/* If a new meta value was added and there was no previous value, add it. */
			elseif ( $new_meta_value && '' == $meta_value )
				groups_add_groupmeta( $group_id, $meta_key, $new_meta_value, true );

			/* If the new meta value does not match the old value, update it. */
			elseif ( $new_meta_value && $new_meta_value != $meta_value )
				groups_update_groupmeta( $group_id, $meta_key, $new_meta_value );
		}

	}

	/**
	 * Filter bp_ajax_querystring to add subgroups of current group that user has access to.
	 *
	 * @since     0.1.0
	 *
	 * @return    string of group ids to include.
	 */
	function activity_aggregation( $query_string, $object ) {

		//Check to see that we're in the BuddyPress groups component, not the member stream or other. Also check that this is an activity request, not the group directory. Else, stop the process.
		if ( ! bp_is_group() || ( $object != 'activity' ) )
			return $query_string;

	    //Get the group id and the user id
	    $group_id = bp_get_current_group_id();

	    //Check if this group is set to aggregate child group activity. Else, stop the process.
	    if ( 1 != groups_get_groupmeta( $group_id, 'group_use_aggregated_activity' ) )
	    	return $query_string;

    	$children = $this->_get_children( $group_id, bp_loggedin_user_id() );
 
	    //Finally, append the result to the query string. This works because bp_has_activities() allows a comma-separated list of ids as the primary_id argument.
	    if ( !empty( $children ) ) {
		    $query_string .= '&primary_id=' . implode( ',', $children );
		}

		return $query_string;
	}

	/**
	* @description: build a list of child group IDs (includes current group ID)
	* I've adopted Christian Wach's code for this piece, it was much more clever than mine.
	* https://github.com/christianwach/bp-group-hierarchy-propagate
	* @param integer $group_id
	* @return array $subgroup_ids
	*/
	function _get_children( $group_id, $user_id ) {

		// Add this group's id to the array
		$this->subgroup_ids[] = $group_id;

		// get children from BP Group Hierarchy
		$children = bp_group_hierarchy_get_by_hierarchy( array( 'parent_id' => $group_id ) );

		// did we get any?
		if ( isset( $children['groups'] ) && count( $children['groups'] ) > 0 ) {

			// check them
			foreach( $children['groups'] as $child ) {

				// is the user allowed to see content from this group?
				if ( 	'public' == $child->status || // Anyone can see this group's activity
						groups_is_user_member( $user_id, $group_id ) || // The user has access to the group's activity
						current_user_can( 'delete_others_pages' ) // Site admin can see everything
				) {

				// recurse down the group hierarchy
				$this->_get_children( $child->id, $user_id );

				}

			}

		}

		// --<
		return $this->subgroup_ids;
	}

} // end class BP_Groups_Hierarchy_Activity_Aggregation