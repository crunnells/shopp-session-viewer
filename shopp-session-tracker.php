<?php
/*
Plugin Name: Shopp Session Tracker
Plugin URI:
Description: Allows you to browse through Shopp's customer session data.
Version: 0.2.1
Author: Chris Runnells
Author URI: http://chrisrunnells.com
*/

/*
Version History:

0.2.1 - 8/2/16
Finally fixing display of cart items and errors in the list

0.2 - 3/3/14
Updating for Shopp 1.3

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
		add_action( 'admin_menu', array( $this, 'add_menu' ), 99);
	}

	function add_menu(){
		add_submenu_page( 'shopp-orders', 'Session Viewer', 'Session Viewer', 'manage_options', $this->sst_slug, array( $this, 'actions' ) );
		/*
		global $Shopp;
		$ShoppMenu = $Shopp->Flow->Admin->MainMenu; //this is our Shopp menu handle
		add_submenu_page($ShoppMenu, 'Session Tracker', 'Session Tracker',(defined('SHOPP_USERLEVEL') ? SHOPP_USERLEVEL : 'manage_options'), 'shopp-sessions', array($this, 'actions'));
		*/
	}

	function actions (){
		$action = !empty($_REQUEST['action']) ? $_REQUEST['action'] : "";

		if("view" == $action){
			$this->view_session($_REQUEST['session']);
		} else {
			$this->list_sessions();
		}

	}

	function list_sessions(){
		// global $Shopp;
		$shopping_table = ShoppDatabaseObject::tablename('shopping');
		// $db = DB::instance();
        // $prefix = $db->table_prefix;
		// $shopping_table = $prefix . "shopp_shopping";

		$query = "SELECT * FROM $shopping_table ORDER BY modified DESC";
		$results = sDB::query($query, 'array');

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
	background-color: #fff;
}
tr.you {
	background-color: #ff0;
}
tr.viewed td.viewed {
	background-color: #ccf;
	color: #006;
}
tr.errors td.errors {
	background-color: #fcc;
	color: #c00;
}
tr.cartitems td.cartitems {
	background-color: #cfc;
	color: #060;
}

td.cartitems,
td.errors,
td.viewed {
	text-align: center;
	font-weight: bold;
}
</style>
<h2>List Sessions</h2>
<table class="widefat">
  <thead>
    <tr>
      <th>Session ID</th>
      <th>Customer</th>
      <th>IP</th>
      <th>Viewed Items</th>
	  <th>Items in Cart</th>
      <th>Created</th>
      <th>Modified</th>
      <th>Errors</th>
	</tr>
  </thead>
  <tbody>
<?php

		// print_r($results);
		foreach ($results as $result) {

			$Session = unserialize($result->data);

			if (!empty($Session->ShoppCustomer->email) && !empty($Session->ShoppCustomer->firstname) && !empty($Session->ShoppCustomer->lastname)){
				$customer = '<a href="mailto:'.$Session->ShoppCustomer->email.'">'.$Session->ShoppCustomer->firstname.' '.$Session->ShoppCustomer->lastname.'</a>';
			} else {
				$customer = "";
			}

			$errors = count($Session->notices);
			$cartitems = $Session->ShoppCart->count();
			$viewed = count($Session->viewed);

			$class = array();

			if ($errors > 0){
				$class[] = "errors";
			}
			if ($cartitems > 0){
				$class[] = "cartitems";
			}
			if ($viewed > 0){
				$class[] = "viewed";
			}
			if ($_SERVER["REMOTE_ADDR"] == $result->ip) {
				$class[] = "you";
			}

			$classes = implode(" ", $class);
?>
    <tr class="<?php echo $classes; ?>">
		<td><a href="admin.php?page=<?php echo $this->sst_slug; ?>&action=view&session=<?php echo $result->session; ?>"><?php echo $result->session; ?></a></td>
		<td><?php echo $customer; ?></td>
		<td><?php echo $result->ip; ?></td>
		<td class="viewed"><?php echo $viewed; ?></td>
		<td class="cartitems"><?php echo $cartitems; ?></td>
		<td><?php echo $this->nicetime($result->created); ?></td>
		<td><?php echo $this->nicetime($result->modified); ?></td>
		<td class="errors"><?php echo $errors; ?></td>
    </tr>
<?php
		}

?>
  </tbody>
</table>
<?php
   } // end list_sessions();

   function view_session($session){

		// global $Shopp;
		$shopping_table = ShoppDatabaseObject::tablename('shopping');

		$query = "SELECT * FROM $shopping_table WHERE session = '$session'";
		$results = sDB::query($query);

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
<?php $this->errors($Session->notices); ?>

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
		      <td><?php echo $Session->ShoppCustomer->id; ?></td>
		      <td><?php echo $Session->ShoppCustomer->firstname ." ". $Session->ShoppCustomer->lastname; ?>
		      <td><?php echo $Session->ShoppCustomer->email; ?></td>
		    </tr>
		  </tbody>
		</table>
	</li>
	<li>
		<h3>Shipping Information</h3>
		<pre class="big"><?php print_r($Session->ShippingAddress); ?>
	</li>
	<li>
		<h3>Billing Information</h3>
		<pre class="big"><?php print_r($Session->BillingAddress); ?>
	</li>
	<li>
		<h3>Items in Cart</h3>
<?php

	$Cart = $Session->ShoppCart;
echo "<ul>";
	foreach($Cart as $id => $item){
		echo "<h4>". $item->name ."</h4>";
		echo '<li>'. $this->cart_items($item) . '</li>';
	}
echo "</ul>";
?>

	</li>
</ul>

<h2>Cart Promotions</h2>
<?php $this->promotions($Session->ShoppDiscounts); ?>

<h2>Worklist</h2>
<h2>Search</h2>
<h2>Browsing</h2>
<h2>Referrer</h2>
<h2>Viewed Items</h2>
<?php $this->viewed_items($Session->viewed); ?>

<h2>Shipping Rates</h2>
<pre class="big">
<?php print_r($Session->ShoppShiprates) ?>
</pre>

<h2>Raw Session Data</h2>
<pre>
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
<?php
foreach ($errors as $error) {
	echo $error;
}
?>
</pre>
<?php
	}

	function cart_items($output){
?>
<pre class="small">
<?php print_r($output); ?>
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

	    if ( empty( $date ) ) {
	        return "No date provided";
	    }

	    $periods = array("second", "minute", "hour", "day", "week", "month", "year", "decade");
	    $lengths = array("60","60","24","7","4.35","12","10");

		// date_default_timezone_set('UTC');
		// $now = time();
		$offset = get_option( 'gmt_offset' ) * 3600;
		$now = time() + $offset;
		$unix_date = strtotime($date);

		// check validity of date
		if(empty($unix_date)) {
			return "Bad date";
		}

	    // is it future date or past date
		if ( $now > $unix_date ) {
	        $difference = $now - $unix_date;
	        $tense = "ago";

	    } else {
	        $difference = $unix_date - $now;
	        $tense = "from now";
	    }

	    for ( $j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++ ) {
	        $difference /= $lengths[$j];
	    }

	    $difference = round($difference);

	    if($difference != 1) {
	        $periods[$j].= "s";
	    }

	    return "$difference $periods[$j] {$tense}";
	}

} // end Class


