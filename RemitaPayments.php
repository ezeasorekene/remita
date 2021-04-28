<?php
class RemitaPayments
{

  public $user_id = string;
  public $transation_id;
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

  protected $apiHash = string;
  protected $dbconnect;

  private const MERCHANT = "2547916";
  private const APIKEY = "1946";
  private const SERVICETYPEID = "4430731";
  private const DBHOST = "dbhost";
  private const DBUSER = "dbuser";
  private const DBPASS = "dbpass";
  private const DBNAME = "dbname";

  function __construct($merchant=self::MERCHANT,$apiKey=self::APIKEY,$serviceTypeId=self::SERVICETYPEID)
  {
    $this->dbconnect = new mysqli(self::DBHOST, self::DBUSER, self::DBPASS, self::DBNAME);
    if ($this->dbconnect->connect_error) {
      die('Failed to connect to MySQL - ' . $this->dbconnect->connect_error);
    }

    $this->merchantId = $merchant;
    $this->apiKey = $apiKey;
    $this->serviceTypeId = $serviceTypeId;
    $this->transaction_id = "CIRMS".uniqid(mt_rand());
  }

  public function generateRRR(array $parameters = [])
  {
    $url = "https://remitademo.net/remita/exapp/api/v1/send/api/echannelsvc/merchant/api/paymentinit";

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

    // Close cURL resource
    curl_close($curl);

    //Get the response
    $jsonData = substr($result, 7, -1);
    $rrr = json_decode($jsonData, true);

    // Troubleshoot
    // var_dump($result);
    // exit;

    //Return the result
    if ($rrr['statuscode']==='025') {
      return $rrr['RRR'];
    } else {
      return false;
    }
  }

  public function checkRRRStatus(int $rrr=null,$return_type="bool")
  {
    if (isset($rrr)) {
      $this->rrr = $rrr;
    }
    $this->apiHash = hash('SHA512',$this->rrr.$this->apiKey.$this->merchantId);
    $url = "https://remitademo.net/remita/exapp/api/v1/send/api/echannelsvc/$this->merchantId/$this->rrr/$this->apiHash/status.reg";
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

  public function generateTransactionID($prefix)
  {
    $this->transaction_id = $prefix.'-'.uniqid(mt_rand());
  }

  public function checkTransactionIDStatus($transation_id)
  {
    // To be done
  }

  public function saveGeneratedRRR($rrr,$payment_purpose,$dept_code)
  {
    $sql = "INSERT INTO payments (transaction_id,reg_number,rrr,payment_purpose,dept_code,servicetype_id,amount,status,payer_name,payer_email,payer_phone)
    VALUES('$this->transaction_id','$this->reg_number','$this->rrr','$payment_purpose','$dept_code','$this->serviceTypeId','$this->amount','Pending','$this->payerName','$this->payerEmail','$this->payerPhone')";
    $result = $this->dbconnect->query($sql) or die($this->dbconnect->error);
    if ($this->dbconnect->affected_rows > 0) {
      return true;
    } else {
      return false;
    }
  }

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

  public function payRRR($rrr)
  {
    // To be done
  }

  public function PaymentListener($value='')
  {
    // To be done
  }

  public function setUserID($user_id)
  {
    $this->user_id = $user_id;
  }

  public function setTransactionID($transaction_id)
  {
    $this->transaction_id = $transaction_id;
  }

  public function setRRR($rrr)
  {
    $this->rrr = $rrr;
  }

  public function setpayerName($payerName)
  {
    $this->payerName = $payerName;
  }

  public function setpayerEmail($payerEmail)
  {
    $this->payerEmail = $payerEmail;
  }

  public function setpayerPhone($payerPhone)
  {
    $this->payerPhone = $payerPhone;
  }

  public function setpaymentDescription($paymentDescription)
  {
    $this->paymentDescription = $paymentDescription;
  }

  public function setAmount($amount)
  {
    $this->amount = $amount;
  }

  public function sendEmail($value='')
  {
    // To be done
  }

}
