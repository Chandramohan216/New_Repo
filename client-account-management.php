<?php
ob_start();	
session_start();
/*
Template Name: Client Account Management

Developed By: Supra Int.
Modify Date: 19-Sep-2014   
Section: Client Account Management
Description: Client Account Management like password details, credit/checking account information.
*/
//print_r($_SESSION);
include("stripe/config.php");
include("email/class.phpmailer.php");
$site_path= get_site_url();
$_SESSION['alert-message']='';
$_SESSION['alert-messagecredit']='';
get_header();
?>
<link href="<?php bloginfo('template_directory'); ?>/css/bootstrap.css" rel="stylesheet" type="text/css" />
<link href="<?php bloginfo('template_directory'); ?>/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
<link href="<?php bloginfo('template_directory'); ?>/css/bootstrap-theme.min.css" rel="stylesheet" type="text/css" />
<link href="<?php bloginfo('template_directory'); ?>/css/bootstrap-theme.css" rel="stylesheet" type="text/css" />
<link href="<?php bloginfo('template_directory'); ?>/css/bootstrap-theme.css.map" rel="stylesheet" type="text/css" />
<script src="<?php bloginfo('template_directory'); ?>/js/bootstrap.js" type="text/javascript"></script>
<script src="<?php bloginfo('template_directory'); ?>/js/custom.js" type="text/javascript"></script>
<link href="<?php bloginfo('template_directory'); ?>/css/style1.css" rel="stylesheet" type="text/css" />
<?php 
$uid	=	$_SESSION['je_userid'];

//From Client Listing Page
if($_GET['client']!='')
{
	 $uid	=	$_GET['client'];
}
if($uid=='')
{
	echo "<script type='text/javascript'>window.location.href='".$site_path."/client-login/';</script>";
}
$client_id=$wpdb->get_var("select client_id from je_client_master where je_user_id='".$uid."'");
$sel_all_invoices	=	$wpdb->get_results("select auto_inv,invoice_id,due_date,bill_to_client,status from je_approve_timelog where client_id='".$client_id."' group by auto_inv order by auto_inv desc");



$sel_mngrname=mysqli_fetch_array($mysqli->query("select fname from je_manager_master where je_users_id='".get_current_user_id()."'"));

if($_SESSION['je_usertype']!='C'){
//Check Accounting Permissions
$mgrid	=	mysqli_fetch_array($mysqli->query("select jmp.permissions,jmm.manager_id,jmm.je_users_id from je_manager_master jmm left join je_manager_permissions jmp on jmm.manager_id=jmp.manager_id where jmm.je_users_id='".get_current_user_id()."'"));
$permission	=	explode('|',$mgrid['permissions']);
//print_r($permission);	
	if($permission[0]=='accounting permission')
	{
		$acc_perm	=	'true';	
	}else
	{
		$acc_perm	=	'false';
	}
}else if($_SESSION['je_usertype']=='C'){$acc_perm	=	'true';}

if($_SESSION['je_usertype']=='M'){$ment_perm	=	'true';}
if($_SESSION['je_usertype']=='MG'){$acc_perm	=	'true';}

