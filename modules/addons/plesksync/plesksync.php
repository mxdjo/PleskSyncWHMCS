<?php
// ------------------------------------------------------------------------------------------------------------
// Plesk Sync for WHMCS :: plesksyncwhmcs.php
// ------------------------------------------------------------------------------------------------------------
//  
// ------------------------------------------------------------------------------------------------------------

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
include ("plesksync.class.php");  // include the ApiRequestException extension class

use WHMCS\Database\Capsule;

set_include_path( get_include_path() . PATH_SEPARATOR . $_SERVER['DOCUMENT_ROOT'] );

function plesksync_config()
{
    return array(
        'name' => 'Plesk Sync for WHMCS', // Display name for your module
        'description' => 'This module enables you to sync your client data from Plesk with WHMCS',
        'author' => 'Websavers Inc.', // Module author name
        'language' => 'english', // Default language
        'version' => '0.9', // Version number
        'fields' => array(
            'accounts_per_page' => array(
                'FriendlyName' => 'Accounts Per Page',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '100',
                'Description' => 'Amount of accounts it searches for on each page',
            ),
            'whmcs_api_url' => array(
                'FriendlyName' => 'WHMCS API URL',
                'Type' => 'text',
                'Size' => '100',
                'Default' => '../../../includes/api.php',
                'Description' => 'If you have moved the API php file, change the location here.',
            ),
            // the yesno field type displays a single checkbox option
            'whmcs_addorder_payment' => array(
                'FriendlyName' => 'Mark Order as Paid By',
                'Type' => 'dropdown',
								'Options' => array(
										'option1' => 'paypal',
										'option2' => 'creditcard',
										'option3' => 'manual',
								),
                'Description' => 'When creating a matching order, use this payment type. Affects renewals',
            ),
            // the dropdown field type renders a select menu of options
            'whmcs_addorder_billingcycle' => array(
                'FriendlyName' => 'Default Billing Cycle when creating orders',
                'Type' => 'dropdown',
                'Options' => array(
                    'option1' => 'Monthly',
                    'option2' => 'Quarterly',
                    'option3' => 'Annually',
										'option4' => 'Biennially',
										'option5' => 'Triennially',
                ),
                'Description' => 'This billing cycle will be used when creating orders.',
            ),
        )
    );
}

