<?php

$locale='en_US.UTF-8';
setlocale(LC_ALL,$locale);
putenv('LC_ALL='.$locale);

$action = $_GET['action'];
$user = $_GET['user'];
$user_server_url = $_GET['user_server_url'];
$dir = $_GET['dir'];
$dir = trim($dir, "/");
$filename = $_GET['filename'];
$filename = iconv(mb_detect_encoding($filename, mb_detect_order(), true), "UTF-8", $filename);
$basename = basename($filename, ".pdf");
$basename = basename($basename, ".signed");
$output = [];
$ret = "";
if(empty($user)){
	exit -1;
}
// Prefix for downloads so users don't overwrite each others files.
$prefix = ''.md5(uniqid(mt_rand(), true));
mkdir($prefix);
switch($action){
	case "sign":
		# Fetch user's private key
		$reqStr = "curl -u $user: --insecure $user_server_url/remote.php/getkey | jq -r .data.private_key > \"$prefix/$user.key\"";
		exec($reqStr, $output, $ret);
		if($ret!=0){
			header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
			echo json_encode(array('data' => array('message'=>'Problem getting private key. '.serialize($output)), 'status'=>'error'));
			break;
		}
		# Fetch user's public certificate
		$reqStr = "curl --insecure $user_server_url/remote.php/getcert?user=$user | jq -r .data.certificate > \"$prefix/$user.crt\"";
		exec($reqStr, $output, $ret);
		if($ret!=0){
			header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
			echo json_encode(array('data' => array('message'=>'Problem getting user certificate. '.serialize($output)), 'status'=>'error'));
			break;
		}
		# Fetch PDF
		$pdfUrl = $user_server_url.preg_replace("|/+|", "/", "/files/".rawurlencode($dir)."/".rawurlencode($filename));
		$reqStr = "curl -u $user: --insecure \"$pdfUrl\" > \"$prefix/$filename\"";
		exec($reqStr, $output, $ret);
		if($ret!=0){
			header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
			echo json_encode(array('data' => array('message'=>'Problem getting PDF. '.serialize($output)), 'status'=>'error'));
			break;
		}
		// Check if already signed by user
		$reqStr = "bash -c \"mysubject=`openssl x509 -in $prefix/*.crt -noout -subject | sed -E 's|^subject=||' | tr -s ',' '\n' | sort | tr -s '\n' ',' | sed 's/,$//g' | sed 's| ||g'`; pdfsubject=`pdfsig $prefix/*.pdf | grep 'Signer full Distinguished Name' | grep \"\\\$mysubject\" | awk -F: '{print $NF}'  | tr -s ',' '\n' | sort | tr -s '\n' ',' | sed s/,$//g | sed 's| ||g'`; echo \"\\\$mysubject\" == \"\\\$pdfsubject\"; [ \"\\\$mysubject\" != \"\" -a \"\\\$mysubject\" = \"\\\$pdfsubject\" ]\"";
		exec($reqStr, $output, $ret);
		if($ret==0){
			header($_SERVER['SERVER_PROTOCOL'] . " 400 Bad Request", true, 400);
			echo json_encode(array('data' => array('message'=>'You have already signed this document. '.serialize($output)), 'status'=>'error'));
			break;
		}
		// Check if already signed by others
		$stamp = "--page -1 --image /var/lib/caddy/sciencedata_signature.png --hint 'Check the validity of this signature at sciencedata.dk'";
		$reqStr = "bash -c \"[[ `pdfsig $prefix/*.pdf | grep -E 'Signature #' | wc -l` > 0 ]]\"";
		exec($reqStr, $output, $ret);
		if($ret==0){
			$stamp = "";
		}
		// Sign
		$reqStr = "cd \"$prefix\" && java -jar /var/lib/caddy/open-pdf-sign.jar $stamp --input \"$filename\" --output \"$basename.signed.pdf\" --certificate $user.crt --key $user.key";
		exec($reqStr, $output, $ret);
		$size = filesize("$prefix/$basename.signed.pdf");
		if($ret==0 && $size>0){
			// Output the signed PDF
			header("Content-Type: application/pdf");
			//header("Content-Type: application/octet-stream");
			header("Content-Length: $size");
			header("Content-Transfer-Encoding: Binary");
			header("Content-disposition: attachment; filename=\"$basename.signed.pdf\"");
			readfile("$prefix/$basename.signed.pdf");
		}
		else{
			header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
			echo json_encode(array('data' => array('message'=>'Problem signing PDF. '.serialize($output)), 'status'=>'error'));
		}
		break;
	case "verify":
	# Fetch PDF
		$pdfUrl = $user_server_url.preg_replace("|/+|", "/", "/files/".rawurlencode($dir)."/".rawurlencode($filename));
		$reqStr = "curl -u $user: --insecure \"$pdfUrl\" > \"$prefix/$filename\"";
		exec($reqStr, $output, $ret);
		if($ret!=0){
			header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
			echo json_encode(array('data' => array('message'=>'Problem getting PDF. '.serialize($output)), 'status'=>'error'));
			break;
		}
		$reqStr = "cd \"$prefix\" && pdfsig \"$filename\"";
		exec($reqStr, $output, $ret);
		if(empty($output)){
			header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
			echo json_encode(array('data' => array('message'=>'Problem getting signature info. '), 'status'=>'error'));
		}
		else{
			echo json_encode(array('data' => array('retval'=>$ret, 'message'=>'Got signing info',
					'info'=>implode("\n", $output)), 'status'=>'success'));
		}
		break;
	default:
		echo json_encode(array('data' => array('message'=>'No action'), 'status'=>'error'));
}

// Clean up
foreach(glob("/var/www/$prefix/*") as $f) {
	unlink($f);
}
rmdir("/var/www/$prefix");
//$reqStr = "rm -rf /var/www/$prefix";
//exec($reqStr, $output, $ret);