//Update Client Info.
if($_POST['action']=='editclientinfo')
{
	$carrieremail=str_replace("-","",$_POST['mbphone']).$_POST['carrier'];
	//print_r($carrieremail);
	$update_client_info	=	$mysqli->query("update je_client_master set ac_street_address1='".protect_data($_POST['streetaddr'])."',ac_street_address2='".protect_data($_POST['streetaddr1'])."',ac_city='".protect_data($_POST['city'])."',ac_state='".protect_data($_POST['state'])."',ac_zip='".protect_data($_POST['zipcode'])."',home_phone='".protect_data($_POST['homephone'])."',cell_phone='".protect_data($_POST['mbphone'])."',emailid='".protect_data($_POST['pemail'])."',je_carrier_email='".$carrieremail."' where je_user_id='".$uid."'");
	
	//Update user_login in je_users
	//echo $_SESSION['je_client_email'];
	
	
	
	if($_SESSION['je_client_email']!=$_POST['pemail'])
	{
	  //echo $_SESSION['je_client_email'];
	 // echo $_POST['pemail'];
		$checkemail=mysqli_fetch_array($mysqli->query("select count(*) as mailcount from je_users where user_login='".$_POST['pemail']."'"));
		//print_r($checkemail);
		if($checkemail['mailcount']>0)
		{
			$_SESSION['alert-message'] = "'".protect_data($_POST['pemail'])."' this email address already exists, please enter another.";
		}	
		else
		{
			$rw_stripedata=$mysqli->query("Select client_id,client_stripe_id,client_emailid from je_stripe_clients where client_id='".$uid."'");
			
					if(mysqli_num_rows($rw_stripedata)>0)
					{
						$rw_custdata=mysqli_fetch_array($rw_stripedata);
						$update_customer = \Stripe\Customer::retrieve($rw_custdata['client_stripe_id']);
						$update_customer->email = protect_data($_POST['pemail']);
						$update_customer->save();
						$mysqli->query("update je_stripe_clients set client_emailid='".protect_data($_POST['pemail'])."' where client_id='".$uid."'");
						
					}//end if

			$mysqli->query("update je_client_master set emailid='".protect_data($_POST['pemail'])."' where je_user_id='".$uid."'");
			$update_userlogin	=	$mysqli->query("update je_users set user_login='".protect_data($_POST['pemail'])."',user_email='".protect_data($_POST['pemail'])."' where ID='".protect_data($uid)."'");
			//echo "update je_users set user_login='".protect_data($_POST['pemail'])."',user_email='".protect_data($_POST['pemail'])."' where ID='".protect_data($uid)."'";
			$_SESSION['je_client_email']  = protect_data($_POST['pemail']); 
			$hideid=$uid;
			$mailemail=$_POST['pemail'];
			$mailfname=$_POST['cname'];
			$carriermail=$carrieremail;
			include('email/client-acc-modification.php');
			$_SESSION['alert-message']		=	'Information Updated Successfully!';
		}
	}
	else
	{
		$_SESSION['alert-message']		=	'Information Updated Successfully!';
	}
	
	//Action Item INSERT/UPDATE Into je_posts AND je_postmeta
	$sel_clientid=$mysqli->query("Select ID,post_author,post_title from je_posts where post_title='Client-".$uid." Action Item'");
	if(mysqli_num_rows($sel_clientid)>0)
	{
		$sel_cli_res=mysqli_fetch_array($sel_clientid);
		update_post_meta($sel_cli_res['ID'],'_ai_status','0');
	}
	else
	{
		$postcont	=	protect_data($_POST['cname'])."(".$_POST['cid'].") has changed account information.
Please review.";
		$insert_action_item	=	$mysqli->query("INSERT INTO `iceposse`.`je_posts` (`post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_title`, `post_excerpt`, `post_status`, `comment_status`, `ping_status`, `post_password`, `post_name`, `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`, `post_content_filtered`, `post_parent`, `guid`, `menu_order`, `post_type`, `post_mime_type`, `comment_count`, `post_icon`) VALUES ('".$uid."', '".date('y-m-d H:i:s')."', '".date('y-m-d H:i:s')."', '".$postcont."', 'Client-".$uid." Action Item', '', 'publish', 'open', 'open', '', '', '', '', '".date('y-m-d H:i:s')."', '".date('y-m-d H:i:s')."', '', '0', '', '0', 'action-item', '', '0', '');");
		$post_id	=	mysqli_insert_id($mysqli);
		
		$sel_acc_id	=	$mysqli->query("select jmm.je_users_id as accid,jmm.manager_type,jmm.manager_id,jmm.fname,jmm.lname,jmm.service_areas,jmp.permissions from je_manager_master jmm,je_manager_permissions jmp where FIND_IN_SET('".protect_data($_POST['state'])."', jmm.service_areas) and jmp.permissions LIKE '%general accounting%' and jmp.manager_id=jmm.manager_id");
		while($managers	=	mysqli_fetch_array($sel_acc_id))
		{
			update_post_meta($post_id,'_ai_status','0');
			update_post_meta($post_id,'_ai_assign',$managers['accid']);	
		}	
	}
}

//Update Password Details
if($_POST['action']=='changeclientpass')
{
	if($_POST['currpass']==$_POST['current-pass'])
	{
	$encrypted_pass = md5_encrypt($_POST['newpass'],$encryption_password,16);
	$update_client_info	=	$mysqli->query("update je_users set user_pass='".protect_data($encrypted_pass)."' where ID='".$uid."'");
	$_SESSION['alert-message']	=	'Password Updated Successfully!';
	}
	else
	{
		$_SESSION['alert-message']	=	'Current Password Does Not Match!';
	}
}

//Select Client Info.
	$sel_client_details	=	mysqli_fetch_array($mysqli->query('select * from je_client_master as jcm, je_users as ju where  jcm.je_user_id=ju.ID and je_user_id='.$uid));	
	//print_r($sel_client_details);
	$decrypted_pass = md5_decrypt($sel_client_details['user_pass'],$encryption_password,16);
	$_SESSION['cli-email']	=	$sel_client_details['user_email'];
	
//Display Credit card Info In Popup
	$client_cc=$mysqli->query('select id,client_id,card_name,card_type,card_number,exp_date,cvv from je_client_cc where client_id='.$uid);
	$ccrows	=	mysqli_num_rows($client_cc);
	$credit	=	mysqli_fetch_array($client_cc);
	
//Display Checking Account Info In Popup
	$select_checking	=	$mysqli->query('select id,client_id,name,bank_routing_no,checking_ac_no,driver_license_no,state from je_client_checking_account where client_id='.$uid);
	
if($_GET['max']!=''){$max=$_GET['max'];}else{$max=20;}
if (isset($_GET["start"]) && $_GET["start"] >0) { $start  = $_GET["start"]; } else { $start=1; };
$start = ($start-1) * $max; 


if($_POST['action']=='searchwo')
{
  if(protect_data($_POST['search'])!="")
  {
	 $keyword= trim($_POST['search']); 
	 $work_codi ="and jwo.wo_id like '%".$keyword."%'";
  }
}
else
{
	$work_codi='';	
}