function plesksync_output($vars){
	
	// Get module configuration parameters
	$max_id_results_per_page 			= $vars['accounts_per_page'];
	$whmcs_api_url 								= $vars['whmcs_api_url'];
	$whmcs_addorder_payment 			= $vars['whmcs_addorder_payment'];
	$whmcs_addorder_billingcycle 	= $vars['whmcs_addorder_billingcycle'];
  $modulelink                   = $vars['modulelink']; //built in
  $module_relpath               = $vars['systemurl'] . '/modules/addons/plesksync';
  $module_abspath               = __DIR__;
	
	$strIp = (isset($_POST['ip']) ? $_POST['ip'] : $_GET['ip']);
  
  // }}---------------------[AJAX RESPONDERS]---------------------------------------------
  
  if (!empty($_REQUEST['ps_action'])){
    
    switch($_REQUEST['ps_action']){
      
      case 'GetServerStats':
        
        $curl = curlInit($_GET['ip'], $_GET['l'], urldecode($_GET['p']), "on");

        try {
        try { 
              $xmlDomDoc = createPacket('<packet><server><get><stat/></get></server></packet>');
              $response = sendRequest($curl, $xmlDomDoc);
              $responseXml = parseResponse($response);
              checkResponse($responseXml);     
            
              $info = $responseXml->xpath('/packet/server/get/result');
                 
              echo '<table border="0" width="100%" cellpadding="1" cellspacing="0">';
              echo '<tr><td style="border-bottom: 1px double grey;background-color: green;color:white">' . '<strong>Plesk v'.(string)$info[0]->stat->version->plesk_version . ' for ' .(string)$info[0]->stat->version->plesk_os . '</strong>'; // ' .(string)$info[0]->stat->version->plesk_os_version . '
             //echo '<tr><td>&bull; ' . 'OS: <strong>'.(string)$info[0]->stat->version->os_release . '</strong>';
             // echo '<tr><td>&bull; ' . 'CPU: <strong>'.(string)$info[0]->stat->other->cpu . '</strong>';
              echo '<tr><td>&bull; ' . 'Uptime: <strong>' .(string)$info[0]->stat->other->uptime . '</strong>';
              echo '<tr><td>&bull; ' . 'Load Averages: <strong>' .(string)$info[0]->stat->load_avg->l1 . ', ' .(string)$info[0]->stat->load_avg->l5 . ', ' .(string)$info[0]->stat->load_avg->l15 . '</strong>';
              echo '<tr><td>&bull; ' . 'Memory: <strong>' . bytesToSize1024($info[0]->stat->mem->used) . ' used, ' . bytesToSize1024($info[0]->stat->mem->free) . ' free, ' .bytesToSize1024($info[0]->stat->mem->total) . ' total.</strong>';
              echo '<tr><td style="border-bottom: 1px solid green;">&bull; ' . 'Disk: <strong>' . (string)$info[0]->stat->diskspace->device->name . ' ' .bytesToSize1024($info[0]->stat->diskspace->device->used) . ' used, ' .bytesToSize1024($info[0]->stat->diskspace->device->free) . ' free, ' . bytesToSize1024($info[0]->stat->diskspace->device->total) . ' total.</strong>';    
              echo '<tr><td>&bull; ' . 'Clients: <strong>' .(string)$info[0]->stat->objects->clients . '</strong>';
              echo '<tr><td style="border-bottom: 1px solid green;">&bull; ' . 'Domains: <strong> ' .(string)$info[0]->stat->objects->active_domains . ' (' .(string)$info[0]->stat->objects->domains . ')</strong>';
              echo '<tr><td>&bull; ' . 'Problem Clients: <strong>' .(string)$info[0]->stat->objects->problem_clients . '</strong>';
              echo '<tr><td style="border-bottom: 1px solid green;">&bull; ' . 'Problem Domains: <strong>' .(string)$info[0]->stat->objects->problem_domains . '</strong>';
              echo '<tr><td>&bull; ' . 'Web Users: <strong>' .(string)$info[0]->stat->objects->web_users . '</strong>';
              echo '<tr><td>&bull; ' . 'Databases: <strong>' .(string)$info[0]->stat->objects->databases . '</strong>';
              echo '<tr><td style="border-bottom: 1px solid green;">&bull; ' . 'Database Users: <strong>' .(string)$info[0]->stat->objects->database_users . '</strong>';
              echo '<tr><td>&bull; ' . 'Mailboxes: <strong>' .(string)$info[0]->stat->objects->mail_boxes . '</strong>';          
              echo '<tr><td>&bull; ' . 'Mail Redirects: <strong>' .(string)$info[0]->stat->objects->mail_redirects . '</strong>';
              echo '<tr><td>&bull; ' . 'Mail Groups: <strong>' .(string)$info[0]->stat->objects->mail_groups . '</strong>';         
              echo '<tr><td>&bull; ' . 'Mail Responders: <strong>' .(string)$info[0]->stat->objects->mail_responders . '</strong>';      
              
              
              echo '</table>';
              
        } catch (ApiRequestException $e) { echo $e; die(); }
        } catch (Exception $e) { echo $e; die(); }
      
        break;
      case 'GetPleskAccountDetails':
      
          $curl = curlInit($_GET['ip'],  $_GET['l'], urldecode($_GET['p']), $_GET['secure']);

          try {
            try {
              
                $response = sendRequest( $curl, createPacket('<packet><customer><get><filter><id>' . $_GET['cid'] . '</id></filter><dataset><gen_info/><stat/></dataset></get></customer></packet>') );
                $responseXml = parseResponse($response);
                checkResponse($responseXml);
                $userNode = $responseXml->xpath('//*[name()="gen_info"]');
                  
                echo '<span style="font-size:8pt;color:black">';
                
                echo '&bull; Name: <b>' . (string)$userNode[0]->pname . '</b><br />';		
                echo '&bull; Company: <b>' . (string)$userNode[0]->cname . '</b><br />';
                echo '&bull; Phone: <b>' . (string)$userNode[0]->phone . '</b><br />';
                if ((string)$userNode[0]->fax) echo '&bull; Fax: <b>' . (string)$userNode[0]->fax . '</b><br />';
                echo '&bull; E-mail: <b>' . (string)$userNode[0]->email . '</b><br />';
                echo '&bull; Address:  <b>' . (string)$userNode[0]->address . '</b><br />';
                echo '&bull; City: <b>' . (string)$userNode[0]->city . '</b><br />';
                echo '&bull; State: <b>' . (string)$userNode[0]->state . '</b><br />';
                echo '&bull; Postcode:  <b>' . (string)$userNode[0]->postcode . '</b><br />';
                echo '&bull; Country:  <b>' . (string)$userNode[0]->country . '</b><br />';
                echo '&bull; Login: <b>' . (string)$userNode[0]->login . '</b><br />';      
                echo '&bull; Password:  <b>' . (string)$userNode[0]->password . '</b>';     
                echo '<br />';

                // status = Allowed values: 0 (active) | 16 (disabled_by admin) | 4 (under backup/restore) | 256 (expired)
                echo '&bull; Status: ';
                
                switch ((string)$userNode[0]->status) {
                   case "0": echo '<span style="color:green">active</span>';
                     break;
                   case "16": echo '<span style="color:red">disabled by admin (suspended)</span>';
                     break;	
                   case "4": echo '<span style="color:red">under backup/restore</span>';
                     break;
                   case "256": echo '<span style="color:red">expired</span>';
                     break;	
                   default: echo '<span style="color:black">'.(string)$userNode[0]->status.'</span><br />';
                     break;	
                }
                            
                echo '</span>';
                  
            } catch (ApiRequestException $e) { echo $e; die(); }
          } catch (Exception $e) { echo $e; die(); }
      
        break;
      case 'ChangeStatusInPlesk':
      
          $curl = curlInit($_GET['ip'], $_GET['l'], urldecode($_GET['p']), $_GET['secure']);

          try {
          try {

                echo '<span style="font-size:7pt;color:black">';
                echo "&rArr; Connecting to Plesk server (".$_GET['ip'].")...<br />";
                
                $suspend = $_GET['suspend'];
                $client_status = $suspend=="1" ? '16' : '0';   // 16 = client suspended
                $domain_status = $suspend=="1" ? '2' : '0';
                
                if ($suspend == "1") echo '&rArr; Sent command to <i>suspend</i> the status of domain.<br />';
                else echo '&rArr; Sent command to <i>unsuspend</i> the status of domain.<br />';
                
                $strPacket = '<packet>';
                $strPacket .= '<client><set><filter><id>' . $_GET['cid'] . '</id></filter><values><gen_info><status>' . $client_status . '</status></gen_info></values></set></client>';
                $strPacket .= '<domain><set><filter><id>' . $_GET['did'] . '</id></filter><values><gen_setup><status>' . $domain_status . '</status></gen_setup></values></set></domain>';
                $strPacket .= '</packet>';      
                
                $response = sendRequest($curl, createPacket($strPacket));
                
                $responseXml = parseResponse($response);

                checkResponse($responseXml);

                echo '</span>';
                
          } catch (ApiRequestException $e) { echo $e; die(); }
          } catch (Exception $e) { echo $e; die(); }
      
        break;
      case 'CreateWHMCSAccount': //Need to switch this to localAPI() calls.
      
          include ("Whmcs.Api.class.php");
          $bNoEmail = (($_GET["sendemail"] == "true") ? 'false' : 'true');
          //header('Content-Type: text/html; charset=UTF-8');   // required for ajax output            
          $whmcsObj = new WhmcsApiWrapper();
          $whmcsObj->setApiUrl($whmcs_api_url);           // path to http://yoursite.com/whmcs_dir/includes/api.php
          $whmcsObj->setLoginAccessAutomatic($db_username,$db_password, $db_name, $db_host);    // no entries in config.php: try to auto-detect
        
          echo '&rArr; WHMCS API: AddClient...<br />';   

          $iResult = $whmcsObj->addClient(
              $_GET['first'],                                 // first name
              $_GET['last'],                                  // last name
              $_GET['company'],                               // (optional) company name
              $_GET['email'],                                 // e-mail address (also username)
              $_GET['password'],                              // plain text password for account
              $_GET['address1'],                              // address 1
              "",                                             // (optional) address 2 - [*NOT used by Plesk*]
              $_GET['city'],                                  // city
              $_GET['state'],                                 // state
              $_GET['postcode'],                              // postcode
              $_GET['country'],                               // two-letter ISO country code
              $_GET['phone'],                                 // telephone number (numbers, dashes and spaces only!)
              $bNoEmail,                                      // pass as true to surpress the client signup welcome email sending
              "1",                                            // default currency                                        
              '*** Imported via Plesk Sync on (' . date("F j, Y, g:i a") . ')  *** (Please check: Payment Method, Pricing, Billing Cycle & Next Due Date)',      // (optional) admin notes
              "",                                     // (optional) group id
              "",                                     // (optional) credit card type
              "",                                     // (optional) credit card number
              "",                                     // (optional) credit card expiration (MMYY)
              "",                                     // (optional) start date   
              "",                                     // (optional) issue number
              ""                                      // (optional) custom fields: a base64 encoded serialized array of custom field values,  ex: base64_encode(serialize(array("1"=>"Google"))); 
          );

          if ($iResult) {
            
              $objData = $whmcsObj->getTransactionData();        
              $iClientId = $objData["clientid"];
              
              echo '<span style="color:green"><br />&#10004; Added new client:</span><br /><br />&bull; Name: '. $_GET['first'] . ' ' . $_GET['last'] . '<br />&bull; Id: <a href="clientssummary.php?userid=' . $results["clientid"] . '" target="_blank">#' . $results["clientid"] . '</a>.<br />';
              if (!$bNoEmail) echo '&raquo; Welcome e-mail sent.<br />';
                                  
          } else {

              $objData = $whmcsObj->getTransactionData();
              echo '<span style="color:red">x WHMCS API Error: ' . $objData["errormessage"] . '<br />';
      
          }
      
        break;
      case 'CreateWHMCSOrder': //Need to switch this to use localAPI()
      
          $bNoEmail = (($_GET["sendemail"] == "true") ? 'false' : 'true');
          $bNoInvoice = (($_GET["createinvoice"] == "true") ? 'false' : 'true');
          //header('Content-Type: text/html; charset=UTF-8');   // required for ajax output
          $whmcsObj = new WhmcsApiWrapper();
          $whmcsObj->setApiUrl($whmcs_api_url);           // path to http://yoursite.com/whmcs_dir/includes/api.php
          $whmcsObj->setLoginAccessAutomatic($db_username,$db_password, $db_name, $db_host);
        
        
          // WHMCS API :: AddOrder
          // -----------------------------------------------------------------------------------------------------

          echo '<span style="color:black">&rArr; WHMCS API: AddOrder...<br />';

          $iResult = $whmcsObj->addOrder(
              $_GET["domain"],                         // domain name
              $_GET["clientid"],                       // client id for to attach order to
              $_GET["packageid"],                      // product id
              $whmcs_addorder_payment,                 // paypal, authorize, webmoney etc...
              $whmcs_addorder_billingcycle,            // onetime, monthly, quarterly, semiannually, etc..
              $bNoInvoice,                             // set true to not generate an invoice for this order
              $bNoEmail,                               // set true to surpress the order confirmation email
              "",                     // (optional) addons - comma seperated list of addon ids
              "",                     // (optional) a base64 encoded serialized array of custom field values
              "",                     // (optional) a base64 encoded serialized array of configurable product options
              "",                     // (optional) set only for domain registration - register or transfer
              "",                     // (optional) set only for domain registration - 1,2,3,etc..
              "",                     // (optional) set only for domain registration - true to enable
              "",                     // (optional) set only for domain registration - true to enable	
              "",                     // (optional) set only for domain registration - true to enable	
              "",                     // (optional) eppcode - set only for domain transfer
              "",                     // (optional) set only for domain registration - DNS Nameserver #1
              "",                     // (optional) set only for domain registration - DNS Nameserver #2
              "",                     // (optional) set only for domain registration - DNS Nameserver #3
              "",                     // (optional) set only for domain registration - DNS Nameserver #4	
              "",                     // (optional) pass coupon code to apply to the order (optional)	
              "",                     // (optional) affiliate ID if you want to assign the order to an affiliate (optional)
              ""                      // (optional) can be used to pass the customers IP (optional)
          );

          if ($iResult) {         // operation was successful
             
            $objData = $whmcsObj->getTransactionData();
            
            $iOrderId = $objData["orderid"];
            $iInvoiceId = $objData["invoiceid"];      // sometimes empty
            $iProductIds = $objData["productids"];
      
            echo '<br /><span style="color:green;"> &#10004; Successfully added!</span><br /><br />';
            echo '&bull; Client: #' . $_GET["clientid"] .' <br />&bull; Hosting: ' . $_GET["domain"] . '<br />&bull; Package: #' . $_GET["packageid"] .' <br /><br />';
            echo '&raquo; Order Id #' . $iOrderId . '<br />';
            
            if (!empty($iInvoiceId)) echo '&raquo;  <a href="invoices.php?action=edit&id=' . $iInvoiceId . '" target="_blank">Invoice #' . $iInvoiceId . '</a><br />';
            
            echo '&raquo; <a href="clientshosting.php?userid=' . $_GET["clientid"] . '&id=' . $iProductIds . '">Product Id # ' . $iProductIds . '</a><br />';
            
            if (!$bNoInvoice) echo '&raquo; Invoice was generated.<br />';
            if (!$bNoEmail) echo '&raquo; Order confirmation e-mail sent.<br />';


            // WHMCS API :: EncryptPassword
            // -----------------------------------------------------------------------------------------------------            
            if (isset($_GET["client_login"]) && isset($_GET["client_password"])) {       // need to use WHMCS API to encrypt the password first!

                echo '<br /><span style="color:black">&rArr; WHMCS API: EncryptPassword...</span>';
                
                $iResult = $whmcsObj->encryptPassword(urldecode($_GET["client_password"]));
                $objData = $whmcsObj->getTransactionData();         
                if ($iResult) {         // operation was successful
                    $whmcsEncryptedPassword = $objData["password"];
                    echo '<span style="color:green;"> &#10004;</span> ';
                } else {                                     
                    echo '<br /><div style="color:red">x WHMCS API Error: ' . $objData["errormessage"] . '</div>';
                    continue;  // *** continue
                }
                
                // manually change the username and password in the database
                echo '<br /><span style="color:black">&rArr; Setting the login & password for hosting...<br />';				
                
                $queryUpdateResult = Capsule::update("UPDATE `tblhosting` SET `username` = '" .$_GET["client_login"] . "', `password` = '" . $whmcsEncryptedPassword . "' WHERE `id` = ". $iProductIds);
                                                      
            } 
            else '<div style="color:red">&rArr; Note: Username & password are blank.</div>';                        
        
          } 
          else {                        
              $objData = $whmcsObj->getTransactionData();
              echo '<div style="color:red">x WHMCS API Error: ' . $objData["errormessage"] . '</div>';
              
              exit; // # quit.
          }                
          
          // WHMCS API :: AcceptOrder
          // -----------------------------------------------------------------------------------------------------
               
          echo '<span style="color:black">&rArr; WHMCS API: AcceptOrder...<br />';
                    
          $iResult = $whmcsObj->acceptOrder($iOrderId);
          $objData = $whmcsObj->getTransactionData();
                                          
          if ($iResult) { // operation was successful
              echo '<span style="color:green">&#10004; Order was accepted!</span><br /><br />';
              echo '&rArr; <strong>Please <a href="clientshosting.php?userid=' . $_GET["clientid"] . '&id=' . $results["productids"] . '">go to product profile</a> and verify:<br />&bull; Payment Method<br />&bull; Pricing<br />&bull; Billing Cycle<br />&bull; Next Due Date</strong><br />';                                      
          } else {                          
              echo '<div style="color:red">x Order # [' . $iOrderId . '] was not accepted!</div><br />';
              echo '<div style="color:red">x WHMCS API Error: ' . $objData["errormessage"] . '</div>';                                    
          }
      
        break;
      case 'ImportPleskAccount':
      
          $iDomainId  =  $_GET['did'];

          $curl = curlInit($_GET['ip'],  $_GET['l'], urldecode($_GET['p']), $_GET['secure']);

          try {		 
            try {
                  
                $strPacket = '<packet><client><get><filter><id>' . $iDomainId . '</id></filter><dataset><gen_info/><permissions/></dataset></get></client></packet>';
                $response = sendRequest($curl, createPacket($strPacket));
                $responseXml = parseResponse($response);
                checkResponse($responseXml);
                       
                $userNode = $responseXml->xpath('//*[name()="gen_info"]');
                
                // first, try to find this account through email address: this has to be done here
        
                if ($strEmail = (string)$userNode[0]->email) {			

                  $clients = Capsule::select("SELECT * FROM `tblclients` WHERE `email` = '" .  $strEmail ."'");
                  $row = json_decode(json_encode($clients), true); //convert from obj to array
                  $row = $row[0];
                                      
                  if ($row['id']) {
                        
                        echo '<div id="addorderimportOut' .  $iDomainId  . '">';
                        echo '<span style="color:green;font-weight:bold;font-size:8pt;">&#10004; User already exists in WHMCS!</span><br /><br />';
                        echo '<div style="color:black;font-size:7pt">&bull; ' .  $strEmail .'<br />&bull; <a href="clientssummary.php?userid=' . $row['id'] . '" target="_blank">'. utf8_decode($row['firstname']) . ' '. utf8_decode($row['lastname']) . '</a><br />&bull; Id: #' . $row['id'] . '</div><br />';
                
                    
                        echo '<strong>Choose Package:</strong> <select name="packageid' . $iDomainId . '" id="packageid' . $iDomainId . '" style="font-size:7pt;color:black;">';
                        
                        // get list of packages
                        $resultProducts = Capsule::select("SELECT name, id FROM `tblproducts` WHERE `servertype` = 'plesk' ORDER BY `name` ASC");
                        $resultProducts = json_decode(json_encode($resultProducts), true); //convert from obj to array
                        foreach($resultProducts as $product){ 
                          echo '<option value="' . $product['id'] . '">' . $product['name'] . '</option>';
                        }
            
                        echo '</select><br /><br /><input type="checkbox" id="createinvoice' . $iDomainId . '" name="createinvoice' . $iDomainId . '" style="font-size:6pt;color:black;"><label for="createinvoice' . $iDomainId . '">Generate invoice?</label><br />';   // checked="checked"
                        
                        echo '<br /><input type="checkbox" id="sendemail' . $iDomainId . '" name="sendemail' . $iDomainId . '" style="font-size:6pt;color:black;"><label for="sendinvoice' . $iDomainId . '">Send Order confirmation e-mail?</label><br />';  
              
                        echo '<br /><center><input type="button" value="Attach Order to Client #' . $row['id'] . '"  id="addorderimportBtn' . $iDomainId . '" ';
                        echo 'onClick="CreateWHMCSOrder(\'addorderimportOut' . $iDomainId . '\',\'clientid=' . $row['id'];
                        echo '&domain=' . $_GET['domain'] . '&client_login=' . ((string)$userNode[0]->login) . '&client_password=' . urlencode(((string)$userNode[0]->password)) . '\',\'packageid'. $iDomainId . '\',\'createinvoice'. $iDomainId . '\',\'sendemail' . $iDomainId . '\')"></center></div>';			      
                        
                        exit;  // no import necessary -- show next step and QUIT!!!
                        
                  }
                  
                }
                    
                // show form to add a new client in whmcs
                $strFirst = (string)$userNode[0]->pname;
                $strFirst = substr($strFirst,0,strpos($strFirst, " ")); 
                $strLast = (string)$userNode[0]->pname;
                $strLast = substr($strLast,strpos($strLast, " ")+1,strrpos($strLast, " "));
                
                echo '<div id="createaccount_output' . $iDomainId . '"><span style="font-size:8pt;color:black"> ';
                
                echo 'First: <input type="text" style="font-size:7pt;color:black;"  id="pleskFirst' . $iDomainId  . '" size="21" value="' . ucfirst($strFirst) . '"/>' . ' <br />';
                echo 'Last: <input type="text" style="font-size:7pt;color:black;"  id="pleskLast' . $iDomainId  . '" size="21" value="' . $strLast . '"/>' . ' <br />';			    
                echo 'Company: <input type="text" style="font-size:7pt;color:black;"  id="pleskCompany' . $iDomainId  . '" size="21" value="' . (string)$userNode[0]->cname . '"/>' . ' <br />';
                echo 'Phone: <input type="text" style="font-size:7pt;color:black;"  id="pleskPhone' . $iDomainId  . '" size="10" value="' . (string)$userNode[0]->phone . '"/>' . ' <br />';		
                echo 'E-mail: <input type="text" style="font-size:7pt;color:black;" id="pleskEmail' . $iDomainId  . '" size="20" value="' . (string)$userNode[0]->email . '"/>' . ' <br />';
                echo 'Address:  <input type="text" style="font-size:7pt;color:black;" id="pleskAddress' . $iDomainId  . '" size="20" value="' . (string)$userNode[0]->address . '"/>' . ' <br />';
                echo 'City: <input type="text" style="font-size:7pt;color:black;" id="pleskCity' . $iDomainId  . '" size="12" value="' . (string)$userNode[0]->city . '"/>' . ' <br />';
                echo 'State:  <input type="text" style="font-size:7pt;color:black;" id="pleskState' . $iDomainId  . '"  size="5" value="' . (string)$userNode[0]->state . '"/>' . ' <br />';
                echo 'Postcode:  <input type="text" style="font-size:7pt;color:black;" id="pleskPostcode' . $iDomainId  . '" size="6" value="' . (string)$userNode[0]->pcode . '"/>' . ' <br />';
                echo 'Country:  <input type="text" style="font-size:7pt;color:black;"  id="pleskCountry' . $iDomainId  . '" size="3" value="' . (string)$userNode[0]->country . '"/>' . ' <br />';
                echo 'Login:  <input type="text" style="font-size:7pt;color:black;" id="pleskLogin' . $iDomainId  . '" size="15" value="' . (string)$userNode[0]->login . '"/>' . ' <br />';      
                echo 'Password:  <input type="text" style="font-size:7pt;color:black;"  id="pleskPassword' . $iDomainId  . '" size="15" value="' . (string)$userNode[0]->password . '"/>' . ' <br />';
                  
                echo '<br /><input type="checkbox" id="sendemail' . $iDomainId  . '" style="font-size:7pt;color:black;"><label for="sendemail' . $iDomainId  . '">Send welcome e-mail message?</label><br />';  //checked="checked"
                  
                echo '<center><br /><span style="font-size:7pt;color:red">(Verify the details above are correct.)</span><br /><br /><input type="button" value="Create new account in WHMCS &raquo;"  id="createaccount' .$iDomainId. '" ';
                echo 'style="color:green" onClick="CreateWHMCSAccount(\'createaccount_output'.$iDomainId. '\',\'did=' . $iDomainId  . '\',\'pleskFirst' . $iDomainId  . '\', \'pleskLast' . $iDomainId  . '\', \'pleskCompany' . $iDomainId  . '\',';
                echo '\'pleskEmail' . $iDomainId  . '\', \'pleskPhone' . $iDomainId  . '\',  \'pleskAddress' . $iDomainId  . '\', \'pleskCity' . $iDomainId  . '\', \'pleskState' . $iDomainId  . '\', \'pleskPostcode' . $iDomainId  . '\',';
                echo '\'pleskCountry' . $iDomainId  . '\', \'pleskLogin' . $iDomainId  . '\', \'pleskPassword' . $iDomainId  . '\',\'sendemail' . $iDomainId . '\')"></form></center><br />';   
                
                echo '</span></div>';
                
                } catch (ApiRequestException $e) { echo $e; die(); }
          } catch (Exception $e) { echo $e; die(); }
      
        break;
    }
    
    exit; //kill all remaining processing.
    
  } /** End AJAX Responders */
  
  /** Begin normal admin area output processing **/
  ?>
  
  <img src="<?php echo $module_relpath; ?>/images/plesksync-icon.png" align="absmiddle"> <strong>Plesk Sync for WHMCS</strong><br /><br />
  <script><!-- Set vars -->
    var moduledir = "<?php echo $module_relpath; ?>";
    var modulelink = "<?php echo $modulelink; ?>";
  </script>
  <script type="text/javascript" src="<?php echo $module_relpath; ?>/scripts.js"></script>
  
<?php
	if (empty($_POST)) {
    
  	// }}---------------------[MAIN() Default Page - Show active Plesk Servers]---------------------------------------------------------
?>
	    <p>Plesk Sync is an addon module for WHMCS to import, control, create and synchronize client hosting accounts with your Parallel Plesk servers.</p>
	    <p>Accounts are color-coded, reports a diagnosis and resolution, contains statistics on disk space and traffic, detailed client profile information and command buttons to resolve.</p>
	    <p>&rArr; Auto-detecting servers...</p>
	    <h2>Plesk Servers</h2>

      <div class="tablebg"><table class="datatable" border="0" cellpadding="3" cellspacing="1" width="100%">
      <tbody><tr><th width="85">#</th><th>Server Name</th><th>Group</th><th width="95">Host (IP) </th><th width="100">Protocol</th><th width="220">Stats</th><th></th></tr>
<?php
  		$servers = Capsule::select("SELECT `tblservers`.name, `tblservers`.hostname, `tblservers`.ipaddress, `tblservers`.maxaccounts, `tblservers`.username, `tblservers`.password, `tblservers`.secure, `tblservergroups`.name AS `groupname` FROM `tblservers` LEFT JOIN `tblservergroupsrel` ON `tblservers`.id = `tblservergroupsrel`.serverid LEFT JOIN `tblservergroups` ON `tblservergroupsrel`.groupid = `tblservergroups`.id WHERE type = 'plesk' ");
      
      $i = 0;
      $servers = json_decode(json_encode($servers), true); //convert from obj to array
  		foreach ($servers as $row){
        $error = false;		
        echo '<tr>';
        
          echo '<td><img src="images/icons/products.png" align="absmiddle"> '.++$i.'.</td>';
          echo '<td style="color:black;font-weight:bold">'.$row['name'].'</td>';
          echo '<td align="center"><i>'.$row['groupname'].'</i></td>';
          echo '<td>'.$row['ipaddress'].'</td>';
    		  echo '<td align="center">';
  		      
  				// connect to each server and get the list of available protocols that it understands
  				try {
            $requestXml = createPacket('<packet><server><get_protos/></server></packet>');
            $curl = curlInit($row['ipaddress'], $row['username'], decrypt($row['password']), $row['secure']);
            $response = sendRequest($curl, $requestXml);
            $responseXml = parseResponse($response);

            $info = $responseXml->xpath('//proto[last()]');    // detect protocols available

            echo  $strServerProtocolVersion = (string)$info[0];
			      
			      if ($strServerProtocolVersion < "1.6.0.0"){
              echo '<span style="color:red;font-size:7pt"> x</span></td><td style="color:red;"><i>Version not supported</i></td>';
              $error = true;
            }
			      else{ 
              echo '<span style="color:green;font-size:8pt"> &#10004;</span>';  
            }
            logModuleCall('plesksync', 'pleskGetProtocol', (string)$requestXml, (string)$responseXml, $info, $replaceVars = "");
  				} 
          catch (Exception $e) {
			      echo '<span style="color:red;font-size:7pt"> x</span></td><td style="color:red;"><i>' . $e . '</i></td>';
            $error = true;
  				}		       

		      echo '</td>';

          if ($error){
            echo '<td>&nbsp;</td>';
          }
          else{
  		      echo '<td align="center"><div id="serverinfo_output' . $i . '"> <input type="button" value="Server Statistics"  id="serverinfobtn' .$i. '" style="color:orange" ';
  		      echo 'onClick="getServerStats(\'serverinfo_output'.$i. '\',\'ip='.$row['ipaddress'].'&l='.$row['username'].'&p='.urlencode(decrypt($row['password'])).'\')"></div></td>';     
            echo '<td><form action="'.$modulelink.'" method="post">';	      
  		      echo '<input name="login_name" value="'.$row['username'].'" type="hidden"><input name="passwd" value="'.decrypt($row['password']).'" type="hidden">';
  		      echo '<input name="ip" value="'.$row['ipaddress'].'" type="hidden"><input name="secure" value="'.$row['secure'].'" type="hidden">';
  		      echo '<input value="Browse Accounts..." type="submit"></form>'; 
  	        echo '</td>';
          }
        echo '</tr>'; 
  		}
  		echo '</tbody></table></div><br />';
  		  		
  	 // ------------------------------------------------------------------------------
	 
	} else {
		
		// ---------------------[MAIN() Process - Show domain accounts from selected server]---------------------------------------------------------	

		$curl = curlInit($strIp, $_POST['login_name'], $_POST['passwd'], $_POST['secure']);

		$iPageNumber = (isset($_POST['page']) ? $_POST['page'] : "0");
		$iMaxResultsPerPage = $max_id_results_per_page;  			// # config.php
		$iStartNumber = ($iPageNumber * $iMaxResultsPerPage) + 1;
		$iEndNumber = ($iPageNumber + 1) * $iMaxResultsPerPage;

		try {
      echo '<a href="">[ Home ] : Plesk Server Summary</a><br /><br />';
      echo '<div style="color:grey">&rArr; Connected to Plesk RPC API at ' . $strIp . ':8443/enterprise/control/agent.php...</div><br />';
      echo '<img src="images/icons/products.png" align="absmiddle"> <span style="color:black;font-weight:bold;font-size:10pt">Plesk Server [' . $strIp . ']</span> <br /><br />';
	   
      if ($iPageNumber == 'all') {
				echo "&rArr; Downloaded properties of all domains on server.<br />";
				$response = sendRequest($curl, createAllDomainsDocument()->saveXML());
		  } 
      else {
				showNavigationButtons($iPageNumber, $strIp, $_POST['login_name'], $_POST['passwd'], $_POST['secure']);
				echo '<div align="center" style="color:black">Domain Accounts with id (<strong>' . $iStartNumber . ' - ' . $iEndNumber . '</strong>)</div>';
				$response = sendRequest($curl, createPagedDomainsDocument($iStartNumber,$iEndNumber) );
      }
		           
		  $responseXml = parseResponse($response);
		  checkResponse($responseXml);

		} 
    catch (ApiRequestException $e) {
		      echo $e; die();
    }

		// Explore the result
    $iCnt = 0;
    $iCountMissingFromWHMCS = 0;
    $iCountSuspendedInPlesk = 0;

    echo '<br /><table border="0" width="1250" cellpadding="1" cellspacing="1"><tr style="border: 1px solid grey;background-color: #f8f5da"><th style="font-size:8pt;">#.</th><th style="font-size:8pt;">ID</th><th style="font-size:8pt;">Domain Name</th>';
    echo '<th style="font-size:8pt;" width="150">Client (Owner)</th><th style="font-size:8pt;" width="80">WHMCS Id</th>';
    echo '<th style="font-size:8pt;" width="70">Created</th><th style="font-size:8pt;">Status</th><th style="font-size:8pt;" width="8">Link</th><th style="font-size:8pt;" width="170">Statistics</th><th style="font-size:8pt;" width="170">Diagnosis</th><th style="font-size:8pt;" width="150">Resolution</th></tr>';

		foreach ($responseXml->xpath('/packet/webspace/get/result') as $resultNode) {

      if ( (string)$resultNode->status != "ok" ) continue;

      //print_r($resultNode); ///// DEBUG: API Results
      
      //$iClientId = (string)$resultNode->data->gen_info->client_id;
      $iClientId = (string)$resultNode->data->gen_info->{'owner-id'};
      $iDomainId = (string)$resultNode->id;
      $strDomainName = (string)$resultNode->data->gen_info->name;
      $iDomainStatus = (string)$resultNode->data->gen_info->status;   /// INCORRECT!
      $iAccountStatus = (string)$resultNode->data->gen_info->status;  // 0 = active, 2 = suspended (lack of payment?), 66 = suspended (over limit: disk/bandwidth)
      $strSolutionText = "";
      
      //print($strDomainName); ///// DEBUG: Domain Name
         
      if ($iDomainStatus  != "0") $iCountSuspendedInPlesk++;
           
      // get info from WHMCS
      $plan = Capsule::table('tblhosting')->where('domain',$strDomainName)->orderBy('regdate', 'desc')->limit(1)->get();
      $row = json_decode(json_encode($plan), true); //convert from obj to array
      		      
      if ($row) {
        $iWHMCSClientId = $row['userid'];
        $iWHMCSHostingId = $row['id'];
        $iWHMCSHostingStatus = $row['domainstatus'];
        $bFoundinWHCMS = TRUE;
      } 
      else {
        $iWHMCSClientId = '<span style="color:white;background-color:red;font-size:7pt;font-weight:bold">Not in WHMCS</span>';
        $bFoundinWHCMS = FALSE;
        $bhasWHMCSDomainAccount = FALSE;
        $iCountMissingFromWHMCS++;
        $strDomainNotes = "&raquo; NO hosting for this domain in WHMCS";
        $strSolutionText =  '<div id="createaccntOut' . $iCnt . '"> <input type="button" style="color:green;font-weight:bold" value="Verify & Import &raquo;"  onClick="ImportPleskAccount(\'createaccntOut'.$iCnt. '\',\'did=' . $iClientId  .  '&domain=' . $strDomainName . '&ip='.$strIp .'&l=' . $_POST['login_name'] .'&p='.urlencode($_POST['passwd']).'&secure='.$bSecure .'\')"></div>';

        // search for domain name match in WHMCS (Plesk API does not return full users stats unless you request a single domain)
				$domains = Capsule::select("SELECT *  from `tbldomains` WHERE `domain` = '$strDomainName' LIMIT 1");
        $rowDomain = json_decode(json_encode($domains), true); //convert from obj to array
				//$rowDomain = $domains[0];
			  
        //print_r($rowDomain); ///DEBUG
        
        if ($rowDomain) {
            $iWHMCSDomainId = $rowDomain['id'];
            $iWHMCSClientId = $rowDomain['userid'];
            $iWHMCSDomainStatus = $rowDomain['status'];
                                
						$strDomainNotes .= '<br />&raquo; Found possible owner through domain:<br />&bull; <a href="clientssummary.php?userid='. $iWHMCSClientId . '">Client #' .  $iWHMCSClientId. '</a><br />&bull; <a href="clientshosting.php?userid=' .  $iWHMCSClientId  . '&hostingid=' . $iWHMCSHostingId . '">Domain #' . $iWHMCSDomainId . ' (' . $iWHMCSDomainStatus . ')</a>';

						$strSolutionText = '<div id="addorderOut' . $iCnt . '">';
						$strSolutionText .= '<strong>Choose Package:</strong> <select name="packageid" id="packageid' . $iCnt. '" style="font-size:7pt;color:black;">';
						
						// get list of packages
						$packages = Capsule::select("SELECT name, id FROM `tblproducts` WHERE `servertype` = 'plesk' ORDER BY `name` ASC");
            $packages = json_decode(json_encode($packages), true); //convert from obj to array
						foreach($packages as $rowProducts)  $strSolutionText .= '<option value="' . $rowProducts['id'] . '">' . $rowProducts['name'] . '</option>';
											      
						$strSolutionText .= '</select><br /><br /><input type="checkbox" id="createinvoice' . $iCnt. '" name="createinvoice' . $iCnt. '" style="font-size:6pt;color:black;"><label for="createinvoice' . $iCnt. '">Generate invoice?</label><br />';   // checked="checked"
						$strSolutionText .= '<br /><input type="checkbox" id="sendemail' . $iCnt. '" name="sendemail' . $iCnt. '" style="font-size:6pt;color:black;"><label for="sendemail' . $iCnt. '">Send Order confirmation e-mail?</label><br />';  
							
						$strSolutionText .= '<br /><center><input type="button" value="Attach Order to Client #'.$iWHMCSClientId.'"  id="addorderOut' . $iCnt. '" onClick="CreateWHMCSOrder(\'addorderOut'.$iCnt. '\',\'clientid=' . $iWHMCSClientId;
						$strSolutionText .= '&domain=' . $strDomainName . '\',\'packageid'.$iCnt. '\',\'createinvoice'.$iCnt. '\',\'sendemail'.$iCnt. '\')"></center></div>';
						
						$bhasWHMCSDomainAccount = TRUE;
        }
                    
      } 
      
      if (!$bFoundinWHCMS && !$bhasWHMCSDomainAccount) echo '<tr style="background-color: #fbb8b8;">';
      else if (!$bFoundinWHCMS && $bhasWHMCSDomainAccount)  echo '<tr style="background-color: #FBEEEB;">';
      else if ($iCnt % 2) echo '<tr style="background-color: #dee8f3">';
      else echo "<tr>";
      
      $iCnt++;
      
      echo '<td style="font-size:8pt;color:black;white-space: nowrap;">' . ($iCnt + ($iStartNumber-1)). '.</td>';
      echo '<td style="font-size:8pt;color:#6a6d6e;white-space: nowrap;" align="center">' . $iDomainId . '</td>';
      
      if (!$bFoundinWHCMS) echo '<td style="font-size:8pt;"><strong>' . $strDomainName . '</strong>';
      else echo '<td style="font-size:8pt"><a href="clientshosting.php?userid=' .  $iWHMCSClientId  . '&hostingid=' . $iWHMCSHostingId . '">' . $strDomainName . '</a>';
      
      echo '</td>';
      echo '<td style="font-size:8pt;white-space: nowrap"><center>';
      echo  'Plesk Id # '. $iClientId;
      echo '</center><br />';
      echo '<div id="userinfo_output' . $iCnt . '"><center><input type="button" value="Details"  id="userinfobtn' .$iCnt. '" onClick="GetAccountDetailsPlesk(\'userinfo_output'.$iCnt. '\',\'cid=' . $iClientId  .  '&ip='.$strIp .'&l=' . $_POST['login_name'] . '&p='.urlencode($_POST['passwd']).'&secure='.$bSecure .'\')"></center></div></td>';

      if (!$bFoundinWHCMS)        echo '<td style="font-size:8pt;white-space: nowrap;">' . $iWHMCSClientId . '</td>';   
      else echo '<td style="font-size:8pt;white-space: nowrap;"><a href="clientsdomains.php?id=' . $iWHMCSClientId . '" style="text-decoration: none;">' . $iWHMCSClientId . '</a></td>';
  
      echo '<td style="font-size:8pt;white-space: nowrap">' . (string)$resultNode->data->gen_info->cr_date . '</td>';
      echo '<td style="font-size:8pt;white-space: nowrap" align="center">';
     
			switch ($iAccountStatus) {
			   case "0": echo ' <span style="color:grey">active</span>';
				   break;
			   case "2": echo ' <span style="color:red">suspended</span>';
				   break;	
			   case "66": echo ' <span style="color:red">suspended<br/>[<strong>over limits!</strong>]</span>';
				   break;
			   case "256": echo ' <span style="color:red">suspended<br/>[not sure why!]</span>';
				   break;	
			   default: echo ' <span style="color:grey">'.$iAccountStatus.'</span>';
				   break;	
			}

      echo '</td>';
      echo '<td style="font-size:8pt;">[<a href="http://www.'. $strDomainName . '" target="_blank">www</a>]</td>';
      echo '<td style="font-size:8pt;white-space: nowrap;">';
      //if ((string)$resultNode->data->user->enabled == "false")  echo  '&bull; Enabled: <span style="color:red;font-weight:bold">NO</span> <br />';
      echo '&bull; Type: ';
      
      switch ($strHtype = (string)$resultNode->data->gen_info->htype) {
			   case "vrt_hst": echo ' <span style="color:black">virtual host</span>';
				   break;
			   case "none": echo ' <span style="color:white;background-color:red;font-weight:bold">NONE</span>';
				   break;		
			   default: echo ' <span style="color:#0398ae">'.$iAccountStatus.'</span>';
				   break;	
      }    
      echo ' <br />';
      
      echo '&bull; DNS: '. (string)$resultNode->data->gen_info->dns_ip_address . '<br />';
      echo '&bull; Disk Space: ' . bytesToSize1024($resultNode->data->gen_info->real_size) . '<br />';    
      echo '&bull; Traffic Today: ' . bytesToSize1024($resultNode->data->stat->traffic) . '<br />';
      echo '&bull; Traffic Yesterday: ' . bytesToSize1024($resultNode->data->stat->traffic_prevday) . '<br />';
      //echo '&bull; Active Domains: '. (string)$resultNode->data->stat->active_domains . '<br />';
      //echo '&bull; Sub-Domains: '. (string)$resultNode->data->stat->subdomains . '<br />';      
      //echo '&bull; Disk Space: ' . bytesToSize1024($resultNode->data->stat->disk_space) . '<br />';     
      echo '</td>';
      
  
      if (!$bFoundinWHCMS) {
		    echo '<td style="font-size:8pt;color:black;white-space: nowrap">'.$strDomainNotes.'</td>';
		    echo '<td style="font-size:8pt;color:black;">' . $strSolutionText. '</td>';
      } 
      else if ($row['domainstatus'] == "Suspended" && $iDomainStatus == 0) {    
        echo '<td style="font-size:8pt;color:black;white-space: nowrap">&raquo; Suspended in WHMCS, <strong>Active in Plesk</strong>.</td>';
        echo '<td><div id="pleskSuspendOut' . $iCnt . '"> <input type="button" value="Plesk: `Suspend`"  id="pleskSuspendBtn' .$iCnt. '" style="color:#5c2de1" onClick="ChangeAccountStatusPlesk(\'pleskSuspendOut'.$iCnt. '\',\'cid=' . $iClientId  . '&did=' . $iDomainId  . '&secure=' . $row['secure'] . '&suspend=1&ip='.$strIp.'&l=' . $_POST['login_name'] .'&p='.urlencode($_POST['passwd']).'\')"></div></td>';
      } 
      else if ($row['domainstatus'] == "Active" && $iDomainStatus <> 0) {
        echo '<td style="font-size:8pt;color:black;white-space: nowrap">&raquo; Active in WHMCS, <strong>Suspended in Plesk</strong>.</td>'; 
        echo '<td><div id="pleskUnsuspendOut' . $iCnt . '"> <input type="button" value="Plesk: `Unsuspend`"  id="pleskUnsuspendBtn' .$iCnt. '" style="color:#9f1fe1" onClick="ChangeAccountStatusPlesk(\'pleskUnsuspendOut'.$iCnt. '\',\'cid=' . $iClientId  . '&did=' . $iDomainId  . '&secure=' . $row['secure'] . '&suspend=0&ip='.$strIp.'&l=' . $_POST['login_name'] . '&p='.urlencode($_POST['passwd']).'\')"></div></td>';     
      } 
      else{
        echo '<td></td><td style="font-size:8pt;color:black;">' . $strSolutionText. '</td>';
      }

      echo "</tr>\n";

    } //endforeach from plesk API request for domains

    echo "</table>\n<br /><br />";

    if ($iCnt == 0) echo '<div align="center" style="padding-top:50px;padding-bottom:90px;color:red;font-weight:bold">( Sorry, there are no client domains accounts within this range... )</div><br />';
    else echo '<div style="color:black"><i>(<strong>' . $iCnt . '</strong> of <strong>' .  $iMaxResultsPerPage . '</strong>), client domain accounts found within this range.</i></div><br />';
      	  	
    showNavigationButtons($iPageNumber, $_POST['ip'], $_POST['login_name'], $_POST['passwd'], $_POST['secure']);	
       
		 /*
		 // collect some statistics  
		 echo '&rArr; <span style="color:black">Total Hosting Accounts in Plesk: <strong>' . $iCnt . '</strong></span><br />';
		 //echo '&rArr; <span style="color:black">Total Hosting Accounts in WHMCS: <strong>' .  '</strong></span><br />';
		 if ($iCountMissingFromWHMCS > 0) echo '<br /><br />&rArr; <span style="color:black">Total Hosting Accounts Missing from WHMCS: <span style="font-weight:bold;color:red">'.$iCountMissingFromWHMCS.'</span></span><br />';
		  
				   $result_count = mysql_query("SELECT  COUNT(distinct `tblhosting`.id) AS `count` FROM `tblhosting` WHERE domainstatus = 'Suspended' AND server = 9");              
		                   $row_count = mysql_fetch_assoc($result_count);
				   $iTotalSuspendedAccountsWHMCS = $row_count['count'];
		                   
		 //if ($iTotalSuspendedAccountsWHMCS != $iCountSuspendedInPlesk) echo "FIX THOSE";
		  
		 echo '&rArr; <span style="color:black">Total Hosting Accounts Suspended in Plesk: <strong>' . $iCountSuspendedInPlesk . '</strong></span><br />';
		 echo '&rArr; <span style="color:black">Total Hosting Accounts Suspended in WHMCS: <strong>' . $iTotalSuspendedAccountsWHMCS . '</strong></span><br /><br />';
		 */
     
  } //end if($post) else portion
  
  echo '<br /><center><hr width="80%" style="color:grey" /><div style="padding-bottom:25px;color:grey">Plesk Sync</a></div></center>';
	
} //end module output function



