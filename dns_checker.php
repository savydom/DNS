 <?php
/**
 * Script in order to compare a set of DNS Records from the current DNS supplier, to a new Name Server
 * A standard array of records are setup, and can be enabled/disabled as required, and altered
 * Then set a domain, and new Name Server to check against, and run the script in CLI PHP
 * ./dns_checker.php
 * 
 * This will create a HTML file in your current folder with details of the checks.
 * 
 * N.B. You must have Net_DNS2 PEAR module installed 'pear install Net_DNS2'
 * 
 * Licence: http://mattclements.mit-license.org/
 */

require_once 'Net/DNS2.php';
if(!class_exists('Net_DNS2_Resolver'))
{
	die("Net_DNS2 is required, please run 'pear install Net_DNS2' to install");
}

//Array of Records to check (enabled = true are checked)
$records_to_check = array(
	array(
		'name' => '',
		'type' => 'A',
		'enabled' => true
	),
	array(
		'name' => 'www',
		'type' => 'A',
		'enabled' => true
	),
	array(
		'name' => 'ftp',
		'type' => 'A',
		'enabled' => false
	),
	array(
		'name' => 'mail',
		'type' => 'A',
		'enabled' => false
	),
	array(
		'name' => 'webmail',
		'type' => 'A',
		'enabled' => false
	),
	array(
		'name' => '',
		'type' => 'MX',
		'enabled' => true
	),
);

//Domain to Check
$domain = "domain.com";

//New Name Server to Check
$ns_server = "???.???.???.???";

$data  = <<< EOF
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <title>DNS Checker</title>
    <!-- Bootstrap core CSS -->
    <link rel="stylesheet" href="http://netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css">
    <style type="text/css">
    body {
    	margin: 0 1em;
    }
    </style>
  </head>
  <body>
EOF;

$data .= "
<h1>Checking ".$domain."</h1>
<table class=\"table table-bordered\">
<thead>
<tr>
	<th>Record Type</th>
	<th>Record Name</th>
	<th>Original NS Response</th>
	<th>New NS Response</th>
	<th>Response</th>
</tr>
</thead>
<tbody>
";

foreach($records_to_check as $record_to_check)
{
	if($record_to_check['enabled']===true)
	{
		$data .= "<tr>";
		$to_check_domain = $domain;
		if($record_to_check['name']!=="")
		{
			$to_check_domain = $record_to_check['name'].".".$domain;
		}

		$original_response = false;
		$new_response = false;

		$data .= "<th>".$record_to_check['type']."</th>";
		$data .= "<th>".$record_to_check['name']."</th>";
		$data .= "<td>";

		try
		{
			$resolver = new Net_DNS2_Resolver(array('nameservers' => array('8.8.8.8')));
			$resp = $resolver->query($to_check_domain, $record_to_check['type']);

			$original_response = array();

			foreach($resp->answer as $response)
			{
				if($resp->answer[0]->type==="MX")
				{
					$data .= $response->exchange;
					if (filter_var($response->exchange, FILTER_VALIDATE_IP))
						$data .= " (".gethostbyaddr($response->exchange).")";
					$original_response[] = $response->exchange;
				}
				elseif($resp->answer[0]->type==="CNAME")
				{
					$data .= $response->cname;
					if (filter_var($response->cname, FILTER_VALIDATE_IP))
						$data .= " (".gethostbyaddr($response->cname).")";
					$original_response[] = $response->cname;
				}
				else
				{
					$data .= $response->address;
					if (filter_var($response->address, FILTER_VALIDATE_IP))
						$data .= " (".gethostbyaddr($response->address).")";
					$original_response[] = $response->address;
				}
				$data .= "<br />";
			}
		}
		catch(Exception $e)
		{
			$data .= "Exception Thrown";
		}

		unset($resolver);
		unset($resp);

		$data .= "</td>";

		$data .= "<td>";

		try
		{
			$resolver = new Net_DNS2_Resolver(array('nameservers' => array($ns_server)));
			$resp = $resolver->query($to_check_domain, $record_to_check['type']);

			$new_response = array();

			foreach($resp->answer as $response)
			{
				if($resp->answer[0]->type==="MX")
				{
					$data .= $response->exchange;
					if (filter_var($response->exchange, FILTER_VALIDATE_IP))
						$data .= " (".gethostbyaddr($response->exchange).")";
					$new_response[] = $response->exchange;
				}
				elseif($resp->answer[0]->type==="CNAME")
				{
					$data .= $response->cname;
					if (filter_var($response->cname, FILTER_VALIDATE_IP))
						$data .= " (".gethostbyaddr($response->cname).")";
					$new_response[] = $response->cname;
				}
				else
				{
					$data .= $response->address;
					if (filter_var($response->address, FILTER_VALIDATE_IP))
						$data .= " (".gethostbyaddr($response->address).")";
					$new_response[] = $response->address;
				}
				$data .= "<br />";
			}
		}
		catch(Exception $e)
		{
			$data .= "Exception Thrown";
		}

		unset($resolver);
		unset($resp);

		$data .= "</td>";

		if(is_array($original_response))
			sort($original_response);

		if(is_array($new_response))
			sort($new_response);

		$data .= "<td>";
		if($original_response!==false && $new_response!==false && (is_array($original_response) || is_array($new_response)))
		{
			$diff = array_diff($original_response,$new_response);

			if(empty($diff))
			{
				$data .= "<span class=\"label label-success\">All OK</span>";
			}
			else
			{
				$data .= "<span class=\"label label-danger\">Error</span>";
			}

		}
		elseif($original_response!==false && $new_response!==false && $original_response===$new_response)
		{
			$data .= "<span class=\"label label-success\">All OK</span>";
		}
		else
		{
			$data .= "<span class=\"label label-danger\">Error</span>";
		}
		$data .= "</td>";
		$data .= "</tr>";
	}
}

$data .= "</tbody></body></html>";

file_put_contents('./NS-Check-'.$domain.'.html',$data);