//Work Order Listing	
$work_order1	=	"select jwo.id,jwo.wo_id,jwo.client_id,jwo.service_type_id,jwo.order_date,jwo.service_date_requested,jwo.service_start_time,jwo.preferred_associate,jwo.wo_status,jt.term_id,jt.name from  je_work_order jwo,je_terms jt where jwo.client_id='".$sel_client_details['client_id']."' and  jwo.service_type_id=jt.term_id and jwo.wo_status!='' $work_codi order by jwo.id desc";
$work_order	=	$mysqli->query($work_order1.' limit '.$start.','.$max.'');	
?>
<script type="text/javascript" src="<?php bloginfo('template_directory');?>/js/client-account-management-validate.js"></script>
<script type="text/javascript">
function checkpassvalid(pass)
{
var passw=  /^(?=.*\d)(?=.*[a-z]{2})(?=.*[0-9]{2})(?=.*[A-Z]{2}).{10,20}$/;  
var pass	=	$("#newpass").val();
if(pass.match(passw))   
{
	$("#err_renewpass").html('');
}
else
{
	$("#err_renewpass").html('Password Must match with the given condition.');
	$("#newpass").val('');
}
}

function isNumberKey(evt)
{
var charCode = (evt.which) ? evt.which : event.keyCode
if (charCode > 31 && (charCode < 48 || charCode > 57) )
return false;

return true;
}

$(document).ready(function($){
$("#zipcode").mask("99999");
$("#homephone").mask("999-999-9999");
$('#mbphone').mask("999-999-9999");
});

</script>
<style type="text/css">
.pad0{padding:0;}
#bankaccount{display:none;}
#accbtn{display:none;}
.simplebtn{border:none;background:none;font-size:14px;}
</style>
<div class="container">
<div class="row padtop marginbot" style="margin-left:0; margin-right:0;">
<div class="col-xs-12 col-sm-12 aligncenter boxredtext">
  <h2>MY ACCOUNT MANAGEMENT</h2>
</div>
</div>
</div>
<div class="container">
<div id="client-account">
<div class="col-xs-12 col-sm-12"><p class="rightred"><?=$_SESSION['alert-message']?></p></div>
<div class="row marginbot padtop" style="margin-left:0; margin-right:0;">
<div class="col-xs-12 col-sm-6 col-md-6 col-lg-6">
<div class="col-xs-12 col-sm-12 marginbot"><strong>(<?=ucfirst($sel_client_details['fname'])?> <?=ucfirst($sel_client_details['mname'])?> <?=ucfirst($sel_client_details['lname'])?>)</strong></div>
<div class="col-xs-12 col-sm-12 marginbot">(<?=ucfirst($sel_client_details['ac_street_address1'])?>)</div>
<?php if($sel_client_details['ac_street_address2']!=''){?>
<div class="col-xs-12 col-sm-12 marginbot">
(<?=ucfirst($sel_client_details['ac_street_address2'])?>)
</div>
<?php }?>
<div class="col-xs-12 col-sm-12 marginbot">(<?=$sel_client_details['ac_city']?>, <?=$sel_client_details['ac_state']?>, <?=$sel_client_details['ac_zip']?>)</div>

<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 marginbot" style="padding:0;">
<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 left-client-info"><strong>Client's ID#:</strong></div>
<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 marginbot right-client-info"><?=$sel_client_details['client_id']?></div></div>

<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 marginbot" style="padding:0;">
<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 left-client-info"><strong>Client Since:</strong></div>
<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 marginbot smallfont right-client-info"><?=date('m-d-Y h:i A',strtotime($sel_client_details['account_created_date']))?></div>
</div>

<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 marginbot" style="padding:0;">
<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 left-client-info"><strong>Mobile Phone:</strong></div>
<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 marginbot right-client-info"><?=$sel_client_details['cell_phone']?></div>
</div>

<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 marginbot" style="padding:0;">
<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 left-client-info"><strong>Other Phone:</strong></div>
<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 marginbot right-client-info">
<?=$sel_client_details['home_phone']?></div>
</div>

<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 marginbot" style="padding:0;">
<div class="col-xs-5 col-sm-6 col-md-6 col-lg-6 left-client-info"><strong>Personal E-mail:</strong></div>
<div class="col-xs-7 col-sm-6 col-md-6 col-lg-6 marginbot smallfont right-client-info"><?=$sel_client_details['emailid']?></div>
</div>
<?php if($acc_perm=='true'){?>
<div class="col-xs-12 col-sm-6">
<a href="#" data-toggle="modal" data-target="#editaccdetails">Edit Client Info</a>
</div>
<?php }?>
</div>

<form method="post" action="" name="change-client-pass" id="change-client-pass" onsubmit="return changepass()">
<input type="hidden" name="action" value="changeclientpass"/>
<input type="hidden" name="current-pass" value="<?=$decrypted_pass?>"/>
<div class="col-xs-12 col-sm-6 col-md-6 col-lg-6">

<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 marginbot" style="padding:0;">
<div class="col-xs-5 col-sm-5 col-md-6 col-lg-6 left-client-info"><strong>Log-in User Name:</strong></div>
<div class="col-xs-7 col-sm-6 col-md-7 col-lg-6 marginbot smallfont right-client-info"><?=$sel_client_details['user_login']?></div></div>

