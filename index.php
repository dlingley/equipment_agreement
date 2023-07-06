<?php
$message = array();
$error = false;

// Process form submission
if ($_POST):
	// Validate email
	if (!preg_match("/\w+@(g\.)?domain\.edu/",$_POST["email"])):
		$error = true;
		array_push($message,"Invalid email.");
	endif;
	// Validate firstname, lastname
	if (!preg_match("/\D+/",$_POST["firstname"]) OR !preg_match("/\D+/",$_POST["lastname"])):
		$error = true;
		array_push($message,"Invalid first or surname.");
	endif;
	if (!$error):
		// Update Patron Data via Alma API
		pushUserNote($_POST["email"]);
		
		// Alternately, copypasta the pushUserNote func. to a separate file and execute in a sub-shell
		// exec("(php -f /path/to/file.php > /dev/null 2>&1 &)");
		
		// Refresh this page after seven seconds
		header("Refresh: 7");
	endif;
endif;

// Function to push user note via Alma API
function pushUserNote($EMAIL) {
	// Base URL
	$ALMA_REQ_URL = "https://api-na.hosted.exlibrisgroup.com/almaws/v1/users/";
	// API KEY
	$ALMA_API_KEY = "API KEY"; 
	// GET PARAMETERS
	$ALMA_GET_PARAM = "?user_id_type=all_unique&view=full&expand=none&apikey=";
	// PUT PARAMETERS
	$ALMA_PUT_PARAM = "?user_id_type=all_unique&send_pin_number_letter=false&recalculate_roles=false&apikey=";
	
	// Initialize cURL GET
	$cr = curl_init();
	$curl_options = array(
		CURLOPT_URL => sprintf("%s%s%s%s",$ALMA_REQ_URL,$EMAIL,$ALMA_GET_PARAM,$ALMA_API_KEY),
		CURLOPT_HTTPGET => true,
		CURLOPT_HTTPHEADER => array("Accept: application/xml"),
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => false
	);
	curl_setopt_array($cr, $curl_options);
	$response = curl_exec($cr);
	curl_close($cr);

	$doc = new DOMDocument();
	$doc->loadXML($response);
	$xpath = new DOMXpath($doc);
	
	// We set the semester year date to the first day before the Fall semester. e.g.: August 13, 2023
	// You'll want to revise this to align with your Library's equipment agreement policy
	$semester = "August 13, 2023";
	
	// This line looks specifically for "Equipemnt Agreement" and the semester. e.g.: Equipment Agreement valid until August 13, 2023
	$note_text = $xpath->query("//note_text/text()[contains(.,\"Equipment Agreement\") and contains(.,\"$semester\")]");
	
	// If this patron doesn't have an existing note, then we add a <user_note> element to XML response
	if ($note_text->length == 0) {
		// Equipment Agreement valid to $semester 
		$user_notes = $xpath->query("//user_notes")->item(0);
		$user_notes_domnode = $user_notes->cloneNode();
		$user_note = new DOMElement("user_note");
		$user_notes->appendChild($user_note);
		$user_note->setAttribute("segment_type","External");
		$user_note->appendChild(new DOMElement("note_type","CIRCULATION"));
		
		// Change the note text to align with your Library's equipment agreement policy
		$user_note->appendChild(new DOMElement("note_text","Equipment Agreement valid to $semester."));
		$user_note->appendChild(new DOMElement("user_viewable","true"));
		$user_note->appendChild(new DOMElement("popup_note","true"));
	
		// Initialize cURL PUT
		$cr = curl_init();
		$curl_options = array(
			CURLOPT_URL => sprintf("%s%s%s%s",$ALMA_REQ_URL,$EMAIL,$ALMA_PUT_PARAM,$ALMA_API_KEY),
			CURLOPT_CUSTOMREQUEST => "PUT",
			CURLOPT_POSTFIELDS => $doc->saveXML(),
			CURLOPT_HTTPHEADER => array("Content-Type: application/xml", "Accept: application/xml"),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false
		);
		curl_setopt_array($cr, $curl_options);
		$response = curl_exec($cr);
		curl_close($cr);
	}
}
?>
<!DOCTYPE html>
<HTML lang="en-US">
<HEAD>
	<META http-equiv="x-ua-compatible" content="IE=edge">
	<META name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">
	<META http-equiv="content-type" content="text/html; charset=utf-8">
	<TITLE>Equipment Agreement</TITLE>
</HEAD>
<BODY>
<?php if ($_POST AND !$error): ?>
	</i>Your Equipment Agreement has been submitted.</i>
<?php elseif ($_POST AND $error): ?>
	<b>Please correct the following errors:</b>
	<ul>
		<?php foreach ($message AS $msg) { print "<li>$msg</li>"; } ?>
	</ul>
<?php else: ?>
	<FORM METHOD="POST" ID="agreement_form">
		<DIV ID="agreement_content">
			<!-- The language for the equipment agreement goes here. -->
		</DIV>
		<DIV ID="agreement_fields">
<?php foreach (array("firstname","lastname") AS $key): ?>
			<LABEL><?php print preg_match("/_/",$key) ? strtoupper(str_replace("_"," ", $key)) : ucfirst($key); ?>:</LABEL>
			<INPUT CLASS="agreement" TYPE="<?php print preg_match("/phone/", $key) ? "tel" : "text"?>" NAME="<?php print $key; ?>" VALUE="<?php !empty($_GET[$key]) AND print $_GET[$key] OR !empty($_GET[$key]) AND print $_GET[$key]; ?>" REQUIRED/>
			<BR/>
<?php endforeach; ?>
			<LABEL>Email:</LABEL>
			<INPUT CLASS="agreement" TYPE="email" NAME="email" VALUE="<?php !empty($_GET["email"]) AND print $_GET["email"]; ?>" PATTERN=".+@domain.edu" REQUIRED/>
			<BR/>
			<INPUT TYPE="submit" NAME="submit" VALUE="Submit" CLASS="inline"/>
			<INPUT TYPE="reset" NAME="cancel" VALUE="Cancel" CLASS="inline" ONCLICK="window.location=''; return false;"/>
		</DIV>
	</FORM>
<?php endif; ?>
</BODY>
</HTML>
