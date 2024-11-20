<?php

/**
 * @author     Veronika Pavlisin
 * @version    1.00
 * @date       12.02.2019
 */

ini_set("soap.wsdl_cache_enabled","0");

// model used in web service functions as parameter
class Contact
{
    public $name;
    public $phone_number;
    public $email;
    public $address;
}

function addContact ($contact)
{
    // DB connection
    global $mysqli;
    
    // input parameter check - only name is required
    if (!$contact->name)
    {
        return new SoapFault("Server", store_log('addContact', json_decode(json_encode($contact), true), null, "Incorrect input parameter - name property expected"));
    }
    
    // bulding the query 
    $query = "INSERT INTO contact (name, phone_number, email, address) VALUES (".
                "'".$contact->name."',".
                "'".($contact->phone_number ? $contact->phone_number : "")."',".
                "'".($contact->email ? $contact->email : "")."',".
                "'".($contact->address ? $contact->address : "")."')";
    
    // executing the query 
    $mysqli->query($query);
    
    // store log information
    store_log('addContact', json_decode(json_encode($contact), true), $mysqli->insert_id ? $mysqli->insert_id : 0);
    
    // return identifier of newly created record
    return $mysqli->insert_id ? $mysqli->insert_id : 0;
}

function updateContact ($id, $contact)
{
    // DB connection
    global $mysqli;
    
    // input parameter check
    if (!$id)
    {
        return new SoapFault("Server", store_log('updateContact', array('id' => $id) + json_decode(json_encode($contact), true), null, "Incorrect input parameter - id expected"));
    }
    
    // bulding the query 
    $query = "UPDATE contact SET";
    $delimiter = "";
    if ($contact->name)
    {
        $query .= " name='".$contact->name."'";
        $delimiter = ",";
    }
    if ($contact->phone_number)
    {
        $query .= $delimiter."phone_number='".$contact->phone_number."'";
        $delimiter = ",";
    }
    if ($contact->email)
    {
        $query .= $delimiter."email='".$contact->email."'";
        $delimiter = ",";
    }
    if ($contact->address)
        $query .= $delimiter."address='".$contact->address."'";
    
    $query .= " WHERE id=".$id;
    
    // executing the query 
    $mysqli->query($query);
    
    store_log('updateContact', array('id' => $id) + json_decode(json_encode($contact), true), $mysqli->affected_rows == 1);
    
    // return whether the record was updated
    return $mysqli->affected_rows == 1;
}

/**
 * Web service operation for deleting the Phone book record
 * 
 * @param integer $id identifier of record for deletion
 * @return boolean
 */
function deleteContact ($id)
{
    // DB connection
    global $mysqli;
    
    // input parameter check
    if (!$id)
    {
        return new SoapFault("Server", store_log('deleteContact', array('id' => $id), null, "Incorrect input parameter - id expected"));
    }
    
    // executing the query 
    $mysqli->query("DELETE FROM contact WHERE id=".$id);
    
    // store log information
    store_log('deleteContact', array('id' => $id), $mysqli->affected_rows == 1);
    
    // return whether the record was deleted
    return $mysqli->affected_rows == 1;
}

/**
 * Web service operation for reading all Phone book records
 * 
 * @return array
 */
function allContacts ()
{
    // DB connection
    global $mysqli;

    // fetching the result from query 
    $result = array();
    if ($res = $mysqli->query("SELECT * FROM contact"))
    {
        while ($row = $res->fetch_assoc())
            $result[] = $row;
        $res->close();
    }
    
    // store log information
    store_log('allContacts', array(), $result);
    
    // return resulting array - for simplicity, encoded into JSON string
    return json_encode($result);
}

/**
 * Web service operation for searching the Phone book record by name
 * 
 * @param string $search searched name of contact
 * @return array
 */
function searchContacts ($search)
{
    // DB connection
    global $mysqli;
    
    // input parameter check
    if (!$search)
    {
        return new SoapFault("Server", store_log('searchContacts', array('search' => $search), null, "Incorrect input parameter - searched name expected"));
    }
    
    // fetching the result from query 
    $result = array();
    if ($res = $mysqli->query("SELECT * FROM contact WHERE name LIKE '".$search."%'"))
    {
        while ($row = $res->fetch_assoc())
            $result[] = $row;
        $res->close();
    }
    
    // store log information
    store_log('searchContacts', array('search' => $search), $result);
    
    // return resulting array - for simplicity, encoded into JSON string
    return json_encode($result);
}