<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 marginbot" style="padding:0;">
<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 left-client-info"><strong>Log-in P/W:</strong></div>
<?php if($acc_perm=='true'){?>
<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 marginbot right-client-info"><?=$decrypted_pass?></div>
<?php }else{?>
<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 marginbot right-client-info">************</div>
<?php }?></div>


<div class="col-xs-12 col-sm-12 col-md-6 col-lg-6 chnage_pwd"><strong >Change P/W:</strong></div>

<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 marginbot" style="padding:0;">
<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 left-client-info">Type Current P/W</div>
<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 marginbot right-client-info"><input type="text" id="currpass" name="currpass" value="" class="col-sm-12 inputreg-large" onblur="return clearerror('err_currpass')"/><div id="err_currpass" class="rightred"></div><a href="javascript:void(0)" class="rightred" onclick="return passgen('newpass')">Suggest a New Secure Password</a></div></div>

<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 marginbot" style="padding:0;">
<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 left-client-info">Type New P/W</div>
<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 marginbot right-client-info"><input type="text" id="newpass" name="newpass" value="" class="col-sm-12 inputreg-large" onblur="checkpassvalid(this.value)"/></div>
</div>

<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 marginbot" style="padding:0;">
<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 left-client-info">Re-Type New P/W</div>
<div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 marginbot right-client-info"><input type="text" id="renewpass" name="renewpass" value="" class="col-sm-12 inputreg-large" onblur="return clearerror('err_renewpass')"/><div id="err_renewpass" class="rightred"></div></div></div>


<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 marginbot rightred">New P/W must be between 10-20 characters, and include at least 2 upper case letters, 2 lower case letters and 2 numbers.</div>

<div class="last-btn-submit">
<?php if($acc_perm=='true'){?>
<input type="submit" id="btn_submit"  name="savechanges" value="Save P/W Changes" />
<?php }?>
</div>
</div>
</form>

</div>
</div>
</div>

<?php if($_GET['client']!=''){?>
<!--Manager's Note Section-->
<div class="container">
<div class="row magrtopbot" style="margin-left:0; margin-right:0;">
<div class="col-xs-12 col-sm-12"><strong>Manager's Notes: </strong></div>
<div class="col-xs-12 col-sm-4 aligncenter"><input type="text" id="mgrmsg" name="todoitem" value="" class="inputreg-large" placeholder="Manager Notes"/><br /><span id="mgrmsgerr" class="rightred"></span></div>
<div class="col-xs-12 col-sm-2">
<button class="btn_redcancel col-sm-12" type="button" onclick="addnotes('addnotes','<?=get_current_user_id()?>','<?=$_GET['client']?>','<?=$_SESSION['je_usertype']?>','<?=$sel_mngrname['fname']?>');" style="margin:0;padding:3px;border-radius:4px;">Add</button>
</div>
</div>
<?php 
//Select Clients Notes
$selnotes	=	$mysqli->query("select manager,client,note,noteby,date(notes_date) as dt,time(notes_date) as tm from je_manager_client_notes where client='".$_GET['client']."' order by id desc");
?>
<div class="row magrtopbot" style="margin-left:0; margin-right:0;">
<div class="col-xs-12 col-sm-12" style="height:200px;overflow-y:scroll;" id="messages">
<?php 
while($row=mysqli_fetch_array($selnotes))
{
	$sel_mgrname=mysqli_fetch_array($mysqli->query("select fname from je_manager_master where je_users_id='".get_current_user_id()."'"));
	echo "<p class='marginbot'><strong>".ucfirst($sel_mgrname['fname'])." Wrote: </strong>".date('m-d-Y',strtotime($row['dt']))." @".date('H:i A',strtotime($row['tm']))." - ".ucfirst(stripslashes($row['note']))."</p>";	
}
?>
</div>
</div>
</div>
<!--END Manager's Note Section-->
<?php }?>

<div class="container">
<div id="client-account">
<div class="row padtop" style="margin-left:0; margin-right:0;">
<p class="rightred" id="notify"><?=$_SESSION['alert-messagecredit']?></p>
<div class="col-xs-12 col-sm-12 client-payment"><strong>Payment Information: </strong>
<?php if($acc_perm=='true'){?>
<a href="#" data-toggle="modal" data-target="#editpaymentdetails">(Edit Payment Accounts)</a>
<?php }?>
</div>
<?php if($acc_perm=='true'){?>
<div class="col-xs-12 col-sm-6" id="creditreplace">
<?php 
$select_credit1	=	$mysqli->query('select id,client_id,card_name,card_type,card_number,exp_date,cvv from je_client_cc where client_id='.$uid);$i=1;
while($payinfo	=	mysqli_fetch_array($select_credit1)){
	$decrypted_cc = md5_decrypt($payinfo['card_number'],$encryption_password,16);
	?>
<div class="col-xs-12 col-sm-12 rep1" id="credittoreplacepage<?=$payinfo['id']?>"><?php echo ucwords($payinfo['card_type'])?> **********<?=$decrypted_cc?> Ex. <?=$payinfo['exp_date']; ?></div>
<?php $i++;}?>
</div>
<div class="col-xs-12 col-sm-6" id="checkreplace">
<?php 
$select_checking1	=	$mysqli->query('select id,client_id,name,bank_routing_no,checking_ac_no,driver_license_no,state from je_client_checking_account where client_id='.$uid);$j=1;
while($checkinfo	=	mysqli_fetch_array($select_checking1)){?>
<div class="col-xs-12 col-sm-12" id="delc<?=$checkinfo['id']?>"><?php echo ucfirst($checkinfo['name'])?> ACH *****<?=substr($checkinfo['bank_routing_no'],10); ?></div>
<?php $j++;}?>
</div>
<?php }else{?>
<div class="row aligncenter rightred" style="margin-left:0; margin-right:0;">You are not allowed to view this information.</div>
<?php }?>
</div>
</div>
</div>
</div>
<div class="container marginbot">
<div class="row marginbot padtop" style="margin-left:0; margin-right:0;">

