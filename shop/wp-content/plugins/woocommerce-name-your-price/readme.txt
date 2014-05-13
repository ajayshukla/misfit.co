=== WooCommerce Name Your Price ===

The WooCommerce Name Your Price extension lets you be flexible in what price you are willing to accept for selected products. You can use this extension to accept user-determined donations, gather pricing data or to take a new approach to selling products!  You can *suggest* a price to your customers and optionally enforce a minimum acceptable price, but otherwise this extension allows the customer to enter the price he's willing to pay.

[screenshot1.png]

== Installation ==

To install Name Your Price:

1. Download the extension from your dashboard

2. Unzip and upload the 'woocommerce-name-your-price' folder to your site's '/wp-content/plugins/' directory

3. Activate the 'WooCommerce Name Your Price' extension through the 'Plugins' menu in WordPress

== Upgrading to Name Your Price 2.0 ==

Please be advised that Name Your Price 2.0 is a very major update and if you were previously using Name Your Price 1.x you might want to consider testing your theme in a developmental environment first. This is a must if you were using the $wc_name_your_price variable or overriding old templates.  

You will also want to double-check the suggested and minimum price strings. 

Name Your Price will continue to work with WooCommerce 2.0.20, but WooCommerce 2.1 is preferred. Support for variable products and variable subscriptions will not function until WooCommerce is upgraded to version 2.1. WooCommerce 2.1 also better handles entering prices with localized decimal points. 

== Plugin Settings ==

Name Your Price has several strings that can be modified from the plugin's settings. Go to WooCommerce->Settings and and click on the Name Your Price tab. From here you can modify the add to cart button texts, the minimum, "From:" and suggested text strings. 

[screenshot3.png]

== How to Use ==

= How to Use With Simple Products = 

[screenshot2.png]

To enable flexible, user-determined pricing on any simple product:

1. Edit a product and look for the 'Name Your Price' checkbox in the Product Data metabox. Simple, subscription and bundle products will support customers naming their own price. 

2. Tick the checkbox to allow users to set their own price for this product.  The suggested and minimum price fields will not be visible until this is checked.  Note that this might not function properly if you have javascript disabled. 

3. Fill in the suggested and minimum prices as desired. The minimum price prevents products from being sold for less than you are willing to accept. 

4. *Important* - When entering prices, do NOT enter thousands seperators! As of WooCommerce 2.1 you will be able to enter prices with the decimal point of your region, as long as it is the same one as you have specified in the settings.

5. To enforce a minimum price (ie. to not sell your product for less than a specified amount), enter this minimum acceptable price in the Minimum Price input. To not enforce a minimum, simply leave this field empty 

6. To *not* display a suggested price, you can simply leave the suggested field blank

7. Save the product.  When viewing the product on the front-end, the suggested and minimum prices will be displayed in place of the regular price and a text input will appear above the Add to Cart Button where the customer can enter what she is willing to pay.  

= How to Use With Subscripton Products = 

Subscription products work similarly to simple products. If you check the Name Your Price box, the subscription price box will be disabled in favor the suggested and minimum price inputs. The subscription billing period inputs remain unchanged. 

[screenshot4.png]

As of Name Your Price 2.0, you can now also offer a variable billing period, which allows the customer to decide if she'd like to pay per month or per day, etc. To enable this, you need to check the "Variable Billing Period" checkbox. With this checked, if you enter suggested or minimum prices, you must select a corresponding suggested or minimum billing period. 

[screenshot5.png]

= How to Use With Variable Products = 

As of Name Your Price 2.0, you can now have name your price variations on variable or variable Subscriptions products. Within each variation look for a "Name Your Price" checkbox and follow the same rules as simple products for suggested and minimum prices for each variation.

[screenshot6.png]

On the front-end when a Name Your Price variation is selected, the Name Your Price price input will appear. 

[screenshot7.png]

== Settings ==

You can change any of the front-end "strings", meaning the phrases for "Name Your Price", "Suggested Price", "Minimum Price" and the button text "Set Price". Go to WooCommerce > Settings and click on the Name Your Price Tab

[screenshot3.png]

From the settings, you can also disable the Name Your Price stylesheet as well as hide the phrasing "Free" when products have no set minimum.

== Updating from Name Your Price 1.+ to Name Your Price 2.0 ==

Please note that as of **Name Your Price 2.0**, the templates have **radically** changed, how you access the Name Your Price functions has changed, as well as the structure of the plugin. If you were overriding the templates, moving the markup around, or unloading the stylesheet you will need to update your code. 

If you were accessing the global variable `$wc_name_your_price` that still exists, but is being deprecated. You can access the class instance in the following manner:

```
if( function_exists( 'WC_Name_Your_Price' ) ){
	$wc_nyp = WC_Name_Your_Price();
}
```

The functions that are primarily concerned with this coding change (those involving the price input and minimum price) are now in the `WC_Name_Your_Price_Display` class, which can be accessed through the main class like so:

```
WC_Name_Your_Price()->display
``` 

Please see the example code in the FAQ for how to update your code if you having been moving the functions around to different action hooks. 

== FAQ ==

= How do I change the markup? =

Similar to WooCommerce, the Name Your Price extension uses a small template part to generate the markup for the price input. For example, you can use your own price input template by placing a price-input.php file inside the /woocommerce/single-product folder of your theme. However, much of what makes the Name Your Price component function is wrapped up in helper functions in that template, so only edit this if you really know what you are doing.

= How can I move the markup? = 

The suggested price & minimum price are displayed in place of the regular price, which is attached to the WooCommerce 'woocommerce_single_product_summary' action hook, while the text input is attached to the 'woocommerce_before_add_to_cart_button' hook. Following typical WordPress behavior for hooks, to change the location of any of these templates you must remove them from their default hook and add them to a new hook.  For example, to relocate the price input place the following in your theme's functions.php and be sure to adjust 'the_hook_you_want_to_add_to' with a real hook name.

Please note that as of **Name Your Price 2.0** how you access the Name Your Price functions has changed. If you were moving the markup you will need to update your code. 

**Name Your Price 2.0**

```
function nyp_move_price_input(){
	if( function_exists( 'WC_Name_Your_Price' ) ){
		$wc_name_your_price_display = WC_Name_Your_Price()->display;
		remove_action( 'woocommerce_before_add_to_cart_button', array( $wc_name_your_price_display, 'display_price_input') );
		add_action( 'the_hook_you_want_to_add_to', array( $wc_name_your_price_display, 'display_price_input' ) );
	}
}
add_action( 'woocommerce_before_main_content' , 'nyp_move_price_input' );
```

**Name Your Price 1.+**

```
function nyp_move_price_input(){ 
	global $wc_name_your_price;

	remove_action( 'woocommerce_before_add_to_cart_button', array( $wc_name_your_price, 'display_price_input') );
	add_action( 'woocommerce_after_main_content', array( $wc_name_your_price, 'display_price_input' ) );
}
add_action( 'woocommerce_before_single_product' , 'nyp_move_price_input' );
```
