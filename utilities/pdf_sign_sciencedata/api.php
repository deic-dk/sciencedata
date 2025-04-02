<?php

$action = $_GET['action'];
$user = $_GET['user'];
$user_server_url = $_GET['user_server_url'];
$dir = $_GET['dir'];
$filename = $_GET['filename'];
$basename = basename($filename);
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
		$reqStr = "curl -u $user: --insecure $user_server_url/remote.php/getkey | jq -r .data.private_key > $prefix/$user.key";
		exec($reqStr, $output, $ret);
		if($ret!=0){
			header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
			echo json_encode(array('data' => array('message'=>'Problem getting private key. '.serialize($output)), 'status'=>'error'));
			break;
		}
		# Fetch user's public certificate
		$reqStr = "curl --insecure $user_server_url/remote.php/getcert?user=$user | jq -r .data.certificate > $prefix/$user.crt";
		exec($reqStr, $output, $ret);
		if($ret!=0){
			header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
			echo json_encode(array('data' => array('message'=>'Problem getting user certificate. '.serialize($output)), 'status'=>'error'));
			break;
		}
		# Fetch PDF
		$pdfUrl = $user_server_url.preg_replace("|/+|", "/", "/files/$dir/$filename");
		$reqStr = "curl -u $user: --insecure $pdfUrl > $prefix/$filename";
		exec($reqStr, $output, $ret);
		if($ret!=0){
			header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
			echo json_encode(array('data' => array('message'=>'Problem getting PDF. '.serialize($output)), 'status'=>'error'));
			break;
		}
		$reqStr = "java -jar /var/lib/caddy/open-pdf-sign.jar --page -1 --image /var/lib/caddy/sciencedata_signature.png --hint 'Check the validity of this signature at sciencedata.dk' --input $prefix/$filename --output $prefix/$basename.signed.pdf --certificate $prefix/$user.crt --key $prefix/$user.key";
		exec($reqStr, $output, $ret);
		$size = filesize("$prefix/$basename.signed.pdf");
		if($ret==0 && $size>0){
			// Output the signed PDF
			header("Content-Type: application/pdf");
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
		$pdfUrl = $user_server_url.preg_replace("|/+|", "/", "/files/$dir/$filename");
		$reqStr = "curl -u $user: --insecure $pdfUrl > $prefix/$filename";
		exec($reqStr, $output, $ret);
		if($ret!=0){
			header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
			echo json_encode(array('data' => array('message'=>'Problem getting PDF. '.serialize($output)), 'status'=>'error'));
			break;
		}
		$reqStr = "pdfsig $prefix/$filename";
		exec($reqStr, $output, $ret);
		if(empty($output)){
			header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
			echo json_encode(array('data' => array('message'=>'Problem getting signature info. '), 'status'=>'error'));
		}
		else{
			echo json_encode(array('data' => array('retval'=>$ret, 'message'=>'Got signing info',
				'info'=>implode("\n", str_replace('.'.$prefix, '', $output))), 'status'=>'success'));
		}
		break;
	default:
		echo json_encode(array('data' => array('message'=>'No action'), 'status'=>'error'));
}

// Clean up
/*foreach(glob("$prefix/*") as $f) {
	unlink($f);
}*/
unlink($prefix);