<div class="col-xs-12 col-sm-5 col-md-5 col-lg-5 boxredtext alignleft">
<input type="button" value="Work Orders" class="btn_red" name="btnlogin" id="btnlogin" style="font-size:19px; white-space:pre-line; margin:0 10px 0 0;"/>
<?php if($_SESSION['je_usertype']=='MG'){?>
<input type="button" value="Invoices" class="btn_black" name="btnlogin" id="btnlogin" style="font-size:19px; white-space:pre-line;margin:0;" onclick="location.href='/invoices?client=<?=$_GET['client']?>'"/>
<?php }else{?>
<input type="button" value="Invoices" class="btn_black" name="btnlogin" id="btnlogin" style="font-size:19px; white-space:pre-line;margin:0;" onclick="location.href='/invoices'"/>
<?php }?>
</div>

<form action="" method="post">
<input type="hidden" value="searchwo" name="action" />
<div class="col-xs-12 col-sm-1 col-md-1 col-lg-1 client-search">Search </div>
<div class="col-xs-12 col-sm-4 col-md-4 col-lg-4 btn-client-search-bar"><input type="text" id="search" name="search" value="" class="col-sm-12 inputreg-large" placeholder="Type Work Order Number"/></div>

<div class="col-xs-12 col-sm-2 col-md-2 col-lg-2 btn-client-search">
<input type="submit" name="submit" value="Search" class="btn_red col-xs-12 col-sm-12" style="padding:3px 0; font-size:19px; white-space:pre-line; margin:0;"/><br /><a href="/client-account-management">Reset</a>
</div>
</form>
</div>

<div class="row marginbot padtop" id="searchres" style="margin-left:0; margin-right:0;">      
<?php 
if(mysqli_num_rows($work_order)>0){
while($woresult	=	mysqli_fetch_array($work_order)){
if($woresult['wo_status']=='accepted')
 {
	 $class='redbg '; 
 }
 else if($woresult['wo_status']=='complete')
 {
	 $class=''; 
 }

$originalDate = $woresult['service_date_requested'];
$newDate = date("m-d-Y", strtotime($originalDate));
?>
<div class="client-account-section <?=$class?>">
  <div class="two-col-container back-red for-head">
           <div class="two-col-left">
				<?php 
				if($acc_perm=='true' || $ment_perm=='true'){
				if($_GET['client']!=''){	
				?>
				<h3><a href="/client-work-order?woid=<?=$woresult['id']?>" class="rightred" target="_blank">View Work Order</a></h3>
				<?php 
				}else
				{?>
				<h3><a href="/client-work-order?woid=<?=$woresult['id']?>" class="rightred">View Work Order</a></h3>	
				<?php }
				}?>
				</div>
           </div> <!-- con main -->
           
           
            <div class="two-col-container service-type-sec">
           <div class="two-col-left">
           <div class="two-col-left">
            <label>Work Order # </label>
           </div>
           <div class="two-col-right">
		   <span><?=$woresult['wo_id']?></span>
           </div>
           </div>
           
            <div class="two-col-right">
            <div class="two-col-left">
            <label>Client:</label>
           </div>
           <div class="two-col-right">
		   <span><?=ucfirst($_SESSION['je_nicename'])?></span>
           </div>
            </div>
            
             <div class="two-col-left">
           <div class="two-col-left">
            <label>Service Type:</label>
           </div>
           <div class="two-col-right">
		   <span><?=$woresult['name']?></span>
           </div>
           </div>
 <?php 
$pref_assoc	=	explode(',',$woresult['preferred_associate']);
for($pa=0;$pa<count($pref_assoc);$pa++)
{
	$seldetails	=	mysqli_fetch_array($mysqli->query("select fname,lname from je_manager_master where manager_id='".$pref_assoc[$pa]."'"));
	if($seldetails['fname']=='')
	{
		$seldetails	=	mysqli_fetch_array($mysqli->query("select team_name from je_team_master where team_id='".$pref_assoc[$pa]."'"));	
		$type	=	'Team';
		$name	=	$seldetails['team_name'];
	}
	else
	{
		$type	=	'Associate';
		$name	=	ucfirst($seldetails['fname']).' '.ucfirst($seldetails['lname']);
	}
?>
            <div class="two-col-right">
            <div class="two-col-left">
            <label><?=$type?>:</label>
           </div>
           <div class="two-col-right">
		   <span><?=$name?></span>
           </div>
            </div>
            <?php }?>
	<?php 
$sel_wo_date=mysqli_fetch_array($mysqli->query("select event_day,event_from,event_to from je_assoc_book_day where wo_id='".$woresult['wo_id']."'"));
?>
            <div class="two-col-left">
           <div class="two-col-left">
            <label>Scheduled Service Date:</label>
           </div>
           <div class="two-col-right"><span><?=$newDate?></span>
           </div>
           </div>
  
             <div class="two-col-left">
           <div class="two-col-left">
            <label>Scheduled Start Time:</label>
           </div>
           <div class="two-col-right"><span><?=date('h:i A',strtotime($sel_wo_date['event_from']))?></span>
           </div>
           </div>
       
</div>

         </div>  <!-- 2 con -section -->
     
	  <?php } }else{?>
<div class="row marginbot aligncenter rightred" style="margin-left:0; margin-right:0;"><h4>No Work Orders Found</h4></div>
<?php }?>
<div class="col-xs-12 col-sm-12 alignright pagination-sec"><? echo $pagingLink = getPagingLinkforHome($work_order1, $max,$mysqli,"");?></div>
</div>


