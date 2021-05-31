<?php
/**
 * Remita Payment Class
 *
 * This class can generate RRR, check transaction status either using RRR or transaction id.
 * It can also save RRR details into the database and update them accordingly.
 *
 * A dedicated page listens to payments.
 * You can set a cron job to also fetch payment status and update them equally.
 *
 * @author Ekene Ezeasor <ezeasorekene@unizik.edu.ng>
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @copyright Copyright (c) 2021, Ekene Ezeasor
 * @package RemitaPayments
 * @version 1.0.0
 *
 */

namespace ezeasorekene\NGPaymentGateway;

class RemitaPayments
{

  public $user_id = string;
  public $transaction_id = string;
  public $amount = float;
  public $rrr = int;
  public $payerName = string;
  public $payerEmail = string;
  public $payerPhone = string;
  public $paymentDescription = string;
  public $param = array();
  public $serviceTypeId = int;

  private $merchantId = int;
  private $apiKey;
  private $mode = string;
  private $dbhost = string;
  private $dbuser = string;
  private $dbpass = string;
  private $dbname = string;

  protected $apiHash = string;
  protected $dbconnect;

  private const MERCHANT = 2547916;
  private const APIKEY = "1946";
  private const SERVICETYPEID = 4430731;

  function __construct($mode,$merchant=self::MERCHANT,$apiKey=self::APIKEY,$serviceTypeId=self::SERVICETYPEID)
  {
    $this->setMode($mode);
    $this->merchantId = $merchant;
    $this->apiKey = $apiKey;
    $this->serviceTypeId = $serviceTypeId;
    $this->transaction_id = uniqid(mt_rand());
  }

  /**
   * Generate RRR using given parameters
   * @param array $parameters This is an optional input if the values are already set
   * @return mixed Returns the generated RRR on success or false or failure
   */
  public function generateRRR(array $parameters = [])
  {
    // Check if mode is demo or live
    if ($this->mode=='LIVE') {
      $url = "https://login.remita.net/remita/exapp/api/v1/send/api/echannelsvc/merchant/api/paymentinit";
    } else {
      $url = "https://remitademo.net/remita/exapp/api/v1/send/api/echannelsvc/merchant/api/paymentinit";
    }

    // Create a new cURL resource
    $this->apiHash = hash('SHA512',$this->merchantId.$this->serviceTypeId.$this->transaction_id.$this->amount.$this->apiKey);

    if (!empty($parameters)) {
      $this->param = $parameters;
    } else {
      $this->param =
      array
      (
      	'serviceTypeId' => $this->serviceTypeId,
      	'amount' => $this->amount,
      	'orderId' => $this->transaction_id,
      	'payerName' => $this->payerName,
      	'payerEmail' => $this->payerEmail,
      	'payerPhone' => $this->payerPhone,
      	'description' => $this->paymentDescription
      );
    }

    // Setup request to send json via POST
    $data = $this->param;
    $payload = json_encode($data);


    $curl = curl_init();
    // Attach encoded JSON string to the POST fields
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json","Authorization: remitaConsumerKey={$this->merchantId},remitaConsumerToken={$this->apiHash}"));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    // Execute the POST request
    $result = curl_exec($curl);

    // Troubleshoot and Debug cURL
    // if (curl_exec($curl) === false) {
    //   echo 'Curl error: ' . curl_error($curl); exit;
    // } else {
    //   var_dump($result); exit;
    // }

    // Close cURL resource
    curl_close($curl);

    //Get the response
    $jsonData = substr($result, 7, -1);
    $rrr = json_decode($jsonData, true);

    //Return the result
    if ($rrr['statuscode']==='025') {
      return $rrr['RRR'];
    } else {
      return false;
    }
  }

  /**
   * Check the status of a transaction using RRR
   * @param string $rrr The RRR to check its status
   * @param string $return_type Default is bool. Set to 'array' to return an array
   * @return mixed Return an array of rrr details if $return_type is array
   */
  public function checkRRRStatus(int $rrr=null,$return_type="bool")
  {
    // Check if mode is demo or live
    if ($this->mode=='LIVE') {
      $url = "https://login.remita.net/remita/exapp/api/v1/send/api/echannelsvc";
    } else {
      $url = "https://remitademo.net/remita/exapp/api/v1/send/api/echannelsvc";
    }

    if (isset($rrr)) {
      $this->rrr = $rrr;
    }
    $this->apiHash = hash('SHA512',$this->rrr.$this->apiKey.$this->merchantId);
    $url = $url."/$this->merchantId/$this->rrr/$this->apiHash/status.reg";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type:application/json","Authorization:remitaConsumerKey={$merchantId},remitaConsumerToken={$apiHash}"));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    //Get the response
    $rrr = json_decode($result, true);

    // Troubleshoot
    // var_dump($result);
    // exit;

    //Return the result
    if ($rrr['status']==='00' && $rrr['message']==='Successful') {
      if ($return_type==="array") {
        $details = array(
          'rrr' => $rrr['RRR'],
          'transaction_date' => $rrr['transactiontime'],
          'debit_date' => $rrr['paymentDate'],
          'status' => 'Paid'
        );
        return $details;
      } else {
        return true; //$rrr['message'];
      }
    } else {
      return false; //$rrr['message'];
    }

  }

  /**
   * Generate and set transaction id
   * @param string $prefix
   * @return null
   */
  public function generateTransactionID($prefix=null)
  {
    isset($prefix) ? $prefix = $prefix : $prefix = 'REMITA';
    $this->transaction_id = $prefix.uniqid(mt_rand());
  }

