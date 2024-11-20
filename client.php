<?php 

/**
 * @author     Veronika Pavlisin
 * @version    1.00
 * @date       12.02.2019
 */

// model used in web service functions as parameter
class Contact
{
    public $name;
    public $phone_number;
    public $email;
    public $address;
}

try {
    // initialize SOAP client
    $context = stream_context_create(array('http' => array('user_agent' => 'PHPSoapClient')));
    $client = new SoapClient('http://localhost:8080/server.php?wsdl', array('stream_context' => $context,'cache_wsdl' => WSDL_CACHE_NONE));
    
    // web service functions calling
    
    $newContact = new Contact();
    $newContact->name = 'Vlado';
    $newContact->phone_number = '+123456';
    $newContact->email = 'vlado@gmail.com';
    $newContact->address = 'Berlin, Germany';
    // adding a contact to Phone Book
    $client->addContact($newContact);
    
    // reading list of all contacts
    $client->allContacts();
    
    // searching for contact starting with Veron
    $client->searchContacts('Veron');
    
    $updateContact = new Contact();
    $newContact->name = 'Veronika';
    $newContact->phone_number = '+123456 / 789';
    
    // updating the name and phone number of a contact
    $client->updateContact(1, $newContact);
    
    // searching for contact starting with Veron
    $client->searchContacts('Veron');
    
    // reading list of all contacts
    $client->allContacts();
    
    // searching for contact - with missing parameter
    $client->searchContacts();
}
catch(Exception $e) {
    echo "Error: ".$e->getMessage();
}
?>