<div class="modal fade" id="editaccdetails" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog" id="editdetailsModalone">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
      </div>
      <div class="modal-body">
 		<div class="row" style="margin-left:0; margin-right:0;">
        <div class="col-xs-12 col-sm-12 center" id="chnge" style="margin:0 0 2% 0;">
        <h4><strong>Manage Account Details</strong></h4>
        </div>
        <div class="col-xs-12 col-sm-12">
        <form action="" method="post" name="accountedit" id="accountedit" enctype="multipart/form-data"  onsubmit="return client_manage_account_details_validate_form();">
        <input type="hidden" name="cname" value="<?=$sel_client_details['fname']?>"/>
		<input type="hidden" name="cid" value="<?=$sel_client_details['client_id']?>"/>
        <input type="hidden" name="action" value="editclientinfo"/>
        <div class="row magrtopbot" style="margin-left:0; margin-right:0;">
        <div class="col-xs-12 col-sm-12">
    		<div class="col-xs-12 col-sm-8"><strong><?=ucfirst($sel_client_details['fname'])?> <?=ucfirst($sel_client_details['mname'])?> <?=ucfirst($sel_client_details['lname'])?></strong></div>
         </div>
	     </div>
         
         <div class="row" style="margin-left:0; margin-right:0;">
        <div class="col-xs-12 col-sm-12 magrtopbot">
    		<div class="col-xs-12 col-sm-6">
			<label class="titles">CURRENT STREET ADDRESS</label>
			<input type="text" id="streetaddr" name="streetaddr" value="<?=ucfirst($sel_client_details['ac_street_address1'])?>" class="col-sm-12 inputreg-large" placeholder="Current Street Address"/>
			<div id="err_streetaddr" class="redtext"></div></div>
            <div class="col-xs-12 col-sm-6">
			<label class="titles">STREET ADDRESS LINE 2</label>
			<input type="text" id="streetaddr1" name="streetaddr1" value="<?=ucfirst($sel_client_details['ac_street_address2'])?>" class="col-sm-12 inputreg-large" placeholder="Address Line 2"/></div>
         </div>
         <div class="col-xs-12 col-sm-12 magrtopbot">
         <div class="col-xs-12 col-sm-4">
		 <label class="titles">CITY</label>
		 <input type="text" id="city" name="city" value="<?=$sel_client_details['ac_city']?>" class="col-sm-12 inputreg-large" placeholder="City"/><div id="err_city" class="redtext"></div></div>
         <div class="col-xs-12 col-sm-4">
		 <label class="titles">STATE</label>
		 <input type="text" id="state" name="state" value="<?=$sel_client_details['ac_state']?>" class="col-sm-12 inputreg-large" placeholder="State" readonly="readonly"/><div id="err_state" class="redtext"></div></div>
         <div class="col-xs-12 col-sm-4">
		  <label class="titles">ZIPCODE</label>
		 <input type="zipcode" id="zipcode" name="zipcode" value="<?=$sel_client_details['ac_zip']?>" class="col-sm-12 inputreg-large" maxlength="5" placeholder="Zip Code"/><div id="err_zipcode" class="redtext"></div></div>
         </div>
         <div class="col-xs-12 col-sm-12 magrtopbot">
         <div class="col-xs-12 col-sm-4">
		  <label class="titles">HOME PHONE</label>
		 <input type="text" id="homephone" name="homephone" value="<?=$sel_client_details['home_phone']?>" class="col-sm-12 inputreg-large" placeholder="home phone number" onkeypress="return isNumberKey(event);" /><div id="err_homephone" class="redtext"></div></div>
         <div class="col-xs-12 col-sm-4">
		 <label class="titles">CELL PHONE</label>
		 <input type="text" id="mbphone" name="mbphone" value="<?=$sel_client_details['cell_phone']?>" class="col-sm-12 inputreg-large" placeholder="mobile phone number" onkeypress="return isNumberKey(event);"/>
		 <div id="err_mbphone" class="redtext"></div></div>
         <div class="col-xs-12 col-sm-4">
		 <label class="titles">PERSONAL E-MAIL</label>
		 <input type="text" id="pemail" name="pemail" value="<?=$sel_client_details['emailid']?>" class="col-sm-12 inputreg-large" placeholder="personal e-mail"/>
		 <div id="err_pemail" class="redtext"></div></div>
         </div>
         <div class="col-xs-12 col-sm-12 magrtopbot">
         <div class="col-xs-12 col-sm-4">
		 <label class="titles">CELL PHONE CARRIER</label>
		 <select id="carrier" name="carrier" class="col-sm-12 inputreg-large" style="float:right;">
            <option value="" selected="selected">Select Carrier</option>
            <?php
			$carr	=	explode("@",$sel_client_details['je_carrier_email']);
			$varmatch='@'.$carr[1];
            $rs_cr = $mysqli->query("select id,carrier,carrier_addr from je_carrier order by id");
            while($rw_cr = mysqli_fetch_array($rs_cr))
            {
            ?>
            <option value="<?=$rw_cr['carrier_addr']?>" <?php if($rw_cr['carrier_addr']==$varmatch){?> selected="selected" <?php }?> ><?=$rw_cr['carrier']?></option>
            <?php } ?>
            </select></div>
            </div>
            
	     </div>
         <div class="row" style="margin-left:0; margin-right:0;">
        <div class="col-xs-12 col-sm-12">
        <div class="col-xs-12 col-sm-8 rightred aligncenter"><strong>Please be advised:</strong><br />Your Account Address must match your registered Payment Method Account's Address.</div>
			<div class="col-xs-12 col-sm-4">
            <input type="submit" value="Update" class="box col-xs-12 col-sm-12" name="submit" id="submit" style="padding:8px 0; font-size:19px; white-space:pre-line;"/></div> 
		</div> 
        </div>
        </form>
        </div>
        
        </div>
        
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