/**
 * Stores XML logging information of a request and response to log files
 *
 * @param string $operation name of web service operation
 * @param array $parameters array of input parameters of operation
 * @param mixed $result result of an operation
 * @param mixed $error error message
 * @return mixed
 */
function store_log ($operation, $parameters, $result, $error = false)
{
    // main XML element
    $request = new SimpleXMLElement('<request/>');
    // operation name as attribute
    $request->addAttribute('operation', $operation);
    // logging time
    $request->addAttribute('time', date_format(date_create(), 'Y-m-d H:i:s'));
    // parameters as sub element
    $params = $request->addChild('params');
    if (!empty($parameters))
    foreach ($parameters as $param_name => $param_value)
    {
        $param = $params->addChild('param', $param_value);
        $param->addAttribute('name', $param_name);
    }
    
    // adding request part into request.log 
    $dom = dom_import_simplexml($request);
    error_log($dom->ownerDocument->saveXML($dom->ownerDocument->documentElement)."\r\n", 3, "request.log");

    if ($error)
    {
        // store error that prevented the execution of operation
        $response = $request->addChild('response', $error);
        $response->addAttribute('success', "false");
    }
    else
    {
        $outcome = "false";
        // check result of executed operation and as successful response
        switch ($operation)
        {
        	case 'addContact':
        	    if ($result > 0)
        	    {
        	        // result of successful record creation
        	        $response = $request->addChild('response', "Phone Book record (id=".$result.") created");
        	        $outcome = "true";
        	    }
        	    else
        	    {
        	        $response = $request->addChild('response');
        	    }
        	    break;
        	case 'updateContact':
        	    if ($result)
        	    {
            	    // result of successful record update
        	        $response = $request->addChild('response', "Phone Book record (id=".$parameters['id'].") updated");
        	    }
        	    else
        	    {
        	        $response = $request->addChild('response');
        	    }
        	    $outcome = "true";
        	    break;
        	case 'deleteContact':
        	    if ($result)
        	    {
            	    // result of successful record deletion
        	        $response = $request->addChild('response', "Phone Book record (id=".$parameters['id'].") deleted");
        	    }
        	    else
        	    {
        	        $response = $request->addChild('response');
        	    }
        	    $outcome = "true";
        	    break;
        	case 'allContacts':
        	    $response = $request->addChild('response');
        	    if (!empty($result))
        	    {
            	    // result of all records read
        	        for ($i = 0; $i < count($result); $i++)
        	        {
            	        $contact = $response->addChild('contact');
            	        foreach ($result[$i] as $contact_property => $contact_property_value)
                	        $contact->addChild($contact_property, $contact_property_value);
        	        }
        	    }
        	    $outcome = "true";
        	    break;
        	case 'searchContacts':
        	    if (!empty($result))
        	    {
            	    // result of all records found
        	        $response = $request->addChild('response');
        	        for ($i = 0; $i < count($result); $i++)
        	        {
        	            $contact = $response->addChild('contact');
        	            foreach ($result[$i] as $contact_property => $contact_property_value)
        	                $contact->addChild($contact_property, $contact_property_value);
        	        }
        	    }
        	    else
        	    {
            	    // result of no records found
        	        $response = $request->addChild('response', "No records found");
        	    }
        	    $outcome = "true";
        	    break;
        	default:
        	    $response = $request->addChild('response');
        	    break;
        }
        $response->addAttribute('success', $outcome);
    }

    // adding request and response into response.log 
    $dom = dom_import_simplexml($request);
    error_log($dom->ownerDocument->saveXML($dom->ownerDocument->documentElement)."\r\n", 3, "response.log");
    
    return $error ? $error : true;
}

// initialize DB connection
global $mysqli;
$mysqli = new mysqli('localhost', 'root', '', 'test');

// initialize SOAP Server
$server = new SoapServer("phone_book.wsdl",['classmap'=>['contact'=>'Contact']]);

// register available functions
$server->addFunction('addContact');
$server->addFunction('updateContact');
$server->addFunction('deleteContact');
$server->addFunction('allContacts');
$server->addFunction('searchContacts');

// start handling requests
$server->handle();

// close DB connnection
$mysqli->close();
?>