/***
 * HELPER FUNCTIONS
 */

// --------------------
function bytesToSize1024($bytes = 0, $precision = 2) {
	
	$unit = array('B','KB','MB','GB','TB','PB','EB');
	return @round($bytes / pow(1024, ($i = floor(log($bytes, 1024)))), $precision).' '.$unit[$i];
}
// --------------------
function getViewDomainsButton($ip, $login, $password, $secure, $button_text = 'Browse Accounts', $page = 0) {
	
	        $strHtml  = '<form action="'.$modulelink.'" method="post">';	      
	        $strHtml .= '<input name="login_name" value="'.$login.'" type="hidden"><input name="passwd" value="'.urldecode($password).'" type="hidden">';
	        $strHtml .= '<input name="ip" value="'.$ip.'" type="hidden"><input name="secure" value="'.$secure.'" type="hidden"><input name="page" value="' . $page . '" type="hidden">';
	        $strHtml .= '<input value="' . $button_text . '" type="submit"></form>';
		
		return $strHtml;
}
// --------------------
function showNavigationButtons($page_number, $ip, $login, $password, $secure) {	
			
	echo '<table border="0" align="center" width="40%"><tr>';
	if ($page_number != 0) echo '<td align="center">' . getViewDomainsButton($ip, $login, $password, $secure, '&laquo; Previous Page (' . $page_number . ')', ($page_number - 1) ) . '</td>';
	echo '<td align="center" style="color:black;font-weight:bold">[ Page (' . ($page_number + 1) . ') ]</td>';
	echo '<td align="center">' . getViewDomainsButton($ip, $login, $password, $secure, 'Next Page (' . ($page_number + 2) . ') &raquo;', ($page_number + 1) )  . '</td>';				
	echo '</table><br />';
		
}

?>