<div class="modal fade" id="editpaymentdetails" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="margin-top:50px;">
  <div class="modal-dialog" id="editdetailsModalone">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
      </div>
      <div class="modal-body">
 		<div class="row marginbot" style="margin-left:0; margin-right:0;">
        <div class="col-xs-12 col-sm-12 aligncenter marginbot" id="chnge">
        <h4><strong>PAYMENT INFORMATION</strong></h4>
        <span>You must maintain at least one Active Account</span><br/>
        <span id="ajaxnotify" class="rightred"></span>
        </div>
        <div class="col-xs-12 col-sm-12">
        <div class="row" style="margin-left:0; margin-right:0;">
         <div class="col-xs-12 col-sm-6 alignleft marginbot"><strong>Cards</strong></div>
            <div class="col-xs-12 col-sm-3 aligncenter marginbot"><a href="javascript:void(0)" onclick="return creditbank('creditcard','addcc','addcheck')" style="color:#999;"><strong>Add Another</strong></a></div>
             <div class="col-xs-12 col-sm-3 aligncenter marginbot"><a href="javascript:void(0)" style="color:#999;" onclick="return showchangedef('<?=$all_invoices->auto_inv?>');"><strong>Change Default</strong></a></div>
            </div>
        </div>
        
         <div class="col-xs-12 col-sm-12 dnone marginbot" id="changedefault<?=$all_invoices->auto_inv?>">
                <div class="row" style="padding-left:10px;padding-right:10px;">
                 <div class="col-xs-12 col-sm-12" style="padding-bottom:10px; border:1px solid #ccc; border-radius:5px;padding-top:15px;">
                    <div class="col-xs-12 col-sm-12 aligncenter marginbot">
                         <h4><strong>Change default card</strong></h4>
                         <span>We'll use this card for invoice payments by default.</span><br />
                         <span id="ajaxsetchange" class="rightred"></span>
                    </div>
                     <div class="col-xs-12 col-sm-12 aligncenter">
                     <div class="col-xs-12 col-sm-6">
                          <?
			     $paychange=$mysqli->query("Select id,card_number,card_type,exp_date,is_default from je_client_cc where client_id='".$uid."'");
				  
				 ?>
                 <select id="cardddl<?=$all_invoices->auto_inv?>" name="cardddl" class="select-large">
                 <?
				  if(mysqli_num_rows($paychange)>0)
					 {
						 while($payreslt=mysqli_fetch_array($paychange))
						 { 
				$decrypted_cardnumber = md5_decrypt($payreslt['card_number'],$encryption_password,16); 
						 ?>
						 <option value="<?=$payreslt['id']?>" <? if($payreslt['is_default']==1){echo "selected";}?>><?=ucwords($payreslt['card_type'])?>&nbsp;&nbsp; ****<?=$decrypted_cardnumber?>&nbsp;&nbsp; <?=$payreslt['exp_date']?></option>
						 <? }//end while
					 }//end if
               ?>
               </select>
               		  </div>
                       <div class="col-xs-12 col-sm-6">
                         <input type="button" name="btn_changedefault" id="btn_changedefault" class="boxbutton col-xs-12 col-sm-8" value="Change Default Card" style="padding:8px 0; font-size:16px; white-space:pre-line;" onclick="return setdeafultcard('<?=$uid?>','<?=$all_invoices->auto_inv ?>');"/>
                     </div>
                     </div>
                    </div> 
                </div>
         </div>
        <div class="col-xs-12 col-sm-12">
        <div class="row" style="padding-left:10px;padding-right:10px;">
         <div class="col-xs-12 col-sm-12" id="cred" style="padding-bottom:10px; border:1px solid #ccc; border-radius:5px;padding-top:15px;">
        <?php 
		 $rwpaydata=$mysqli->query("Select id,card_number,card_type,exp_date,is_default from je_client_cc where client_id='".$uid."'");
		if(mysqli_num_rows($rwpaydata)>0){
				while($credit=mysqli_fetch_array($rwpaydata))
				{
					//print_r($credit);
				$decrypted_cardno = md5_decrypt($credit['card_number'],$encryption_password,16);
		?>
        <div class="col-xs-12 col-sm-12 marginbot card-info-custom" id="credittoreplace<?=$credit['id']?>">
         <div class="col-xs-12 col-sm-2">
         	<?=ucwords($credit['card_type'])?>
         </div>
         <div class="col-xs-12 col-sm-2 card-info-inner-sm">
         	****<?=$decrypted_cardno?> 
         </div>
		
         <div class="col-xs-12 col-sm-2">
         	<?=$credit['exp_date']?> 
         </div>
        <div class="col-xs-12 col-sm-2"><a href="javascript:void(0)" onclick=" creditbank('creditcard','update',''),selcreditinfo('<?=$credit['client_id']?>','<?=$decrypted_cardno?>','selcredit','<?=$credit['id']?>')">Edit</a></div>
        <div class="col-xs-12 col-sm-2"><a href="javascript:void(0)" class="rightred" onclick="return delcreditinfo('<?=$credit['client_id']?>','<?=$decrypted_cardno?>','creditdelete','<?=$credit['id']?>');">Delete</a></div>
         <div class="col-xs-12 col-sm-2 changdefault" id="setdeafult<?=$credit['id']?>">
         	<?
            if($credit['is_default']==1)
			{?>
				<div class="setdefault">DEFAULT</div>
			<? }//end if
			?> 
         </div>
        </div><br />
        <?php //} 
			}
		}
		?>
        </div>
        </div>
        </div>
       
        <div class="col-xs-12 col-sm-12">
         <div class="row" style="margin-left:0; margin-right:0;">
         <form action="" method="post" name="creditedit">
        <input type="hidden" name="action" value="addcc" id="cchidden"/>
        <input type="hidden" name="action" value="editcc" id="cchidden"/>
        <input type="hidden" name="hiddencardno" value="<?=$credit['id']?>" id="hiddencard"/>
        <div class="col-xs-12 col-sm-6 dnone" id="creditcard">
        <p><h4><strong>CREDIT OR DEBIT CARD:</strong></h4><span id="valerror" class="rightred"></span></p>
        <p>
        <div class="col-xs-12 col-sm-12 magrtopbot">
        <img src="<?php bloginfo('template_directory'); ?>/images/cards.png" class="img-responsive" alt="banner">
        <P><select id="ctype" class="select-large" name="cardtype"><option value="">Select Card Type</option><option value="visa">Visa</option><option value="master card">Master Card</option><option value="discover">Discover</option><option value="american exp">American Express</option></select></P></div>
        <div class="col-xs-12 col-sm-12 magrtopbot"><strong>Name on Card</strong><P><input type="text" id="cardname" name="cardname" value="" class="inputreg-large" autocomplete="off"/></P></div>
        <div class="col-xs-12 col-sm-12 magrtopbot"><strong>Card Number</strong><P><input type="text" id="carrdno" name="carrdno" value="" class="inputreg-large" maxlength="16" onkeypress="return isNumberKey(event)" autocomplete="off"/></P></div>
        <div class="col-xs-12 col-sm-12 magrtopbot"><strong>Expiration Date</strong>
        <P><select id="expmonth" name="expmonth" class="select-large col-sm-4" style="margin:0 7% 0 0;">
        <?php for($i=1;$i<=12;$i++){?>
        <option value="<?=$i?>"><?=$i?></option>
        <?php }?>
        </select><select id="expyear" name="expyear" class="select-large col-sm-4">
        <?php for($j=date('Y');$j<=date('Y')+10;$j++){?>
        <option value="<?=$j?>"><?=$j?></option>
        <?php }?>
        </select></P></div>
        <div class="col-xs-12 col-sm-12 magrtopbot"><strong>CVV #</strong>
        <P><input type="text" id="cvv" name="cvv" value="" class="inputreg-large col-sm-4" onkeypress="return isNumberKey(event)" maxlength="3" autocomplete="off"/></P></div></p>
        <div class="col-xs-12 col-sm-12"><input type="button" class="box col-xs-12 col-sm-12" name="savechanges" value="Save Details" style="padding:8px 0; font-size:19px; white-space:pre-line;" onclick="return updatecreditinsert('<?=$uid?>','<?=$all_invoices->auto_inv?>')"/></div>
        </div>
        </form>
        
        </div>
        </div>
        
        </div>
        
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
</div>
</div>

<?php get_footer();?>
