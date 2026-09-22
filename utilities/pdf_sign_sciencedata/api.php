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

// Normalize a DN for comparison: split components, strip spaces, sort, rejoin.
// (Replaces the old bash/awk pipeline whose parse broke with newer pdfsig output.)
function normalize_dn($dn){
	$parts = array_map('trim', explode(',', trim($dn)));
	$parts = array_filter($parts);
	$parts = array_map(function($p){ return str_replace(' ', '', $p); }, $parts);
	sort($parts);
	return implode(',', $parts);
}

// Prefix for downloads so users don't overwrite each others files.
$prefix = ''.md5(uniqid(mt_rand(), true));
mkdir($prefix);
switch($action){
	case "sign":
		# Fetch user's private key
		$output = [];
		$reqStr = "curl -u $user: --insecure $user_server_url/remote.php/getkey | jq -r .data.private_key > \"$prefix/$user.key\"";
		exec($reqStr, $output, $ret);
		if($ret!=0){
			header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
			echo json_encode(array('data' => array('message'=>'Problem getting private key. '.serialize($output)), 'status'=>'error'));
			break;
		}
		# Fetch user's public certificate
		$output = [];
		$reqStr = "curl --insecure $user_server_url/remote.php/getcert?user=$user | jq -r .data.certificate > \"$prefix/$user.crt\"";
		exec($reqStr, $output, $ret);
		if($ret!=0){
			header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
			echo json_encode(array('data' => array('message'=>'Problem getting user certificate. '.serialize($output)), 'status'=>'error'));
			break;
		}
		# Fetch PDF
		$pdfUrl = $user_server_url.preg_replace("|/+|", "/", "/files/".rawurlencode($dir)."/".rawurlencode($filename));
		$output = [];
		$reqStr = "curl -u $user: --insecure \"$pdfUrl\" > \"$prefix/$filename\"";
		exec($reqStr, $output, $ret);
		if($ret!=0){
			header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
			echo json_encode(array('data' => array('message'=>'Problem getting PDF. '.serialize($output)), 'status'=>'error'));
			break;
		}

		// Existing signatures: parse pdfsig ONCE, in PHP (the old bash/awk DN
		// pipeline broke with newer pdfsig output formatting).
		$output = [];
		exec("cd \"$prefix\" && pdfsig \"$filename\" 2>/dev/null", $output, $ret);
		$nSigs = 0;
		$signerDns = [];
		foreach($output as $line){
			if(preg_match('/^Signature #\d+/', trim($line))){
				$nSigs++;
			}
			if(preg_match('/Signer full Distinguished Name:\s*(.+)$/', $line, $m)){
				$signerDns[] = normalize_dn($m[1]);
			}
		}

		// Refuse a second signature by the SAME person.
		$output = [];
		exec("openssl x509 -in \"$prefix/$user.crt\" -noout -subject 2>/dev/null", $output, $ret);
		$mySubject = normalize_dn(preg_replace('/^subject=\s*/', '', $output[0] ?? ''));
		if($mySubject !== '' && in_array($mySubject, $signerDns, true)){
			header($_SERVER['SERVER_PROTOCOL'] . " 400 Bad Request", true, 400);
			echo json_encode(array('data' => array('message'=>'You have already signed this document.'), 'status'=>'error'));
			break;
		}

		// Timestamping: the caller asks with ?ts=<non-empty>, and the deployment
		// names the authority with TSA_URL in the environment (pdfsign.yaml, passed
		// to PHP by the Caddyfile's php_fastcgi env line). --timestamp alone gives
		// PAdES Baseline-T. NOT --baseline-lt: that profile embeds revocation data
		// and DSS refuses outright, because our CA publishes neither CRL nor OCSP.
		$ts  = !empty($_GET['ts']);
		$tsa = getenv('TSA_URL');
		$tsaArgs = ($ts && !empty($tsa))
			? " --timestamp --tsa " . escapeshellarg($tsa)
			: "";
		if ($ts && empty($tsa)) {
			error_log("pdf_sign: ts requested but TSA_URL is not set; signing without a timestamp");
		}
		// The visible block's time row is the signing machine's clock, not the
		// authority's, so it must not be called "Timestamp" when a real timestamp
		// exists. The hint says there is one and where to check it; the attested
		// time itself cannot go on the stamp, which is fixed before the token exists.
		$labelArgs = $tsaArgs !== "" ? " --label-timestamp 'Signed at'" : "";
		$hint = ($tsaArgs !== "" ? "Signed at sciencedata.dk and timestamped by its timestamp authority. " : "")
			. "This document may carry more than one signature; only the first is shown here. Check the validity of all signatures at sciencedata.dk";

		// ONE visible stamp per document, owned by the FIRST signature. Later
		// signatures are added invisibly (equally valid PAdES; every reader's
		// signature panel and our Verify action list them all). A previous stamp
		// can never be removed or edited (it is the earlier signature's own
		// annotation — touching it invalidates that signature).
		$stamp = "";
		if($nSigs == 0){
			$stamp = "--page -1 --left 1 --top 1 --width 10" . $labelArgs
				." --image /var/lib/caddy/sciencedata_signature.png"
				." --hint '".$hint."'";
		}

		// --certification not-certified = APPROVAL signature, so the document can
		// be signed by several people. $tsaArgs lives in the closure, not in
		// $stamp, so every path keeps the timestamp: later (invisible) signatures
		// and the overlap fallback below that signs with no stamp at all.
		$javaCmd = function($stampArgs) use ($prefix, $filename, $basename, $user, $tsaArgs) {
			return "cd \"$prefix\" && java -jar /var/lib/caddy/open-pdf-sign.jar $stampArgs"
				." --certification not-certified" . $tsaArgs
				." --input \"$filename\" --output \"out_$basename.signed.pdf\""
				." --certificate \"$user.crt\" --key \"$user.key\" 2>&1";
		};
		$output = [];
		exec($javaCmd($stamp), $output, $ret);
		// DSS refuses to place a signature field over ANY existing annotation
		// (hyperlinks etc.), so even the first stamp can collide on documents
		// with links near the stamp position. Fall back to an INVISIBLE
		// signature — equally valid PAdES, just no visual mark on this document.
		if($stamp !== "" && $ret!=0 && strpos(implode("\n", $output), 'overlaps with an existing annotation') !== false){
			$output = [];
			exec($javaCmd(""), $output, $ret);
		}
		$size = @filesize("$prefix/out_$basename.signed.pdf");
		if($ret==0 && $size>0){
			// Output the signed PDF
			header("Content-Type: application/pdf");
			header("Content-Length: $size");
			header("Content-Transfer-Encoding: Binary");
			header("Content-disposition: attachment; filename=\"$basename.signed.pdf\"");
			readfile("$prefix/out_$basename.signed.pdf");
		}
		else{
			header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
			echo json_encode(array('data' => array('message'=>'Problem signing PDF. '.serialize($output)), 'status'=>'error'));
		}
		break;
	case "verify":
	# Fetch PDF
		$pdfUrl = $user_server_url.preg_replace("|/+|", "/", "/files/".rawurlencode($dir)."/".rawurlencode($filename));
		$output = [];
		$reqStr = "curl -u $user: --insecure \"$pdfUrl\" > \"$prefix/$filename\"";
		exec($reqStr, $output, $ret);
		if($ret!=0){
			header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
			echo json_encode(array('data' => array('message'=>'Problem getting PDF. '.serialize($output)), 'status'=>'error'));
			break;
		}
		$output = [];
		$reqStr = "cd \"$prefix\" && pdfsig \"$filename\"";
		exec($reqStr, $output, $ret);
		if(empty($output)){
			header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
			echo json_encode(array('data' => array('message'=>'Problem getting signature info. '), 'status'=>'error'));
			break;
		}
		echo json_encode(array('data' => array('info'=>implode("\n", $output)), 'status'=>'success'));
		break;
}
// Clean up
exec("rm -rf \"$prefix\"");
