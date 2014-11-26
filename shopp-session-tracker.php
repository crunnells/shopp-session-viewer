<?php
/*
Plugin Name: Shopp Session Tracker
Plugin URI: 
Description: Allows you to browse through Shopp's customer session data.
Version: 0.1
Author: Chris Runnells
Author URI: http://chrisrunnells.com
*/

/*
Version History:

0.1 - 5/1/12
First version release.

TODO:

- break sessions into more readable data
- add ability to clear session
- show human readable dates next to session links

*/

new ShoppSessionTracker();

class ShoppSessionTracker {

	var $sst_slug = "shopp-session-tracker";

	function __construct(){
		// if ( !class_exists('Shopp') ) return;

		add_action('admin_menu', array(&$this, 'add_menu'), 99);
	}

	function add_menu(){

/*
		if ( version_compare( 'SHOPP_VERSION' , '1.3' , '>=' ) ) {
			add_submenu_page('shopp-orders','Session Viewer','Session Viewer','manage_options',$this->sst_slug,array(&$this, 'actions'));
		} else {
*/
			add_submenu_page( 'shopp-orders', 'Session Viewer', 'Session Viewer', 'manage_options', $this->sst_slug, array(&$this, 'actions'));
//		}

	}

	function actions (){
	
		if($_REQUEST['action'] == "view"){
			$this->view_session($_REQUEST['session']);
		} else {
			$this->list_sessions();
		}

	}

	function list_sessions(){
		global $wpdb;
		$db = DB::get();
		$shopping_table = DatabaseObject::tablename('shopping');
	   
		$query = "SELECT * FROM $shopping_table ORDER BY modified DESC";
		$results = $wpdb->get_results($query);

?>
<style>
table {
	border-collapse: collapse;
}
td {
	padding: 4px;
}
th {
	border-bottom: 1px solid #999;
	padding: 2px 4px;
	text-align: left;

}
tr:hover {
	background-color: #eee;
}
tr.you {
	background-color: #ff0;
}
</style>
<h2>List Sessions</h2>
<table>
  <thead>
    <tr>
      <th>Session ID</th>
      <th>Customer</th>
      <th>IP</th>
	  <th>Items in Cart</th>
      <th>Created</th>
      <th>Modified</th>
	</tr>
  </thead>
  <tbody>
<?php

		// print_r($results);
		foreach ($results as $result) {

			$Session = unserialize($result->data);

			if (!empty($Session->Order->Customer->email) && !empty($Session->Order->Customer->firstname) && !empty($Session->Order->Customer->lastname)){
				$customer = '<a href="mailto:'.$Session->Order->Customer->email.'">'.$Session->Order->Customer->firstname.' '.$Session->Order->Customer->lastname.'</a>';
			} else {
				$customer = "";
			}

			$cartitems = is_array($Session->Order->Cart->contents) ? count($Session->Order->Cart->contents) : 0;
			
			$class = ($_SERVER["REMOTE_ADDR"] == $result->ip) ? ' class="you"' : '';
?>
    <tr<?php echo $class; ?>>
		<td><a href="admin.php?page=<?php echo $this->sst_slug; ?>&action=view&session=<?php echo $result->session; ?>"><?php echo $result->session; ?></a></td>
		<td><?php echo $customer; ?></td>
		<td><?php echo $result->ip; ?></td>
		<td><?php echo $cartitems; ?></td>
		<td><?php echo $this->nicetime($result->created); ?></td>
		<td><?php echo $this->nicetime($result->modified); ?></td>
    </tr>
<?php 
		}

?>
  </tbody>
</table>
<?php
   } // end list_sessions();
   
   function view_session($session){

		global $Shopp;
		$db = DB::get();
		$shopping_table = DatabaseObject::tablename('shopping');

		$query = "SELECT * FROM $shopping_table WHERE session = '$session'";
		$results = $db->query($query);
		
		$Session = unserialize($results->data);

?>
<style type="text/css">
.big {
	width:90%; 
	height:400px; 
	overflow:scroll; 
	border:1px solid #ccc;
}
.small {
	width:90%; 
	height:200px; 
	overflow:scroll; 
	border:1px solid #ccc;	
}
</style>
<h1>Session ID: <?php echo $session; ?></h1>

<h2>Errors</h2>
<?php $this->errors($Session->errors); ?>

<h2>Order</h2>
<ul>
	<li>
		<h3>Customer Object</h3>
		<table>
		  <thead>
		    <tr>
		      <th>ID</th>
		      <th>Name</th>
		      <th>Email</th>
		    </tr>
		  </thead>
		  <tbody>
		    <tr>
		      <td><?php echo $Session->Order->Customer->id; ?></td>
		      <td><?php echo $Session->Order->Customer->firstname ." ". $Session->Order->Customer->lastname; ?>
		      <td><?php echo $Session->Order->Customer->email; ?></td>
		    </tr>
		  </tbody>
		</table>
	</li>
	<li>Shipping Information</li>
	<li>Billing Information</li>
	<li>
		<h3>Items in Cart</h3>
<?php 

	$Cart = $Session->Order->Cart;
echo "<ul>";
	foreach($Cart->contents as $item){
		echo "<h4>". $item->name ."</h4>";
		echo '<li>'. $this->cart_items($item) . '</li>';
	}
echo "</ul>";
?>
		
	</li>
</ul>

<h2>Cart Promotions</h2>
<?php $this->promotions($Session->CartPromotions->promotions); ?>

<h2>Worklist</h2>
<h2>Search</h2>
<h2>Browsing</h2>
<h2>Referrer</h2>
<h2>Viewed Items</h2>
<?php $this->viewed_items($Session->viewed); ?>

<h2>Raw Session Data</h2>
<pre class="big">
<?php print_r(unserialize($results->data)); ?>
</pre>
<?php 
   
	}

	function promotions($promotions){
		echo "<p>" . count($promotions) . " promotions applied to this session.</p>";

?>
<pre class="big">
<?php print_r($promotions); ?>
</pre>			
<?php
	}

	function errors($errors){
?>
<pre class="big">
<?php print_r($errors); ?>
</pre>			
<?php	
	}

	function cart_items($output){
?>
<pre class="big">
<?php print_r($output); ?>
</pre>

<strong>Data</strong>
<pre class="small">
<?php print_r($output->data); ?>
</pre>

<?php	
	}

	function customer($customer){
?>
<pre class="big">
<?php print_r($customer); ?>
</pre>
<?php
	}

	function viewed_items($viewed) {
		foreach	($viewed as $view){
			$Product = shopp_product($view);
			echo $Product->name . "<br>";
		}
	}

	function nicetime($date){

	    if(empty($date)) {
	        return "No date provided";
	    }

	    $periods = array("second", "minute", "hour", "day", "week", "month", "year", "decade");
	    $lengths = array("60","60","24","7","4.35","12","10");

		$offset = get_option( 'gmt_offset' ) * 3600;
		$now = time() + $offset;
		$unix_date = strtotime($date);

		// check validity of date
		if(empty($unix_date)) {   
			return "Bad date";
		}

	    // is it future date or past date
		if($now > $unix_date) {   
	        $difference = $now - $unix_date;
	        $tense = "ago";

	    } else {
	        $difference = $unix_date - $now;
	        $tense = "from now";
	    }

	    for($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++) {
	        $difference /= $lengths[$j];
	    }

	    $difference = round($difference);

	    if($difference != 1) {
	        $periods[$j].= "s";
	    }

	    return "$difference $periods[$j] {$tense}";
	}

} // end Class
