<div class="wrap">
    <h4>Order Details:</h4>
    <a class="button-secondary" href="?page=wpwa_manage_orders&sub_page=action_needed" title="<?php esc_attr_e( 'Action Needed' ); ?>"><?php esc_attr_e( 'Action Needed' ); ?></a>
    <?php 
    $payment_id = isset($_GET['payment_id']) ? $_GET['payment_id']: false;
    $action = isset($_GET['action']) ? $_GET['action']: false;

    if ($payment_id != false && $action !=false && $action != 'delete') {
        if($action == 'notified'){
          $gross_amount = isset($_GET['gross_amount']) ? $_GET['gross_amount']: '';
          $net_amount = isset($_GET['net_amount']) ? $_GET['net_amount']: '';
          $payable_amount = isset($_GET['payable_amount']) ? $_GET['payable_amount']: '';
          $access_token = isset($_GET['access_token']) ? $_GET['access_token']: '';
          $app_name = isset($_GET['app_name']) ? $_GET['app_name']: '';
		    //echo $gross_amount;
			//echo '<br>';
			//echo $payable_amount;
			//echo '<br>';
			//echo $access_token;
          if($gross_amount != '' && $payable_amount != '' && $access_token != ''){
              $curl = curl_init();
              curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.weebly.com/v1/admin/app/payment_notifications",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "{\r\n\"name\": \" ". $app_name ." Install Fee\",\r\n\"method\": \"purchase\",\r\n\"kind\": \"single\",\r\n\"term\": \"forever\", \r\n\"gross_amount\": ".$gross_amount.", \r\n\"payable_amount\": ".$payable_amount.", \r\n\"currency\": \"USD\"\r\n}",
                CURLOPT_HTTPHEADER => array(
                  "cache-control: no-cache",
                  "x-weebly-access-token: " . $access_token
                ),
              ));

              $response = curl_exec($curl);
              $err = curl_error($curl);
              $responseInfo = curl_getinfo($curl);
              $httpResponseCode = $responseInfo['http_code'];

              //echo $httpResponseCode;
              if ($httpResponseCode == '403') {
                echo "Unknown api key provided. I guess the payment notification is already submitted";
              }else if($httpResponseCode == '400'){
                  echo $response;
              }else if($httpResponseCode == '200'){
                echo $response;
                if(get_post_type($payment_id) == 'shop_order'){
                	update_post_meta( $payment_id, 'weebly_notification','notified' );
                    echo 'Record is updated';
                }else{
                	update_post_meta( $payment_id, 'wpwa_order_payment_notif', sanitize_text_field($action) );
                    update_post_meta( $payment_id, 'wpwa_order_not_status', 'submitted' );
                    echo 'Record is updated';
                }
                
              }
              curl_close($curl);    
          }
      }else if($action == 'submitted'){
          update_post_meta( $payment_id, 'wpwa_order_not_status', sanitize_text_field($action) );
          echo 'Record is updated';
      }else{
      	if(get_post_type($payment_id) == 'shop_order'){
        	update_post_meta( $payment_id, 'weebly_notification', sanitize_text_field($action) );
          	echo 'Record is updated';
        }else{
        	update_post_meta( $payment_id, 'wpwa_order_payment_notif', sanitize_text_field($action) );
          	echo 'Record is updated';
        }
          
      }
    }else if ($payment_id != false && $action !=false && $action == 'delete'){
        $user_id = isset($_GET['user_id']) ? $_GET['user_id']: '';
        $site_id = isset($_GET['site_id']) ? $_GET['site_id']: '';
        $app_id = isset($_GET['app_id']) ? $_GET['app_id']: '';
        $access_token = isset($_GET['access_token']) ? $_GET['access_token']: '';
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://api.weebly.com/v1/user/sites/".$site_id."/apps/".$app_id."/deauthorize",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => "{\r\n\"site_id\": \"".$site_id."\",\r\n\"platform_app_id\": \"".$app_id."\"\r\n}",
          CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "x-weebly-access-token: " . $access_token
          ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $responseInfo = curl_getinfo($curl);
        $httpResponseCode = $responseInfo['http_code'];

        //echo $httpResponseCode;
        if ($httpResponseCode == '403') {
          echo "Unknown api key provided. I guess the app is already disconnected";
          wp_delete_post( $payment_id);
            echo '<p class="alert alert-success">Record is deleted successfully</p>';
          
        }else if($httpResponseCode == '400'){
            echo $response;
        }else if($httpResponseCode == '200'){
          echo $response;
          wp_delete_post( $payment_id);
          echo '<p class="alert alert-success">Record is deleted successfully</p>';
        }
        curl_close($curl);
    }else if ($payment_id != false && $action !=false && $action == 'remove_access'){
        $user_id = isset($_GET['user_id']) ? $_GET['user_id']: '';
        $site_id = isset($_GET['site_id']) ? $_GET['site_id']: '';
        $app_id = isset($_GET['app_id']) ? $_GET['app_id']: '';
        $access_token = isset($_GET['access_token']) ? $_GET['access_token']: '';
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://api.weebly.com/v1/user/sites/".$site_id."/apps/".$app_id."/deauthorize",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => "{\r\n\"site_id\": \"".$site_id."\",\r\n\"platform_app_id\": \"".$app_id."\"\r\n}",
          CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "x-weebly-access-token: " . $access_token
          ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $responseInfo = curl_getinfo($curl);
        $httpResponseCode = $responseInfo['http_code'];

        //echo $httpResponseCode;
        if ($httpResponseCode == '403') {
          echo "Unknown api key provided. I guess the app is already disconnected";
        }else if($httpResponseCode == '400'){
            echo $response;
        }else if($httpResponseCode == '200'){
          echo $response;
          update_post_meta( $payment_id, 'weebly_notification', 'access_removed' );
          	echo 'Record is updated';
          echo '<p class="alert alert-success">Access removed successfully</p>';
        }
        curl_close($curl);
    }
    if(isset($_GET['sub_page']) && $_GET['sub_page']=='action_needed'){
    ?>
      <h2 id="action-needed">Action needed for following Woocommerce orders</h2>
      <div class="table-responsive">
        <table class="widefat">
          <thead>
            <tr>
              <th class="row-title">ID</th>
              <th class="row-title">Product Name</th>
              <th class="row-title">Payer Email</th>
              <th class="row-title">Amount</th>
              <th class="row-title">Notif Status</th>
            </tr>
          </thead>
          <tbody>
          <?php
			$query = new WC_Order_Query( array(
                'limit' => 100,
				'type' => 'shop_order',
                'orderby' => 'date',
                'order' => 'DESC',
				'status' => 'wc-completed'
            ) );
            $orders = $query->get_orders();
            
            foreach($orders as $order){
            	//var_dump($order->get_id());
                $items = $order->get_items();
                foreach ( $items as $item ) {
                	$product_name = $item->get_name();
                    $product_id = $item->get_product_id();
                    if( $item->get_meta('access_token') ){
                        $access_token = $item->get_meta('access_token');  
                    }
                    if( $item->get_meta('site_id') ){
                        $site_id = $item->get_meta('site_id');  
                    }
                    if( $item->get_meta('user_id') ){
                        $user_id = $item->get_meta('user_id');  
                    }
                }
				$gross_amount = $order->get_total() - $order->get_total_tax();
				$fee = ((2.9/100)*$gross_amount)+0.52;
				//var_dump($fee);
				$net_amount = $gross_amount - $fee;
				$weebly_amount = (30/100)*$net_amount;
				echo "<tr><td class='row-title'> " . $order->get_id(). " </td>
						<td class='row-title'>"; 
				echo $product_name.'<br>';
				echo " 
						<div>
						<a href='?page=wpwa_manage_orders&action=notified&gross_amount=".$gross_amount."&net_amount=".$net_amount."&payable_amount=".$weebly_amount."&access_token=".$access_token."&app_name=".$product_name."&payment_id=".$order->get_id()."'>Notified</a>
						<a href='?page=wpwa_manage_orders&sub_page=action_needed&action=completed&payment_id=".$order->get_id()."'>Completed</a>
						<a href='".get_edit_post_link($order->get_id())."'>View/Edit</a>
						<a href='?page=wpwa_manage_orders&action=for-testing&payment_id=".$order->get_id()."'>For Testing</a>
						<a href='?page=wpwa_manage_orders&action=refunded&payment_id=".$order->get_id()."'>Refunded</a>
						<a href='?page=wpwa_manage_orders&action=remove_access&payment_id=".$order->get_id()."&site_id=".$site_id."&user_id=".$user_id."&app_id=".$product_id."&access_token=".$access_token."'>Remove Access</a>
						</div></td>
						<td class='row-title'> ".$order->get_billing_email()." </td>
						<td class='row-title'> ".$order->get_formatted_order_total()." </td>
						<td class='row-title'> ".$order->get_meta('weebly_notification')." </td>";
				
            }
            
          ?>
          </tbody>
        </table>
      </div>
    <?php
    }else{
    ?>
      <h2 id="action-needed">Action needed for following Woocommerce orders</h2>
      <div class="table-responsive">
        <table class="widefat">
          <thead>
            <tr>
              <th class="row-title">ID</th>
              <th class="row-title">Product Name</th>
              <th class="row-title">Payer Email</th>
              <th class="row-title">Amount</th>
              <th class="row-title">Notif Status</th>
            </tr>
          </thead>
          <tbody>
          <?php
			$query = new WC_Order_Query( array(
                'limit' => 100,
				'type' => 'shop_order',
                'orderby' => 'date',
                'order' => 'DESC',
				'status' => 'wc-completed'
            ) );
            $orders = $query->get_orders();
            
            foreach($orders as $order){
            	//var_dump($order->get_id());
                $items = $order->get_items();
                foreach ( $items as $item ) {
                	$product_name = $item->get_name();
                    $product_id = $item->get_product_id();
                    if( $item->get_meta('access_token') ){
                        $access_token = $item->get_meta('access_token');  
                    }
                    if( $item->get_meta('site_id') ){
                        $site_id = $item->get_meta('site_id');  
                    }
                    if( $item->get_meta('user_id') ){
                        $user_id = $item->get_meta('user_id');  
                    }
                }
				$gross_amount = $order->get_total() - $order->get_total_tax();
				$fee = ((2.9/100)*$gross_amount)+0.52;
				//var_dump($fee);
				$net_amount = $gross_amount - $fee;
				$weebly_amount = (30/100)*$net_amount;
				if($order->get_meta('weebly_notification') != 'completed' && $order->get_meta('weebly_notification') != 'for-testing'){
					echo "<tr><td class='row-title'> " . $order->get_id(). " </td>
						<td class='row-title'>"; 
					echo $product_name.'<br>';
					echo "        
						<div>
						<a href='?page=wpwa_manage_orders&action=notified&gross_amount=".$gross_amount."&net_amount=".$net_amount."&payable_amount=".$weebly_amount."&access_token=".$access_token."&app_name=".$product_name."&payment_id=".$order->get_id()."'>Notified</a>
						<a href='?page=wpwa_manage_orders&sub_page=action_needed&action=completed&payment_id=".$order->get_id()."'>Completed</a>
						<a href='".get_edit_post_link($order->get_id())."'>View/Edit</a>
						<a href='?page=wpwa_manage_orders&action=for-testing&payment_id=".$order->get_id()."'>For Testing</a>
						<a href='?page=wpwa_manage_orders&action=refunded&payment_id=".$order->get_id()."'>Refunded</a>
						<a href='?page=wpwa_manage_orders&action=remove_access&payment_id=".$order->get_id()."&site_id=".$site_id."&user_id=".$user_id."&app_id=".$product_id."&access_token=".$access_token."'>Remove Access</a>
						</div></td>
						<td class='row-title'> ".$order->get_billing_email()." </td>
						<td class='row-title'> ".$order->get_formatted_order_total()." </td>
						<td class='row-title'> ".$order->get_meta('weebly_notification')." </td></tr>";
				}
                
            }
            
          ?>
          </tbody>
        </table>
      </div>
    <?php
    }
    ?>
</div>