  /**
   * Check the status of transaction using transaction id
   * @param string $transaction_id The transaction ID you want to check its status
   * @param string $return_type Default is bool. Set to 'array' to return an array
   * @return mixed Return an array of rrr details if @$return_type is array
   */
  public function checkTransactionIDStatus($transaction_id=null,$return_type="bool")
  {
    // Check if mode is demo or live
    if ($this->mode=='LIVE') {
      $url = "https://login.remita.net/remita/exapp/api/v1/send/api/echannelsvc";
    } else {
      $url = "https://remitademo.net/remita/exapp/api/v1/send/api/echannelsvc";
    }

    if (isset($transaction_id)) {
      $this->transation_id = $transaction_id;
    }
    $this->apiHash = hash('SHA512',$this->transation_id.$this->apiKey.$this->merchantId);
    $url = $url."/$this->merchantId/$this->transation_id/$this->apiHash/status.reg";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type:application/json","Authorization:remitaConsumerKey={$merchantId},remitaConsumerToken={$apiHash}"));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    //Get the response
    $rrr = json_decode($result, true);

    // Troubleshoot
    // var_dump($result);
    // exit;

    //Return the result
    if ($rrr['status']==='00' && $rrr['message']==='Successful') {
      if ($return_type==="array") {
        $details = array(
          'rrr' => $rrr['RRR'],
          'transaction_date' => $rrr['transactiontime'],
          'debit_date' => $rrr['paymentDate'],
          'status' => 'Paid'
        );
        return $details;
      } else {
        return true; //$rrr['message'];
      }
    } else {
      return false; //$rrr['message'];
    }
  }

  /**
   * Connect to the database to use database related functions
   * @param string $dbhost
   * @param string $dbuser
   * @param string $dbpass
   * @param string $dbname
   * @return null
   */
  public function dbconnect($dbhost,$dbuser,$dbpass,$dbname)
  {
    $this->dbconnect = new mysqli(self::DBHOST, self::DBUSER, self::DBPASS, self::DBNAME);
    if ($this->dbconnect->connect_error) {
      die('Failed to connect to MySQL - ' . $this->dbconnect->connect_error);
    }
  }

  /**
   * Save generated RRR to database for future use
   * @param int $rrr
   * @param string $payment_purpose
   * @return bool
   */
  public function saveGeneratedRRR(int $rrr,$payment_purpose)
  {
    $sql = "INSERT INTO payments (transaction_id,user_id,rrr,payment_purpose,servicetype_id,amount,status,payer_name,payer_email,payer_phone)
    VALUES('$this->transaction_id','$this->user_id','$this->rrr','$payment_purpose','$this->serviceTypeId','$this->amount','Pending','$this->payerName','$this->payerEmail','$this->payerPhone')";
    $result = $this->dbconnect->query($sql) or die($this->dbconnect->error);
    if ($this->dbconnect->affected_rows > 0) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * Update payment status
   * @param array $details
   * @return bool
   */
  public function updatePaymentStatus(array $details)
  {
    $rrr = $details['rrr'];
    $status = $details['status'];
    $transaction_date = $details['transaction_date'];
    isset($details['channel']) ? $channel = $details['channel'] : $channel = 'REMITA';
    $debit_date = $details['debit_date'];
    $sql = "UPDATE payments SET status='$status',transaction_date='$transaction_date',channel='$channel',debit_date='$debit_date' WHERE rrr='$rrr'";
    $result = $this->dbconnect->query($sql);
    if ($this->dbconnect->affected_rows > 0) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * Pay generated RRR
   * @todo create function to pay rrr
   */
  public function payRRR($rrr)
  {
    // To be done
  }

  /**
   * Listen to payments
   * @todo create function to listen to payments when paid
   */
  public function PaymentListener()
  {
    // To be done
  }

  /**
   * Set the user id
   * @param string $user_id
   * @return null
   */
  public function setUserID($user_id)
  {
    $this->user_id = $user_id;
  }

  /**
   * Set the transaction id
   * @param string $transaction_id
   * @return null
   */
  public function setTransactionID($transaction_id)
  {
    $this->transaction_id = $transaction_id;
  }

  /**
   * Set the rrr
   * @param string $rrr
   * @return null
   */
  public function setRRR($rrr)
  {
    $this->rrr = $rrr;
  }

  /**
   * Set the payer name
   * @param string $payerName
   * @return null
   */
  public function setpayerName($payerName)
  {
    $this->payerName = $payerName;
  }

  /**
   * Set the payer email
   * @param string $payerEmail
   * @return null
   */
  public function setpayerEmail($payerEmail)
  {
    $this->payerEmail = $payerEmail;
  }

  /**
   * Set the payer phone
   * @param string $payerPhone
   * @return null
   */
  public function setpayerPhone($payerPhone)
  {
    $this->payerPhone = $payerPhone;
  }

  /**
   * Set the payment description
   * @param string $paymentDescription
   * @return null
   */
  public function setpaymentDescription($paymentDescription)
  {
    $this->paymentDescription = $paymentDescription;
  }

  /**
   * Set the amount
   * @param string $amount
   * @return null
   */
  public function setAmount($amount)
  {
    $this->amount = $amount;
  }

  /**
   * Set the mode of the API to be used
   * @param string $mode
   * @return null
   */
  public function setMode($mode)
  {
    if ($mode==="LIVE" || $mode==="DEMO") {
      $this->mode = $mode;
    } else {
      $this->mode = "DEMO";
    }
  }

  /**
   * Send email notifications
   * @todo create function to use PHPMailer to send email notifications
   */
  public function sendEmail()
  {
    // To be done
  }

}
